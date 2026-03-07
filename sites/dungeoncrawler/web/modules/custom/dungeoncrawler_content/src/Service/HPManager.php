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
   * PF2e: Temp HP absorbs damage first. Remaining damage reduces current HP.
   * If HP drops to 0 or below, dying condition is applied.
   * If HP drops to negative of max HP or less, instant death (massive damage).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applydamage
   */
  public function applyDamage($participant_id, $damage, $damage_type, $source, $encounter_id) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['final_damage' => 0, 'new_hp' => 0, 'new_status' => 'not_found'];
    }

    $now = time();
    $base_hp = (int) ($participant['hp'] ?? 0);
    $max_hp = (int) ($participant['max_hp'] ?? 0);
    $temp_hp = (int) ($participant['temp_hp'] ?? 0);
    $damage = max(0, (int) $damage);

    // PF2e: Temp HP absorbs damage first.
    $temp_absorbed = 0;
    if ($temp_hp > 0 && $damage > 0) {
      $temp_absorbed = min($temp_hp, $damage);
      $remaining_damage = $damage - $temp_absorbed;
      $new_temp_hp = $temp_hp - $temp_absorbed;
    }
    else {
      $remaining_damage = $damage;
      $new_temp_hp = $temp_hp;
    }

    $new_hp = $base_hp - $remaining_damage;
    $is_defeated = $new_hp <= 0 ? 1 : (int) ($participant['is_defeated'] ?? 0);

    $txn = $this->database->startTransaction();

    $this->database->update('combat_participants')
      ->fields([
        'hp' => $new_hp,
        'temp_hp' => $new_temp_hp,
        'is_defeated' => $is_defeated,
        'updated' => $now,
      ])
      ->condition('id', $participant_id)
      ->execute();

    $this->database->insert('combat_damage_log')
      ->fields([
        'encounter_id' => $encounter_id,
        'participant_id' => $participant_id,
        'amount' => $damage,
        'damage_type' => $damage_type,
        'source' => is_string($source) ? $source : json_encode($source),
        'hp_before' => $base_hp,
        'hp_after' => $new_hp,
        'created' => $now,
      ])
      ->execute();

    if ($is_defeated) {
      $this->conditionManager->applyCondition($participant_id, 'dying', 1, ['type' => 'encounter', 'remaining' => NULL], $source, $encounter_id);
    }

    $death_state = $this->checkDeathCondition($participant_id, $encounter_id, $new_hp, $max_hp);

    return [
      'final_damage' => $damage,
      'hp_damage' => $remaining_damage,
      'temp_hp_used' => $temp_absorbed,
      'new_hp' => $new_hp,
      'new_temp_hp' => $new_temp_hp,
      'new_status' => $death_state['is_dead'] ? 'dead' : ($is_defeated ? 'defeated' : 'active'),
      'death_reason' => $death_state['death_reason'],
    ];
  }

  /**
   * Apply healing.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyhealing
   */
  public function applyHealing($participant_id, $healing, $source, $encounter_id) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['healing_applied' => 0, 'new_hp' => 0];
    }

    $now = time();
    $base_hp = (int) ($participant['hp'] ?? 0);
    $max_hp = (int) ($participant['max_hp'] ?? $base_hp);
    $healing = max(0, (int) $healing);
    $new_hp = $max_hp > 0 ? min($base_hp + $healing, $max_hp) : $base_hp + $healing;

    $this->database->update('combat_participants')
      ->fields([
        'hp' => $new_hp,
        'is_defeated' => $new_hp > 0 ? 0 : (int) ($participant['is_defeated'] ?? 0),
        'updated' => $now,
      ])
      ->condition('id', $participant_id)
      ->execute();

    return [
      'healing_applied' => $new_hp - $base_hp,
      'new_hp' => $new_hp,
    ];
  }

  /**
   * Apply temporary HP.
   *
   * PF2e: Temp HP does not stack. If the participant already has temp HP,
   * keep whichever value is higher (new or existing). Temp HP cannot be
   * restored by healing.
   *
   * @param int $participant_id
   *   The combat participant ID.
   * @param int $temp_hp
   *   The temp HP amount to grant.
   * @param string|array $source
   *   Source of temp HP (e.g. "False Life spell").
   * @param int $encounter_id
   *   The encounter ID.
   *
   * @return array
   *   Keys: temp_hp_before, temp_hp_after, applied (bool).
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applytemporaryhp
   */
  public function applyTemporaryHP($participant_id, $temp_hp, $source, $encounter_id) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['temp_hp_before' => 0, 'temp_hp_after' => 0, 'applied' => FALSE];
    }

    $current_temp = (int) ($participant['temp_hp'] ?? 0);
    $new_temp = max(0, (int) $temp_hp);

    // PF2e: Temp HP doesn't stack — take the higher value.
    if ($new_temp <= $current_temp) {
      return [
        'temp_hp_before' => $current_temp,
        'temp_hp_after' => $current_temp,
        'applied' => FALSE,
      ];
    }

    $this->database->update('combat_participants')
      ->fields([
        'temp_hp' => $new_temp,
        'updated' => time(),
      ])
      ->condition('id', $participant_id)
      ->execute();

    return [
      'temp_hp_before' => $current_temp,
      'temp_hp_after' => $new_temp,
      'applied' => TRUE,
    ];
  }

  /**
   * Check death condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#checkdeathcondition
   */
  public function checkDeathCondition($participant_id, $encounter_id, ?int $hp_override = NULL, ?int $max_hp_override = NULL) {
    $participant = $this->loadParticipant($participant_id);
    if (!$participant) {
      return ['is_dead' => FALSE, 'death_reason' => ''];
    }

    $hp = $hp_override ?? (int) ($participant['hp'] ?? 0);
    $max_hp = $max_hp_override ?? (int) ($participant['max_hp'] ?? 0);

    return $this->evaluateDeath($hp, $max_hp);
  }

  /**
   * Apply dying condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applydyingcondition
   */
  public function applyDyingCondition($participant_id, $dying_value, $encounter_id) {
    // PF2E: wounded condition increases dying value at start.
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);
    $wounded_value = 0;
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'wounded') {
        $wounded_value = max($wounded_value, (int) ($cond['value'] ?? 0));
      }
    }

    $effective_dying = $dying_value + $wounded_value;

    $this->conditionManager->applyCondition($participant_id, 'dying', $effective_dying, ['type' => 'encounter', 'remaining' => NULL], 'dying_condition', $encounter_id);
    $this->conditionManager->applyCondition($participant_id, 'unconscious', 0, ['type' => 'encounter', 'remaining' => NULL], 'dying_condition', $encounter_id);
    $this->conditionManager->applyCondition($participant_id, 'prone', 0, ['type' => 'encounter', 'remaining' => NULL], 'dying_condition', $encounter_id);

    return TRUE;
  }

  /**
   * Stabilize character.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#stabilizecharacter
   */
  public function stabilizeCharacter($participant_id, $encounter_id) {
    // PF2E: on stabilize — remove dying, apply wounded (value = prior dying − 1), set HP to 1.
    $active = $this->conditionManager->getActiveConditions($participant_id, $encounter_id);
    $dying_value = 0;
    foreach ($active as $cond) {
      if ($cond['condition_type'] === 'dying') {
        $dying_value = max($dying_value, (int) ($cond['value'] ?? 1));
        $this->conditionManager->removeCondition($participant_id, (int) $cond['id'], $encounter_id);
      }
    }

    $wounded_stacks = max(0, $dying_value - 1);
    if ($wounded_stacks > 0) {
      $this->conditionManager->applyCondition($participant_id, 'wounded', $wounded_stacks, ['type' => 'persistent', 'remaining' => NULL], 'stabilize', $encounter_id);
    }

    $this->database->update('combat_participants')
      ->fields(['hp' => 1, 'is_defeated' => 0, 'updated' => time()])
      ->condition('id', $participant_id)
      ->execute();

    return TRUE;
  }

  protected function loadParticipant($participant_id): ?array {
    $record = $this->database->select('combat_participants', 'p')
      ->fields('p')
      ->condition('id', $participant_id)
      ->execute()
      ->fetchAssoc();

    return $record ?: NULL;
  }

  protected function evaluateDeath(int $hp, int $max_hp): array {
    if ($max_hp > 0 && $hp <= -1 * $max_hp) {
      return ['is_dead' => TRUE, 'death_reason' => 'hp_threshold'];
    }
    return ['is_dead' => FALSE, 'death_reason' => ''];
  }

}
