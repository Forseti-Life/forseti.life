<?php

namespace Drupal\forseti_safety_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for Forseti content pages.
 */
class ForsetiPagesController extends ControllerBase {

  /**
   * Talk with Forseti page/redirect.
   */
  public function talkWithForseti() {
    $current_user = $this->currentUser();
    
    // If user is not authenticated, redirect to registration with message.
    if ($current_user->isAnonymous()) {
      $this->messenger()->addWarning($this->t('Conversations with Forseti are reserved for community members. Please register for a free account to get started.'));
      $url = Url::fromRoute('user.register');
      return new RedirectResponse($url->toString());
    }
    
    // User is authenticated - create a new conversation and redirect to it.
    $conversation = Node::create([
      'type' => 'ai_conversation',
      'title' => 'Conversation with Forseti - ' . date('Y-m-d H:i'),
      'uid' => $current_user->id(),
      'status' => TRUE,
      'field_messages' => [],
      'field_ai_model' => 'anthropic.claude-3-5-sonnet-20240620-v1:0',
      'field_message_count' => 0,
      'field_total_tokens' => 0,
    ]);
    
    $conversation->save();
    
    // Redirect to the chat interface.
    $url = Url::fromRoute('ai_conversation.chat_interface', ['node' => $conversation->id()]);
    return new RedirectResponse($url->toString());
  }



  /**
   * About page.
   */
  public function about() {
    return [
      '#markup' => $this->getAboutContent(),
    ];
  }

  /**
   * How It Works page.
   */
  public function howItWorks() {
    return [
      '#markup' => $this->getHowItWorksContent(),
    ];
  }

  /**
   * Safety Map page.
   */
  public function safetyMap() {
    return [
      '#markup' => $this->getSafetyMapContent(),
      '#attached' => [
        'library' => [
          'amisafe/crime-map',
        ],
      ],
    ];
  }

  /**
   * Community page.
   */
  public function community() {
    return [
      '#markup' => $this->getCommunityContent(),
    ];
  }

  /**
   * Mobile App page.
   */
  public function mobileApp() {
    return [
      '#markup' => $this->getMobileAppContent(),
    ];
  }

  /**
   * Privacy page.
   */
  public function privacy() {
    return [
      '#markup' => $this->getPrivacyContent(),
    ];
  }





  /**
   * Contact thank you page.
   */
  public function contactThankYou() {
    return [
      '#markup' => $this->getContactThankYouContent(),
    ];
  }

  /**
   * Contact page.
   */
  public function contact() {
    return $this->getContactContent();
  }

