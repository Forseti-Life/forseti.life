<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\CombatCalculator;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\ConditionManager;
use Drupal\dungeoncrawler_content\Service\HPManager;
use Drupal\dungeoncrawler_content\Service\RulesEngine;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Action Processor service - Executes and validates combat actions.
 *
 * @see /docs/dungeoncrawler/issues/combat-engine-service.md (ActionProcessor)
 * @see /docs/dungeoncrawler/issues/combat-action-validation.md
 */
class ActionProcessor {

  protected $calculator;
  protected $hpManager;
  protected $conditionManager;
  protected $logger;
  protected $store;
  protected $numberGeneration;
  protected $rulesEngine;

  public function __construct(CombatCalculator $calculator, HPManager $hp_manager, ConditionManager $condition_manager, LoggerChannelFactoryInterface $logger_factory, CombatEncounterStore $store, NumberGenerationService $number_generation, RulesEngine $rules_engine) {
    $this->calculator = $calculator;
    $this->hpManager = $hp_manager;
    $this->conditionManager = $condition_manager;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->store = $store;
    $this->numberGeneration = $number_generation;
    $this->rulesEngine = $rules_engine;
  }

  /**
   * Execute combat action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executeaction
   */
  public function executeAction($encounter_id, $participant_id, $action_type, array $action_data) {
    switch ($action_type) {
      case 'stride':
        return $this->executeStride($participant_id, $action_data['distance'] ?? 0, $action_data['path'] ?? [], $encounter_id);

      case 'strike':
        return $this->executeStrike($participant_id, $action_data['target_id'] ?? NULL, $action_data, $encounter_id);

      default:
        return ['status' => 'error', 'message' => 'Unsupported action type'];
    }
  }

