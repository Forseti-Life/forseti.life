<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Drupal\ai_conversation\Service\PromptManager;
use Psr\Log\LoggerInterface;

// Hierarchical chat session integration.
// These bridge legacy dungeon_data JSON chat into the normalized session tables.

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
  protected NpcPsychologyService $psychologyService;
  protected ?NarrationEngine $narrationEngine;
  protected ?ChatSessionManager $chatSessionManager;

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
    ChatChannelManager $channel_manager,
    NpcPsychologyService $psychology_service,
    ?NarrationEngine $narration_engine = NULL,
    ?ChatSessionManager $chat_session_manager = NULL
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
    $this->psychologyService = $psychology_service;
    $this->narrationEngine = $narration_engine;
    $this->chatSessionManager = $chat_session_manager;
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

    // Bridge into the hierarchical chat session system.
    // This dual-writes to the normalized dc_chat_messages table via NarrationEngine.
    $this->bridgeToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $dungeon_data, $room_index,
      $speaker, $message, $type, $character_id, $channel
    );

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

    // Bridge GM reply into hierarchical session system.
    $this->bridgeGmReplyToSessionSystem(
      $campaign_id, $dungeon_id, $room_id, $narrative, $actions, $dice_rolls
    );

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

    // Find the live entity instance for real-time stats.
    $live_entity = [];
    $entities = $room_meta['entities'] ?? [];
    foreach ($entities as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? $ent['entity_ref'] ?? '';
      $ent_name = $ent['state']['metadata']['display_name'] ?? $ent['name'] ?? '';
      if ($ent_ref === $target_entity || $ent_name === $target_name) {
        $live_entity = $ent;
        break;
      }
    }

    // Ensure this NPC has a psychology profile (auto-create if needed).
    $npc_ref = $target_entity;
    if ($live_entity && !$npc_ref) {
      $npc_ref = $live_entity['entity_ref']['content_id']
        ?? $live_entity['entity_instance_id']
        ?? $target_entity;
    }
    if ($npc_ref) {
      $seed_data = [];
      if ($live_entity) {
        $meta = $live_entity['state']['metadata'] ?? [];
        $seed_data = [
          'display_name' => $meta['display_name'] ?? $target_name,
          'creature_type' => $live_entity['entity_ref']['content_id'] ?? $npc_ref,
          'level' => $live_entity['level'] ?? ($meta['stats']['level'] ?? 1),
          'description' => $live_entity['description'] ?? ($meta['description'] ?? ''),
          'stats' => $meta['stats'] ?? [],
          'role' => $live_entity['role'] ?? 'neutral',
          'initial_attitude' => $live_entity['attitude'] ?? 'indifferent',
        ];
      }
      $this->psychologyService->getOrCreateProfile($campaign_id, $npc_ref, $seed_data);
    }

    // Build full character sheet + psychology context for the AI.
    $npc_context = '';
    if ($npc_ref) {
      $npc_context = $this->psychologyService->buildNpcContextForPrompt(
        $campaign_id,
        $npc_ref,
        $live_entity
      );
    }
    // Fallback: use description from entity if no psychology profile.
    if (empty($npc_context) && $live_entity) {
      $npc_context = $live_entity['description'] ?? '';
    }

    // Build the prompt with full NPC context.
    $prompt = '';
    if ($session_context !== '') {
      $prompt .= $session_context . "\n\n---\n";
    }
    if (!empty($scene_parts)) {
      $prompt .= implode("\n", $scene_parts) . "\n\n";
    }
    if ($npc_context) {
      $prompt .= $npc_context . "\n\n";
    }
    $prompt .= "You are {$target_name}, an NPC in a Pathfinder 2e dungeon crawl.\n";
    $prompt .= "The player character is communicating with you via {$source_ability}.\n";
    $prompt .= "Stay in character as {$target_name}. Do NOT respond as the Game Master.\n";
    $prompt .= "Your responses should reflect your personality traits, current attitude, and motivations as described above.\n\n";
    $prompt .= "Conversation so far:\n" . implode("\n", $history_lines);
    $prompt .= "\n\nRespond in character as {$target_name}. Keep your reply concise (1-3 sentences).";

    $context_data = [
      'campaign_id' => $campaign_id,
      'room_id' => $room_id,
      'channel' => $channel_key,
      'npc_entity' => $target_entity,
      'session_key' => $ai_session_key,
    ];

    // Get NPC's current attitude for system prompt.
    $npc_attitude = 'indifferent';
    if ($npc_ref) {
      $npc_attitude = $this->psychologyService->getAttitude($campaign_id, $npc_ref);
    }

    try {
      $result = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'channel_npc_reply',
        $context_data,
        [
          'system_prompt' => "You are {$target_name}, a character in a tabletop RPG. Your current attitude toward the party is: {$npc_attitude}. Use the character sheet and psychology profile provided in the user prompt to stay in character. Reflect your personality traits, motivations, and recent inner thoughts in your tone and word choice. Do not break the fourth wall. Do not mention that you are an AI.",
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

    // Bridge NPC channel reply into hierarchical session system.
    $this->bridgeChannelReplyToSessionSystem(
      $campaign_id, $room_id, $channel_key, $target_name, $target_entity, $response_text
    );

    // Record inner monologue: NPC reacts privately to what the player said.
    if ($npc_ref) {
      $player_speaker = end($channel_chat)['speaker'] ?? 'the player';
      $this->psychologyService->recordInnerMonologue(
        $campaign_id,
        $npc_ref,
        'pc_action',
        "{$player_speaker} said via {$source_ability}: \"{$player_msg}\"",
        [
          'actor' => $player_speaker,
          'severity' => 'minor',
        ]
      );
    }

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
   * Ensure all NPCs in a room have psychology profiles.
   *
   * Call this on room entry to auto-create personality matrices for NPCs
   * that don't already have one. This enables full character-sheet-aware
   * inner monologues and AI portrayal from the first interaction.
   *
   * @param int $campaign_id
   *   Campaign ID.
   * @param array $room_entities
   *   Entities array from dungeon_data room.
   *
   * @return int
   *   Number of new profiles created.
   */
  public function ensureNpcProfiles(int $campaign_id, array $room_entities): int {
    return $this->psychologyService->ensureRoomNpcProfiles($campaign_id, $room_entities);
  }

  /**
   * Broadcast an event to all NPCs in a room for inner monologue processing.
   *
   * Use this when a significant event occurs (combat, diplomacy, death, etc.)
   * and nearby NPCs should react internally.
   *
   * @param int $campaign_id
   * @param array $npc_entity_refs
   * @param string $event_type
   * @param string $event_description
   * @param array $context
   *
   * @return array
   */
  public function broadcastNpcEvent(int $campaign_id, array $npc_entity_refs, string $event_type, string $event_description, array $context = []): array {
    return $this->psychologyService->broadcastEventToNpcs($campaign_id, $npc_entity_refs, $event_type, $event_description, $context);
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

  // =========================================================================
  // Session system bridge methods.
  //
  // These methods dual-write from the legacy dungeon_data JSON chat storage
  // into the new normalized dc_chat_sessions / dc_chat_messages hierarchy.
  // The NarrationEngine handles event routing, perception filtering, and
  // per-character narrative generation via the ChatSessionManager.
  //
  // This is a transitional bridge — eventually the legacy JSON path will be
  // removed and all chat flows through the session system directly.
  // =========================================================================

  /**
   * Bridge a player message from the legacy path into the session system.
   *
   * Routes the message as a room event through NarrationEngine::queueRoomEvent().
   * For player speech (room channel), this triggers immediate per-character
   * narration via GenAI. For other channels, it records the message in the
   * appropriate session.
   *
   * @param int $campaign_id
   * @param int|string $dungeon_id
   * @param string $room_id
   * @param array $dungeon_data
   *   Current dungeon_data payload.
   * @param int|string $room_index
   *   Room index in dungeon_data['rooms'].
   * @param string $speaker
   * @param string $message
   * @param string $type
   * @param int|null $character_id
   * @param string $channel
   */
  protected function bridgeToSessionSystem(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    array $dungeon_data,
    int|string $room_index,
    string $speaker,
    string $message,
    string $type,
    ?int $character_id,
    string $channel
  ): void {
    if ($this->narrationEngine === NULL) {
      return;
    }

    try {
      if ($channel === 'room') {
        // Room channel: route through NarrationEngine for perception-filtered narration.
        $event = [
          'type' => ($type === 'player') ? 'dialogue' : 'npc_speech',
          'speaker' => $speaker,
          'speaker_type' => $type,
          'speaker_ref' => $character_id ? (string) $character_id : '',
          'content' => $message,
          'language' => 'Common',
          'volume' => 'normal',
          'perception_dc' => NULL,
          'mechanical_data' => [],
          'visibility' => 'public',
        ];

        // Build present_characters from room entities and PC.
        $present_characters = $this->buildPresentCharactersFromDungeonData(
          $dungeon_data, $room_index, $campaign_id
        );

        $this->narrationEngine->queueRoomEvent(
          $campaign_id, $dungeon_id, $room_id, $event, $present_characters
        );
      }
      else {
        // Private channel (whisper/spell): record in dedicated session.
        $this->bridgeChannelMessageToSession(
          $campaign_id, $room_id, $channel, $speaker, $type, $character_id, $message
        );
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Session bridge error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Bridge a GM reply into the session system as a narrative event.
   */
  protected function bridgeGmReplyToSessionSystem(
    int $campaign_id,
    int|string $dungeon_id,
    string $room_id,
    string $narrative,
    array $actions = [],
    array $dice_rolls = []
  ): void {
    if ($this->chatSessionManager === NULL) {
      return;
    }

    try {
      $room_session = $this->chatSessionManager->ensureRoomSession($campaign_id, $dungeon_id, $room_id);

      // Post the GM narrative to the room session.
      $this->chatSessionManager->postMessage(
        (int) $room_session['id'],
        $campaign_id,
        'Game Master',
        'gm',
        '',
        $narrative,
        'narrative',
        'public',
        [
          'actions' => array_map(fn($a) => ['type' => $a['type'] ?? '', 'name' => $a['name'] ?? ''], $actions),
          'dice_rolls' => $dice_rolls,
        ],
        TRUE // feed up to dungeon + campaign
      );

      // If there were mechanical actions, also log to system log.
      if (!empty($actions) || !empty($dice_rolls)) {
        $sys_key = $this->chatSessionManager->systemLogSessionKey($campaign_id);
        $sys_session = $this->chatSessionManager->loadSession($sys_key);
        if ($sys_session) {
          $mechanical_summary = [];
          foreach ($actions as $a) {
            $mechanical_summary[] = ($a['name'] ?? 'Unknown') . ' (' . ($a['type'] ?? '') . ')';
          }
          foreach ($dice_rolls as $roll) {
            $label = $roll['label'] ?? 'Roll';
            $total = $roll['total'] ?? '?';
            $mechanical_summary[] = "{$label}: {$total}";
          }
          $this->chatSessionManager->postMessage(
            (int) $sys_session['id'],
            $campaign_id,
            'System',
            'system',
            '',
            implode('; ', $mechanical_summary),
            'mechanical',
            'public',
            ['actions' => $actions, 'dice_rolls' => $dice_rolls],
            FALSE
          );
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Session bridge GM reply error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Bridge a channel NPC reply into the session system.
   */
  protected function bridgeChannelReplyToSessionSystem(
    int $campaign_id,
    string $room_id,
    string $channel_key,
    string $npc_name,
    string $npc_entity_ref,
    string $response_text
  ): void {
    if ($this->chatSessionManager === NULL) {
      return;
    }

    try {
      // Parse channel type from key (whisper:entity → whisper session, spell:spell_key:target → spell session).
      $parts = explode(':', $channel_key);
      $channel_type = $parts[0] ?? 'whisper';

      $session = NULL;
      if ($channel_type === 'whisper') {
        $entity_ref = $parts[1] ?? $npc_entity_ref;
        $key = $this->chatSessionManager->whisperSessionKey($campaign_id, $entity_ref);
        $session = $this->chatSessionManager->loadSession($key);
        if (!$session) {
          $root = $this->chatSessionManager->loadSession(
            $this->chatSessionManager->campaignSessionKey($campaign_id)
          );
          $session = $this->chatSessionManager->getOrCreateSession(
            $campaign_id,
            'whisper',
            $key,
            "Whisper: {$npc_name}",
            $entity_ref,
            $root ? (int) $root['id'] : NULL,
            ['target_entity' => $npc_entity_ref, 'target_name' => $npc_name]
          );
        }
      }
      elseif ($channel_type === 'spell') {
        $spell_key = $parts[1] ?? 'generic';
        $target_ref = $parts[2] ?? $npc_entity_ref;
        $key = $this->chatSessionManager->spellSessionKey($campaign_id, $spell_key, $target_ref);
        $session = $this->chatSessionManager->loadSession($key);
        if (!$session) {
          $root = $this->chatSessionManager->loadSession(
            $this->chatSessionManager->campaignSessionKey($campaign_id)
          );
          $session = $this->chatSessionManager->getOrCreateSession(
            $campaign_id,
            'spell',
            $key,
            "Spell: {$spell_key} → {$npc_name}",
            $target_ref,
            $root ? (int) $root['id'] : NULL,
            ['spell_key' => $spell_key, 'target_entity' => $npc_entity_ref]
          );
        }
      }

      if ($session) {
        $this->chatSessionManager->postMessage(
          (int) $session['id'],
          $campaign_id,
          $npc_name,
          'npc',
          $npc_entity_ref,
          $response_text,
          'dialogue',
          'private',
          [],
          TRUE // feed up to campaign root
        );
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Session bridge channel reply error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Bridge a private channel message (player side) into the session system.
   */
  protected function bridgeChannelMessageToSession(
    int $campaign_id,
    string $room_id,
    string $channel_key,
    string $speaker,
    string $type,
    ?int $character_id,
    string $message
  ): void {
    if ($this->chatSessionManager === NULL) {
      return;
    }

    try {
      $parts = explode(':', $channel_key);
      $channel_type = $parts[0] ?? 'whisper';

      $session = NULL;
      if ($channel_type === 'whisper') {
        $entity_ref = $parts[1] ?? '';
        $key = $this->chatSessionManager->whisperSessionKey($campaign_id, $entity_ref);
        $session = $this->chatSessionManager->loadSession($key);
      }
      elseif ($channel_type === 'spell') {
        $spell_key = $parts[1] ?? 'generic';
        $target_ref = $parts[2] ?? '';
        $key = $this->chatSessionManager->spellSessionKey($campaign_id, $spell_key, $target_ref);
        $session = $this->chatSessionManager->loadSession($key);
      }

      if ($session) {
        $this->chatSessionManager->postMessage(
          (int) $session['id'],
          $campaign_id,
          $speaker,
          $type,
          $character_id ? (string) $character_id : '',
          $message,
          'dialogue',
          'private',
          [],
          TRUE
        );
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Session bridge channel message error: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Build the present_characters array from dungeon_data for NarrationEngine.
   *
   * Extracts PC + NPC entities in the current room and formats them into
   * the shape expected by NarrationEngine::queueRoomEvent().
   *
   * @return array
   *   Array of character descriptors for perception filtering.
   */
  protected function buildPresentCharactersFromDungeonData(
    array $dungeon_data,
    int|string $room_index,
    int $campaign_id
  ): array {
    $characters = [];
    $room = $dungeon_data['rooms'][$room_index] ?? [];

    // PC characters in the room.
    $pc_characters = $room['characters'] ?? [];
    foreach ($pc_characters as $pc) {
      $char_id = $pc['character_id'] ?? $pc['id'] ?? NULL;
      if ($char_id === NULL) {
        continue;
      }
      $characters[] = [
        'character_id' => $char_id,
        'name' => $pc['name'] ?? $pc['display_name'] ?? 'Unknown',
        'perception' => $pc['perception'] ?? ($pc['stats']['perception'] ?? 0),
        'languages' => $pc['languages'] ?? ['Common'],
        'senses' => $pc['senses'] ?? [],
        'conditions' => $pc['conditions'] ?? [],
        'position' => $pc['position'] ?? NULL,
      ];
    }

    // NPC entities in the room.
    $entities = $room['entities'] ?? [];
    foreach ($entities as $ent) {
      $ent_ref = $ent['entity_ref']['content_id'] ?? $ent['entity_ref'] ?? '';
      $meta = $ent['state']['metadata'] ?? [];
      $stats = $meta['stats'] ?? [];

      $characters[] = [
        'character_id' => $ent['entity_instance_id'] ?? $ent_ref,
        'name' => $meta['display_name'] ?? $ent['name'] ?? 'Unknown Entity',
        'perception' => $stats['perception'] ?? 0,
        'languages' => $ent['languages'] ?? ['Common'],
        'senses' => $ent['senses'] ?? [],
        'conditions' => $ent['conditions'] ?? ($meta['conditions'] ?? []),
        'position' => $ent['position'] ?? NULL,
      ];
    }

    return $characters;
  }

  // =========================================================================
  // Validation and sanitization.
  // =========================================================================

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
