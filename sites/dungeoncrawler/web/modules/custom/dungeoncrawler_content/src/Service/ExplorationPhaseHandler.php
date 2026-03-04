<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles game actions during the Exploration phase.
 *
 * Exploration is the default game phase where the player moves freely between
 * rooms, interacts with objects and NPCs, and the AI GM narrates discoveries.
 * Time is tracked in 10-minute intervals. Encounters can trigger when entering
 * rooms or interacting with certain entities.
 *
 * Wraps existing services: entity movement, room chat, room state, quest
 * tracking. Does NOT rewrite them — delegates and coordinates.
 */
class ExplorationPhaseHandler implements PhaseHandlerInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\RoomChatService
   */
  protected RoomChatService $roomChatService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\DungeonStateService
   */
  protected DungeonStateService $dungeonStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected CharacterStateService $characterStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGenerationService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\AiGmService
   */
  protected AiGmService $aiGmService;

  /**
   * Constructs an ExplorationPhaseHandler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    RoomChatService $room_chat_service,
    DungeonStateService $dungeon_state_service,
    CharacterStateService $character_state_service,
    NumberGenerationService $number_generation_service,
    AiGmService $ai_gm_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->roomChatService = $room_chat_service;
    $this->dungeonStateService = $dungeon_state_service;
    $this->characterStateService = $character_state_service;
    $this->numberGenerationService = $number_generation_service;
    $this->aiGmService = $ai_gm_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'exploration';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'move',
      'interact',
      'talk',
      'search',
      'transition',
      'set_activity',
      'rest',
      'cast_spell',
      'open_door',
      'open_passage',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateIntent(array $intent, array $game_state, array $dungeon_data): array {
    $type = $intent['type'] ?? '';

    if (!in_array($type, $this->getLegalIntents(), TRUE)) {
      return [
        'valid' => FALSE,
        'reason' => "Action '$type' is not legal during exploration phase.",
      ];
    }

    // Validate actor exists.
    $actor_id = $intent['actor'] ?? NULL;
    if ($actor_id && !$this->findEntityInDungeon($actor_id, $dungeon_data)) {
      return [
        'valid' => FALSE,
        'reason' => "Actor entity '$actor_id' not found in dungeon data.",
      ];
    }

    // Transition-specific: validate the connection exists and is discoverable.
    if ($type === 'transition') {
      $target_room = $intent['params']['target_room_id'] ?? NULL;
      if (!$target_room) {
        return [
          'valid' => FALSE,
          'reason' => 'Room transition requires params.target_room_id.',
        ];
      }
    }

    return ['valid' => TRUE, 'reason' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function processIntent(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $type = $intent['type'] ?? '';
    $actor_id = $intent['actor'] ?? NULL;
    $target_id = $intent['target'] ?? NULL;
    $params = $intent['params'] ?? [];

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    switch ($type) {

      case 'move':
        $result = $this->processMove($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('move', 'exploration', $actor_id, [
          'from' => $params['from_hex'] ?? NULL,
          'to' => $params['to_hex'] ?? NULL,
        ]);
        break;

      case 'interact':
        $result = $this->processInteract($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('interact', 'exploration', $actor_id, [
          'target' => $target_id,
          'interaction' => $params['interaction_type'] ?? 'generic',
        ], NULL, $target_id);
        break;

      case 'talk':
        $result = $this->processTalk($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        $events[] = GameEventLogger::buildEvent('talk', 'exploration', $actor_id, [
          'target' => $target_id,
          'message' => $params['message'] ?? '',
        ], $narration, $target_id);
        break;

      case 'search':
        $result = $this->processSearch($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        // Searching advances time by 10 minutes.
        $this->advanceExplorationTime($game_state, 10);
        $events[] = GameEventLogger::buildEvent('search', 'exploration', $actor_id, [
          'roll' => $result['roll'] ?? NULL,
          'dc' => $result['dc'] ?? NULL,
          'degree' => $result['degree'] ?? NULL,
          'discoveries' => $result['discoveries'] ?? [],
        ], $narration);
        break;

      case 'transition':
        $result = $this->processRoomTransition($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        // Room transition advances time.
        $this->advanceExplorationTime($game_state, 10);

        // AI GM narration for room entry.
        $target_room_id = $params['target_room_id'] ?? NULL;
        $room_data = $this->findRoomInDungeon($target_room_id, $dungeon_data);
        if ($room_data) {
          $first_visit = $this->isFirstVisit($target_room_id, $dungeon_data);
          $gm_narration = $this->aiGmService->narrateRoomEntry($room_data, $dungeon_data, $first_visit, $campaign_id);
          if ($gm_narration) {
            $narration = $gm_narration;
          }
        }

        $events[] = GameEventLogger::buildEvent('room_entered', 'exploration', $actor_id, [
          'from_room' => $game_state['exploration']['previous_room'] ?? NULL,
          'to_room' => $target_room_id,
        ], $narration);

        // Check for encounter trigger on room entry.
        $encounter_check = $this->checkEncounterTrigger($params['target_room_id'] ?? '', $dungeon_data);
        if ($encounter_check['should_trigger']) {
          $phase_transition = [
            'from' => 'exploration',
            'to' => 'encounter',
            'reason' => $encounter_check['reason'] ?? 'Hostile creatures detected!',
            'encounter_context' => $encounter_check['encounter_context'] ?? [],
          ];
          $events[] = GameEventLogger::buildEvent('encounter_triggered', 'exploration', $actor_id, [
            'room_id' => $params['target_room_id'],
            'reason' => $encounter_check['reason'],
          ]);
        }
        break;

      case 'set_activity':
        $activity = $params['activity'] ?? 'search';
        $game_state['exploration']['character_activities'][$actor_id] = $activity;
        $result = ['activity' => $activity];
        $events[] = GameEventLogger::buildEvent('set_activity', 'exploration', $actor_id, [
          'activity' => $activity,
        ]);
        break;

      case 'rest':
        $result = $this->processRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        // Short rest advances 10 minutes, long rest transitions to downtime.
        $rest_type = $params['rest_type'] ?? 'short';
        if ($rest_type === 'long') {
          $phase_transition = [
            'from' => 'exploration',
            'to' => 'downtime',
            'reason' => 'Long rest initiated.',
            'context' => ['rest_type' => 'long'],
          ];
        }
        else {
          $this->advanceExplorationTime($game_state, 10);
        }
        $events[] = GameEventLogger::buildEvent('rest', 'exploration', $actor_id, [
          'rest_type' => $rest_type,
        ]);
        break;

      case 'open_door':
      case 'open_passage':
        $result = $this->processOpenPassage($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent($type, 'exploration', $actor_id, [
          'target' => $target_id,
        ], NULL, $target_id);
        break;

      case 'cast_spell':
        $result = $this->processCastSpell($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;
        $events[] = GameEventLogger::buildEvent('cast_spell', 'exploration', $actor_id, [
          'spell' => $params['spell_name'] ?? 'unknown',
        ], $narration);
        break;

      default:
        return [
          'success' => FALSE,
          'result' => ['error' => "Unknown exploration action: $type"],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ];
    }

    return [
      'success' => TRUE,
      'result' => $result,
      'mutations' => $mutations,
      'events' => $events,
      'phase_transition' => $phase_transition,
      'narration' => $narration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onEnter(array $context, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $game_state['phase'] = 'exploration';
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['encounter_id'] = NULL;

    // Initialize exploration sub-state if not present.
    if (!isset($game_state['exploration'])) {
      $game_state['exploration'] = [
        'time_elapsed_minutes' => 0,
        'character_activities' => [],
        'previous_room' => NULL,
      ];
    }

    return [
      GameEventLogger::buildEvent('phase_entered', 'exploration', NULL, [
        'from_phase' => $context['from_phase'] ?? 'none',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Snapshot exploration state so it can be restored when re-entering.
    // The exploration sub-state persists in game_state.exploration.
    return [
      GameEventLogger::buildEvent('phase_exited', 'exploration', NULL, [
        'time_elapsed' => $game_state['exploration']['time_elapsed_minutes'] ?? 0,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    // In exploration, most actions are always available.
    $actions = ['move', 'interact', 'talk', 'search', 'set_activity', 'rest'];

    // Transition is available if the current room has passable connections.
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;
    if ($active_room_id && !empty($dungeon_data['connections'])) {
      foreach ($dungeon_data['connections'] as $conn) {
        $from_room = $conn['from']['room_id'] ?? NULL;
        $to_room = $conn['to']['room_id'] ?? NULL;
        $passable = $conn['is_passable'] ?? FALSE;
        if ($passable && ($from_room === $active_room_id || $to_room === $active_room_id)) {
          $actions[] = 'transition';
          break;
        }
      }
    }

    return array_unique($actions);
  }

  // =========================================================================
  // Action processors (delegate to existing services where possible).
  // =========================================================================

  /**
   * Process a movement action (free movement during exploration).
   */
  protected function processMove(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $to_hex = $params['to_hex'] ?? NULL;
    if (!$to_hex || !isset($to_hex['q'], $to_hex['r'])) {
      return ['error' => 'Missing to_hex with q,r coordinates.'];
    }

    // Find and update the entity's placement in dungeon_data.
    $entity = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if (!$entity) {
      return ['error' => "Entity $actor_id not found."];
    }

    $from_hex = $entity['placement']['hex'] ?? ['q' => 0, 'r' => 0];
    $entity['placement']['hex'] = ['q' => (int) $to_hex['q'], 'r' => (int) $to_hex['r']];

    // Persist to DB.
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return [
      'moved' => TRUE,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'placement.hex', 'from' => $from_hex, 'to' => $to_hex],
      ],
    ];
  }

  /**
   * Process an interact action (doors, objects, containers).
   */
  protected function processInteract(string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $interaction_type = $params['interaction_type'] ?? 'generic';

    // For now, delegate generic interactions.
    // Future: check for traps, locked doors, containers with loot.
    return [
      'interacted' => TRUE,
      'interaction_type' => $interaction_type,
      'target' => $target_id,
      'mutations' => [],
    ];
  }

  /**
   * Process a talk action (delegates to AI GM via RoomChatService).
   */
  protected function processTalk(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $message = $params['message'] ?? '';
    $room_id = $dungeon_data['active_room_id'] ?? NULL;
    $character_id = $params['character_id'] ?? NULL;

    if (!$room_id) {
      return ['error' => 'No active room set.'];
    }

    // Delegate to the existing RoomChatService for AI GM interaction.
    // The chat service handles: AI prompt building, response parsing,
    // gameplay action extraction, character & room state mutations.
    try {
      $chat_result = $this->roomChatService->postMessage(
        $campaign_id,
        $room_id,
        $message,
        'player',
        $character_id
      );

      return [
        'talked' => TRUE,
        'message' => $message,
        'gm_response' => $chat_result['gm_response'] ?? NULL,
        'narration' => $chat_result['gm_response']['text'] ?? NULL,
        'state_diff' => $chat_result['state_diff'] ?? [],
        'mutations' => $chat_result['mutations'] ?? [],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Talk action failed: @error', ['@error' => $e->getMessage()]);
      return [
        'talked' => FALSE,
        'error' => 'Chat service error.',
        'mutations' => [],
      ];
    }
  }

  /**
   * Process a search action (Perception check to reveal hidden entities).
   */
  protected function processSearch(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Roll Perception check using server-authoritative dice.
    $perception_bonus = $params['perception_bonus'] ?? 0;
    $roll_result = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $roll_result + $perception_bonus;

    // Check against room's search DC (default 15).
    $room = $this->getActiveRoom($dungeon_data);
    $search_dc = $room['gameplay_state']['search_dc'] ?? 15;

    $degree = $this->calculateDegreeOfSuccess($total, $search_dc, $roll_result);

    $discoveries = [];
    // Reveal hidden entities based on degree of success.
    if (in_array($degree, ['critical_success', 'success'])) {
      $discoveries = $this->revealHiddenEntities($dungeon_data, $degree === 'critical_success');
    }

    return [
      'searched' => TRUE,
      'roll' => $roll_result,
      'total' => $total,
      'dc' => $search_dc,
      'degree' => $degree,
      'discoveries' => $discoveries,
      'mutations' => [],
    ];
  }

  /**
   * Process a room transition.
   */
  protected function processRoomTransition(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $target_room_id = $params['target_room_id'] ?? NULL;
    if (!$target_room_id) {
      return ['error' => 'No target room specified.'];
    }

    // Track the previous room for event logging.
    $game_state['exploration']['previous_room'] = $dungeon_data['active_room_id'] ?? NULL;

    // Update active room.
    $dungeon_data['active_room_id'] = $target_room_id;

    // Move the actor entity to the destination room's entry hex.
    $entry_hex = $params['entry_hex'] ?? ['q' => 0, 'r' => 0];
    $entity = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if ($entity) {
      $entity['placement']['hex'] = $entry_hex;
      $entity['placement']['room_id'] = $target_room_id;
    }

    // Persist.
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return [
      'transitioned' => TRUE,
      'from_room' => $game_state['exploration']['previous_room'],
      'to_room' => $target_room_id,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'placement.room_id', 'to' => $target_room_id],
      ],
    ];
  }

  /**
   * Process opening a door or passage.
   */
  protected function processOpenPassage(?string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Find the connection and mark it passable.
    if (!empty($dungeon_data['connections'])) {
      foreach ($dungeon_data['connections'] as &$conn) {
        $conn_id = $conn['id'] ?? NULL;
        if ($conn_id === $target_id) {
          $conn['is_passable'] = TRUE;
          $conn['is_discovered'] = TRUE;
          break;
        }
      }
      unset($conn);
    }

    // Also check for door entities.
    if ($target_id) {
      $entity = &$this->findEntityInDungeon($target_id, $dungeon_data, TRUE);
      if ($entity) {
        $entity['state']['metadata']['passable'] = TRUE;
      }
    }

    $this->persistDungeonData($campaign_id, $dungeon_data);

    return [
      'opened' => TRUE,
      'target' => $target_id,
      'mutations' => [
        ['entity' => $target_id, 'field' => 'passable', 'to' => TRUE],
      ],
    ];
  }

  /**
   * Process a rest action.
   */
  protected function processRest(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $rest_type = $params['rest_type'] ?? 'short';

    if ($rest_type === 'short') {
      // Short rest: refocus, catch breath. 10 minutes.
      return [
        'rested' => TRUE,
        'rest_type' => 'short',
        'mutations' => [],
      ];
    }

    // Long rest handled via phase transition to downtime.
    return [
      'rested' => TRUE,
      'rest_type' => 'long',
      'mutations' => [],
    ];
  }

  /**
   * Process a spell casting action during exploration.
   */
  protected function processCastSpell(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $spell_name = $params['spell_name'] ?? 'unknown';

    // Exploration-mode spell casting (e.g., Detect Magic, Light, Mage Armor).
    // Character spell slot tracking is handled by CharacterStateService.
    // For now, log the intent. The AI GM chat can narrate the effect.
    return [
      'cast' => TRUE,
      'spell' => $spell_name,
      'narration' => NULL,
      'mutations' => [],
    ];
  }

  // =========================================================================
  // Helper methods.
  // =========================================================================

  /**
   * Advances the exploration time tracker.
   */
  protected function advanceExplorationTime(array &$game_state, int $minutes): void {
    if (!isset($game_state['exploration']['time_elapsed_minutes'])) {
      $game_state['exploration']['time_elapsed_minutes'] = 0;
    }
    $game_state['exploration']['time_elapsed_minutes'] += $minutes;
  }

  /**
   * Checks whether entering a room should trigger an encounter.
   */
  protected function checkEncounterTrigger(string $room_id, array $dungeon_data): array {
    // Check if the room has an encounter template that hasn't been triggered.
    $rooms = $dungeon_data['rooms'] ?? [];
    foreach ($rooms as $room) {
      if (($room['room_id'] ?? '') !== $room_id) {
        continue;
      }

      $gameplay_state = $room['gameplay_state'] ?? [];
      $encounter_template = $gameplay_state['encounter_template'] ?? NULL;
      $encounter_triggered = $gameplay_state['encounter_triggered'] ?? FALSE;

      if ($encounter_template && !$encounter_triggered) {
        // Find hostile entities in the room.
        $hostile_entities = [];
        $entities = $dungeon_data['entities'] ?? [];
        foreach ($entities as $entity) {
          $entity_room = $entity['placement']['room_id'] ?? NULL;
          $content_type = $entity['content_type'] ?? '';
          if ($entity_room === $room_id && $content_type === 'creature') {
            $hostile_entities[] = $entity;
          }
        }

        if (!empty($hostile_entities)) {
          return [
            'should_trigger' => TRUE,
            'reason' => $encounter_template['reason'] ?? 'Hostile creatures detected!',
            'encounter_context' => [
              'template' => $encounter_template,
              'enemies' => $hostile_entities,
              'room_id' => $room_id,
            ],
          ];
        }
      }
    }

    return ['should_trigger' => FALSE];
  }

  /**
   * Finds an entity in dungeon_data by instance_id.
   *
   * @param string $entity_id
   *   The entity instance_id.
   * @param array &$dungeon_data
   *   The dungeon_data payload.
   * @param bool $by_reference
   *   If TRUE, returns by reference for mutation.
   *
   * @return array|null
   *   The entity array, or NULL if not found.
   */
  protected function &findEntityInDungeon(string $entity_id, array &$dungeon_data, bool $by_reference = FALSE): ?array {
    $null = NULL;
    if (empty($dungeon_data['entities'])) {
      return $null;
    }

    foreach ($dungeon_data['entities'] as &$entity) {
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
      if ($instance_id === $entity_id) {
        return $entity;
      }
    }

    return $null;
  }

  /**
   * Gets the currently active room from dungeon_data.
   */
  protected function getActiveRoom(array $dungeon_data): ?array {
    $active_id = $dungeon_data['active_room_id'] ?? NULL;
    if (!$active_id || empty($dungeon_data['rooms'])) {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] as $room) {
      if (($room['room_id'] ?? '') === $active_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Reveals hidden entities based on a successful search.
   */
  protected function revealHiddenEntities(array &$dungeon_data, bool $reveal_all = FALSE): array {
    $discoveries = [];
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;

    if (empty($dungeon_data['entities'])) {
      return $discoveries;
    }

    foreach ($dungeon_data['entities'] as &$entity) {
      $entity_room = $entity['placement']['room_id'] ?? NULL;
      $is_hidden = $entity['state']['metadata']['hidden'] ?? FALSE;

      if ($entity_room === $active_room_id && $is_hidden) {
        $entity['state']['metadata']['hidden'] = FALSE;
        $discoveries[] = [
          'instance_id' => $entity['instance_id'] ?? $entity['id'] ?? NULL,
          'content_id' => $entity['content_id'] ?? 'unknown',
          'name' => $entity['name'] ?? 'Unknown object',
        ];

        // Only reveal one on normal success; reveal all on crit.
        if (!$reveal_all) {
          break;
        }
      }
    }
    unset($entity);

    return $discoveries;
  }

  /**
   * Calculates PF2e degree of success.
   */
  protected function calculateDegreeOfSuccess(int $total, int $dc, int $natural_roll): string {
    $diff = $total - $dc;
    if ($diff >= 10 || $natural_roll === 20) {
      return 'critical_success';
    }
    if ($diff >= 0) {
      return 'success';
    }
    if ($diff >= -10 && $natural_roll !== 1) {
      return 'failure';
    }
    return 'critical_failure';
  }

  /**
   * Persists dungeon_data to the database.
   */
  protected function persistDungeonData(int $campaign_id, array $dungeon_data): void {
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist dungeon data: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * Finds a room's data in the dungeon_data payload.
   *
   * @param string|null $room_id
   *   The room ID to find.
   * @param array $dungeon_data
   *   The dungeon data payload.
   *
   * @return array|null
   *   Room data array, or NULL if not found.
   */
  protected function findRoomInDungeon(?string $room_id, array $dungeon_data): ?array {
    if ($room_id === NULL) {
      return NULL;
    }

    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? NULL) === $room_id) {
        return $room;
      }
    }

    return NULL;
  }

  /**
   * Checks if this is the first time the player has entered a given room.
   *
   * @param string $room_id
   *   The room ID.
   * @param array $dungeon_data
   *   The dungeon data payload.
   *
   * @return bool
   *   TRUE if no prior room_entered event exists for this room.
   */
  protected function isFirstVisit(string $room_id, array $dungeon_data): bool {
    foreach ($dungeon_data['event_log'] ?? [] as $event) {
      if (($event['type'] ?? '') === 'room_entered') {
        $to_room = $event['data']['to_room'] ?? NULL;
        if ($to_room === $room_id) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

}