  /**
   * Execute Strike action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestrike
   */
  public function executeStrike($attacker_id, $target_id, $weapon, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $attacker = $this->findParticipant($participants, $attacker_id);
    $target = $this->findParticipant($participants, $target_id);

    if (!$attacker || !$target) {
      return ['status' => 'error', 'message' => 'Attacker or target not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $attacker_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $economy = $this->rulesEngine->validateActionEconomy($attacker, 1);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    $attack_number = (int) ($attacker['attacks_this_turn'] ?? 0) + 1;
    $is_agile = !empty($weapon['is_agile']);
    $map_penalty = $this->calculator->calculateMultipleAttackPenalty($attack_number, $is_agile);

    $base_attack_bonus = (int) ($weapon['attack_bonus'] ?? 0);
    $attacker_mod = $this->conditionManager->getConditionModifiers($attacker_id, 'attack', $encounter_id);
    $target_ac_mod = $this->conditionManager->getConditionModifiers($target_id, 'ac', $encounter_id);

    $roll_natural = isset($weapon['natural_roll'])
      ? max(1, min(20, (int) $weapon['natural_roll']))
      : $this->numberGeneration->rollPathfinderDie(20);
    $attack_total = $roll_natural + $base_attack_bonus + $attacker_mod + $map_penalty;

    $target_ac = (int) ($target['ac'] ?? 10) + $target_ac_mod;
    $degree = $this->calculator->calculateDegreeOfSuccess($attack_total, $target_ac, $roll_natural);

    $base_damage = isset($weapon['damage']) ? (int) $weapon['damage'] : 0;
    $damage = 0;
    if ($degree === 'success') {
      $damage = $base_damage;
    }
    elseif ($degree === 'critical_success') {
      $damage = $base_damage * 2;
    }

    $damage_result = NULL;
    if ($damage > 0) {
      $damage_result = $this->hpManager->applyDamage($target_id, $damage, $weapon['damage_type'] ?? 'physical', ['action' => 'strike', 'attacker' => $attacker_id], $encounter_id);
    }

    $actions_left = max(0, ((int) $attacker['actions_remaining']) - 1);
    $this->store->updateParticipant($attacker_id, [
      'actions_remaining' => $actions_left,
      'attacks_this_turn' => $attack_number,
    ]);

    $this->logAction($encounter_id, $attacker_id, 'strike', $target_id, $weapon, [
      'roll' => $roll_natural,
      'total' => $attack_total,
      'map' => $map_penalty,
      'degree' => $degree,
      'target_ac' => $target_ac,
      'damage' => $damage,
      'damage_result' => $damage_result,
    ]);

    return [
      'status' => 'ok',
      'degree' => $degree,
      'attack_roll' => $attack_total,
      'natural_roll' => $roll_natural,
      'target_ac' => $target_ac,
      'damage' => $damage,
      'damage_result' => $damage_result,
      'actions_remaining' => $actions_left,
      'attacks_this_turn' => $attack_number,
    ];
  }

  /**
   * Execute Stride action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executestride
   */
  public function executeStride($participant_id, $distance, array $path, $encounter_id) {
    $state = $this->loadEncounterState($encounter_id);
    if ($state['status'] === 'error') {
      return $state;
    }

    [$encounter, $participants] = $state['data'];
    $actor = $this->findParticipant($participants, $participant_id);
    if (!$actor) {
      return ['status' => 'error', 'message' => 'Participant not found'];
    }

    if (!$this->isCurrentTurn($encounter, $participants, $participant_id)) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $economy = $this->rulesEngine->validateActionEconomy($actor, 1);
    if (!$economy['is_valid']) {
      return ['status' => 'error', 'message' => $economy['reason']];
    }

    $end = $this->lastPathCoordinate($path);
    $actions_left = max(0, ((int) $actor['actions_remaining']) - 1);

    $this->store->updateParticipant($participant_id, [
      'actions_remaining' => $actions_left,
      'position_q' => $end['q'],
      'position_r' => $end['r'],
    ]);

    $this->logAction($encounter_id, $participant_id, 'stride', NULL, ['distance' => $distance, 'path' => $path], [
      'end_position' => $end,
    ]);

    return [
      'status' => 'ok',
      'end_position' => $end,
      'actions_remaining' => $actions_left,
    ];
  }

  /**
   * Execute Cast Spell action.
   *
   * @see /docs/dungeoncrawler/issues/combat-engine-service.md#executecastspell
   */
  public function executeCastSpell($caster_id, $spell_id, $spell_level, array $targets, $encounter_id) {
    // TODO: Implement spell casting with slot management
    return [];
  }

  protected function loadEncounterState(int $encounter_id): array {
    $encounter = $this->store->loadEncounter($encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }
    $participants = $encounter['participants'] ?? [];
    return ['status' => 'ok', 'data' => [$encounter, $participants]];
  }

  protected function findParticipant(array $participants, int $id): ?array {
    foreach ($participants as $p) {
      if ((int) $p['id'] === (int) $id) {
        return $p;
      }
    }
    return NULL;
  }

  protected function isCurrentTurn(array $encounter, array $participants, int $participant_id): bool {
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $current = $participants[$turn_index] ?? NULL;
    return $current && (int) $current['id'] === (int) $participant_id;
  }

  protected function lastPathCoordinate(array $path): array {
    if (empty($path)) {
      return ['q' => NULL, 'r' => NULL];
    }
    $last = end($path);
    return [
      'q' => isset($last['q']) ? (int) $last['q'] : NULL,
      'r' => isset($last['r']) ? (int) $last['r'] : NULL,
    ];
  }

  protected function logAction(int $encounter_id, int $participant_id, string $action_type, ?int $target_id, array $payload, array $result): void {
    try {
      $this->store->logAction([
        'encounter_id' => $encounter_id,
        'participant_id' => $participant_id,
        'action_type' => $action_type,
        'target_id' => $target_id,
        'payload' => json_encode($payload),
        'result' => json_encode($result),
      ]);
    }
    catch (\Throwable $t) {
      $this->logger->warning('Failed to log combat action: @msg', ['@msg' => $t->getMessage()]);
    }
  }

}
