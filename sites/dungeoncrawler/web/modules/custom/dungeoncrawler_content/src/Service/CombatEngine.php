<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\ConditionManager;

/**
 * Combat Engine service - Main orchestrator for combat operations.
 *
 * Coordinates encounter lifecycle, round management, and turn management.
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatEngine {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\StateManager
   */
  protected $stateManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ActionProcessor
   */
  protected $actionProcessor;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $store;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected $hpManager;
  protected $numberGeneration;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatCalculator
   */
  protected $combatCalculator;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ConditionManager
   */
  protected $conditionManager;

  public function __construct(Connection $database, StateManager $state_manager, ActionProcessor $action_processor, CombatEncounterStore $store, HPManager $hp_manager, NumberGenerationService $number_generation, CombatCalculator $combat_calculator = NULL, ConditionManager $condition_manager = NULL) {
    $this->database = $database;
    $this->stateManager = $state_manager;
    $this->actionProcessor = $action_processor;
    $this->store = $store;
    $this->hpManager = $hp_manager;
    $this->numberGeneration = $number_generation;
    $this->combatCalculator = $combat_calculator ?? new CombatCalculator();
    $this->conditionManager = $condition_manager;
  }

  /**
   * Create new combat encounter and insert participants.
   */
  public function createEncounter($campaign_id, $encounter_name, array $participants, array $settings = []) {
    $room_id = $settings['room_id'] ?? (is_string($encounter_name) ? $encounter_name : NULL);
    return $this->store->createEncounter($campaign_id, $room_id, $participants);
  }

  /**
   * Start combat encounter: auto-roll or apply custom initiatives, activate, and start round 1.
   *
   * Initiative = d20 + perception modifier for any participant without a custom initiative.
   * Ties are broken by perception modifier (higher wins), then arbitrarily by participant ID.
   */
  public function startEncounter($encounter_id, array $custom_initiatives = []) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    foreach ($encounter['participants'] as $participant) {
      $pid = (int) $participant['id'];
      if (isset($custom_initiatives[$pid])) {
        $this->store->updateParticipant($pid, ['initiative' => (int) $custom_initiatives[$pid]]);
      }
      else {
        // Auto-roll: Perception check = d20 + perception modifier.
        // Perception modifier is stored in entity_ref JSON or defaults to 0.
        $perception_mod = $this->resolvePerceptionModifier($participant);
        $roll = $this->numberGeneration->rollPathfinderDie(20);
        $initiative = $roll + $perception_mod;
        $this->store->updateParticipant($pid, [
          'initiative' => $initiative,
          'initiative_roll' => $roll,
        ]);
      }
    }

    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'active',
      'current_round' => 1,
      'turn_index' => 0,
    ]);

    $state = $this->startRound((int) $encounter_id, 1);
    return ['status' => 'ok', 'encounter' => $state];
  }

  /**
   * Begin a new round: sort initiative order, reset action economy, and set turn_index to 0.
   *
   * Sort: descending initiative. Ties: higher perception modifier wins; then lower participant ID.
   */
  public function startRound($encounter_id, $round_number) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    // Sort participants by initiative descending; ties broken by perception mod then ID.
    $participants = $encounter['participants'];
    usort($participants, function (array $a, array $b): int {
      $init_diff = (int) ($b['initiative'] ?? 0) - (int) ($a['initiative'] ?? 0);
      if ($init_diff !== 0) {
        return $init_diff;
      }
      $perc_diff = $this->resolvePerceptionModifier($b) - $this->resolvePerceptionModifier($a);
      if ($perc_diff !== 0) {
        return $perc_diff;
      }
      return (int) ($a['id'] ?? 0) - (int) ($b['id'] ?? 0);
    });

    foreach ($participants as $participant) {
      $this->store->updateParticipant((int) $participant['id'], [
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
      ]);
    }

    $this->store->updateEncounter((int) $encounter_id, [
      'current_round' => (int) $round_number,
      'turn_index' => 0,
    ]);

    return $this->store->loadEncounter((int) $encounter_id) ?: [];
  }

  /**
   * End round and advance to the next.
   */
  public function endRound($encounter_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $next_round = ((int) ($encounter['current_round'] ?? 1)) + 1;
    $state = $this->startRound((int) $encounter_id, $next_round);

    return [
      'status' => 'ok',
      'next_round' => $next_round,
      'encounter' => $state,
    ];
  }

  /**
   * Start participant's turn.
   */
  public function startTurn($encounter_id, $participant_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $participants = $encounter['participants'] ?? [];
    $current = $participants[$turn_index] ?? NULL;

    if (!$current || (int) $current['id'] !== (int) $participant_id) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $this->store->updateParticipant((int) $participant_id, [
      'actions_remaining' => 3,
      'attacks_this_turn' => 0,
      'reaction_available' => 1,
    ]);

    return [
      'status' => 'ok',
      'participant_id' => (int) $participant_id,
      'turn_state' => 'awaiting_action',
      'actions_remaining' => 3,
      'reaction_available' => TRUE,
      'attacks_this_turn' => 0,
      'current_round' => (int) ($encounter['current_round'] ?? 1),
    ];
  }

  /**
   * End participant's turn, apply end-of-turn effects, and advance initiative.
   */
  public function endTurn($encounter_id, $participant_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $participants = $encounter['participants'] ?? [];
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $current = $participants[$turn_index] ?? NULL;

    if (!$current || (int) $current['id'] !== (int) $participant_id) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $end_effects = $this->processEndOfTurnEffects((int) $participant_id, (int) $encounter_id, (int) ($encounter['current_round'] ?? 1));

    // Reload encounter state after effects to capture defeated participants.
    $encounter_after = $this->store->loadEncounter((int) $encounter_id);
    $participants_after = $encounter_after['participants'] ?? [];

    $outcome = $this->evaluateEncounterOutcome($participants_after);
    if ($outcome['ended']) {
      $summary = $this->endEncounter((int) $encounter_id, $outcome['outcome'], $outcome['victory_condition']);
      return [
        'status' => 'ok',
        'turn_ended' => TRUE,
        'end_of_turn_effects' => $end_effects,
        'encounter_ended' => TRUE,
        'summary' => $summary,
      ];
    }

    $next_index = $turn_index + 1;
    $current_round = (int) ($encounter['current_round'] ?? 1);
    if ($next_index >= count($participants_after)) {
      $current_round += 1;
      $this->startRound((int) $encounter_id, $current_round);
      $next_index = 0;
    }
    else {
      $this->store->updateEncounter((int) $encounter_id, [
        'turn_index' => $next_index,
        'current_round' => $current_round,
      ]);
    }

    $next_state = $this->store->loadEncounter((int) $encounter_id);
    $next_participant = $next_state['participants'][$next_index] ?? NULL;

    return [
      'status' => 'ok',
      'turn_ended' => TRUE,
      'end_of_turn_effects' => $end_effects,
      'next_turn' => [
        'participant_id' => $next_participant ? (int) $next_participant['id'] : NULL,
        'turn_index' => $next_index,
        'current_round' => (int) ($next_state['current_round'] ?? $current_round),
      ],
    ];
  }

  /**
   * Delay participant's turn (stub).
   */
  public function delayTurn($encounter_id, $participant_id) {
    return TRUE;
  }

  /**
   * Resume from delay (stub).
   */
  public function resumeFromDelay($encounter_id, $participant_id, $new_initiative) {
    return [];
  }

  /**
   * Pause combat encounter.
   */
  public function pauseEncounter($encounter_id, $reason) {
    return $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'paused',
    ]);
  }

  /**
   * Resume paused encounter.
   */
  public function resumeEncounter($encounter_id) {
    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'active',
    ]);

    return $this->store->loadEncounter((int) $encounter_id) ?: [];
  }

  /**
   * End combat encounter and return summary.
   */
  public function endEncounter($encounter_id, $outcome, $victory_condition) {
    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'ended',
      'updated' => time(),
    ]);

    $encounter = $this->store->loadEncounter((int) $encounter_id);
    $summary = [
      'encounter_id' => $encounter_id,
      'outcome' => $outcome,
      'victory_condition' => $victory_condition,
      'rounds' => $encounter['current_round'] ?? NULL,
      'participants' => $encounter['participants'] ?? [],
    ];

    // TODO: Compute XP awards based on defeated enemies (PF2e encounter XP tables) and attach to summary.
    return $summary;
  }

  /**
   * Apply persistent damage and decrement durations.
   * Also triggers end-of-turn valued condition tick via ConditionManager.
   */
  protected function processEndOfTurnEffects(int $participant_id, int $encounter_id, int $current_round): array {
    $effects = [
      'persistent_damage' => [],
      'expired_conditions' => [],
      'ticked_conditions' => [],
    ];

    $conditions = $this->store->listActiveConditions($participant_id);

    foreach ($conditions as $condition) {
      if ($condition['condition_type'] === 'persistent_damage') {
        $damage = (int) ($condition['value'] ?? 0);
        $result = $this->hpManager->applyDamage($participant_id, $damage, 'persistent', ['condition' => 'persistent_damage'], $encounter_id);
        $flat_check = $this->numberGeneration->rollPathfinderDie(20);
        $cleared = $flat_check >= 15;

        if ($cleared) {
          $this->store->removeCondition((int) $condition['id'], $current_round);
        }

        $effects['persistent_damage'][] = [
          'condition_id' => (int) $condition['id'],
          'damage' => $result,
          'flat_check' => $flat_check,
          'cleared' => $cleared,
        ];
      }

      if (!empty($condition['duration_type']) && $condition['duration_type'] === 'rounds' && $condition['duration_remaining'] !== NULL) {
        $remaining = (int) $condition['duration_remaining'] - 1;
        if ($remaining <= 0) {
          $this->store->removeCondition((int) $condition['id'], $current_round);
          $effects['expired_conditions'][] = [
            'condition_id' => (int) $condition['id'],
            'condition_type' => $condition['condition_type'],
          ];
        }
        else {
          $this->database->update('combat_conditions')
            ->fields([
              'duration_remaining' => $remaining,
              'updated' => time(),
            ])
            ->condition('id', (int) $condition['id'])
            ->execute();
        }
      }
    }

    // Tick valued end_of_turn conditions (frightened, clumsy, etc.) via ConditionManager.
    if ($this->conditionManager) {
      $effects['ticked_conditions'] = $this->conditionManager->tickConditions($participant_id, $encounter_id);
    }

    return $effects;
  }

  /**
   * Resolve an attack roll and apply damage on hit.
   *
   * PF2E rules:
   *   Roll = d20 + attack_bonus + MAP penalty
   *   vs. target AC; natural 20 bumps degree up; natural 1 bumps down.
   *
   * @param int $participant_id  Attacker participant row ID.
   * @param int $target_id       Defender participant row ID.
   * @param array $weapon        ['attack_bonus'=>int,'damage_dice'=>'1d6','damage_type'=>'slashing','is_agile'=>bool]
   * @param int $encounter_id
   *
   * @return array ['roll','attack_bonus','map_penalty','total','target_ac','degree','damage_dealt','damage_result','error']
   */
  public function resolveAttack(int $participant_id, int $target_id, array $weapon, int $encounter_id): array {
    $attacker = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$attacker) {
      return ['error' => "Attacker participant {$participant_id} not found in encounter {$encounter_id}"];
    }

    $target = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', $target_id)
      ->condition('encounter_id', $encounter_id)
      ->execute()
      ->fetchAssoc();

    if (!$target) {
      return ['error' => "Target participant {$target_id} not found in encounter {$encounter_id}"];
    }

    $attacks_this_turn = (int) ($attacker['attacks_this_turn'] ?? 0) + 1;
    $is_agile = !empty($weapon['is_agile']);
    $map_penalty = $this->combatCalculator->calculateMultipleAttackPenalty($attacks_this_turn, $is_agile);

    $natural_roll = $this->numberGeneration->rollPathfinderDie(20);
    $attack_bonus = (int) ($weapon['attack_bonus'] ?? 0);
    $total = $natural_roll + $attack_bonus + $map_penalty;
    $target_ac = (int) ($target['ac'] ?? 10);

    $degree = $this->combatCalculator->calculateDegreeOfSuccess($total, $target_ac, $natural_roll);

    // Record attack in participant state.
    $this->store->updateParticipant($participant_id, [
      'attacks_this_turn' => $attacks_this_turn,
      'actions_remaining' => max(0, (int) ($attacker['actions_remaining'] ?? 3) - 1),
    ]);

    $damage_dealt = NULL;
    $damage_result = NULL;

    if ($degree === 'critical_success' || $degree === 'success') {
      $damage_roll = $this->numberGeneration->rollNotation($weapon['damage_dice'] ?? '1d4');
      $dice_total = array_sum($damage_roll['rolls'] ?? [$damage_roll['total'] ?? 1]);
      $ability_mod = (int) ($weapon['ability_modifier'] ?? $damage_roll['modifier'] ?? 0);
      $damage_type = $weapon['damage_type'] ?? 'untyped';
      if ($degree === 'critical_success') {
        // PF2E req 2115: double dice only, then add flat bonuses once.
        $damage_dealt = $this->calculator->applyCriticalDamage($damage_roll['rolls'] ?? [], $ability_mod)['doubled_total'];
      }
      else {
        $damage_dealt = $dice_total + $ability_mod;
      }
      $damage_result = $this->hpManager->applyDamage($target_id, $damage_dealt, $damage_type, ['attacker' => $participant_id], $encounter_id);
    }

    return [
      'roll'          => $natural_roll,
      'attack_bonus'  => $attack_bonus,
      'map_penalty'   => $map_penalty,
      'total'         => $total,
      'target_ac'     => $target_ac,
      'degree'        => $degree,
      'damage_dealt'  => $damage_dealt,
      'damage_result' => $damage_result,
      'error'         => NULL,
    ];
  }

  /**
   * Resolve a participant's Perception modifier from entity_ref JSON (defaults to 0).
   */
  protected function resolvePerceptionModifier(array $participant): int {
    if (!empty($participant['entity_ref'])) {
      $entity = json_decode($participant['entity_ref'], TRUE);
      if (is_array($entity)) {
        return (int) ($entity['perception_modifier'] ?? $entity['perception_mod'] ?? 0);
      }
    }
    return 0;
  }

  /**
   * Determine whether the encounter has ended.
   */
  protected function evaluateEncounterOutcome(array $participants): array {
    $active_teams = [];
    foreach ($participants as $p) {
      if (empty($p['is_defeated'])) {
        $team = $p['team'] ?? 'neutral';
        $active_teams[$team] = TRUE;
      }
    }

    $team_count = count($active_teams);
    if ($team_count === 0) {
      return ['ended' => TRUE, 'outcome' => 'draw', 'victory_condition' => 'all combatants down'];
    }

    if ($team_count === 1) {
      $team = array_keys($active_teams)[0];
      return ['ended' => TRUE, 'outcome' => 'victory', 'victory_condition' => "team {$team} stands"];
    }

    return ['ended' => FALSE, 'outcome' => NULL, 'victory_condition' => NULL];
  }

}
