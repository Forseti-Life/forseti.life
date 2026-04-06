<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handles game actions during the Encounter (combat) phase.
 *
 * Wraps the existing CombatEngine, ActionProcessor, and related services.
 * Enforces PF2e encounter rules: initiative, turn order, 3-action economy,
 * MAP, degree of success, conditions, HP tracking.
 *
 * Also handles NPC auto-play by delegating to EncounterAiIntegrationService.
 */
class EncounterPhaseHandler implements PhaseHandlerInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEngine
   */
  protected CombatEngine $combatEngine;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ActionProcessor
   */
  protected ActionProcessor $actionProcessor;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected CombatEncounterStore $encounterStore;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected HPManager $hpManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected ConditionManager $conditionManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator
   */
  protected CombatCalculator $combatCalculator;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NumberGenerationService
   */
  protected NumberGenerationService $numberGenerationService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService
   */
  protected EncounterAiIntegrationService $encounterAiService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\RulesEngine
   */
  protected RulesEngine $rulesEngine;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\AiGmService
   */
  protected AiGmService $aiGmService;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\NpcPsychologyService
   */
  protected NpcPsychologyService $psychologyService;

  /**
   * Narration engine for per-character perception-filtered narration.
   *
   * @var \Drupal\dungeoncrawler_content\Service\NarrationEngine|null
   */
  protected ?NarrationEngine $narrationEngine;

  /**
   * Constructs an EncounterPhaseHandler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CombatEngine $combat_engine,
    ActionProcessor $action_processor,
    CombatEncounterStore $encounter_store,
    HPManager $hp_manager,
    ConditionManager $condition_manager,
    CombatCalculator $combat_calculator,
    NumberGenerationService $number_generation_service,
    EncounterAiIntegrationService $encounter_ai_service,
    RulesEngine $rules_engine,
    EventDispatcherInterface $event_dispatcher,
    AiGmService $ai_gm_service,
    ConfigFactoryInterface $config_factory,
    NpcPsychologyService $psychology_service = NULL,
    ?NarrationEngine $narration_engine = NULL
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->combatEngine = $combat_engine;
    $this->actionProcessor = $action_processor;
    $this->encounterStore = $encounter_store;
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
    $this->combatCalculator = $combat_calculator;
    $this->numberGenerationService = $number_generation_service;
    $this->encounterAiService = $encounter_ai_service;
    $this->rulesEngine = $rules_engine;
    $this->eventDispatcher = $event_dispatcher;
    $this->aiGmService = $ai_gm_service;
    $this->configFactory = $config_factory;
    $this->psychologyService = $psychology_service ?? new NpcPsychologyService($database, $logger_factory);
    $this->narrationEngine = $narration_engine;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'encounter';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'strike',
      'stride',
      'cast_spell',
      'interact',
      'talk',
      'end_turn',
      'delay',
      'delay_reenter',
      'ready',
      'reaction',
      'aid',
      'aid_setup',
      'crawl',
      'drop_prone',
      'escape',
      'leap',
      'release',
      'seek',
      'sense_motive',
      'stand',
      'step',
      'take_cover',
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
        'reason' => "Action '$type' is not legal during encounter phase.",
      ];
    }

    $encounter_id = $game_state['encounter_id'] ?? NULL;
    if (!$encounter_id) {
      return [
        'valid' => FALSE,
        'reason' => 'No active encounter.',
      ];
    }

    // Validate it's the actor's turn (except for reactions and talk).
    if (!in_array($type, ['reaction', 'talk'])) {
      $actor_id = $intent['actor'] ?? NULL;
      $current_turn = $game_state['turn'] ?? [];
      $current_entity = $current_turn['entity'] ?? NULL;

      if ($actor_id && $current_entity && $actor_id !== $current_entity) {
        return [
          'valid' => FALSE,
          'reason' => "It is not $actor_id's turn. Current turn: $current_entity.",
        ];
      }
    }

    // Validate action economy.
    if (in_array($type, ['strike', 'stride', 'cast_spell', 'interact'])) {
      $actions_remaining = $game_state['turn']['actions_remaining'] ?? 0;
      $action_cost = $this->getActionCost($type, $intent['params'] ?? []);
      if ($actions_remaining < $action_cost) {
        return [
          'valid' => FALSE,
          'reason' => "Not enough actions remaining ($actions_remaining) for $type (costs $action_cost).",
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
    $encounter_id = $game_state['encounter_id'] ?? NULL;

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    switch ($type) {

      case 'strike':
        $result = $this->processStrike($encounter_id, $actor_id, $target_id, $params, $game_state);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;

        // Consume 1 action.
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $game_state['turn']['attacks_this_turn'] = ($game_state['turn']['attacks_this_turn'] ?? 0) + 1;

        $events[] = GameEventLogger::buildEvent('strike', 'encounter', $actor_id, [
          'target' => $target_id,
          'roll' => $result['roll'] ?? NULL,
          'total' => $result['total'] ?? NULL,
          'dc' => $result['ac'] ?? NULL,
          'degree' => $result['degree'] ?? NULL,
          'damage' => $result['damage'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ], $narration, $target_id);

        // Queue strike for perception-filtered narration.
        $attacker_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
        $target_name = $this->resolveEntityName($target_id, $game_state, $dungeon_data);
        $degree_text = $result['degree'] ?? 'unknown';
        $damage_val = $result['damage'] ?? 0;
        $strike_desc = match ($degree_text) {
          'critical_success' => sprintf('%s critically strikes %s for %d damage!', $attacker_name, $target_name, $damage_val),
          'success' => sprintf('%s strikes %s for %d damage.', $attacker_name, $target_name, $damage_val),
          'failure' => sprintf('%s swings at %s but misses.', $attacker_name, $target_name),
          'critical_failure' => sprintf('%s fumbles an attack at %s!', $attacker_name, $target_name),
          default => sprintf('%s attacks %s.', $attacker_name, $target_name),
        };
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => $attacker_name,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => $strike_desc,
          'visibility' => 'public',
          'mechanical_data' => [
            'attack_roll' => $result['roll'] ?? NULL,
            'total' => $result['total'] ?? NULL,
            'ac' => $result['ac'] ?? NULL,
            'degree' => $degree_text,
            'damage' => $damage_val,
            'weapon' => $params['weapon'] ?? NULL,
          ],
        ]);
        // Also queue mechanical damage event if hit.
        if ($damage_val > 0) {
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'damage_applied',
            'speaker' => 'System',
            'speaker_type' => 'system',
            'speaker_ref' => '',
            'content' => sprintf('%s takes %d damage.', $target_name, $damage_val),
            'mechanical_data' => [
              'target' => $target_id,
              'damage' => $damage_val,
              'damage_type' => $result['damage_type'] ?? 'physical',
            ],
            'visibility' => 'public',
          ]);
        }

        // Check for encounter end (all enemies defeated).
        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
        break;

      case 'stride':
        $result = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

        $events[] = GameEventLogger::buildEvent('stride', 'encounter', $actor_id, [
          'from' => $params['from_hex'] ?? NULL,
          'to' => $params['to_hex'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'cast_spell':
        $spell_name = $params['spell_name'] ?? 'unknown';
        $action_cost = $params['action_cost'] ?? 2;
        $result = $this->processCastSpell($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - $action_cost);

        $events[] = GameEventLogger::buildEvent('cast_spell', 'encounter', $actor_id, [
          'spell' => $spell_name,
          'action_cost' => $action_cost,
          'round' => $game_state['round'] ?? NULL,
        ], $result['narration'] ?? NULL, $target_id);

        // Queue spell cast for narration.
        $caster_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
        $spell_target_name = $target_id ? $this->resolveEntityName($target_id, $game_state, $dungeon_data) : NULL;
        $spell_desc = $spell_target_name
          ? sprintf('%s casts %s targeting %s.', $caster_name, $spell_name, $spell_target_name)
          : sprintf('%s casts %s.', $caster_name, $spell_name);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => $caster_name,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => $spell_desc,
          'visibility' => 'public',
          'mechanical_data' => [
            'spell_name' => $spell_name,
            'spell_level' => $params['spell_level'] ?? NULL,
            'action_cost' => $action_cost,
            'target' => $target_id,
          ],
        ]);

        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
        break;

      case 'interact':
        $result = $this->processInteract($encounter_id, $actor_id, $target_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];

        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);

        $events[] = GameEventLogger::buildEvent('interact', 'encounter', $actor_id, [
          'target' => $target_id,
          'interaction' => $params['interaction_type'] ?? 'generic',
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      case 'talk':
        // Talk is a free action in encounter mode.
        $message = $params['message'] ?? '';
        $result = [
          'talked' => TRUE,
          'message' => $message,
        ];
        $events[] = GameEventLogger::buildEvent('talk', 'encounter', $actor_id, [
          'message' => $message,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);

        // Queue talk as speech event for immediate narration.
        $talker_name = $this->resolveEntityName($actor_id, $game_state, $dungeon_data);
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'speech',
          'speaker' => $talker_name,
          'speaker_type' => 'player',
          'speaker_ref' => $actor_id,
          'content' => $message,
          'language' => $params['language'] ?? 'Common',
          'volume' => $params['volume'] ?? 'normal',
          'visibility' => 'public',
        ]);
        break;

      case 'end_turn':
        $result = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $narration = $result['narration'] ?? NULL;

        $events[] = GameEventLogger::buildEvent('end_turn', 'encounter', $actor_id, [
          'round' => $game_state['round'] ?? NULL,
          'turn_index' => $game_state['turn']['index'] ?? NULL,
        ], $narration);

        // End turn may trigger NPC auto-play, which generates additional events.
        if (!empty($result['npc_events'])) {
          $events = array_merge($events, $result['npc_events']);
        }

        // If round changed, add round event.
        if (!empty($result['new_round'])) {
          // AI GM narration for new round.
          $round_narration = $this->aiGmService->narrateRoundStart(
            (int) $result['new_round'],
            $game_state,
            $dungeon_data,
            $campaign_id
          );

          $events[] = GameEventLogger::buildEvent('round_start', 'encounter', NULL, [
            'round' => $result['new_round'],
          ], $round_narration);

          // Queue round start for perception-filtered narration.
          $this->queueNarrationEvent($campaign_id, $dungeon_data, [
            'type' => 'action',
            'speaker' => 'GM',
            'speaker_type' => 'gm',
            'speaker_ref' => '',
            'content' => sprintf('Round %d begins.', (int) $result['new_round']),
            'visibility' => 'public',
            'mechanical_data' => ['round' => (int) $result['new_round']],
          ]);
        }

        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
        break;

      case 'delay':
        // REQ 2193-2195: Store remaining actions, set delayed flag.
        $delay_remaining = $game_state['turn']['actions_remaining'] ?? 0;
        $game_state['turn']['delayed'] = TRUE;
        $game_state['turn']['delayed_actions_remaining'] = $delay_remaining;
        $game_state['turn']['actions_remaining'] = 0;
        $result = ['delayed' => TRUE, 'remaining_actions' => $delay_remaining];
        $events[] = GameEventLogger::buildEvent('delay', 'encounter', $actor_id, [
          'remaining_actions' => $delay_remaining,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'delay_reenter':
        // REQ 2193: Re-enter initiative after delay, restoring stored actions.
        if (empty($game_state['turn']['delayed'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Not currently delayed.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $reenter_actions = $game_state['turn']['delayed_actions_remaining'] ?? 0;
        $game_state['turn']['delayed'] = FALSE;
        $game_state['turn']['actions_remaining'] = $reenter_actions;
        $result = ['reentered' => TRUE, 'actions_restored' => $reenter_actions];
        $events[] = GameEventLogger::buildEvent('delay_reenter', 'encounter', $actor_id, [
          'actions_restored' => $reenter_actions,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'ready':
        // REQ 2203-2205: 2-action activity; store trigger action + MAP at time of readying.
        $ready_action = $params['ready_action'] ?? NULL;
        $ready_trigger = $params['ready_trigger'] ?? NULL;
        if (!$ready_action || !$ready_trigger) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'ready_action and ready_trigger are required.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        // REQ 2205: Cannot Ready a free action that already has its own trigger.
        if (!empty($params['is_triggered_free_action'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Cannot Ready a free action that already has a trigger.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $game_state['turn']['ready'] = [
          'action' => $ready_action,
          'trigger' => $ready_trigger,
          'map_at_ready' => $game_state['turn']['attacks_this_turn'] ?? 0,
        ];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 2);
        $result = ['readied' => TRUE, 'action' => $ready_action, 'trigger' => $ready_trigger];
        $events[] = GameEventLogger::buildEvent('ready', 'encounter', $actor_id, [
          'action' => $ready_action,
          'trigger' => $ready_trigger,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'stand':
        // REQ 2213: Remove prone condition. 1 action.
        $enc_stand = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_stand = $enc_stand ? $this->findEncounterParticipantByEntityId($enc_stand, $actor_id) : NULL;
        if ($ptcp_stand) {
          $pid_stand = (int) $ptcp_stand['id'];
          foreach ($this->conditionManager->getActiveConditions($pid_stand, $encounter_id) as $cid => $crow) {
            if ($crow['condition_type'] === 'prone') {
              $this->conditionManager->removeCondition($pid_stand, $cid, $encounter_id);
              break;
            }
          }
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['stood' => TRUE];
        $events[] = GameEventLogger::buildEvent('stand', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;

      case 'drop_prone':
        // REQ 2196: Apply prone condition. 1 action.
        $enc_dp = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_dp = $enc_dp ? $this->findEncounterParticipantByEntityId($enc_dp, $actor_id) : NULL;
        if ($ptcp_dp) {
          $pid_dp = (int) $ptcp_dp['id'];
          $this->conditionManager->applyCondition($pid_dp, 'prone', 1, 'persistent', 'drop_prone', $encounter_id);
        }
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['prone' => TRUE];
        $events[] = GameEventLogger::buildEvent('drop_prone', 'encounter', $actor_id, ['round' => $game_state['round'] ?? NULL]);
        break;

      case 'step':
        // REQ 2214-2215: Move exactly 5 ft without triggering AoO. 1 action.
        if (empty($params['to_hex'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Missing to_hex.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $step_move = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $step_move['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $game_state['turn']['last_move_type'] = 'step';
        $result = ['stepped' => TRUE, 'to_hex' => $params['to_hex']];
        $events[] = GameEventLogger::buildEvent('step', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'crawl':
        // REQ 2192: Move 5 ft while prone; requires Speed >= 10. 1 action.
        if (empty($params['to_hex'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Missing to_hex.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $enc_crawl = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_crawl = $enc_crawl ? $this->findEncounterParticipantByEntityId($enc_crawl, $actor_id) : NULL;
        if (!$ptcp_crawl) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Participant not found.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $pid_crawl = (int) $ptcp_crawl['id'];
        if (!$this->conditionManager->hasCondition($pid_crawl, 'prone', $encounter_id)) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Must be prone to Crawl.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        if ((int) ($ptcp_crawl['speed'] ?? 25) < 10) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Speed is too low to Crawl (requires Speed >= 10 ft).'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $crawl_move = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $crawl_move['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['crawled' => TRUE, 'to_hex' => $params['to_hex']];
        $events[] = GameEventLogger::buildEvent('crawl', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'leap':
        // REQ 2201-2202: Jump up to 10 ft (Speed 15+) or 15 ft (Speed 30+). 1 action.
        if (empty($params['to_hex'])) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Missing to_hex.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $enc_leap = $this->encounterStore->loadEncounter($encounter_id);
        $ptcp_leap = $enc_leap ? $this->findEncounterParticipantByEntityId($enc_leap, $actor_id) : NULL;
        $leap_speed = (int) ($ptcp_leap['speed'] ?? 25);
        $max_leap_ft = $leap_speed >= 30 ? 15 : 10;
        $leap_move = $this->processStride($encounter_id, $actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $leap_move['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['leaped' => TRUE, 'to_hex' => $params['to_hex'], 'max_leap_ft' => $max_leap_ft];
        $events[] = GameEventLogger::buildEvent('leap', 'encounter', $actor_id, [
          'to' => $params['to_hex'],
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'escape':
        // REQ 2197-2199: Roll vs grapple DC; attack trait applies MAP. 1 action.
        $result = $this->processEscape($encounter_id, $actor_id, $params, $game_state);
        $mutations = $result['mutations'] ?? [];
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $events[] = GameEventLogger::buildEvent('escape', 'encounter', $actor_id, [
          'degree' => $result['degree'] ?? NULL,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'seek':
        // REQ 2207-2210: Secret Perception roll vs each target's Stealth DC. 1 action.
        $result = $this->processSeek($encounter_id, $actor_id, $params, $game_state);
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $events[] = GameEventLogger::buildEvent('seek', 'encounter', $actor_id, [
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'sense_motive':
        // REQ 2211-2212: Secret Perception vs Deception; track retry cooldown. 1 action.
        {
          $sm_bonus = (int) ($params['perception_bonus'] ?? 0);
          $sm_dc = (int) ($params['deception_dc'] ?? 15);
          $sm_d20 = $this->numberGenerationService->rollPathfinderDie(20);
          $sm_total = $sm_d20 + $sm_bonus;
          $sm_degree = $this->combatCalculator->calculateDegreeOfSuccess($sm_total, $sm_dc, $sm_d20);
          if (!isset($game_state['sense_motive'])) {
            $game_state['sense_motive'] = [];
          }
          if (!isset($game_state['sense_motive'][$actor_id])) {
            $game_state['sense_motive'][$actor_id] = [];
          }
          // Track last-used round for retry cooldown (REQ 2212).
          $game_state['sense_motive'][$actor_id][$target_id] = $game_state['round'] ?? 0;
          $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
          // Secret result: return degree only (not raw d20) to caller.
          $result = ['sense_motive' => TRUE, 'degree' => $sm_degree];
          $events[] = GameEventLogger::buildEvent('sense_motive', 'encounter', $actor_id, [
            'round' => $game_state['round'] ?? NULL,
          ], NULL, $target_id);
        }
        break;

      case 'take_cover':
        // REQ 2218: Upgrade cover tier (none→standard, standard→greater). 1 action.
        if (!isset($game_state['entities'])) {
          $game_state['entities'] = [];
        }
        if (!isset($game_state['entities'][$actor_id])) {
          $game_state['entities'][$actor_id] = [];
        }
        $cur_cover = $game_state['entities'][$actor_id]['cover'] ?? 'none';
        $new_cover = ($cur_cover === 'standard') ? 'greater' : 'standard';
        $game_state['entities'][$actor_id]['cover'] = $new_cover;
        $game_state['entities'][$actor_id]['cover_active'] = TRUE;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['cover' => $new_cover, 'cover_active' => TRUE];
        $events[] = GameEventLogger::buildEvent('take_cover', 'encounter', $actor_id, [
          'cover' => $new_cover,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'release':
        // REQ 2206: Free action; drop held item; does not trigger manipulate-trait reactions.
        $rel_item = $params['item_id'] ?? NULL;
        if (!empty($dungeon_data['entities'])) {
          foreach ($dungeon_data['entities'] as &$rel_ent) {
            $rel_iid = $rel_ent['entity_instance_id'] ?? ($rel_ent['instance_id'] ?? ($rel_ent['id'] ?? NULL));
            if ($rel_iid === $actor_id) {
              if ($rel_item && isset($rel_ent['equipment']['held'][$rel_item])) {
                unset($rel_ent['equipment']['held'][$rel_item]);
              }
              break;
            }
          }
          unset($rel_ent);
        }
        // Free action: no standard action deducted.
        $result = ['released' => TRUE, 'item_id' => $rel_item];
        $events[] = GameEventLogger::buildEvent('release', 'encounter', $actor_id, [
          'item_id' => $rel_item,
          'round' => $game_state['round'] ?? NULL,
        ]);
        break;

      case 'aid_setup':
        // REQ 2190: Prepare Aid for a target ally. 1 action (on a previous turn).
        if (!isset($game_state['turn']['aid_prepared'])) {
          $game_state['turn']['aid_prepared'] = [];
        }
        $aid_skill = $params['skill'] ?? 'generic';
        $game_state['turn']['aid_prepared'][$actor_id][$target_id] = $aid_skill;
        $game_state['turn']['actions_remaining'] = max(0, ($game_state['turn']['actions_remaining'] ?? 0) - 1);
        $result = ['aid_prepared' => TRUE, 'target' => $target_id, 'skill' => $aid_skill];
        $events[] = GameEventLogger::buildEvent('aid_setup', 'encounter', $actor_id, [
          'target' => $target_id,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      case 'aid':
        // REQ 2190-2191: Reaction; verify aid was prepared, roll check vs DC 20.
        $result = $this->processAid($actor_id, $target_id, $params, $game_state);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('aid', 'encounter', $actor_id, [
          'target' => $target_id,
          'degree' => $result['degree'] ?? NULL,
          'aid_bonus' => $result['aid_bonus'] ?? 0,
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      case 'reaction':
        // Reaction: spend reaction resource.
        $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
        if (!$reaction_available) {
          return [
            'success' => FALSE,
            'result' => ['error' => 'Reaction already spent this round.'],
            'mutations' => [],
            'events' => [],
            'phase_transition' => NULL,
            'narration' => NULL,
          ];
        }
        $game_state['turn']['reaction_available'] = FALSE;
        $result = ['reaction_used' => TRUE, 'reaction_type' => $params['reaction_type'] ?? 'generic'];
        $events[] = GameEventLogger::buildEvent('reaction', 'encounter', $actor_id, [
          'reaction_type' => $params['reaction_type'] ?? 'generic',
          'round' => $game_state['round'] ?? NULL,
        ], NULL, $target_id);
        break;

      default:
        return [
          'success' => FALSE,
          'result' => ['error' => "Unknown encounter action: $type"],
          'mutations' => [],
          'events' => [],
          'phase_transition' => NULL,
          'narration' => NULL,
        ];
    }

    // Check for auto-end-turn (actions depleted + no movement remaining).
    // Delay is intentional initiative exit — do NOT auto-end-turn for it.
    $no_auto_end_types = ['end_turn', 'delay', 'delay_reenter', 'release', 'aid'];
    if (!in_array($type, $no_auto_end_types, TRUE) && $this->shouldAutoEndTurn($game_state)) {
      $auto_end = $this->processEndTurn($encounter_id, $actor_id, $game_state, $dungeon_data, $campaign_id);
      $events[] = GameEventLogger::buildEvent('auto_end_turn', 'encounter', $actor_id, [
        'round' => $game_state['round'] ?? NULL,
      ]);
      if (!empty($auto_end['npc_events'])) {
        $events = array_merge($events, $auto_end['npc_events']);
      }
      if (!$phase_transition) {
        $phase_transition = $this->checkEncounterEnd($encounter_id, $game_state);
      }
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
    $game_state['phase'] = 'encounter';
    $events = [];

    // Create the encounter via CombatEngine.
    $encounter_context = $context['encounter_context'] ?? [];
    $room_id = $encounter_context['room_id'] ?? ($dungeon_data['active_room_id'] ?? NULL);
    $enemies = $encounter_context['enemies'] ?? [];

    try {
      // Build participant list from entities in the room.
      $participants = $this->buildParticipantList($dungeon_data, $room_id, $enemies);

      // Create encounter in the combat_encounters table.
      $encounter_id = $this->combatEngine->createEncounter($campaign_id, $room_id, $participants, [
        'room_id' => $room_id,
      ]);

      if ($encounter_id) {
        // Start the encounter (rolls initiative, sorts order, starts round 1).
        $start_result = $this->combatEngine->startEncounter($encounter_id);

        $game_state['encounter_id'] = $encounter_id;
        $game_state['round'] = 1;

        // Set up the first turn.
        $initiative_order = $start_result['encounter']['participants'] ?? [];
        if (!empty($initiative_order)) {
          $first = $initiative_order[0];
          $game_state['turn'] = [
            'entity' => $first['entity_id'] ?? NULL,
            'index' => 0,
            'actions_remaining' => 3,
            'attacks_this_turn' => 0,
            'reaction_available' => TRUE,
            'delayed' => FALSE,
          ];
        }

        $game_state['initiative_order'] = $initiative_order;

        $events[] = GameEventLogger::buildEvent('encounter_started', 'encounter', NULL, [
          'encounter_id' => $encounter_id,
          'room_id' => $room_id,
          'participants' => count($participants),
          'initiative_order' => $initiative_order,
        ]);

        // AI GM narration for encounter start.
        $gm_narration = $this->aiGmService->narrateEncounterStart([
          'participants' => $participants,
          'room_name' => $room_id,
          'reason' => $context['reason'] ?? 'Hostile creatures detected',
        ], $dungeon_data, $campaign_id);
        if ($gm_narration) {
          $events[] = GameEventLogger::buildEvent('gm_narration', 'encounter', NULL, [
            'trigger' => 'encounter_start',
          ], $gm_narration);
        }

        // Queue encounter start for perception-filtered narration.
        $this->queueNarrationEvent($campaign_id, $dungeon_data, [
          'type' => 'action',
          'speaker' => 'GM',
          'speaker_type' => 'gm',
          'speaker_ref' => '',
          'content' => sprintf('Combat begins! %s', $context['reason'] ?? 'Hostile creatures detected!'),
          'visibility' => 'public',
          'mechanical_data' => [
            'encounter_id' => $encounter_id,
            'participant_count' => count($participants),
            'round' => 1,
          ],
        ], $room_id);

        // Mark the room's encounter as triggered.
        $this->markRoomEncounterTriggered($dungeon_data, $room_id);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create encounter: @error', ['@error' => $e->getMessage()]);
      $events[] = GameEventLogger::buildEvent('encounter_start_failed', 'encounter', NULL, [
        'error' => $e->getMessage(),
      ]);
    }

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $encounter_id = $game_state['encounter_id'] ?? NULL;
    $events = [];

    if ($encounter_id) {
      try {
        // End the encounter in the combat engine.
        $this->combatEngine->endEncounter(
          $encounter_id,
          'victory',
          'phase transition to exploration'
        );
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to end encounter: @error', ['@error' => $e->getMessage()]);
      }

      $events[] = GameEventLogger::buildEvent('encounter_ended', 'encounter', NULL, [
        'encounter_id' => $encounter_id,
        'final_round' => $game_state['round'] ?? NULL,
      ]);

      // AI GM narration for encounter end.
      $gm_narration = $this->aiGmService->narrateEncounterEnd([
        'encounter_id' => $encounter_id,
        'final_round' => $game_state['round'] ?? NULL,
        'victory' => TRUE,
      ], $dungeon_data, $campaign_id);
      if ($gm_narration) {
        $events[] = GameEventLogger::buildEvent('gm_narration', 'encounter', NULL, [
          'trigger' => 'encounter_end',
        ], $gm_narration);
      }

      // Queue encounter end for perception-filtered narration.
      $this->queueNarrationEvent($campaign_id, $dungeon_data, [
        'type' => 'action',
        'speaker' => 'GM',
        'speaker_type' => 'gm',
        'speaker_ref' => '',
        'content' => sprintf('The encounter ends after %d rounds.', $game_state['round'] ?? 0),
        'visibility' => 'public',
        'mechanical_data' => [
          'encounter_id' => $encounter_id,
          'final_round' => $game_state['round'] ?? NULL,
        ],
      ]);
    }

    // Clean up encounter state from game_state, but preserve it for history.
    $game_state['last_encounter'] = [
      'encounter_id' => $encounter_id,
      'final_round' => $game_state['round'] ?? NULL,
      'ended_at' => date('c'),
    ];

    $game_state['encounter_id'] = NULL;
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['initiative_order'] = NULL;

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    $actions = [];
    $turn = $game_state['turn'] ?? [];
    $current_entity = $turn['entity'] ?? NULL;
    $actions_remaining = $turn['actions_remaining'] ?? 0;
    $reaction_available = $turn['reaction_available'] ?? FALSE;

    // If it's the actor's turn.
    if ($actor_id && $actor_id === $current_entity) {
      if ($actions_remaining >= 1) {
        $actions[] = 'strike';
        $actions[] = 'stride';
        $actions[] = 'interact';
      }
      if ($actions_remaining >= 2) {
        $actions[] = 'cast_spell';
      }
      $actions[] = 'talk'; // Always free.
      $actions[] = 'end_turn';
      $actions[] = 'delay';
    }

    if ($reaction_available) {
      $actions[] = 'reaction';
    }

    return $actions;
  }

  // =========================================================================
  // Action processors.
  // =========================================================================

  /**
   * Processes a strike action via the existing combat system.
   */
  protected function processStrike(int $encounter_id, string $actor_id, string $target_id, array $params, array &$game_state): array {
    try {
      // Load encounter data.
      $encounter = $this->encounterStore->loadEncounter($encounter_id);
      if (!$encounter) {
        return ['error' => 'Encounter not found.'];
      }

      $attacker_participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
      $target_participant = $this->findEncounterParticipantByEntityId($encounter, $target_id);
      if (!$attacker_participant || !$target_participant) {
        return ['error' => 'Attacker or target is not present in the encounter.'];
      }

      $weapon = is_array($params['weapon'] ?? NULL) ? $params['weapon'] : [];
      $weapon += [
        'attack_bonus' => (int) ($params['attack_bonus'] ?? 100),
        'damage_dice' => (string) ($params['damage_dice'] ?? '1d8+50'),
        'damage_type' => (string) ($params['damage_type'] ?? 'physical'),
        'is_agile' => !empty($params['is_agile']),
      ];

      // Resolve attack through the combat engine.
      $attack_result = $this->combatEngine->resolveAttack(
        (int) ($attacker_participant['id'] ?? 0),
        (int) ($target_participant['id'] ?? 0),
        $weapon,
        $encounter_id
      );

      $updated_encounter = $this->encounterStore->loadEncounter($encounter_id) ?: $encounter;
      $game_state['initiative_order'] = $updated_encounter['participants'] ?? ($game_state['initiative_order'] ?? []);

      $updated_target = $this->findEncounterParticipantByEntityId($updated_encounter, $target_id) ?? $target_participant;

      $mutations = [];

      // If damage was dealt, track mutations.
      if (!empty($attack_result['damage_dealt'])) {
        $mutations[] = [
          'entity' => $target_id,
          'field' => 'hp',
          'from' => $target_participant['hp'] ?? NULL,
          'to' => $updated_target['hp'] ?? ($attack_result['damage_result']['new_hp'] ?? NULL),
        ];
      }

      return [
        'strike' => TRUE,
        'roll' => $attack_result['roll'] ?? NULL,
        'total' => $attack_result['total'] ?? NULL,
        'ac' => $attack_result['target_ac'] ?? NULL,
        'degree' => $attack_result['degree'] ?? NULL,
        'damage' => $attack_result['damage_dealt'] ?? NULL,
        'damage_type' => $weapon['damage_type'] ?? 'physical',
        'is_defeated' => !empty($updated_target['is_defeated']),
        'mutations' => $mutations,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Strike failed: @error', ['@error' => $e->getMessage()]);
      return ['error' => 'Strike resolution failed.', 'mutations' => []];
    }
  }

  /**
   * Find a combat participant by encounter entity_id.
   */
  protected function findEncounterParticipantByEntityId(array $encounter, string $entity_id): ?array {
    foreach (($encounter['participants'] ?? []) as $participant) {
      if ((string) ($participant['entity_id'] ?? '') === (string) $entity_id) {
        return $participant;
      }
    }

    return NULL;
  }

  /**
   * Processes a stride action (movement during encounter, costs 1 action).
   */
  protected function processStride(int $encounter_id, string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $to_hex = $params['to_hex'] ?? NULL;
    if (!$to_hex) {
      return ['error' => 'Missing to_hex.', 'mutations' => []];
    }

    // Update entity position in dungeon_data.
    $entity = NULL;
    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$e) {
        $iid = $e['entity_instance_id'] ?? ($e['instance_id'] ?? ($e['id'] ?? NULL));
        if ($iid === $actor_id) {
          $entity = &$e;
          break;
        }
      }
      unset($e);
    }

    $from_hex = NULL;
    if ($entity) {
      $from_hex = $entity['placement']['hex'] ?? NULL;
      $entity['placement']['hex'] = ['q' => (int) $to_hex['q'], 'r' => (int) $to_hex['r']];
    }

    // Also update the participant's position in the encounter store.
    try {
      $this->encounterStore->updateParticipant($encounter_id, $actor_id, [
        'position_q' => (int) $to_hex['q'],
        'position_r' => (int) $to_hex['r'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to update participant position: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'stride' => TRUE,
      'from_hex' => $from_hex,
      'to_hex' => $to_hex,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'placement.hex', 'from' => $from_hex, 'to' => $to_hex],
      ],
    ];
  }

  /**
   * Processes a spell cast during encounter.
   */
  protected function processCastSpell(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Spell casting is a stub in the existing system.
    // For now, log the intent and let the AI narrate.
    $spell_name = $params['spell_name'] ?? 'unknown';

    return [
      'cast' => TRUE,
      'spell' => $spell_name,
      'narration' => NULL,
      'mutations' => [],
    ];
  }

  /**
   * Processes an interact action during encounter (1 action).
   */
  protected function processInteract(int $encounter_id, string $actor_id, ?string $target_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $interaction_type = $params['interaction_type'] ?? 'generic';

    // Handle door/passage opening.
    if (in_array($interaction_type, ['open_door', 'open_passage'])) {
      if (!empty($dungeon_data['connections'])) {
        foreach ($dungeon_data['connections'] as &$conn) {
          if (($conn['id'] ?? NULL) === $target_id) {
            $conn['is_passable'] = TRUE;
            $conn['is_discovered'] = TRUE;
            break;
          }
        }
        unset($conn);
      }
    }

    return [
      'interacted' => TRUE,
      'interaction_type' => $interaction_type,
      'target' => $target_id,
      'mutations' => [],
    ];
  }

  /**
   * Processes end-of-turn: advance to next combatant, auto-play NPCs.
   */
  protected function processEndTurn(int $encounter_id, ?string $actor_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $initiative_order = $game_state['initiative_order'] ?? [];
    $current_index = $game_state['turn']['index'] ?? 0;
    $npc_events = [];
    $new_round = NULL;

    // Tick end-of-turn conditions for the current combatant.
    if ($encounter_id && $actor_id) {
      try {
        $this->conditionManager->tickConditions($encounter_id, $actor_id);
      }
      catch (\Throwable $e) {
        $this->logger->warning('Condition tick failed: @error', ['@error' => $e->getMessage()]);
      }
    }

    // Advance to next non-defeated combatant.
    $next_index = $current_index + 1;
    $wrapped = FALSE;

    while (TRUE) {
      if ($next_index >= count($initiative_order)) {
        // Wrap to next round.
        $next_index = 0;
        $game_state['round'] = ($game_state['round'] ?? 1) + 1;
        $new_round = $game_state['round'];
        $wrapped = TRUE;
      }

      // Safety: don't loop forever.
      if ($wrapped && $next_index > $current_index) {
        break;
      }

      $next_combatant = $initiative_order[$next_index] ?? NULL;
      if ($next_combatant && empty($next_combatant['is_defeated'])) {
        break;
      }
      $next_index++;
    }

    $next_entity = $initiative_order[$next_index]['entity_id'] ?? NULL;
    $next_team = $initiative_order[$next_index]['team'] ?? 'enemy';

    // Update game_state turn.
    $game_state['turn'] = [
      'entity' => $next_entity,
      'index' => $next_index,
      'actions_remaining' => 3,
      'attacks_this_turn' => 0,
      'reaction_available' => TRUE,
      'delayed' => FALSE,
    ];

    // Update the encounter store.
    try {
      $this->encounterStore->updateEncounter($encounter_id, [
        'turn_index' => $next_index,
        'current_round' => $game_state['round'],
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('Encounter store update failed: @error', ['@error' => $e->getMessage()]);
    }

    // If next combatant is NPC/enemy, auto-play their turn.
    if ($next_team !== 'player') {
      $npc_result = $this->autoPlayNpcTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id);
      $npc_events = $npc_result['events'] ?? [];

      // After NPC turn, recursively advance (NPC might be followed by another NPC).
      if (!$this->isEncounterOver($encounter_id, $game_state)) {
        $further = $this->processEndTurn($encounter_id, $next_entity, $game_state, $dungeon_data, $campaign_id);
        $npc_events = array_merge($npc_events, $further['npc_events'] ?? []);
        if (!$new_round && !empty($further['new_round'])) {
          $new_round = $further['new_round'];
        }
      }
    }

    return [
      'turn_advanced' => TRUE,
      'next_entity' => $next_entity,
      'next_team' => $next_team,
      'round' => $game_state['round'],
      'new_round' => $new_round,
      'npc_events' => $npc_events,
      'mutations' => [],
    ];
  }

  // =========================================================================
  // NPC Auto-play.
  // =========================================================================

  /**
   * Auto-plays a non-player combatant's turn using AI or fallback logic.
   */
  protected function autoPlayNpcTurn(int $encounter_id, string $entity_id, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $events = [];
    $context = $this->buildNpcContext($entity_id, $game_state, $dungeon_data);

    // Check config flag — if AI autoplay disabled, always use fallback.
    $ai_enabled = (bool) $this->configFactory->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_npc_autoplay_enabled');

    $action_type = NULL;
    $target = NULL;
    $narration = NULL;

    if ($ai_enabled) {
      try {
        $result = $this->encounterAiService->requestNpcActionRecommendation($context);

        if (!empty($result['success']) && !empty($result['recommendation'])) {
          $rec = $result['recommendation'];
          $action = $rec['recommended_action'] ?? [];
          $valid = $result['validation']['valid'] ?? FALSE;

          if ($valid) {
            $action_type = $action['type'] ?? NULL;
            $target = $action['target_instance_id'] ?? ($action['target'] ?? NULL);
            $narration = $rec['narration'] ?? NULL;
          }
          else {
            $this->logger->info('NPC AI recommendation invalid, using fallback. Errors: @errors', [
              '@errors' => implode('; ', $result['validation']['errors'] ?? []),
            ]);
          }
        }
      }
      catch (\Exception $e) {
        $this->logger->warning('NPC AI failed, using fallback: @error', ['@error' => $e->getMessage()]);
      }
    }

    // Fallback: choose a sensible action without AI.
    if ($action_type === NULL) {
      $action_type = $this->chooseFallbackAction($entity_id, $game_state);
      $target = ($action_type === 'strike')
        ? $this->findNearestAlivePlayer($entity_id, $game_state)
        : NULL;
    }

    // Execute the chosen action.
    switch ($action_type) {
      case 'strike':
        if ($target) {
          $strike_result = $this->processStrike($encounter_id, $entity_id, $target, [], $game_state);
          $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
            'target' => $target,
            'roll' => $strike_result['roll'] ?? NULL,
            'degree' => $strike_result['degree'] ?? NULL,
            'damage' => $strike_result['damage'] ?? NULL,
          ], $narration, $target);

          // Check for entity defeat after strike.
          $this->checkEntityDefeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
        }
        break;

      case 'stride':
        // Move toward the nearest player.
        $nearest = $this->findNearestAlivePlayer($entity_id, $game_state);
        $events[] = GameEventLogger::buildEvent('npc_stride', 'encounter', $entity_id, [
          'toward' => $nearest,
        ], $narration);
        break;

      case 'interact':
        $events[] = GameEventLogger::buildEvent('npc_interact', 'encounter', $entity_id, [
          'interaction' => 'raise_shield',
        ], $narration);
        break;

      case 'talk':
        $events[] = GameEventLogger::buildEvent('npc_talk', 'encounter', $entity_id, [
          'message' => $narration ?? 'The creature snarls at you.',
        ], $narration);
        break;

      default:
        // Unknown action — default to strike.
        $target = $target ?? $this->findFirstAlivePlayer($game_state);
        if ($target) {
          $strike_result = $this->processStrike($encounter_id, $entity_id, $target, [], $game_state);
          $events[] = GameEventLogger::buildEvent('npc_strike', 'encounter', $entity_id, [
            'target' => $target,
            'roll' => $strike_result['roll'] ?? NULL,
            'degree' => $strike_result['degree'] ?? NULL,
            'damage' => $strike_result['damage'] ?? NULL,
            'fallback' => TRUE,
          ], NULL, $target);

          // Check for entity defeat after fallback strike.
          $this->checkEntityDefeated($target, $entity_id, $game_state, $events, $dungeon_data, $campaign_id);
        }
        break;
    }

    return ['events' => $events];
  }

  /**
   * Choose a fallback action for NPC without AI.
   *
   * Basic tactical heuristic: if adjacent to player → strike; otherwise → stride.
   */
  protected function chooseFallbackAction(string $entity_id, array $game_state): string {
    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return 'strike';
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);

    // Check if any alive player is adjacent (distance = 1 hex).
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['team'] ?? '') !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($dist <= 1) {
        return 'strike';
      }
    }

    return 'stride';
  }

  /**
   * Find the nearest alive player to an NPC.
   */
  protected function findNearestAlivePlayer(string $entity_id, array $game_state): ?string {
    $npc = $this->findCombatant($entity_id, $game_state);
    if (!$npc) {
      return $this->findFirstAlivePlayer($game_state);
    }

    $npc_q = (int) ($npc['position_q'] ?? 0);
    $npc_r = (int) ($npc['position_r'] ?? 0);
    $closest = NULL;
    $closest_dist = PHP_INT_MAX;

    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['team'] ?? '') !== 'player' || !empty($combatant['is_defeated'])) {
        continue;
      }
      $pq = (int) ($combatant['position_q'] ?? 0);
      $pr = (int) ($combatant['position_r'] ?? 0);
      $dist = $this->hexDistance($npc_q, $npc_r, $pq, $pr);

      if ($dist < $closest_dist) {
        $closest_dist = $dist;
        $closest = $combatant['entity_id'] ?? NULL;
      }
    }

    return $closest;
  }

  /**
   * Find a combatant in the initiative order by entity ID.
   */
  protected function findCombatant(string $entity_id, array $game_state): ?array {
    foreach (($game_state['initiative_order'] ?? []) as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant;
      }
    }
    return NULL;
  }

  /**
   * Calculate hex distance (cube coordinates).
   */
  protected function hexDistance(int $q1, int $r1, int $q2, int $r2): int {
    $dq = abs($q1 - $q2);
    $dr = abs($r1 - $r2);
    $ds = abs((-$q1 - $r1) - (-$q2 - $r2));
    return (int) max($dq, $dr, $ds);
  }

  /**
   * Check if an entity was defeated after damage and generate narration.
   *
   * @param string $entity_id
   *   The entity to check for defeat.
   * @param string $attacker_id
   *   The entity that dealt the killing blow.
   * @param array &$game_state
   *   Current game state (modified if entity defeated).
   * @param array &$events
   *   Events array to append defeat event to.
   * @param array $dungeon_data
   *   Dungeon data for AI narration context.
   */
  protected function checkEntityDefeated(string $entity_id, string $attacker_id, array &$game_state, array &$events, array $dungeon_data, int $campaign_id = 0): void {
    foreach ($game_state['initiative_order'] as &$combatant) {
      if (($combatant['entity_id'] ?? '') !== $entity_id) {
        continue;
      }

      $hp = (int) ($combatant['hp'] ?? 0);
      if ($hp <= 0 && empty($combatant['is_defeated'])) {
        $combatant['is_defeated'] = TRUE;
        $name = $combatant['name'] ?? $entity_id;
        $team = $combatant['team'] ?? 'unknown';

        // Resolve attacker name for narration.
        $attacker = $this->findCombatant($attacker_id, $game_state);
        $killer_name = $attacker['name'] ?? $attacker_id;

        $narration = $this->aiGmService->narrateEntityDefeated($name, $killer_name, $dungeon_data, $campaign_id);
        $events[] = GameEventLogger::buildEvent('entity_defeated', 'encounter', $entity_id, [
          'name' => $name,
          'team' => $team,
          'killed_by' => $killer_name,
        ], $narration);
      }
      break;
    }
    unset($combatant);
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Processes an Escape attempt (REQ 2197-2199).
   * Attack trait: applies MAP. Crit success: freed + may Stride 5 ft.
   * Crit fail: blocks further escape attempts this turn.
   */
  protected function processEscape(int $encounter_id, string $actor_id, array $params, array &$game_state): array {
    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    if (!$encounter) {
      return ['error' => 'Encounter not found.', 'mutations' => []];
    }
    $participant = $this->findEncounterParticipantByEntityId($encounter, $actor_id);
    if (!$participant) {
      return ['error' => 'Participant not found.', 'mutations' => []];
    }
    $pid = (int) $participant['id'];

    // REQ 2198: crit fail blocks further escape this turn.
    if (!empty($game_state['turn']['escape_blocked'][$actor_id])) {
      return ['error' => 'Cannot attempt Escape again this turn (critical failure).', 'mutations' => []];
    }

    // Must have grabbed, immobilized, or restrained.
    $active = $this->conditionManager->getActiveConditions($pid, $encounter_id);
    $condition_row_id = NULL;
    foreach ($active as $row_id => $row) {
      if (in_array($row['condition_type'], ['grabbed', 'immobilized', 'restrained'], TRUE)) {
        $condition_row_id = $row_id;
        break;
      }
    }
    if ($condition_row_id === NULL) {
      return ['error' => 'Must be grabbed, immobilized, or restrained to Escape.', 'mutations' => []];
    }

    // REQ 2199: attack trait — apply MAP.
    $attacks_this_turn = $game_state['turn']['attacks_this_turn'] ?? 0;
    $map = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, !empty($params['is_agile']));
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + (int) ($params['skill_bonus'] ?? 0) + $map;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, (int) ($params['grapple_dc'] ?? 15), $d20);

    // Increment MAP for future attacks.
    $game_state['turn']['attacks_this_turn'] = $attacks_this_turn + 1;

    if (in_array($degree, ['critical_success', 'success'], TRUE)) {
      $this->conditionManager->removeCondition($pid, $condition_row_id, $encounter_id);
    }
    if ($degree === 'critical_failure') {
      if (!isset($game_state['turn']['escape_blocked'])) {
        $game_state['turn']['escape_blocked'] = [];
      }
      $game_state['turn']['escape_blocked'][$actor_id] = TRUE;
    }

    return [
      'escaped' => in_array($degree, ['critical_success', 'success'], TRUE),
      'may_stride_5ft' => ($degree === 'critical_success'),
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Processes a Seek action (REQ 2207-2210).
   * Secret GM-side Perception roll vs each target's Stealth DC.
   * Updates visibility state in game_state['visibility'][$seeker_id][$target_id].
   */
  protected function processSeek(int $encounter_id, string $actor_id, array $params, array &$game_state): array {
    $perception_bonus = (int) ($params['perception_bonus'] ?? 0);
    $target_ids = $params['target_ids'] ?? [];
    $is_imprecise = !empty($params['imprecise_sense']);
    // stealth_dcs: assoc array of target_id → DC; defaults to 15 if not provided.
    $stealth_dcs = $params['stealth_dcs'] ?? [];

    if (!isset($game_state['visibility'])) {
      $game_state['visibility'] = [];
    }
    if (!isset($game_state['visibility'][$actor_id])) {
      $game_state['visibility'][$actor_id] = [];
    }

    $seek_results = [];
    foreach ($target_ids as $tid) {
      $stealth_dc = (int) ($stealth_dcs[$tid] ?? 15);
      $d20 = $this->numberGenerationService->rollPathfinderDie(20);
      $total = $d20 + $perception_bonus;
      $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $stealth_dc, $d20);

      $current = $game_state['visibility'][$actor_id][$tid] ?? 'undetected';
      $new_visibility = $current;

      // REQ 2208: detection rules.
      if ($degree === 'critical_success' && in_array($current, ['undetected', 'hidden'], TRUE)) {
        $new_visibility = 'observed';
      }
      elseif ($degree === 'success' && $current === 'undetected') {
        $new_visibility = 'hidden';
      }
      // failure / crit fail: no change.

      // REQ 2210: Imprecise sense cap — cannot exceed hidden.
      if ($is_imprecise && $new_visibility === 'observed') {
        $new_visibility = 'hidden';
      }

      $game_state['visibility'][$actor_id][$tid] = $new_visibility;
      // Secret: d20/total not included in returned result (GM-only).
      $seek_results[$tid] = ['degree' => $degree, 'new_visibility' => $new_visibility];
    }

    return ['sought' => TRUE, 'results' => $seek_results];
  }

  /**
   * Processes an Aid reaction (REQ 2190-2191).
   * Requires prior aid_setup on a previous turn. Rolls vs DC 20.
   */
  protected function processAid(string $actor_id, ?string $target_id, array $params, array &$game_state): array {
    $reaction_available = $game_state['turn']['reaction_available'] ?? TRUE;
    if (!$reaction_available) {
      return ['error' => 'Reaction already spent.', 'mutations' => []];
    }

    $aiding_actor = $params['aiding_actor'] ?? $actor_id;
    $aid_prepared = $game_state['turn']['aid_prepared'][$aiding_actor][$target_id] ?? NULL;
    if (!$aid_prepared) {
      return ['error' => 'Aid has not been prepared for this target.', 'mutations' => []];
    }

    $skill_bonus = (int) ($params['skill_bonus'] ?? 0);
    // proficiency_rank: 0=untrained,1=trained,2=expert,3=master,4=legendary.
    $proficiency_rank = (int) ($params['proficiency_rank'] ?? 0);
    $d20 = $this->numberGenerationService->rollPathfinderDie(20);
    $total = $d20 + $skill_bonus;
    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, 20, $d20);

    // REQ 2191: Aid bonus by degree and proficiency rank.
    $aid_bonus = 0;
    if ($degree === 'critical_success') {
      if ($proficiency_rank >= 4) {
        $aid_bonus = 4;
      }
      elseif ($proficiency_rank >= 3) {
        $aid_bonus = 3;
      }
      else {
        $aid_bonus = 2;
      }
    }
    elseif ($degree === 'success') {
      $aid_bonus = 1;
    }
    elseif ($degree === 'critical_failure') {
      $aid_bonus = -1;
    }

    // Store aid bonus for the aided actor's next action.
    if (!isset($game_state['aid_bonuses'])) {
      $game_state['aid_bonuses'] = [];
    }
    if (!isset($game_state['aid_bonuses'][$target_id])) {
      $game_state['aid_bonuses'][$target_id] = [];
    }
    $game_state['aid_bonuses'][$target_id][] = $aid_bonus;
    $game_state['turn']['reaction_available'] = FALSE;

    return [
      'aided' => TRUE,
      'aid_bonus' => $aid_bonus,
      'degree' => $degree,
      'd20' => $d20,
      'total' => $total,
      'mutations' => [],
    ];
  }

  /**
   * Gets the action cost for an intent type.
   */
  protected function getActionCost(string $type, array $params = []): int {
    switch ($type) {
      case 'strike':
      case 'stride':
      case 'interact':
      case 'stand':
      case 'drop_prone':
      case 'step':
      case 'crawl':
      case 'leap':
      case 'escape':
      case 'seek':
      case 'sense_motive':
      case 'take_cover':
      case 'aid_setup':
        return 1;

      case 'ready':
        return 2;

      case 'cast_spell':
        return $params['action_cost'] ?? 2;

      case 'talk':
      case 'release':
      case 'aid':
      case 'delay_reenter':
        return 0;

      default:
        return 1;
    }
  }

  /**
   * Checks if auto-end-turn conditions are met.
   */
  protected function shouldAutoEndTurn(array $game_state): bool {
    $actions = $game_state['turn']['actions_remaining'] ?? 0;
    return $actions <= 0;
  }

  /**
   * Checks if the encounter should end (all enemies defeated or all players defeated).
   */
  protected function checkEncounterEnd(int $encounter_id, array &$game_state): ?array {
    if ($this->isEncounterOver($encounter_id, $game_state)) {
      return [
        'from' => 'encounter',
        'to' => 'exploration',
        'reason' => 'All enemies have been defeated!',
        'encounter_result' => [
          'encounter_id' => $encounter_id,
          'final_round' => $game_state['round'] ?? NULL,
          'victory' => TRUE,
        ],
      ];
    }

    return NULL;
  }

  /**
   * Determines if the encounter is over.
   */
  protected function isEncounterOver(int $encounter_id, array $game_state): bool {
    $initiative_order = $game_state['initiative_order'] ?? [];
    $teams_alive = [];

    foreach ($initiative_order as $combatant) {
      if (empty($combatant['is_defeated'])) {
        $team = $combatant['team'] ?? 'enemy';
        $teams_alive[$team] = TRUE;
      }
    }

    // Encounter is over if only one team (or zero) remains.
    return count($teams_alive) <= 1;
  }

  /**
   * Builds participant list from dungeon entities for encounter creation.
   */
  protected function buildParticipantList(array $dungeon_data, string $room_id, array $enemies = []): array {
    $participants = [];
    $entities = $dungeon_data['entities'] ?? [];

    foreach ($entities as $entity) {
      $entity_room = $entity['placement']['room_id'] ?? NULL;
      if ($entity_room !== $room_id) {
        continue;
      }

      $content_type = $entity['entity_type'] ?? ($entity['entity_ref']['content_type'] ?? '');
      $instance_id = $entity['entity_instance_id'] ?? ($entity['instance_id'] ?? ($entity['id'] ?? NULL));

      if ($content_type === 'player_character') {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
          ]),
          'team' => 'player',
          'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? 'Unknown'),
          'hp' => $stats['currentHp'] ?? ($entity['state']['hit_points']['current'] ?? 20),
          'max_hp' => $stats['maxHp'] ?? ($entity['state']['hit_points']['max'] ?? 20),
          'ac' => $stats['ac'] ?? ($entity['state']['armor_class'] ?? 10),
          'perception' => $perception,
          'position_q' => $entity['placement']['hex']['q'] ?? 0,
          'position_r' => $entity['placement']['hex']['r'] ?? 0,
        ];
      }
      elseif ($content_type === 'creature' || $content_type === 'npc' || in_array($instance_id, array_column($enemies, 'entity_instance_id'))) {
        $stats = $entity['state']['metadata']['stats'] ?? [];
        $perception = $stats['perception'] ?? ($entity['state']['perception'] ?? 0);
        $participants[] = [
          'entity_id' => $instance_id,
          'entity_ref' => json_encode([
            'content_type' => $entity['entity_ref']['content_type'] ?? $content_type,
            'content_id' => $entity['entity_ref']['content_id'] ?? $instance_id,
            'perception_modifier' => (int) $perception,
          ]),
          'team' => 'enemy',
          'name' => $entity['state']['metadata']['display_name'] ?? ($entity['entity_ref']['content_id'] ?? 'Unknown'),
          'hp' => $stats['currentHp'] ?? ($entity['state']['hit_points']['current'] ?? 10),
          'max_hp' => $stats['maxHp'] ?? ($entity['state']['hit_points']['max'] ?? 10),
          'ac' => $stats['ac'] ?? ($entity['state']['armor_class'] ?? 12),
          'perception' => $perception,
          'position_q' => $entity['placement']['hex']['q'] ?? 0,
          'position_r' => $entity['placement']['hex']['r'] ?? 0,
        ];
      }
    }

    return $participants;
  }

  /**
   * Marks a room's encounter as triggered.
   */
  protected function markRoomEncounterTriggered(array &$dungeon_data, string $room_id): void {
    if (empty($dungeon_data['rooms'])) {
      return;
    }

    foreach ($dungeon_data['rooms'] as &$room) {
      if (($room['room_id'] ?? '') === $room_id) {
        if (!isset($room['gameplay_state'])) {
          $room['gameplay_state'] = [];
        }
        $room['gameplay_state']['encounter_triggered'] = TRUE;
        break;
      }
    }
    unset($room);
  }

  /**
   * Builds context object for NPC AI decision-making.
   */
  protected function buildNpcContext(string $entity_id, array $game_state, array $dungeon_data): array {
    $initiative_order = $game_state['initiative_order'] ?? [];
    $npc = NULL;
    $allies = [];
    $enemies = [];

    foreach ($initiative_order as $combatant) {
      $cid = $combatant['entity_id'] ?? '';
      if ($cid === $entity_id) {
        $npc = $combatant;
        continue;
      }
      if (!empty($combatant['is_defeated'])) {
        continue;
      }
      $team = $combatant['team'] ?? 'enemy';
      if ($team === 'player') {
        $enemies[] = [
          'entity_id' => $cid,
          'name' => $combatant['name'] ?? $cid,
          'hp_ratio' => $this->hpRatio($combatant),
          'position_q' => (int) ($combatant['position_q'] ?? 0),
          'position_r' => (int) ($combatant['position_r'] ?? 0),
          'ac' => (int) ($combatant['ac'] ?? 10),
        ];
      }
      else {
        $allies[] = [
          'entity_id' => $cid,
          'name' => $combatant['name'] ?? $cid,
          'hp_ratio' => $this->hpRatio($combatant),
        ];
      }
    }

    return [
      'encounter_id' => $game_state['encounter_id'] ?? NULL,
      'campaign_id' => $game_state['campaign_id'] ?? NULL,
      'round' => $game_state['round'] ?? NULL,
      'entity_id' => $entity_id,
      'current_actor' => $npc ? [
        'entity_id' => $entity_id,
        'entity_ref' => $entity_id,
        'name' => $npc['name'] ?? $entity_id,
        'team' => $npc['team'] ?? 'enemy',
        'hp' => (int) ($npc['hp'] ?? 0),
        'max_hp' => (int) ($npc['max_hp'] ?? 0),
        'hp_ratio' => $this->hpRatio($npc ?? []),
        'ac' => (int) ($npc['ac'] ?? 12),
        'position_q' => (int) ($npc['position_q'] ?? 0),
        'position_r' => (int) ($npc['position_r'] ?? 0),
        'actions_remaining' => (int) ($game_state['turn']['actions_remaining'] ?? 3),
      ] : ['entity_id' => $entity_id, 'entity_ref' => $entity_id],
      'participants' => $initiative_order,
      'allies' => $allies,
      'threats' => $enemies,
      'allowed_actions' => [
        'strike', 'stride', 'interact', 'talk', 'end_turn',
      ],
      // NPC personality/psychology context for AI decision-making.
      'npc_psychology' => $this->buildNpcPsychologyContext($entity_id, $game_state),
    ];
  }

  /**
   * Build psychology context string for an NPC in combat.
   *
   * Provides personality-driven combat behavior hints to the AI:
   * - Cowardly NPCs may flee when badly hurt
   * - Disciplined NPCs focus fire and protect allies
   * - Cunning NPCs target weak PCs
   * - NPC attitude affects willingness to parley / surrender
   *
   * @param string $entity_id
   *   Entity ID.
   * @param array $game_state
   *   Current game state.
   *
   * @return string
   *   Formatted psychology context or empty string.
   */
  protected function buildNpcPsychologyContext(string $entity_id, array $game_state): string {
    $campaign_id = $game_state['campaign_id'] ?? 0;
    if (!$campaign_id) {
      return '';
    }

    // entity_id might be "entity_creature_2_1", entity_ref is the content_id like "goblin_warrior_1"
    // Try to find the entity's content_id from the initiative_order or use entity_id directly.
    $entity_ref = $entity_id;
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        $entity_ref = $combatant['entity_ref'] ?? $combatant['entity_id'] ?? $entity_id;
        break;
      }
    }

    $profile = $this->psychologyService->loadProfile($campaign_id, $entity_ref);
    if (!$profile && $entity_ref !== $entity_id) {
      // Fall back to entity_id as ref.
      $profile = $this->psychologyService->loadProfile($campaign_id, $entity_id);
    }

    if (!$profile) {
      return '';
    }

    $parts = [];
    $parts[] = "=== NPC COMBAT PERSONALITY ===";
    $parts[] = "Name: {$profile['display_name']}";
    $parts[] = "Attitude toward party: {$profile['attitude']}";

    if (!empty($profile['personality_traits'])) {
      $parts[] = "Personality: {$profile['personality_traits']}";
    }
    if (!empty($profile['motivations'])) {
      $parts[] = "Fighting motivation: {$profile['motivations']}";
    }

    // Translate personality axes into combat behavioral hints.
    $axes = $profile['personality_axes'] ?? [];
    $hints = [];
    $boldness = $axes['boldness'] ?? 5;
    if ($boldness <= 3) {
      $hints[] = 'Will try to flee or surrender if below 25% HP';
    }
    elseif ($boldness >= 8) {
      $hints[] = 'Fights recklessly to the death, never retreats';
    }

    $discipline = $axes['discipline'] ?? 5;
    if ($discipline >= 7) {
      $hints[] = 'Coordinates with allies, focuses fire on wounded targets';
    }
    elseif ($discipline <= 3) {
      $hints[] = 'Fights chaotically, may switch targets randomly';
    }

    $cunning = $axes['cunning'] ?? 5;
    if ($cunning >= 7) {
      $hints[] = 'Targets the weakest or most dangerous PC strategically';
    }

    $empathy = $axes['empathy'] ?? 5;
    if ($empathy >= 7 && in_array($profile['attitude'], ['friendly', 'helpful'])) {
      $hints[] = 'May refuse to fight, or try to end combat through diplomacy';
    }

    if ($hints) {
      $parts[] = "Combat behavior: " . implode('; ', $hints);
    }

    // Recent relevant thoughts.
    $monologue = $profile['inner_monologue'] ?? [];
    if ($monologue) {
      $last = end($monologue);
      if ($last && !empty($last['thought'])) {
        $parts[] = "Current mindset: \"{$last['thought']}\" (feeling {$last['emotion']})";
      }
    }

    return implode("\n", $parts);
  }

  /**
   * Calculate HP ratio for tactical context.
   */
  protected function hpRatio(array $combatant): float {
    $max = (int) ($combatant['max_hp'] ?? 0);
    if ($max <= 0) {
      return 1.0;
    }
    $current = (int) ($combatant['hp'] ?? 0);
    return round($current / $max, 2);
  }

  /**
   * Finds the first alive player entity for NPC fallback targeting.
   */
  protected function findFirstAlivePlayer(array $game_state): ?string {
    $initiative_order = $game_state['initiative_order'] ?? [];

    foreach ($initiative_order as $combatant) {
      if (($combatant['team'] ?? '') === 'player' && empty($combatant['is_defeated'])) {
        return $combatant['entity_id'] ?? NULL;
      }
    }

    return NULL;
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
   * Resolve a display name for an entity from initiative order or dungeon data.
   */
  protected function resolveEntityName(?string $entity_id, array $game_state, array $dungeon_data = []): string {
    if (!$entity_id) {
      return 'Unknown';
    }

    // Check initiative order first (encounter context).
    foreach ($game_state['initiative_order'] ?? [] as $combatant) {
      if (($combatant['entity_id'] ?? '') === $entity_id) {
        return $combatant['name'] ?? $combatant['display_name'] ?? $entity_id;
      }
    }

    // Check dungeon_data entities.
    foreach ($dungeon_data['entities'] ?? [] as $ent) {
      $ent_id = $ent['entity_instance_id'] ?? ($ent['entity_ref']['content_id'] ?? '');
      if ($ent_id === $entity_id) {
        return $ent['state']['metadata']['display_name'] ?? $ent['name'] ?? $entity_id;
      }
    }

    return $entity_id;
  }

}
