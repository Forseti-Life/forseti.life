<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Service for calculating character statistics.
 *
 * Implements PF2e rules for character stat calculations including HP, AC,
 * ability modifiers, proficiency bonuses, and other derived statistics.
 *
 * @see docs/dungeoncrawler/issues/issue-testing-strategy-design.md
 *   Section: "Mock Service Designs" - CharacterCalculator Service
 *
 * Design Reference:
 * - Testing Strategy: Unit Tests (80% coverage target, 90% for services)
 * - Mock Strategy: Use test fixtures, no database operations
 * - PF2e Rules: HP calculation, ability modifiers, proficiency
 */
class CharacterCalculator {

  /**
   * Calculate total hit points for a character.
   *
   * Formula: (class_hp * level) + (con_modifier * level) + ancestry_hp + other
   *
   * @param array $characterData
   *   Character data array containing:
   *   - class_hp: int - Base HP from class
   *   - level: int - Character level
   *   - abilities: array - Ability scores
   *   - ancestry_hp_bonus: int - HP bonus from ancestry
   *
   * @return array
   *   HP calculation result:
   *   - total: int - Total HP
   *   - breakdown: array - Detailed breakdown of HP sources
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   hp_calculation section for official PF2e rules
   *
   * @see docs/dungeoncrawler/testing/fixtures/characters/level_1_fighter.json
   *   Example: Fighter with 10 base HP, 16 CON = 13 total HP
   *
   * TODO: Implement per design document Section "Service Layer Design"
   */
  public function calculateHP(array $characterData): array {
    // PSEUDOCODE:
    // 1. Extract class_hp, level, CON score, ancestry_hp_bonus
    // 2. Calculate CON modifier using calculateAbilityModifier()
    // 3. Calculate: (class_hp * level) + (con_mod * level) + ancestry_bonus
    // 4. Return array with 'total' and 'breakdown' keys
    
    throw new \Exception('Not yet implemented - see design doc section "Service Layer Design"');
  }

  /**
   * Calculate ability modifier from ability score.
   *
   * Formula: floor((score - 10) / 2)
   * Per PF2e Core Rulebook pp. 20-21
   *
   * @param int $score
   *   Ability score (1-30, typically 8-18 for starting characters).
   *
   * @return int
   *   Ability modifier (-5 to +10).
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ability_scores.modifiers section for official table
   *
   * Examples:
   * - Score 10 → Modifier 0
   * - Score 18 → Modifier 4
   * - Score 8 → Modifier -1
   *
   * TODO: Implement per PF2e Core Rulebook rules
   */
  public function calculateAbilityModifier(int $score): int {
    // PSEUDOCODE:
    // return floor(($score - 10) / 2);
    
    throw new \Exception('Not yet implemented - see PF2e Core Rulebook pp. 20-21');
  }

  /**
   * Apply ability boost per PF2e rules.
   *
   * Boosts add +2 to scores under 18, or +1 to scores at 18 or higher.
   * Per PF2e Core Rulebook p. 20
   *
   * @param int $score
   *   Current ability score.
   *
   * @return int
   *   New ability score after boost.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ability_scores.boost_rules section
   *
   * TODO: Implement boost rules
   */
  public function applyAbilityBoost(int $score): int {
    // PSEUDOCODE:
    // if ($score < 18) {
    //   return $score + 2;
    // }
    // return $score + 1;
    
    throw new \Exception('Not yet implemented - see design doc PF2e boost rules');
  }

  /**
   * Calculate proficiency bonus.
   *
   * Formula: proficiency_rank_bonus + level
   * Ranks: Untrained (0), Trained (2), Expert (4), Master (6), Legendary (8)
   *
   * @param string $rank
   *   Proficiency rank (untrained, trained, expert, master, legendary).
   * @param int $level
   *   Character level.
   *
   * @return int
   *   Total proficiency bonus.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   proficiency_ranks section
   *
   * TODO: Implement proficiency calculation
   */
  public function calculateProficiencyBonus(string $rank, int $level): int {
    // PSEUDOCODE:
    // $rankBonuses = [
    //   'untrained' => 0, 'trained' => 2, 'expert' => 4,
    //   'master' => 6, 'legendary' => 8
    // ];
    // return ($rankBonuses[$rank] ?? 0) + $level;
    
    throw new \Exception('Not yet implemented - see proficiency rules');
  }

  /**
   * Calculate armor class.
   *
   * Formula: 10 + DEX + armor + shield + proficiency + level + other
   *
   * @param array $characterData
   *   Character data with DEX, armor bonuses, proficiency.
   *
   * @return array
   *   AC calculation with total and breakdown.
   *
   * @see docs/dungeoncrawler/testing/fixtures/pf2e_reference/core_mechanics.json
   *   ac_calculation section
   *
   * TODO: Implement AC calculation
   */
  public function calculateArmorClass(array $characterData): array {
    // PSEUDOCODE:
    // 1. Base 10
    // 2. Add DEX modifier
    // 3. Add armor bonus
    // 4. Add shield bonus
    // 5. Add proficiency bonus
    // 6. Add level
    // 7. Return total and breakdown
    
    throw new \Exception('Not yet implemented - see AC calculation design');
  }

}
