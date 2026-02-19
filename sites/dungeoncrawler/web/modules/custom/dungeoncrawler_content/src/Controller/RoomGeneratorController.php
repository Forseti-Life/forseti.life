<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\RoomGeneratorService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for room generation API endpoints.
 *
 * Provides REST API for generating individual dungeon rooms.
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class RoomGeneratorController extends ControllerBase {

  /**
   * The room generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomGeneratorService
   */
  protected RoomGeneratorService $roomGenerator;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * Constructs a RoomGeneratorController object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\RoomGeneratorService $room_generator
   *   The room generator service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   */
  public function __construct(
    RoomGeneratorService $room_generator,
    SchemaLoader $schema_loader
  ) {
    $this->roomGenerator = $room_generator;
    $this->schemaLoader = $schema_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_generator'),
      $container->get('dungeoncrawler_content.schema_loader')
    );
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}/rooms
   *
   * Generate a new room in a dungeon level.
   *
   * Request:
   * {
   *   "level_id": "uuid",
   *   "depth": 1,
   *   "party_level": 5,
   *   "room_size": "medium",
   *   "room_type": "chamber",
   *   "terrain_type": "stone"
   * }
   *
   * Response: 201 Created
   * {
   *   "room_id": "uuid",
   *   "name": "The Goblin Barracks",
   *   "description": "...",
   *   "hexes": [...],
   *   "entities": [...],
   *   ...room.schema.json...
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with generated room (201 Created)
   */
  public function createRoom(
    Request $request,
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // TODO: Implementation
    // 1. Validate request has required fields
    // 2. Check campaign_id exists and user has access
    // 3. Check dungeon_id exists under campaign
    // 4. Validate request body against schema
    // 5. Call roomGenerator->generateRoom($context)
    // 6. Return 201 Created with room data
    // 7. Handle errors: 400 (bad request), 403 (forbidden), 404 (not found), 422 (validation)

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}
   *
   * Get room details (either cached or newly generated if missing).
   *
   * Response: 200 OK
   * {
   *   "room_id": "uuid",
   *   "name": "The Goblin Barracks",
   *   ...room.schema.json...
   * }
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param string $room_id
   *   Room ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with room data (200 OK) or 404 Not Found
   */
  public function getRoom(
    int $campaign_id,
    int $dungeon_id,
    string $room_id
  ): JsonResponse {
    // TODO: Implementation
    // 1. Check campaign_id exists and user has access
    // 2. Check dungeon_id exists under campaign
    // 3. Load room from database
    // 4. If room exists, return 200 OK
    // 5. If not exists, return 404 Not Found
    // 6. Validate user has permission to view

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate
   *
   * Force regenerate a room (admin only).
   * WARNING: This will overwrite existing room data!
   *
   * Request:
   * {
   *   "confirm": true
   * }
   *
   * Response: 200 OK
   * {
   *   "status": "regenerated",
   *   "room_id": "uuid",
   *   ...room.schema.json...
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID.
   * @param string $room_id
   *   Room ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with regenerated room
   */
  public function regenerateRoom(
    Request $request,
    int $campaign_id,
    int $dungeon_id,
    string $room_id
  ): JsonResponse {
    // TODO: Implementation
    // 1. Check user is admin (permission check)
    // 2. Validate campaign_id, dungeon_id, room_id exist
    // 3. Load existing room to get original context
    // 4. Delete old room and entities from database
    // 5. Call roomGenerator->generateRoom() with original context
    // 6. Persist new room
    // 7. Return 200 OK with new room data
    // 8. Handle errors: 403 (not admin), 404 (not found)

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

}
