<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\dungeoncrawler_content\Service\RoomChatService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for quest tracking endpoints.
 */
class QuestTrackerController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Room chat service.
   *
   * @var \Drupal\dungeoncrawler_content\Service\RoomChatService
   */
  protected RoomChatService $chatService;

  /**
   * Constructs a QuestTrackerController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory, RoomChatService $chat_service) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
    $this->chatService = $chat_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('database'),
      $container->get('logger.factory'),
      $container->get('dungeoncrawler_content.room_chat_service')
    );
  }

  /**
   * Get available quests for a campaign.
   *
   * GET /api/campaign/{campaign_id}/quests/available
   *
   * @param int $campaign_id
   *   The campaign ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getAvailableQuests(int $campaign_id): JsonResponse {
    try {
      $available = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', [
          'quest_id',
          'quest_name',
          'quest_description',
          'quest_type',
          'generated_objectives',
          'generated_rewards',
          'giver_npc_id',
          'location_id',
          'status',
        ])
        ->condition('q.campaign_id', $campaign_id)
        ->condition('q.status', 'available')
        ->execute()
        ->fetchAllAssoc('quest_id');

      $quests = array_map(function ($quest) {
        $quest->generated_objectives = json_decode($quest->generated_objectives, TRUE) ?? [];
        $quest->generated_rewards = json_decode($quest->generated_rewards, TRUE) ?? [];
        return $quest;
      }, $available);

      return new JsonResponse([
        'success' => TRUE,
        'quests' => array_values($quests),
        'count' => count($quests),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch available quests: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Start a quest.
   *
   * POST /api/campaign/{campaign_id}/quests/{quest_id}/start
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function startQuest(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      $character_id = $payload['character_id'] ?? NULL;
      $party_id = $payload['party_id'] ?? NULL;

      if (empty($character_id) && empty($party_id)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Either character_id or party_id is required',
        ], 400);
      }

      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $result = $quest_tracker->startQuest($campaign_id, $quest_id, $character_id, $party_id);

      if (!$result) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to start quest',
        ], 500);
      }

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Quest started successfully',
        'quest_id' => $quest_id,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to start quest: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Update quest progress.
   *
   * PUT /api/campaign/{campaign_id}/quests/{quest_id}/progress
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function updateProgress(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (empty($payload)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid request body',
        ], 400);
      }

      $required_fields = ['objective_id', 'action', 'entity_id'];
      foreach ($required_fields as $field) {
        if (empty($payload[$field])) {
          return new JsonResponse([
            'success' => FALSE,
            'error' => "Missing required field: {$field}",
          ], 400);
        }
      }

      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $entity_type = $payload['entity_type'] ?? 'party';
      $amount = $payload['amount'] ?? 1;

      $result = $quest_tracker->updateObjectiveProgress(
        $campaign_id,
        $quest_id,
        $payload['objective_id'],
        $payload['entity_id'],
        $amount
      );

      if (empty($result)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to update quest progress',
        ], 500);
      }

      return new JsonResponse([
        'success' => TRUE,
        'objective_state' => $result,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update quest progress: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Complete a quest.
   *
   * POST /api/campaign/{campaign_id}/quests/{quest_id}/complete
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $quest_id
   *   The quest ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function completeQuest(int $campaign_id, string $quest_id, Request $request): JsonResponse {
    try {
      $payload = json_decode($request->getContent(), TRUE);
      if (empty($payload['entity_id'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Missing required field: entity_id',
        ], 400);
      }

      $entity_type = $payload['entity_type'] ?? 'party';
      $outcome = $payload['outcome'] ?? 'success';

      $quest_tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');

      $result = $quest_tracker->completeQuest(
        $campaign_id,
        $quest_id,
        $payload['entity_id'],
        $outcome
      );

      if (empty($result)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Failed to complete quest',
        ], 500);
      }

      $this->postQuestCompletionDialog($campaign_id, $quest_id, (int) $payload['entity_id']);

      return new JsonResponse([
        'success' => TRUE,
        'message' => 'Quest completed',
        'quest_id' => $quest_id,
        'outcome' => $outcome,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to complete quest: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Get quest journal for a character.
   *
   * GET /api/campaign/{campaign_id}/character/{character_id}/quest-journal
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param string $character_id
   *   The character ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getQuestJournal(int $campaign_id, string $character_id): JsonResponse {
    try {
      $quests = $this->database->select('dc_campaign_quest_progress', 'qp')
        ->fields('qp', ['quest_id', 'campaign_id', 'character_id', 'objective_states', 'current_phase', 'started_at', 'completed_at', 'outcome'])
        ->condition('qp.campaign_id', $campaign_id)
        ->condition('qp.character_id', $character_id)
        ->execute()
        ->fetchAllAssoc('quest_id');

      $journal = array_map(function ($entry) {
        $entry->objective_states = json_decode($entry->objective_states, TRUE) ?? [];
        return $entry;
      }, $quests);

      return new JsonResponse([
        'success' => TRUE,
        'quests' => array_values($journal),
        'count' => count($journal),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch quest journal: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Post a quest completion message to room chat.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $quest_id
   *   Quest ID.
   * @param int $character_id
   *   Character ID completing the quest.
   */
  protected function postQuestCompletionDialog(int $campaign_id, string $quest_id, int $character_id): void {
    try {
      $quest = $this->database->select('dc_campaign_quests', 'q')
        ->fields('q', ['quest_name', 'giver_npc_id'])
        ->condition('campaign_id', $campaign_id)
        ->condition('quest_id', $quest_id)
        ->execute()
        ->fetchAssoc();

      if (empty($quest)) {
        return;
      }

      $speaker = 'Quest Giver';
      if (!empty($quest['giver_npc_id'])) {
        $npc_name = $this->database->select('dc_campaign_characters', 'cc')
          ->fields('cc', ['name'])
          ->condition('campaign_id', $campaign_id)
          ->condition('id', (int) $quest['giver_npc_id'])
          ->execute()
          ->fetchField();
        if (!empty($npc_name)) {
          $speaker = $npc_name;
        }
      }

      $message = sprintf('Quest complete: %s', $quest['quest_name'] ?? $quest_id);
      $this->chatService->postMessage(
        $campaign_id,
        'tavern_entrance',
        $speaker,
        $message,
        'npc',
        $character_id
      );
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to post quest completion dialog: @error', ['@error' => $e->getMessage()]);
    }
  }

}
