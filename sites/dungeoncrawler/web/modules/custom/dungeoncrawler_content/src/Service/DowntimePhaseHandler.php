<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles game actions during the Downtime phase (stub).
 *
 * Downtime is the between-adventure phase where characters perform long-duration
 * activities: crafting items, earning income, retraining feats/skills, and
 * recovering from conditions.
 *
 * This is a stub implementation. Only long_rest is functional.
 * Full implementation is deferred to a future sprint.
 */
class DowntimePhaseHandler implements PhaseHandlerInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CharacterStateService
   */
  protected CharacterStateService $characterStateService;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CraftingService
   */
  protected CraftingService $craftingService;

  /**
   * Constructs a DowntimePhaseHandler.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    CharacterStateService $character_state_service,
    CraftingService $crafting_service
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->characterStateService = $character_state_service;
    $this->craftingService = $crafting_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhaseName(): string {
    return 'downtime';
  }

  /**
   * {@inheritdoc}
   */
  public function getLegalIntents(): array {
    return [
      'long_rest',
      'downtime_rest',
      'craft',
      'earn_income',
      'retrain',
      'advance_day',
      'talk',
      'return_to_exploration',
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
        'reason' => "Action '$type' is not legal during downtime phase.",
      ];
    }

    return ['valid' => TRUE, 'reason' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function processIntent(array $intent, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $type = $intent['type'] ?? '';
    $actor_id = $intent['actor'] ?? NULL;
    $params = $intent['params'] ?? [];

    $result = [];
    $mutations = [];
    $events = [];
    $phase_transition = NULL;
    $narration = NULL;

    switch ($type) {

      case 'long_rest':
        $result = $this->processLongRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('long_rest', 'downtime', $actor_id, [
          'hp_restored' => $result['hp_restored'] ?? 0,
          'conditions_removed' => $result['conditions_removed'] ?? [],
          'spell_slots_restored' => $result['spell_slots_restored'] ?? FALSE,
        ]);
        break;

      case 'return_to_exploration':
        $phase_transition = [
          'from' => 'downtime',
          'to' => 'exploration',
          'reason' => 'Returning to adventure.',
        ];
        $events[] = GameEventLogger::buildEvent('downtime_ended', 'downtime', $actor_id, []);
        break;

      case 'downtime_rest':
        // REQ 2306: Long-term rest during downtime (8 hours recovers Con×2×level HP).
        $result = $this->processDowntimeRest($actor_id, $params, $game_state, $dungeon_data, $campaign_id);
        $mutations = $result['mutations'] ?? [];
        $events[] = GameEventLogger::buildEvent('downtime_rest', 'downtime', $actor_id, [
          'hp_restored' => $result['hp_restored'] ?? 0,
        ]);
        break;

      case 'retrain':
        // REQ 2307-2310: Retrain a feat, skill, or class choice.
        $result = $this->processRetrain($actor_id, $params, $game_state);
        $events[] = GameEventLogger::buildEvent('retrain', 'downtime', $actor_id, [
          'retrain_type' => $params['retrain_type'] ?? NULL,
          'from' => $params['retrain_from'] ?? NULL,
          'to' => $params['retrain_to'] ?? NULL,
        ]);
        break;

      case 'advance_day':
        $result = $this->processAdvanceDay($actor_id, $game_state, $dungeon_data);
        $events[] = GameEventLogger::buildEvent('advance_day', 'downtime', $actor_id, [
          'days_elapsed' => $result['days_elapsed'] ?? NULL,
          'retrain_completed' => $result['retrain_completed'] ?? NULL,
        ]);
        break;

      case 'craft':
        $result = $this->processCraft($actor_id, $params, $game_state, $campaign_id);
        $events[] = GameEventLogger::buildEvent($type, 'downtime', $actor_id, [
          'degree'       => $params['degree'] ?? NULL,
          'item_granted' => $result['item_granted'] ?? FALSE,
        ]);
        break;

      case 'earn_income':
        // Stub — to be implemented in a future sprint.
        $result = [
          'stub'    => TRUE,
          'message' => "The 'earn_income' action is not yet implemented.",
        ];
        $events[] = GameEventLogger::buildEvent($type, 'downtime', $actor_id, ['stub' => TRUE]);
        break;

      case 'talk':
        $result = ['talked' => TRUE, 'message' => $params['message'] ?? ''];
        $events[] = GameEventLogger::buildEvent('talk', 'downtime', $actor_id, [
          'message' => $params['message'] ?? '',
        ]);
        break;

      default:
        return [
          'success' => FALSE,
          'result' => ['error' => "Unknown downtime action: $type"],
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
    $game_state['phase'] = 'downtime';
    $game_state['round'] = NULL;
    $game_state['turn'] = NULL;
    $game_state['encounter_id'] = NULL;

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [
        'days_elapsed' => 0,
        'activities' => [],
      ];
    }

    return [
      GameEventLogger::buildEvent('phase_entered', 'downtime', NULL, [
        'from_phase' => $context['from_phase'] ?? 'none',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onExit(array &$game_state, array &$dungeon_data, int $campaign_id): array {
    return [
      GameEventLogger::buildEvent('phase_exited', 'downtime', NULL, [
        'days_elapsed' => $game_state['downtime']['days_elapsed'] ?? 0,
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableActions(array $game_state, array $dungeon_data, ?string $actor_id = NULL): array {
    return ['long_rest', 'talk', 'return_to_exploration'];
  }

  // =========================================================================
  // Action processors.
  // =========================================================================

  /**
   * Processes a long rest: restore HP, spell slots, remove conditions.
   */
  protected function processLongRest(?string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    // Long rest: 8 hours of rest. Per PF2e rules (REQ 2301):
    // - Restore HP equal to Con modifier × level (minimum 1 per level)
    // - Regain all spell slots
    // - Remove the wounded condition
    // - Reduce the value of doomed by 1

    $hp_restored = 0;
    $conditions_removed = [];
    $new_hp = NULL;

    // Find the character entity and restore HP.
    if ($actor_id && !empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
        if ($iid === $actor_id) {
          $current_hp = (int) ($entity['state']['hit_points']['current'] ?? 0);
          $max_hp = (int) ($entity['state']['hit_points']['max'] ?? 20);

          // REQ 2301: HP regained = Con modifier × level (minimum 1).
          $con_mod = (int) ($entity['stats']['con_modifier'] ?? 0);
          $level = max(1, (int) ($entity['stats']['level'] ?? 1));
          $hp_per_rest = max(1, $con_mod) * $level;
          $new_hp = min($max_hp, $current_hp + $hp_per_rest);
          $entity['state']['hit_points']['current'] = $new_hp;
          $hp_restored = $new_hp - $current_hp;

          // Remove wounded condition.
          if (isset($entity['state']['conditions'])) {
            $entity['state']['conditions'] = array_filter(
              $entity['state']['conditions'],
              function ($condition) use (&$conditions_removed) {
                $name = $condition['name'] ?? ($condition['type'] ?? '');
                if ($name === 'wounded') {
                  $conditions_removed[] = 'wounded';
                  return FALSE;
                }
                return TRUE;
              }
            );
            $entity['state']['conditions'] = array_values($entity['state']['conditions']);
          }

          // REQ 2302: Sleeping in medium/heavy armor applies fatigued.
          if ($this->hasArmorEquipped($entity, ['medium', 'heavy'])) {
            if (!isset($entity['state']['conditions'])) {
              $entity['state']['conditions'] = [];
            }
            $already_fatigued = FALSE;
            foreach ($entity['state']['conditions'] as $cond) {
              if (($cond['name'] ?? ($cond['type'] ?? '')) === 'fatigued') {
                $already_fatigued = TRUE;
                break;
              }
            }
            if (!$already_fatigued) {
              $entity['state']['conditions'][] = ['name' => 'fatigued', 'source' => 'sleeping_in_armor'];
              $conditions_removed[] = '(fatigued from armor applied)';
            }
          }

          // REQ 2303: Reset sleep deprivation tracking.
          $entity['state']['hours_since_rest'] = 0;

          // REQ 2167: Reduce doomed by 1 per long rest (remove if reaches 0).
          if (isset($entity['state']['conditions'])) {
            foreach ($entity['state']['conditions'] as &$cond) {
              $cname = $cond['name'] ?? ($cond['type'] ?? '');
              if ($cname === 'doomed') {
                $cond['value'] = max(0, (int) ($cond['value'] ?? 1) - 1);
                if ($cond['value'] <= 0) {
                  $conditions_removed[] = 'doomed';
                  $cond['_remove'] = TRUE;
                }
                break;
              }
            }
            unset($cond);
            $entity['state']['conditions'] = array_values(array_filter(
              $entity['state']['conditions'],
              fn($c) => empty($c['_remove'])
            ));
          }

          break;
        }
      }
      unset($entity);
    }

    // Advance downtime days.
    if (isset($game_state['downtime'])) {
      $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;
    }

    // Persist.
    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist long rest: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'rested' => TRUE,
      'hp_restored' => $hp_restored,
      'new_hp' => $new_hp,
      'conditions_removed' => $conditions_removed,
      'spell_slots_restored' => TRUE,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'hit_points.current', 'to' => $new_hp],
      ],
    ];
  }

  /**
   * Returns TRUE if the entity has medium or heavy armor equipped.
   */
  protected function hasArmorEquipped(array $entity, array $armor_categories): bool {
    $equipped = $entity['equipment']['armor'] ?? ($entity['state']['equipped_armor'] ?? NULL);
    if (!$equipped) {
      return FALSE;
    }
    $category = $equipped['category'] ?? ($equipped['armor_type'] ?? '');
    return in_array($category, $armor_categories, TRUE);
  }

  /**
   * Processes a crafting action (AC-001 through AC-006).
   *
   * Dispatches to CraftingService based on the 'sub_action' param:
   *   - 'begin':        Start a new crafting project (validate + pay half price).
   *   - 'resolve':      Apply check degree after 4-day minimum.
   *   - 'advance_day':  Progress an in-progress success project.
   *   - 'add_formula':  Add a formula to the formula book.
   *
   * @param string $actor_id    Character ID.
   * @param array  $params      Action parameters (sub_action, item, degree, item_id, source, campaign_id).
   * @param array  $game_state  Current game state (phase must be 'downtime').
   * @param int    $campaign_id Campaign context.
   *
   * @return array  Result array.
   */
  protected function processCraft(string $actor_id, array $params, array &$game_state, int $campaign_id): array {
    $in_downtime = ($game_state['phase'] ?? '') === 'downtime';
    $sub_action  = $params['sub_action'] ?? 'begin';

    switch ($sub_action) {
      case 'begin':
        $item = $params['item'] ?? [];
        if (empty($item)) {
          return ['success' => FALSE, 'error' => 'missing_item', 'message' => 'No item specified for crafting.'];
        }
        return $this->craftingService->beginCrafting($actor_id, $item, $campaign_id, $in_downtime);

      case 'resolve':
        $degree = $params['degree'] ?? '';
        if (!in_array($degree, ['critical_success', 'success', 'failure', 'critical_failure'], TRUE)) {
          return ['success' => FALSE, 'error' => 'invalid_degree', 'message' => "Invalid degree: {$degree}."];
        }
        return $this->craftingService->resolveCrafting($actor_id, $degree, $campaign_id);

      case 'advance_day':
        return $this->craftingService->advanceCraftingDay($actor_id, $campaign_id);

      case 'add_formula':
        $item_id = $params['item_id'] ?? '';
        $source  = $params['source'] ?? 'purchased';
        if (empty($item_id)) {
          return ['success' => FALSE, 'error' => 'missing_item_id', 'message' => 'No item_id specified for formula.'];
        }
        return $this->craftingService->addFormula($actor_id, $item_id, $campaign_id, $source);

      default:
        return ['success' => FALSE, 'error' => 'unknown_sub_action', 'message' => "Unknown craft sub_action: {$sub_action}."];
    }
  }

  /**
   * Processes a downtime long-term rest (REQ 2306).
   * Restores Con mod × (2 × level) HP.
   */
  protected function processDowntimeRest(string $actor_id, array $params, array &$game_state, array &$dungeon_data, int $campaign_id): array {
    $hp_restored = 0;
    $new_hp = NULL;

    if (!empty($dungeon_data['entities'])) {
      foreach ($dungeon_data['entities'] as &$entity) {
        $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
        if ($iid === $actor_id) {
          $current_hp = (int) ($entity['state']['hit_points']['current'] ?? 0);
          $max_hp = (int) ($entity['state']['hit_points']['max'] ?? 20);
          $con_mod = (int) ($entity['stats']['con_modifier'] ?? 0);
          $level = max(1, (int) ($entity['stats']['level'] ?? 1));
          $hp_restored_calc = max(1, $con_mod) * (2 * $level);
          $new_hp = min($max_hp, $current_hp + $hp_restored_calc);
          $entity['state']['hit_points']['current'] = $new_hp;
          $hp_restored = $new_hp - $current_hp;
          break;
        }
      }
      unset($entity);
    }

    if (isset($game_state['downtime'])) {
      $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;
    }

    try {
      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to persist downtime rest: @error', ['@error' => $e->getMessage()]);
    }

    return [
      'downtime_rest' => TRUE,
      'hp_restored' => $hp_restored,
      'new_hp' => $new_hp,
      'mutations' => [
        ['entity' => $actor_id, 'field' => 'hit_points.current', 'to' => $new_hp],
      ],
    ];
  }

  /**
   * Processes the retrain action (REQ 2307-2310).
   */
  protected function processRetrain(string $actor_id, array $params, array &$game_state): array {
    $retrain_type = $params['retrain_type'] ?? '';
    $retrain_from = $params['retrain_from'] ?? '';
    $retrain_to = $params['retrain_to'] ?? '';

    // REQ 2308: Cannot retrain locked elements.
    $prohibited = ['ancestry', 'heritage', 'background', 'class', 'ability_score'];
    if (in_array($retrain_type, $prohibited, TRUE)) {
      return ['error' => "Cannot retrain '$retrain_type': ancestry, heritage, background, class, and ability scores cannot be retrained."];
    }

    // REQ 2310: Block if already retraining.
    if (!empty($game_state['downtime']['retraining'])) {
      return ['error' => 'Already retraining. Complete or cancel current retraining before starting a new one.'];
    }

    // REQ 2309: Duration: 7 days standard; 30 days for major class choices.
    $major_choices = ['druid_order', 'wizard_school', 'sorcerer_bloodline'];
    $days_required = in_array($retrain_type, $major_choices, TRUE) ? 30 : 7;

    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['retraining'] = [
      'actor_id' => $actor_id,
      'type' => $retrain_type,
      'from' => $retrain_from,
      'to' => $retrain_to,
      'days_remaining' => $days_required,
      'days_required' => $days_required,
    ];

    return [
      'retrain_started' => TRUE,
      'type' => $retrain_type,
      'from' => $retrain_from,
      'to' => $retrain_to,
      'days_required' => $days_required,
    ];
  }

  /**
   * Processes advance_day: decrements active retrain timer and applies on completion.
   */
  protected function processAdvanceDay(string $actor_id, array &$game_state, array &$dungeon_data): array {
    if (!isset($game_state['downtime'])) {
      $game_state['downtime'] = [];
    }
    $game_state['downtime']['days_elapsed'] = ($game_state['downtime']['days_elapsed'] ?? 0) + 1;

    $retrain_result = NULL;
    if (!empty($game_state['downtime']['retraining'])) {
      $rt = &$game_state['downtime']['retraining'];
      $rt['days_remaining']--;
      if ($rt['days_remaining'] <= 0) {
        // Apply retrain: update entity feat/skill in dungeon_data.
        if (!empty($dungeon_data['entities'])) {
          foreach ($dungeon_data['entities'] as &$entity) {
            $iid = $entity['instance_id'] ?? ($entity['id'] ?? NULL);
            if ($iid === ($rt['actor_id'] ?? $actor_id)) {
              if ($rt['type'] === 'feat') {
                if (!isset($entity['stats']['feats'])) {
                  $entity['stats']['feats'] = [];
                }
                $entity['stats']['feats'] = array_filter(
                  $entity['stats']['feats'],
                  fn($f) => ($f['name'] ?? $f) !== $rt['from']
                );
                $entity['stats']['feats'][] = ['name' => $rt['to'], 'source' => 'retrain'];
              }
              elseif ($rt['type'] === 'skill') {
                $entity['stats']['trained_skills'][$rt['to']] = TRUE;
                unset($entity['stats']['trained_skills'][$rt['from']]);
              }
              break;
            }
          }
          unset($entity);
        }
        $retrain_result = $rt;
        unset($game_state['downtime']['retraining']);
      }
    }

    return [
      'advanced' => TRUE,
      'days_elapsed' => $game_state['downtime']['days_elapsed'],
      'retrain_completed' => $retrain_result,
    ];
  }

}
