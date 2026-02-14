<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Random;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lightweight combat encounter API for hexmap integration.
 *
 * Provides stubbed turn lifecycle endpoints while the full combat engine
 * services are being implemented. State is stored in a key/value store so the
 * frontend can rely on stable encounter IDs across requests.
 */
class CombatEncounterApiController extends ControllerBase {

  /**
   * Encounter storage service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\CombatEncounterStore
   */
  protected $encounterStore;

  /**
   * Constructor.
   */
  public function __construct(CombatEncounterStore $encounter_store) {
    $this->encounterStore = $encounter_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_encounter_store')
    );
  }

  /**
   * Start a new encounter.
   */
  public function start(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];

    $participants = $this->normalizeParticipants($data['entities'] ?? []);
    if (empty($participants)) {
      return new JsonResponse([
        'error' => 'At least one participant is required',
      ], 400);
    }

    // Sort by initiative (desc, then roll, then name).
    usort($participants, function (array $a, array $b) {
      $cmp = ($b['initiative'] ?? 0) <=> ($a['initiative'] ?? 0);
      if ($cmp !== 0) {
        return $cmp;
      }
      $cmp = ($b['initiative_roll'] ?? 0) <=> ($a['initiative_roll'] ?? 0);
      if ($cmp !== 0) {
        return $cmp;
      }
      return strcmp((string) $a['name'], (string) $b['name']);
    });

    // Reset turn index to the first non-defeated participant.
    $turn_index = $this->findNextTurnIndex($participants, -1);

    $encounter_id = $this->encounterStore->createEncounter(
      $data['campaignId'] ?? NULL,
      $data['roomId'] ?? NULL,
      $participants
    );

    // Persist the computed turn index.
    $this->encounterStore->updateEncounter($encounter_id, [
      'turn_index' => $turn_index,
      'current_round' => 1,
      'status' => 'active',
    ]);

    $encounter = $this->encounterStore->loadEncounter($encounter_id);
    $encounter['turn_index'] = $turn_index;

    return new JsonResponse($this->buildEncounterResponse($encounter), 201);
  }

  /**
   * Advance turn for the active encounter.
   */
  public function endTurn(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

      $encounter = $this->autoPlayNonPlayerTurns($this->loadEncounter($encounter_id));
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $participant_count = count($encounter['participants']);
    if ($participant_count === 0) {
      return new JsonResponse($this->buildEncounterResponse($encounter));
    }

    $next_index = $this->findNextTurnIndex($encounter['participants'], $encounter['turn_index']);

    // If we wrapped around, increment round.
    $fields = [
      'turn_index' => $next_index,
    ];
    if ($next_index <= $encounter['turn_index']) {
      $fields['current_round'] = (int) $encounter['current_round'] + 1;
      $encounter['current_round'] = $fields['current_round'];
    }
    $encounter['turn_index'] = $next_index;

    $this->encounterStore->updateEncounter($encounter_id, $fields);
    $encounter = $this->autoPlayNonPlayerTurns($this->loadEncounter($encounter_id));

    return new JsonResponse($this->buildEncounterResponse($encounter));
  }

  /**
   * End an encounter.
   */
  public function end(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter($encounter_id);
    if ($encounter) {
      $this->encounterStore->updateEncounter($encounter_id, ['status' => 'ended']);
    }

    return new JsonResponse([
      'encounter_id' => $encounter_id,
      'ended' => TRUE,
    ]);
  }

  /**
   * Get encounter state for a given encounterId.
   */
  public function get(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    return new JsonResponse($this->buildEncounterResponse($encounter));
  }

  /**
   * Replace encounter state (turn index/status/participants) with optimistic lock.
   */
  public function set(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];
    $encounter_id = $data['encounterId'] ?? NULL;
    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $current_version = (int) ($encounter['updated'] ?? 0);
    if ($expected_version !== NULL && $expected_version !== $current_version) {
      return new JsonResponse([
        'error' => 'Version conflict',
        'currentVersion' => $current_version,
        'state' => $this->buildEncounterResponse($encounter),
      ], 409);
    }

    // Core fields update
    $fields = [];
    if (isset($data['turn_index'])) {
      $fields['turn_index'] = (int) $data['turn_index'];
    }
    if (isset($data['current_round'])) {
      $fields['current_round'] = (int) $data['current_round'];
    }
    if (!empty($data['status'])) {
      $fields['status'] = $data['status'];
    }
    if ($fields) {
      $this->encounterStore->updateEncounter((int) $encounter_id, $fields);
    }

    // Replace participants when provided
    if (!empty($data['participants']) && is_array($data['participants'])) {
      $this->encounterStore->saveParticipants((int) $encounter_id, $data['participants']);
    }

    $fresh = $this->loadEncounter((int) $encounter_id);
    return new JsonResponse($this->buildEncounterResponse($fresh));
  }

  /**
   * Execute a basic attack (stub).
   */
  public function attack(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];

    $encounter_id = $data['encounterId'] ?? NULL;
    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }

    $encounter = $this->loadEncounter($encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    $damage = (int) ($data['action']['damage'] ?? 0);
    $target_ref = $data['targetId'] ?? NULL;

    $target = $this->findParticipantByEntityRef($encounter['participants'], $target_ref);
    if ($target && $damage > 0) {
      $hp_before = $target['hp'] ?? NULL;
      $hp_after = $hp_before !== NULL ? max(0, $hp_before - $damage) : NULL;

      $this->encounterStore->updateParticipant((int) $target['id'], [
        'hp' => $hp_after,
        'is_defeated' => ($hp_after !== NULL && $hp_after <= 0) ? 1 : 0,
      ]);

      $this->encounterStore->logDamage([
        'encounter_id' => $encounter_id,
        'participant_id' => (int) $target['id'],
        'amount' => $damage,
        'damage_type' => $data['action']['damage_type'] ?? NULL,
        'source' => $data['attackerId'] ?? NULL,
        'hp_before' => $hp_before,
        'hp_after' => $hp_after,
      ]);
    }

    $encounter = $this->loadEncounter($encounter_id);

    return new JsonResponse([
      'success' => TRUE,
      'encounter_id' => $encounter_id,
      'attacker_id' => $data['attackerId'] ?? NULL,
      'target_id' => $target_ref,
      'result' => [
        'hit' => TRUE,
        'damage' => $damage,
      ],
      'state' => $this->buildEncounterResponse($encounter),
    ]);
  }

  /**
   * Generate a simple encounter ID.
   */
  protected function generateEncounterId(): int {
    return (int) round(microtime(TRUE) * 1000);
  }

  /**
   * Normalize participant payloads.
   */
  protected function normalizeParticipants(array $entities): array {
    $participants = [];

    foreach ($entities as $index => $entity) {
      $entity_id = $entity['entityId'] ?? $entity['id'] ?? $index + 1;
      $name = $entity['name'] ?? "Entity {$entity_id}";

      // Initiative: use provided value, otherwise roll d20 + perception + initiative_bonus.
      $initiative = $entity['initiative'] ?? NULL;
      $initiative_roll = NULL;
      if ($initiative === NULL) {
        $roll = random_int(1, 20);
        $bonus = (int) ($entity['perception'] ?? 0) + (int) ($entity['initiative_bonus'] ?? 0);
        $initiative = $roll + $bonus;
        $initiative_roll = $roll;
      }

      $hp = isset($entity['hp']) ? (int) $entity['hp'] : NULL;
      $max_hp = isset($entity['max_hp']) ? (int) $entity['max_hp'] : NULL;

      $participants[] = [
        'entity_id' => $entity_id,
        'name' => $name,
        'team' => $entity['team'] ?? NULL,
        'initiative' => $initiative,
        'initiative_roll' => $initiative_roll,
        'ac' => isset($entity['ac']) ? (int) $entity['ac'] : NULL,
        'hp' => $hp,
        'max_hp' => $max_hp,
        'is_defeated' => (bool) ($entity['is_defeated'] ?? FALSE),
      ];
    }

    return $participants;
  }

  /**
   * Build response DTO for frontend consumption.
   */
  protected function buildEncounterResponse(array $encounter): array {
    $participants = $encounter['participants'] ?? [];
    $turn_index = $encounter['turn_index'] ?? 0;

    $normalized_participants = [];
    $initiative_order = [];
    foreach ($participants as $idx => $participant) {
      $entity_id = $participant['entity_ref'] ?? ($participant['entity_id'] ?? $participant['id']);
      $is_defeated = (bool) ($participant['is_defeated'] ?? FALSE);

      $normalized = $participant;
      $normalized['entity_id'] = $entity_id;
      $normalized['is_defeated'] = $is_defeated;
      $normalized_participants[] = $normalized;

      $initiative_order[] = [
        'entity_id' => $entity_id,
        'name' => $participant['name'],
        'initiative' => $participant['initiative'],
        'is_current' => $idx === $turn_index,
        'is_defeated' => $is_defeated,
      ];
    }

    $current_participant = $normalized_participants[$turn_index] ?? NULL;

    return [
      'encounter_id' => $encounter['id'] ?? $encounter['encounter_id'],
      'campaign_id' => $encounter['campaign_id'],
      'room_id' => $encounter['room_id'],
      'status' => $encounter['status'],
      'current_round' => $encounter['current_round'],
      'turn_index' => $turn_index,
      'version' => (int) ($encounter['updated'] ?? 0),
      'initiative_order' => $initiative_order,
      'participants' => $normalized_participants,
      'current_participant' => $current_participant,
    ];
  }

  /**
   * Persist encounter state.
   */
  protected function saveEncounter(array $encounter): void {
    $this->store->set('encounter.' . $encounter['encounter_id'], $encounter);
  }

  /**
   * Load encounter state.
   */
  protected function loadEncounter(int $encounter_id): ?array {
    return $this->encounterStore->loadEncounter($encounter_id);
  }

  /**
   * Run a minimal server-side NPC loop: each non-player gets one swing at the first alive player.
   * Advances turn index until we hit a player or exhaust participants.
   */
  protected function autoPlayNonPlayerTurns(?array $encounter): ?array {
    if (!$encounter) {
      return NULL;
    }

    $limit = max(1, count($encounter['participants'] ?? []));
    for ($i = 0; $i < $limit; $i++) {
      $participants = $encounter['participants'] ?? [];
      $turn_index = (int) ($encounter['turn_index'] ?? 0);
      $current = $participants[$turn_index] ?? NULL;

      if (!$current || ($current['team'] ?? 'player') === 'player' || !empty($current['is_defeated'])) {
        break;
      }

      // Pick first alive player target.
      $target_idx = $this->findFirstAlivePlayerIndex($participants);
      if ($target_idx !== NULL) {
        $target = $participants[$target_idx];
        $damage = random_int(1, 6);
        $hp_before = $target['hp'] ?? NULL;
        $hp_after = $hp_before !== NULL ? max(0, $hp_before - $damage) : NULL;

        $this->encounterStore->updateParticipant((int) $target['id'], [
          'hp' => $hp_after,
          'is_defeated' => ($hp_after !== NULL && $hp_after <= 0) ? 1 : 0,
        ]);

        $this->encounterStore->logDamage([
          'encounter_id' => $encounter['id'],
          'participant_id' => (int) $target['id'],
          'amount' => $damage,
          'damage_type' => 'bludgeoning',
          'source' => $current['entity_ref'] ?? $current['entity_id'] ?? NULL,
          'hp_before' => $hp_before,
          'hp_after' => $hp_after,
        ]);

        $encounter = $this->loadEncounter((int) $encounter['id']);
      }

      // Advance turn index and round.
      $next_index = $this->findNextTurnIndex($encounter['participants'] ?? [], (int) $encounter['turn_index']);
      $fields = ['turn_index' => $next_index];
      if ($next_index <= $encounter['turn_index']) {
        $fields['current_round'] = (int) $encounter['current_round'] + 1;
        $encounter['current_round'] = $fields['current_round'];
      }
      $encounter['turn_index'] = $next_index;
      $this->encounterStore->updateEncounter((int) $encounter['id'], $fields);
      $encounter = $this->loadEncounter((int) $encounter['id']);
    }

    return $encounter;
  }

  /**
   * Find first alive player participant.
   */
  protected function findFirstAlivePlayerIndex(array $participants): ?int {
    foreach ($participants as $idx => $participant) {
      if (($participant['team'] ?? NULL) === 'player' && empty($participant['is_defeated'])) {
        return $idx;
      }
    }
    return NULL;
  }

  /**
   * Find the next non-defeated participant index, wrapping around.
   */
  protected function findNextTurnIndex(array $participants, int $current_index): int {
    $count = count($participants);
    if ($count === 0) {
      return 0;
    }

    for ($offset = 1; $offset <= $count; $offset++) {
      $candidate = ($current_index + $offset) % $count;
      if (empty($participants[$candidate]['is_defeated'])) {
        return $candidate;
      }
    }

    // All defeated; stay at current or zero.
    return max(0, $current_index);
  }

  /**
   * Find participant by entity_ref.
   */
  protected function findParticipantByEntityRef(array $participants, $entity_ref): ?array {
    foreach ($participants as $participant) {
      if ((string) ($participant['entity_ref'] ?? '') === (string) $entity_ref) {
        return $participant;
      }
    }
    return NULL;
  }

}
