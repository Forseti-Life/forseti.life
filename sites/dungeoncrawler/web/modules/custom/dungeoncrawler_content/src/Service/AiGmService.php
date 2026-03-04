<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_conversation\Service\AIApiService;
use Psr\Log\LoggerInterface;

/**
 * Unified AI Game Master narration service.
 *
 * Generates immersive narration text for key game moments across all phases:
 * - Room entry (exploration)
 * - Encounter start / end
 * - Round start (combat)
 * - Entity defeated
 * - Phase transitions
 *
 * Uses the ai_conversation module's AIApiService for LLM calls, with
 * deterministic template fallbacks when AI is unavailable or disabled.
 *
 * Design:
 * - Single service, trigger-based methods (one per narration type).
 * - Context pulled from GameEventLogger for continuity.
 * - Respects config toggles (ai_gm_narration_enabled, per-trigger overrides).
 * - Prompt structure: system prompt + JSON context → plain text narration.
 */
class AiGmService {

  /**
   * AI API service (nullable — module may be absent).
   */
  protected ?AIApiService $aiApiService;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Game event logger for recent events context.
   */
  protected GameEventLogger $eventLogger;

  /**
   * AI session manager for per-campaign/NPC conversation isolation.
   */
  protected AiSessionManager $sessionManager;

  /**
   * Constructs the AiGmService.
   */
  public function __construct(
    ?AIApiService $ai_api_service,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    GameEventLogger $event_logger,
    AiSessionManager $session_manager
  ) {
    $this->aiApiService = $ai_api_service;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('dungeoncrawler');
    $this->eventLogger = $event_logger;
    $this->sessionManager = $session_manager;
  }

  // =========================================================================
  // Public narration triggers.
  // =========================================================================

  /**
   * Narrate a room entry during exploration.
   *
   * @param array $room
   *   Room data: id, name, description, lighting, terrain, entities.
   * @param array $dungeon_data
   *   Full dungeon_data payload for recent event context.
   * @param bool $first_visit
   *   Whether this is the player's first time entering the room.
   *
   * @return string|null
   *   Narration text, or NULL if disabled / AI unavailable.
   */
  public function narrateRoomEntry(array $room, array $dungeon_data, bool $first_visit = TRUE, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('room_entry')) {
      return NULL;
    }

    $context = [
      'trigger' => 'room_entry',
      'room' => [
        'name' => $room['name'] ?? 'Unknown Room',
        'description' => $room['description'] ?? '',
        'lighting' => $room['lighting'] ?? 'dim',
        'terrain' => $room['terrain'] ?? 'stone',
        'entity_count' => count($room['entities'] ?? []),
        'entity_names' => $this->extractEntityNames($room['entities'] ?? []),
      ],
      'first_visit' => $first_visit,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    $system = $this->buildSystemPrompt('room_entry');
    $prompt = $this->buildPrompt($context);

    return $this->invokeNarration($system, $prompt, 'room_entry', $context, $campaign_id);
  }

  /**
   * Narrate the start of an encounter.
   *
   * @param array $encounter_context
   *   Encounter details: participants, room, trigger reason.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Dramatic narration for encounter start.
   */
  public function narrateEncounterStart(array $encounter_context, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('encounter_start')) {
      return NULL;
    }

