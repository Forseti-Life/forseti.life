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
    if ($attackNumber <= 1) {
      return 0;
    }

    if ($isAgile) {
      return $attackNumber === 2 ? -4 : -8;
    }

    return $attackNumber === 2 ? -5 : -10;
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
    $difference = $result - $dc;

    if ($difference >= 10) {
      $degree = 'critical_success';
    }
    elseif ($difference >= 0) {
      $degree = 'success';
    }
    elseif ($difference <= -10) {
      $degree = 'critical_failure';
    }
    else {
      $degree = 'failure';
    }

    if ($naturalRoll !== NULL) {
      if ($naturalRoll === 20) {
        $degree = $this->bumpDegree($degree, 1);
      }
      elseif ($naturalRoll === 1) {
        $degree = $this->bumpDegree($degree, -1);
      }
    }

    return $degree;
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
    $ability = (int) ($attackData['ability_modifier'] ?? 0);
    $proficiency = (int) ($attackData['proficiency_bonus'] ?? 0);
    $level = (int) ($attackData['level'] ?? 0);
    $item = (int) ($attackData['item_bonus'] ?? 0);
    $other = (int) ($attackData['other_bonuses'] ?? 0);

    return $ability + $proficiency + $level + $item + $other;
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
    $ability = (int) ($casterData['ability_modifier'] ?? 0);
    $proficiency = (int) ($casterData['proficiency_bonus'] ?? 0);
    $level = (int) ($casterData['level'] ?? 0);
    $item = (int) ($casterData['item_bonus'] ?? 0);
    $other = (int) ($casterData['other_bonuses'] ?? 0);

    return 10 + $ability + $proficiency + $level + $item + $other;
  }

  /**
   * Shift success degree up or down one step.
   */
  protected function bumpDegree(string $degree, int $steps): string {
    $order = [
      'critical_failure',
      'failure',
      'success',
      'critical_success',
    ];

    $currentIndex = array_search($degree, $order, TRUE);
    if ($currentIndex === FALSE) {
      return $degree;
    }

    $newIndex = max(0, min(count($order) - 1, $currentIndex + $steps));
    return $order[$newIndex];
  }

}
