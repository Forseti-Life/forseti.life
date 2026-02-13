<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for combat-related calculations.
 *
 * Implements PF2e combat rules including attack bonuses, multiple attack
 * penalty, degrees of success, and other combat mechanics.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "PF2e Rules Validation Tests" - Combat Calculations
 *
 * Design Reference:
 * - Multiple Attack Penalty rules (PF2e Core Rulebook p. 446)
 * - Degrees of Success (PF2e Core Rulebook p. 445)
 * - Attack rolls and modifiers
 */
class CombatCalculator {

  /**
   * Calculate multiple attack penalty.
   *
   * First attack: no penalty
   * Second attack: -5 (normal) or -4 (agile)
   * Third+ attack: -10 (normal) or -8 (agile)
   *
   * Per PF2e Core Rulebook p. 446
   *
   * @param int $attackNumber
   *   Which attack in the turn (1, 2, 3+).
   * @param bool $isAgile
   *   Whether the weapon has the agile trait.
   *
   * @return int
   *   Penalty to apply to the attack roll.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   multiple_attack_penalty section
   *
   * TODO: Implement MAP calculation
   */
  public function calculateMultipleAttackPenalty(int $attackNumber, bool $isAgile = FALSE): int {
    // PSEUDOCODE:
    // if ($attackNumber === 1) return 0;
    // if ($isAgile) {
    //   return $attackNumber === 2 ? -4 : -8;
    // }
    // return $attackNumber === 2 ? -5 : -10;
    
    throw new \Exception('Not yet implemented - see MAP rules');
  }

  /**
   * Determine degree of success.
   *
   * Critical Success: Beat DC by 10+, or natural 20
   * Success: Meet or beat DC
   * Failure: Below DC
   * Critical Failure: Miss DC by 10+, or natural 1
   *
   * Per PF2e Core Rulebook p. 445
   *
   * @param int $result
   *   Total roll result.
   * @param int $dc
   *   Difficulty class.
   * @param int|null $naturalRoll
   *   Natural die roll (1-20), or NULL if not applicable.
   *
   * @return string
   *   'critical_success', 'success', 'failure', or 'critical_failure'.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   degrees_of_success section
   *
   * TODO: Implement degree of success logic
   */
  public function calculateDegreeOfSuccess(int $result, int $dc, ?int $naturalRoll = NULL): string {
    // PSEUDOCODE:
    // 1. Check for natural 20 → critical success
    // 2. Check for natural 1 → critical failure
    // 3. Check if result >= DC + 10 → critical success
    // 4. Check if result < DC - 10 → critical failure
    // 5. Check if result >= DC → success
    // 6. Otherwise → failure
    
    throw new \Exception('Not yet implemented - see degrees of success rules');
  }

  /**
   * Calculate attack bonus.
   *
   * Formula: ability_mod + proficiency + level + item + other
   *
   * @param array $attackData
   *   Attack data including ability modifier, proficiency, level, bonuses.
   *
   * @return int
   *   Total attack bonus.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   attack_bonus_calculation section
   *
   * TODO: Implement attack bonus calculation
   */
  public function calculateAttackBonus(array $attackData): int {
    // PSEUDOCODE:
    // 1. Sum ability modifier
    // 2. Add proficiency bonus
    // 3. Add level
    // 4. Add item bonus
    // 5. Add other bonuses
    // 6. Return total
    
    throw new \Exception('Not yet implemented - see attack bonus design');
  }

  /**
   * Calculate spell save DC.
   *
   * Formula: 10 + ability_mod + proficiency + level + item
   *
   * @param array $casterData
   *   Caster data including spellcasting ability, proficiency, level.
   *
   * @return int
   *   Spell save DC.
   *
   * TODO: Implement spell DC calculation
   */
  public function calculateSpellSaveDC(array $casterData): int {
    // PSEUDOCODE:
    // return 10 + ability_mod + proficiency + level + item_bonus;
    
    throw new \Exception('Not yet implemented - see spell DC design');
  }

}
