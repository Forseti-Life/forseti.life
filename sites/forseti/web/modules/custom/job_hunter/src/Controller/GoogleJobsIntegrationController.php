<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\job_hunter\Service\GoogleJobsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Google Jobs Integration.
 * 
 * Provides UI and API endpoints for integrating job postings with Google for Jobs
 * via Schema.org JobPosting structured data.
 */
class GoogleJobsIntegrationController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Google Jobs service.
   *
   * @var \Drupal\job_hunter\Service\GoogleJobsService
   */
  protected $googleJobsService;

  /**
   * Constructs a GoogleJobsIntegrationController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\job_hunter\Service\GoogleJobsService $google_jobs_service
   *   The Google Jobs service.
   */
  public function __construct(Connection $database, GoogleJobsService $google_jobs_service) {
    $this->database = $database;
    $this->googleJobsService = $google_jobs_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('job_hunter.google_jobs_service')
    );
  }

  /**
   * Google Jobs Integration home page.
   *
   * @return array
   *   Render array for the page.
   */
  public function home() {
    // Get statistics
    $stats = $this->getIntegrationStatistics();
    
    // Get recent job postings with sync status
    $recent_jobs = $this->getRecentJobsWithSyncStatus(10);
    
    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();
    
    // Build content
    $content = [
      '#theme' => 'google_jobs_integration_home',
      '#stats' => $stats,
      '#recent_jobs' => $recent_jobs,
      '#documentation_url' => Url::fromRoute('job_hunter.documentation.google_jobs')->toString(),
      '#attached' => [
        'library' => [
          'job_hunter/google_jobs_integration',
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
   * Get integration statistics.
   *
   * @return array
   *   Statistics array.
   */
  protected function getIntegrationStatistics() {
    // Total job postings
    $total_jobs = $this->database->select('job_hunter_job_requirements', 'j')
      ->countQuery()
      ->execute()
      ->fetchField();
    
    // Jobs with Google integration enabled
    $enabled_count = $this->database->select('job_hunter_google_jobs_sync', 'g')
      ->condition('is_enabled', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    // Valid jobs
    $valid_count = $this->database->select('job_hunter_google_jobs_sync', 'g')
      ->condition('validation_status', 'valid')
      ->condition('is_enabled', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    // Invalid jobs
    $invalid_count = $this->database->select('job_hunter_google_jobs_sync', 'g')
      ->condition('validation_status', 'invalid')
      ->condition('is_enabled', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    // Indexed by Google
    $indexed_count = $this->database->select('job_hunter_google_jobs_sync', 'g')
      ->condition('google_indexing_status', 'indexed')
      ->condition('is_enabled', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    // Total impressions and clicks via SQL SUM.
    $aggregate = $this->database->query(
      'SELECT COALESCE(SUM(impressions_count), 0) AS total_impressions, COALESCE(SUM(clicks_count), 0) AS total_clicks FROM {job_hunter_google_jobs_sync}'
    )->fetchObject();

    $impressions = (int) $aggregate->total_impressions;
    $clicks = (int) $aggregate->total_clicks;
    
    $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;
    
    return [
      'total_jobs' => $total_jobs,
      'enabled_count' => $enabled_count,
      'valid_count' => $valid_count,
      'invalid_count' => $invalid_count,
      'indexed_count' => $indexed_count,
      'total_impressions' => $impressions,
      'total_clicks' => $clicks,
      'ctr' => $ctr,
    ];
  }

  /**
   * Get recent job postings with sync status.
   *
   * @param int $limit
   *   Number of jobs to return.
   *
   * @return array
   *   Array of job data.
   */
  protected function getRecentJobsWithSyncStatus($limit = 10) {
    $query = $this->database->select('job_hunter_job_requirements', 'j');
    $query->leftJoin('job_hunter_companies', 'c', 'j.company_id = c.id');
    $query->leftJoin('job_hunter_google_jobs_sync', 'g', 'j.id = g.job_id');
    
    $query->fields('j', ['id', 'job_title', 'created_at'])
      ->fields('c', ['company_name'])
      ->fields('g', [
        'is_enabled',
        'validation_status',
        'last_validated',
        'google_indexing_status',
        'impressions_count',
        'clicks_count',
      ])
      ->orderBy('j.created_at', 'DESC')
      ->range(0, $limit);
    
    $results = $query->execute()->fetchAll();
    
    $jobs = [];
    foreach ($results as $row) {
      $jobs[] = [
        'id' => $row->id,
        'title' => $row->job_title,
        'company' => $row->company_name,
        'created' => $row->created_at,
        'enabled' => $row->is_enabled ?? 0,
        'validation_status' => $row->validation_status ?? 'pending',
        'last_validated' => $row->last_validated,
        'indexing_status' => $row->google_indexing_status ?? 'unknown',
        'impressions' => $row->impressions_count ?? 0,
        'clicks' => $row->clicks_count ?? 0,
        'view_url' => Url::fromRoute('job_hunter.google_jobs_job_detail', ['job_id' => $row->id])->toString(),
      ];
    }
    
    return $jobs;
  }

  /**
   * Job detail page for Google Jobs integration.
   *
   * @param int $job_id
   *   The job ID.
   *
   * @return array
   *   Render array.
   */
  public function jobDetail($job_id) {
    // Get job data
    $job = $this->database->select('job_hunter_job_requirements', 'j')
      ->fields('j')
      ->condition('id', $job_id)
      ->execute()
      ->fetchObject();
    
    if (!$job) {
      $this->messenger()->addError($this->t('Job not found.'));
      return $this->redirect('job_hunter.google_jobs_home');
    }
    
    // Get company
    $company = $this->database->select('job_hunter_companies', 'c')
      ->fields('c')
      ->condition('id', $job->company_id)
      ->execute()
      ->fetchObject();
    
    // Get sync status
    $sync = $this->database->select('job_hunter_google_jobs_sync', 'g')
      ->fields('g')
      ->condition('job_id', $job_id)
      ->execute()
      ->fetchObject();
    
    // Get validation history
    $validation_log = $this->database->select('job_hunter_google_jobs_validation_log', 'v')
      ->fields('v')
      ->condition('job_id', $job_id)
      ->orderBy('created', 'DESC')
      ->range(0, 10)
      ->execute()
      ->fetchAll();
    
    // Pre-decode JSON fields for Twig (json_decode filter doesn't exist in Drupal).
    foreach ($validation_log as $log) {
      $log->errors_decoded = !empty($log->errors) ? json_decode($log->errors, TRUE) : [];
      $log->warnings_decoded = !empty($log->warnings) ? json_decode($log->warnings, TRUE) : [];
    }

    // Pre-decode sync validation errors for Twig.
    $sync_validation_errors = [];
    if ($sync && !empty($sync->validation_errors)) {
      $sync_validation_errors = json_decode($sync->validation_errors, TRUE) ?: [];
    }

    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();

    // Build content
    $content = [
      '#theme' => 'google_jobs_job_detail',
      '#job' => $job,
      '#company' => $company,
      '#sync' => $sync,
      '#validation_log' => $validation_log,
      '#sync_validation_errors' => $sync_validation_errors,
      '#attached' => [
        'library' => [
          'job_hunter/google_jobs_integration',
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
   * AJAX: Enable/disable Google Jobs integration for a job.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function toggleJobSync(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $job_id = $data['job_id'] ?? NULL;
    $enabled = $data['enabled'] ?? 1;
    
    if (!$job_id) {
      return new JsonResponse(['error' => 'Missing job_id'], 400);
    }
    
    // Check if sync record exists
    $exists = $this->database->select('job_hunter_google_jobs_sync', 'g')
      ->condition('job_id', $job_id)
      ->countQuery()
      ->execute()
      ->fetchField();
    
    if ($exists) {
      // Update
      $this->database->update('job_hunter_google_jobs_sync')
        ->fields([
          'is_enabled' => $enabled ? 1 : 0,
          'updated' => time(),
        ])
        ->condition('job_id', $job_id)
        ->execute();
    }
    else {
      // Insert
      $this->database->insert('job_hunter_google_jobs_sync')
        ->fields([
          'job_id' => $job_id,
          'is_enabled' => $enabled ? 1 : 0,
          'validation_status' => 'pending',
          'created' => time(),
          'updated' => time(),
        ])
        ->execute();
    }
    
    return new JsonResponse([
      'success' => TRUE,
      'message' => $enabled ? 'Google Jobs integration enabled' : 'Google Jobs integration disabled',
    ]);
  }

  /**
   * AJAX: Generate structured data for a job.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with structured data.
   */
  public function generateStructuredData(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $job_id = $data['job_id'] ?? NULL;
    
    if (!$job_id) {
      return new JsonResponse(['error' => 'Missing job_id'], 400);
    }
    
    try {
      $structured_data = $this->googleJobsService->generateJobPostingJsonLd($job_id);
      
      // Save to sync table
      $this->database->merge('job_hunter_google_jobs_sync')
        ->key(['job_id' => $job_id])
        ->fields([
          'structured_data_json' => json_encode($structured_data, JSON_PRETTY_PRINT),
          'updated' => time(),
        ])
        ->insertFields([
          'created' => time(),
          'is_enabled' => 1,
          'validation_status' => 'pending',
        ])
        ->execute();
      
      return new JsonResponse([
        'success' => TRUE,
        'structured_data' => $structured_data,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX: Validate structured data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with validation results.
   */
  public function validateStructuredData(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $job_id = $data['job_id'] ?? NULL;
    
    if (!$job_id) {
      return new JsonResponse(['error' => 'Missing job_id'], 400);
    }
    
    try {
      $validation_result = $this->googleJobsService->validateJobPosting($job_id);
      
      // Get sync ID
      $sync_id = $this->database->select('job_hunter_google_jobs_sync', 'g')
        ->fields('g', ['id'])
        ->condition('job_id', $job_id)
        ->execute()
        ->fetchField();
      
      if ($sync_id) {
        // Update sync record
        $this->database->update('job_hunter_google_jobs_sync')
          ->fields([
            'validation_status' => $validation_result['status'],
            'validation_errors' => json_encode($validation_result['errors'] ?? []),
            'last_validated' => time(),
            'updated' => time(),
          ])
          ->condition('id', $sync_id)
          ->execute();
        
        // Log validation attempt
        $this->database->insert('job_hunter_google_jobs_validation_log')
          ->fields([
            'sync_id' => $sync_id,
            'job_id' => $job_id,
            'validation_type' => 'schema',
            'status' => $validation_result['status'],
            'errors' => json_encode($validation_result['errors'] ?? []),
            'warnings' => json_encode($validation_result['warnings'] ?? []),
            'created' => time(),
          ])
          ->execute();
      }
      
      return new JsonResponse($validation_result);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX: Get list of all jobs with sync status.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with jobs list.
   */
  public function getJobsList() {
    $jobs = $this->getRecentJobsWithSyncStatus(100);
    return new JsonResponse(['jobs' => $jobs]);
  }

}