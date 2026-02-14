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
    $now = time();
    [$duration_type, $duration_remaining] = $this->normalizeDuration($duration);
    $applied_at_round = $this->getCurrentRound($encounter_id);

    return (int) $this->database->insert('combat_conditions')
      ->fields([
        'participant_id' => $participant_id,
        'encounter_id' => $encounter_id,
        'condition_type' => $condition_type,
        'value' => is_numeric($value) ? (int) $value : NULL,
        'duration_type' => $duration_type,
        'duration_remaining' => $duration_remaining,
        'source' => is_string($source) ? $source : json_encode($source),
        'applied_at_round' => $applied_at_round,
        'removed_at_round' => NULL,
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();
  }

  /**
   * Remove condition.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#removecondition
   */
  public function removeCondition($participant_id, $condition_id, $encounter_id) {
    $now = time();
    $removed_at_round = $this->getCurrentRound($encounter_id);

    $count = $this->database->update('combat_conditions')
      ->fields([
        'removed_at_round' => $removed_at_round,
        'updated' => $now,
      ])
      ->condition('id', $condition_id)
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->execute();

    return $count > 0;
  }

  /**
   * Apply condition effects.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#applyconditioneffects
   */
  public function applyConditionEffects($participant, $condition_type, $value) {
    $value = (int) ($value ?? 1);
    $effects = [];

    switch ($condition_type) {
      case 'frightened':
        $penalty = -abs($value);
        $effects['checks'] = $penalty; // All checks
        $effects['dcs'] = $penalty; // All DCs including AC
        break;

      case 'clumsy':
        $penalty = -abs($value);
        $effects['dex_checks'] = $penalty;
        $effects['ac'] = $penalty;
        break;

      case 'enfeebled':
        $penalty = -abs($value);
        $effects['str_checks'] = $penalty;
        $effects['melee_attack'] = $penalty;
        break;

      case 'stupefied':
        $penalty = -abs($value);
        $effects['spell_dc'] = $penalty;
        $effects['spell_attack'] = $penalty;
        break;

      case 'flat_footed':
        $effects['ac'] = -2;
        break;

      case 'prone':
        $effects['ac'] = -2;
        $effects['attack'] = -2;
        break;

      default:
        break;
    }

    return $effects;
  }

  /**
   * Get condition modifiers.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#getconditionmodifiers
   */
  public function getConditionModifiers($participant_id, $stat_type, $encounter_id) {
    $conditions = $this->database->select('combat_conditions', 'c')
      ->fields('c')
      ->condition('participant_id', $participant_id)
      ->condition('encounter_id', $encounter_id)
      ->condition('removed_at_round', NULL, 'IS')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $modifier = 0;
    foreach ($conditions as $condition) {
      $effects = $this->applyConditionEffects($condition, $condition['condition_type'], $condition['value']);
      foreach ($effects as $effect_type => $value) {
        if ($effect_type === $stat_type || $effect_type === 'all') {
          $modifier += $value;
        }

        if ($effect_type === 'checks' && in_array($stat_type, ['attack', 'melee_attack', 'ranged_attack', 'skill', 'perception', 'saving_throw'], TRUE)) {
          $modifier += $value;
        }

        if ($effect_type === 'dcs' && in_array($stat_type, ['ac', 'spell_dc', 'save_dc'], TRUE)) {
          $modifier += $value;
        }

        if ($stat_type === 'ac' && $effect_type === 'dex_checks') {
          $modifier += $value;
        }
      }
    }

    return $modifier;
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

  protected function getCurrentRound(int $encounter_id): int {
    $round = $this->database->select('combat_encounters', 'e')
      ->fields('e', ['current_round'])
      ->condition('id', $encounter_id)
      ->execute()
      ->fetchField();

    return $round !== FALSE ? (int) $round : 0;
  }

  protected function normalizeDuration($duration): array {
    if (is_array($duration)) {
      $type = $duration['type'] ?? ($duration['duration_type'] ?? NULL);
      $remaining = $duration['remaining'] ?? ($duration['duration'] ?? $duration['value'] ?? NULL);
      return [$type, $remaining !== NULL ? (int) $remaining : NULL];
    }

    if (is_string($duration)) {
      return [$duration, NULL];
    }

    if (is_numeric($duration)) {
      return [NULL, (int) $duration];
    }

    return [NULL, NULL];
  }

}
