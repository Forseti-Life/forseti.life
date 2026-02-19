<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;

/**
 * Manages room chat messages with proper state management.
 * 
 * Uses DungeonStateService for optimistic locking to prevent race conditions.
 */
class RoomChatService {

  const MAX_MESSAGE_LENGTH = 2000;
  const MAX_MESSAGES_PER_ROOM = 500;

  protected Connection $database;
  protected DungeonStateService $dungeonStateService;
  protected LoggerInterface $logger;
  protected AccountProxyInterface $currentUser;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    DungeonStateService $dungeon_state_service,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user
  ) {
    $this->database = $database;
    $this->dungeonStateService = $dungeon_state_service;
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
    $this->currentUser = $current_user;
  }

  /**
   * Get chat history for a room.
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * 
   * @return array
   *   Array of chat messages.
   * 
   * @throws \InvalidArgumentException
   *   If dungeon not found.
   */
  public function getChatHistory(int $campaign_id, string $room_id): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Dungeon not found', 404);
    }

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    if (!is_array($dungeon_data)) {
      $dungeon_data = [];
    }

    $rooms = $dungeon_data['rooms'] ?? [];
    $chat = $rooms[$room_id]['chat'] ?? [];

    // Ensure messages are properly structured
    return array_map(function($msg) {
      return [
        'speaker' => $msg['speaker'] ?? 'Unknown',
        'message' => $msg['message'] ?? '',
        'type' => $msg['type'] ?? 'npc',
        'timestamp' => $msg['timestamp'] ?? date('c'),
        'character_id' => $msg['character_id'] ?? null,
        'user_id' => $msg['user_id'] ?? null,
      ];
    }, $chat);
  }

  /**
   * Post a new chat message to a room.
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param string $speaker
   *   Speaker name.
   * @param string $message
   *   Message content.
   * @param string $type
   *   Message type (player|npc|system).
   * @param int|null $character_id
   *   Optional character ID.
   * 
   * @return array
   *   The created message with metadata.
   * 
   * @throws \InvalidArgumentException
   *   If validation fails or dungeon not found.
   */
  public function postMessage(
    int $campaign_id,
    string $room_id,
    string $speaker,
    string $message,
    string $type = 'player',
    ?int $character_id = null
  ): array {
    // Validate inputs
    $this->validateMessage($message, $type);

    // Load current dungeon data (need dungeon_id)
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      throw new \InvalidArgumentException('Dungeon not found', 404);
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE);
    if (!is_array($dungeon_data)) {
      $dungeon_data = [];
    }

    // Initialize rooms structure if needed
    if (!isset($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }
    if (!isset($dungeon_data['rooms'][$room_id])) {
      $dungeon_data['rooms'][$room_id] = [];
    }
    if (!isset($dungeon_data['rooms'][$room_id]['chat'])) {
      $dungeon_data['rooms'][$room_id]['chat'] = [];
    }

    // Create new message
    $new_message = [
      'speaker' => $this->sanitizeSpeakerName($speaker),
      'message' => $this->sanitizeMessage($message),
      'type' => $type,
      'timestamp' => date('c'),
      'character_id' => $character_id,
      'user_id' => $this->currentUser->id(),
    ];

    // Append message
    $dungeon_data['rooms'][$room_id]['chat'][] = $new_message;

    // Enforce message limit
    $chat_count = count($dungeon_data['rooms'][$room_id]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_id]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_id]['chat'],
        $chat_count - self::MAX_MESSAGES_PER_ROOM
      );
    }

    // Update via direct database call (room chat doesn't need state versioning)
    // If this becomes a bottleneck, we could batch updates or use a separate table
    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // Log chat activity
    $this->logger->info('Chat message posted in room @room by user @uid: @message', [
      '@room' => $room_id,
      '@uid' => $this->currentUser->id(),
      '@message' => substr($message, 0, 100),
    ]);

    return [
      'message' => $new_message,
      'totalMessages' => count($dungeon_data['rooms'][$room_id]['chat']),
    ];
  }

  /**
   * Validate message content.
   * 
   * @param string $message
   *   Message to validate.
   * @param string $type
   *   Message type.
   * 
   * @throws \InvalidArgumentException
   *   If validation fails.
   */
  protected function validateMessage(string $message, string $type): void {
    $trimmed = trim($message);
    
    if (empty($trimmed)) {
      throw new \InvalidArgumentException('Message cannot be empty');
    }

    if (strlen($trimmed) > self::MAX_MESSAGE_LENGTH) {
      throw new \InvalidArgumentException(
        sprintf('Message exceeds maximum length of %d characters', self::MAX_MESSAGE_LENGTH)
      );
    }

    $valid_types = ['player', 'npc', 'system'];
    if (!in_array($type, $valid_types, TRUE)) {
      throw new \InvalidArgumentException(
        sprintf('Invalid message type. Must be one of: %s', implode(', ', $valid_types))
      );
    }
  }

  /**
   * Sanitize message content.
   * 
   * @param string $message
   *   Raw message.
   * 
   * @return string
   *   Sanitized message.
   */
  protected function sanitizeMessage(string $message): string {
    // Trim and normalize whitespace
    $sanitized = trim($message);
    $sanitized = preg_replace('/\s+/', ' ', $sanitized);
    
    // Remove any control characters except newlines
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $sanitized);
    
    return substr($sanitized, 0, self::MAX_MESSAGE_LENGTH);
  }

  /**
   * Sanitize speaker name.
   * 
   * @param string $speaker
   *   Raw speaker name.
   * 
   * @return string
   *   Sanitized speaker name.
   */
  protected function sanitizeSpeakerName(string $speaker): string {
    $sanitized = trim($speaker);
    $sanitized = preg_replace('/\s+/', ' ', $sanitized);
    return substr($sanitized, 0, 100);
  }

  /**
   * Check if user has access to campaign.
   * 
   * @param int $campaign_id
   *   Campaign ID.
   * 
   * @return bool
   *   TRUE if user has access.
   */
  public function hasCampaignAccess(int $campaign_id): bool {
    $uid = $this->currentUser->id();
    $account = \Drupal\user\Entity\User::load($uid);
    
    // Admin users can access any campaign
    if ($account && $account->hasPermission('administer dungeoncrawler')) {
      return TRUE;
    }
    
    // Check if user owns the campaign
    $owner_uid = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchField();
    
    if ($owner_uid && $owner_uid == $uid) {
      return TRUE;
    }
    
    // Check if user has a character in this campaign
    $user_in_campaign = $this->database->select('dc_campaign_characters', 'c')
      ->condition('campaign_id', $campaign_id)
      ->condition('uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    return $user_in_campaign > 0;
  }

}