    $context = [
      'trigger' => 'encounter_start',
      'participants' => $this->summarizeParticipants($encounter_context['participants'] ?? []),
      'room_name' => $encounter_context['room_name'] ?? 'Unknown',
      'trigger_reason' => $encounter_context['reason'] ?? 'Hostile creatures detected',
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('encounter_start'),
      $this->buildPrompt($context),
      'encounter_start',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate the end of an encounter.
   *
   * @param array $encounter_result
   *   Result details: encounter_id, final_round, victory, defeated_enemies.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Narration wrapping up the encounter.
   */
  public function narrateEncounterEnd(array $encounter_result, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('encounter_end')) {
      return NULL;
    }

    $context = [
      'trigger' => 'encounter_end',
      'encounter_id' => $encounter_result['encounter_id'] ?? NULL,
      'final_round' => $encounter_result['final_round'] ?? 1,
      'victory' => $encounter_result['victory'] ?? TRUE,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 10),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('encounter_end'),
      $this->buildPrompt($context),
      'encounter_end',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate the start of a new combat round.
   *
   * @param int $round_number
   *   The round number starting.
   * @param array $game_state
   *   Current game state with initiative order.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Short tactical narration for the round.
   */
  public function narrateRoundStart(int $round_number, array $game_state, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('round_start')) {
      return NULL;
    }

    $context = [
      'trigger' => 'round_start',
      'round' => $round_number,
      'combatants_alive' => $this->countAliveCombatants($game_state),
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('round_start'),
      $this->buildPrompt($context),
      'round_start',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate an entity being defeated.
   *
   * @param string $entity_name
   *   Name of the defeated entity.
   * @param string $killer_name
   *   Name of the entity that dealt the final blow.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Dramatic defeat narration.
   */
  public function narrateEntityDefeated(string $entity_name, string $killer_name, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('entity_defeated')) {
      return NULL;
    }

    $context = [
      'trigger' => 'entity_defeated',
      'defeated_name' => $entity_name,
      'killer_name' => $killer_name,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('entity_defeated'),
      $this->buildPrompt($context),
      'entity_defeated',
      $context,
      $campaign_id
    );
  }

  /**
   * Narrate a phase transition.
   *
   * @param string $from_phase
   *   Phase being exited.
   * @param string $to_phase
   *   Phase being entered.
   * @param string $reason
   *   Reason for transition.
   * @param array $dungeon_data
   *   Full dungeon_data for context.
   *
   * @return string|null
   *   Narration bridging the phase change.
   */
  public function narratePhaseTransition(string $from_phase, string $to_phase, string $reason, array $dungeon_data, int $campaign_id = 0): ?string {
    if (!$this->isEnabled('phase_transition')) {
      return NULL;
    }

    $context = [
      'trigger' => 'phase_transition',
      'from_phase' => $from_phase,
      'to_phase' => $to_phase,
      'reason' => $reason,
      'recent_events' => $this->getRecentEventSummary($dungeon_data, 5),
    ];

    return $this->invokeNarration(
      $this->buildSystemPrompt('phase_transition'),
      $this->buildPrompt($context),
      'phase_transition',
      $context,
      $campaign_id
    );
  }

  // =========================================================================
  // AI Invocation.
  // =========================================================================

  /**
   * Invoke the LLM for narration and return plain text.
   *
   * Threads session context (prior conversation history + rolling summary)
   * into the prompt when a campaign_id is provided, ensuring continuity
   * within a campaign and isolation across campaigns.
   *
   * @param string $system_prompt
   *   System prompt establishing GM persona.
   * @param string $prompt
   *   JSON context prompt.
   * @param string $operation
   *   Operation name for usage tracking.
   * @param array $context
   *   Context metadata for tracking.
   * @param int $campaign_id
   *   Campaign ID for session scoping (0 = no session).
   *
   * @return string|null
   *   Narration text, or NULL on failure.
   */
  protected function invokeNarration(string $system_prompt, string $prompt, string $operation, array $context, int $campaign_id = 0): ?string {
    if ($this->aiApiService === NULL) {
      $this->logger->info('[AiGmService] AI API service not available, using fallback for @op', [
        '@op' => $operation,
      ]);
      return $this->fallbackForTrigger($context['trigger'] ?? $operation, $context);
    }

    // Thread session context into the prompt for campaign continuity.
    if ($campaign_id > 0) {
      $session_key = $this->sessionManager->gmSessionKey($campaign_id);
      $session_context = $this->sessionManager->buildSessionContext($session_key, $campaign_id, 8);
      if ($session_context !== '') {
        $prompt = $session_context . "\n\n---\nCURRENT REQUEST:\n" . $prompt;
      }
    }

    $max_tokens = $this->getMaxTokens();

    try {
      $response = $this->aiApiService->invokeModelDirect(
        $prompt,
        'dungeoncrawler_content',
        'ai_gm_' . $operation,
        [
          'trigger' => $operation,
          'campaign_context' => 'ai_gm_narration',
          'campaign_id' => $campaign_id,
        ],
        [
          'max_tokens' => $max_tokens,
          'skip_cache' => TRUE,
          'system_prompt' => $system_prompt,
        ]
      );

      if (!empty($response['success'])) {
        $text = trim((string) ($response['response'] ?? ''));
        // Strip markdown fences if the model wraps output.
        $text = $this->stripMarkdownFences($text);
        if ($text !== '') {
          // Record this exchange in the session for future context.
          if ($campaign_id > 0) {
            $session_key = $this->sessionManager->gmSessionKey($campaign_id);
            $this->sessionManager->appendMessage($session_key, $campaign_id, 'user', $prompt, ['trigger' => $operation]);
            $this->sessionManager->appendMessage($session_key, $campaign_id, 'assistant', $text, ['trigger' => $operation]);
          }
          return $text;
        }
      }

      $this->logger->warning('[AiGmService] AI narration failed for @op: @error', [
        '@op' => $operation,
        '@error' => (string) ($response['error'] ?? 'Empty response'),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->warning('[AiGmService] AI exception for @op: @error', [
        '@op' => $operation,
        '@error' => $e->getMessage(),
      ]);
    }

    // Fall back to template narration.
    return $this->fallbackForTrigger($context['trigger'] ?? $operation, $context);
  }

  // =========================================================================
  // Prompt building.
  // =========================================================================

  /**
   * Build the system prompt for the GM persona.
   *
   * @param string $trigger
   *   Narration trigger type.
   *
   * @return string
   *   System prompt text.
   */
  protected function buildSystemPrompt(string $trigger): string {
    $base = implode("\n", [
      'You are the Game Master for a Pathfinder 2e dungeon crawl.',
      'Write immersive, concise narration in second person ("You see…").',
      'Keep narration to 1-3 sentences. Be vivid but brief.',
      'Do not include dice rolls, mechanical numbers, or JSON.',
      'Do not break the fourth wall.',
      'Maintain dark fantasy tone with moments of wonder.',
    ]);

    // Trigger-specific guidance.
    $extras = [
      'room_entry' => 'Describe the room atmosphere, notable features, and any immediate impressions. Set the scene for exploration.',
      'encounter_start' => 'Build dramatic tension. Describe the enemies appearing and the moment combat begins. Make it feel urgent.',
      'encounter_end' => 'Describe the aftermath. Convey relief, exhaustion, or triumph. Mention the state of the battlefield.',
      'round_start' => 'Write one short tactical sentence about the ebb and flow of the battle. Keep under 20 words.',
      'entity_defeated' => 'Describe the final blow dramatically. Keep it impactful and respectful.',
      'phase_transition' => 'Bridge the narrative between phases. Describe the shift in pace and mood.',
    ];

    if (isset($extras[$trigger])) {
      $base .= "\n\nFor this narration: " . $extras[$trigger];
    }

    return $base;
  }

  /**
   * Build the JSON context prompt.
   *
   * @param array $context
   *   Context data for the narration trigger.
   *
   * @return string
   *   JSON-encoded prompt.
   */
  protected function buildPrompt(array $context): string {
    return json_encode([
      'task' => 'Write narration for the described game moment.',
      'context' => $context,
      'output_format' => 'plain_text_narration_only',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
  }

  // =========================================================================
  // Fallback narration (deterministic templates).
  // =========================================================================

  /**
   * Route to the appropriate fallback based on trigger type.
   */
  protected function fallbackForTrigger(string $trigger, array $context): ?string {
    switch ($trigger) {
      case 'room_entry':
        return $this->fallbackRoomEntry(
          $context['room'] ?? [],
          $context['first_visit'] ?? TRUE
        );

      case 'encounter_start':
        return $this->fallbackEncounterStart($context);

      case 'encounter_end':
        return $this->fallbackEncounterEnd($context);

      case 'round_start':
        return $this->fallbackRoundStart(
          $context['round'] ?? 1,
          []
        );

      case 'entity_defeated':
        return $this->fallbackEntityDefeated(
          $context['defeated_name'] ?? 'the creature',
          $context['killer_name'] ?? 'an unknown force'
        );

      case 'phase_transition':
        return $this->fallbackPhaseTransition(
          $context['from_phase'] ?? 'exploration',
          $context['to_phase'] ?? 'exploration',
          $context['reason'] ?? ''
        );

      default:
        return NULL;
    }
  }

  /**
   * Fallback narration for room entry.
   */
  protected function fallbackRoomEntry(array $room, bool $first_visit): ?string {
    $name = $room['name'] ?? 'the room';
    if ($first_visit) {
      return "You step into $name for the first time. The air shifts around you as shadows dance in the dim light.";
    }
    return "You re-enter $name. Familiar shadows greet your return.";
  }

  /**
   * Fallback narration for encounter start.
   */
  protected function fallbackEncounterStart(array $context): ?string {
    $reason = $context['trigger_reason'] ?? $context['reason'] ?? 'Hostile creatures detected';
    return "Steel rings in the darkness — $reason! Roll for initiative!";
  }

  /**
   * Fallback narration for encounter end.
   */
  protected function fallbackEncounterEnd(array $context): ?string {
    $victory = $context['victory'] ?? TRUE;
    $rounds = $context['final_round'] ?? 1;
    if ($victory) {
      return "After $rounds rounds of fierce combat, silence reclaims the chamber. Victory is yours.";
    }
    return "The battle concludes after $rounds rounds. The outcome hangs heavy in the air.";
  }

  /**
   * Fallback narration for round start.
   */
  protected function fallbackRoundStart(int $round, array $game_state): ?string {
    $templates = [
      'The clash of steel echoes as round %d begins.',
      'Round %d — the combatants reposition for another exchange.',
      'The battle rages on into round %d.',
      'Weapons ready — round %d commences.',
    ];
    $template = $templates[($round - 1) % count($templates)];
    return sprintf($template, $round);
  }

  /**
   * Fallback narration for entity defeated.
   */
  protected function fallbackEntityDefeated(string $entity_name, string $killer_name): ?string {
    return "$entity_name falls before $killer_name's decisive blow.";
  }

  /**
   * Fallback narration for phase transition.
   */
  protected function fallbackPhaseTransition(string $from, string $to, string $reason): ?string {
    $transitions = [
      'exploration_encounter' => 'The calm shatters — danger emerges!',
      'encounter_exploration' => 'The dust settles. You may explore freely once more.',
      'exploration_downtime' => 'You find a safe haven and settle in for rest.',
      'downtime_exploration' => 'Refreshed and restored, you venture forth once more.',
    ];

    $key = $from . '_' . $to;
    return $transitions[$key] ?? "The journey shifts from $from to $to. $reason";
  }

  // =========================================================================
  // Helpers.
  // =========================================================================

  /**
   * Check if AI GM narration is enabled for a given trigger.
   */
  protected function isEnabled(string $trigger = ''): bool {
    $config = $this->configFactory->get('dungeoncrawler_content.settings');

    // Master toggle.
    if (!$config->get('ai_gm_narration_enabled')) {
      return FALSE;
    }

    // Per-trigger override (default TRUE if master is on).
    if ($trigger !== '') {
      $key = 'ai_gm_trigger_' . $trigger;
      $value = $config->get($key);
      // If the per-trigger key isn't set, it defaults to enabled.
      if ($value !== NULL && !$value) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get max tokens for narration requests.
   */
  protected function getMaxTokens(): int {
    return (int) ($this->configFactory->get('dungeoncrawler_content.settings')
      ->get('ai_gm_narration_max_tokens') ?? 300);
  }

  /**
   * Build a summary of recent events for context.
   */
  protected function getRecentEventSummary(array $dungeon_data, int $count): array {
    $events = $this->eventLogger->getRecentEvents($dungeon_data, $count);
    $summary = [];

    foreach ($events as $event) {
      $entry = [
        'type' => $event['type'] ?? 'unknown',
        'actor' => $event['actor'] ?? NULL,
      ];

      // Include narration if present (for conversation continuity).
      if (!empty($event['narration'])) {
        $entry['narration'] = $event['narration'];
      }

      // Include key data fields.
      $data = $event['data'] ?? [];
      if (isset($data['degree'])) {
        $entry['degree'] = $data['degree'];
      }
      if (isset($data['damage'])) {
        $entry['damage'] = $data['damage'];
      }
      if (isset($data['target'])) {
        $entry['target'] = $data['target'];
      }

      $summary[] = $entry;
    }

    return $summary;
  }

  /**
   * Extract entity names from a room entity list.
   */
  protected function extractEntityNames(array $entities): array {
    $names = [];
    foreach ($entities as $entity) {
      $name = $entity['name'] ?? $entity['label'] ?? $entity['entity_ref'] ?? NULL;
      if ($name) {
        $names[] = $name;
      }
    }
    return array_slice($names, 0, 10); // Cap at 10 for prompt size.
  }

  /**
   * Summarize participants for prompt context.
   */
  protected function summarizeParticipants(array $participants): array {
    $summary = [];
    foreach ($participants as $p) {
      $summary[] = [
        'name' => $p['name'] ?? $p['entity_id'] ?? 'Unknown',
        'team' => $p['team'] ?? 'unknown',
        'level' => $p['level'] ?? NULL,
      ];
    }
    return array_slice($summary, 0, 12);
  }

  /**
   * Count alive combatants by team.
   */
  protected function countAliveCombatants(array $game_state): array {
    $counts = ['player' => 0, 'enemy' => 0];
    foreach ($game_state['initiative_order'] ?? [] as $c) {
      if (empty($c['is_defeated'])) {
        $team = $c['team'] ?? 'enemy';
        $counts[$team] = ($counts[$team] ?? 0) + 1;
      }
    }
    return $counts;
  }

  /**
   * Strip markdown code fences from model output.
   */
  protected function stripMarkdownFences(string $text): string {
    // Remove ```text ... ``` or ```json ... ``` wrappers.
    if (preg_match('/^```\w*\s*\n?(.*?)\n?```$/s', $text, $m)) {
      return trim($m[1]);
    }
    return $text;
  }

}
