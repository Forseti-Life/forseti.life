<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
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
  protected AIApiService $aiApiService;
  protected PromptManager $promptManager;
  protected GameplayActionProcessor $actionProcessor;
  protected AiSessionManager $sessionManager;
  protected ChatChannelManager $channelManager;

  /**
   * Constructor.
   */
  public function __construct(
    Connection $database,
    DungeonStateService $dungeon_state_service,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user,
    AIApiService $ai_api_service,
    PromptManager $prompt_manager,
    GameplayActionProcessor $action_processor,
    AiSessionManager $session_manager,
    ChatChannelManager $channel_manager
  ) {
    $this->database = $database;
    $this->dungeonStateService = $dungeon_state_service;
    $this->logger = $logger_factory->get('dungeoncrawler_chat');
    $this->currentUser = $current_user;
    $this->aiApiService = $ai_api_service;
    $this->promptManager = $prompt_manager;
    $this->actionProcessor = $action_processor;
    $this->sessionManager = $session_manager;
    $this->channelManager = $channel_manager;
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
  public function getChatHistory(int $campaign_id, string $room_id, string $channel = 'room', ?int $character_id = NULL): array {
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
    $room_entry = $this->findRoomByRoomId($rooms, $room_id);
    $chat = $room_entry['chat'] ?? [];

    // Filter by channel.
    $chat = $this->channelManager->filterMessagesByChannel($chat, $channel);

    // For non-room channels, verify the character has access.
    if ($channel !== 'room' && $character_id !== NULL) {
      $room_index = $this->findRoomIndex($rooms, $room_id);
      if ($room_index !== NULL) {
        $channels = $this->channelManager->getChannels($dungeon_data, $room_index);
        if (isset($channels[$channel])) {
          $access = $this->channelManager->validateChannelAccess($channels[$channel], $character_id);
          if (!$access['valid']) {
            return [];
          }
        }
      }
    }

    // Ensure messages are properly structured
    return array_map(function($msg) {
      return [
        'speaker' => $msg['speaker'] ?? 'Unknown',
        'message' => $msg['message'] ?? '',
        'type' => $msg['type'] ?? 'npc',
        'channel' => $msg['channel'] ?? 'room',
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
    ?int $character_id = null,
    string $channel = 'room'
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

    // Find the room index — rooms may be keyed by room_id or numerically indexed.
    $room_index = $this->findRoomIndex($dungeon_data['rooms'], $room_id);
    if ($room_index === NULL) {
      // Room doesn't exist yet; append a new entry.
      $dungeon_data['rooms'][] = ['room_id' => $room_id, 'chat' => []];
      $room_index = array_key_last($dungeon_data['rooms']);
    }
    if (!isset($dungeon_data['rooms'][$room_index]['chat'])) {
      $dungeon_data['rooms'][$room_index]['chat'] = [];
    }

    // Validate channel access for non-room channels.
    if ($channel !== 'room') {
      $channels = $this->channelManager->getChannels($dungeon_data, $room_index);
      if (!isset($channels[$channel])) {
        throw new \InvalidArgumentException('Channel not found: ' . $channel);
      }
      if ($character_id !== null) {
        $access = $this->channelManager->validateChannelAccess($channels[$channel], $character_id, $message);
        if (!$access['valid']) {
          throw new \InvalidArgumentException($access['error']);
        }
      }
    }

    // Create new message
    $new_message = [
      'speaker' => $this->sanitizeSpeakerName($speaker),
      'message' => $this->sanitizeMessage($message),
      'type' => $type,
      'channel' => $channel,
      'timestamp' => date('c'),
      'character_id' => $character_id,
      'user_id' => $this->currentUser->id(),
    ];

    // Append message
    $dungeon_data['rooms'][$room_index]['chat'][] = $new_message;

    // Enforce message limit
    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
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

    // Generate AI response (GM for room channel, NPC for private channels).
    $gm_response = NULL;
    $state_diff = NULL;
    if ($type === 'player') {
      if ($channel === 'room') {
        // Room channel: GM responds.
        $gm_result = $this->generateGmReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id);
      } else {
        // Private channel: target NPC responds.
        $channel_def = $dungeon_data['rooms'][$room_index]['channels'][$channel] ?? [];
        $gm_result = $this->generateChannelNpcReply($campaign_id, $room_id, $room_index, $dungeon_id, $dungeon_data, $character_id, $channel, $channel_def);
      }
      if ($gm_result !== NULL) {
        $gm_response = $gm_result['message'];
        $state_diff = $gm_result['state_diff'] ?? NULL;
      }
    }

    $result = [
      'message' => $new_message,
      'totalMessages' => count($dungeon_data['rooms'][$room_index]['chat']),
    ];
    if ($gm_response !== NULL) {
      $result['gm_response'] = $gm_response;
    }
    if ($state_diff !== NULL) {
      $result['state_diff'] = $state_diff;
    }
    return $result;
  }

  /**
   * Generate a GM reply via the AI and persist it, processing mechanical actions.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Array index of the room in dungeon_data['rooms'].
   * @param int $dungeon_id
   *   Dungeon record ID (for DB update).
   * @param array $dungeon_data
   *   Current dungeon_data payload (already contains the player message).
   * @param int|null $character_id
   *   The acting character's ID (for mechanical state updates).
   *
   * @return array|null
   *   ['message' => array, 'state_diff' => array|null], or NULL on failure.
   */
  protected function generateGmReply(int $campaign_id, string $room_id, int|string $room_index, int|string $dungeon_id, array &$dungeon_data, ?int $character_id = NULL): ?array {
    $chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];

    // Build scene context from the room definition.
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $scene_parts = [];
    if (!empty($room_meta['name'])) {
      $scene_parts[] = 'Current room: ' . $room_meta['name'];
    }
    if (!empty($room_meta['description'])) {
      $scene_parts[] = 'Room description: ' . $room_meta['description'];
    }

    // Build the user prompt from recent chat history (last 10 messages).
    $recent = array_slice($chat, -10);
    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Build session context for campaign-scoped conversation continuity.
    // This ensures the GM remembers prior interactions within this campaign
    // but starts fresh for a new campaign.
    $session_key = $this->sessionManager->roomChatSessionKey($campaign_id);
    $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 6);

    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if (!empty($scene_parts)) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    $prompt .= "Recent conversation:\n" . implode("\n", $history_lines);
    $prompt .= "\n\nRespond in character as the Game Master. Keep your reply concise (2-4 sentences). If the player is performing a mechanical action (casting a spell, using a skill, using a feat, attacking, exploring), include the JSON action block as instructed in your system prompt.";

    // Build enhanced system prompt with character abilities if character_id is available.
    $base_system_prompt = $this->promptManager->getBaseSystemPrompt();
    $system_prompt = $base_system_prompt;

    $char_data = NULL;
    if ($character_id) {
      $char_data = $this->actionProcessor->loadCharacterData($character_id);
      if ($char_data) {
        $system_prompt = $this->actionProcessor->buildEnhancedSystemPrompt(
          $base_system_prompt,
          $char_data,
          $room_meta
        );
      }
    }

    $context_data = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'session_key' => $session_key,
    ];

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'room_chat_gm_reply',
        $context_data,
        [
          'system_prompt' => $system_prompt,
          'max_tokens' => 800,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->error('AI API error generating GM reply: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      $this->logger->warning('AI API returned unsuccessful or empty response for GM reply in room @room', [
        '@room' => $room_id,
      ]);
      return NULL;
    }

    $response_text = $result['response'];

    // Parse the response for mechanical actions.
    $parsed = $this->actionProcessor->parseResponse($response_text);
    $narrative = $parsed['narrative'];
    $actions = $parsed['actions'] ?? [];
    $dice_rolls = $parsed['dice_rolls'] ?? [];

    // Apply state mutations if there are mechanical actions.
    $char_diff = [];
    $room_diff = [];
    $state_diff = NULL;

    if (!empty($actions)) {
      // Apply character state changes.
      if ($character_id) {
        $char_diff = $this->actionProcessor->applyCharacterStateChanges($character_id, $actions);
      }

      // Apply room/dungeon state changes.
      $room_diff = $this->actionProcessor->applyRoomStateChanges(
        $dungeon_id, $campaign_id, $room_index, $dungeon_data, $actions
      );

      // Build the state diff summary for the client.
      $state_diff = $this->actionProcessor->buildStateDiffSummary(
        $char_diff, $room_diff, $dice_rolls, $actions
      );

      $this->logger->info('Mechanical actions processed: @count actions, @rolls dice rolls', [
        '@count' => count($actions),
        '@rolls' => count($dice_rolls),
      ]);
    }

    $gm_message = [
      'speaker' => 'Game Master',
      'message' => $narrative,
      'type' => 'npc',
      'channel' => 'room',
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
    ];

    // If there were mechanical actions, attach a summary to the message.
    if (!empty($actions)) {
      $gm_message['mechanical_actions'] = array_map(function($a) {
        return [
          'type' => $a['type'] ?? 'unknown',
          'name' => $a['name'] ?? 'Unknown',
        ];
      }, $actions);
      if (!empty($dice_rolls)) {
        $gm_message['dice_rolls'] = $dice_rolls;
      }
    }

    // Persist the GM reply (and any dungeon_data state changes from actions).
    $dungeon_data['rooms'][$room_index]['chat'][] = $gm_message;

    // Enforce message limit again.
    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - self::MAX_MESSAGES_PER_ROOM
      );
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // Record this exchange in the campaign room chat session for future context.
    $player_msg_text = end($chat)['message'] ?? '';
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $player_msg_text);
    $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $narrative);

    $this->logger->info('GM reply persisted in room @room (@chars chars, @actions_count mechanical actions)', [
      '@room' => $room_id,
      '@chars' => strlen($narrative),
      '@actions_count' => count($actions),
    ]);

    return [
      'message' => $gm_message,
      'state_diff' => $state_diff,
    ];
  }

  /**
   * Generate an NPC reply for a private channel (whisper/spell).
   *
   * The AI responds as the target NPC rather than the GM. Uses the
   * per-NPC AI session from AiSessionManager for conversation memory.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|string $room_index
   *   Room index.
   * @param int|string $dungeon_id
   *   Dungeon record ID.
   * @param array &$dungeon_data
   *   Dungeon data (modified in place).
   * @param int|null $character_id
   *   Acting character ID.
   * @param string $channel_key
   *   Channel key (e.g. "whisper:goblin_1").
   * @param array $channel_def
   *   Channel definition from dungeon_data.
   *
   * @return array|null
   *   ['message' => array, 'state_diff' => array|null], or NULL.
   */
  protected function generateChannelNpcReply(
    int $campaign_id,
    string $room_id,
    int|string $room_index,
    int|string $dungeon_id,
    array &$dungeon_data,
    ?int $character_id,
    string $channel_key,
    array $channel_def
  ): ?array {
    // Only respond if the channel allows NPC responses.
    if (empty($channel_def['npc_responds'])) {
      return NULL;
    }

    $target_name = $channel_def['target_name'] ?? 'Unknown NPC';
    $target_entity = $channel_def['target_entity'] ?? '';
    $source_ability = $channel_def['source_ability'] ?? 'whisper';

    // Gather channel-specific chat history (only messages on this channel).
    $all_chat = $dungeon_data['rooms'][$room_index]['chat'] ?? [];
    $channel_chat = $this->channelManager->filterMessagesByChannel($all_chat, $channel_key);
    $recent = array_slice($channel_chat, -10);

    $history_lines = [];
    foreach ($recent as $msg) {
      $speaker = $msg['speaker'] ?? 'Unknown';
      $text = $msg['message'] ?? '';
      $history_lines[] = "{$speaker}: {$text}";
    }

    // Build NPC-scoped session context from AiSessionManager.
    $ai_session_key = $this->channelManager->getAiSessionKeyForChannel($campaign_id, $channel_key);
    $session_context = $this->sessionManager->buildSessionContext($ai_session_key, $campaign_id, 6);

    // Build room context.
    $room_meta = $dungeon_data['rooms'][$room_index] ?? [];
    $scene_parts = [];
    if (!empty($room_meta['name'])) {
      $scene_parts[] = 'Current room: ' . $room_meta['name'];
    }

    // Find NPC data from room entities for personality.
    $npc_description = '';
    $entities = $room_meta['entities'] ?? [];
    foreach ($entities as $ent) {
      if (($ent['entity_ref'] ?? '') === $target_entity || ($ent['name'] ?? '') === $target_name) {
        $npc_description = $ent['description'] ?? '';
        break;
      }
    }

    // Build the prompt: instruct AI to respond as the NPC, not as the GM.
    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if (!empty($scene_parts)) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    $prompt .= "You are {$target_name}, an NPC in a Pathfinder 2e dungeon crawl.\n";
    if ($npc_description) {
      $prompt .= "Your description: {$npc_description}\n";
    }
    $prompt .= "The player character is communicating with you via {$source_ability}.\n";
    $prompt .= "Stay in character as {$target_name}. Do NOT respond as the Game Master.\n\n";
    $prompt .= "Conversation so far:\n" . implode("\n", $history_lines);
    $prompt .= "\n\nRespond in character as {$target_name}. Keep your reply concise (1-3 sentences).";

    $context_data = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel_key,
      'npc_entity' => $target_entity,
      'session_key' => $ai_session_key,
    ];

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'channel_npc_reply',
        $context_data,
        [
          'system_prompt' => "You are {$target_name}, a character in a tabletop RPG. Respond naturally in character. Do not break the fourth wall. Do not mention that you are an AI.",
          'max_tokens' => 400,
          'skip_cache' => TRUE,
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->error('AI API error generating NPC reply on channel @channel: @msg', [
        '@channel' => $channel_key,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }

    if (empty($result['success']) || empty($result['response'])) {
      return NULL;
    }

    $response_text = trim($result['response']);

    $npc_message = [
      'speaker' => $target_name,
      'message' => $response_text,
      'type' => 'npc',
      'channel' => $channel_key,
      'timestamp' => date('c'),
      'character_id' => NULL,
      'user_id' => 0,
    ];

    // Persist the NPC reply.
    $dungeon_data['rooms'][$room_index]['chat'][] = $npc_message;

    // Enforce message limit.
    $chat_count = count($dungeon_data['rooms'][$room_index]['chat']);
    if ($chat_count > self::MAX_MESSAGES_PER_ROOM) {
      $dungeon_data['rooms'][$room_index]['chat'] = array_slice(
        $dungeon_data['rooms'][$room_index]['chat'],
        $chat_count - self::MAX_MESSAGES_PER_ROOM
      );
    }

    $this->database->update('dc_campaign_dungeons')
      ->fields([
        'dungeon_data' => json_encode($dungeon_data),
        'updated' => time(),
      ])
      ->condition('dungeon_id', $dungeon_id)
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // Record in NPC-specific AI session.
    $player_msg = end($channel_chat)['message'] ?? '';
    $this->sessionManager->appendMessage($ai_session_key, $campaign_id, 'user', $player_msg);
    $this->sessionManager->appendMessage($ai_session_key, $campaign_id, 'assistant', $response_text);

    $this->logger->info('NPC @npc reply on channel @channel (@chars chars)', [
      '@npc' => $target_name,
      '@channel' => $channel_key,
      '@chars' => strlen($response_text),
    ]);

    return [
      'message' => $npc_message,
      'state_diff' => NULL,
    ];
  }

  /**
   * Get available channels for a room (for the channel selector UI).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param int|null $character_id
   *   Character ID to filter visibility.
   *
   * @return array
   *   ['channels' => array, 'active_channel' => string]
   */
  public function getChannelsForRoom(int $campaign_id, string $room_id, ?int $character_id = NULL): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['channels' => [], 'active_channel' => 'room'];
    }

    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE) ?: [];
    $rooms = $dungeon_data['rooms'] ?? [];
    $room_index = $this->findRoomIndex($rooms, $room_id);

    if ($room_index === NULL) {
      return ['channels' => ['room' => ['key' => 'room', 'label' => 'Room', 'type' => 'room', 'active' => TRUE]], 'active_channel' => 'room'];
    }

    $channels = $this->channelManager->getChannels($dungeon_data, $room_index);
    $visible = $this->channelManager->getVisibleChannels($channels, $character_id);

    // Only return active channels.
    $active_channels = array_filter($visible, fn($ch) => $ch['active'] ?? TRUE);

    return [
      'channels' => $active_channels,
      'active_channel' => 'room',
    ];
  }

  /**
   * Open a channel in a room (delegates to ChatChannelManager).
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param string $room_id
   *   Room UUID.
   * @param string $channel_key
   *   Channel key to open.
   * @param string $opened_by
   *   Character ID that opened it.
   * @param string $target_entity_ref
   *   Target entity ref.
   * @param string $target_name
   *   Target display name.
   * @param string $source_ability
   *   Spell/ability that opens the channel.
   *
   * @return array
   *   ['success' => bool, 'channel' => array|null, 'error' => string|null]
   */
  public function openChannel(
    int $campaign_id,
    string $room_id,
    string $channel_key,
    string $opened_by,
    string $target_entity_ref,
    string $target_name,
    string $source_ability = 'whisper'
  ): array {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['success' => FALSE, 'channel' => NULL, 'error' => 'Dungeon not found'];
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE) ?: [];
    if (!isset($dungeon_data['rooms'])) {
      $dungeon_data['rooms'] = [];
    }

    $room_index = $this->findRoomIndex($dungeon_data['rooms'], $room_id);
    if ($room_index === NULL) {
      return ['success' => FALSE, 'channel' => NULL, 'error' => 'Room not found'];
    }

    $result = $this->channelManager->openChannel(
      $dungeon_data,
      $room_index,
      $channel_key,
      $opened_by,
      $target_entity_ref,
      $target_name,
      $source_ability
    );

    if ($result['success']) {
      // Persist the updated dungeon_data.
      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data),
          'updated' => time(),
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();

      // Post a system message on the channel.
      $channel_def = $result['channel'];
      $system_msg = [
        'speaker' => 'System',
        'message' => sprintf('%s channel opened with %s.', $channel_def['label'] ?? 'Private', $target_name),
        'type' => 'system',
        'channel' => $channel_key,
        'timestamp' => date('c'),
        'character_id' => NULL,
        'user_id' => 0,
      ];
      $dungeon_data['rooms'][$room_index]['chat'][] = $system_msg;

      $this->database->update('dc_campaign_dungeons')
        ->fields(['dungeon_data' => json_encode($dungeon_data)])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    return $result;
  }

  /**
   * Close a channel in a room.
   */
  public function closeChannel(int $campaign_id, string $room_id, string $channel_key): bool {
    $record = $this->database->select('dc_campaign_dungeons', 'd')
      ->fields('d', ['dungeon_id', 'dungeon_data'])
      ->condition('campaign_id', $campaign_id)
      ->orderBy('updated', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return FALSE;
    }

    $dungeon_id = $record['dungeon_id'];
    $dungeon_data = json_decode($record['dungeon_data'] ?? '{}', TRUE) ?: [];
    $room_index = $this->findRoomIndex($dungeon_data['rooms'] ?? [], $room_id);
    if ($room_index === NULL) {
      return FALSE;
    }

    $closed = $this->channelManager->closeChannel($dungeon_data, $room_index, $channel_key);

    if ($closed) {
      $this->database->update('dc_campaign_dungeons')
        ->fields([
          'dungeon_data' => json_encode($dungeon_data),
          'updated' => time(),
        ])
        ->condition('dungeon_id', $dungeon_id)
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    return $closed;
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

  /**
   * Find a room entry by room_id in a rooms array (may be keyed or indexed).
   *
   * @param array $rooms
   *   The rooms array from dungeon_data.
   * @param string $room_id
   *   The room UUID to find.
   *
   * @return array
   *   The room entry, or empty array if not found.
   */
  protected function findRoomByRoomId(array $rooms, string $room_id): array {
    // Direct key match (rooms keyed by room_id).
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $rooms[$room_id];
    }

    // Numeric/sequential array — search by room_id field.
    foreach ($rooms as $room) {
      if (is_array($room) && ($room['room_id'] ?? '') === $room_id) {
        return $room;
      }
    }

    return [];
  }

  /**
   * Find the array index for a room by room_id.
   *
   * @param array $rooms
   *   The rooms array from dungeon_data.
   * @param string $room_id
   *   The room UUID to find.
   *
   * @return int|string|null
   *   The array key, or NULL if not found.
   */
  protected function findRoomIndex(array $rooms, string $room_id): int|string|null {
    // Direct key match.
    if (isset($rooms[$room_id]) && is_array($rooms[$room_id])) {
      return $room_id;
    }

    // Numeric/sequential array — search by room_id field.
    foreach ($rooms as $key => $room) {
      if (is_array($room) && ($room['room_id'] ?? '') === $room_id) {
        return $key;
      }
    }

    return NULL;
  }

}
