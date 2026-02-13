<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Calculator service - All combat-related calculations and formulas.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (Calculator)
 */
class Calculator {

  /**
   * Calculate initiative.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculateinitiative
   */
  public function calculateInitiative($perception_modifier, array $bonuses = []) {
    // TODO: Roll d20 + perception_modifier + sum(bonuses)
    return 0;
  }

  /**
   * Sort initiative order.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#sortinitiativeorder
   */
  public function sortInitiativeOrder(array $participants) {
    // TODO: Sort by initiative_total DESC, tiebreaker DESC
    return [];
  }

  /**
   * Calculate attack bonus.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculateattackbonus
   */
  public function calculateAttackBonus($proficiency, $ability_mod, $item_bonus, $map, array $bonuses, array $penalties) {
    // TODO: proficiency + ability_mod + item_bonus - map + bonuses - penalties
    return 0;
  }

  /**
   * Calculate Multiple Attack Penalty.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculatemap
   */
  public function calculateMAP($attacks_this_turn, $is_agile_weapon) {
    // TODO: First: 0, Second: -5/-4, Third: -10/-8
    return 0;
  }

  /**
   * Determine degree of success.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#determinedegreeofs uccess
   */
  public function determineDegreeOfSuccess($roll, $dc, $is_natural_1, $is_natural_20) {
    // TODO: Critical success/success/failure/critical failure
    return 'success';
  }

  /**
   * Roll damage.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#rolldamage
   */
  public function rollDamage($damage_dice, $ability_modifier, array $bonuses = []) {
    // TODO: Roll dice + ability_modifier + bonuses
    return 0;
  }

  /**
   * Apply critical damage.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applycriticaldamage
   */
  public function applyCriticalDamage(array $base_damage_rolls, $static_modifiers) {
    // TODO: Double dice rolls (not modifiers)
    return 0;
  }

  /**
   * Apply resistances and weaknesses.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyresistancesweaknesses
   */
  public function applyResistancesWeaknesses($damage, $damage_type, array $resistances, array $weaknesses) {
    // TODO: Apply resistance then weakness
    return $damage;
  }

  /**
   * Calculate AC.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#calculateac
   */
  public function calculateAC($base_ac, $dex_mod, $armor_bonus, $shield_raised, array $conditions) {
    // TODO: 10 + dex_mod + armor + shield - condition penalties
    return 10;
  }

}
