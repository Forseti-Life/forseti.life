<?php

namespace Drupal\job_hunter\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\job_hunter\Traits\QueueWorkerBaseTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Application submission queue worker.
 *
 * Processes job applications asynchronously by automating form submission
 * using browser automation (Playwright/Puppeteer).
 *
 * @QueueWorker(
 *   id = "job_hunter_application_submission",
 *   title = @Translation("Job Application Submission"),
 *   cron = {"time" = 120}
 * )
 */
class ApplicationSubmitterQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use QueueWorkerBaseTrait;

  /**
   * The application submission service.
   *
   * @var \Drupal\job_hunter\Service\ApplicationSubmissionService
   */
  protected $applicationSubmissionService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->applicationSubmissionService = $container->get('job_hunter.application_submission_service');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $job_id = $data['job_id'];
    $application_id = $data['application_id'];
    $app_data = $data['app_data'];

    $logger = \Drupal::logger('job_hunter');
    $logger->info('📧 Queue: Starting application submission for user @uid, job @job_id, application @app_id', [
      '@uid' => $uid,
      '@job_id' => $job_id,
      '@app_id' => $application_id,
    ]);

    try {
      // Update status to processing
      $this->applicationSubmissionService->updateApplicationStatus(
        $application_id,
        'processing',
        []
      );

      // Attempt submission via browser automation
      $result = $this->submitApplicationViaBrowser($app_data);

      if ($result['success']) {
        $logger->info('✅ Application submitted successfully for user @uid, job @job_id. Confirmation: @confirmation', [
          '@uid' => $uid,
          '@job_id' => $job_id,
          '@confirmation' => $result['confirmation'] ?? 'N/A',
        ]);

        // Update application status to submitted
        $this->applicationSubmissionService->updateApplicationStatus(
          $application_id,
          'submitted',
          [
            'confirmation' => $result['confirmation'] ?? '',
            'automation_success' => TRUE,
          ]
        );

        // Update job_requirements table
        $this->updateJobSubmissionStatus($job_id, 'submitted');
      } else {
        // Submission failed - queue for manual review
        $logger->warning('⚠️ Application submission failed for user @uid, job @job_id. Error: @error', [
          '@uid' => $uid,
          '@job_id' => $job_id,
          '@error' => $result['error'] ?? 'Unknown error',
        ]);

        $this->applicationSubmissionService->updateApplicationStatus(
          $application_id,
          'manual_required',
          [
            'error' => [
              'message' => $result['error'] ?? 'Automation failed',
              'reason' => $result['reason'] ?? 'unknown',
            ],
            'admin_review' => TRUE,
          ]
        );

        // Queue for admin review
        $this->queueForErrorQueue($uid, $job_id, $application_id, $result['error'] ?? 'Application submission failed');
      }
    } catch (SuspendQueueException $e) {
      // Database unavailable or other critical issue
      $logger->error('🔴 Queue worker suspended during application submission: @error', [
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    } catch (\Exception $e) {
      $logger->error('🔴 Queue worker exception during application submission: @error', [
        '@error' => $e->getMessage(),
      ]);

      // Mark for manual review and continue
      $this->applicationSubmissionService->updateApplicationStatus(
        $application_id,
        'manual_required',
        [
          'error' => [
            'message' => $e->getMessage(),
            'reason' => 'exception',
          ],
          'admin_review' => TRUE,
        ]
      );

      $this->queueForErrorQueue($uid, $job_id, $application_id, 'Exception: ' . $e->getMessage());
    }
  }

  /**
   * Submits application via browser automation.
   *
   * @param array $app_data
   *   The application data prepared by ApplicationSubmissionService.
   *
   * @return array
   *   Result with structure:
   *   [
   *     'success' => bool,
   *     'confirmation' => string,
   *     'error' => string,
   *     'reason' => string (captcha|authentication|form_error|timeout|unsupported),
   *   ]
   */
  protected function submitApplicationViaBrowser(array $app_data): array {
    $logger = \Drupal::logger('job_hunter');

    try {
      // Get BrowserAutomationService (will be created in next phase)
      $browser_service = \Drupal::service('job_hunter.browser_automation_service');

      if (!$browser_service) {
        return [
          'success' => FALSE,
          'error' => 'Browser automation service not available',
          'reason' => 'unsupported',
        ];
      }

      // Validate job URL
      $job_url = $app_data['job_url'] ?? '';
      if (empty($job_url)) {
        return [
          'success' => FALSE,
          'error' => 'Job URL not available',
          'reason' => 'unsupported',
        ];
      }

      // Detect company/ATS type
      $ats_type = $this->detectATSPlatform($job_url);
      $logger->info('Detected ATS platform: @ats for URL @url', [
        '@ats' => $ats_type,
        '@url' => $job_url,
      ]);

      // For MVP, we'll implement a basic response
      // In phase 2, this will call actual browser automation

      // PLACEHOLDER: Return manual required (since browser automation not yet implemented)
      return [
        'success' => FALSE,
        'error' => 'Browser automation framework not yet implemented',
        'reason' => 'unsupported',
      ];
    } catch (\Exception $e) {
      $logger->error('Exception in browser automation: @error', [
        '@error' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => $e->getMessage(),
        'reason' => 'exception',
      ];
    }
  }

  /**
   * Detects the ATS platform from job URL.
   *
   * @param string $job_url
   *   The job URL.
   *
   * @return string
   *   The ATS platform type (workday, greenhouse, taleo, custom, unknown).
   */
  protected function detectATSPlatform(string $job_url): string {
    $url_lower = strtolower($job_url);

    if (strpos($url_lower, 'workday') !== FALSE) {
      return 'workday';
    } elseif (strpos($url_lower, 'greenhouse') !== FALSE) {
      return 'greenhouse';
    } elseif (strpos($url_lower, 'taleo') !== FALSE) {
      return 'taleo';
    } elseif (strpos($url_lower, 'applicanttrack') !== FALSE) {
      return 'applicanttrack';
    } elseif (strpos($url_lower, 'lever') !== FALSE) {
      return 'lever';
    }

    return 'custom';
  }

  /**
   * Updates the job submission status in jobhunter_job_requirements.
   *
   * @param int $job_id
   *   The job ID.
   * @param string $status
   *   The new submission status.
   */
  protected function updateJobSubmissionStatus(int $job_id, string $status): void {
    $connection = \Drupal::database();
    $connection->update('jobhunter_job_requirements')
      ->fields([
        'submission_status' => $status,
        'submission_date' => date('Y-m-d H:i:s'),
      ])
      ->condition('id', $job_id)
      ->execute();
  }

  /**
   * Queues failed application for admin review.
   *
   * @param int $uid
   *   The user ID.
   * @param int $job_id
   *   The job ID.
   * @param int $application_id
   *   The application ID.
   * @param string $error_message
   *   The error message.
   */
  protected function queueForErrorQueue(int $uid, int $job_id, int $application_id, string $error_message): void {
    try {
      $error_queue = \Drupal::queue('job_hunter_error_queue');
      $error_queue->createItem([
        'type' => 'application_submission_failed',
        'uid' => $uid,
        'job_id' => $job_id,
        'application_id' => $application_id,
        'error_message' => $error_message,
        'timestamp' => time(),
        'action_required' => 'Assist user in manual application completion',
      ]);
    } catch (\Exception $e) {
      \Drupal::logger('job_hunter')->error('Failed to queue error item: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

}
