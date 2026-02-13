<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * HP Manager service - Manage hit point changes and dying/wounded conditions.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (HPManager)
 */
class HPManager {

  protected $database;
  protected $conditionManager;

  public function __construct(Connection $database, ConditionManager $condition_manager) {
    $this->database = $database;
    $this->conditionManager = $condition_manager;
  }

  /**
   * Apply damage.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applydamage
   */
  public function applyDamage($participant_id, $damage, $damage_type, $source, $encounter_id) {
    // TODO: Apply to temp HP, then current HP, check death/dying
    return ['final_damage' => 0, 'new_hp' => 0, 'new_status' => ''];
  }

  /**
   * Apply healing.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyhealing
   */
  public function applyHealing($participant_id, $healing, $source, $encounter_id) {
    // TODO: Increase current_hp (cap at max), remove dying if applicable
    return ['healing_applied' => 0, 'new_hp' => 0];
  }

  /**
   * Apply temporary HP.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applytemporaryhp
   */
  public function applyTemporaryHP($participant_id, $temp_hp, $source, $encounter_id) {
    // TODO: Take higher value (doesn't stack)
    return 0;
  }

  /**
   * Check death condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#checkdeathcondition
   */
  public function checkDeathCondition($participant_id, $encounter_id) {
    // TODO: Check HP <= -max_hp or dying >= 4
    return ['is_dead' => FALSE, 'death_reason' => ''];
  }

  /**
   * Apply dying condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applydyingcondition
   */
  public function applyDyingCondition($participant_id, $dying_value, $encounter_id) {
    // TODO: Set unconscious, dying, prone, add wounded if applicable
    return TRUE;
  }

  /**
   * Stabilize character.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#stabilizecharacter
   */
  public function stabilizeCharacter($participant_id, $encounter_id) {
    // TODO: Remove dying, add wounded, set HP to 1
    return TRUE;
  }

}
