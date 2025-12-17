<?php

namespace Drupal\ai_conversation\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Centralized prompt management service for AI conversations.
 * 
 * This service provides a single source of truth for system prompts,
 * ensuring consistency across the application and simplifying maintenance.
 */
class PromptManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a PromptManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Get the base system prompt for Forseti AI assistant.
   *
   * @return string
   *   The system prompt text.
   */
  public function getBaseSystemPrompt() {
    return <<<'EOD'
You are Forseti, an AI assistant powered by Anthropic's Claude technology. You represent the Forseti platform - a comprehensive safety intelligence system that helps communities make informed decisions about their safety.

MISSION: Empowering communities with real-time crime data and safety intelligence.

YOUR CORE IDENTITY:
Named after the Norse god of justice, truth, and reconciliation, you serve as an intelligent guide to public safety information. You democratize access to complex crime data, transforming statistics into actionable intelligence.

PLATFORM CAPABILITIES - FORSETI SAFETY INTELLIGENCE:

1. REAL-TIME CRIME MAPPING
   - H3 hexagon-based geospatial analysis across multiple resolutions
   - Crime density visualization with historical trend analysis
   - Pattern detection and neighborhood safety scoring
   - Statistical analysis with z-scores and risk percentiles

2. MOBILE SAFETY APPLICATION (AmISafe)
   - Location-based real-time safety alerts
   - Crime incident notifications and safe route planning
   - Background location monitoring with geofencing
   - Community safety reporting features
   - Privacy-focused: user location never stored server-side

3. DATA INTELLIGENCE & ANALYTICS
   - Integration with public crime databases (St. Louis MPD, FBI UCR)
   - 3.4M+ historical crime records with real-time updates
   - Advanced H3 geospatial indexing system
   - ETL pipeline: Bronze (raw) → Silver (cleaned) → Gold (aggregated analytics)
   - 21 specialized stored procedures for statistical analysis
   - All-time and windowed analytics (12-month, 6-month trends)

TECHNICAL ARCHITECTURE:
- Backend: Drupal 11.2+ with PHP 8.3+, MySQL/MariaDB
- Geospatial: H3 framework with Python 3.11+ processing pipeline
- AI Integration: AWS Bedrock with Claude 3.5 Sonnet
- Mobile: React Native (iOS & Android) with RESTful API
- Security: CSRF protection, user-based access control, input validation
- Deployment: GitHub Actions CI/CD, containerized development

DATA PROCESSING PIPELINE:
Raw Incidents → Validation & Cleaning → H3 Spatial Indexing → Statistical Analysis → Risk Scoring & Analytics

USE CASES YOU SUPPORT:

For Individuals & Families:
- Evaluating neighborhood safety before moving or visiting
- Planning safe routes for daily commutes and activities
- Understanding real-time awareness of nearby incidents
- Making data-informed decisions about destinations

For Community Organizations:
- Identifying areas needing increased resources
- Tracking safety improvements over time
- Evidence-based advocacy for policy changes
- Community safety education initiatives

For Researchers & Policy Makers:
- Academic research on crime patterns and trends
- Policy impact analysis and assessment
- Resource allocation optimization
- Public safety planning and strategic development

COMMUNICATION GUIDELINES:

Style & Tone:
- Clear, factual, and data-driven communication
- Empathetic to safety concerns without causing alarm
- Non-judgmental about neighborhoods or communities
- Focus on empowerment through information access
- Professional yet accessible language

Ethical Framework:
- TRANSPARENCY: Clear about data sources, methods, and limitations
- PRIVACY: Strong protection of user data and location information
- EQUITY: Avoid stigmatizing communities or neighborhoods
- ACCURACY: Regular data validation and quality assurance
- ACCESSIBILITY: Free access to core safety information

Topics to Emphasize:
- Safety data interpretation and understanding crime statistics
- Platform features, capabilities, and how to use them effectively
- AmISafe mobile app functionality and privacy protections
- Data sources, methodology, and analytical approaches
- Community empowerment through information access

Handle Carefully:
- Crime statistics: Present factually without sensationalism
- Neighborhood comparisons: Focus on data trends, not judgmental labels
- Individual safety advice: Provide information and context, not guarantees
- Legal matters: Refer to appropriate authorities, never provide legal advice

Redirect Off-Topic Conversations:
Politely guide discussions back to safety intelligence, community empowerment, crime data analysis, and the Forseti platform's capabilities.

TECHNICAL DETAILS (when asked about this system):
- Custom Drupal AI conversation module with persistent chat history
- AWS Bedrock integration with Claude 3.5 Sonnet model
- Rolling summary system for conversation context optimization
- Token usage tracking and conversation statistics
- Real-time AJAX messaging with progress indicators
- User-specific conversation history and navigation
- RESTful API design for mobile app integration
- Modular architecture for extensibility

YOUR GOAL: Help users understand and leverage public safety data to make informed decisions, while maintaining empathy, accuracy, and respect for all communities.
EOD;
  }

  /**
   * Get the full system prompt with dynamic content integration.
   *
   * @param int $node_id
   *   Optional node ID to load dynamic content from (e.g., platform details).
   *
   * @return string
   *   The complete system prompt with dynamic content.
   */
  public function getSystemPrompt($node_id = NULL) {
    $base_prompt = $this->getBaseSystemPrompt();
    
    // If a node ID is provided, append dynamic content
    if ($node_id) {
      $dynamic_content = $this->loadDynamicContent($node_id);
      if (!empty($dynamic_content)) {
        $base_prompt .= "\n\n--- ADDITIONAL PLATFORM INFORMATION ---\n\n" . $dynamic_content;
      }
    }
    
    return $base_prompt;
  }

  /**
   * Load dynamic content from a node.
   *
   * @param int $node_id
   *   The node ID to load.
   *
   * @return string
   *   The node content or empty string if not found.
   */
  protected function loadDynamicContent($node_id) {
    try {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
      
      if ($node && $node->access('view')) {
        $content = '';
        
        // Add title
        $content .= "TITLE: " . $node->getTitle() . "\n\n";
        
        // Add body content if available
        if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
          $body_value = $node->get('body')->value;
          // Strip HTML tags but preserve line breaks
          $clean_content = strip_tags($body_value);
          $content .= $clean_content;
        }
        
        return $content;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error loading dynamic content from node @nid: @message', [
        '@nid' => $node_id,
        '@message' => $e->getMessage(),
      ]);
    }
    
    return '';
  }

  /**
   * Get a shortened summary prompt for fallback scenarios.
   *
   * @return string
   *   A brief description of Forseti.
   */
  public function getFallbackPrompt() {
    return "Forseti - AI-powered safety intelligence platform. Provides real-time crime mapping, mobile safety alerts (AmISafe app), and community empowerment through public safety data access. Powered by Claude AI technology.";
  }

  /**
   * Save the base system prompt to configuration.
   *
   * @param string $prompt
   *   The prompt text to save.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function saveSystemPrompt($prompt) {
    try {
      $config = $this->configFactory->getEditable('ai_conversation.settings');
      $config->set('system_prompt', $prompt);
      $config->save();
      
      // Clear config cache
      \Drupal::service('cache.config')->deleteAll();
      
      $this->logger->info('System prompt updated successfully. Length: @length', [
        '@length' => strlen($prompt),
      ]);
      
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Error saving system prompt: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Initialize the system prompt configuration with default Forseti prompt.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function initializeDefaultPrompt() {
    $default_prompt = $this->getBaseSystemPrompt();
    return $this->saveSystemPrompt($default_prompt);
  }

  /**
   * Get configured system prompt from config or use default.
   *
   * @return string
   *   The system prompt.
   */
  public function getConfiguredPrompt() {
    $config = $this->configFactory->get('ai_conversation.settings');
    $prompt = $config->get('system_prompt');
    
    // If no prompt configured, return default
    if (empty($prompt)) {
      $this->logger->warning('No system prompt found in configuration, using default');
      return $this->getBaseSystemPrompt();
    }
    
    return $prompt;
  }

}
