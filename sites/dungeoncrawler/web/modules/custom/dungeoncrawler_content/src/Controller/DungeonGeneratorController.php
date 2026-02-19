<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\DungeonGeneratorService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP controller for dungeon generation API endpoints.
 *
 * Provides REST API for generating complete multi-level dungeons.
 *
 * @see /docs/dungeoncrawler/ROOM_DUNGEON_GENERATOR_ARCHITECTURE.md
 */
class DungeonGeneratorController extends ControllerBase {

  /**
   * The dungeon generator service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\DungeonGeneratorService
   */
  protected DungeonGeneratorService $dungeonGenerator;

  /**
   * The schema loader service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\SchemaLoader
   */
  protected SchemaLoader $schemaLoader;

  /**
   * Constructs a DungeonGeneratorController object.
   *
   * @param \Drupal\dungeoncrawler_content\Service\DungeonGeneratorService $dungeon_generator
   *   The dungeon generator service.
   * @param \Drupal\dungeoncrawler_content\Service\SchemaLoader $schema_loader
   *   The schema loader service.
   */
  public function __construct(
    DungeonGeneratorService $dungeon_generator,
    SchemaLoader $schema_loader
  ) {
    $this->dungeonGenerator = $dungeon_generator;
    $this->schemaLoader = $schema_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_generator'),
      $container->get('dungeoncrawler_content.schema_loader')
    );
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/generate
   *
   * Generate a complete new dungeon at world coordinates.
   *
   * Request:
   * {
   *   "location_x": 100,
   *   "location_y": 200,
   *   "party_level": 5,
   *   "party_size": 4,
   *   "party_composition": {
   *     "fighter": 1,
   *     "wizard": 1,
   *     "cleric": 1,
   *     "rogue": 1
   *   },
   *   "theme": null
   * }
   *
   * Response: 201 Created
   * {
   *   "dungeon_id": "uuid",
   *   "name": "The Goblin Warren",
   *   "theme": "goblin_warrens",
   *   "depth": 3,
   *   "location_x": 100,
   *   "location_y": 200,
   *   "levels": [
   *     { ...dungeon_level.schema.json... },
   *     { ...dungeon_level.schema.json... },
   *     { ...dungeon_level.schema.json... }
   *   ]
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with generated dungeon (201 Created)
   */
  public function generateDungeon(
    Request $request,
    int $campaign_id
  ): JsonResponse {
    // TODO: Implementation
    // 1. Validate request has required fields
    // 2. Check campaign_id exists and user has access
    // 3. Validate request body (location_x, location_y, party_level, party_size)
    // 4. Check if dungeon already exists at location
    //    - If yes: return 409 Conflict (but include dungeon data as full response body)
    // 5. Call dungeonGenerator->generateDungeon($context)
    // 6. Persist dungeon to database
    // 7. Return 201 Created with complete dungeon data (all levels)
    // 8. Handle errors: 400 (bad request), 403 (forbidden), 404 (not found), 422 (validation)

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}
   *
   * Get dungeon details with all levels.
   *
   * Response: 200 OK
   * {
   *   "dungeon_id": "uuid",
   *   "name": "The Goblin Warren",
   *   "theme": "goblin_warrens",
   *   "depth": 3,
   *   "levels": [...]
   * }
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with dungeon data (200 OK) or 404 Not Found
   */
  public function getDungeon(
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // TODO: Implementation
    // 1. Check campaign_id exists and user has access
    // 2. Load dungeon from database
    // 3. If exists, return 200 OK with all levels
    // 4. If not exists, return 404 Not Found
    // 5. Validate user has permission to view

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}
   *
   * Get single dungeon level.
   *
   * Response: 200 OK
   * {
   *   "level_id": "uuid",
   *   "depth": 1,
   *   "theme": "goblin_warrens",
   *   ...dungeon_level.schema.json...
   * }
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID (UUID).
   * @param int $depth
   *   Level depth (1-based).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with level data (200 OK) or 404 Not Found
   */
  public function getDungeonLevel(
    int $campaign_id,
    int $dungeon_id,
    int $depth
  ): JsonResponse {
    // TODO: Implementation
    // 1. Check campaign_id, dungeon_id exist and user has access
    // 2. Load level from database
    // 3. If exists, return 200 OK
    // 4. If not exists, return 404 Not Found

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels
   *
   * Extend dungeon with new level (when party descends deeper).
   *
   * Request:
   * {
   *   "party_level": 5,
   *   "party_composition": {...}
   * }
   *
   * Response: 201 Created
   * {
   *   "level_id": "uuid",
   *   "depth": 4,
   *   ...dungeon_level.schema.json...
   * }
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param int $campaign_id
   *   Campaign ID.
   * @param int $dungeon_id
   *   Dungeon ID (UUID).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with new level (201 Created)
   */
  public function addDungeonLevel(
    Request $request,
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // TODO: Implementation
    // 1. Check campaign_id, dungeon_id exist and user has access
    // 2. Load dungeon to get theme and current depth
    // 3. Check if level at next depth already exists
    //    - If yes: return 409 Conflict
    // 4. Call dungeonGenerator->generateLevel() with depth+1
    // 5. Persist new level
    // 6. Return 201 Created with new level data

    return new JsonResponse([], JsonResponse::HTTP_NOT_IMPLEMENTED);
  }

}
