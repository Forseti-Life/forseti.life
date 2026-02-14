<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\DungeonStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for dungeon state get/set with optimistic locking.
 */
class DungeonStateController extends ControllerBase {

  private DungeonStateService $dungeonStateService;

  public function __construct(DungeonStateService $dungeon_state_service) {
    $this->dungeonStateService = $dungeon_state_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_state_service')
    );
  }

  /**
   * GET /api/dungeon/{dungeonId}/state
   */
  public function getState(string $dungeon_id, Request $request): JsonResponse {
    $campaign_id = $request->query->get('campaignId');
    if (!is_numeric($campaign_id)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'campaignId is required',
      ], 400);
    }
    $campaign_id = (int) $campaign_id;

    try {
      $state = $this->dungeonStateService->getState($dungeon_id, $campaign_id);
      return new JsonResponse([
        'success' => TRUE,
        'data' => $state,
        'version' => $state['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $e->getCode() === 404 ? 404 : 400);
    }
  }

  /**
   * POST /api/dungeon/{dungeonId}/state
   */
  public function setState(string $dungeon_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $state_payload = $data['state'] ?? NULL;
    if (!isset($data['campaignId']) || !is_numeric($data['campaignId'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'campaignId is required'], 400);
    }
    $campaign_id = (int) $data['campaignId'];

    if (isset($state_payload['dungeonId']) && (string) $state_payload['dungeonId'] !== (string) $dungeon_id) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'dungeonId in payload must match path',
      ], 400);
    }

    if (!is_array($state_payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing state payload'], 400);
    }

    try {
      $updated = $this->dungeonStateService->setState($dungeon_id, $state_payload, $expected_version, $campaign_id);
      return new JsonResponse([
        'success' => TRUE,
        'data' => $updated,
        'version' => $updated['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() === 409 ? 409 : 400;
      $current = $this->dungeonStateService->getState($dungeon_id);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
        'currentVersion' => $current['version'] ?? 0,
        'data' => $current,
      ], $code);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

}
