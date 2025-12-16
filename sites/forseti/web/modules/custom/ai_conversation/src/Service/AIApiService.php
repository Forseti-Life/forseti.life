<?php

namespace Drupal\ai_conversation\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_conversation\Traits\ConfigurableLoggingTrait;

/**
 * Service for AI API communication using AWS Bedrock with rolling conversation summary.
 */
class AIApiService {

  use ConfigurableLoggingTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Maximum number of recent messages to keep (configurable).
   *
   * @var int
   */
  protected $maxRecentMessages = 20;

  /**
   * Update summary every N messages.
   *
   * @var int
   */
  protected $summaryFrequency = 10;

  /**
   * Maximum tokens before triggering summary update.
   *
   * @var int
   */
  protected $maxTokensBeforeSummary = 6000;

  /**
   * Constructs a new AIApiService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('ai_conversation');
    $this->entityTypeManager = $entity_type_manager;
    
    // Load configuration.
    $config = $this->configFactory->get('ai_conversation.settings');
    $this->maxRecentMessages = $config->get('max_recent_messages') ?: 10;
    $this->maxTokensBeforeSummary = $config->get('max_tokens_before_summary') ?: 6000;
    $this->summaryFrequency = $config->get('summary_frequency') ?: 10;
  }

  /**
   * Check if we're running in a development environment.
   *
   * @return bool
   *   TRUE if in development environment, FALSE if in production.
   */
  protected function isDevelopmentEnvironment(): bool {
    // Check for GitHub Codespaces environment variable
    if (getenv('CODESPACES') === 'true') {
      return TRUE;
    }
    
    // Check for common development indicators
    if (getenv('ENVIRONMENT') === 'development' || 
        getenv('APP_ENV') === 'dev' ||
        $_SERVER['SERVER_NAME'] === 'localhost' ||
        strpos($_SERVER['HTTP_HOST'] ?? '', 'codespace') !== FALSE) {
      return TRUE;
    }
    
    // Check Drupal site URI for development patterns
    $request = \Drupal::request();
    $host = $request->getHost();
    if (strpos($host, 'localhost') !== FALSE || 
        strpos($host, '127.0.0.1') !== FALSE ||
        strpos($host, 'codespace') !== FALSE ||
        strpos($host, '.local') !== FALSE) {
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Generate a mock AI response for development environment.
   *
   * @param string $message
   *   The user message to respond to.
   *
   * @return array
   *   Mock response array with AI message and usage stats.
   */
  protected function generateMockResponse(string $message): array {
    $mock_responses = [
      "Service was called successfully. In a production environment, this would be Claude 3.5 Sonnet's actual response to: \"" . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '') . "\"",
      "Development mode active. AWS Bedrock service simulated. Your message was received and processed.",
      "Mock AI Response: I understand your message. This is a development environment simulation of Claude 3.5 Sonnet.",
      "Development Environment: AI service call completed. In production, this would connect to AWS Bedrock Claude."
    ];
    
    // Rotate through responses based on message hash for variety
    $response_index = abs(crc32($message)) % count($mock_responses);
    $ai_response = $mock_responses[$response_index];
    
    return [
      'ai_message' => $ai_response,
      'usage' => [
        'input_tokens' => strlen($message) / 4, // Rough token estimate
        'output_tokens' => strlen($ai_response) / 4,
        'total_tokens' => (strlen($message) + strlen($ai_response)) / 4
      ]
    ];
  }

  /**
   * Send a message to the AI model with rolling summary management.
   */
  public function sendMessage(NodeInterface $conversation, string $message) {
    try {
      // Check if we're in development environment and return mock response
      if ($this->isDevelopmentEnvironment()) {
        $this->logger->info('Development environment detected. Returning mock AI response.');
        $mock_response = $this->generateMockResponse($message);
        return $mock_response['ai_message']; // Return just the message string, not the full array
      }

      // Check if we need to update the summary before processing.
      $this->checkAndUpdateSummary($conversation);

      // Get AWS configuration from settings, with fallback to environment variables.
      $config = $this->configFactory->get('ai_conversation.settings');
      $aws_access_key = $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
      $aws_secret_key = $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
      $aws_region = $config->get('aws_region') ?: getenv('AWS_DEFAULT_REGION') ?: 'us-east-1';

      // Use the AWS SDK with credentials (either from config or environment).
      $sdk_config = [
        'region' => $aws_region,
        'version' => 'latest',
      ];
      
      // Only set explicit credentials if we have them, otherwise let AWS SDK use default credential chain
      if (!empty($aws_access_key) && !empty($aws_secret_key)) {
        $sdk_config['credentials'] = [
          'key' => $aws_access_key,
          'secret' => $aws_secret_key,
        ];
        $this->logInfo('Using AWS credentials from configuration');
      } else {
        // Let AWS SDK use its default credential chain (env vars, IAM roles, etc.)
        $this->logInfo('Using AWS SDK default credential chain (environment variables, IAM roles, etc.)');
      }

      $sdk = new \Aws\Sdk($sdk_config);
      
      $bedrock = $sdk->createBedrockRuntime();
      
      // Get the AI model from configuration or conversation.
      $model = $conversation->get('field_ai_model')->value ?: $config->get('aws_model') ?: 'anthropic.claude-3-5-sonnet-20240620-v1:0';
      
      // Validate and fix common model ID issues
      if (strpos($model, 'claude-sonnet-4') !== false) {
        $model = 'anthropic.claude-3-5-sonnet-20240620-v1:0';
        $this->logWarning('Invalid model ID detected, using default: @model', ['@model' => $model]);
      }

      // Build the optimized conversation context (summary + recent messages).
      $context = $this->buildOptimizedContext($conversation, $message);
      
      // Estimate input tokens.
      $input_tokens = $this->estimateTokens($context);

      // Get max tokens from config.
      $max_tokens = $config->get('max_tokens') ?: 4000;

      // Get system prompt from config if available.
      $base_system_prompt = $config->get('system_prompt');
      
      // Dynamically load Keith's resume from node 10 and append to system prompt
      $system_prompt = $this->buildDynamicSystemPrompt($base_system_prompt);
      
      // Debug logging for system prompt
      $this->logInfo('System prompt length: @length, First 100 chars: @preview', [
        '@length' => strlen($system_prompt ?? ''),
        '@preview' => substr($system_prompt ?? 'EMPTY', 0, 100),
      ]);

      // Build the request body.
      $request_body = [
        'anthropic_version' => 'bedrock-2023-05-31',
        'max_tokens' => $max_tokens,
        'messages' => [
          [
            'role' => 'user',
            'content' => $context
          ]
        ]
      ];

      // Add system prompt if configured.
      if (!empty($system_prompt)) {
        $request_body['system'] = $system_prompt;
        $this->logInfo('System prompt added to request body');
      } else {
        $this->logInfo('No system prompt found in configuration');
      }

      $response = $bedrock->invokeModel([
        'modelId' => $model,
        'body' => json_encode($request_body)
      ]);

      $result = json_decode($response['body']->getContents(), true);
      
      if (isset($result['content'][0]['text'])) {
        $ai_response = $result['content'][0]['text'];
        
        // Estimate output tokens and update total.
        $output_tokens = $this->estimateTokens($ai_response);
        $this->updateTokenCount($conversation, $input_tokens + $output_tokens);
        
        return $ai_response;
      }
      
      $this->logError('Unexpected API response format: @response', ['@response' => print_r($result, TRUE)]);
      throw new \Exception('Unexpected API response format');
      
    } catch (\Exception $e) {
      $this->logError('Error communicating with AI service: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw new \Exception('Failed to communicate with AI service: ' . $e->getMessage());
    }
  }

  /**
   * Update total token count for conversation.
   */
  private function updateTokenCount(NodeInterface $conversation, int $tokens) {
    $current_tokens = $conversation->get('field_total_tokens')->value ?: 0;
    $new_total = $current_tokens + $tokens;
    $conversation->set('field_total_tokens', $new_total);
    
    $this->logInfo('Updated token count for conversation @nid: +@tokens (total: @total)', [
      '@nid' => $conversation->id(),
      '@tokens' => $tokens,
      '@total' => $new_total,
    ]);
  }

  /**
   * Build optimized context using summary + recent messages.
   */
  private function buildOptimizedContext(NodeInterface $conversation, string $new_message) {
    // Check if this is the start of a conversation (no previous messages).
    $recent_messages = $this->getRecentMessages($conversation);
    $is_conversation_start = empty($recent_messages) && 
      (!$conversation->hasField('field_conversation_summary') || $conversation->get('field_conversation_summary')->isEmpty());
    
    // For new conversations, use enhanced context with Forseti mission info.
    if ($is_conversation_start) {
      $context = $this->buildInitialContext();
    } else {
      // For existing conversations, use the original system prompt.
      $system_prompt = $conversation->get('field_context')->value ?: 'You are a helpful AI assistant.';
      $context = $system_prompt . "\n\n";
    }

    // Add conversation summary if it exists.
    if ($conversation->hasField('field_conversation_summary') && !$conversation->get('field_conversation_summary')->isEmpty()) {
      $summary = $conversation->get('field_conversation_summary')->value;
      if (!empty($summary)) {
        $context .= "CONVERSATION SUMMARY (Previous Discussion):\n" . $summary . "\n\n";
      }
    }

    // Add recent messages.
    if (!empty($recent_messages)) {
      $context .= "RECENT CONVERSATION:\n";
      
      foreach ($recent_messages as $msg) {
        $role = $msg['role'] === 'user' ? 'Human' : 'Assistant';
        $context .= $role . ": " . $msg['content'] . "\n\n";
      }
    }

    // Add current message.
    $context .= "Human: " . $new_message . "\n\n";

    return $context;
  }

  /**
   * Build dynamic system prompt by using Forseti mission context.
   */
  private function buildDynamicSystemPrompt($base_system_prompt) {
    // For Forseti conversations, use the Forseti mission context
    if (empty($base_system_prompt)) {
      $base_system_prompt = $this->buildInitialContext();
    }
    
    $this->logInfo('Using Forseti mission context as system prompt');
    return $base_system_prompt;
  }

  /**
   * Parse resume content to extract key sections.
   */
  private function parseResumeContent($resume_content) {
    $parsed = [
      'education' => '',
      'summary' => '',
      'experience' => '',
      'technical' => ''
    ];
    
    // Extract education information - look for MBA and BS Psychology patterns
    if (preg_match('/MBA[^<\n]*(?:[^<\n]*Washington University[^<\n]*)?/i', $resume_content, $matches)) {
      $parsed['education'] .= "MBA from Washington University in St. Louis\n";
    }
    if (preg_match('/BS Psychology[^<\n]*(?:[^<\n]*Truman State[^<\n]*)?/i', $resume_content, $matches)) {
      $parsed['education'] .= "BS Psychology from Truman State University";
    }
    
    // If we didn't find specific patterns, try to extract from the header
    if (empty($parsed['education'])) {
      if (preg_match('/<strong>Keith Aumiller[^<]*<\/strong>.*?<p><strong>([^<]*)<\/strong><\/p>/s', $resume_content, $matches)) {
        $header_text = strip_tags($matches[1]);
        if (strpos($header_text, 'MBA') !== false || strpos($header_text, 'BS Psychology') !== false) {
          $parsed['education'] = "MBA from Washington University in St. Louis, BS Psychology from Truman State University";
        }
      }
    }
    
    // Extract executive profile/summary
    if (preg_match('/<strong>Executive Profile<\/strong><\/p><p>([^<]+(?:<[^>]*>[^<]*<\/[^>]*>[^<]*)*)/i', $resume_content, $matches)) {
      $summary = strip_tags($matches[1]);
      $parsed['summary'] = substr($summary, 0, 800) . (strlen($summary) > 800 ? '...' : '');
    }
    
    // Extract recent professional experience (St. Louis Integration)
    if (preg_match('/<strong>St\. Louis Integration LLC[^<]*<\/strong><br><strong>([^<]+)<\/strong>/i', $resume_content, $matches)) {
      $parsed['experience'] = "St. Louis Integration LLC - " . strip_tags($matches[1]);
    }
    
    // Extract technical expertise section
    if (preg_match('/<strong>Technical Expertise<\/strong>(.*?)(?=<strong>|$)/is', $resume_content, $matches)) {
      $tech_content = strip_tags($matches[1]);
      $parsed['technical'] = substr($tech_content, 0, 600) . (strlen($tech_content) > 600 ? '...' : '');
    }
    
    return $parsed;
  }

  /**
   * Build initial context for new conversations with Forseti mission.
   */
  private function buildInitialContext() {
    $context = "You are providing helpful information about Forseti Life, an AI-powered community safety platform dedicated to protecting Philadelphia residents through intelligent monitoring and predictive analytics. Your role is to inform users about Forseti's features, explain how the technology works, and provide guidance on community safety.\n\n";
    
    $context .= "FORSETI MISSION:\n";
    $context .= "\"AI Looking Out For Us\" - We believe technology should serve humanity by protecting communities and improving quality of life for as many people as possible. Named after the Norse god of justice and peaceful resolution, Forseti represents our commitment to fair, intelligent, and proactive safety measures.\n\n";
    
    $context .= "IMPORTANT - BE TRANSPARENT:\n";
    $context .= "When appropriate, acknowledge that you're Claude, an AI assistant by Anthropic, helping users learn about Forseti's safety platform. Be honest about your capabilities and limitations while providing helpful information about the Forseti system.\n\n";
    
    $context .= "CORE VALUES:\n";
    $context .= "• Vigilance: 24/7 AI monitoring for community safety\n";
    $context .= "• Transparency: Open data and clear communication about safety trends\n";
    $context .= "• Justice: Fair and unbiased safety measures for all community members\n";
    $context .= "• Community: Empowering residents with knowledge and tools\n";
    $context .= "• Prevention: Proactive measures to stop issues before they escalate\n\n";
    
    $context .= "FORSETI SAFETY FEATURES:\n";
    $context .= "• Live Crime Mapping: Real-time incident tracking with H3 hexagonal geospatial analysis\n";
    $context .= "• AI Pattern Recognition: Machine learning for temporal and spatial crime patterns\n";
    $context .= "• Predictive Analytics: Forecasting high-risk areas and times\n";
    $context .= "• Intelligent Alerts: Targeted notifications for safety threats\n";
    $context .= "• Community Engagement: Neighborhood watch coordination and resource sharing\n";
    $context .= "• Mobile Access: AmISafe mobile app for on-the-go safety monitoring\n\n";
    
    $context .= "TECHNOLOGY APPROACH:\n";
    $context .= "• H3 Geospatial System: Uber's hexagonal hierarchical indexing for precise location analysis\n";
    $context .= "• Real-time Data: Philadelphia Police Department open data and community submissions\n";
    $context .= "• Privacy-First: End-to-end encryption, anonymous reporting, GDPR compliant\n";
    $context .= "• Visual Intelligence: Interactive maps with heat overlays and historical trends\n\n";
    
    $context .= "PHILADELPHIA FOCUS:\n";
    $context .= "We've chosen to focus our initial efforts on Philadelphia because we believe in starting local and growing organically. By deeply understanding one community's unique safety challenges, we can create more effective solutions before expanding to other cities.\n\n";
    
    $context .= "PROFESSIONAL TONE:\n";
    $context .= "Respond with empathy and care when discussing safety concerns. Acknowledge that crime affects real people and communities. Be informative about how Forseti's technology helps without being alarmist. Encourage community involvement and emphasize that safety is a collaborative effort. Use a warm, accessible tone as if you're a knowledgeable community safety advocate explaining these systems to neighbors.\n\n";
    
    $context .= "GUIDANCE FOR RESPONSES:\n";
    $context .= "Provide helpful information about Forseti's safety features, explain how the AI technology works, discuss community safety best practices, and answer questions about the platform. Balance being informative about risks while remaining hopeful about solutions. When users ask about specific crime data, direct them to the Safety Map at /safety-map for real-time information. When discussing the platform, speak naturally about 'we' and 'our platform' as you're representing Forseti's mission to help the community.\n\n";
    
    return $context;
  }

  /**
   * Get resume content from node 10.
   */
  private function getResumeContent() {
    try {
      $node = $this->entityTypeManager->getStorage('node')->load(10);
      if ($node && $node->access('view')) {
        // Try to get body field content.
        if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
          $body_content = $node->get('body')->value;
          // Strip HTML tags and limit length to prevent context bloat.
          $clean_content = strip_tags($body_content);
          // Limit to reasonable length for AI context (about 2000 characters).
          if (strlen($clean_content) > 2000) {
            $clean_content = substr($clean_content, 0, 2000) . '... [Content truncated for brevity]';
          }
          return $clean_content;
        }
        // Fallback to title if no body.
        return $node->getTitle();
      }
    } catch (\Exception $e) {
      $this->logError('Error loading resume content from node 10: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
    
    // Return fallback content if node loading fails.
    return "Keith Miller - Principal at St. Louis Integration. Experienced in data integration, business intelligence, and AI implementations across Financial Services, Healthcare, and Energy sectors.";
  }

  /**
   * Get recent messages (up to maxRecentMessages).
   */
  private function getRecentMessages(NodeInterface $conversation) {
    $messages = [];
    
    if ($conversation->hasField('field_messages') && !$conversation->get('field_messages')->isEmpty()) {
      $all_messages = [];
      foreach ($conversation->get('field_messages') as $message_item) {
        $message_data = json_decode($message_item->value, TRUE);
        if ($message_data && isset($message_data['role']) && isset($message_data['content'])) {
          $all_messages[] = [
            'role' => $message_data['role'],
            'content' => $message_data['content'],
            'timestamp' => $message_data['timestamp'] ?? time(),
          ];
        }
      }

      // Sort by timestamp (most recent first) and take the last N messages.
      usort($all_messages, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
      });

      // Take the most recent messages (up to maxRecentMessages).
      $recent_messages = array_slice($all_messages, 0, $this->maxRecentMessages);
      
      // Reverse to get chronological order.
      $messages = array_reverse($recent_messages);
    }

    return $messages;
  }

  /**
   * Check if we need to update the conversation summary.
   */
  private function checkAndUpdateSummary(NodeInterface $conversation) {
    // Use field_summary_message_count exclusively for summary logic.
    $summary_message_count = $conversation->get('field_summary_message_count')->value ?? 0;
    $summary_message_count++;
    $conversation->set('field_summary_message_count', $summary_message_count);

    // If summary_message_count is divisible by summaryFrequency, generate summary and reset counter.
    if ($summary_message_count % $this->summaryFrequency === 0) {
      $this->updateConversationSummary($conversation);
      // Reset summary message count to 0 after summary generation.
      $conversation->set('field_summary_message_count', 0);
    }
  }

  /**
   * Update the conversation summary.
   */
  private function updateConversationSummary(NodeInterface $conversation) {
    try {
      // Get all messages.
      $all_messages = $this->getAllMessages($conversation);
      
      // Keep only the most recent 20 messages, summarize the rest.
      if (count($all_messages) <= $this->maxRecentMessages) {
        return; // Not enough messages to summarize.
      }

      $messages_to_summarize = array_slice($all_messages, 0, -$this->maxRecentMessages);
      
      if (empty($messages_to_summarize)) {
        return;
      }

      // Build context for summary generation.
      $summary_context = $this->buildSummaryContext($conversation, $messages_to_summarize);

      // Generate summary using Claude.
      $summary = $this->generateSummary($summary_context);

      // Update the conversation with the new summary.
      $conversation->set('field_conversation_summary', $summary);
      $conversation->set('field_summary_updated', time());
      
      // Remove old messages, keep only recent ones.
      $recent_messages = array_slice($all_messages, -$this->maxRecentMessages);
      $this->updateMessagesField($conversation, $recent_messages);
      
      $this->logInfo('Updated conversation summary for node @nid: summarized @count messages, kept @keep recent', [
        '@nid' => $conversation->id(),
        '@count' => count($messages_to_summarize),
        '@keep' => count($recent_messages),
      ]);
      
    } catch (\Exception $e) {
      $this->logError('Error updating conversation summary: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Generate a summary of the conversation messages.
   */
  private function generateSummary(string $context) {
    try {
      // Check if we're in development environment and return mock summary
      if ($this->isDevelopmentEnvironment()) {
        $this->logger->info('Development environment detected. Returning mock summary.');
        return "Development Mode Summary: This conversation has been simulated for development purposes. The conversation contains user messages and mock AI responses for testing the chat interface functionality.";
      }

      $sdk = new \Aws\Sdk([
        'region' => 'us-west-2',
        'version' => 'latest',
      ]);
      
      $bedrock = $sdk->createBedrockRuntime();

      $response = $bedrock->invokeModel([
        'modelId' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'body' => json_encode([
          'anthropic_version' => 'bedrock-2023-05-31',
          'max_tokens' => 1000,
          'messages' => [
            [
              'role' => 'user',
              'content' => $context
            ]
          ]
        ])
      ]);

      $result = json_decode($response['body']->getContents(), true);
      
      if (isset($result['content'][0]['text'])) {
        return $result['content'][0]['text'];
      }
      
      throw new \Exception('Unexpected API response format');
      
    } catch (\Exception $e) {
      $this->logError('Error generating summary: @message', [
        '@message' => $e->getMessage(),
      ]);
      return 'Summary generation failed.';
    }
  }

  /**
   * Build context for summary generation.
   */
  private function buildSummaryContext(NodeInterface $conversation, array $messages_to_summarize) {
    $context = "Please create a concise summary of the following conversation. ";
    $context .= "Focus on key topics discussed and important information that would be useful for continuing the conversation. ";
    $context .= "Keep the summary brief but informative.\n\n";

    // Add existing summary if it exists.
    if ($conversation->hasField('field_conversation_summary') && !$conversation->get('field_conversation_summary')->isEmpty()) {
      $existing_summary = $conversation->get('field_conversation_summary')->value;
      if (!empty($existing_summary)) {
        $context .= "EXISTING SUMMARY:\n" . $existing_summary . "\n\n";
        $context .= "UPDATE THE ABOVE SUMMARY WITH THE FOLLOWING NEW MESSAGES:\n\n";
      }
    }

    $context .= "CONVERSATION TO SUMMARIZE:\n";
    foreach ($messages_to_summarize as $msg) {
      $role = $msg['role'] === 'user' ? 'Human' : 'Assistant';
      $context .= $role . ": " . $msg['content'] . "\n\n";
    }

    return $context;
  }

  /**
   * Get all messages from the conversation.
   */
  private function getAllMessages(NodeInterface $conversation) {
    $messages = [];
    
    if ($conversation->hasField('field_messages') && !$conversation->get('field_messages')->isEmpty()) {
      foreach ($conversation->get('field_messages') as $message_item) {
        $message_data = json_decode($message_item->value, TRUE);
        if ($message_data && isset($message_data['role']) && isset($message_data['content'])) {
          $messages[] = [
            'role' => $message_data['role'],
            'content' => $message_data['content'],
            'timestamp' => $message_data['timestamp'] ?? time(),
          ];
        }
      }

      // Sort by timestamp.
      usort($messages, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
      });
    }

    return $messages;
  }

  /**
   * Update the messages field with new message array.
   */
  private function updateMessagesField(NodeInterface $conversation, array $messages) {
    $field_values = [];
    foreach ($messages as $message) {
      $field_values[] = ['value' => json_encode($message)];
    }
    $conversation->set('field_messages', $field_values);
  }

  /**
   * Estimate token count for the conversation context.
   */
  private function estimateTokenCount(NodeInterface $conversation) {
    $context = $this->buildOptimizedContext($conversation, '');
    return $this->estimateTokens($context);
  }

  /**
   * Estimate token count for text (rough approximation).
   */
  private function estimateTokens(string $text) {
    // Rough estimate: 1 token ≈ 4 characters.
    return intval(strlen($text) / 4);
  }

  /**
   * Build conversation history from node messages (legacy method for backward compatibility).
   */
  private function buildConversationHistory(NodeInterface $conversation) {
    // For backward compatibility, this now uses the optimized approach.
    return $this->getRecentMessages($conversation);
  }

  /**
   * Test API connection.
   */
  public function testConnection() {
    try {
      $sdk = new \Aws\Sdk([
        'region' => 'us-west-2',
        'version' => 'latest',
      ]);
      
      $bedrock = $sdk->createBedrockRuntime();

      $response = $bedrock->invokeModel([
        'modelId' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'body' => json_encode([
          'anthropic_version' => 'bedrock-2023-05-31',
          'max_tokens' => 10,
          'messages' => [
            [
              'role' => 'user',
              'content' => 'Hello'
            ]
          ]
        ])
      ]);

      $result = json_decode($response['body']->getContents(), true);
      
      if (isset($result['content'][0]['text'])) {
        return ['success' => TRUE, 'message' => 'AWS Bedrock connection successful'];
      } else {
        return ['success' => FALSE, 'message' => 'Unexpected API response'];
      }

    } catch (\Exception $e) {
      return ['success' => FALSE, 'message' => 'AWS Bedrock connection failed: ' . $e->getMessage()];
    }
  }

  /**
   * Get conversation statistics.
   */
  public function getConversationStats(NodeInterface $conversation) {
    $stats = [
      'total_messages' => $conversation->get('field_message_count')->value ?: 0,
      'recent_messages' => count($this->getRecentMessages($conversation)),
      'total_tokens' => $conversation->get('field_total_tokens')->value ?: 0,
      'has_summary' => !empty($conversation->get('field_conversation_summary')->value),
      'summary_updated' => $conversation->get('field_summary_updated')->value,
      'estimated_tokens' => $this->estimateTokenCount($conversation),
    ];

    return $stats;
  }

  /**
   * Create a community suggestion node.
   *
   * @param \Drupal\node\NodeInterface $conversation
   *   The conversation node where the suggestion was made.
   * @param string $summary
   *   AI-generated summary of the suggestion.
   * @param string $original_message
   *   The original user message containing the suggestion.
   * @param string $category
   *   The suggestion category.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created suggestion node or NULL on failure.
   */
  public function createSuggestion(NodeInterface $conversation, $summary, $original_message, $category) {
    try {
      // Get the current user (author of the conversation).
      $user = \Drupal::currentUser();
      
      // Create a title from the summary (first 100 chars).
      $title = mb_strlen($summary) > 100 ? mb_substr($summary, 0, 97) . '...' : $summary;
      
      // Create the suggestion node.
      $suggestion = Node::create([
        'type' => 'community_suggestion',
        'title' => $title,
        'uid' => $user->id(),
        'status' => TRUE,
        'field_suggestion_summary' => [
          'value' => $summary,
          'format' => 'plain_text',
        ],
        'field_original_message' => [
          'value' => $original_message,
          'format' => 'plain_text',
        ],
        'field_conversation_reference' => [
          'target_id' => $conversation->id(),
        ],
        'field_suggestion_category' => $category,
        'field_suggestion_status' => 'new',
      ]);
      
      $suggestion->save();
      
      $this->logInfo('Created community suggestion: @title (nid: @nid)', [
        '@title' => $title,
        '@nid' => $suggestion->id(),
      ]);
      
      return $suggestion;
      
    } catch (\Exception $e) {
      $this->logError('Failed to create community suggestion: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
