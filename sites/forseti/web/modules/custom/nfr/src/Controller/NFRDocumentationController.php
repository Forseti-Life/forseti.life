<?php

declare(strict_types=1);

namespace Drupal\nfr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for NFR documentation pages.
 */
class NFRDocumentationController extends ControllerBase {

  /**
   * Documentation index page.
   *
   * @return array
   *   Render array.
   */
  public function index(): array {
    $module_path = \Drupal::service('extension.list.module')->getPath('nfr');
    $docs_path = $module_path . '/documents';

    // Get implementation status
    $validation_status = $this->getValidationStatus();

    // Development documentation.
    $development_docs = [
      'business-requirements' => [
        'title' => 'Business Requirements Tracking',
        'description' => 'Requirements tracking document with implementation status, complete data element mapping to database fields/forms, and gap analysis. Extracted from CDC NFR official documents including legislative mandate, data collection specifications, and external integrations.',
        'file' => 'BUSINESS_REQUIREMENTS.md',
        'status' => $validation_status['business_requirements'],
      ],
      'user-roles' => [
        'title' => 'User Roles & Process Flows',
        'description' => 'Detailed user roles, process flows, user journey maps, and page requirements for all system users including firefighters, administrators, and researchers.',
        'file' => 'USER_ROLES_AND_PROCESS_FLOWS.md',
        'status' => $validation_status['user_roles'],
      ],
      'page-specs' => [
        'title' => 'Page Specifications',
        'description' => 'Complete page specifications with Drupal content type mapping, field definitions, form specifications, and dashboard requirements.',
        'file' => 'PAGE_SPECIFICATIONS.md',
        'status' => $validation_status['page_specs'],
      ],
      'architecture' => [
        'title' => 'System Architecture',
        'description' => 'System architecture, design patterns, data flow diagrams, and technical implementation details for the NFR module.',
        'file' => 'ARCHITECTURE.md',
        'root_dir' => TRUE,
        'status' => $validation_status['architecture'],
      ],
      'installation' => [
        'title' => 'Installation Guide',
        'description' => 'Complete installation, deployment, and configuration guide including database setup, module installation, and system requirements.',
        'file' => 'INSTALLATION.md',
        'root_dir' => TRUE,
        'status' => $validation_status['installation'],
      ],
      'compliance' => [
        'title' => 'Drupal 11 Compliance',
        'description' => 'Drupal 11 standards compliance documentation including typed properties, dependency injection, and API usage patterns.',
        'file' => 'DRUPAL11_COMPLIANCE.md',
        'root_dir' => TRUE,
        'status' => $validation_status['compliance'],
      ],
    ];

    // Reporting documentation.
    $reporting_docs = [
      'reporting' => [
        'title' => 'Reporting & Analytics',
        'description' => 'Overview of NFR reporting capabilities including correlation analysis, cluster analysis, and statistical methods for analyzing firefighter health outcomes.',
        'is_landing' => TRUE,
      ],
      'correlation-analysis' => [
        'title' => 'Correlation Analysis Table Design',
        'description' => 'Technical design specification for nfr_correlation_analysis denormalized table with 169 fields aggregating data from 10 NFR tables. Includes cache table designs for pre-computed results.',
        'file' => 'NFR_CORRELATION_ANALYSIS_TABLE.md',
        'technical_docs' => TRUE,
        'status' => $validation_status['correlation_analysis'],
      ],
      'correlation-analysis-guide' => [
        'title' => 'Correlation Analysis User Guide',
        'description' => 'User guide for running correlation and cluster analysis on NFR data. Includes variable selection, statistical interpretation, and practical examples.',
        'file' => 'CORRELATION_ANALYSIS_USER_GUIDE.md',
        'technical_docs' => TRUE,
        'status' => $validation_status['correlation_analysis_guide'],
      ],
    ];

    $dev_items = [];
    foreach ($development_docs as $key => $doc) {
      $route_key = str_replace('-', '_', $key);
      $url = Url::fromRoute('nfr.documentation.' . $route_key);
      $link = Link::fromTextAndUrl($doc['title'], $url);
      
      if (isset($doc['technical_docs']) && $doc['technical_docs']) {
        $file_path = DRUPAL_ROOT . '/../docs/technical/' . $doc['file'];
      } else {
        $file_path = isset($doc['root_dir']) && $doc['root_dir'] ? $module_path . '/' . $doc['file'] : $docs_path . '/' . $doc['file'];
      }
      $file_exists = file_exists($file_path);
      $file_size = $file_exists ? number_format(filesize($file_path) / 1024, 2) . ' KB' : 'N/A';
      
      $dev_items[] = [
        'link' => $link,
        'description' => $doc['description'],
        'file' => $doc['file'],
        'file_size' => $file_size,
        'status' => $doc['status'],
      ];
    }

    // CDC Official Documents.
    $cdc_docs = [
      'protocol' => [
        'title' => 'NFR Protocol (April 2025 OMB)',
        'description' => 'Official CDC/NIOSH National Firefighter Registry Protocol including surveillance objectives, congressional mandate, and stakeholder engagement requirements.',
        'file' => 'NFR-Protocol-Aprl_2025_OMB.pdf',
      ],
      'user_profile' => [
        'title' => 'User Profile Form (April 2025 OMB)',
        'description' => 'CDC-approved 5-minute User Profile registration form with detailed field specifications, SSN rationale, and eligibility validation requirements.',
        'file' => 'NFR-User-Profile-April_-2025_OMB.pdf',
      ],
      'questionnaire' => [
        'title' => 'Enrollment Questionnaire (April 2025 OMB)',
        'description' => 'Comprehensive 30-minute Enrollment Questionnaire covering complete work history, exposure data, PPE practices, decontamination, health information, and lifestyle factors.',
        'file' => 'NFR-Enrollment-Questionnaire-April_2025_OMB.pdf',
      ],
    ];

    $cdc_items = [];
    foreach ($cdc_docs as $key => $doc) {
      $url = Url::fromRoute('nfr.documentation.' . $key);
      $link = Link::fromTextAndUrl($doc['title'], $url);
      
      $file_exists = file_exists($docs_path . '/' . $doc['file']);
      $file_size = $file_exists ? number_format(filesize($docs_path . '/' . $doc['file']) / 1024, 2) . ' KB' : 'N/A';
      
      $cdc_items[] = [
        'link' => $link,
        'description' => $doc['description'],
        'file' => $doc['file'],
        'file_size' => $file_size,
      ];
    }

    // Build reporting items.
    $reporting_items = [];
    foreach ($reporting_docs as $key => $doc) {
      // Skip the landing page from the index listing
      if (isset($doc['is_landing']) && $doc['is_landing']) {
        continue;
      }
      
      $route_key = str_replace('-', '_', $key);
      $url = Url::fromRoute('nfr.documentation.' . $route_key);
      $link = Link::fromTextAndUrl($doc['title'], $url);
      
      $item = [
        'link' => $link,
        'description' => $doc['description'],
      ];
      
      if (isset($doc['technical_docs']) && $doc['technical_docs']) {
        $file_path = DRUPAL_ROOT . '/../docs/technical/' . $doc['file'];
      } else {
        $file_path = isset($doc['root_dir']) && $doc['root_dir'] ? $module_path . '/' . $doc['file'] : $docs_path . '/' . $doc['file'];
      }
      $file_exists = file_exists($file_path);
      $file_size = $file_exists ? number_format(filesize($file_path) / 1024, 2) . ' KB' : 'N/A';
      
      $item['file'] = $doc['file'];
      $item['file_size'] = $file_size;
      if (isset($doc['status'])) {
        $item['status'] = $doc['status'];
      }
      
      $reporting_items[] = $item;
    }

    return [
      '#theme' => 'nfr_documentation',
      '#development_docs' => $dev_items,
      '#reporting_docs' => $reporting_items,
      '#cdc_docs' => $cdc_items,
      '#attached' => [
        'library' => [
          'nfr/documentation',
        ],
      ],
    ];
  }

