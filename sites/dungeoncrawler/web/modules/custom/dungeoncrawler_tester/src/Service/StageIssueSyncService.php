<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;

/**
 * Synchronizes linked GitHub issues and stage state.
 */
class StageIssueSyncService {

  public function __construct(
    private readonly StateInterface $state,
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
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

    [$repo, $token] = $this->getRepoToken();
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
        $issue = $this->fetchIssue($repo, $token, $issue_number);
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

  /**
   * Determine repository and token for GitHub calls.
   */
  private function getRepoToken(): array {
    $testerConfig = $this->configFactory->get('dungeoncrawler_tester.settings');
    $repo = $testerConfig->get('github_repo');
    $token = $testerConfig->get('github_token');

    $aiConfig = $this->configFactory->get('ai_conversation.settings');
    $repo = $repo ?: $aiConfig->get('github_repo');
    $repo = $repo ?: $aiConfig->get('copilot_default_repo');
    $token = $token ?: $aiConfig->get('github_token');
    $token = $token ?: $aiConfig->get('copilot_token');

    $repo = $repo ?: getenv('TESTER_GITHUB_REPO') ?: 'keithaumiller/forseti.life';
    $token = $token ?: (getenv('TESTER_GITHUB_TOKEN') ?: (getenv('GITHUB_TOKEN_COPILOT') ?: getenv('GITHUB_TOKEN')));

    return [$repo, $token];
  }

  /**
   * Fetch an issue payload from GitHub.
   */
  private function fetchIssue(string $repo, string $token, int $number): ?array {
    $url = "https://api.github.com/repos/{$repo}/issues/{$number}";

    try {
      $resp = $this->httpClient->request('GET', $url, [
        'headers' => [
          'Authorization' => "Bearer {$token}",
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester-issues-sync',
        ],
        'timeout' => 8,
      ]);

      if ($resp->getStatusCode() >= 200 && $resp->getStatusCode() < 300) {
        return json_decode((string) $resp->getBody(), TRUE) ?: NULL;
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Issue sync failed: @msg', ['@msg' => $e->getMessage()]);
    }

    return NULL;
  }

}
