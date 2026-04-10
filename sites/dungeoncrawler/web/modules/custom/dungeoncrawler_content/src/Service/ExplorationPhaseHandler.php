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
   * AC-003: Maps each exploration activity to its initiative skill.
   * AC-005: Also used when computing surprise (Avoid Notice → Stealth).
   */
  protected const ACTIVITY_INITIATIVE_SKILLS = [
    'avoid_notice'   => 'stealth',
    'defend'         => 'perception',
    'detect_magic'   => 'perception',
    'follow_expert'  => 'perception',
    'hustle'         => 'athletics',
    'investigate'    => 'perception',
    'repeat_spell'   => 'perception',
    'scout'          => 'perception',
    'search'         => 'perception',
    'sense_direction' => 'survival',
  ];

  /**
   * AC-002: Hustle causes fatigue after this many hustle-minutes elapsed.
   */
  protected const HUSTLE_FATIGUE_MINUTES = 10;

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
   * @var \Drupal\dungeoncrawler_content\Service\KnowledgeAcquisitionService
   */
  protected KnowledgeAcquisitionService $knowledgeAcquisition;

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
    ?NarrationEngine $narration_engine = NULL,
    ?KnowledgeAcquisitionService $knowledge_acquisition = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->roomChatService = $room_chat_service;
    $this->dungeonStateService = $dungeon_state_service;
    $this->characterStateService = $character_state_service;
    $this->numberGenerationService = $number_generation_service;
    $this->aiGmService = $ai_gm_service;
    $this->narrationEngine = $narration_engine;
    $this->knowledgeAcquisition = $knowledge_acquisition
      ?? new KnowledgeAcquisitionService(
        $database,
        $character_state_service,
        new IdentifyMagicService(new DcAdjustmentService()),
        new LearnASpellService(new DcAdjustmentService()),
        new DcAdjustmentService()
      );
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
      // REQ 1591–1594, 2329: Recall Knowledge [1 action, Secret].
      'recall_knowledge',
      // REQ for Occultism/Religion: Decipher Writing, Identify Magic, Learn a Spell.
      'decipher_writing',
      'identify_magic',
      'learn_a_spell',
      // AC-003, AC-005: Prepared spell assignment and Refocus.
      'prepare_spell',
      'refocus',
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
        $encounter_check = $this->checkEncounterTrigger($params['target_room_id'] ?? '', $dungeon_data, $game_state);
        if ($encounter_check['should_trigger']) {
          // AC-001/AC-005: Transition sets time_unit to rounds; snapshot activity skills.
          $game_state['exploration']['time_unit'] = 'rounds';
          $encounter_ctx = $encounter_check['encounter_context'] ?? [];
          $encounter_ctx['initiative_skills'] = $encounter_check['initiative_skills'] ?? [];
          $encounter_ctx['surprised_enemies'] = $encounter_check['surprised_enemies'] ?? [];
          // Deactivate exploration activities when combat begins.
          $game_state['exploration']['pre_encounter_activities'] = $game_state['exploration']['character_activities'] ?? [];
          $game_state['exploration']['character_activities'] = [];
          $phase_transition = [
            'from' => 'exploration',
            'to' => 'encounter',
            'reason' => $encounter_check['reason'] ?? 'Hostile creatures detected!',
            'encounter_context' => $encounter_ctx,
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
          'sense_direction',
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

      // -----------------------------------------------------------------------
      // REQ 1591–1594, 2329: Recall Knowledge [1 action, Secret]
      // -----------------------------------------------------------------------
      case 'recall_knowledge': {
        // Use provided DC or compute via RecallKnowledgeService.
        if (!empty($params['dc'])) {
          $dc_rk = (int) $params['dc'];
        }
        else {
          $rk_svc = new RecallKnowledgeService(new DcAdjustmentService());
          $dc_result_rk = $rk_svc->computeDc(
            $params['subject_type'] ?? 'general',
            (int) ($params['level'] ?? 0),
            $params['rarity'] ?? 'common',
            (int) ($params['spell_rank'] ?? 0),
            $params['availability'] ?? 'trained'
          );
          $dc_rk = $dc_result_rk['dc'];
        }

        $skill_used_rk = $params['skill_used'] ?? 'arcana';
        $skill_bonus_rk = (int) ($params['skill_bonus'] ?? 0);

        // REQ 2329: Block re-attempts on same target until new info is found.
        $attempt_key_rk = $actor_id . ':' . ($target_id ?? 'general');
        if (!empty($game_state['recall_knowledge_attempts'][$attempt_key_rk])) {
          return ['success' => FALSE, 'result' => ['error' => 'Cannot re-attempt Recall Knowledge on the same target without new information.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }
        $game_state['recall_knowledge_attempts'][$attempt_key_rk] = TRUE;

        $d20_rk = $this->numberGenerationService->rollPathfinderDie(20);
        $total_rk = $d20_rk + $skill_bonus_rk;
        $degree_rk = $this->calculateDegreeOfSuccess($total_rk, $dc_rk, $d20_rk);

        switch ($degree_rk) {
          case 'critical_success':
            $player_msg_rk = 'You recall detailed information about the subject.';
            $info_rk = $params['known_info'] ?? NULL;
            $bonus_rk = $params['bonus_detail'] ?? NULL;
            break;

          case 'success':
            $player_msg_rk = 'You recall accurate information about the subject.';
            $info_rk = $params['known_info'] ?? NULL;
            $bonus_rk = NULL;
            break;

          case 'failure':
            $player_msg_rk = 'You fail to recall anything useful.';
            $info_rk = NULL;
            $bonus_rk = NULL;
            break;

          case 'critical_failure':
          default:
            // REQ 1594: Crit fail returns false info presented as truthful.
            $player_msg_rk = 'You recall information about the subject.';
            $info_rk = $params['false_info'] ?? NULL;
            $bonus_rk = NULL;
            break;
        }

        $result = [
          'degree' => $degree_rk,
          'skill_used' => $skill_used_rk,
          'dc' => $dc_rk,
          'd20' => $d20_rk,
          'total' => $total_rk,
          'player_facing_message' => $player_msg_rk,
          'info' => $info_rk,
          'bonus_detail' => $bonus_rk,
          'secret' => TRUE,
        ];
        $events[] = GameEventLogger::buildEvent('recall_knowledge', 'exploration', $actor_id, ['skill_used' => $skill_used_rk, 'degree' => $degree_rk], NULL, $target_id);
        break;
      }

      // -----------------------------------------------------------------------
      // Decipher Writing [Exploration, Secret, Trained] (dc-cr-decipher-identify-learn)
      // Skills: Arcana (arcane/esoteric), Occultism (metaphysical/occult),
      //         Religion (religious/divine), Society (coded/legal/historical).
      // Timing: 1 min/page standard; 60 min/page for ciphers.
      // Degrees: Crit Success = full meaning; Success = true meaning (coded = summary);
      //          Failure = blocked + –2 circumstance retry penalty; Crit Fail = false.
      // -----------------------------------------------------------------------
      case 'decipher_writing': {
        $dw_params = array_merge($params, [
          'text_id'       => $target_id ?? ($params['text_id'] ?? 'text_unknown'),
          'skill_used'    => $params['skill_used'] ?? 'society',
          'skill_bonus'   => (int) ($params['skill_bonus'] ?? 0),
        ]);
        $result_dw = $this->knowledgeAcquisition->processDecipherWriting(
          (string) $actor_id, $dw_params
        );
        $this->advanceExplorationTime($game_state, $result_dw['time_cost_minutes'] ?? 1);
        $result = $result_dw;
        $events[] = GameEventLogger::buildEvent(
          'decipher_writing', 'exploration', $actor_id,
          ['degree' => $result_dw['degree'], 'skill_used' => $result_dw['skill_used'] ?? NULL, 'is_false' => $result_dw['is_false']],
          NULL, $target_id
        );
        break;
      }

      // -----------------------------------------------------------------------
      // Identify Magic [Exploration, Trained] (dc-cr-decipher-identify-learn)
      // Skills: Arcana (arcane), Nature (primal), Occultism (occult), Religion (divine).
      // Wrong-tradition: +5 DC penalty (not blocked).
      // Degrees: Crit Success = full ID + bonus fact; Success = full ID;
      //          Failure = 1-day block same item; Crit Fail = false ID (secret).
      // -----------------------------------------------------------------------
      case 'identify_magic': {
        $im_params = array_merge($params, [
          'item_id'     => $target_id ?? ($params['item_id'] ?? 'item_unknown'),
          'skill_used'  => $params['skill_used'] ?? 'arcana',
          'skill_bonus' => (int) ($params['skill_bonus'] ?? 0),
        ]);
        $result_im = $this->knowledgeAcquisition->processIdentifyMagic(
          (string) $actor_id, $im_params
        );
        $this->advanceExplorationTime($game_state, $result_im['time_cost_minutes'] ?? 10);
        $result = $result_im;
        $events[] = GameEventLogger::buildEvent(
          'identify_magic', 'exploration', $actor_id,
          ['degree' => $result_im['degree'], 'tradition_match' => $result_im['tradition_match'] ?? TRUE, 'is_false' => $result_im['is_false'] ?? FALSE],
          NULL, $target_id
        );
        break;
      }

      // -----------------------------------------------------------------------
      // Learn a Spell [Exploration, Trained] (dc-cr-decipher-identify-learn)
      // Cost: spell_rank × 10 gp (deducted immediately; refunded on Failure).
      // Degrees: Crit Success = learn + refund 50%; Success = learn;
      //          Failure = NOT learned, NO cost; Crit Fail = not learned + cost lost.
      // -----------------------------------------------------------------------
      case 'learn_a_spell': {
        $las_actor_entity = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        $las_entity_val   = $las_actor_entity ?: [];
        $las_params = array_merge($params, [
          'spell_id'    => $target_id ?? ($params['spell_id'] ?? 'spell_unknown'),
          'skill_used'  => $params['skill_used'] ?? 'arcana',
          'skill_bonus' => (int) ($params['skill_bonus'] ?? 0),
        ]);
        $result_las = $this->knowledgeAcquisition->processLearnASpell(
          (string) $actor_id,
          (string) $campaign_id,
          $las_entity_val,
          $las_params
        );
        $this->advanceExplorationTime($game_state, $result_las['time_cost_minutes'] ?? 60);
        $result = $result_las;
        $events[] = GameEventLogger::buildEvent(
          'learn_a_spell', 'exploration', $actor_id,
          ['degree' => $result_las['degree'], 'spell_learned' => $result_las['spell_learned'] ?? FALSE, 'gp_spent' => $result_las['gp_spent'] ?? 0],
          NULL, $target_id
        );
        break;
      }

      // -----------------------------------------------------------------------
      // AC-003: Prepare Spells [Exploration, part of daily_prepare]
      // Allows a prepared caster to assign spells to specific slot levels.
      // -----------------------------------------------------------------------
      case 'prepare_spell': {
        $entity_ps = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        if (!$entity_ps) {
          return ['success' => FALSE, 'result' => ['error' => 'Character not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $casting_type_ps = $entity_ps['stats']['casting_type'] ?? 'spontaneous';
        if ($casting_type_ps !== 'prepared') {
          return ['success' => FALSE, 'result' => ['error' => 'Only prepared casters can prepare spells in advance.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        // params['prepared_spells']: {slot_level: [spell_name, ...], ...}
        $new_prepared_ps = $params['prepared_spells'] ?? [];
        if (!isset($entity_ps['state'])) {
          $entity_ps['state'] = [];
        }
        $entity_ps['state']['prepared_spells'] = $new_prepared_ps;

        $this->persistDungeonData($campaign_id, $dungeon_data);
        $events[] = GameEventLogger::buildEvent('prepare_spell', 'exploration', $actor_id, ['slot_count' => count($new_prepared_ps)]);
        break;
      }

      // -----------------------------------------------------------------------
      // AC-007: Refocus [Exploration, 10 minutes]
      // Restores 1 Focus Point (up to max 3).
      // -----------------------------------------------------------------------
      case 'refocus': {
        $entity_rf = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
        if (!$entity_rf) {
          return ['success' => FALSE, 'result' => ['error' => 'Character not found.'], 'mutations' => [], 'events' => [], 'phase_transition' => NULL, 'narration' => NULL];
        }

        $fp_max_rf = (int) ($entity_rf['stats']['focus_points_max'] ?? 3);
        $fp_current_rf = (int) ($entity_rf['state']['focus_points'] ?? 0);

        if ($fp_current_rf >= $fp_max_rf) {
          $result = ['focus_points' => $fp_current_rf, 'restored' => 0, 'message' => 'Focus pool already full.'];
        }
        else {
          $fp_new_rf = min($fp_max_rf, $fp_current_rf + 1);
          if (!isset($entity_rf['state'])) {
            $entity_rf['state'] = [];
          }
          $entity_rf['state']['focus_points'] = $fp_new_rf;
          $this->persistDungeonData($campaign_id, $dungeon_data);
          $result = ['focus_points' => $fp_new_rf, 'restored' => 1];
        }

        // Refocus takes 10 minutes.
        $this->advanceExplorationTime($game_state, 10);
        $events[] = GameEventLogger::buildEvent('refocus', 'exploration', $actor_id, ['focus_points' => $result['focus_points']]);
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
        'time_unit' => 'minutes',
        'character_activities' => [],
        'hustle_minutes' => [],
        'previous_room' => NULL,
      ];
    }
    // AC-001: Always ensure time_unit = minutes (e.g. returning from encounter).
    $game_state['exploration']['time_unit'] = 'minutes';
    unset($game_state['round']);

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

    $mutations = [
      ['entity' => $actor_id, 'field' => 'placement.hex', 'from' => $from_hex, 'to' => $to_hex],
    ];
    $result = [
      'moved' => TRUE,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'mutations' => $mutations,
    ];

    $activity = $game_state['exploration']['character_activities'][$actor_id] ?? 'search';

    // AC-002: While Searching, each hex moved (≈ 10 ft) triggers a Perception check.
    if ($activity === 'search') {
      $perception_bonus = $entity['stats']['perception'] ?? ($entity['state']['skills']['perception'] ?? 0);
      $roll = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $roll + (int) $perception_bonus;
      $room = $this->getActiveRoom($dungeon_data);
      $search_dc = $room['gameplay_state']['search_dc'] ?? 15;
      $degree = $this->calculateDegreeOfSuccess($total, $search_dc, $roll);
      $discoveries = [];
      if (in_array($degree, ['critical_success', 'success'], TRUE)) {
        $discoveries = $this->revealHiddenEntities($dungeon_data, $degree === 'critical_success');
      }
      $result['search_on_move'] = [
        'roll' => $roll,
        'total' => $total,
        'dc' => $search_dc,
        'degree' => $degree,
        'discoveries' => $discoveries,
      ];
    }

    // AC-002: Hustle doubles speed; apply fatigue after HUSTLE_FATIGUE_MINUTES.
    if ($activity === 'hustle') {
      if (!isset($game_state['exploration']['hustle_minutes'][$actor_id])) {
        $game_state['exploration']['hustle_minutes'][$actor_id] = 0;
      }
      // Each move in Hustle counts as 1 exploration minute.
      $game_state['exploration']['hustle_minutes'][$actor_id]++;
      $hustle_elapsed = $game_state['exploration']['hustle_minutes'][$actor_id];
      if ($hustle_elapsed >= self::HUSTLE_FATIGUE_MINUTES
        && empty($entity['state']['conditions']['fatigued'])) {
        $entity['state']['conditions']['fatigued'] = TRUE;
        $result['fatigue_applied'] = TRUE;
        $result['hustle_minutes_elapsed'] = $hustle_elapsed;
        $mutations[] = ['entity' => $actor_id, 'field' => 'state.conditions.fatigued', 'from' => FALSE, 'to' => TRUE];
      }
      $result['speed_bonus'] = 2.0;
      $result['hustle_minutes_elapsed'] = $hustle_elapsed;
    }

    // AC-004: Resolve light level at destination hex.
    $result['visibility'] = $this->resolveCharacterVisibility($entity, $to_hex, $dungeon_data);

    $result['mutations'] = $mutations;

    // Persist to DB.
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return $result;
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
      // AC-007: Short rest allows Refocus — restore focus points via refocus action.
      // Short rest itself (10 min) does not automatically restore focus; player must
      // take the Refocus exploration activity. This case is a fallback catch-breath rest.
      return [
        'rested' => TRUE,
        'rest_type' => 'short',
        'mutations' => [],
      ];
    }

    // AC-001: Long rest (8 hours) restores all spell slots to maximum.
    $entity_lr = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if ($entity_lr) {
      if (!isset($entity_lr['state'])) {
        $entity_lr['state'] = [];
      }
      // Restore all spell slot 'used' counts to 0.
      if (!empty($entity_lr['state']['spell_slots'])) {
        foreach ($entity_lr['state']['spell_slots'] as $level => &$slot) {
          $slot['used'] = 0;
        }
        unset($slot);
      }
      // AC-007: Long rest also restores focus pool to max.
      $fp_max_lr = (int) ($entity_lr['stats']['focus_points_max'] ?? 0);
      if ($fp_max_lr > 0) {
        $entity_lr['state']['focus_points'] = $fp_max_lr;
      }
      $this->persistDungeonData($campaign_id, $dungeon_data);
    }

    return [
      'rested' => TRUE,
      'rest_type' => 'long',
      'spell_slots_restored' => TRUE,
      'mutations' => [],
    ];
  }

  /**
   * Process a spell casting action during exploration.
   */
  protected function processCastSpell(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $spell_name = $params['spell_name'] ?? 'unknown';
    $spell_level = (int) ($params['spell_level'] ?? 0);
    $cast_at_level = (int) ($params['cast_at_level'] ?? $spell_level);
    $is_cantrip = !empty($params['is_cantrip']);
    $is_focus_spell = !empty($params['is_focus_spell']);
    $spell_tradition = $params['spell_tradition'] ?? NULL;

    $entity_ep = &$this->findEntityInDungeon($actor_id, $dungeon_data, TRUE);
    if (!$entity_ep) {
      return ['cast' => FALSE, 'error' => 'Character not found.', 'mutations' => [], 'narration' => NULL];
    }

    // AC-002: Tradition validation.
    $char_tradition_ep = $entity_ep['stats']['spellcasting_tradition'] ?? NULL;
    if ($spell_tradition && $char_tradition_ep && $spell_tradition !== $char_tradition_ep) {
      return ['cast' => FALSE, 'error' => "Spell tradition '{$spell_tradition}' does not match '{$char_tradition_ep}'.", 'mutations' => [], 'narration' => NULL];
    }

    // AC-006: Cantrips never consume slots.
    if ($is_cantrip) {
      return ['cast' => TRUE, 'spell' => $spell_name, 'is_cantrip' => TRUE, 'narration' => NULL, 'mutations' => []];
    }

    // AC-007: Focus spells consume 1 Focus Point.
    if ($is_focus_spell) {
      $fp_ep = (int) ($entity_ep['state']['focus_points'] ?? 0);
      if ($fp_ep < 1) {
        return ['cast' => FALSE, 'error' => 'No Focus Points remaining.', 'mutations' => [], 'narration' => NULL];
      }
      $entity_ep['state']['focus_points'] = $fp_ep - 1;
      $this->persistDungeonData($campaign_id, $dungeon_data);
      return ['cast' => TRUE, 'spell' => $spell_name, 'is_focus_spell' => TRUE, 'focus_points_remaining' => $fp_ep - 1, 'narration' => NULL, 'mutations' => []];
    }

    // Slot-consuming spell.
    $slot_level_ep = $cast_at_level > 0 ? $cast_at_level : $spell_level;
    if ($slot_level_ep < 1) {
      $slot_level_ep = 1;
    }
    $slot_key_ep = (string) $slot_level_ep;
    $slots_ep = $entity_ep['state']['spell_slots'] ?? [];
    $slot_data_ep = $slots_ep[$slot_key_ep] ?? ['max' => 0, 'used' => 0];
    $avail_ep = max(0, (int) ($slot_data_ep['max'] ?? 0) - (int) ($slot_data_ep['used'] ?? 0));
    if ($avail_ep < 1) {
      return ['cast' => FALSE, 'error' => "No level-{$slot_level_ep} spell slots remaining.", 'mutations' => [], 'narration' => NULL];
    }

    // AC-003: Prepared casters must have spell prepared.
    $casting_type_ep = $entity_ep['stats']['casting_type'] ?? 'spontaneous';
    if ($casting_type_ep === 'prepared') {
      $prepared_ep = $entity_ep['state']['prepared_spells'][$slot_key_ep] ?? [];
      if (!in_array($spell_name, $prepared_ep, TRUE)) {
        return ['cast' => FALSE, 'error' => "'{$spell_name}' is not prepared in a level-{$slot_level_ep} slot.", 'mutations' => [], 'narration' => NULL];
      }
    }

    // Deduct slot.
    $entity_ep['state']['spell_slots'][$slot_key_ep]['used'] = (int) ($slot_data_ep['used'] ?? 0) + 1;
    $this->persistDungeonData($campaign_id, $dungeon_data);

    return [
      'cast' => TRUE,
      'spell' => $spell_name,
      'spell_level' => $spell_level,
      'cast_at_level' => $slot_level_ep,
      'heightened' => $slot_level_ep > $spell_level,
      'slots_remaining' => $avail_ep - 1,
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
      'fatigue_warning' => $hustle ? 'Hustle causes fatigue after 10 minutes.' : NULL,
    ];
  }

  /**
   * Processes daily preparation (REQ 2304-2305).
   * Takes 1 hour. Restores focus points, spell slots; marks daily abilities ready.
   * AC-001: Restores all spell slots to max.
   * AC-003: Stores prepared_spells from params for prepared casters.
   */
  protected function processDailyPrepare(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $prepared = [];

    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));
        if ($iid === $actor_id) {
          if (!isset($entity['state'])) {
            $entity['state'] = [];
          }

          // AC-001: Restore all spell slots to max.
          if (!empty($entity['state']['spell_slots'])) {
            foreach ($entity['state']['spell_slots'] as $level => &$slot) {
              $slot['used'] = 0;
            }
            unset($slot);
            $prepared[] = 'spell_slots';
          }

          // Restore focus points to max.
          $max_focus = (int) ($entity['stats']['focus_points_max'] ?? 0);
          if ($max_focus > 0) {
            $entity['state']['focus_points'] = $max_focus;
            $prepared[] = 'focus_points';
          }

          // AC-003: Store prepared spells for prepared casters.
          $casting_type_dp = $entity['stats']['casting_type'] ?? 'spontaneous';
          if ($casting_type_dp === 'prepared' && !empty($params['prepared_spells'])) {
            $entity['state']['prepared_spells'] = $params['prepared_spells'];
            $prepared[] = 'prepared_spells';
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
   *
   * AC-003: Enriches encounter_context with per-character initiative skills.
   * AC-005: Computes surprised enemies for characters using Avoid Notice.
   */
  protected function checkEncounterTrigger(string $room_id, array $dungeon_data, array $game_state = []): array {
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
          // AC-003: Build per-character initiative skill map from current activities.
          $character_activities = $game_state['exploration']['character_activities'] ?? [];
          $initiative_skills = [];
          foreach ($character_activities as $char_id => $char_activity) {
            $initiative_skills[$char_id] = self::ACTIVITY_INITIATIVE_SKILLS[$char_activity] ?? 'perception';
          }

          // AC-005: Compute surprised enemies (Avoid Notice: Stealth vs enemy Perception).
          $surprised_enemy_ids = [];
          foreach ($character_activities as $char_id => $char_activity) {
            if ($char_activity !== 'avoid_notice') {
              continue;
            }
            // Find the player entity to get their Stealth bonus.
            $player_entity = NULL;
            foreach ($dungeon_data['entities'] ?? [] as $ent) {
              $eid = $ent['entity_instance_id'] ?? ($ent['instance_id'] ?? ($ent['id'] ?? NULL));
              if ($eid === $char_id) {
                $player_entity = $ent;
                break;
              }
            }
            $stealth_bonus = (int) ($player_entity['stats']['stealth']
              ?? $player_entity['state']['skills']['stealth']
              ?? 0);
            $stealth_roll = $this->numberGenerationService->rollPathfinderDie(20) + $stealth_bonus;
            foreach ($hostile_entities as $enemy) {
              $enemy_id = $enemy['entity_instance_id'] ?? ($enemy['instance_id'] ?? ($enemy['id'] ?? NULL));
              if ($enemy_id === NULL) {
                continue;
              }
              $perception_bonus = (int) ($enemy['stats']['perception'] ?? 0);
              $enemy_roll = $this->numberGenerationService->rollPathfinderDie(20) + $perception_bonus;
              if ($enemy_roll < $stealth_roll) {
                $surprised_enemy_ids[] = $enemy_id;
              }
            }
          }

          return [
            'should_trigger' => TRUE,
            'reason' => $encounter_template['reason'] ?? 'Hostile creatures detected!',
            'initiative_skills' => $initiative_skills,
            'surprised_enemies' => array_values(array_unique($surprised_enemy_ids)),
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

  /**
   * AC-004: Resolves what a character can see at a given hex.
   *
   * Uses dungeon_data['light_sources'] (bright_radius / dim_radius in feet)
   * and the character's vision type (darkvision / low_light_vision / normal).
   *
   * @param array $entity        The moving entity (for vision type).
   * @param array $hex           Destination hex {'q', 'r'}.
   * @param array $dungeon_data  Dungeon data payload.
   * @return array               Keys: light_level, can_see (bool), vision_type.
   */
  protected function resolveCharacterVisibility(array $entity, array $hex, array $dungeon_data): array {
    $light_level = $this->resolveLightLevel($hex, $dungeon_data);

    $greater_darkvision = !empty($entity['stats']['greater_darkvision'])
      || !empty($entity['state']['senses']['greater_darkvision']);
    $darkvision = !empty($entity['stats']['darkvision'])
      || !empty($entity['state']['senses']['darkvision']);
    $low_light   = !empty($entity['stats']['low_light_vision'])
      || !empty($entity['state']['senses']['low_light_vision']);

    if ($greater_darkvision) {
      $vision_type = 'greater_darkvision';
    }
    elseif ($darkvision) {
      $vision_type = 'darkvision';
    }
    elseif ($low_light) {
      $vision_type = 'low_light_vision';
    }
    else {
      $vision_type = 'normal';
    }

    $can_see = match ($light_level) {
      'bright' => TRUE,
      'dim'    => $vision_type !== 'normal',
      'dark'   => in_array($vision_type, ['darkvision', 'greater_darkvision'], TRUE),
      default  => TRUE,
    };

    return [
      'light_level' => $light_level,
      'can_see'     => $can_see,
      'vision_type' => $vision_type,
    ];
  }

  /**
   * AC-004: Resolves the effective light level at a hex position.
   *
   * Mirrors CombatEngine::resolveLightLevel() for use during exploration.
   * dungeon_data['light_sources'] = [['hex'=>{'q','r'},'bright_radius'=>ft,'dim_radius'=>ft],…]
   * Fallback: room ambient lighting → bright.
   *
   * @param array $hex          Target hex {'q', 'r'}.
   * @param array $dungeon_data Dungeon data payload.
   * @return string             'bright'|'dim'|'dark'.
   */
  protected function resolveLightLevel(array $hex, array $dungeon_data): string {
    foreach ($dungeon_data['light_sources'] ?? [] as $source) {
      if (!isset($source['hex'])) {
        continue;
      }
      $dq = (int) $hex['q'] - (int) $source['hex']['q'];
      $dr = (int) $hex['r'] - (int) $source['hex']['r'];
      $ds = -$dq - $dr;
      $dist = (int) max(abs($dq), abs($dr), abs($ds));
      // Radii given in feet; 5 ft = 1 hex.
      $bright_hexes = (int) ceil(($source['bright_radius'] ?? 0) / 5);
      $dim_hexes    = (int) ceil(($source['dim_radius'] ?? $bright_hexes * 2) / 5);
      if ($dist <= $bright_hexes) {
        return 'bright';
      }
      if ($dist <= $dim_hexes) {
        return 'dim';
      }
    }
    // Fall back to active room ambient lighting.
    $active_room_id = $dungeon_data['active_room_id'] ?? NULL;
    foreach ($dungeon_data['rooms'] ?? [] as $room) {
      if (($room['room_id'] ?? '') === $active_room_id) {
        return $room['lighting'] ?? $room['ambient_light'] ?? 'bright';
      }
    }
    return 'bright';
  }

}
