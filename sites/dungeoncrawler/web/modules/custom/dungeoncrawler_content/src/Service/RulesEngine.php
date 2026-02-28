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
   * @see /docs/dungeoncrawler/issues/combat-action-validation.md#condition-restriction-rules
   */
  public function checkConditionRestrictions($participant, $action_type) {
    // TODO: Check paralyzed, unconscious, immobilized, etc.
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
