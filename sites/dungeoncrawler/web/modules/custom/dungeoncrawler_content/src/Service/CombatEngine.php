<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\HPManager;

/**
 * Combat Engine service - Main orchestrator for combat operations.
 *
 * Coordinates encounter lifecycle, round management, and turn management.
 * @see /docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md
 */
class CombatEngine {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\StateManager
   */
  protected $stateManager;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\ActionProcessor
   */
  protected $actionProcessor;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $store;

  /**
   * @var \Drupal\dungeoncrawler_content\Service\HPManager
   */
  protected $hpManager;
  protected $numberGeneration;

  public function __construct(Connection $database, StateManager $state_manager, ActionProcessor $action_processor, CombatEncounterStore $store, HPManager $hp_manager, NumberGenerationService $number_generation) {
    $this->database = $database;
    $this->stateManager = $state_manager;
    $this->actionProcessor = $action_processor;
    $this->store = $store;
    $this->hpManager = $hp_manager;
    $this->numberGeneration = $number_generation;
  }

  /**
   * Create new combat encounter and insert participants.
   */
  public function createEncounter($campaign_id, $encounter_name, array $participants, array $settings = []) {
    $room_id = $settings['room_id'] ?? NULL;
    return $this->store->createEncounter($campaign_id, $room_id, $participants);
  }