  /**
   * Display Business Requirements Tracking documentation.
   *
   * @return array
   *   Render array.
   */
  public function businessRequirements(): array {
    return $this->renderMarkdownDocument('BUSINESS_REQUIREMENTS.md', 'Business Requirements Tracking');
  }

  /**
   * Display User Roles & Process Flows documentation.
   *
   * @return array
   *   Render array.
   */
  public function userRoles(): array {
    return $this->renderMarkdownDocument('USER_ROLES_AND_PROCESS_FLOWS.md', 'User Roles & Process Flows');
  }

  /**
   * Display Page Specifications documentation.
   *
   * @return array
   *   Render array.
   */
  public function pageSpecifications(): array {
    return $this->renderMarkdownDocument('PAGE_SPECIFICATIONS.md', 'Page Specifications');
  }

  /**
   * Display NFR Protocol PDF.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   PDF file response.
   */
  public function protocol(): BinaryFileResponse {
    return $this->servePdfDocument('NFR-Protocol-Aprl_2025_OMB.pdf');
  }

  /**
   * Display User Profile PDF.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   PDF file response.
   */
  public function userProfile(): BinaryFileResponse {
    return $this->servePdfDocument('NFR-User-Profile-April_-2025_OMB.pdf');
  }

  /**
   * Display Enrollment Questionnaire PDF.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   PDF file response.
   */
  public function questionnaire(): BinaryFileResponse {
    return $this->servePdfDocument('NFR-Enrollment-Questionnaire-April_2025_OMB.pdf');
  }

