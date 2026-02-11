<?php

namespace Drupal\job_hunter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Controller for job posting operations.
 */
class JobPostingController extends ControllerBase {

  /**
   * Retry AI parsing for a failed job posting.
   *
   * @param int $job_id
   *   The job ID to retry parsing for.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect back to referrer or jobs list.
   */
  public function retryParsing($job_id) {
    $database = \Drupal::database();
    
    // Get job details
    $job = $database->select('jobhunter_job_requirements', 'j')
      ->fields('j', ['id', 'raw_posting_text', 'ai_extraction_status'])
      ->condition('id', $job_id)
      ->execute()
      ->fetchObject();
    
    if (!$job) {
      $this->messenger()->addError($this->t('Job posting not found.'));
      return new RedirectResponse(Url::fromRoute('job_hunter.jobs_list')->toString());
    }
    
    if (empty($job->raw_posting_text)) {
      $this->messenger()->addError($this->t('Cannot retry parsing: No raw posting text available.'));
      return $this->redirect('job_hunter.job_view', ['job_id' => $job_id]);
    }
    
    // Reset AI extraction status
    $database->update('jobhunter_job_requirements')
      ->fields([
        'ai_extraction_status' => 'pending',
      ])
      ->condition('id', $job_id)
      ->execute();
    
    // Re-queue for AI parsing
    $queue = \Drupal::queue('job_hunter_job_posting_parsing');
    $queue->createItem([
      'job_id' => $job_id,
      'raw_posting_text' => $job->raw_posting_text,
    ]);
    
    \Drupal::logger('job_hunter')->info('📋 Re-queued job posting @id for AI parsing', ['@id' => $job_id]);
    
    $this->messenger()->addMessage($this->t('Job posting #@id has been re-queued for AI parsing.', [
      '@id' => $job_id,
    ]));
    
    // Redirect back to referrer or jobs list
    $referer = \Drupal::request()->headers->get('referer');
    if ($referer) {
      return new RedirectResponse($referer);
    }
    
    return new RedirectResponse(Url::fromRoute('job_hunter.jobs_list')->toString());
  }

}
