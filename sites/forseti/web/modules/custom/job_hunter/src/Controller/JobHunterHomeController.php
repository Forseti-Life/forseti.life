<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Job Hunter home page.
 */
class JobHunterHomeController extends ControllerBase {

  /**
   * Queue definitions with display names and descriptions.
   */
  protected const QUEUE_DEFINITIONS = [
    'job_hunter_genai_parsing' => [
      'name' => 'Resume AI Parsing',
      'description' => 'Extracts structured data from uploaded resumes using Claude AI',
      'icon' => '📄',
    ],
    'job_hunter_job_posting_parsing' => [
      'name' => 'Job Posting AI Parsing',
      'description' => 'Extracts job requirements, skills, and company info from job postings',
      'icon' => '📋',
    ],
    'job_hunter_resume_tailoring' => [
      'name' => 'Resume Tailoring',
      'description' => 'Generates tailored resumes matching job requirements',
      'icon' => '✨',
    ],
    'job_hunter_text_extraction' => [
      'name' => 'Resume Text Extraction',
      'description' => 'Extracts raw text from PDF/DOCX resume files',
      'icon' => '📝',
    ],
    'job_hunter_profile_text_extraction' => [
      'name' => 'Profile Text Extraction',
      'description' => 'Extracts text from profile attachments',
      'icon' => '👤',
    ],
  ];

  /**
   * Display the Job Hunter home page.
   *
   * @return array
   *   Render array for the home page.
   */
  public function home() {
    $current_user = $this->currentUser();
    $user_id = $current_user->id();

    // Render the navigation block
    $block_manager = \Drupal::service('plugin.manager.block');
    $plugin_block = $block_manager->createInstance('job_hunter_navigation', []);
    $navigation_block = $plugin_block->build();

    $libraries = [
      'job_hunter/job-hunter-home',
    ];

    // Add queue controls library for admin users
    if ($current_user->hasPermission('administer job application automation')) {
      $libraries[] = 'job_hunter/queue-controls';
    }

    $build = [
      '#theme' => 'job_hunter_home',
      '#attached' => [
        'library' => $libraries,
      ],
      '#navigation' => $navigation_block,
    ];

    // User profile section
    $build['#user_profile'] = [
      'view_url' => Url::fromRoute('job_hunter.user_job_seeker_view')->toString(),
      'edit_url' => Url::fromRoute('job_hunter.job_seeker_edit', ['job_seeker_id' => $user_id])->toString(),
    ];

    // Job discovery section
    $build['#job_discovery'] = [
      'start_url' => Url::fromRoute('job_hunter.start_job_discovery')->toString(),
    ];

    // Dashboard section
    $build['#dashboard'] = [
      'main_url' => Url::fromRoute('job_hunter.dashboard')->toString(),
      'companies_url' => Url::fromRoute('job_hunter.companies_overview')->toString(),
    ];

    // Statistics (if available)
    $stats = $this->getUserStatistics($user_id);
    $build['#statistics'] = $stats;

    // Queue status - visible to all authenticated users (read-only)
    $build['#queue_status'] = $this->getQueueStatus();

    return $build;
  }

  /**
   * Get status of all Job Hunter queues.
   *
   * @return array
   *   Array of queue status information.
   */
  protected function getQueueStatus(): array {
    $queue_factory = \Drupal::service('queue');
    $status = [];

    foreach (self::QUEUE_DEFINITIONS as $queue_id => $info) {
      $queue = $queue_factory->get($queue_id);
      $status[$queue_id] = [
        'id' => $queue_id,
        'name' => $info['name'],
        'description' => $info['description'],
        'icon' => $info['icon'],
        'items' => $queue->numberOfItems(),
      ];
    }

    return $status;
  }