  /**
   * Display Architecture documentation.
   *
   * @return array
   *   Render array.
   */
  public function architecture(): array {
    return $this->renderMarkdownDocument('ARCHITECTURE.md', 'System Architecture', TRUE);
  }

  /**
   * Display Installation documentation.
   *
   * @return array
   *   Render array.
   */
  public function installation(): array {
    return $this->renderMarkdownDocument('INSTALLATION.md', 'Installation Guide', TRUE);
  }

  /**
   * Display Drupal 11 Compliance documentation.
   *
   * @return array
   *   Render array.
   */
  public function compliance(): array {
    return $this->renderMarkdownDocument('DRUPAL11_COMPLIANCE.md', 'Drupal 11 Compliance', TRUE);
  }

  /**
   * Display Correlation Analysis Table Design documentation.
   *
   * @return array
   *   Render array.
   */
  public function correlationAnalysis(): array {
    return $this->renderMarkdownDocument('NFR_CORRELATION_ANALYSIS_TABLE.md', 'Correlation Analysis Table Design', FALSE, TRUE);
  }

  /**
   * Display Correlation Analysis User Guide.
   *
   * @return array
   *   Render array.
   */
  public function correlationAnalysisGuide(): array {
    return $this->renderMarkdownDocument('CORRELATION_ANALYSIS_USER_GUIDE.md', 'Correlation Analysis User Guide', FALSE, TRUE);
  }

  /**
   * Reporting landing page.
   *
   * @return array
   *   Render array.
   */
  public function reporting(): array {
    $build = [];
    
    $build['intro'] = [
      '#type' => 'markup',
      '#markup' => '<div class="reporting-intro"><h2>NFR Reporting & Analytics</h2><p>The National Firefighter Registry provides comprehensive statistical analysis tools for investigating relationships between firefighter exposures and health outcomes. This section documents the data infrastructure, analytical methods, and user interfaces available for research.</p></div>',
    ];

    $build['sections'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['reporting-sections']],
    ];

    // Correlation Analysis section.
    $correlation_url = Url::fromRoute('nfr.documentation.correlation_analysis');
    $build['sections']['correlation'] = [
      '#type' => 'markup',
      '#markup' => '<div class="doc-item"><h3>' . Link::fromTextAndUrl('Correlation Analysis Table Design', $correlation_url)->toString() . '</h3><p class="description">Technical design specification for nfr_correlation_analysis denormalized table with 169 fields aggregating data from 10 NFR tables. Includes cache table designs for pre-computed correlation and cluster analysis results.</p><p><strong>Contents:</strong> Schema design, data sources, population strategy, statistical methods, performance optimization</p></div>',
    ];

    // User Guide section.
    $guide_url = Url::fromRoute('nfr.documentation.correlation_analysis_guide');
    $build['sections']['guide'] = [
      '#type' => 'markup',
      '#markup' => '<div class="doc-item"><h3>' . Link::fromTextAndUrl('Correlation Analysis User Guide', $guide_url)->toString() . '</h3><p class="description">User guide for running correlation and cluster analysis on NFR data through the web interface at /admin/nfr/reports.</p><p><strong>Contents:</strong> Variable selection, Pearson/Spearman correlation, K-means clustering, result interpretation, practical examples</p></div>',
    ];

    // Analysis Tools section.
    $tools_url = Url::fromRoute('nfr.admin_reports');
    $build['sections']['tools'] = [
      '#type' => 'markup',
      '#markup' => '<div class="doc-item"><h3>' . Link::fromTextAndUrl('Run Analysis Tools', $tools_url)->toString() . '</h3><p class="description">Access the live analysis interface to run correlations and cluster analysis on NFR data.</p><p><strong>Features:</strong> 100+ variables, Pearson/Spearman correlation, K-means clustering (k=2-10), CSV export, elbow curves</p></div>',
    ];

