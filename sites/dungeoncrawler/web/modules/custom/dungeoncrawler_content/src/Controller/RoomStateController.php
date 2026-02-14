<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\RoomStateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API endpoints for room-level state management.
 */
class RoomStateController extends ControllerBase {

  private RoomStateService $roomStateService;
  private CampaignAccessCheck $campaignAccessCheck;
  private AccountInterface $currentUser;

  public function __construct(
    RoomStateService $room_state_service,
    CampaignAccessCheck $campaign_access_check,
    AccountInterface $current_user
  ) {
    $this->roomStateService = $room_state_service;
    $this->campaignAccessCheck = $campaign_access_check;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_state_service'),
      $container->get('dungeoncrawler_content.campaign_access_check'),
      $container->get('current_user')
    );
  }

  /**
   * GET /api/dungeon/{dungeonId}/room/{roomId}/state?campaignId=123
   */
  public function getState(string $dungeon_id, string $room_id, Request $request): JsonResponse {
    $campaign_id = $request->query->get('campaignId');
    if (!is_numeric($campaign_id)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'campaignId is required'], 400);
    }
    $campaign_id = (int) $campaign_id;

    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    try {
      $state = $this->roomStateService->getState($campaign_id, $room_id);
      // Enforce dungeonId consistency if present in stored state.
      if (isset($state['state']['dungeonId']) && (string) $state['state']['dungeonId'] !== (string) $dungeon_id) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'dungeonId mismatch for room state',
        ], 400);
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $state,
        'version' => $state['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() === 404 ? 404 : 400;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $code);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * POST /api/dungeon/{dungeonId}/room/{roomId}/state
   * Body: { campaignId, expectedVersion?, state: { dungeonId, roomId?, ... } }
   */
  public function setState(string $dungeon_id, string $room_id, Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Invalid JSON'], 400);
    }

    if (!isset($data['campaignId']) || !is_numeric($data['campaignId'])) {
      return new JsonResponse(['success' => FALSE, 'error' => 'campaignId is required'], 400);
    }
    $campaign_id = (int) $data['campaignId'];

    // Check campaign access.
    $access = $this->campaignAccessCheck->access($this->currentUser, $campaign_id);
    if (!$access->isAllowed()) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Access denied to campaign',
      ], 403);
    }

    $expected_version = isset($data['expectedVersion']) ? (int) $data['expectedVersion'] : NULL;
    $state_payload = $data['state'] ?? NULL;
    if (!is_array($state_payload)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Missing state payload'], 400);
    }

    // Enforce dungeonId and roomId consistency.
    if (empty($state_payload['dungeonId']) || (string) $state_payload['dungeonId'] !== (string) $dungeon_id) {
      return new JsonResponse(['success' => FALSE, 'error' => 'dungeonId is required in state and must match path'], 400);
    }
    if (isset($state_payload['roomId']) && (string) $state_payload['roomId'] !== (string) $room_id) {
      return new JsonResponse(['success' => FALSE, 'error' => 'roomId in state must match path'], 400);
    }

    // Basic validation: state_payload should be a non-empty object.
    if (empty($state_payload)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'State payload must be a non-empty object',
      ], 400);
    }

    try {
      $updated = $this->roomStateService->setState($campaign_id, $room_id, $dungeon_id, $state_payload, $expected_version);
      // Also enforce returned state matches path IDs.
      if (isset($updated['state']['dungeonId']) && (string) $updated['state']['dungeonId'] !== (string) $dungeon_id) {
        return new JsonResponse(['success' => FALSE, 'error' => 'dungeonId mismatch for room state'], 400);
      }
      if ((string) $updated['roomId'] !== (string) $room_id) {
        return new JsonResponse(['success' => FALSE, 'error' => 'roomId mismatch for room state'], 400);
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $updated,
        'version' => $updated['version'] ?? 0,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $code = $e->getCode() === 409 ? 409 : ($e->getCode() === 404 ? 404 : 400);
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
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
