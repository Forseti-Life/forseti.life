<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
   * Constructs a QuestTrackerController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Database $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dungeoncrawler_content');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('database'),
      $container->get('logger.factory')
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
        ->fields('q', ['quest_id', 'quest_name', 'quest_description', 'quest_type', 'quest_level'])
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

      $entity_id = $character_id ?? $party_id;
      $entity_type = $character_id ? 'character' : 'party';

      $result = $quest_tracker->startQuest($campaign_id, $quest_id, $entity_id, $entity_type);

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
        ->fields('qp', ['quest_id', 'campaign_id', 'entity_id', 'objective_states', 'current_phase', 'status', 'started_at', 'completed_at'])
        ->condition('qp.campaign_id', $campaign_id)
        ->condition('qp.entity_id', $character_id)
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

}
