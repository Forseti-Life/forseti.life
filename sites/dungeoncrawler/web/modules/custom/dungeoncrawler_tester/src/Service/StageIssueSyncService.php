<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;

/**
 * Synchronizes linked GitHub issues and stage state.
 */
class StageIssueSyncService {

  public function __construct(
    private readonly StateInterface $state,
    private readonly GithubIssuePrClientInterface $githubClient,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * Logger channel.
   */
  private LoggerChannelInterface $logger;

  /**
   * Sync linked issues; optionally auto-resume and unlink when closed.
   */
  public function syncIssues(bool $autoResume = FALSE, bool $unlinkOnClose = FALSE): void {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    if (empty($states)) {
      $this->logger->info('Issue sync skipped: no staged tests are tracking issues.');
      return;
    }

    $githubContext = $this->githubClient->resolveContext();
    $repo = (string) ($githubContext['repo'] ?? '');
    $token = $githubContext['token'] ?? NULL;
    if (!$token) {
      $this->logger->warning('Issue sync skipped: missing GitHub token.');
      return;
    }

    $updated = FALSE;
    $linkedCount = 0;
    $closedCount = 0;
    $resumedCount = 0;
    $unlinkedCount = 0;

    foreach ($states as $stage_id => $state) {
      $linked_issue_numbers = [];
      if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
        $linked_issue_numbers = array_values(array_unique(array_filter(array_map('intval', $state['issue_numbers']))));
      }
      if (!empty($state['issue_number'])) {
        $linked_issue_numbers[] = (int) $state['issue_number'];
      }
      $linked_issue_numbers = array_values(array_unique(array_filter($linked_issue_numbers)));

      if (empty($linked_issue_numbers)) {
        continue;
      }

      $linkedCount++;

      $open_issues = [];
      foreach ($linked_issue_numbers as $issue_number) {
        $issue = $this->githubClient->getIssue($repo, (int) $issue_number, (string) $token);
        if (!$issue) {
          $open_issues[] = $issue_number;
          continue;
        }
        $isClosed = ($issue['state'] ?? '') === 'closed';
        if (!$isClosed) {
          $open_issues[] = $issue_number;
        }
      }

      $all_closed = empty($open_issues);
      $states[$stage_id]['issue_status'] = $all_closed ? 'closed' : 'open';
      $states[$stage_id]['issue_numbers'] = $linked_issue_numbers;
      if (!empty($linked_issue_numbers)) {
        $states[$stage_id]['issue_number'] = (int) $linked_issue_numbers[0];
      }

      if ($all_closed) {
        $updated = TRUE;
        $closedCount++;
        $wasActive = !empty($state['active']);

        // Re-enable stage and clear failure metadata.
        $states[$stage_id]['active'] = TRUE;
        unset($states[$stage_id]['failure_reason'], $states[$stage_id]['failure_excerpt']);

        if ($unlinkOnClose) {
          unset($states[$stage_id]['issue_number'], $states[$stage_id]['issue_status'], $states[$stage_id]['issue_numbers'], $states[$stage_id]['issue_test_cases']);
          $unlinkedCount++;
        }

        if ($autoResume) {
          if (!$wasActive) {
            $resumedCount++;
          }
          $this->logger->notice('Stage @stage auto-resumed after issue closure (#@issue).', [
            '@stage' => $stage_id,
            '@issue' => implode(',', $linked_issue_numbers),
          ]);
        }
      }
    }

    if ($updated) {
      $this->state->set('dungeoncrawler_tester.stage_state', $states);
      $this->logger->info('Issue sync completed. Linked: @linked, closed: @closed, resumed: @resumed, unlinked: @unlinked.', [
        '@linked' => $linkedCount,
        '@closed' => $closedCount,
        '@resumed' => $resumedCount,
        '@unlinked' => $unlinkedCount,
      ]);
    }
    else {
      $this->logger->info('Issue sync completed. Linked: @linked, closed: 0. No updates applied.', [
        '@linked' => $linkedCount,
      ]);
    }
  }

}