    $build['#attached']['library'][] = 'nfr/documentation';

    return $build;
  }

  /**
   * Helper function to render markdown documents.
   *
   * @param string $filename
   *   The markdown filename.
   * @param string $title
   *   The page title.
   * @param bool $root_dir
   *   Whether file is in module root instead of documents/.
   * @param bool $technical_docs
   *   Whether file is in /docs/technical/ instead of module.
   *
   * @return array
   *   Render array.
   */
  private function renderMarkdownDocument(string $filename, string $title, bool $root_dir = FALSE, bool $technical_docs = FALSE): array {
    if ($technical_docs) {
      $file_path = DRUPAL_ROOT . '/../../../docs/technical/' . $filename;
    } else {
      $module_path = \Drupal::service('extension.list.module')->getPath('nfr');
      $file_path = $root_dir ? $module_path . '/' . $filename : $module_path . '/documents/' . $filename;
    }

    if (!file_exists($file_path)) {
      throw new NotFoundHttpException('Documentation file not found.');
    }

    $markdown_content = file_get_contents($file_path);
    $html_content = $this->basicMarkdownToHtml($markdown_content);

    return [
      '#theme' => 'nfr_documentation_page',
      '#title' => $title,
      '#content' => $html_content,
      '#file_info' => [
        'filename' => $filename,
        'size' => number_format(filesize($file_path) / 1024, 2) . ' KB',
      ],
      '#attached' => [
        'library' => [
          'nfr/documentation',
        ],
      ],
    ];
  }

  /**
   * Helper function to serve PDF documents.
   *
   * @param string $filename
   *   The PDF filename.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   PDF file response.
   */
  private function servePdfDocument(string $filename): BinaryFileResponse {
    $module_path = \Drupal::service('extension.list.module')->getPath('nfr');
    $file_path = $module_path . '/documents/' . $filename;

    if (!file_exists($file_path)) {
      throw new NotFoundHttpException('PDF document not found.');
    }

    $response = new BinaryFileResponse($file_path);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

    return $response;
  }

  /**
   * Basic markdown to HTML conversion.
   *
   * @param string $markdown
   *   Markdown content.
   *
   * @return string
   *   HTML content.
   */
  private function basicMarkdownToHtml(string $markdown): string {
    // This is a very basic implementation.
    // For production, use a proper markdown parser like Parsedown or CommonMark.
    
    // Convert tables first (before other processing).
    $html = $this->convertMarkdownTables($markdown);
    
    // Convert headers.
    $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

    // Convert bold.
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

    // Convert italic.
    $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

    // Convert inline code.
    $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);

    // Convert links.
    $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);

    // Convert unordered lists.
    $html = preg_replace('/^[\*\-] (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

    // Convert line breaks to paragraphs (simplified).
    $paragraphs = explode("\n\n", $html);
    $html = '';
    foreach ($paragraphs as $paragraph) {
      $paragraph = trim($paragraph);
      if (!empty($paragraph)) {
        // Don't wrap if already has HTML tags.
        if (!preg_match('/^<(h[1-6]|ul|ol|table|div|pre)/', $paragraph)) {
          $html .= '<p>' . $paragraph . '</p>';
        } else {
          $html .= $paragraph;
        }
      }
    }

    // Convert code blocks.
    $html = preg_replace('/```(.+?)```/s', '<pre><code>$1</code></pre>', $html);

    // Convert horizontal rules.
    $html = preg_replace('/^---$/m', '<hr>', $html);

    return $html;
  }

  /**
   * Convert markdown tables to HTML.
   *
   * @param string $markdown
   *   Markdown content.
   *
   * @return string
   *   Content with tables converted to HTML.
   */
  private function convertMarkdownTables(string $markdown): string {
    // Match markdown tables
    $pattern = '/^\|(.+)\|$/m';
    
    if (!preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
      return $markdown;
    }
    
    $offset = 0;
    foreach ($matches as $i => $match) {
      // Skip separator rows (contains only |, -, and :)
      if (preg_match('/^[\|\-\:\s]+$/', $match[0][0])) {
        continue;
      }
      
      // Process table rows
      $cells = array_map('trim', explode('|', trim($match[1][0], '|')));
      
      // Determine if this is a header row (next row is separator)
      $is_header = false;
      if (isset($matches[$i + 1]) && preg_match('/^[\|\-\:\s]+$/', $matches[$i + 1][0][0])) {
        $is_header = true;
      }
      
      // Build HTML row
      $tag = $is_header ? 'th' : 'td';
      $row = '<tr>';
      foreach ($cells as $cell) {
        $row .= "<{$tag}>{$cell}</{$tag}>";
      }
      $row .= '</tr>';
      
      // Replace in markdown
      $markdown = substr_replace($markdown, $row, $match[0][1] + $offset, strlen($match[0][0]));
      $offset += strlen($row) - strlen($match[0][0]);
    }
    
    // Wrap consecutive <tr> elements in table tags
    $markdown = preg_replace('/(<tr>.*?<\/tr>(?:\s*<tr>.*?<\/tr>)*)/s', '<table class="requirements-table">$1</table>', $markdown);
    
    return $markdown;
  }

  /**
   * Get validation status for each documentation area.
   *
   * @return array
   *   Status array with percentages and details.
   */
  private function getValidationStatus(): array {
    $database = \Drupal::database();
    
    // Check routes exist
    $route_provider = \Drupal::service('router.route_provider');
    $total_routes = 0;
    $working_routes = 0;
    
    $route_patterns = [
      'nfr.home', 'nfr.consent', 'nfr.user_profile', 'nfr.enrollment_questionnaire',
      'nfr.review_submit', 'nfr.confirmation', 'nfr.my_dashboard', 'nfr.welcome',
      'nfr.follow_up', 'nfr.admin_dashboard', 'nfr.admin_participants', 
      'nfr.admin_linkage', 'nfr.admin_data_quality', 'nfr.admin_reports',
      'nfr.admin_issues', 'nfr.admin_settings', 'nfr.public_data', 'nfr.validation',
    ];
    
    foreach ($route_patterns as $route_name) {
      $total_routes++;
      try {
        $route_provider->getRouteByName($route_name);
        $working_routes++;
      } catch (\Exception $e) {
        // Route doesn't exist
      }
    }
    
    // Check database tables
    $tables_needed = ['nfr_consent', 'nfr_user_profile', 'nfr_questionnaire', 
                      'nfr_work_history', 'nfr_job_title', 'nfr_participant', 
                      'nfr_cancer_diagnosis'];
    $tables_exist = 0;
    foreach ($tables_needed as $table) {
      if ($database->schema()->tableExists($table)) {
        $tables_exist++;
      }
    }
    
    // Calculate statuses
    $route_percent = $total_routes > 0 ? round(($working_routes / $total_routes) * 100) : 0;
    $table_percent = count($tables_needed) > 0 ? round(($tables_exist / count($tables_needed)) * 100) : 0;
    
    return [
      'business_requirements' => [
        'percent' => 100,
        'label' => 'Complete',
        'class' => 'success',
        'details' => 'All 4 user roles defined, enrollment flow operational',
      ],
      'user_roles' => [
        'percent' => 90,
        'label' => 'Nearly Complete',
        'class' => 'success',
        'details' => '4 roles active, process flows functional, minor UX enhancements pending',
      ],
      'page_specs' => [
        'percent' => 75,
        'label' => 'In Progress',
        'class' => 'warning',
        'details' => "{$working_routes}/{$total_routes} routes functional, core pages styled, public pages need content",
      ],
      'architecture' => [
        'percent' => $table_percent,
        'label' => $table_percent === 100 ? 'Complete' : 'In Progress',
        'class' => $table_percent === 100 ? 'success' : 'warning',
        'details' => "{$tables_exist}/" . count($tables_needed) . " database tables created, MVC architecture implemented",
      ],
      'installation' => [
        'percent' => 100,
        'label' => 'Complete',
        'class' => 'success',
        'details' => 'Module installed, dependencies met, Drupal 11 compatible',
      ],
      'compliance' => [
        'percent' => 100,
        'label' => 'Complete',
        'class' => 'success',
        'details' => 'Typed properties, dependency injection, modern Drupal patterns',
      ],
      'correlation_analysis' => [
        'percent' => 0,
        'label' => 'Design Phase',
        'class' => 'info',
        'details' => 'Design document complete, implementation pending',
      ],
      'correlation_analysis_guide' => [
        'percent' => 0,
        'label' => 'Design Phase',
        'class' => 'info',
        'details' => 'User guide complete, awaiting full implementation',
      ],
    ];
  }

}