  /**
   * Start combat encounter: apply custom initiatives, activate, and start round 1.
   */
  public function startEncounter($encounter_id, array $custom_initiatives = []) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    foreach ($custom_initiatives as $pid => $initiative) {
      $this->store->updateParticipant((int) $pid, ['initiative' => (int) $initiative]);
    }

    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'active',
      'current_round' => 1,
      'turn_index' => 0,
    ]);

    $state = $this->startRound((int) $encounter_id, 1);
    return ['status' => 'ok', 'encounter' => $state];
  }

  /**
   * Begin a new round: reset action economy and set turn order to the start.
   */
  public function startRound($encounter_id, $round_number) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    foreach ($encounter['participants'] as $participant) {
      $this->store->updateParticipant((int) $participant['id'], [
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
      ]);
    }

    $this->store->updateEncounter((int) $encounter_id, [
      'current_round' => (int) $round_number,
      'turn_index' => 0,
    ]);

    return $this->store->loadEncounter((int) $encounter_id) ?: [];
  }

  /**
   * End round and advance to the next.
   */
  public function endRound($encounter_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $next_round = ((int) ($encounter['current_round'] ?? 1)) + 1;
    $state = $this->startRound((int) $encounter_id, $next_round);

    return [
      'status' => 'ok',
      'next_round' => $next_round,
      'encounter' => $state,
    ];
  }

  /**
   * Start participant's turn.
   */
  public function startTurn($encounter_id, $participant_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $participants = $encounter['participants'] ?? [];
    $current = $participants[$turn_index] ?? NULL;

    if (!$current || (int) $current['id'] !== (int) $participant_id) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $this->store->updateParticipant((int) $participant_id, [
      'actions_remaining' => 3,
      'attacks_this_turn' => 0,
    ]);

    return [
      'status' => 'ok',
      'participant_id' => (int) $participant_id,
      'turn_state' => 'awaiting_action',
      'actions_remaining' => 3,
      'attacks_this_turn' => 0,
      'current_round' => (int) ($encounter['current_round'] ?? 1),
    ];
  }

  /**
   * End participant's turn, apply end-of-turn effects, and advance initiative.
   */
  public function endTurn($encounter_id, $participant_id) {
    $encounter = $this->store->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return ['status' => 'error', 'message' => 'Encounter not found'];
    }

    $participants = $encounter['participants'] ?? [];
    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $current = $participants[$turn_index] ?? NULL;

    if (!$current || (int) $current['id'] !== (int) $participant_id) {
      return ['status' => 'error', 'message' => 'Not this participant\'s turn'];
    }

    $end_effects = $this->processEndOfTurnEffects((int) $participant_id, (int) $encounter_id, (int) ($encounter['current_round'] ?? 1));

    // Reload encounter state after effects to capture defeated participants.
    $encounter_after = $this->store->loadEncounter((int) $encounter_id);
    $participants_after = $encounter_after['participants'] ?? [];

    $outcome = $this->evaluateEncounterOutcome($participants_after);
    if ($outcome['ended']) {
      $summary = $this->endEncounter((int) $encounter_id, $outcome['outcome'], $outcome['victory_condition']);
      return [
        'status' => 'ok',
        'turn_ended' => TRUE,
        'end_of_turn_effects' => $end_effects,
        'encounter_ended' => TRUE,
        'summary' => $summary,
      ];
    }

    $next_index = $turn_index + 1;
    $current_round = (int) ($encounter['current_round'] ?? 1);
    if ($next_index >= count($participants_after)) {
      $current_round += 1;
      $this->startRound((int) $encounter_id, $current_round);
      $next_index = 0;
    }
    else {
      $this->store->updateEncounter((int) $encounter_id, [
        'turn_index' => $next_index,
        'current_round' => $current_round,
      ]);
    }

    $next_state = $this->store->loadEncounter((int) $encounter_id);
    $next_participant = $next_state['participants'][$next_index] ?? NULL;

    return [
      'status' => 'ok',
      'turn_ended' => TRUE,
      'end_of_turn_effects' => $end_effects,
      'next_turn' => [
        'participant_id' => $next_participant ? (int) $next_participant['id'] : NULL,
        'turn_index' => $next_index,
        'current_round' => (int) ($next_state['current_round'] ?? $current_round),
      ],
    ];
  }

  /**
   * Delay participant's turn (stub).
   */
  public function delayTurn($encounter_id, $participant_id) {
    return TRUE;
  }

  /**
   * Resume from delay (stub).
   */
  public function resumeFromDelay($encounter_id, $participant_id, $new_initiative) {
    return [];
  }

  /**
   * Pause combat encounter.
   */
  public function pauseEncounter($encounter_id, $reason) {
    return $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'paused',
    ]);
  }

  /**
   * Resume paused encounter.
   */
  public function resumeEncounter($encounter_id) {
    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'active',
    ]);

    return $this->store->loadEncounter((int) $encounter_id) ?: [];
  }

  /**
   * End combat encounter and return summary.
   */
  public function endEncounter($encounter_id, $outcome, $victory_condition) {
    $this->store->updateEncounter((int) $encounter_id, [
      'status' => 'ended',
      'updated' => time(),
    ]);

    $encounter = $this->store->loadEncounter((int) $encounter_id);
    $summary = [
      'encounter_id' => $encounter_id,
      'outcome' => $outcome,
      'victory_condition' => $victory_condition,
      'rounds' => $encounter['current_round'] ?? NULL,
      'participants' => $encounter['participants'] ?? [],
    ];

    // TODO: Compute XP awards based on defeated enemies (PF2e encounter XP tables) and attach to summary.
    return $summary;
  }

  /**
   * Apply persistent damage and decrement durations.
   */
  protected function processEndOfTurnEffects(int $participant_id, int $encounter_id, int $current_round): array {
    $effects = [
      'persistent_damage' => [],
      'expired_conditions' => [],
    ];

    $conditions = $this->store->listActiveConditions($participant_id);

    foreach ($conditions as $condition) {
      if ($condition['condition_type'] === 'persistent_damage') {
        $damage = (int) ($condition['value'] ?? 0);
        $result = $this->hpManager->applyDamage($participant_id, $damage, 'persistent', ['condition' => 'persistent_damage'], $encounter_id);
        $flat_check = $this->numberGeneration->rollPathfinderDie(20);
        $cleared = $flat_check >= 15;

        if ($cleared) {
          $this->store->removeCondition((int) $condition['id'], $current_round);
        }

        $effects['persistent_damage'][] = [
          'condition_id' => (int) $condition['id'],
          'damage' => $result,
          'flat_check' => $flat_check,
          'cleared' => $cleared,
        ];
      }

      if (!empty($condition['duration_type']) && $condition['duration_type'] === 'rounds' && $condition['duration_remaining'] !== NULL) {
        $remaining = (int) $condition['duration_remaining'] - 1;
        if ($remaining <= 0) {
          $this->store->removeCondition((int) $condition['id'], $current_round);
          $effects['expired_conditions'][] = [
            'condition_id' => (int) $condition['id'],
            'condition_type' => $condition['condition_type'],
          ];
        }
        else {
          $this->database->update('combat_conditions')
            ->fields([
              'duration_remaining' => $remaining,
              'updated' => time(),
            ])
            ->condition('id', (int) $condition['id'])
            ->execute();
        }
      }
    }

    return $effects;
  }

  /**
   * Determine whether the encounter has ended.
   */
  protected function evaluateEncounterOutcome(array $participants): array {
    $active_teams = [];
    foreach ($participants as $p) {
      if (empty($p['is_defeated'])) {
        $team = $p['team'] ?? 'neutral';
        $active_teams[$team] = TRUE;
      }
    }

    $team_count = count($active_teams);
    if ($team_count === 0) {
      return ['ended' => TRUE, 'outcome' => 'draw', 'victory_condition' => 'all combatants down'];
    }

    if ($team_count === 1) {
      $team = array_keys($active_teams)[0];
      return ['ended' => TRUE, 'outcome' => 'victory', 'victory_condition' => "team {$team} stands"];
    }

    return ['ended' => FALSE, 'outcome' => NULL, 'victory_condition' => NULL];
  }

}
