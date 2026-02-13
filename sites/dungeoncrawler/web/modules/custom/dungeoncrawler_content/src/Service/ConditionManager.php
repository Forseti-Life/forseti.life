<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Condition Manager service - Manage combat conditions and effects.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ConditionManager)
 * @see /docs/dungeoncrawler/issues/combat-database-schema.md (combat_conditions)
 */
class ConditionManager {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Apply condition to participant.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applycondition
   */
  public function applyCondition($participant_id, $condition_type, $value, $duration, $source, $encounter_id) {
    // TODO: Insert condition, check immunities, apply effects
    return 0;
  }

  /**
   * Remove condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#removecondition
   */
  public function removeCondition($participant_id, $condition_id, $encounter_id) {
    // TODO: Mark removed, restore stats
    return TRUE;
  }

  /**
   * Apply condition effects.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyconditioneffects
   */
  public function applyConditionEffects($participant, $condition_type, $value) {
    // TODO: Apply stat modifications based on condition
    // Frightened, clumsy, enfeebled, flat-footed, etc.
    return [];
  }

  /**
   * Get condition modifiers.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getconditionmodifiers
   */
  public function getConditionModifiers($participant_id, $stat_type, $encounter_id) {
    // TODO: Aggregate modifiers with stacking rules
    return 0;
  }

  /**
   * Process persistent damage.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processpersistentdamage
   */
  public function processPersistentDamage($participant_id, $encounter_id) {
    // TODO: Apply damage, roll flat check DC 15
    return [];
  }

  /**
   * Process dying condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#processdyingcondition
   */
  public function processDyingCondition($participant_id, $constitution_modifier, $encounter_id) {
    // TODO: Roll recovery check, adjust dying value
    return [];
  }

}
