<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\job_hunter\Service\CloudTalentSolutionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Google Jobs search via Cloud Talent Solution API.
 */
class GoogleJobsSearchController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Cloud Talent Solution service.
   *
   * @var \Drupal\job_hunter\Service\CloudTalentSolutionService
   */
  protected $cloudTalentService;

  /**
   * Constructs a GoogleJobsSearchController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\job_hunter\Service\CloudTalentSolutionService $cloud_talent_service
   *   The Cloud Talent Solution service.
   */
  public function __construct(Connection $database, CloudTalentSolutionService $cloud_talent_service) {
    $this->database = $database;
    $this->cloudTalentService = $cloud_talent_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('job_hunter.cloud_talent_solution')
    );
  }

  /**
   * Google Jobs search page.
   *
   * @return array
   *   Render array for the page.
   */
  public function searchPage() {
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    $content = [
      '#theme' => 'google_jobs_search',
      '#attached' => [
        'library' => [
          'job_hunter/google_jobs_search',
        ],
      ],
    ];
    
    // Wrap with navigation
    $build = [
      '#theme' => 'job_application_dashboard_wrapper',
      '#navigation' => $navigation_block,
      '#content' => $content,
    ];
    
    return $build;
  }

  /**
   * API endpoint for searching Google Jobs.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with search results.
   */
  public function apiSearch(Request $request) {
    try {
      // Get search parameters from request
      $query = $request->query->get('q', '');
      $location = $request->query->get('location', '');
      $page_token = $request->query->get('page_token', '');
      $page_size = $request->query->get('page_size', 10);
      $employment_types = $request->query->get('employment_types', '');

      if (empty($query)) {
        return new JsonResponse([
          'error' => 'Search query is required',
        ], 400);
      }

      // Perform search via Cloud Talent Solution
      $params = [
        'query' => $query,
        'location' => $location,
        'page_size' => $page_size,
      ];

      if (!empty($page_token)) {
        $params['page_token'] = $page_token;
      }

      if (!empty($employment_types)) {
        $params['employment_types'] = explode(',', $employment_types);
      }

      $results = $this->cloudTalentService->searchJobs($params);

      // Check which jobs are already imported
      $job_names = array_column(array_column($results['jobs'], 'job'), 'name');
      $imported_jobs = [];
      
      if (!empty($job_names)) {
        $imported = $this->database->select('jobhunter_job_requirements', 'j')
          ->fields('j', ['external_job_id', 'id'])
          ->condition('external_source', 'cloud_talent_solution')
          ->condition('external_job_id', $job_names, 'IN')
          ->execute()
          ->fetchAllKeyed();
        
        $imported_jobs = array_keys($imported);
      }

      // Add imported flag to results
      foreach ($results['jobs'] as &$job_match) {
        $job_name = $job_match['job']['name'] ?? '';
        $job_match['is_imported'] = in_array($job_name, $imported_jobs);
      }

      return new JsonResponse([
        'success' => TRUE,
        'data' => $results,
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Google Jobs search failed: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * API endpoint for importing a job from Google Jobs.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with import result.
   */
  public function apiImport(Request $request) {
    try {
      // Get job data from POST body
      $content = json_decode($request->getContent(), TRUE);
      
      if (empty($content['job_data'])) {
        return new JsonResponse([
          'error' => 'Job data is required',
        ], 400);
      }

      $job_data = $content['job_data'];
      $user_id = \Drupal::currentUser()->id();

      // Import the job
      $job_id = $this->cloudTalentService->importJob($job_data, $user_id);

      if ($job_id) {
        return new JsonResponse([
          'success' => TRUE,
          'job_id' => $job_id,
          'message' => 'Job imported successfully',
        ]);
      }
      else {
        return new JsonResponse([
          'error' => 'Failed to import job',
        ], 500);
      }

    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Job import failed: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * API endpoint for batch importing jobs.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with batch import results.
   */
  public function apiBatchImport(Request $request) {
    try {
      $content = json_decode($request->getContent(), TRUE);
      
      if (empty($content['jobs']) || !is_array($content['jobs'])) {
        return new JsonResponse([
          'error' => 'Jobs array is required',
        ], 400);
      }

      $user_id = \Drupal::currentUser()->id();
      $imported = [];
      $skipped = [];
      $errors = [];

      foreach ($content['jobs'] as $job_data) {
        try {
          $job_id = $this->cloudTalentService->importJob($job_data, $user_id);
          
          if ($job_id) {
            $imported[] = [
              'job_id' => $job_id,
              'title' => $job_data['title'],
            ];
          }
          else {
            $skipped[] = $job_data['title'];
          }
        }
        catch (\Exception $e) {
          $errors[] = [
            'title' => $job_data['title'] ?? 'Unknown',
            'error' => $e->getMessage(),
          ];
        }
      }

      return new JsonResponse([
        'success' => TRUE,
        'imported_count' => count($imported),
        'skipped_count' => count($skipped),
        'error_count' => count($errors),
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Batch import failed: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * API endpoint for getting job details.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $job_name
   *   The Cloud Talent Solution job resource name.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with job details.
   */
  public function apiGetJobDetails(Request $request, $job_name) {
    try {
      $details = $this->cloudTalentService->getJob($job_name);

      return new JsonResponse([
        'success' => TRUE,
        'data' => $details,
      ]);

    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Get job details failed: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

}
