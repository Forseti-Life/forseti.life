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
   * Narration engine for per-character perception-filtered narration.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NarrationEngine|null
   */
  protected ?NarrationEngine $narrationEngine;

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
    AiGmService $ai_gm_service,
    ?NarrationEngine $narration_engine = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->roomChatService = $room_chat_service;
    $this->dungeonStateService = $dungeon_state_service;
    $this->characterStateService = $character_state_service;
    $this->numberGenerationService = $number_generation_service;
    $this->aiGmService = $ai_gm_service;
    $this->narrationEngine = $narration_engine;
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
      'daily_prepare',
      'treat_wounds',
      'treat_disease',
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

        // Queue interaction for narration.
        $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => $actor_entity['name'] ?? $actor_id,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => sprintf('%s interacts with %s (%s).', $actor_entity['name'] ?? $actor_id, $target_id, $params['interaction_type'] ?? 'generic'),
          'visibility' => 'public',
        ]);
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

        // Queue search roll as mechanical event + search action for narration.
        $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'skill_check_result',
          'speaker' => 'System',
          'speaker_type' => 'system',
          'speaker_ref' => '',
          'content' => sprintf('%s searches the area (Perception %d vs DC %d: %s).', $actor_entity['name'] ?? $actor_id, $result['total'] ?? 0, $result['dc'] ?? 15, $result['degree'] ?? 'unknown'),
          'mechanical_data' => [
            'skill' => 'perception',
            'roll' => $result['roll'] ?? NULL,
            'total' => $result['total'] ?? NULL,
            'dc' => $result['dc'] ?? NULL,
            'degree' => $result['degree'] ?? NULL,
          ],
          'visibility' => 'public',
        ]);
        // If discoveries were made, queue a narration event for them.
        if (!empty($result['discoveries'])) {
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'action',
            'speaker' => $actor_entity['name'] ?? $actor_id,
            'speaker_type' => 'player',
            'speaker_ref' => $actor_id,
            'content' => sprintf('%s discovers: %s', $actor_entity['name'] ?? $actor_id, implode(', ', array_map(fn($d) => $d['name'] ?? $d['id'] ?? 'something', $result['discoveries']))),
            'visibility' => 'public',
          ]);
        }
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

        // Queue room entry for perception-filtered narration.
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => 'GM',
          'speaker_type' => 'gm',
          'speaker_ref' => '',
          'content' => sprintf('The party enters %s.', $room_data['name'] ?? $target_room_id),
          'visibility' => 'public',
          'mechanical_data' => [
            'from_room' => $game_state['exploration']['previous_room'] ?? NULL,
            'to_room' => $target_room_id,
            'first_visit' => $first_visit ?? TRUE,
          ],
        ], $target_room_id);

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

          // Queue encounter trigger event.
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'action',
            'speaker' => 'GM',
            'speaker_type' => 'gm',
            'speaker_ref' => '',
            'content' => $encounter_check['reason'] ?? 'Hostile creatures detected!',
            'visibility' => 'public',
          ], $target_room_id);
        }
        break;

      case 'set_activity':
        // REQ 2292-2300: Set a character's exploration activity (persists each move).
        $activity = $params['activity'] ?? 'search';
        $legal_activities = [
          'avoid_notice', 'defend', 'detect_magic', 'follow_expert',
          'hustle', 'scout', 'investigate', 'repeat_spell', 'search',
        ];
        if (!in_array($activity, $legal_activities, TRUE)) {
          return [
            'success' => FALSE,
            'result' => ['error' => "Unknown exploration activity: $activity"],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
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

        // Queue spell cast for narration.
        $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => $actor_entity['name'] ?? $actor_id,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => sprintf('%s casts %s.', $actor_entity['name'] ?? $actor_id, $params['spell_name'] ?? 'a spell'),
          'visibility' => 'public',
          'mechanical_data' => [
            'spell_name' => $params['spell_name'] ?? 'unknown',
            'spell_level' => $params['spell_level'] ?? NULL,
          ],
        ]);
        break;

      case 'daily_prepare':
        // REQ 2304-2305: Daily preparation — prepare spells, channel focus, etc.
        // Takes 1 hour. Restores focus points and marks daily abilities as ready.
        $result = $this->processDailyPrepare($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('daily_prepare', 'exploration', $actor_id, [
          'prepared' => $result['prepared'] ?? [],
        ]);
        break;

      // -----------------------------------------------------------------------
      // REQ 1553–1563: Treat Wounds [Exploration, 10 min, Trained, healer's tools]
      // -----------------------------------------------------------------------
      case 'treat_wounds': {
        $result = $this->processTreatWounds($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $this->advanceExplorationTime($game_state, 10);
        $events[] = GameEventLogger::buildEvent('treat_wounds', 'exploration', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'healed' => $result['healed'] ?? 0,
        ], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // REQ 1563–1568: Treat Disease [Downtime, 8 hrs, Trained, healer's tools]
      // -----------------------------------------------------------------------
      case 'treat_disease': {
        $result = $this->processTreatDisease($actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        // 8 hours of effort is tracked; downtime phase handles the rest period.
        $events[] = GameEventLogger::buildEvent('treat_disease', 'exploration', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'upgraded' => $result['upgraded'] ?? FALSE,
        ], NULL, $target_id);
        break;
      }

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

    // Queue phase entry for perception-filtered narration.
    $from_phase = $context['from_phase'] ?? 'none';
    if ($from_phase !== 'none') {
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'action',
        'speaker' => 'GM',
        'speaker_type' => 'gm',
        'speaker_ref' => '',
        'content' => $from_phase === 'encounter'
          ? 'The encounter ends. The party returns to exploration.'
          : 'Exploration begins.',
        'visibility' => 'public',
        'mechanical_data' => ['from_phase' => $from_phase],
      ]);
    }

    return [
      GameEventLogger::buildEvent('phase_entered', 'exploration', NULL, [
        'from_phase' => $from_phase,
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
    $speaker = 'Player';

    if ($actor_id) {
      $actor_entity = $this->findEntityInDungeon($actor_id, $dungeon_data);
      $speaker = $actor_entity['state']['metadata']['display_name']
        ?? $actor_entity['name']
        ?? $actor_id;
    }

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
        $speaker,
        $message,
        'player',
        $character_id
      );

      if (!empty($chat_result['dungeon_data']) && is_array($chat_result['dungeon_data'])) {
        $dungeon_data = $chat_result['dungeon_data'];
        $game_state = $dungeon_data['game_state'] ?? $game_state;
      }

      return [
        'talked' => TRUE,
        'message' => $message,
        'gm_response' => $chat_result['gm_response'] ?? NULL,
        'narration' => $chat_result['gm_response']['message'] ?? ($chat_result['gm_response']['text'] ?? NULL),
        'npc_interjections' => $chat_result['npc_interjections'] ?? [],
        'state_diff' => $chat_result['state_diff'] ?? [],
        'combat_transition' => $chat_result['combat_transition'] ?? NULL,
        'canonical_actions' => $chat_result['canonical_actions'] ?? [],
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

    // Auto-create psychology profiles for NPCs in the new room.
    $room_entities = [];
    foreach ($dungeon_data['entities'] ?? [] as $ent) {
      $ent_room = $ent['placement']['room_id'] ?? '';
      if ($ent_room === $target_room_id) {
        $room_entities[] = $ent;
      }
    }
    // Also check room-level entities if stored differently.
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? $room['id'] ?? '') === $target_room_id) {
        foreach ($room['entities'] ?? [] as $ent) {
          $room_entities[] = $ent;
        }
        break;
      }
    }
    if ($room_entities) {
      try {
        $this->roomChatService->ensureNpcProfiles($campaign_id, $room_entities);
      }
      catch (\Exception $e) {
        $this->logger->warning('Auto-profile creation failed on room entry: @err', ['@err' => $e->getMessage()]);
      }
    }

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
   * Calculates travel speed in feet per minute, modified by terrain (REQ 2290-2291).
   *
   * Speed = base_speed × terrain_multiplier × 10 (10 min/move action assumed).
   * Hustle activity: multiplier ×2 but applies fatigue after 30 min.
   */
  public function calculateTravelSpeed(int $base_speed, string $terrain = 'normal', string $activity = 'search'): array {
    $terrain_multipliers = [
      'normal'   => 1.0,
      'difficult' => 0.5,
      'greater_difficult' => 0.25,
      'rubble'   => 0.5,
      'crowd'    => 0.5,
    ];
    $multiplier = $terrain_multipliers[$terrain] ?? 1.0;

    $hustle = ($activity === 'hustle');
    if ($hustle) {
      $multiplier *= 2.0;
    }

    $feet_per_minute = $base_speed * $multiplier;

    return [
      'base_speed' => $base_speed,
      'terrain' => $terrain,
      'multiplier' => $multiplier,
      'feet_per_minute' => $feet_per_minute,
      'hustle' => $hustle,
      'fatigue_warning' => $hustle ? 'Hustle causes fatigue after 30 minutes.' : NULL,
    ];
  }

  /**
   * Processes daily preparation (REQ 2304-2305).
   * Takes 1 hour. Restores focus points; marks daily abilities as ready.
   */
  protected function processDailyPrepare(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $prepared = [];

    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
        if ($iid === $actor_id) {
          // Restore focus points to max.
          if (isset($entity['state']['focus_points'])) {
            $max_focus = $entity['stats']['focus_points_max'] ?? 3;
            $entity['state']['focus_points'] = $max_focus;
            $prepared[] = 'focus_points';
          }

          // Mark daily abilities as ready.
          if (isset($entity['state']['daily_abilities'])) {
            foreach ($entity['state']['daily_abilities'] as &$ability) {
              $ability['used'] = FALSE;
            }
            unset($ability);
            $prepared[] = 'daily_abilities';
          }

          // Record prepare time (REQ 2305: takes 1 hour).
          $entity['state']['last_daily_prepare'] = $game_state['exploration']['time_elapsed_minutes'] ?? 0;
          $prepared[] = 'spells_prepared';
          break;
        }
      }
      unset($entity);
    }

    // Daily prepare takes 1 hour.
    $this->advanceExplorationTime($game_state, 60);

    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist daily prepare: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'prepared' => $prepared,
      'time_cost_minutes' => 60,
      'mutations' => [],
    ];
  }

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

  // =========================================================================
  // NarrationEngine bridge.
  // =========================================================================

  /**
   * Queue a game event through the NarrationEngine for perception filtering.
   *
   * Silently skips if NarrationEngine is not available.
   *
   * @param int $campaign_id
   * @param array $dungeon_data
   * @param array $event
   *   Event array matching NarrationEngine::queueRoomEvent() format.
   * @param string|null $room_id
   *   Override room ID. NULL uses active_room_id.
   *
   * @return array
   *   NarrationEngine result, or empty array if engine unavailable.
   */
  protected function queueNarrationEvent(int $campaign_id, array $dungeon_data, array $event, ?string $room_id = NULL): array {
    if (!$this->narrationEngine) {
      return [];
    }

    $dungeon_id = $dungeon_data['dungeon_id'] ?? $dungeon_data['id'] ?? 0;
    $room_id = $room_id ?? ($dungeon_data['active_room_id'] ?? '');
    $present_characters = NarrationEngine::buildPresentCharacters($dungeon_data, $room_id);

    try {
      return $this->narrationEngine->queueRoomEvent(
        $campaign_id,
        $dungeon_id,
        $room_id,
        $event,
        $present_characters
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('NarrationEngine queue failed: @err', ['@err' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Processes Treat Wounds exploration activity.
   *
   * REQ 1553–1562: 10-min activity, Trained Medicine + healer's tools.
   * DC/HP restored table: Trained DC 15/2d8, Expert DC 20/2d8+10,
   * Master DC 30/2d8+30, Legendary DC 40/2d8+50.
   * Crit success = double HP. Crit fail = 1d8 damage.
   * 1-hour immunity per target tracked on dungeon_data entity state.
   *
   * @return array
   *   Keys: degree, healed, damage, dc, error (optional), mutations.
   */
  protected function processTreatWounds(string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return ['error' => 'Treat Wounds requires Trained Medicine.', 'degree' => NULL, 'healed' => 0, 'mutations' => []];
    }
    if (empty($params['has_healers_tools'])) {
      return ['error' => 'Treat Wounds requires healer\'s tools.', 'degree' => NULL, 'healed' => 0, 'mutations' => []];
    }

    $effective_target = $target_id ?? $actor_id;

    // REQ 1562: 1-hour immunity per target.
    $now_minutes = $game_state['exploration']['time_elapsed_minutes'] ?? 0;
    foreach ($dungeon_data['entities'] ?? [] as &$entity) {
      if (($entity['entity_id'] ?? $entity['id'] ?? '') === $effective_target) {
        $last_treated = $entity['state']['last_treated_wounds_at'] ?? NULL;
        if ($last_treated !== NULL && ($now_minutes - $last_treated) < 60) {
          $remaining = 60 - ($now_minutes - $last_treated);
          return ['error' => "Target cannot benefit from Treat Wounds for {$remaining} more minutes.", 'degree' => NULL, 'healed' => 0, 'mutations' => []];
        }
        break;
      }
    }
    unset($entity);

    // DC and healing table (rank: 1=Trained, 2=Expert, 3=Master, 4=Legendary).
    $dc_table   = [1 => 15, 2 => 20, 3 => 30, 4 => 40];
    $hp_bonus   = [1 => 0,  2 => 10, 3 => 30, 4 => 50];
    $rank_key   = min(4, max(1, $med_rank));
    $dc         = (int) ($params['override_dc'] ?? $dc_table[$rank_key]);
    $med_bonus  = (int) ($params['medicine_bonus'] ?? 0);
    $item_bonus = !empty($params['is_improvised_tools']) ? -2 : 0;

    $d20   = $this->numberGenerationService->rollPathfinderDie(20);
    $d8a   = $this->numberGenerationService->rollPathfinderDie(8);
    $d8b   = $this->numberGenerationService->rollPathfinderDie(8);
    $total = $d20 + $med_bonus + $item_bonus;

    // Degree of success (inline — ExplorationPhaseHandler has no combatCalculator).
    if ($d20 === 20 || $total >= $dc + 10) {
      $degree = 'critical_success';
    }
    elseif ($d20 === 1 || $total < $dc - 9) {
      $degree = 'critical_failure';
    }
    elseif ($total >= $dc) {
      $degree = 'success';
    }
    else {
      $degree = 'failure';
    }

    $healed = 0;
    $damage = 0;
    $mutations = [];

    if ($degree === 'critical_success') {
      $healed = (($d8a + $d8b) + $hp_bonus[$rank_key]) * 2;
    }
    elseif ($degree === 'success') {
      $healed = ($d8a + $d8b) + $hp_bonus[$rank_key];
    }
    elseif ($degree === 'critical_failure') {
      // REQ 1561: 1d8 damage instead of healing.
      $damage = $this->numberGenerationService->rollPathfinderDie(8);
    }

    // Record immunity timestamp on entity state.
    foreach ($dungeon_data['entities'] as &$entity) {
      if (($entity['entity_id'] ?? $entity['id'] ?? '') === $effective_target) {
        $entity['state']['last_treated_wounds_at'] = $now_minutes;
        $mutations[] = ['type' => 'entity_state', 'entity_id' => $effective_target, 'state' => $entity['state']];
        break;
      }
    }
    unset($entity);

    return [
      'degree'   => $degree,
      'healed'   => $healed,
      'damage'   => $damage,
      'dc'       => $dc,
      'd20'      => $d20,
      'total'    => $total,
      'mutations' => $mutations,
    ];
  }

  /**
   * Processes Treat Disease downtime activity.
   *
   * REQ 1563–1568: Requires Trained Medicine + healer's tools.
   * On success/crit-success, target's next disease save is one degree better.
   * Can only be applied once per disease per rest period per target.
   *
   * @return array
   *   Keys: degree, upgraded (bool), dc, error (optional), mutations.
   */
  protected function processTreatDisease(string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $med_rank = (int) ($params['medicine_proficiency_rank'] ?? 0);
    if ($med_rank < 1) {
      return ['error' => 'Treat Disease requires Trained Medicine.', 'degree' => NULL, 'upgraded' => FALSE, 'mutations' => []];
    }
    if (empty($params['has_healers_tools'])) {
      return ['error' => 'Treat Disease requires healer\'s tools.', 'degree' => NULL, 'upgraded' => FALSE, 'mutations' => []];
    }

    $effective_target = $target_id ?? $actor_id;
    $disease_id = $params['disease_id'] ?? 'disease';

    // REQ 1567: Once per disease per rest period per target.
    $disease_key = $effective_target . ':' . $disease_id;
    if (!empty($game_state['disease_treated'][$disease_key])) {
      return ['error' => 'Already treated this disease for this target during this rest period.', 'degree' => NULL, 'upgraded' => FALSE, 'mutations' => []];
    }

    $dc         = (int) ($params['disease_dc'] ?? 15);
    $med_bonus  = (int) ($params['medicine_bonus'] ?? 0);
    $item_bonus = !empty($params['is_improvised_tools']) ? -2 : 0;

    $d20   = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $med_bonus + $item_bonus;

    if ($d20 === 20 || $total >= $dc + 10) {
      $degree = 'critical_success';
    }
    elseif ($d20 === 1 || $total < $dc - 9) {
      $degree = 'critical_failure';
    }
    elseif ($total >= $dc) {
      $degree = 'success';
    }
    else {
      $degree = 'failure';
    }

    $upgraded = in_array($degree, ['critical_success', 'success'], TRUE);
    if ($upgraded) {
      // REQ 1565: Next disease save gets one degree better (checked by save handler).
      $game_state['disease_treated'][$disease_key] = TRUE;
    }

    return [
      'degree'   => $degree,
      'upgraded' => $upgraded,
      'dc'       => $dc,
      'd20'      => $d20,
      'total'    => $total,
      'mutations' => [],
    ];
  }

}
