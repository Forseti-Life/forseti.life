<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Rules Engine service - Validates actions against PF2e rules.
 *
 * @see /docs/dungeoncrawler/issues/combat-action-validation.md
 */
class RulesEngine {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Validate action.
   *
   * 6-layer validation: state → economy → conditions → prerequisites → resources → targets
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md
   */
  public function validateAction($participant_id, $action, $encounter_id) {
    // TODO: Implement 6-layer validation pipeline
    return ['is_valid' => TRUE, 'reason' => ''];
  }

  /**
   * Validate action economy.
   *
   * Enforces PF2E three-action economy: 3 actions + 1 reaction per turn.
   * Valid costs: 1, 2, 3 (integer actions), 'free' (no cost), 'reaction'.
   *
   * @param array $participant Participant state array with keys:
   *   - actions_remaining (int 0–3)
   *   - reaction_available (bool/int)
   * @param int|string $action_cost 1, 2, 3, 'free', or 'reaction'.
   *
   * @return array ['is_valid' => bool, 'reason' => string, 'actions_after' => int|null]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#action-economy-validation
   */
  public function validateActionEconomy($participant, $action_cost) {
    $valid_costs = [1, 2, 3, 'free', 'reaction'];
    if (!in_array($action_cost, $valid_costs, TRUE)) {
      return [
        'is_valid' => FALSE,
        'reason' => 'Invalid action cost: ' . json_encode($action_cost) . '. Must be 1, 2, 3, "free", or "reaction".',
        'actions_after' => NULL,
      ];
    }

    $actions_remaining = (int) ($participant['actions_remaining'] ?? 0);

    if ($action_cost === 'free') {
      return ['is_valid' => TRUE, 'reason' => '', 'actions_after' => $actions_remaining];
    }

    if ($action_cost === 'reaction') {
      $available = !empty($participant['reaction_available']);
      return [
        'is_valid' => $available,
        'reason' => $available ? '' : 'Reaction already used this turn.',
        'actions_after' => $actions_remaining,
      ];
    }

    // Integer action cost (1, 2, or 3).
    $cost = (int) $action_cost;
    if ($actions_remaining < $cost) {
      return [
        'is_valid' => FALSE,
        'reason' => 'Not enough actions. Need ' . $cost . ', have ' . $actions_remaining . '.',
        'actions_after' => $actions_remaining,
      ];
    }

    $after = max(0, $actions_remaining - $cost);
    return ['is_valid' => TRUE, 'reason' => '', 'actions_after' => $after];
  }

  /**
   * Validate action prerequisites.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#action-prerequisite-rules
   */
  public function validateActionPrerequisites($participant, $action, $target) {
    // TODO: Check weapon equipped, spell slots, etc.
    return ['is_valid' => TRUE, 'reason' => ''];
  }

  /**
   * Check condition restrictions.
   *
   * Queries active combat_conditions for the participant and returns the most
   * restrictive applicable restriction for the given action type.
   *
   * Rules implemented:
   *   - paralyzed  → cannot_act (all actions blocked)
   *   - unconscious → cannot_act (all actions blocked)
   *   - petrified  → cannot_act (all actions blocked)
   *   - dying      → cannot_act (all actions blocked while dying)
   *   - grabbed    → cannot_move (movement actions blocked)
   *   - immobilized → cannot_move
   *   - restrained → cannot_move
   *
   * @param array|object $participant Participant row; must contain 'id' and 'encounter_id'.
   * @param string $action_type The action type being attempted.
   *
   * @return array ['can_act' => bool, 'restriction' => string]
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#condition-restriction-rules
   */
  public function checkConditionRestrictions($participant, $action_type) {
    $participant = (array) $participant;
    $participant_id = (int) ($participant['id'] ?? 0);
    $encounter_id   = (int) ($participant['encounter_id'] ?? 0);

    if (!$participant_id || !$encounter_id) {
      return ['can_act' => TRUE, 'restriction' => ''];
    }

    $blocking_act  = ['paralyzed', 'unconscious', 'petrified', 'dying'];
    $blocking_move = ['grabbed', 'immobilized', 'restrained'];

    $rows = $this->database->select('combat_conditions', 'c')
      ->fields('c', ['condition_type'])
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->isNull('removed_at_round')
      ->execute()
      ->fetchCol();

    foreach ($blocking_act as $cond) {
      if (in_array($cond, $rows, TRUE)) {
        return ['can_act' => FALSE, 'restriction' => "Cannot act: {$cond}"];
      }
    }

    $move_actions = ['move', 'stride', 'step', 'crawl', 'fly', 'swim'];
    if (in_array($action_type, $move_actions, TRUE)) {
      foreach ($blocking_move as $cond) {
        if (in_array($cond, $rows, TRUE)) {
          return ['can_act' => FALSE, 'restriction' => "Cannot move: {$cond}"];
        }
      }
    }

    return ['can_act' => TRUE, 'restriction' => ''];
  }

  /**
   * Check immunities.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md
   */
  public function checkImmunities($participant, $effect_type, $effect_source) {
    // TODO: Check participant immunities
    return ['is_immune' => FALSE];
  }

  /**
   * Validate attack.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#attack-validation
   */
  public function validateAttack($attacker, $target, $weapon, $encounter_id) {
    // TODO: Check can attack, target valid, in range, line of sight
    return ['is_valid' => TRUE, 'modifiers' => []];
  }

  /**
   * Validate spell cast.
   *
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#spell-validation
   */
  public function validateSpellCast($caster, $spell, $spell_level, array $targets, $encounter_id) {
    // TODO: Check spell slots, not silenced, targets valid, range
    return ['is_valid' => TRUE, 'reason' => ''];
  }

}
