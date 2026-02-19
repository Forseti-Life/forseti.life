<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API controller for room chat messages.
 * 
 * Provides REST endpoints for reading and posting chat messages in dungeon rooms.
 * All business logic is handled by RoomChatService.
 */
class RoomChatController extends ControllerBase {

  protected RoomChatService $chatService;

  /**
   * Constructor.
   */
  public function __construct(RoomChatService $chat_service) {
    $this->chatService = $chat_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.room_chat_service')
    );
  }

  /**
   * Get chat history for a room.
   * 
   * GET /api/campaign/{campaign_id}/room/{room_id}/chat
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Chat history response.
   */
  public function getChatHistory(int $campaign_id, string $room_id): JsonResponse {
    try {
      // Verify user has access to campaign
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      $messages = $this->chatService->getChatHistory($campaign_id, $room_id);

      return new JsonResponse([
        'success' => TRUE,
        'data' => [
          'roomId' => $room_id,
          'messages' => $messages,
        ],
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 500;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $status === 404 ? 'Dungeon not found' : 'Invalid request',
      ], $status);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
    }
  }

  /**
   * Post a new chat message to a room.
   * 
   * POST /api/campaign/{campaign_id}/room/{room_id}/chat
   * 
   * Payload: { "speaker": "Name", "message": "...", "type": "player", "character_id": 123 }
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Success response with message metadata.
   */
  public function postChatMessage(int $campaign_id, string $room_id, Request $request): JsonResponse {
    try {
      // Verify user has access to campaign
      if (!$this->chatService->hasCampaignAccess($campaign_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Access denied',
        ], 403);
      }

      // Parse request body
      $payload = json_decode($request->getContent(), TRUE);
      if (!is_array($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid JSON payload',
        ], 400);
      }

      $speaker = $payload['speaker'] ?? '';
      $message = $payload['message'] ?? '';
      $type = $payload['type'] ?? 'player';
      $character_id = isset($payload['character_id']) ? (int) $payload['character_id'] : null;

      $result = $this->chatService->postMessage(
        $campaign_id,
        $room_id,
        $speaker,
        $message,
        $type,
        $character_id
      );

      return new JsonResponse([
        'success' => TRUE,
        'data' => $result,
      ]);
    }
    catch (\InvalidArgumentException $e) {
      $status = (int) $e->getCode() ?: 400;
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], $status);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'An error occurred',
      ], 500);
    }
  }

}