  /**
   * AJAX endpoint to run a specific queue.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with results.
   */
  public function runQueueAjax(Request $request): JsonResponse {
    // Check permission
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    $queue_id = $request->request->get('queue_id');
    
    if (!$queue_id || !isset(self::QUEUE_DEFINITIONS[$queue_id])) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid queue ID',
      ], 400);
    }

    try {
      $processed = $this->processQueue($queue_id);
      
      return new JsonResponse([
        'success' => TRUE,
        'message' => "Processed {$processed} items from " . self::QUEUE_DEFINITIONS[$queue_id]['name'],
        'processed' => $processed,
        'queue_id' => $queue_id,
        'remaining' => \Drupal::service('queue')->get($queue_id)->numberOfItems(),
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Queue processing error: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Error: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * AJAX endpoint to run all queues.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with results.
   */
  public function runAllQueuesAjax(Request $request): JsonResponse {
    // Check permission
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    $results = [];
    $total_processed = 0;

    // Process queues in logical order
    $queue_order = [
      'job_hunter_text_extraction',
      'job_hunter_profile_text_extraction',
      'job_hunter_genai_parsing',
      'job_hunter_job_posting_parsing',
      'job_hunter_resume_tailoring',
    ];

    foreach ($queue_order as $queue_id) {
      try {
        $processed = $this->processQueue($queue_id);
        $total_processed += $processed;
        $results[$queue_id] = [
          'processed' => $processed,
          'remaining' => \Drupal::service('queue')->get($queue_id)->numberOfItems(),
        ];
      }
      catch (\Exception $e) {
        $results[$queue_id] = [
          'error' => $e->getMessage(),
        ];
      }
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => "Processed {$total_processed} total items across all queues",
      'total_processed' => $total_processed,
      'results' => $results,
    ]);
  }

  /**
   * AJAX endpoint to get current queue status.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with queue status.
   */
  public function getQueueStatusAjax(): JsonResponse {
    // Allow any authenticated user to view queue status
    if (!$this->currentUser()->isAuthenticated()) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    return new JsonResponse([
      'success' => TRUE,
      'queues' => $this->getQueueStatus(),
    ]);
  }

  /**
   * Get recent queue activity logs (AJAX endpoint).
   */
  public function getQueueLogsAjax(): JsonResponse {
    // Admin only for detailed logs
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $database = \Drupal::database();
    
    // Get last 20 queue-related log entries
    $query = $database->select('watchdog', 'w')
      ->fields('w', ['wid', 'timestamp', 'type', 'severity', 'message', 'variables'])
      ->condition('type', 'job_hunter')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 20);
    
    $results = $query->execute()->fetchAll();
    
    $logs = [];
    foreach ($results as $row) {
      $variables = unserialize($row->variables);
      $message = strtr($row->message, $variables);
      
      // Map severity to type
      $type_map = [
        0 => 'error',    // EMERGENCY
        1 => 'error',    // ALERT
        2 => 'error',    // CRITICAL
        3 => 'error',    // ERROR
        4 => 'warning',  // WARNING
        5 => 'warning',  // NOTICE
        6 => 'info',     // INFO
        7 => 'info',     // DEBUG
      ];
      
      $logs[] = [
        'timestamp' => $row->timestamp,
        'message' => $message,
        'type' => $type_map[$row->severity] ?? 'info',
      ];
    }
    
    return new JsonResponse([
      'success' => TRUE,
      'logs' => $logs,
    ]);
  }

  /**
   * Process items from a queue.
   *
   * @param string $queue_id
   *   The queue ID.
   * @param int $max_items
   *   Maximum items to process.
   *
   * @return int
   *   Number of items processed.
   */
  protected function processQueue(string $queue_id, int $max_items = 10): int {
    $queue_factory = \Drupal::service('queue');
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
    
    $queue = $queue_factory->get($queue_id);
    $worker = $queue_worker_manager->createInstance($queue_id);
    
    $processed = 0;
    
    while ($processed < $max_items && ($item = $queue->claimItem())) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        // Release item back to queue on failure
        $queue->releaseItem($item);
        \Drupal::logger('job_hunter')->error('Queue @queue item failed: @error', [
          '@queue' => $queue_id,
          '@error' => $e->getMessage(),
        ]);
        // Continue to next item
      }
    }
    
    return $processed;
  }

  /**
   * Get user statistics for display on home page.
   *
   * @param int $user_id
   *   The user ID.
   *
   * @return array
   *   Array of statistics.
   */
  protected function getUserStatistics($user_id) {
    $stats = [
      'total_applications' => 0,
      'active_applications' => 0,
      'companies_tracked' => 0,
      'jobs_saved' => 0,
    ];

    try {
      // Count job postings
      $job_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'job_posting')
        ->condition('uid', $user_id);
      $stats['jobs_saved'] = $job_query->count()->execute();

      // Count companies
      $company_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'company')
        ->condition('uid', $user_id);
      $stats['companies_tracked'] = $company_query->count()->execute();

      // Count applications (if application content type exists)
      $application_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'application')
        ->condition('uid', $user_id);
      $stats['total_applications'] = $application_query->count()->execute();

      // Count active applications (status = in_progress, applied, etc.)
      $active_query = $this->entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'application')
        ->condition('uid', $user_id)
        ->condition('status', 1);
      $stats['active_applications'] = $active_query->count()->execute();
    }
    catch (\Exception $e) {
      $this->getLogger('job_hunter')->error('Error fetching user statistics: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $stats;
  }

  /**
   * Display queue management page for admins.
   *
   * @return array
   *   Render array for the queue management page.
   */
  public function queueManagement() {
    $database = \Drupal::database();
    
    // Get all queue items with related data
    $queue_items = [];
    foreach (array_keys(self::QUEUE_DEFINITIONS) as $queue_name) {
      $query = $database->select('queue', 'q')
        ->fields('q', ['item_id', 'data', 'expire', 'created'])
        ->condition('name', $queue_name);
      $results = $query->execute()->fetchAll();
      
      foreach ($results as $row) {
        $data = unserialize($row->data);
        $queue_items[] = [
          'item_id' => $row->item_id,
          'queue_name' => $queue_name,
          'queue_label' => self::QUEUE_DEFINITIONS[$queue_name]['name'],
          'created' => $row->created,
          'expire' => $row->expire,
          'data' => $data,
          'data_preview' => $this->getQueueItemPreview($data, $queue_name),
        ];
      }
    }
    
    // Sort by created date (newest first)
    usort($queue_items, function($a, $b) {
      return $b['created'] - $a['created'];
    });
    
    // Check database table health
    $table_health = $this->checkTableHealth();
    
    return [
      '#theme' => 'job_hunter_queue_management',
      '#queue_items' => $queue_items,
      '#queue_status' => $this->getQueueStatus(),
      '#table_health' => $table_health,
      '#attached' => [
        'library' => [
          'job_hunter/queue-management',
          'job_hunter/queue-controls',
        ],
      ],
    ];
  }

  /**
   * Check health of all job_hunter database tables.
   *
   * @return array
   *   Health check results with overall status and table details.
   */
  private function checkTableHealth() {
    $schema = \Drupal::database()->schema();
    
    // Define expected tables and their critical columns
    $expected_tables = [
      'jobhunter_job_seeker' => ['id', 'uid', 'created', 'changed'],
      'jobhunter_job_history' => ['id', 'job_seeker_id', 'company', 'title'],
      'jobhunter_education_history' => ['id', 'job_seeker_id', 'institution', 'degree'],
      'jobhunter_resume_parsed_data' => ['id', 'uid', 'resume_file_id', 'parsed_data', 'status', 'raw_genai_response_core', 'raw_genai_response_experience'],
      'jobhunter_job_seeker_resumes' => ['id', 'job_seeker_id', 'file_id', 'extracted_text'],
      'jobhunter_tailored_resumes' => ['id', 'job_seeker_id', 'company', 'job_title'],
    ];
    
    $results = [];
    $all_healthy = TRUE;
    
    foreach ($expected_tables as $table_name => $required_columns) {
      $table_exists = $schema->tableExists($table_name);
      $columns_ok = TRUE;
      $missing_columns = [];
      
      if ($table_exists) {
        foreach ($required_columns as $column) {
          if (!$schema->fieldExists($table_name, $column)) {
            $columns_ok = FALSE;
            $missing_columns[] = $column;
          }
        }
      } else {
        $columns_ok = FALSE;
        $all_healthy = FALSE;
      }
      
      $is_healthy = $table_exists && $columns_ok;
      if (!$is_healthy) {
        $all_healthy = FALSE;
      }
      
      $results[$table_name] = [
        'exists' => $table_exists,
        'columns_ok' => $columns_ok,
        'missing_columns' => $missing_columns,
        'healthy' => $is_healthy,
      ];
    }
    
    return [
      'overall_healthy' => $all_healthy,
      'tables' => $results,
      'checked_at' => time(),
    ];
  }

  /**
   * Get a preview of queue item data.
   */
  private function getQueueItemPreview($data, $queue_name) {
    $preview = [];
    
    if (isset($data->uid)) {
      $user = \Drupal\user\Entity\User::load($data->uid);
      $preview['user'] = $user ? $user->getAccountName() : "User #{$data->uid}";
    }
    
    if (isset($data->fid)) {
      $file = \Drupal\file\Entity\File::load($data->fid);
      $preview['file'] = $file ? $file->getFilename() : "File #{$data->fid}";
      $preview['file_id'] = $data->fid;
    }
    
    if (isset($data->resume_file_id)) {
      $file = \Drupal\file\Entity\File::load($data->resume_file_id);
      $preview['resume_file'] = $file ? $file->getFilename() : "File #{$data->resume_file_id}";
      $preview['resume_file_id'] = $data->resume_file_id;
    }
    
    if (isset($data->company_name)) {
      $preview['company'] = $data->company_name;
    }
    
    if (isset($data->job_title)) {
      $preview['job_title'] = $data->job_title;
    }
    
    if (isset($data->extracted_text)) {
      $preview['text_length'] = strlen($data->extracted_text);
    }
    
    return $preview;
  }

  /**
   * Delete a queue item (AJAX endpoint).
   */
  public function deleteQueueItem(Request $request) {
    // Check admin permission
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    // Handle JSON request body
    $content = $request->getContent();
    if ($content) {
      $data = json_decode($content, TRUE);
      $item_id = $data['item_id'] ?? NULL;
      $queue_name = $data['queue_name'] ?? NULL;
    } else {
      $item_id = $request->request->get('item_id');
      $queue_name = $request->request->get('queue_name');
    }
    
    if (!$item_id || !$queue_name) {
      return new JsonResponse(['success' => false, 'message' => 'Missing parameters'], 400);
    }
    
    \Drupal::logger('job_hunter')->info('🔧 Queue Management: Attempting to delete queue item @item_id from queue @queue', [
      '@item_id' => $item_id,
      '@queue' => $queue_name,
    ]);
    
    try {
      $database = \Drupal::database();
      $deleted = $database->delete('queue')
        ->condition('item_id', $item_id)
        ->condition('name', $queue_name)
        ->execute();
      
      if ($deleted) {
        \Drupal::logger('job_hunter')->info('✅ Queue Management: Successfully deleted queue item @item_id from queue @queue', [
          '@item_id' => $item_id,
          '@queue' => $queue_name,
        ]);
        
        return new JsonResponse([
          'success' => true,
          'message' => 'Queue item deleted successfully',
        ]);
      } else {
        \Drupal::logger('job_hunter')->warning('⚠️ Queue Management: Queue item @item_id not found in queue @queue', [
          '@item_id' => $item_id,
          '@queue' => $queue_name,
        ]);
        return new JsonResponse([
          'success' => false,
          'message' => 'Queue item not found',
        ], 404);
      }
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Error deleting queue item @item_id: @error', [
        '@item_id' => $item_id,
        '@error' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'success' => false,
        'message' => 'Error deleting queue item: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Delete a file (AJAX endpoint).
   */
  public function deleteFile(Request $request) {
    // Check admin permission
    if (!$this->currentUser()->hasPermission('administer job application automation')) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Access denied',
      ], 403);
    }

    // Handle JSON request body
    $content = $request->getContent();
    if ($content) {
      $data = json_decode($content, TRUE);
      $file_id = $data['file_id'] ?? NULL;
    } else {
      $file_id = $request->request->get('file_id');
    }
    
    if (!$file_id) {
      return new JsonResponse(['success' => false, 'message' => 'Missing file ID'], 400);
    }
    
    \Drupal::logger('job_hunter')->info('🔧 Queue Management: Attempting to delete file ID @fid', [
      '@fid' => $file_id,
    ]);
    
    try {
      $file = \Drupal\file\Entity\File::load($file_id);
      if (!$file) {
        \Drupal::logger('job_hunter')->warning('⚠️ Queue Management: File ID @fid not found', [
          '@fid' => $file_id,
        ]);
        return new JsonResponse(['success' => false, 'message' => 'File not found'], 404);
      }
      
      $filename = $file->getFilename();
      $file_uri = $file->getFileUri();
      
      \Drupal::logger('job_hunter')->info('🗑️ Queue Management: Deleting file ID @fid (@filename) at @uri', [
        '@fid' => $file_id,
        '@filename' => $filename,
        '@uri' => $file_uri,
      ]);
      
      $file->delete();
      
      \Drupal::logger('job_hunter')->info('✅ Queue Management: Successfully deleted file ID @fid (@filename)', [
        '@fid' => $file_id,
        '@filename' => $filename,
      ]);
      
      return new JsonResponse([
        'success' => true,
        'message' => "File '{$filename}' deleted successfully",
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Error deleting file @fid: @error', [
        '@fid' => $file_id,
        '@error' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'success' => false,
        'message' => 'Error deleting file: ' . $e->getMessage(),
      ], 500);
    }
  }

}
