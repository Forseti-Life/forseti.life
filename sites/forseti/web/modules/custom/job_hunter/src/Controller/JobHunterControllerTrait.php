<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Url;

/**
 * Trait for Job Hunter controllers to ensure consistent navigation.
 *
 * This trait provides a standardized method to wrap content with the
 * Job Hunter navigation sidebar, ensuring all pages have consistent
 * navigation regardless of how the controller returns content.
 *
 * Usage:
 * @code
 * class MyController extends ControllerBase {
 *   use JobHunterControllerTrait;
 *
 *   public function myPage() {
 *     $content = [
 *       '#markup' => '<p>My content</p>',
 *     ];
 *     return $this->wrapWithNavigation($content);
 *   }
 * }
 * @endcode
 */
trait JobHunterControllerTrait {

  /**
   * Wraps content with Job Hunter navigation sidebar.
   *
   * This is the SINGLE SOURCE OF TRUTH for how Job Hunter pages
   * should be rendered with navigation. All controller methods that
   * return page content should use this method.
   *
   * @param array $content
   *   The render array for the page content.
   * @param array $additional_libraries
   *   Optional additional libraries to attach (beyond the default navigation libraries).
   *
   * @return array
   *   A render array with navigation wrapper.
   */
  protected function wrapWithNavigation(array $content, array $additional_libraries = []): array {
    // Render navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();

    // Default libraries that should be on every Job Hunter page
    $default_libraries = [
      'job_hunter/job-hunter-navigation',
      'job_hunter/job-hunter-home',
    ];

    // Merge with any additional libraries
    $libraries = array_merge($default_libraries, $additional_libraries);

    return [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
      '#attached' => [
        'library' => $libraries,
      ],
    ];
  }

}
