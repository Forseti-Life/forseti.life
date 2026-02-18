<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Random;
use Drupal\dungeoncrawler_content\Service\CombatEncounterStore;
use Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService;
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
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Encounter AI integration service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\EncounterAiIntegrationService
   */
  protected $encounterAiIntegration;

  /**
   * Constructor.
   */
  public function __construct(CombatEncounterStore $encounter_store, ConfigFactoryInterface $config_factory, EncounterAiIntegrationService $encounter_ai_integration) {
    $this->encounterStore = $encounter_store;
    $this->configFactory = $config_factory;
    $this->encounterAiIntegration = $encounter_ai_integration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.combat_encounter_store'),
      $container->get('config.factory'),
      $container->get('dungeoncrawler_content.encounter_ai_integration')
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
    $next_participant = $encounter['participants'][$next_index] ?? NULL;
    if (!empty($next_participant['id'])) {
      $this->encounterStore->updateParticipant((int) $next_participant['id'], [
        'actions_remaining' => 3,
        'attacks_this_turn' => 0,
      ]);
    }
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

    $target_ref = $data['targetId'] ?? NULL;
    $attacker_ref = $data['attackerId'] ?? NULL;
    $target = $this->findParticipantByReference($encounter['participants'], $target_ref);
    $attacker = $this->findParticipantByReference($encounter['participants'], $attacker_ref);

    if (!$attacker) {
      return new JsonResponse(['error' => 'Attacker not found in encounter'], 400);
    }

    $attacker_actions_remaining = (int) ($attacker['actions_remaining'] ?? 0);
    if ($attacker_actions_remaining <= 0) {
      return new JsonResponse(['error' => 'No actions remaining for attacker'], 409);
    }

    $requested_damage = isset($data['damage']) ? (int) $data['damage'] : 0;
    $damage = $requested_damage > 0 ? $requested_damage : random_int(1, 8);

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

    $this->encounterStore->updateParticipant((int) $attacker['id'], [
      'actions_remaining' => max(0, $attacker_actions_remaining - 1),
      'attacks_this_turn' => (int) ($attacker['attacks_this_turn'] ?? 0) + 1,
    ]);

    $this->encounterStore->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $attacker['id'],
      'action_type' => 'strike',
      'target_id' => $target['id'] ?? NULL,
      'payload' => json_encode([
        'attacker' => $attacker_ref,
        'target' => $target_ref,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'result' => json_encode([
        'hit' => !empty($target),
        'damage' => $damage,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $encounter = $this->loadEncounter($encounter_id);

    $response = $this->buildEncounterResponse($encounter);
    $response['success'] = TRUE;
    $response['action_result'] = [
      'type' => 'strike',
      'attacker_id' => $attacker_ref,
      'target_id' => $target_ref,
      'hit' => !empty($target),
      'damage' => $damage,
    ];

    return new JsonResponse($response);
  }

  /**
   * Execute non-attack combat actions (interact/talk).
   */
  public function action(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE) ?: [];

    $encounter_id = $data['encounterId'] ?? NULL;
    $actor_ref = $data['actorId'] ?? NULL;
    $action_type = (string) ($data['actionType'] ?? '');

    if (!$encounter_id) {
      return new JsonResponse(['error' => 'encounterId is required'], 400);
    }
    if ($action_type === '') {
      return new JsonResponse(['error' => 'actionType is required'], 400);
    }

    $allowed_types = ['interact', 'talk'];
    if (!in_array($action_type, $allowed_types, TRUE)) {
      return new JsonResponse([
        'error' => 'Unsupported actionType',
        'supported' => $allowed_types,
      ], 400);
    }

    $encounter = $this->loadEncounter((int) $encounter_id);
    if (!$encounter) {
      return new JsonResponse(['error' => 'Encounter not found'], 404);
    }

    if (($encounter['status'] ?? '') !== 'active') {
      return new JsonResponse(['error' => 'Encounter is not active'], 409);
    }

    $actor = $this->findParticipantByReference($encounter['participants'] ?? [], $actor_ref);
    if (!$actor) {
      return new JsonResponse(['error' => 'Actor not found in encounter'], 400);
    }

    $turn_index = (int) ($encounter['turn_index'] ?? 0);
    $active_participant = $encounter['participants'][$turn_index] ?? NULL;
    if (!$active_participant || (int) ($active_participant['id'] ?? 0) !== (int) ($actor['id'] ?? 0)) {
      return new JsonResponse(['error' => 'Actor is not the active turn participant'], 409);
    }

    // Server-authoritative action costs.
    $cost_by_type = [
      'interact' => 1,
      'talk' => 0,
    ];
    $cost = $cost_by_type[$action_type] ?? 1;

    if ($action_type === 'interact') {
      $target_hex = $data['targetHex'] ?? NULL;
      if (!is_array($target_hex) || !isset($target_hex['q']) || !isset($target_hex['r'])) {
        return new JsonResponse(['error' => 'targetHex {q,r} is required for interact'], 400);
      }
    }

    if ($action_type === 'talk') {
      $message = trim((string) ($data['message'] ?? ''));
      if ($message === '') {
        return new JsonResponse(['error' => 'message is required for talk'], 400);
      }
    }

    $actions_remaining = (int) ($actor['actions_remaining'] ?? 0);
    if ($cost > 0 && $actions_remaining < $cost) {
      return new JsonResponse(['error' => 'Not enough actions remaining'], 409);
    }

    if ($cost > 0) {
      $this->encounterStore->updateParticipant((int) $actor['id'], [
        'actions_remaining' => max(0, $actions_remaining - $cost),
      ]);
    }

    $this->encounterStore->logAction([
      'encounter_id' => (int) $encounter_id,
      'participant_id' => (int) $actor['id'],
      'action_type' => $action_type,
      'target_id' => NULL,
      'payload' => json_encode([
        'actor' => $actor_ref,
        'target' => $data['targetId'] ?? NULL,
        'interaction_type' => $data['interactionType'] ?? NULL,
        'target_hex' => $data['targetHex'] ?? NULL,
        'message' => $data['message'] ?? NULL,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'result' => json_encode([
        'accepted' => TRUE,
        'cost' => $cost,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);

    $updated = $this->loadEncounter((int) $encounter_id);
    $response = $this->buildEncounterResponse($updated);
    $response['success'] = TRUE;
    $response['action_result'] = [
      'type' => $action_type,
      'actor_id' => $actor_ref,
      'cost' => $cost,
      'interaction_type' => $data['interactionType'] ?? NULL,
    ];

    return new JsonResponse($response);
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

      $this->runNpcTurnAction($encounter, $current, $participants);
      $encounter = $this->loadEncounter((int) $encounter['id']);

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
   * Run current NPC turn action using AI recommendation when enabled.
   *
   * Falls back to deterministic first-alive-player strike if AI is disabled,
   * invalid, or unavailable.
   */
  protected function runNpcTurnAction(array $encounter, array $current, array $participants): void {
    $action_type = 'strike';
    $target_idx = $this->findFirstAlivePlayerIndex($participants);
    $ai_context = NULL;

    if ($this->isEncounterAiNpcAutoplayEnabled()) {
      try {
        $campaign_id = isset($encounter['campaign_id']) && $encounter['campaign_id'] !== NULL
          ? (int) $encounter['campaign_id']
          : 0;
        $encounter_id = isset($encounter['id']) ? (int) $encounter['id'] : (int) ($encounter['encounter_id'] ?? 0);

        $context = $this->encounterAiIntegration->buildEncounterContext($campaign_id, $encounter_id, $encounter);
        $ai_context = $context;
        $ai_response = $this->encounterAiIntegration->requestNpcActionRecommendation($context);
        $validation = $ai_response['validation'] ?? [];

        if (!empty($validation['valid'])) {
          $recommendation = is_array($ai_response['recommendation'] ?? NULL) ? $ai_response['recommendation'] : [];
          $recommended_action = is_array($recommendation['recommended_action'] ?? NULL) ? $recommendation['recommended_action'] : [];
          $action_type = (string) ($recommended_action['type'] ?? 'strike');

          if ($action_type === 'strike') {
            $target_ref = (string) ($recommended_action['target_instance_id'] ?? '');
            $target_idx = $this->findParticipantIndexByReference($participants, $target_ref);
            if ($target_idx === NULL) {
              $target_idx = $this->findFirstAlivePlayerIndex($participants);
            }
          }
        }
      }
      catch (\Throwable $exception) {
        $this->logger('dungeoncrawler_content')->warning('Encounter AI autoplay fallback: @message', [
          '@message' => $exception->getMessage(),
        ]);
      }
    }

    if ($this->isEncounterAiNarrationEnabled() && is_array($ai_context) && isset($current['id'])) {
      $this->persistEncounterNarrationEvent($encounter, (int) $current['id'], $ai_context);
    }

    if ($action_type !== 'strike' || $target_idx === NULL) {
      return;
    }

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
  }

  /**
   * Check if encounter AI-driven NPC auto-play is enabled in config.
   */
  protected function isEncounterAiNpcAutoplayEnabled(): bool {
    return (bool) $this->configFactory
      ->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_npc_autoplay_enabled');
  }

  /**
   * Check if encounter narration event persistence is enabled in config.
   */
  protected function isEncounterAiNarrationEnabled(): bool {
    return (bool) $this->configFactory
      ->get('dungeoncrawler_content.settings')
      ->get('encounter_ai_narration_enabled');
  }

  /**
   * Persist AI narration event into encounter action timeline.
   */
  protected function persistEncounterNarrationEvent(array $encounter, int $participant_id, array $context): void {
    try {
      $narration_response = $this->encounterAiIntegration->requestEncounterNarration($context);
      $narration_payload = is_array($narration_response['narration'] ?? NULL)
        ? $narration_response['narration']
        : [];

      if (empty($narration_payload)) {
        return;
      }

      $this->encounterStore->logAction([
        'encounter_id' => (int) ($encounter['id'] ?? $encounter['encounter_id']),
        'participant_id' => $participant_id,
        'action_type' => 'ai_narration',
        'target_id' => NULL,
        'payload' => json_encode($narration_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'result' => json_encode([
          'provider' => $narration_response['provider'] ?? 'unknown',
          'requested_at' => $narration_response['requested_at'] ?? time(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      ]);
    }
    catch (\Throwable $exception) {
      $this->logger('dungeoncrawler_content')->warning('Encounter narration persistence skipped: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Find participant index by entity reference or entity ID.
   */
  protected function findParticipantIndexByReference(array $participants, string $reference): ?int {
    if ($reference === '') {
      return NULL;
    }

    foreach ($participants as $idx => $participant) {
      $entity_ref = (string) ($participant['entity_ref'] ?? '');
      $entity_id = (string) ($participant['entity_id'] ?? '');
      if ($entity_ref === $reference || $entity_id === $reference) {
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
  protected function findParticipantByReference(array $participants, $entity_ref): ?array {
    foreach ($participants as $participant) {
      if ((string) ($participant['entity_ref'] ?? '') === (string) $entity_ref || (string) ($participant['entity_id'] ?? '') === (string) $entity_ref || (string) ($participant['id'] ?? '') === (string) $entity_ref) {
        return $participant;
      }
    }
    return NULL;
  }

}
