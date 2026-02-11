<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for documentation pages.
 */
class DocumentationController extends ControllerBase {
  use JobHunterControllerTrait;

  /**
   * Documentation home page.
   *
   * @return array
   *   A render array for the documentation index.
   */
  public function index() {
    // Documentation files with descriptions and route names
    $docs = [
      [
        'title' => 'Documentation Home',
        'route' => 'job_hunter.documentation.readme',
        'description' => 'Complete documentation index and quick start guide',
        'icon' => '📚',
      ],
      [
        'title' => 'Architecture',
        'route' => 'job_hunter.documentation.architecture',
        'description' => 'Technical architecture, data model, service layer, and system design',
        'icon' => '🏗️',
      ],
      [
        'title' => 'Process Flows',
        'route' => 'job_hunter.documentation.process_flow',
        'description' => 'Detailed workflows, sequence diagrams, and user journeys',
        'icon' => '🔄',
      ],
      [
        'title' => 'FAQ',
        'route' => 'job_hunter.documentation.faq',
        'description' => 'Frequently asked questions, troubleshooting, and common issues',
        'icon' => '❓',
      ],
    ];
    
    $doc_links = [];
    foreach ($docs as $doc) {
      $doc_links[] = [
        '#title' => $doc['title'],
        '#description' => $doc['description'],
        '#icon' => $doc['icon'],
        '#url' => Url::fromRoute($doc['route'])->toString(),
      ];
    }
    
    $content = [
      '#theme' => 'documentation_home',
      '#doc_links' => $doc_links,
      '#attached' => [
        'library' => [
          'job_hunter/documentation',
          'job_hunter/job-hunter-home',
        ],
      ],
    ];
    
    return $this->wrapWithNavigation($content, ['job_hunter/documentation']);
  }

  /**
   * View a specific documentation file.
   *
   * @param string $file
   *   The filename to display.
   *
   * @return array
   *   A render array for the documentation content.
   */
  public function viewDocument($file = 'README.md') {
    $module_path = \Drupal::service('extension.list.module')->getPath('job_hunter');
    $file_path = DRUPAL_ROOT . '/' . $module_path . '/docs/' . $file;
    
    // Check if file exists
    if (!file_exists($file_path)) {
      \Drupal::messenger()->addError($this->t('Documentation file not found: @file', ['@file' => $file]));
      return [
        '#markup' => '<p>' . $this->t('The requested documentation file could not be found.') . '</p>',
      ];
    }
    
    // Read the markdown file
    $markdown_content = file_get_contents($file_path);
    
    // Convert markdown to HTML (basic conversion)
    // For a more robust solution, consider using a library like league/commonmark
    $html_content = $this->convertMarkdownToHtml($markdown_content);
    
    // Get module version info
    $module_info = \Drupal::service('extension.list.module')->getExtensionInfo('job_hunter');
    $version = $module_info['version'] ?? '1.0.0';
    
    // Get deployment timestamp (use state API to track)
    $state = \Drupal::state();
    $deployed_at = $state->get('job_hunter.deployed_at', 'Unknown');
    $deploy_timestamp = is_numeric($deployed_at) ? date('Y-m-d H:i:s T', $deployed_at) : $deployed_at;
    $deploy_date = is_numeric($deployed_at) ? date('M j, Y', $deployed_at) : 'Unknown';
    
    // Build collapsible version accordion
    $unique_id = 'version-info-' . uniqid();
    $version_accordion = '
      <div class="accordion mb-3" id="versionAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingVersion">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $unique_id . '" aria-expanded="false" aria-controls="' . $unique_id . '">
              <span style="font-weight: 600;">📦 Version ' . htmlspecialchars($version) . '</span>
              <span style="margin-left: 20px; color: #666;">🚀 Deployed: ' . htmlspecialchars($deploy_date) . '</span>
            </button>
          </h2>
          <div id="' . $unique_id . '" class="accordion-collapse collapse" aria-labelledby="headingVersion" data-bs-parent="#versionAccordion">
            <div class="accordion-body">
              <table class="table table-sm table-borderless mb-0">
                <tbody>
                  <tr>
                    <td style="width: 140px;"><strong>📦 Version:</strong></td>
                    <td>' . htmlspecialchars($version) . '</td>
                  </tr>
                  <tr>
                    <td><strong>🚀 Deployed:</strong></td>
                    <td>' . htmlspecialchars($deploy_timestamp) . '</td>
                  </tr>
                  <tr>
                    <td><strong>📍 Environment:</strong></td>
                    <td>' . (getenv('ENVIRONMENT') ?: 'Production') . '</td>
                  </tr>
                  <tr>
                    <td><strong>🔧 Module:</strong></td>
                    <td>Job Hunter (job_hunter)</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>';
    
    $content = [
      '#type' => 'container',
      '#attributes' => ['class' => ['documentation-content']],
      'version_accordion' => [
        '#markup' => $version_accordion,
      ],
      'breadcrumb' => [
        '#markup' => '<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="' . Url::fromRoute('job_hunter.documentation')->toString() . '">Documentation</a></li><li class="breadcrumb-item active" aria-current="page">' . basename($file, '.md') . '</li></ol></nav>',
      ],
      'content' => [
        '#markup' => $html_content,
      ],
      '#attached' => [
        'library' => [
          'job_hunter/documentation',
        ],
      ],
    ];
    
    return $this->wrapWithNavigation($content, ['job_hunter/documentation']);
  }

  /**
   * Simple markdown to HTML converter.
   *
   * @param string $markdown
   *   The markdown content.
   *
   * @return string
   *   The HTML content.
   */
  private function convertMarkdownToHtml($markdown) {
    // Basic markdown conversion
    $html = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
    
    // Headers
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Bold and italic
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    
    // Links
    $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);
    
    // Code blocks
    $html = preg_replace('/```([a-z]*)\n(.+?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
    $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
    
    // Lists
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
    $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);
    
    // Paragraphs
    $html = preg_replace('/\n\n/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // Clean up empty paragraphs and fix HTML tag issues
    $html = preg_replace('/<p>\s*<\/p>/', '', $html);
    $html = preg_replace('/<p>\s*(<h[1-6]>)/', '$1', $html);
    $html = preg_replace('/(<\/h[1-6]>)\s*<\/p>/', '$1', $html);
    $html = preg_replace('/<p>\s*(<ul>)/', '$1', $html);
    $html = preg_replace('/(<\/ul>)\s*<\/p>/', '$1', $html);
    $html = preg_replace('/<p>\s*(<pre>)/', '$1', $html);
    $html = preg_replace('/(<\/pre>)\s*<\/p>/', '$1', $html);
    
    return $html;
  }

}
