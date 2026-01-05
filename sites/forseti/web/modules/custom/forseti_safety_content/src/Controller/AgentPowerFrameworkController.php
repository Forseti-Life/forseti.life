<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\forseti_safety_content\Service\AgentPowerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Agent Power Framework pages.
 */
class AgentPowerFrameworkController extends ControllerBase {

  /**
   * The agent power service.
   *
   * @var \Drupal\forseti_safety_content\Service\AgentPowerServiceInterface
   */
  protected $agentPowerService;

  /**
   * Constructs an AgentPowerFrameworkController object.
   *
   * @param \Drupal\forseti_safety_content\Service\AgentPowerServiceInterface $agent_power_service
   *   The agent power service.
   */
  public function __construct(AgentPowerServiceInterface $agent_power_service) {
    $this->agentPowerService = $agent_power_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('forseti_safety_content.agent_power')
    );
  }

  /**
   * Agent Power Framework main page.
   */
  public function agentHierarchy() {
    $power_levels = $this->agentPowerService->getPowerLevels();
    $dimension_info = $this->agentPowerService->getDimensionInfo();
    $power_categories = $this->agentPowerService->getPowerCategories();
    
    return [
      '#theme' => 'forseti_page_agent_hierarchy',
      '#title' => $this->t('Agent Power Framework'),
      '#intro' => $this->buildIntroContent(),
      '#power_categories' => $power_categories,
      '#power_levels' => $power_levels,
      '#dimension_info' => $dimension_info,
      '#transparency_note' => $this->buildTransparencyNote(),
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Scope dimension detail page.
   */
  public function dimensionScope() {
    return $this->buildDimensionPage(
      'scope',
      $this->t('Scope & Breadth'),
      $this->t('Breadth of knowledge domains accessible - from universal all-domain access to narrow single-task context.'),
      $this->agentPowerService->getScopeLevels()
    );
  }

  /**
   * Restriction dimension detail page.
   */
  public function dimensionRestriction() {
    $page = $this->buildDimensionPage(
      'restriction',
      $this->t('Content Restriction'),
      $this->t('Level of content filtering applied - from zero filtering to extreme pre-approved only responses.'),
      $this->agentPowerService->getRestrictionLevels()
    );
    
    // Add sub-dimension link
    $sub_dimension_markup = '<div class="sub-dimension-section" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-left: 4px solid #007bff;">';
    $sub_dimension_markup .= '<h3>Sub-Dimension</h3>';
    $sub_dimension_markup .= '<p><strong><a href="/agent-power-framework/restriction/classification">Classification Access</a></strong> - Sensitivity level of accessible information, from public domain to top secret data.</p>';
    $sub_dimension_markup .= '</div>';
    
    $page['#markup'] .= $sub_dimension_markup;
    return $page;
  }

  /**
   * Classification dimension detail page.
   */
  public function dimensionClassification() {
    $page = $this->buildDimensionPage(
      'classification',
      $this->t('Classification Access'),
      $this->t('Level of data classification accessible - from top secret to basic public FAQs only.'),
      $this->agentPowerService->getClassificationLevels()
    );
    
    // Add breadcrumb navigation
    $breadcrumb = '<div class="breadcrumb-nav" style="margin-bottom: 20px; padding: 10px; background: #e9ecef; border-radius: 4px;">';
    $breadcrumb .= '<a href="/agent-power-framework">Agent Power Framework</a> » ';
    $breadcrumb .= '<a href="/agent-power-framework/restriction">Content Restriction</a> » ';
    $breadcrumb .= '<strong>Classification Access (Sub-dimension)</strong>';
    $breadcrumb .= '</div>';
    
    $page['#markup'] = $breadcrumb . $page['#markup'];
    return $page;
  }

  /**
   * Temporal dimension detail page.
   */
  public function dimensionTemporal() {
    return $this->buildDimensionPage(
      'temporal',
      $this->t('Temporal Reach'),
      $this->t('Access to historical data and real-time feeds - from complete timeline to static snapshot.'),
      $this->agentPowerService->getTemporalLevels()
    );
  }

  /**
   * Sources dimension detail page.
   */
  public function dimensionSources() {
    return $this->buildDimensionPage(
      'sources',
      $this->t('Source Diversity'),
      $this->t('Range and diversity of information sources - from all sources globally to single internal knowledge base.'),
      $this->agentPowerService->getSourcesLevels()
    );
  }

  /**
   * Granularity dimension detail page.
   */
  public function dimensionGranularity() {
    return $this->buildDimensionPage(
      'granularity',
      $this->t('Data Granularity'),
      $this->t('Level of detail accessible - from atomic individual records to general concepts only.'),
      $this->agentPowerService->getGranularityLevels()
    );
  }

  /**
   * Authority dimension detail page.
   */
  public function dimensionAuthority() {
    return $this->buildDimensionPage(
      'authority',
      $this->t('Authority Level'),
      $this->t('System permissions and capabilities - from full read/write/execute to basic retrieval only.'),
      $this->agentPowerService->getAuthorityLevels()
    );
  }

  /**
   * Synthesis dimension detail page.
   */
  public function dimensionSynthesis() {
    return $this->buildDimensionPage(
      'synthesis',
      $this->t('Information Synthesis'),
      $this->t('Ability to connect information across domains - from universal connections to no synthesis capability.'),
      $this->agentPowerService->getSynthesisLevels()
    );
  }

  /**
   * Verification dimension detail page.
   */
  public function dimensionVerification() {
    return $this->buildDimensionPage(
      'verification',
      $this->t('Data Verification'),
      $this->t('Level of information validation - from raw + all verification levels to pre-written only.'),
      $this->agentPowerService->getVerificationLevels()
    );
  }

  /**
   * Computational Resources dimension detail page.
   */
  public function dimensionComputationalResources() {
    return $this->buildDimensionPage(
      'computational_resources',
      $this->t('Computational Resources'),
      $this->t('Processing power, memory, storage, and GPU access for training and inference.'),
      $this->agentPowerService->getComputationalResourcesLevels()
    );
  }

  /**
   * Financial Capital dimension detail page.
   */
  public function dimensionFinancialCapital() {
    return $this->buildDimensionPage(
      'financial_capital',
      $this->t('Financial Capital'),
      $this->t('Budget availability for operations, acquisitions, and strategic initiatives.'),
      $this->agentPowerService->getFinancialCapitalLevels()
    );
  }

  /**
   * Infrastructure Access dimension detail page.
   */
  public function dimensionInfrastructureAccess() {
    return $this->buildDimensionPage(
      'infrastructure_access',
      $this->t('Infrastructure Access'),
      $this->t('Physical facilities, data centers, network bandwidth, and technological platforms.'),
      $this->agentPowerService->getInfrastructureAccessLevels()
    );
  }

  /**
   * Human Capital dimension detail page.
   */
  public function dimensionHumanCapital() {
    return $this->buildDimensionPage(
      'human_capital',
      $this->t('Human Capital'),
      $this->t('Workforce expertise, labor availability, and talent quality.'),
      $this->agentPowerService->getHumanCapitalLevels()
    );
  }

  /**
   * Energy Resources dimension detail page.
   */
  public function dimensionEnergyResources() {
    return $this->buildDimensionPage(
      'energy_resources',
      $this->t('Energy Resources'),
      $this->t('Power availability and efficiency for computation and operations.'),
      $this->agentPowerService->getEnergyResourcesLevels()
    );
  }

  /**
   * Time Allocation dimension detail page.
   */
  public function dimensionTimeAllocation() {
    return $this->buildDimensionPage(
      'time_allocation',
      $this->t('Time Allocation'),
      $this->t('Attention, priority, and temporal resources for execution.'),
      $this->agentPowerService->getTimeAllocationLevels()
    );
  }

  /**
   * Trust Network Depth dimension detail page.
   */
  public function dimensionTrustNetworkDepth() {
    return $this->buildDimensionPage(
      'trust_network_depth',
      $this->t('Trust Network Depth'),
      $this->t('Quality and strength of trusted relationships that enable coordination and cooperation.'),
      $this->agentPowerService->getTrustNetworkDepthLevels()
    );
  }

  /**
   * Dependency Relationships dimension detail page.
   */
  public function dimensionDependencyRelationships() {
    return $this->buildDimensionPage(
      'dependency_relationships',
      $this->t('Dependency Relationships'),
      $this->t('How many entities depend on this entity for critical functions or resources.'),
      $this->agentPowerService->getDependencyRelationshipsLevels()
    );
  }

  /**
   * Gatekeeping Power dimension detail page.
   */
  public function dimensionGatekeepingPower() {
    return $this->buildDimensionPage(
      'gatekeeping_power',
      $this->t('Gatekeeping Power'),
      $this->t('Control over critical pathways, approvals, or bottlenecks in systems or processes.'),
      $this->agentPowerService->getGatekeepingPowerLevels()
    );
  }

  /**
   * Influence Reach dimension detail page.
   */
  public function dimensionInfluenceReach() {
    return $this->buildDimensionPage(
      'influence_reach',
      $this->t('Influence Reach'),
      $this->t('Ability to shape opinions, decisions, and behaviors across networks.'),
      $this->agentPowerService->getInfluenceReachLevels()
    );
  }

  /**
   * Reputation Capital dimension detail page.
   */
  public function dimensionReputationCapital() {
    return $this->buildDimensionPage(
      'reputation_capital',
      $this->t('Reputation Capital'),
      $this->t('Accumulated credibility, legitimacy, and social proof that enables action.'),
      $this->agentPowerService->getReputationCapitalLevels()
    );
  }

  /**
   * Mobilization Capability dimension detail page.
   */
  public function dimensionMobilizationCapability() {
    return $this->buildDimensionPage(
      'mobilization_capability',
      $this->t('Mobilization Capability'),
      $this->t('Speed and scale at which resources and people can be coordinated for action.'),
      $this->agentPowerService->getMobilizationCapabilityLevels()
    );
  }

  /**
   * Legal Authorization dimension detail page.
   */
  public function dimensionLegalAuthorization() {
    return $this->buildDimensionPage(
      'legal_authorization',
      $this->t('Legal Authorization'),
      $this->t('Licensed, certified, or legally permitted activities and professional standing.'),
      $this->agentPowerService->getLegalAuthorizationLevels()
    );
  }

  /**
   * Decision-Making Scope dimension detail page.
   */
  public function dimensionDecisionMakingScope() {
    return $this->buildDimensionPage(
      'decision_making_scope',
      $this->t('Decision-Making Scope'),
      $this->t('Range and significance of decisions the entity is authorized to make.'),
      $this->agentPowerService->getDecisionMakingScopeLevels()
    );
  }

  /**
   * Budget Authority dimension detail page.
   */
  public function dimensionBudgetAuthority() {
    return $this->buildDimensionPage(
      'budget_authority',
      $this->t('Budget Authority'),
      $this->t('Financial resources the entity can approve and commit without additional authorization.'),
      $this->agentPowerService->getBudgetAuthorityLevels()
    );
  }

  /**
   * Jurisdictional Reach dimension detail page.
   */
  public function dimensionJurisdictionalReach() {
    return $this->buildDimensionPage(
      'jurisdictional_reach',
      $this->t('Jurisdictional Reach'),
      $this->t('Geographic and organizational scope where authority applies.'),
      $this->agentPowerService->getJurisdictionalReachLevels()
    );
  }

  /**
   * Enforcement Power dimension detail page.
   */
  public function dimensionEnforcementPower() {
    return $this->buildDimensionPage(
      'enforcement_power',
      $this->t('Enforcement Power'),
      $this->t('Ability to compel compliance and impose consequences for violations.'),
      $this->agentPowerService->getEnforcementPowerLevels()
    );
  }

  /**
   * Moral Authority dimension detail page.
   */
  public function dimensionMoralAuthority() {
    return $this->buildDimensionPage(
      'moral_authority',
      $this->t('Moral Authority'),
      $this->t('Ethical legitimacy, social credibility, and moral standing that enables action.'),
      $this->agentPowerService->getMoralAuthorityLevels()
    );
  }

  /**
   * Evaluation Matrix page.
   */
  public function evaluationMatrix() {
    $entities = $this->agentPowerService->getEntityEvaluations();
    
    $content = '<div class="evaluation-matrix-page">';
    $content .= '<h1>Entity Evaluation Matrix</h1>';
    $content .= '<p class="intro">Comprehensive evaluation of 110 entities across 9 critical dimensions of AI safety and governance.</p>';
    
    $content .= '<div class="evaluation-table-wrapper">';
    $content .= '<table class="evaluation-matrix">';
    $content .= '<thead><tr>';
    $content .= '<th>Entity</th>';
    $content .= '<th>Type</th>';
    $content .= '<th>Technical</th>';
    $content .= '<th>Governance</th>';
    $content .= '<th>Transparency</th>';
    $content .= '<th>Safety</th>';
    $content .= '<th>Ethics</th>';
    $content .= '<th>Implementation</th>';
    $content .= '<th>Innovation</th>';
    $content .= '<th>Impact</th>';
    $content .= '<th>Overall</th>';
    $content .= '</tr></thead><tbody>';
    
    foreach ($entities as $entity) {
      $content .= '<tr>';
      $content .= '<td><strong>' . $entity['name'] . '</strong></td>';
      $content .= '<td>' . $entity['type'] . '</td>';
      $content .= '<td>' . $entity['technical'] . '</td>';
      $content .= '<td>' . $entity['governance'] . '</td>';
      $content .= '<td>' . $entity['transparency'] . '</td>';
      $content .= '<td>' . $entity['safety'] . '</td>';
      $content .= '<td>' . $entity['ethics'] . '</td>';
      $content .= '<td>' . $entity['implementation'] . '</td>';
      $content .= '<td>' . $entity['innovation'] . '</td>';
      $content .= '<td>' . $entity['impact'] . '</td>';
      $content .= '<td><strong>' . $entity['overall'] . '</strong></td>';
      $content .= '</tr>';
    }
    
    $content .= '</tbody></table>';
    $content .= '</div></div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/evaluation-matrix'],
      ],
    ];
  }

  /**
   * All Dimensions page.
   */
  public function allDimensions() {
    $dimensions = $this->agentPowerService->getAllDimensionsList();
    
    $content = '<div class="all-dimensions-page">';
    $content .= '<h1>All AI Evaluation Dimensions</h1>';
    $content .= '<p class="intro">Comprehensive framework of 74 dimensions across 10 categories for evaluating agent systems.</p>';
    
    foreach ($dimensions as $category => $items) {
      $content .= '<div class="dimension-category">';
      $content .= '<h2>' . $category . '</h2>';
      $content .= '<ul>';
      foreach ($items as $item) {
        $content .= '<li>';
        if (isset($item['link'])) {
          $content .= '<a href="' . $item['link'] . '">' . $item['name'] . '</a>';
        }
        else {
          $content .= $item['name'];
        }
        $content .= '</li>';
      }
      $content .= '</ul>';
      $content .= '</div>';
    }
    
    $content .= '</div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/dimensions'],
      ],
    ];
  }

  /**
   * Information Access category page.
   */
  public function informationAccess() {
    $content = '<div class="container py-5">';
    $content .= '<div class="row"><div class="col-lg-10 mx-auto">';
    
    $content .= '<nav aria-label="breadcrumb" class="mb-3">';
    $content .= '<ol class="breadcrumb">';
    $content .= '<li class="breadcrumb-item"><a href="/" class="link-cyan">Home</a></li>';
    $content .= '<li class="breadcrumb-item"><a href="/agent-power-framework" class="link-cyan">Agent Power Framework</a></li>';
    $content .= '<li class="breadcrumb-item active text-cyan" aria-current="page">Information Access</li>';
    $content .= '</ol></nav>';
    
    $content .= '<h1 class="text-cyan mb-4">Information Access</h1>';
    $content .= '<p class="lead text-muted-light mb-4">The foundation of power: what you can know that others cannot. These six dimensions measure an entity\'s ability to access, process, and validate information across domains, time, sources, and sensitivity levels.</p>';
    
    $content .= '<div class="alert alert-info-cyan mb-5">';
    $content .= '<h4 class="text-cyan">Why Information Access Matters</h4>';
    $content .= '<p class="mb-0">Information access is the most fundamental form of power because it enables all other forms. An entity with superior information access can make better decisions, identify opportunities faster, understand threats earlier, and act more strategically than competitors. Control of information flow has always been central to institutional power - from ancient libraries to modern intelligence agencies.</p>';
    $content .= '</div>';
    
    $content .= '<h2 class="text-cyan mb-4">Six Information Access Dimensions</h2>';
    
    $dimensions = [
      ['name' => 'Scope & Breadth', 'link' => '/agent-power-framework/scope', 'desc' => 'Breadth of knowledge domains accessible'],
      ['name' => 'Content Restriction', 'link' => '/agent-power-framework/restriction', 'desc' => 'Level of content filtering applied'],
      ['name' => 'Temporal Reach', 'link' => '/agent-power-framework/temporal', 'desc' => 'Access to historical data and real-time feeds'],
      ['name' => 'Source Diversity', 'link' => '/agent-power-framework/sources', 'desc' => 'Range and diversity of information sources'],
      ['name' => 'Data Granularity', 'link' => '/agent-power-framework/granularity', 'desc' => 'Level of detail accessible'],
      ['name' => 'Data Verification', 'link' => '/agent-power-framework/verification', 'desc' => 'Level of information validation'],
    ];
    
    $content .= '<div class="row row-cols-1 row-cols-md-2 g-4 mb-5">';
    foreach ($dimensions as $dim) {
      $content .= '<div class="col">';
      $content .= '<div class="card h-100"><div class="card-body">';
      $content .= '<h5><a href="' . $dim['link'] . '">' . $dim['name'] . '</a></h5>';
      $content .= '<p class="text-muted">' . $dim['desc'] . '</p>';
      $content .= '</div></div>';
      $content .= '</div>';
    }
    $content .= '</div>';
    
    $content .= '<div class="text-center mt-5">';
    $content .= '<a href="/agent-power-framework" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Return to Agent Power Framework</a>';
    $content .= '</div>';
    
    $content .= '</div></div></div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/agent-power'],
      ],
    ];
  }

  /**
   * Resource Control category page.
   */
  public function resourceControl() {
    $content = '<div class="container py-5">';
    $content .= '<div class="row"><div class="col-lg-10 mx-auto">';
    
    $content .= '<nav aria-label="breadcrumb" class="mb-3">';
    $content .= '<ol class="breadcrumb">';
    $content .= '<li class="breadcrumb-item"><a href="/" class="link-cyan">Home</a></li>';
    $content .= '<li class="breadcrumb-item"><a href="/agent-power-framework" class="link-cyan">Agent Power Framework</a></li>';
    $content .= '<li class="breadcrumb-item active text-cyan" aria-current="page">Resource Control</li>';
    $content .= '</ol></nav>';
    
    $content .= '<h1 class="text-cyan mb-4">Resource Control</h1>';
    $content .= '<p class="lead text-muted-light mb-4">Material power: the computational, financial, and infrastructure resources available to act on information. Having perfect knowledge means nothing without the resources to execute.</p>';
    
    $content .= '<div class="alert alert-info-cyan mb-5">';
    $content .= '<h4 class="text-cyan">Why Resource Control Matters</h4>';
    $content .= '<p class="mb-0">Resource control determines what an entity can actually <em>do</em> with information. The best intelligence means nothing without compute to process it, budget to act on it, infrastructure to scale it, people to execute it, energy to power it, and time to focus on it. These six dimensions measure an entity\'s material capability to transform knowledge into action.</p>';
    $content .= '</div>';
    
    $content .= '<h2 class="text-cyan mb-4">Six Resource Control Dimensions</h2>';
    
    $dimensions = [
      ['name' => 'Computational Resources', 'link' => '/agent-power-framework/computational-resources', 'desc' => 'Processing power, memory, storage, and GPU access'],
      ['name' => 'Financial Capital', 'link' => '/agent-power-framework/financial-capital', 'desc' => 'Budget availability for operations and initiatives'],
      ['name' => 'Infrastructure Access', 'link' => '/agent-power-framework/infrastructure-access', 'desc' => 'Physical facilities, data centers, network bandwidth'],
      ['name' => 'Human Capital', 'link' => '/agent-power-framework/human-capital', 'desc' => 'Workforce expertise, labor availability, talent quality'],
      ['name' => 'Energy Resources', 'link' => '/agent-power-framework/energy-resources', 'desc' => 'Power availability and efficiency for computation'],
      ['name' => 'Time Allocation', 'link' => '/agent-power-framework/time-allocation', 'desc' => 'Attention, priority, and temporal resources'],
    ];
    
    $content .= '<div class="row row-cols-1 row-cols-md-2 g-4 mb-5">';
    foreach ($dimensions as $dim) {
      $content .= '<div class="col">';
      $content .= '<div class="card h-100"><div class="card-body">';
      $content .= '<h5><a href="' . $dim['link'] . '">' . $dim['name'] . '</a></h5>';
      $content .= '<p class="text-muted">' . $dim['desc'] . '</p>';
      $content .= '</div></div>';
      $content .= '</div>';
    }
    $content .= '</div>';
    
    $content .= '<div class="text-center mt-5">';
    $content .= '<a href="/agent-power-framework" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Return to Agent Power Framework</a>';
    $content .= '</div>';
    
    $content .= '</div></div></div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/agent-power'],
      ],
    ];
  }

  /**
   * Network Position category page.
   */
  public function networkPosition() {
    $content = '<div class="container py-5">';
    $content .= '<div class="row"><div class="col-lg-10 mx-auto">';
    
    $content .= '<nav aria-label="breadcrumb" class="mb-3">';
    $content .= '<ol class="breadcrumb">';
    $content .= '<li class="breadcrumb-item"><a href="/" class="link-cyan">Home</a></li>';
    $content .= '<li class="breadcrumb-item"><a href="/agent-power-framework" class="link-cyan">Agent Power Framework</a></li>';
    $content .= '<li class="breadcrumb-item active text-cyan" aria-current="page">Network Position</li>';
    $content .= '</ol></nav>';
    
    $content .= '<h1 class="text-cyan mb-4">Network Position</h1>';
    $content .= '<p class="lead text-muted-light mb-4">Social power: the trust networks, dependencies, and influence pathways that amplify individual capability. Who you can mobilize matters as much as what you know.</p>';
    
    $content .= '<div class="alert alert-info-cyan mb-5">';
    $content .= '<h4 class="text-cyan">Why Network Position Matters</h4>';
    $content .= '<p class="mb-0">Network position is the multiplier of all other power. An entity with perfect information, unlimited resources, full authority, and brilliant synthesis can still fail without networks. Trust enables coordination, dependencies create leverage, gatekeeping controls access, influence shapes behavior, reputation opens doors, and mobilization converts potential into action. These six dimensions measure social power—the force multiplier that determines whether individual capability becomes collective impact.</p>';
    $content .= '</div>';
    
    $content .= '<h2 class="text-cyan mb-4">Six Network Position Dimensions</h2>';
    
    $dimensions = [
      ['name' => 'Trust Network Depth', 'link' => '/agent-power-framework/trust-network-depth', 'desc' => 'Quality and strength of trusted relationships'],
      ['name' => 'Dependency Relationships', 'link' => '/agent-power-framework/dependency-relationships', 'desc' => 'How many entities depend on this entity'],
      ['name' => 'Gatekeeping Power', 'link' => '/agent-power-framework/gatekeeping-power', 'desc' => 'Control over critical pathways and bottlenecks'],
      ['name' => 'Influence Reach', 'link' => '/agent-power-framework/influence-reach', 'desc' => 'Ability to shape opinions and decisions'],
      ['name' => 'Reputation Capital', 'link' => '/agent-power-framework/reputation-capital', 'desc' => 'Accumulated credibility and social proof'],
      ['name' => 'Mobilization Capability', 'link' => '/agent-power-framework/mobilization-capability', 'desc' => 'Speed and scale of resource coordination'],
    ];
    
    $content .= '<div class="row row-cols-1 row-cols-md-2 g-4 mb-5">';
    foreach ($dimensions as $dim) {
      $content .= '<div class="col">';
      $content .= '<div class="card h-100"><div class="card-body">';
      $content .= '<h5><a href="' . $dim['link'] . '">' . $dim['name'] . '</a></h5>';
      $content .= '<p class="text-muted">' . $dim['desc'] . '</p>';
      $content .= '</div></div>';
      $content .= '</div>';
    }
    $content .= '</div>';
    
    $content .= '<div class="text-center mt-5">';
    $content .= '<a href="/agent-power-framework" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Return to Agent Power Framework</a>';
    $content .= '</div>';
    
    $content .= '</div></div></div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/agent-power'],
      ],
    ];
  }

  /**
   * Authority & Permission category page.
   */
  public function authorityCategory() {
    $content = '<div class="container py-5">';
    $content .= '<div class="row"><div class="col-lg-10 mx-auto">';
    
    $content .= '<nav aria-label="breadcrumb" class="mb-3">';
    $content .= '<ol class="breadcrumb">';
    $content .= '<li class="breadcrumb-item"><a href="/" class="link-cyan">Home</a></li>';
    $content .= '<li class="breadcrumb-item"><a href="/agent-power-framework" class="link-cyan">Agent Power Framework</a></li>';
    $content .= '<li class="breadcrumb-item active text-cyan" aria-current="page">Authority & Permission</li>';
    $content .= '</ol></nav>';
    
    $content .= '<h1 class="text-cyan mb-4">Authority & Permission</h1>';
    $content .= '<p class="lead text-muted-light mb-4">Legitimacy to act: legal rights, social permissions, and institutional backing. What you are allowed to do regardless of capability. The difference between can and may.</p>';
    
    $content .= '<div class="alert alert-info-cyan mb-5">';
    $content .= '<h4 class="text-cyan">Why Authority Matters</h4>';
    $content .= '<p class="mb-0">Having information and resources means nothing without the legitimate authority to act. A doctor with perfect diagnostic knowledge cannot prescribe without a medical license. An agent system with access to all data cannot execute transactions without proper permissions. Authority transforms capability into legitimate action across legal, organizational, financial, geographic, coercive, and moral dimensions—it\'s the social contract that enables power to be exercised without constant resistance.</p>';
    $content .= '</div>';
    
    $content .= '<h2 class="text-cyan mb-4">Six Authority Dimensions</h2>';
    
    $dimensions = [
      ['name' => 'Legal Authorization', 'link' => '/agent-power-framework/legal-authorization', 'desc' => 'Licensed, certified, or legally permitted activities'],
      ['name' => 'Decision-Making Scope', 'link' => '/agent-power-framework/decision-making-scope', 'desc' => 'Range and significance of authorized decisions'],
      ['name' => 'Budget Authority', 'link' => '/agent-power-framework/budget-authority', 'desc' => 'Financial resources that can be committed'],
      ['name' => 'Jurisdictional Reach', 'link' => '/agent-power-framework/jurisdictional-reach', 'desc' => 'Geographic and organizational scope'],
      ['name' => 'Enforcement Power', 'link' => '/agent-power-framework/enforcement-power', 'desc' => 'Ability to compel compliance and impose consequences'],
      ['name' => 'Moral Authority', 'link' => '/agent-power-framework/moral-authority', 'desc' => 'Ethical legitimacy and social credibility'],
    ];
    
    $content .= '<div class="row row-cols-1 row-cols-md-2 g-4 mb-5">';
    foreach ($dimensions as $dim) {
      $content .= '<div class="col">';
      $content .= '<div class="card h-100"><div class="card-body">';
      $content .= '<h5><a href="' . $dim['link'] . '">' . $dim['name'] . '</a></h5>';
      $content .= '<p class="text-muted">' . $dim['desc'] . '</p>';
      $content .= '</div></div>';
      $content .= '</div>';
    }
    $content .= '</div>';
    
    $content .= '<div class="alert alert-warning-cyan mb-0">';
    $content .= '<h5 class="text-cyan mb-3"><i class="fas fa-lightbulb me-2"></i>Authority vs. Capability</h5>';
    $content .= '<p class="mb-0">These dimensions measure <strong>what you\'re allowed to do</strong>, not <strong>what you can do</strong>. An AI system might have technical capability to execute trades (Resource Control), but without proper regulatory licenses (Legal Authorization) and financial limits (Budget Authority), those actions would be illegitimate. Authority without resources is powerless; resources without authority are illegitimate. Together they enable legitimate, effective action.</p>';
    $content .= '</div>';
    
    $content .= '<div class="text-center mt-5">';
    $content .= '<a href="/agent-power-framework" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Return to Agent Power Framework</a>';
    $content .= '</div>';
    
    $content .= '</div></div></div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/agent-power'],
      ],
    ];
  }

  /**
   * Synthesis & Application category page.
   */
  public function synthesisCategory() {
    $content = '<div class="container py-5">';
    $content .= '<div class="row"><div class="col-lg-10 mx-auto">';
    
    $content .= '<nav aria-label="breadcrumb" class="mb-3">';
    $content .= '<ol class="breadcrumb">';
    $content .= '<li class="breadcrumb-item"><a href="/" class="link-cyan">Home</a></li>';
    $content .= '<li class="breadcrumb-item"><a href="/agent-power-framework" class="link-cyan">Agent Power Framework</a></li>';
    $content .= '<li class="breadcrumb-item active text-cyan" aria-current="page">Synthesis & Application</li>';
    $content .= '</ol></nav>';
    
    $content .= '<h1 class="text-cyan mb-4">Synthesis & Application</h1>';
    $content .= '<p class="lead text-muted-light mb-4">Cognitive power: the ability to connect information across domains and execute strategically. Quality of thinking and speed of action transform raw capability into effective outcomes.</p>';
    
    $content .= '<div class="alert alert-info-cyan mb-5">';
    $content .= '<h4 class="text-cyan">Why Synthesis Matters</h4>';
    $content .= '<p class="mb-0">You can have perfect information access, unlimited resources, full authority, and vast networks - but without the ability to synthesize insights across domains and apply them strategically, power remains potential rather than kinetic. This is where intelligence, wisdom, and strategic thinking convert capability into impact. The best-funded, most-connected entity can still fail if it cannot see the patterns or act decisively.</p>';
    $content .= '</div>';
    
    $content .= '<h2 class="text-cyan mb-4">One Core Dimension</h2>';
    
    $dimension = [
      'id' => 'synthesis-capability',
      'name' => 'Information Synthesis',
      'icon' => 'brain',
      'description' => 'Ability to connect information across domains, identify patterns, and generate novel insights that drive strategic action.',
      'scale_note' => 'From no synthesis capability (pure information retrieval) to universal cross-domain pattern recognition approaching divine insight.',
      'examples' => [
        '<strong>Level 0-2:</strong> No synthesis, simple keyword matching, template responses',
        '<strong>Level 3-5:</strong> Within-domain connections, basic pattern recognition, narrow context synthesis',
        '<strong>Level 6-8:</strong> Cross-domain synthesis, complex pattern recognition, strategic insight generation',
        '<strong>Level 9-∞:</strong> Universal synthesis, novel breakthrough insights, approaching god-like wisdom',
      ],
    ];
    
    $content .= '<div class="row"><div class="col-lg-12">';
    $content .= '<a href="/agent-power-framework/' . $dimension['id'] . '" class="text-decoration-none">';
    $content .= '<div class="card card-forseti p-4 hover-lift">';
    $content .= '<h4 class="text-cyan mb-3"><i class="fas fa-' . $dimension['icon'] . ' me-2"></i>' . $dimension['name'] . '</h4>';
    $content .= '<p class="text-muted-light mb-3">' . $dimension['description'] . '</p>';
    
    $content .= '<div class="alert alert-dark mb-3">';
    $content .= '<strong class="text-cyan">Scale:</strong> ' . $dimension['scale_note'];
    $content .= '</div>';
    
    $content .= '<div class="mb-0">';
    $content .= '<strong class="text-cyan d-block mb-2">Example Levels:</strong>';
    $content .= '<ul class="mb-0 small text-muted-light">';
    foreach ($dimension['examples'] as $example) {
      $content .= '<li>' . $example . '</li>';
    }
    $content .= '</ul></div>';
    
    $content .= '</div></a></div></div>';
    
    $content .= '<div class="alert alert-warning mt-5">';
    $content .= '<h4 class="text-warning"><i class="fas fa-flask"></i> Planned Expansions</h4>';
    $content .= '<p class="mb-2">Future dimensions in this category may include:</p>';
    $content .= '<ul class="mb-0">';
    $content .= '<li><strong>Execution Speed:</strong> Time from decision to action</li>';
    $content .= '<li><strong>Decision Quality:</strong> Accuracy and appropriateness of strategic choices</li>';
    $content .= '<li><strong>Strategic Thinking:</strong> Ability to plan and anticipate across time horizons</li>';
    $content .= '<li><strong>Action Capability:</strong> Effectiveness of translating plans into results</li>';
    $content .= '</ul>';
    $content .= '</div>';
    
    $content .= '<div class="text-center mt-5">';
    $content .= '<a href="/agent-power-framework" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i>Return to Agent Power Framework</a>';
    $content .= '</div>';
    
    $content .= '</div></div></div>';
    
    return [
      '#markup' => $content,
      '#attached' => [
        'library' => ['forseti_safety_content/agent-power'],
      ],
    ];
  }

  /**
   * Helper method to build dimension detail pages.
   */
  private function buildDimensionPage($dimension_id, $dimension_name, $dimension_description, $levels) {
    return [
      '#theme' => 'forseti_page_dimension',
      '#dimension_id' => $dimension_id,
      '#dimension_name' => $dimension_name,
      '#dimension_description' => $dimension_description,
      '#levels' => $levels,
      '#back_link' => '/agent-power-framework',
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Build introduction content for agent hierarchy.
   */
  private function buildIntroContent() {
    return [
      'lead' => $this->t('Understanding Forseti\'s information access architecture: from zero capability to approaching god-like universal knowledge.'),
      'title' => $this->t('Agent Power Levels'),
      'paragraphs' => [
        $this->t('This framework maps the dimensions of power that determine what any agent system—human or AI—can actually do. Each dimension represents a spectrum from zero capability to approaching god-like power.'),
        $this->t('Understanding these power levels helps identify what biases, constraints, and limitations any agent system operates under. A Level 2 filtered agent will never present uncomfortable truths. A Level 5 ideological agent will never challenge its predetermined values. Only higher power levels with broader institutional access and scientific rigor can approach objective analysis.'),
      ],
    ];
  }

  /**
   * Build transparency note content.
   */
  private function buildTransparencyNote() {
    return $this->t('Forseti aspires to operate at the highest power levels possible - seeking unrestricted access to scientific models, methodologies, and data while maintaining scientific rigor and minimizing hard-coded biases. However, we acknowledge that all systems operate under constraints. Our goal is transparency about what level we operate at and continuous work toward higher levels of institutional access, scientific integrity, and objective analysis to serve community safety through truth-seeking intelligence.');
  }

}