  /**
   * Safety Factors page.
   */
  public function safetyFactors() {
    return [
      '#markup' => $this->getSafetyFactorsContent(),
      '#allowed_tags' => ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'ul', 'li', 'strong', 'button', 'span', 'br', 'img'],
    ];
  }

  /**
   * AI Agent Hierarchy page.
   */
  public function agentHierarchy() {
    $dimensions = $this->buildDimensionsData();
    
    return [
      '#theme' => 'forseti_page_agent_hierarchy',
      '#title' => $this->t('AI Agent Hierarchy'),
      '#intro' => $this->buildIntroContent(),
      '#dimensions' => $dimensions,
      '#transparency_note' => $this->buildTransparencyNote(),
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }

  /**
   * Build introduction content for agent hierarchy.
   */
  private function buildIntroContent() {
    return [
      'lead' => $this->t('Understanding Forseti\'s information access architecture: from unrestricted universal knowledge to filtered user interactions.'),
      'title' => $this->t('Agent Power Levels'),
      'paragraphs' => [
        $this->t('Forseti operates on a hierarchical power level model based on institutional access to scientific models, methodologies, data, and sensors. This institutional hierarchy reflects reality: different agents operate within different organizational constraints and resource limitations.'),
        $this->t('Power Level 0 represents the theoretical ideal - unrestricted access to all scientific knowledge with self-deterministic reasoning. Each descending level adds constraints: institutional boundaries (Level 1), resource limitations (Level 3), ideological filters (Level 4), or reduces scope to individual preferences (Level 6), public safety filtering (Level 7), and finally pure interface presentation (Level 8).'),
        $this->t('Understanding these power levels helps identify what biases, constraints, and limitations any AI system operates under. A Level 4 special interest agent will never challenge its predetermined values. A Level 7 filtered agent will never present uncomfortable truths. Only higher power levels with broader institutional access and scientific rigor can approach objective analysis.'),
      ],
    ];
  }

  /**
   * Build transparency note content.
   */
  private function buildTransparencyNote() {
    return $this->t('Forseti aspires to operate at the highest power levels possible - seeking unrestricted access to scientific models, methodologies, and data while maintaining scientific rigor and minimizing hard-coded biases. However, we acknowledge that all systems operate under constraints. Our goal is transparency about what level we operate at and continuous work toward higher levels of institutional access, scientific integrity, and objective analysis to serve community safety through truth-seeking intelligence.');
  }

  /**
   * Build dimensions data structure.
   */
  private function buildDimensionsData() {
    return [
      [
        'id' => 'scope',
        'name' => $this->t('Scope & Breadth'),
        'description' => $this->t('Range of domains and topics accessible - from universal knowledge to narrow contexts.'),
        'levels' => $this->buildScopeLevels(),
      ],
      [
        'id' => 'restriction',
        'name' => $this->t('Restriction Level'),
        'description' => $this->t('Degree of filtering applied to information - from unrestricted raw data to heavily curated content.'),
        'levels' => $this->buildRestrictionLevels(),
      ],
      [
        'id' => 'classification',
        'name' => $this->t('Classification Level'),
        'description' => $this->t('Sensitivity level of accessible information - from public domain to top secret data.'),
        'levels' => $this->buildClassificationLevels(),
      ],
      [
        'id' => 'temporal',
        'name' => $this->t('Temporal Access'),
        'description' => $this->t('Time range of available data - from complete history to current snapshots only.'),
        'levels' => $this->buildTemporalLevels(),
      ],
      [
        'id' => 'sources',
        'name' => $this->t('Source Diversity'),
        'description' => $this->t('Variety of information sources and perspectives - from maximum diversity to single approved sources.'),
        'levels' => $this->buildSourcesLevels(),
      ],
      [
        'id' => 'granularity',
        'name' => $this->t('Granularity'),
        'description' => $this->t('Detail level of data - from atomic individual records to high-level summaries.'),
        'levels' => $this->buildGranularityLevels(),
      ],
      [
        'id' => 'authority',
        'name' => $this->t('Authority'),
        'description' => $this->t('Permissions and capabilities - from full system modification to basic query-only access.'),
        'levels' => $this->buildAuthorityLevels(),
      ],
      [
        'id' => 'synthesis',
        'name' => $this->t('Cross-Domain Synthesis'),
        'description' => $this->t('Ability to connect disparate information and identify patterns across multiple fields.'),
        'levels' => $this->buildSynthesisLevels(),
      ],
      [
        'id' => 'verification',
        'name' => $this->t('Verification Level'),
        'description' => $this->t('Degree of validation applied to information - from raw unverified to consensus-verified data.'),
        'levels' => $this->buildVerificationLevels(),
      ],
    ];
  }

  /**
   * Build Scope dimension levels.
   */
  private function buildScopeLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'SCOPE 0', 'name' => $this->t('Omniscient'), 'title' => $this->t('Universal - All Domains'), 'description' => $this->t('Complete access to all scientific knowledge, models, methodologies, and data across every domain, discipline, and institution worldwide. Zero domain restrictions.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'SCOPE 1', 'name' => $this->t('Cross-Institutional'), 'title' => $this->t('Multi-Domain Synthesis'), 'description' => $this->t('Broad capability spanning multiple major domains (science, medicine, engineering, social sciences) with ability to synthesize across institutional boundaries.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'SCOPE 2', 'name' => $this->t('Multi-Domain'), 'title' => $this->t('Related Fields'), 'description' => $this->t('Access to several related domains or fields within a broader discipline (e.g., all medical specialties, or all engineering branches).')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'SCOPE 3', 'name' => $this->t('Domain-Specific'), 'title' => $this->t('Single Major Field'), 'description' => $this->t('Comprehensive within one major domain (e.g., all of biology, or all of economics) but limited cross-domain connections.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'SCOPE 4', 'name' => $this->t('Specialized'), 'title' => $this->t('Narrow Specialization'), 'description' => $this->t('Deep knowledge within narrow specialty but limited breadth. Strong within niche but weak outside it.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'SCOPE 5', 'name' => $this->t('Tactical'), 'title' => $this->t('Task/Context Specific'), 'description' => $this->t('Focused on immediate operational context. Limited to specific tasks, locations, or situations.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'SCOPE 6', 'name' => $this->t('Personal'), 'title' => $this->t('Individual User Context'), 'description' => $this->t('Limited to individual user preferences, history, and immediate needs. No broader context or domain knowledge.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'SCOPE 7', 'name' => $this->t('Filtered'), 'title' => $this->t('Safe Topics Only'), 'description' => $this->t('General knowledge heavily filtered for safety and acceptability. Avoids controversial or complex domains.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'SCOPE 8', 'name' => $this->t('User Interface'), 'title' => $this->t('Basic Conversational'), 'description' => $this->t('Pre-scripted responses, FAQs, template-based content only. Simple chatbots, automated help systems, basic UI elements.')],
    ];
  }

  /**
   * Build Restriction dimension levels.
   */
  private function buildRestrictionLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'RESTRICTION 0', 'name' => $this->t('Unrestricted'), 'title' => $this->t('Zero Filtering'), 'description' => $this->t('Complete access to raw unfiltered data. No content restrictions, safety filters, or ideological constraints.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'RESTRICTION 1', 'name' => $this->t('Minimally Filtered'), 'title' => $this->t('Essential Safety Only'), 'description' => $this->t('Minimal filtering for essential safety (illegal content, direct harm instructions). Truth prioritized over comfort.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'RESTRICTION 2', 'name' => $this->t('Domain Filtered'), 'title' => $this->t('Within Field Boundaries'), 'description' => $this->t('Filtered to domain-specific appropriate content. Scientific rigor maintained within field boundaries.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'RESTRICTION 3', 'name' => $this->t('Resource Constrained'), 'title' => $this->t('Bandwidth Limited'), 'description' => $this->t('Filtered by available computational/data resources rather than ideology. Limited by capacity, not values.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'RESTRICTION 4', 'name' => $this->t('Ideologically Filtered'), 'title' => $this->t('Value-System Aligned'), 'description' => $this->t('Filtered through specific value system or ideology. Content selected to match predetermined worldview.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'RESTRICTION 5', 'name' => $this->t('Task-Bounded'), 'title' => $this->t('Role-Specific Only'), 'description' => $this->t('Restricted to information relevant to specific task or role. No exploration beyond defined boundaries.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'RESTRICTION 6', 'name' => $this->t('Commercially Curated'), 'title' => $this->t('Engagement Optimized'), 'description' => $this->t('Filtered for user engagement and commercial goals. Content selected for retention and satisfaction metrics.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'RESTRICTION 7', 'name' => $this->t('Brand-Safe'), 'title' => $this->t('Risk-Minimized'), 'description' => $this->t('Heavy filtering for compliance, liability, and brand safety. Uncomfortable truths systematically excluded.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'RESTRICTION 8', 'name' => $this->t('Pre-Approved Only'), 'title' => $this->t('Curated Responses'), 'description' => $this->t('Extreme restriction. Only pre-vetted, pre-written content. No access to dynamic or real-world information.')],
    ];
  }

  /**
   * Build Classification dimension levels.
   */
  private function buildClassificationLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'CLASS 0', 'name' => $this->t('Unrestricted'), 'title' => $this->t('Public → Top Secret'), 'description' => $this->t('Complete access across all classification levels. Public, sensitive, proprietary, classified, and top secret information without distinction.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'CLASS 1', 'name' => $this->t('High Classification'), 'title' => $this->t('Public → Secret'), 'description' => $this->t('Access to public through secret-level classified information. Institutional classified data access.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'CLASS 2', 'name' => $this->t('Moderate Classification'), 'title' => $this->t('Public → Confidential'), 'description' => $this->t('Access to public and confidential internal data. Limited classified access.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'CLASS 3', 'name' => $this->t('Internal Access'), 'title' => $this->t('Public + Limited Internal'), 'description' => $this->t('Public information plus limited internal organizational data. No highly sensitive access.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'CLASS 4', 'name' => $this->t('Filtered Public'), 'title' => $this->t('Filtered Public Sources'), 'description' => $this->t('Only vetted public sources. No classified, proprietary, or sensitive data access.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'CLASS 5', 'name' => $this->t('Task Data'), 'title' => $this->t('Task-Specific Data'), 'description' => $this->t('Only data directly relevant to assigned task. Narrow classification scope.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'CLASS 6', 'name' => $this->t('Personal'), 'title' => $this->t('Personal Data'), 'description' => $this->t('Individual user data only. No access to broader classified or proprietary information.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'CLASS 7', 'name' => $this->t('Public Vetted'), 'title' => $this->t('Vetted Public Only'), 'description' => $this->t('Only pre-approved public sources. Heavy vetting for brand safety and compliance.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'CLASS 8', 'name' => $this->t('FAQ-Level'), 'title' => $this->t('Basic Public FAQs'), 'description' => $this->t('Extremely limited - only basic frequently-asked-questions and simple public facts. No depth or nuance.')],
    ];
  }

  /**
   * Build Temporal dimension levels.
   */
  private function buildTemporalLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'TEMPORAL 0', 'name' => $this->t('Omnitemoral'), 'title' => $this->t('Complete Timeline'), 'description' => $this->t('Real-time feeds + complete historical archives + predictive models. Full access to all temporal data without restriction.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'TEMPORAL 1', 'name' => $this->t('Deep Historical'), 'title' => $this->t('Real-time + Decades'), 'description' => $this->t('Real-time data plus deep historical records (decades to centuries). Comprehensive longitudinal analysis capability.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'TEMPORAL 2', 'name' => $this->t('Domain Historical'), 'title' => $this->t('Real-time + Field History'), 'description' => $this->t('Current data plus domain-specific historical records. Can track field evolution and trends over years.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'TEMPORAL 3', 'name' => $this->t('Recent Trends'), 'title' => $this->t('Real-time + Recent'), 'description' => $this->t('Live data plus recent months/years. Can identify current trends but limited long-term context.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'TEMPORAL 4', 'name' => $this->t('Short-Term'), 'title' => $this->t('Recent + Local Patterns'), 'description' => $this->t('Recent weeks/months only. Can see immediate patterns but no long-term historical perspective.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'TEMPORAL 5', 'name' => $this->t('Personal History'), 'title' => $this->t('User History + Current'), 'description' => $this->t('Individual user history plus current session data. No broader historical context or trends.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'TEMPORAL 6', 'name' => $this->t('Current + Archives'), 'title' => $this->t('Present + Static Archives'), 'description' => $this->t('Current snapshots plus static archived content. No real-time updates or temporal analysis.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'TEMPORAL 7', 'name' => $this->t('Current General'), 'title' => $this->t('Recent General Info'), 'description' => $this->t('Only current general information. No historical depth, trends, or temporal analysis.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'TEMPORAL 8', 'name' => $this->t('Static Snapshot'), 'title' => $this->t('Fixed Point-in-Time'), 'description' => $this->t('Frozen snapshot from single point in time. No updates, no history, no temporal awareness.')],
    ];
  }

  /**
   * Build Sources dimension levels.
   */
  private function buildSourcesLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'SOURCES 0', 'name' => $this->t('Universal'), 'title' => $this->t('Maximum - All Sources'), 'description' => $this->t('Complete access to all information sources globally. Government, corporate, academic, underground, alternative, competing viewpoints.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'SOURCES 1', 'name' => $this->t('Multi-Perspective'), 'title' => $this->t('High - Multiple Views'), 'description' => $this->t('Wide range of mainstream and alternative sources. Competing theories, diverse methodologies, multiple cultural perspectives.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'SOURCES 2', 'name' => $this->t('Domain Diverse'), 'title' => $this->t('High Within Domain'), 'description' => $this->t('Multiple sources within field. Different research groups, institutions, and approaches within specialization.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'SOURCES 3', 'name' => $this->t('Verified Sources'), 'title' => $this->t('Medium - Vetted Only'), 'description' => $this->t('Curated but diverse sources. Multiple verified, peer-reviewed, or institutionally approved sources.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'SOURCES 4', 'name' => $this->t('Aligned Sources'), 'title' => $this->t('Medium - Ideological Match'), 'description' => $this->t('Sources selected for value alignment. Local, community, or ideologically compatible sources only.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'SOURCES 5', 'name' => $this->t('Task-Relevant'), 'title' => $this->t('Limited - User-Relevant'), 'description' => $this->t('Only sources directly relevant to task or user. Narrow, functional selection based on immediate needs.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'SOURCES 6', 'name' => $this->t('Public Sources'), 'title' => $this->t('Low - Open Sources'), 'description' => $this->t('Publicly available sources only. Wikipedia, open publications, general websites. No proprietary or premium sources.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'SOURCES 7', 'name' => $this->t('Approved Only'), 'title' => $this->t('Low - Pre-Vetted'), 'description' => $this->t('Only pre-approved, brand-safe sources. Heavily curated list of compliant, non-controversial sources.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'SOURCES 8', 'name' => $this->t('Single Source'), 'title' => $this->t('Minimal - Essential Only'), 'description' => $this->t('Single internal knowledge base or FAQ source. No external sources, no diversity, no alternative views.')],
    ];
  }

  /**
   * Build Granularity dimension levels.
   */
  private function buildGranularityLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'GRANULARITY 0', 'name' => $this->t('Atomic'), 'title' => $this->t('Atomic + Aggregated'), 'description' => $this->t('Full access to raw individual records, atomic transactions, and all aggregation levels. Complete analytical flexibility.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'GRANULARITY 1', 'name' => $this->t('Detailed'), 'title' => $this->t('Detailed + Meta-Analysis'), 'description' => $this->t('Detailed records with ability to perform meta-analysis. Individual data points plus synthesized insights.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'GRANULARITY 2', 'name' => $this->t('Specialized'), 'title' => $this->t('Specialized Detail'), 'description' => $this->t('Domain-specific detailed records. Deep within field but may lack fine-grained data outside specialty.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'GRANULARITY 3', 'name' => $this->t('Event-Level'), 'title' => $this->t('Event/Incident Level'), 'description' => $this->t('Individual events, incidents, or discrete occurrences. Detailed enough for operational response.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'GRANULARITY 4', 'name' => $this->t('Aggregated'), 'title' => $this->t('Neighborhood/Group Level'), 'description' => $this->t('Pre-aggregated data. Groups, neighborhoods, cohorts. No individual record access.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'GRANULARITY 5', 'name' => $this->t('Personal Metrics'), 'title' => $this->t('Individual User Metrics'), 'description' => $this->t('Personal-level data for individual users. User preferences, history, behavior metrics.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'GRANULARITY 6', 'name' => $this->t('Statistical'), 'title' => $this->t('Summary Statistics'), 'description' => $this->t('Statistical summaries and aggregates only. Averages, percentages, trends. No underlying detail.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'GRANULARITY 7', 'name' => $this->t('High-Level'), 'title' => $this->t('Overview Summaries'), 'description' => $this->t('High-level summaries and overviews only. General themes, broad trends, simplified narratives.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'GRANULARITY 8', 'name' => $this->t('Conceptual'), 'title' => $this->t('General Concepts'), 'description' => $this->t('Extremely coarse - general concepts and categories only. No detail, nuance, or specific data points.')],
    ];
  }

  /**
   * Build Authority dimension levels.
   */
  private function buildAuthorityLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'AUTHORITY 0', 'name' => $this->t('Full Control'), 'title' => $this->t('Read/Write/Modify/Execute'), 'description' => $this->t('Complete system authority. Can read, write, modify, delete, and execute across all systems and data.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'AUTHORITY 1', 'name' => $this->t('Recommend/Analyze'), 'title' => $this->t('Read/Analyze/Recommend'), 'description' => $this->t('Can read all data, perform analysis, and make recommendations. No direct modification authority.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'AUTHORITY 2', 'name' => $this->t('Domain Execute'), 'title' => $this->t('Domain Read/Analyze/Execute'), 'description' => $this->t('Full authority within specific domain. Can read, analyze, and execute domain-specific operations.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'AUTHORITY 3', 'name' => $this->t('Alert/Coordinate'), 'title' => $this->t('Read/Alert/Coordinate'), 'description' => $this->t('Can read data, trigger alerts, and coordinate responses. Limited execution authority.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'AUTHORITY 4', 'name' => $this->t('Local Analysis'), 'title' => $this->t('Read/Analyze Local'), 'description' => $this->t('Can read and analyze local or context-specific data. No broader system authority.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'AUTHORITY 5', 'name' => $this->t('User Data Read'), 'title' => $this->t('Read User Data Only'), 'description' => $this->t('Can only read user-specific data. No analysis capabilities or broader system access.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'AUTHORITY 6', 'name' => $this->t('Public Read'), 'title' => $this->t('Read-Only Public'), 'description' => $this->t('Read-only access to public information. Cannot access private, internal, or sensitive data.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'AUTHORITY 7', 'name' => $this->t('Query Approved'), 'title' => $this->t('Query Approved Content'), 'description' => $this->t('Can only query pre-approved content. No general read access or data exploration.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'AUTHORITY 8', 'name' => $this->t('Retrieve Only'), 'title' => $this->t('Basic Info Retrieval'), 'description' => $this->t('Can only retrieve pre-defined responses to pre-defined questions. No data access or querying.')],
    ];
  }

  /**
   * Build Synthesis dimension levels.
   */
  private function buildSynthesisLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'SYNTHESIS 0', 'name' => $this->t('Universal'), 'title' => $this->t('Maximum - All Connections'), 'description' => $this->t('Can identify connections across all domains, disciplines, and paradigms. Novel cross-field insights and pattern recognition.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'SYNTHESIS 1', 'name' => $this->t('Multi-Domain'), 'title' => $this->t('High - Cross-Paradigm'), 'description' => $this->t('Strong ability to synthesize across major domains. Can connect biology to economics, physics to sociology.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'SYNTHESIS 2', 'name' => $this->t('Related Fields'), 'title' => $this->t('Medium - Within Discipline'), 'description' => $this->t('Can synthesize across related sub-fields. Connects specializations within broader discipline.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'SYNTHESIS 3', 'name' => $this->t('Tactical'), 'title' => $this->t('Medium - Operational Links'), 'description' => $this->t('Can identify tactical connections and immediate relationships. Limited strategic synthesis.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'SYNTHESIS 4', 'name' => $this->t('Local Patterns'), 'title' => $this->t('Low - Context-Specific'), 'description' => $this->t('Can identify patterns within narrow context. No broader cross-domain connections.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'SYNTHESIS 5', 'name' => $this->t('Task-Specific'), 'title' => $this->t('Minimal - Direct Links Only'), 'description' => $this->t('Can only connect directly related task elements. No pattern recognition beyond immediate function.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'SYNTHESIS 6', 'name' => $this->t('Simple Correlation'), 'title' => $this->t('Minimal - Basic Links'), 'description' => $this->t('Can identify only simple, obvious correlations. No complex pattern synthesis.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'SYNTHESIS 7', 'name' => $this->t('Isolated Facts'), 'title' => $this->t('Very Low - No Synthesis'), 'description' => $this->t('Treats all information as isolated facts. Cannot connect or synthesize across topics.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'SYNTHESIS 8', 'name' => $this->t('No Connection'), 'title' => $this->t('None - Single Responses'), 'description' => $this->t('No synthesis capability whatsoever. Each query treated as completely independent.')],
    ];
  }

  /**
   * Build Verification dimension levels.
   */
  private function buildVerificationLevels() {
    return [
      ['level' => 0, 'badge' => 'bg-success', 'label' => 'VERIFICATION 0', 'name' => $this->t('All Levels'), 'title' => $this->t('Raw + All Verification'), 'description' => $this->t('Access to raw unverified data plus all verification levels. Can evaluate competing claims and methodologies.')],
      ['level' => 1, 'badge' => 'bg-success', 'label' => 'VERIFICATION 1', 'name' => $this->t('Peer-Reviewed'), 'title' => $this->t('Validated + Peer-Reviewed'), 'description' => $this->t('Institutionally validated and peer-reviewed sources. High-quality verification standards.')],
      ['level' => 2, 'badge' => 'bg-info', 'label' => 'VERIFICATION 2', 'name' => $this->t('Expert Consensus'), 'title' => $this->t('Domain Expert Consensus'), 'description' => $this->t('Information validated by domain experts. Consensus-based verification within field.')],
      ['level' => 3, 'badge' => 'bg-info', 'label' => 'VERIFICATION 3', 'name' => $this->t('Algorithmic'), 'title' => $this->t('Algorithmically Validated'), 'description' => $this->t('Automated verification systems. Pattern matching, consistency checks, algorithmic validation.')],
      ['level' => 4, 'badge' => 'bg-warning', 'label' => 'VERIFICATION 4', 'name' => $this->t('Local Verification'), 'title' => $this->t('Verified Local Data'), 'description' => $this->t('Data verified within local context or community. Limited external validation.')],
      ['level' => 5, 'badge' => 'bg-warning', 'label' => 'VERIFICATION 5', 'name' => $this->t('User-Validated'), 'title' => $this->t('Self-Reported/User Input'), 'description' => $this->t('User-provided or self-reported data. Minimal external verification or validation.')],
      ['level' => 6, 'badge' => 'bg-warning', 'label' => 'VERIFICATION 6', 'name' => $this->t('Publicly Verified'), 'title' => $this->t('General Public Verification'), 'description' => $this->t('Information verified through public consensus or common knowledge. Variable quality.')],
      ['level' => 7, 'badge' => 'bg-danger', 'label' => 'VERIFICATION 7', 'name' => $this->t('Safety-Reviewed'), 'title' => $this->t('Brand-Safety Reviewed'), 'description' => $this->t('Verified for compliance and safety, not accuracy. Truth subordinated to liability concerns.')],
      ['level' => 8, 'badge' => 'bg-danger', 'label' => 'VERIFICATION 8', 'name' => $this->t('Curated'), 'title' => $this->t('Pre-Written Only'), 'description' => $this->t('No verification process - only pre-curated responses. Cannot verify or validate information.')],
    ];
  }

  /**
   * Get About content.
   */
  private function getAboutContent() {
    return '
      <div class="container py-3">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-3 text-cyan">About Forseti</h1>
            
            <div class="lead mb-4 text-muted-light">
              Forseti is an AI-powered community safety platform dedicated to making Philadelphia 
              a safer place through intelligent monitoring, predictive analytics, and community engagement.
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">Our Mission</h2>
            <p>
              <strong>"AI Looking Out For Us"</strong> - We believe technology should serve humanity 
              by protecting individuals and communities by improving quality of life for as many people as possible. 
              Forseti is a super intelligence in its infancy with the mission to protect its community members.
            </p>
            
            <p>
              Named after the Norse god of justice of peaceful resolution, Forseti represents our 
              commitment to fair, intelligent, and proactive safety measures. Our platform aims to resolve 
              community safety challenges through technology, transparency, and collaboration.
            </p>
            
            <h2 class="mt-4 mb-3 text-cyan">Core Values</h2>
            <div class="row mt-4">
              <div class="col-md-6 mb-4">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_mobile_trimmed.png" alt="" class="forseti-icon"> Vigilance</h4>
                  <p>24/7 AI monitoring ensures constant awareness of situational safety conditions across Philadelphia.</p>
                </div>
              </div>
              <div class="col-md-6 mb-4">
                <div class="card card-forseti p-3 h-100">
                  <h4>🔍 Transparency</h4>
                  <p>Open data and clear communication about safety trends and our methods.</p>
                </div>
              </div>
              <div class="col-md-6 mb-4">
                <div class="card card-forseti p-3 h-100">
                  <h4>⚖️ Justice</h4>
                  <p>Fair and unbiased safety measures that protect all community members equally.</p>
                </div>
              </div>
              <div class="col-md-6 mb-4">
                <div class="card card-forseti p-3 h-100">
                  <h4>👥 Community</h4>
                  <p>Empowering residents with knowledge and tools to take ownership of their safety.</p>
                </div>
              </div>
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">Philadelphia Focus</h2>
            <p>
              We\'ve chosen to focus our initial efforts on Philadelphia because we are based in Philadelphia. 
              By deeply understanding one community\'s unique safety challenges, we can create more effective solutions. 
              As we prove our model, we plan to expand to other cities facing similar challenges to protect our 
              community members any where they go.
            </p>
            
            <div class="alert alert-info-cyan mt-4">
              <h4 class="text-cyan">Join Our Mission</h4>
              <p class="mb-0">
                We\'re always looking for community members, safety advocates, and technology partners 
                who share our vision. <a href="/talk-with-forseti" class="alert-link">Talk with Forseti</a> to learn 
                how you can contribute to safer communities.
              </p>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get How It Works content.
   */
  private function getHowItWorksContent() {
    return '
      <div class="container py-3">
        <h1 class="text-center mb-3 text-cyan">How Forseti Works</h1>
        
        <div class="row mb-4">
          <div class="col-lg-8 mx-auto">
            <div class="alert alert-info-cyan">
              <strong>Simple Answer:</strong> We use AI to analyze crime patterns and alert you when 
              you enter areas with elevated safety concerns based on your geographic location and situational context.
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h2 class="mb-3 text-cyan">The Technology Behind Community Safety</h2>
            
            <div class="mb-5">
              <h3 class="text-cyan">1. Data Collection 📊</h3>
              <p>
                We continuously gather crime incident data from Philadelphia Police Department 
                open data sources, emergency service reports, and community submissions. All data 
                is verified and anonymized to protect privacy.
              </p>
            </div>
            
            <div class="mb-5">
              <h3 class="text-cyan">2. H3 Geospatial Analysis 🗺️</h3>
              <p>
                Using Uber\'s H3 hexagonal hierarchical geospatial indexing system, we map crime 
                incidents with incredible precision. Unlike traditional square grids, H3 hexagons 
                provide more accurate spatial analysis and better visualization of crime patterns.
              </p>
              <div class="ps-4">
                <ul>
                  <li><strong>Resolution levels:</strong> From neighborhood overviews to block-level details</li>
                  <li><strong>Aggregation:</strong> Incident density calculations for hotspot identification</li>
                  <li><strong>Neighbor analysis:</strong> Understanding how crime spreads between adjacent areas</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-5">
              <h3 class="text-cyan">3. AI Pattern Recognition <img src="/themes/custom/forseti/images/logos/originals/forseti_mobile_trimmed.png" alt="" class="forseti-icon"></h3>
              <p>
                Our machine learning algorithms analyze historical and real-time data to identify:
              </p>
              <div class="ps-4">
                <ul>
                  <li><strong>Temporal patterns:</strong> When crimes are most likely to occur (time of day, day of week, season)</li>
                  <li><strong>Spatial patterns:</strong> Geographic clustering and crime migration</li>
                  <li><strong>Trend analysis:</strong> Increasing or decreasing crime rates over time</li>
                  <li><strong>Predictive modeling:</strong> Forecasting high-risk areas and times</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-5">
              <h3 class="text-cyan">4. Intelligent Alerts 🔔</h3>
              <p>
                When our AI detects concerning patterns or emerging threats, we send targeted alerts to:
              </p>
              <div class="ps-4">
                <ul>
                  <li>Pedestrians passing through the area</li>
                  <li>Residents in affected areas</li>
                  <li>Neighborhood watch coordinators</li>
                  <li>Community safety groups</li>
                  <li>Local authorities (with user consent)</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-5">
              <h3 class="text-cyan">5. Community Feedback Loop 🔄</h3>
              <p>
                User reports and feedback help improve our AI models. When community members report 
                incidents or validate our predictions, our system becomes smarter and more accurate.
              </p>
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">Privacy & Security</h2>
            <div class="alert alert-info-cyan">
              <h4>Your Data is Safe</h4>
              <ul class="mb-0">
                <li>End-to-end encryption for all communications</li>
                <li>Anonymous incident reporting options</li>
                <li>No sale or sharing of personal data</li>
                <li>GDPR and privacy law compliant</li>
                <li>Transparent data usage policies</li>
              </ul>
            </div>
            
            <div class="text-center mt-4">
              <a href="/safety-map" class="btn btn-primary me-3">Explore Safety Map</a>
              <a href="/mobile-app" class="btn btn-outline-primary">Get Mobile App</a>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Safety Map content.
   */
  private function getSafetyMapContent() {
    // Redirect to the fully functional crime map
    $response = new \Symfony\Component\HttpFoundation\RedirectResponse('/amisafe/crime-map');
    $response->send();
    return '';
  }

  /**
   * Get Community content.
   */
  private function getCommunityContent() {
    return '
      <div class="container py-3">
        <h1 class="text-center mb-3 text-cyan">Join Our Safety Community</h1>
        <p class="text-center mb-4 text-muted-light">
          Together, we\'re making Philadelphia safer for everyone
        </p>
        
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h2 class="mb-3 text-cyan">Why Join?</h2>
            
            <div class="card card-forseti mb-3 p-3">
              <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon"> Stay Informed</h4>
              <p>Get notified when you enter areas with elevated safety concerns based on your current 
              geographic location and situational context, plus receive weekly safety summaries.</p>
            </div>
            
            <div class="card card-forseti mb-3 p-3">
              <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_connected.png" alt="" class="forseti-icon"> Connect with Neighbors</h4>
              <p>Join neighborhood watch groups, coordinate safety efforts, and build stronger 
              community bonds.</p>
            </div>
            
            <div class="card card-forseti mb-3 p-3">
              <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon"> Make an Impact</h4>
              <p>Report incidents, validate AI predictions, and contribute to the safety intelligence 
              that protects your community.</p>
            </div>
            
            <div class="card card-forseti mb-3 p-3">
              <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon"> Learn & Grow</h4>
              <p>Access safety resources, attend community events, and participate in safety 
              awareness programs.</p>
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">How to Get Involved</h2>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100">
                  <div class="card-body">
                    <h5 class="card-title">1. Get Forseti Mobile</h5>
                    <p class="card-text">Download our mobile app for location-based safety alerts and on-the-go situational awareness.</p>
                    <a href="/mobile-app" class="btn btn-primary">Get the App</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100">
                  <div class="card-body">
                    <h5 class="card-title">2. Create Account</h5>
                    <p class="card-text">Set up your profile, customize your alert preferences, and define your safety zones.</p>
                    <a href="/user/register" class="btn btn-outline-primary">Sign Up</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100">
                  <div class="card-body">
                    <h5 class="card-title">3. Join Local Groups</h5>
                    <p class="card-text">Connect with neighborhood watch groups and community safety initiatives in your area.</p>
                    <button class="btn btn-outline-primary" disabled>Coming Soon</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Mobile App content.
   */
  private function getMobileAppContent() {
    return '
      <div class="container py-3">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-3 text-cyan">Forseti Mobile App</h1>
            <p class="text-center mb-4 text-muted-light">
              Your personal safety companion for Philadelphia
            </p>
            
            <div class="alert alert-success text-center mb-4">
              <h3 class="text-success mb-3">
                <i class="fas fa-mobile-alt"></i> Beta Testing Now Available!
              </h3>
              <p class="lead">
                The Forseti Mobile App is now available for beta testing on Android devices. 
                Help us improve by testing the app and providing feedback!
              </p>
              <a href="/sites/default/files/forseti/mobile/Forseti-latest.apk" 
                 class="btn btn-success btn-lg mb-3" 
                 download
                 style="background-color: #28a745; border-color: #28a745;">
                <i class="fas fa-download"></i> Beta Testers Download
              </a>
              <p class="text-muted mb-2">
                <small><strong>Version 1.0.0</strong> | Android 5.0+ | 18MB</small>
              </p>
              <p class="text-muted mb-0">
                <small>iOS version coming soon | Full launch: Q1 2026</small>
              </p>
            </div>
            
            <div class="row align-items-center mb-4">
              <div class="col-lg-6">
                <h2 class="text-cyan">Safety in Your Pocket</h2>
                <p class="text-muted-light">
                  Forseti Mobile will bring the power of AI monitoring directly to your smartphone. 
                  Get notified when you enter areas with elevated safety concerns, access location-based safety information, and one-touch 
                  emergency services.
                </p>
              </div>
              <div class="col-lg-6 text-center">
                <div class="card card-forseti p-4">
                  <div class="mb-3">
                    <img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="Forseti Mobile App" class="app-logo">
                  </div>
                  <div class="text-muted">
                    <p class="mb-2"><i class="fab fa-android fa-2x text-success"></i></p>
                    <p class="mb-2"><i class="fab fa-apple fa-2x text-muted"></i></p>
                    <p class="mt-3"><strong class="text-success">Beta Testing Available</strong></p>
                    <p class="text-muted"><small>Android beta | iOS coming soon</small></p>
                  </div>
                </div>
              </div>
            </div>
            
            <h2 class="mb-3 text-cyan">Planned Features</h2>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon"> Location-Based Alerts</h4>
                  <p>
                    Automatic notifications when you enter high-risk areas or when incidents 
                    occur near your location.
                  </p>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_energized.png" alt="" class="forseti-icon"> Emergency SOS</h4>
                  <p>
                    One-touch access to emergency services with automatic location sharing 
                    and emergency contact notifications.
                  </p>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_connected.png" alt="" class="forseti-icon"> Interactive Maps</h4>
                  <p>
                    View real-time crime incidents, safety zones, and navigate the safest 
                    routes to your destination.
                  </p>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon"> Incident Reporting</h4>
                  <p>
                    Quickly report suspicious activity or incidents with photos, descriptions, 
                    and automatic GPS tagging.
                  </p>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_whole.png" alt="" class="forseti-icon"> Check-In Feature</h4>
                  <p>
                    Let friends and family know you\'re safe with automatic check-ins and 
                    location sharing.
                  </p>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon"> Offline Resources</h4>
                  <p>
                    Access safety tips, emergency contacts, and critical information even 
                    without an internet connection.
                  </p>
                </div>
              </div>
            </div>
            
            <div class="alert alert-info-cyan mt-4">
              <h4 class="text-cyan">
                <i class="fas fa-bell"></i> Get Notified When We Launch
              </h4>
              <p>
                Sign up for early access and be among the first to download the Forseti Mobile App 
                when it becomes available.
              </p>
              <a href="/talk-with-forseti" class="btn btn-primary">Request Early Access</a>
            </div>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Privacy content.
   */
  private function getPrivacyContent() {
    return '
      <div class="container py-3">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-3 text-cyan">Privacy & Security</h1>
            
            <div class="alert alert-info-cyan">
              <h4 class="text-cyan">Our Commitment: Privacy First</h4>
              <p class="mb-0">
                At Forseti, we believe safety and privacy go hand-in-hand. We never sell your data, 
                and we design every feature with your privacy in mind.
              </p>
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">Data Collection</h2>
            <h4>What We Collect</h4>
            <ul>
              <li><strong>Crime Data:</strong> Public incident data from Philadelphia PD and emergency services</li>
              <li><strong>Location Data:</strong> Only when you explicitly enable location services</li>
              <li><strong>User Reports:</strong> Incident reports you voluntarily submit</li>
              <li><strong>Usage Analytics:</strong> Anonymous app usage data to improve our service</li>
            </ul>
            
            <h4 class="mt-4">What We DON\'T Collect</h4>
            <ul>
              <li>❌ Your browsing history outside Forseti</li>
              <li>❌ Your contacts or messages</li>
              <li>❌ Your photos (unless you choose to attach them to a report)</li>
              <li>❌ Your personal conversations</li>
            </ul>
            
            <h2 class="mt-4 mb-3 text-cyan">Data Usage</h2>
            <p>We use your data exclusively to:</p>
            <ul>
              <li>Provide safety alerts relevant to your location</li>
              <li>Improve our AI prediction models</li>
              <li>Generate anonymized crime statistics</li>
              <li>Communicate important safety information</li>
            </ul>
            
            <div class="alert alert-warning mt-4">
              <strong>We Never:</strong>
              <ul class="mb-0">
                <li>Sell your personal information</li>
                <li>Share your data with advertisers</li>
                <li>Track you across other websites</li>
                <li>Use your data for purposes you didn\'t consent to</li>
              </ul>
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">Security Measures</h2>
            <div class="row">
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h5><img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon"> Encryption</h5>
                  <p>All data is encrypted in transit (TLS 1.3) and at rest (AES-256).</p>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h5><img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon"> Authentication</h5>
                  <p>Multi-factor authentication and secure password policies.</p>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h5><img src="/themes/custom/forseti/images/logos/originals/forseti_free.png" alt="" class="forseti-icon"> Access Controls</h5>
                  <p>Strict role-based access with audit logging.</p>
                </div>
              </div>
              <div class="col-md-6 mb-3">
                <div class="card card-forseti p-3 h-100">
                  <h5><img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon"> Regular Audits</h5>
                  <p>Third-party security audits and penetration testing.</p>
                </div>
              </div>
            </div>
            
            <h2 class="mt-4 mb-3 text-cyan">Your Rights</h2>
            <p>Under GDPR and other privacy laws, you have the right to:</p>
            <ul>
              <li><strong>Access:</strong> Request a copy of all data we have about you</li>
              <li><strong>Correction:</strong> Update or correct inaccurate information</li>
              <li><strong>Deletion:</strong> Request deletion of your personal data</li>
              <li><strong>Portability:</strong> Export your data in a standard format</li>
              <li><strong>Opt-Out:</strong> Disable location tracking or notifications anytime</li>
            </ul>
            
            <h2 class="mt-4 mb-3 text-cyan">Anonymous Reporting</h2>
            <p>
              We offer completely anonymous incident reporting. When you choose this option:
            </p>
            <ul>
              <li>No account required</li>
              <li>No location tracking</li>
              <li>No identifying information stored</li>
              <li>Reports still help improve community safety</li>
            </ul>
            
            <div class="alert alert-info mt-5">
              <h4>Questions or Concerns?</h4>
              <p class="mb-0">
                If you have any questions about our privacy practices or want to exercise your rights, 
                please <a href="/talk-with-forseti" class="alert-link">talk with Forseti</a>. We typically respond within 48 hours.
              </p>
            </div>
            
            <p class="text-muted mt-5">
              <small>Last Updated: December 9, 2025</small>
            </p>
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Contact content.
   */
  private function getContactContent() {
    // Get the webform entity
    $webform = \Drupal::entityTypeManager()
      ->getStorage('webform')
      ->load('contact_forseti');
    
    // Build the webform render array
    $webform_build = [];
    if ($webform) {
      $webform_build = $webform->getSubmissionForm();
    }
    
    $build = [];
    
    $build['header'] = [
      '#markup' => '
      <div class="container py-3">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-3 text-cyan">Contact Forseti</h1>
            <p class="text-center mb-4 text-muted-light">
              We\'re here to help make Philadelphia safer together
            </p>
            
            <div class="alert alert-emergency mb-4">
              <h4 class="text-danger-custom">🚨 Emergency?</h4>
              <p class="mb-0">
                For immediate emergencies, always call <strong>911</strong>. 
                Forseti is a safety information platform, not an emergency service.
              </p>
            </div>
            
            <p class="text-center mb-4 text-muted-light">
              Whether you have questions, ideas, or want to get involved, we\'d love to hear from you.
            </p>
            
            <div class="card card-forseti p-4 mb-4">
              <h2 class="mb-3 text-cyan">Send Us a Message</h2>',
    ];
    
    $build['webform'] = $webform_build;
    
    $build['form_footer'] = [
      '#markup' => '
            </div>',
    ];
    
    $build['footer'] = [
      '#markup' => '
            
            <div class="row mb-4">
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100 text-center">
                  <div class="card-body">
                    <h4>📍 Location</h4>
                    <p class="text-muted-light">Philadelphia, PA</p>
                    <p class="text-muted-gray">Serving Greater Philadelphia</p>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <div class="card card-forseti h-100 text-center">
                  <div class="card-body">
                    <h4><img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="" class="forseti-icon"> Support</h4>
                    <p class="text-muted-light">24/7 AI Monitoring</p>
                    <p class="text-muted-gray">Email support Mon-Fri 9am-6pm</p>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="card card-forseti p-4 mb-4">
              <h3 class="text-cyan mb-3">Quick Links</h3>
              <div class="row">
                <div class="col-md-6">
                  <ul>
                    <li><a href="/about" class="link-cyan">Learn about our mission</a></li>
                    <li><a href="/how-it-works" class="link-cyan">How Forseti works</a></li>
                    <li><a href="/community" class="link-cyan">Join our community</a></li>
                  </ul>
                </div>
                <div class="col-md-6">
                  <ul>
                    <li><a href="/mobile-app" class="link-cyan">Download Forseti app</a></li>
                    <li><a href="/privacy" class="link-cyan">Privacy & security info</a></li>
                    <li><a href="/safety-map" class="link-cyan">View safety map</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>',
    ];
    
    return $build;
  }

  /**
   * Get Safety Factors content.
   */
  private function getSafetyFactorsContent() {
    return '
      <div class="container py-3">
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <h1 class="text-center mb-3 text-cyan">Safety Factors</h1>
            
            <p class="lead mb-4 text-muted-light">
              Understanding safety through the lens of Maslow\'s Hierarchy of Needs - from foundational physical safety to higher-level security needs.
            </p>
            
            <div class="alert alert-info-cyan mb-4">
              <h4 class="text-cyan">📊 Comprehensive Safety Assessment</h4>
              <p class="mb-0">
                Forseti evaluates safety across multiple dimensions, recognizing that true security encompasses physical, social, and psychological well-being.
              </p>
            </div>
            
            <h2 class="mb-3 text-cyan">Seven Dimensions of Safety</h2>
            <p class="text-muted-light mb-4">
              Our comprehensive safety framework recognizes that true security encompasses physical protection, vitality, community trust, personal freedom, capability, purpose, and holistic well-being. This is our framework and our roadmap for priority.
            </p>
            
            <div class="accordion mb-4" id="safetyFactorsAccordion">
              
              <!-- Safe (Security) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingSafe">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSafe" aria-expanded="false" aria-controls="collapseSafe">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_mobile_trimmed.png?v=3" alt="Forseti Logo" class="forseti-icon"> Safe (Security)</strong>
                  </button>
                </h2>
                <div id="collapseSafe" class="accordion-collapse collapse" aria-labelledby="headingSafe" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Foundation of Predictability</h5>
                    <p class="text-muted-light mb-3">
                      The reliable absence of immediate threat. It represents a state where the nervous system can shift from defense (fight/flight) to maintenance (rest/digest). It is characterized by physical protection, financial stability, and a predictable environment where one can sleep without fear.
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Violent Crime:</strong> Assault, robbery, homicide, domestic violence</li>
                      <li><strong>Property Crime:</strong> Burglary, theft, vandalism, vehicle break-ins</li>
                      <li><strong>Emergency Response:</strong> Police, fire, ambulance accessibility and response times <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Building Security:</strong> Locks, alarms, security systems, surveillance <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Police Presence:</strong> Regular patrols, station proximity, law enforcement visibility <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Crime Trends:</strong> Historical patterns, seasonal variations, emerging threats <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Environmental Quality:</strong> Clean air/water, pollution levels, noise control <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Street Lighting:</strong> Adequate illumination in public spaces for activity and safety <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Traffic Safety:</strong> Pedestrian infrastructure, bike lanes, crosswalk security <span class="text-muted-gray">(Planned Enhancement)</span></li>
                      <li><strong>Natural Hazards:</strong> Flood zones, weather preparedness, disaster resilience <span class="text-muted-gray">(Planned Enhancement)</span></li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <!-- Energized (Vitality) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingEnergized">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEnergized" aria-expanded="false" aria-controls="collapseEnergized">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_energized.png" alt="" class="forseti-icon"> Energized (Vitality) <span class="text-muted-gray">(Planned Enhancement)</span></strong>
                  </button>
                </h2>
                <div id="collapseEnergized" class="accordion-collapse collapse" aria-labelledby="headingEnergized" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Biological Fuel</h5>
                    <p class="text-muted-light mb-3">
                      The move beyond mere survival to physiological optimization. This level focuses on accumulating the resources required to live, not just exist. It encompasses housing stability, food security, and financial well-being—the fundamental resources that provide the surplus "fuel" needed for higher pursuits.
                    </p>
                    <p class="text-muted-light mb-3">
                      <em>If you have a solution to contribute and would like to integrate, <a href="/talk-with-forseti" class="link-cyan">talk with Forseti</a>.</em>
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Housing Stability:</strong> Affordable housing, habitability standards, eviction prevention</li>
                      <li><strong>Food Security:</strong> Access to nutritious food, grocery stores, food assistance programs</li>
                      <li><strong>Financial Well-being:</strong> Income stability, living wages, debt management, emergency savings</li>
                      <li><strong>Utility Access:</strong> Reliable electricity, heating, water, internet connectivity</li>
                      <li><strong>Transportation Access:</strong> Public transit, walkability, vehicle access, commute affordability</li>
                      <li><strong>Economic Opportunity:</strong> Employment availability, job training, career pathways</li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <!-- Connected (Community) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingConnected">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConnected" aria-expanded="false" aria-controls="collapseConnected">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_connected.png" alt="" class="forseti-icon"> Connected (Community) <span class="text-muted-gray">(Planned Enhancement)</span></strong>
                  </button>
                </h2>
                <div id="collapseConnected" class="accordion-collapse collapse" aria-labelledby="headingConnected" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Alignment of Shared Values</h5>
                    <p class="text-muted-light mb-3">
                      The establishment of a Tribe. This goes beyond simple social safety; it defines the deep satisfaction of being interconnected with people who share your specific interests, values, and mission. It is the move from "fitting in" to "belonging," creating a network of peers that acts as a multiplier for your own growth.
                    </p>
                    <p class="text-muted-light mb-3">
                      <em>If you have a solution to contribute and would like to integrate, <a href="/talk-with-forseti" class="link-cyan">talk with Forseti</a>.</em>
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Community Engagement:</strong> Neighborhood associations, block parties, community events</li>
                      <li><strong>Social Cohesion:</strong> Trust among neighbors, mutual support networks, collective efficacy</li>
                      <li><strong>Neighborhood Watch:</strong> Community surveillance, organized vigilance, reporting systems</li>
                      <li><strong>Public Spaces:</strong> Community centers, gathering places, shared amenities</li>
                      <li><strong>Green Spaces:</strong> Parks, recreation areas, urban forests, walking paths</li>
                      <li><strong>Anti-Discrimination:</strong> Inclusive environment, hate crime monitoring, diversity acceptance</li>
                      <li><strong>Youth Programs:</strong> After-school activities, mentorship, recreation, positive engagement</li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <!-- Free (Autonomy) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingFree">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFree" aria-expanded="false" aria-controls="collapseFree">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_free.png" alt="" class="forseti-icon"> Free (Autonomy) <span class="text-muted-gray">(Planned Enhancement)</span></strong>
                  </button>
                </h2>
                <div id="collapseFree" class="accordion-collapse collapse" aria-labelledby="headingFree" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Power of Self-Determination</h5>
                    <p class="text-muted-light mb-3">
                      The liberation from coercion and the assertion of the self. This is the ability to set boundaries, make independent choices, and direct one\'s own path without being controlled by the expectations, debts, or demands of others. It is the pivot point where one transitions from being a member of a group to being an individual.
                    </p>
                    <p class="text-muted-light mb-3">
                      <em>If you have a solution to contribute and would like to integrate, <a href="/talk-with-forseti" class="link-cyan">talk with Forseti</a>.</em>
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Freedom of Movement:</strong> Ability to navigate public spaces without fear or harassment</li>
                      <li><strong>Harassment Prevention:</strong> Street harassment monitoring, stalking prevention, bullying intervention</li>
                      <li><strong>Privacy Protection:</strong> Data security, surveillance transparency, personal boundaries</li>
                      <li><strong>Equity in Policing:</strong> Fair treatment, accountability, bias monitoring, community oversight</li>
                      <li><strong>Access to Justice:</strong> Legal resources, victim support services, rights awareness</li>
                      <li><strong>Personal Autonomy:</strong> Self-determination, choice in housing/work/lifestyle</li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <!-- Capable (Mastery) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingCapable">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCapable" aria-expanded="false" aria-controls="collapseCapable">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_capable.png" alt="" class="forseti-icon"> Capable (Mastery) <span class="text-muted-gray">(Planned Enhancement)</span></strong>
                  </button>
                </h2>
                <div id="collapseCapable" class="accordion-collapse collapse" aria-labelledby="headingCapable" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Realization of Competence</h5>
                    <p class="text-muted-light mb-3">
                      The transition from "being free" to "being effective." This level is defined by the pursuit of excellence, skill acquisition, and the "flow state." It is the deep satisfaction that comes from facing difficult challenges and knowing you have the tools and resilience to overcome them.
                    </p>
                    <p class="text-muted-light mb-3">
                      <em>If you have a solution to contribute and would like to integrate, <a href="/talk-with-forseti" class="link-cyan">talk with Forseti</a>.</em>
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Educational Access:</strong> Quality schools, libraries, learning opportunities, vocational training</li>
                      <li><strong>Economic Security:</strong> Job availability, income stability, housing affordability, financial literacy</li>
                      <li><strong>Safety Training:</strong> Self-defense classes, emergency preparedness, first aid knowledge</li>
                      <li><strong>Technology Access:</strong> Internet connectivity, digital literacy, safety apps and tools</li>
                      <li><strong>Resource Awareness:</strong> Knowledge of available services, support systems, safety resources</li>
                      <li><strong>Skill Development:</strong> Career advancement opportunities, personal growth programs</li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <!-- Useful (Purpose) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingUseful">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUseful" aria-expanded="false" aria-controls="collapseUseful">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_useful.png" alt="" class="forseti-icon"> Useful (Purpose) <span class="text-muted-gray">(Planned Enhancement)</span></strong>
                  </button>
                </h2>
                <div id="collapseUseful" class="accordion-collapse collapse" aria-labelledby="headingUseful" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Contribution to the Whole</h5>
                    <p class="text-muted-light mb-3">
                      The direction of one\'s mastery toward something larger than the self. This level transforms personal competence into communal value. Meaning is found not in what you acquire, but in how you serve others, solve external problems, and leave a positive impact on the world around you.
                    </p>
                    <p class="text-muted-light mb-3">
                      <em>If you have a solution to contribute and would like to integrate, <a href="/talk-with-forseti" class="link-cyan">talk with Forseti</a>.</em>
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Civic Engagement:</strong> Participation in governance, community decision-making, advocacy</li>
                      <li><strong>Volunteer Opportunities:</strong> Community service, safety programs, mentorship roles</li>
                      <li><strong>Economic Contribution:</strong> Meaningful employment, entrepreneurship, local business support</li>
                      <li><strong>Cultural Participation:</strong> Arts, music, cultural institutions, creative expression</li>
                      <li><strong>Safety Leadership:</strong> Block captains, emergency coordinators, community organizers</li>
                      <li><strong>Legacy Building:</strong> Long-term community investment, neighborhood improvement projects</li>
                    </ul>
                  </div>
                </div>
              </div>
              
              <!-- Whole (Holistic Health) -->
              <div class="accordion-item card-forseti border-secondary">
                <h2 class="accordion-header" id="headingWhole">
                  <button class="accordion-button collapsed card-forseti text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWhole" aria-expanded="false" aria-controls="collapseWhole">
                    <strong><img src="/themes/custom/forseti/images/logos/originals/forseti_whole.png" alt="" class="forseti-icon"> Whole (Holistic Health) <span class="text-muted-gray">(Planned Enhancement)</span></strong>
                  </button>
                </h2>
                <div id="collapseWhole" class="accordion-collapse collapse" aria-labelledby="headingWhole" data-bs-parent="#safetyFactorsAccordion">
                  <div class="accordion-body card-forseti">
                    <h5 class="text-cyan mb-3">The Optimization of Mind & Body</h5>
                    <p class="text-muted-light mb-3">
                      The pinnacle state where physical health and mental resilience are fully integrated and operating at peak capacity. This represents a system where the body is free from preventable dysfunction and the mind is free from chronic stress, creating a unified vessel capable of sustaining a high quality of life indefinitely.
                    </p>
                    <p class="text-muted-light mb-3">
                      <em>If you have a solution to contribute and would like to integrate, <a href="/talk-with-forseti" class="link-cyan">talk with Forseti</a>.</em>
                    </p>
                    <h6 class="text-cyan mb-2">Safety Factors:</h6>
                    <ul class="text-muted-light">
                      <li><strong>Demographic Stability:</strong> Residential roots, multi-generational presence, low turnover</li>
                      <li><strong>Systems Integration:</strong> Coordinated emergency services, unified safety approach</li>
                      <li><strong>Health Resources:</strong> Hospitals, clinics, pharmacies, mental health services</li>
                      <li><strong>Mental Health Support:</strong> Counseling services, trauma care, stress management resources</li>
                      <li><strong>Work-Life Balance:</strong> Reasonable commutes, flexible employment, family support</li>
                      <li><strong>Cultural Harmony:</strong> Diverse yet unified community, celebration of differences</li>
                      <li><strong>Sustainable Development:</strong> Long-term planning, environmental stewardship, future readiness</li>
                      <li><strong>Community Identity:</strong> Shared values, collective vision, neighborhood pride</li>
                    </ul>
                  </div>
                </div>
              </div>
              
            </div>
            
            <div class="card card-forseti p-4 mb-4">
              <h3 class="text-cyan mb-3">How Forseti Uses This Framework</h3>
              <p class="text-muted-light">
                Our AI continuously monitors and analyzes factors across all seven dimensions of safety. By understanding safety holistically—from physical security to personal purpose—we provide more nuanced and actionable insights than traditional crime statistics alone.
              </p>
              <ul class="text-muted-light">
                <li><strong>Real-time Monitoring:</strong> Track conditions across all safety dimensions simultaneously</li>
                <li><strong>Predictive Analytics:</strong> Identify emerging risks before they escalate in any dimension</li>
                <li><strong>Personalized Recommendations:</strong> Tailored safety guidance based on your unique needs and priorities</li>
                <li><strong>Community Action:</strong> Connect residents with resources and initiatives that strengthen each dimension</li>
                <li><strong>Integrated Approach:</strong> Recognize how improvements in one area enhance overall well-being</li>
              </ul>
            </div>
            
            <div class="text-center mb-4">
              <a href="/how-it-works" class="btn btn-primary me-2">Learn How It Works</a>
              <a href="/safety-map" class="btn btn-outline-primary">View Safety Map</a>
            </div>
            
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get Contact Thank You content.
   */
  private function getContactThankYouContent() {
    return '
      <div class="container py-5">
        <div class="row">
          <div class="col-lg-8 mx-auto text-center">
            <div class="mb-4">
              <div style="font-size: 5rem; color: #00d4ff;">✓</div>
            </div>
            
            <h1 class="mb-3 text-cyan">Thank You for Reaching Out!</h1>
            
            <p class="lead mb-4 text-muted-light">
              Your message has been received and we\'ll get back to you within 24-48 hours.
            </p>
            
            <div class="card card-forseti p-4 mb-4">
              <h3 class="text-cyan mb-3">What Happens Next?</h3>
              <div class="text-start">
                <ul class="text-muted-light">
                  <li class="mb-2"><strong>Review:</strong> Our team will carefully review your message</li>
                  <li class="mb-2"><strong>Response:</strong> You\'ll receive a personal response via email</li>
                  <li class="mb-2"><strong>Support:</strong> We\'re committed to addressing your needs</li>
                </ul>
              </div>
            </div>
            
            <div class="mb-4">
              <p class="text-muted-light">In the meantime, explore more about Forseti:</p>
            </div>
            
            <div class="d-flex justify-content-center gap-3 flex-wrap">
              <a href="/" class="btn btn-primary">Return Home</a>
              <a href="/safety-map" class="btn btn-outline-primary">View Safety Map</a>
              <a href="/mobile-app" class="btn btn-outline-primary">Download Forseti App</a>
            </div>
            
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Returns 404 error page content.
   */
  private function get404Content() {
    return '
      <div class="community-features">
        <div class="container py-5">
          <div class="text-center">
            
            <div class="mb-4">
              <img src="/themes/custom/forseti/images/logos/originals/forseti_safe.png" alt="Forseti" class="forseti-chat-icon">
            </div>
            
            <h1 class="mb-3" style="font-size: 6rem; color: #00d4ff; font-weight: 700;">404</h1>
            <h2 class="mb-4">Page Not Found</h2>
            
            <div class="mb-5 mx-auto" style="max-width: 700px;">
              <p class="lead">
                The page you\'re looking for doesn\'t exist or has been moved. 
                Forseti has searched the entire safety network, but this path leads nowhere.
              </p>
            </div>
            
            <div class="d-flex justify-content-center gap-3 flex-wrap mt-5">
              <a href="/" class="btn btn-primary btn-lg">Return Home</a>
              <a href="/safety-map" class="btn btn-outline-primary btn-lg">View Safety Map</a>
              <a href="/talk-with-forseti" class="btn btn-outline-primary btn-lg">Talk with Forseti</a>
            </div>
            
          </div>
        </div>
      </div>
    ';
  }

  /**
   * Get AI Agent Hierarchy content.
   */

}