<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for campaign entity lifecycle (spawn/move/despawn).
 */
class CampaignEntityController extends ControllerBase {

  private Connection $database;
  private CampaignAccessCheck $campaignAccessCheck;
  private AccountInterface $currentUser;

  public function __construct(
    Connection $database,
    CampaignAccessCheck $campaign_access_check,
    AccountInterface $current_user
  ) {
    $this->database = $database;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user')
    );
  }

  /**
   * POST /api/campaign/{campaignId}/entity/spawn
   * 
   * Body: {
   *   "type": "npc|obstacle|trap|hazard|pc",
   *   "instanceId": "unique-instance-id",
   *   "characterId": 123 (optional, for pc/npc),
   *   "locationType": "room|dungeon|tavern",
   *   "locationRef": "room-id-123",
   *   "stateData": { ... entity-specific state }
   * }
   */
  public function spawnEntity(int $campaign_id, Request $request): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    // Validate required fields.
    if (empty($data['type'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'type is required'], 400);
    }
    if (empty($data['instanceId'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'instanceId is required'], 400);
    }
    if (empty($data['locationType'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationType is required'], 400);
    }
    if (empty($data['locationRef'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationRef is required'], 400);
    }

    $type = $data['type'];
    $allowed_types = ['npc', 'obstacle', 'trap', 'hazard', 'pc'];
    if (!in_array($type, $allowed_types, TRUE)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Invalid type. Allowed: ' . implode(', ', $allowed_types),
      ], 400);
    }

    $instance_id = $data['instanceId'];
    $character_id = isset($data['characterId']) ? (int) $data['characterId'] : NULL;
    $location_type = $data['locationType'];
    $location_ref = $data['locationRef'];
    $state_data = $data['stateData'] ?? [];

    // Check if instance already exists.
    $existing = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->execute()
      ->fetchField();

    if ($existing) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity with this instanceId already exists',
      ], 400);
    }

    // Insert entity.
    try {
      $id = $this->database->insert('dc_campaign_characters')
        ->fields([
          'campaign_id' => $campaign_id,
          'character_id' => $character_id ?? 0,
          'instance_id' => $instance_id,
          'type' => $type,
          'location_type' => $location_type,
          'location_ref' => $location_ref,
          'state_data' => json_encode($state_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
          'created' => time(),
          'updated' => time(),
        ])
        ->execute();

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'id' => (int) $id,
          'campaignId' => $campaign_id,
          'type' => $type,
          'instanceId' => $instance_id,
          'characterId' => $character_id,
          'locationType' => $location_type,
          'locationRef' => $location_ref,
          'stateData' => $state_data,
        ],
      ], 201);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to spawn entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * POST /api/campaign/{campaignId}/entity/{instanceId}/move
   * 
   * Body: {
   *   "locationType": "room|dungeon|tavern",
   *   "locationRef": "room-id-456"
   * }
   */
  public function moveEntity(int $campaign_id, string $instance_id, Request $request): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    // Validate required fields.
    if (empty($data['locationType'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationType is required'], 400);
    }
    if (empty($data['locationRef'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'locationRef is required'], 400);
    }

    $location_type = $data['locationType'];
    $location_ref = $data['locationRef'];

    // Check if entity exists.
    $entity = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->execute()
      ->fetchAssoc();

    if (!$entity) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity not found',
      ], 404);
    }

    // Update location.
    try {
      $this->database->update('dc_campaign_characters')
        ->fields([
          'location_type' => $location_type,
          'location_ref' => $location_ref,
          'updated' => time(),
        ])
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $instance_id)
        ->execute();

      $state_data = json_decode($entity['state_data'] ?? '{}', TRUE);
      if (!is_array($state_data)) {
        $state_data = [];
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'id' => (int) $entity['id'],
          'campaignId' => $campaign_id,
          'type' => $entity['type'],
          'instanceId' => $instance_id,
          'characterId' => (int) $entity['character_id'],
          'locationType' => $location_type,
          'locationRef' => $location_ref,
          'stateData' => $state_data,
        ],
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to move entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * DELETE /api/campaign/{campaignId}/entity/{instanceId}
   */
  public function despawnEntity(int $campaign_id, string $instance_id): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    // Check if entity exists.
    $entity = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_id', $campaign_id)
      ->condition('instance_id', $instance_id)
      ->execute()
      ->fetchAssoc();

    if (!$entity) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Entity not found',
      ], 404);
    }

    // Delete entity.
    try {
      $this->database->delete('dc_campaign_characters')
        ->condition('campaign_id', $campaign_id)
        ->condition('instance_id', $instance_id)
        ->execute();

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Entity despawned successfully',
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to despawn entity: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * GET /api/campaign/{campaignId}/entities
   * 
   * Query params: locationType, locationRef, type (all optional filters)
   */
  public function listEntities(int $campaign_id, Request $request): JsonResponse {
    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $query = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('campaign_id', $campaign_id);

    // Apply optional filters.
    $location_type = $request->query->get('locationType');
    if ($location_type) {
      $query->condition('location_type', $location_type);
    }

    $location_ref = $request->query->get('locationRef');
    if ($location_ref) {
      $query->condition('location_ref', $location_ref);
    }

    $type = $request->query->get('type');
    if ($type) {
      $query->condition('type', $type);
    }

    try {
      $results = $query->execute()->fetchAll();
      
      $entities = [];
      foreach ($results as $entity) {
        $state_data = json_decode($entity->state_data ?? '{}', TRUE);
        if (!is_array($state_data)) {
          $state_data = [];
        }

        $entities[] = [
          'id' => (int) $entity->id,
          'campaignId' => (int) $entity->campaign_id,
          'type' => $entity->type,
          'instanceId' => $entity->instance_id,
          'characterId' => (int) $entity->character_id,
          'locationType' => $entity->location_type,
          'locationRef' => $entity->location_ref,
          'stateData' => $state_data,
        ];
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $entities,
        'count' => count($entities),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Failed to list entities: ' . $e->getMessage(),
      ], 500);
    }
  }

}
