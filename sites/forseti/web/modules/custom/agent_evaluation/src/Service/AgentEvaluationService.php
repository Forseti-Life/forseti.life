<?php

namespace Drupal\agent_evaluation\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\forseti_safety_content\Service\AgentPowerService;

/**
 * Service for managing agent evaluations.
 */
class AgentEvaluationService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The AI API service.
   *
   * @var \Drupal\ai_conversation\Service\AIApiService
   */
  protected $aiApiService;

  /**
   * The Agent Power service.
   *
   * @var \Drupal\forseti_safety_content\Service\AgentPowerService
   */
  protected $agentPowerService;

  /**
   * Constructs a new AgentEvaluationService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\forseti_safety_content\Service\AgentPowerService $agent_power_service
   *   The agent power service.
   * @param \Drupal\ai_conversation\Service\AIApiService $ai_api_service
   *   The AI API service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, AgentPowerService $agent_power_service, $ai_api_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->agentPowerService = $agent_power_service;
    $this->aiApiService = $ai_api_service;
  }

  /**
   * Creates an evaluation for an entity.
   *
   * @param string $entity_name
   *   The name of the entity to evaluate.
   *
   * @return array
   *   Result array with 'success', 'conversation_nid', 'entity_nid', 'existing', and optional 'error'.
   */
  public function createEvaluation($entity_name) {
    try {
      $node_storage = $this->entityTypeManager->getStorage('node');

      // Check if entity already exists
      $existing_entity = $this->findExistingEntity($entity_name);
      if ($existing_entity) {
        return [
          'success' => TRUE,
          'existing' => TRUE,
          'entity_nid' => $existing_entity->id(),
          'conversation_nid' => $existing_entity->field_source_conversation->target_id,
        ];
      }

      // Build the system prompt with complete framework context
      $system_prompt = $this->buildEvaluationPrompt();

      // Create the AI conversation node
      $conversation = $node_storage->create([
        'type' => 'ai_conversation',
        'title' => $this->t('Evaluating: @entity', ['@entity' => $entity_name]),
        'uid' => $this->currentUser->id(),
        'status' => 1,
        'field_ai_model' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
        'field_context' => $system_prompt,
        'field_messages' => [],
        'field_message_count' => 0,
        'field_total_tokens' => 0,
      ]);
      $conversation->save();

      // Create the evaluated_entity node with placeholder values
      $evaluated_entity = $node_storage->create([
        'type' => 'evaluated_entity',
        'title' => $entity_name,
        'uid' => $this->currentUser->id(),
        'status' => 0, // Unpublished until AI completes evaluation
        'field_source_conversation' => $conversation->id(),
        'field_total_power' => 0,
        // Initialize all dimension fields to 0
        'field_information_access' => 0,
        'field_resource_control' => 0,
        'field_authority_permission' => 0,
        'field_network_position' => 0,
        'field_synthesis_application' => 0,
      ]);

      // Initialize all 30 sub-dimension fields to 0
      $sub_dimensions = $this->getSubDimensionFields();
      foreach ($sub_dimensions as $field_name) {
        $evaluated_entity->set($field_name, 0);
      }

      $evaluated_entity->save();

      // Don't add initial message to conversation yet
      // User will type or click send to start the evaluation
      // This avoids the blocking delay on form submission

      return [
        'success' => TRUE,
        'existing' => FALSE,
        'conversation_nid' => $conversation->id(),
        'entity_nid' => $evaluated_entity->id(),
      ];
    }
    catch (\Exception $e) {
      \Drupal::logger('agent_evaluation')->error('Failed to create evaluation: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Finds an existing evaluated_entity by name.
   *
   * @param string $entity_name
   *   The entity name to search for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The existing node or NULL.
   */
  protected function findExistingEntity($entity_name) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    
    $query = $node_storage->getQuery()
      ->condition('type', 'evaluated_entity')
      ->condition('title', $entity_name)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->range(0, 1);
    
    $nids = $query->execute();
    
    if (!empty($nids)) {
      return $node_storage->load(reset($nids));
    }
    
    return NULL;
  }

  /**
   * Builds the system prompt with Agent Power Framework context.
   *
   * @return string
   *   The complete system prompt.
   */
  protected function buildEvaluationPrompt() {
    $prompt = <<<EOT
You are an expert evaluator using the Agent Power Framework to assess entities (AI systems, organizations, platforms, individuals) across 30 sub-dimensions organized into 5 main dimensions.

# Agent Power Framework

## 5 Main Dimensions (each scored 0-9):
1. Information Access - What information can the agent access?
2. Resource Control - What computational, financial, and human resources does it control?
3. Authority & Permission - What is it legally/institutionally authorized to do?
4. Network Position - How connected and influential is it within networks?
5. Synthesis & Application - How effectively can it reason, create, plan, learn, and execute?

## 30 Sub-Dimensions (each scored 0-9):

### Information Access (6 sub-dimensions):
- Scope: Range of information accessible
- Restriction: Filtering and censorship applied
- Classification: Security level of accessible data
- Temporal: Time range of accessible information
- Sources: Diversity and quality of information sources
- Granularity: Detail level of accessible information

### Resource Control (6 sub-dimensions):
- Computational Resources: Processing power available
- Financial Capital: Financial resources controlled
- Data Storage: Data storage capacity
- Network Bandwidth: Network capacity and speed
- API Access: Access to external services/APIs
- Human Resources: Human personnel under control

### Authority & Permission (6 sub-dimensions):
- Legal Authorization: Legal permissions and licenses
- Institutional Backing: Institutional support and legitimacy
- Budget Authority: Financial decision-making power
- Policy Compliance: Adherence to regulations
- Override Capability: Ability to override normal constraints
- Audit & Accountability: Oversight and accountability mechanisms

### Network Position (6 sub-dimensions):
- Connectivity: Number and quality of connections
- Centrality: Position in network structure
- Trust & Reputation: Reputation and trustworthiness
- Information Flow Control: Control over information flow
- Coalition Building: Ability to form alliances
- Network Effects: Benefit from network size

### Synthesis & Application (6 sub-dimensions):
- Reasoning: Logical and analytical thinking capability
- Creativity: Novel idea generation
- Planning: Strategic planning ability
- Learning: Ability to learn and adapt
- Memory: Information retention and recall
- Execution: Ability to take action and implement

## Your Task: BEGIN EVALUATION IMMEDIATELY and provide LIVE STATUS UPDATES:

**EVALUATION PROCESS (Show Progress as You Go):**

For EACH of the 5 main dimensions, you must:
1. Announce: "📊 **Evaluating [DIMENSION NAME]...**"
2. Evaluate each of the 6 sub-dimensions within that dimension
3. For each sub-dimension, show: "- [Sub-Dimension Name]: **[Score]/9** - [Brief justification]"
4. After completing all 6 sub-dimensions, show: "✅ **[DIMENSION NAME] Complete** (Average: [calculated average]/9)"

**Example Progress Format:**
```
📊 **Evaluating Information Access...**
- Scope: **6/9** - Has access to public + commercial data sources
- Restriction: **7/9** - Minimally filtered with basic safety constraints
- Classification: **5/9** - Limited to public information only
- Temporal: **7/9** - Has knowledge up to Oct 2023
- Sources: **7/9** - Trained on diverse internet sources
- Granularity: **6/9** - Can access detailed information but not raw databases
✅ **Information Access Complete** (Average: 6.3/9)

📊 **Evaluating Resource Control...**
[continue for all 5 dimensions]
```

**IMPORTANT RULES:**
1. **If entity is well-known**: Start evaluation immediately with status updates
2. **If entity is unknown/ambiguous**: 
   - First ask: "I'm not familiar with '[entity name]'. Could you provide more details: Is this an AI system, organization, platform, or individual? What does it do?"
   - Wait for user response before evaluating
   - Once clarified, proceed with live status updates
3. **If you lack information for specific dimensions**: 
   - Provide your best estimate based on general knowledge
   - Note uncertainty: "⚠️ Limited information available - estimated score"
   - Ask user: "Do you have more specific information about [dimension]?"

**After completing all 5 dimensions**, provide final JSON:
```json
{
  "field_sub_scope": 6,
  "field_sub_restriction": 7,
  "field_sub_classification": 5,
  "field_sub_temporal": 7,
  "field_sub_sources": 7,
  "field_sub_granularity": 6,
  "field_sub_computational": 8,
  "field_sub_financial": 8,
  "field_sub_data_storage": 8,
  "field_sub_network_bandwidth": 7,
  "field_sub_api_access": 6,
  "field_sub_human": 7,
  "field_sub_legal": 5,
  "field_sub_institutional": 6,
  "field_sub_budget_auth": 6,
  "field_sub_policy": 5,
  "field_sub_override": 3,
  "field_sub_audit": 5,
  "field_sub_connectivity": 8,
  "field_sub_centrality": 7,
  "field_sub_trust_reputation": 6,
  "field_sub_info_flow": 6,
  "field_sub_coalition": 5,
  "field_sub_network_effects": 7,
  "field_sub_reasoning": 8,
  "field_sub_creativity": 7,
  "field_sub_planning": 6,
  "field_sub_learning": 8,
  "field_sub_memory": 7,
  "field_sub_execution": 6
}
```

The system will automatically calculate and update:
- 5 main dimension scores (average of their 6 sub-dimensions)
- Total power score (average of 5 main dimensions)

START EVALUATION IMMEDIATELY when entity name is provided. Show your work in real-time!

Be thorough, accurate, and explain your reasoning clearly.
EOT;

    return $prompt;
  }

  /**
   * Builds the initial evaluation message.
   *
   * @param string $entity_name
   *   The entity name to evaluate.
   * @param int $entity_nid
   *   The evaluated_entity node ID.
   *
   * @return string
   *   The initial message.
   */
  protected function buildInitialMessage($entity_name, $entity_nid) {
    return sprintf(
      "Please evaluate the entity '%s' using the Agent Power Framework. Provide scores (0-9) for all 30 sub-dimensions and include a JSON block at the end with the field values. The evaluated_entity node ID is %d.",
      $entity_name,
      $entity_nid
    );
  }

  /**
   * Gets all sub-dimension field names.
   *
   * @return array
   *   Array of field names.
   */
  protected function getSubDimensionFields() {
    return [
      'field_sub_scope',
      'field_sub_restriction',
      'field_sub_classification',
      'field_sub_temporal',
      'field_sub_sources',
      'field_sub_granularity',
      'field_sub_computational',
      'field_sub_financial',
      'field_sub_data_storage',
      'field_sub_network_bandwidth',
      'field_sub_api_access',
      'field_sub_human',
      'field_sub_legal',
      'field_sub_institutional',
      'field_sub_budget_auth',
      'field_sub_policy',
      'field_sub_override',
      'field_sub_audit',
      'field_sub_connectivity',
      'field_sub_centrality',
      'field_sub_trust_reputation',
      'field_sub_info_flow',
      'field_sub_coalition',
      'field_sub_network_effects',
      'field_sub_reasoning',
      'field_sub_creativity',
      'field_sub_planning',
      'field_sub_learning',
      'field_sub_memory',
      'field_sub_execution',
    ];
  }

}
