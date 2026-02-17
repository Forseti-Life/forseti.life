<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;

/**
 * Planner/executor service for SDLC reset operations.
 */
class SdlcResetService {

  /**
   * Labels treated as testing issues during SDLC reset closure.
   */
  private const RESET_TESTING_LABELS = [
    'testing',
    'testing-defect',
    'ci-failure',
    'program-defect',
    'tester',
  ];

  /**
   * PR merge states treated as failed for reset closure.
   */
  private const FAILED_PR_STATES = ['unstable', 'blocked', 'dirty'];

  public function __construct(
    private readonly StateInterface $state,
    private readonly GithubIssuePrClientInterface $githubClient,
    private readonly Connection $database,
    private readonly StageDefinitionService $stageDefinitions,
    private readonly CacheBackendInterface $cacheBackend,
  ) {
  }

  /**
   * Resolve GitHub repo and token from settings/env fallback chain.
   */
  public function getRepoToken(): array {
    $context = $this->githubClient->resolveContext();
    return [
      (string) ($context['repo'] ?? 'keithaumiller/forseti.life'),
      (string) ($context['token'] ?? ''),
    ];
  }

  /**
   * Build a quick impact preview for the reset action.
   */
  public function getResetPreviewStats(): array {
    [$repo, $token] = $this->getRepoToken();

    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $definedStageIds = array_values(array_map(
      static fn(array $definition): string => (string) ($definition['id'] ?? ''),
      $this->stageDefinitions->getDefinitions()
    ));
    $definedStageIds = array_values(array_filter($definedStageIds));
    $definedStageIdSet = array_fill_keys($definedStageIds, TRUE);

    $definedStageStateCount = 0;
    foreach (array_keys($stageStates) as $stageId) {
      if (isset($definedStageIdSet[(string) $stageId])) {
        $definedStageStateCount++;
      }
    }

    $totalStageStateCount = count($stageStates);
    $historicalStageStateCount = max(0, $totalStageStateCount - $definedStageStateCount);

    $openTestingIssueSet = [];
    if (!empty($token)) {
      foreach ($this->fetchOpenTestingIssueNumbers($repo, $token) as $issueNumber) {
        $openTestingIssueSet[(int) $issueNumber] = TRUE;
      }
    }

    $openIssues = [];
    foreach ($stageStates as $state) {
      $linkedIssueNumbers = [];
      if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
        $linkedIssueNumbers = array_values(array_unique(array_filter(array_map('intval', $state['issue_numbers']))));
      }
      if (!empty($state['issue_number'])) {
        $linkedIssueNumbers[] = (int) $state['issue_number'];
      }
      $linkedIssueNumbers = array_values(array_unique(array_filter($linkedIssueNumbers)));

      $hasOpenIssue = !empty($linkedIssueNumbers) && (($state['issue_status'] ?? 'open') === 'open');
      if ($hasOpenIssue) {
        if (!empty($token)) {
          foreach ($linkedIssueNumbers as $issueNumber) {
            if (isset($openTestingIssueSet[(int) $issueNumber])) {
              $openIssues[(int) $issueNumber] = TRUE;
            }
          }
        }
        else {
          foreach ($linkedIssueNumbers as $issueNumber) {
            $openIssues[(int) $issueNumber] = TRUE;
          }
        }
      }
    }

    $queueItems = (int) $this->database->select('queue', 'q')
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      'open_issues' => count($openIssues),
      'defined_stages' => $definedStageStateCount,
      'expected_defined_stages' => count($definedStageIds),
      'historical_stage_states' => $historicalStageStateCount,
      'total_stage_states' => $totalStageStateCount,
      'queue_items' => $queueItems,
    ];
  }

  /**
   * Collect open issue numbers from stage state + testing labels.
   */
  public function collectOpenIssueNumbers(array $stageStates, string $repo, string $token): array {
    $issueNumbers = [];

    foreach ($stageStates as $state) {
      $hasOpenStatus = (($state['issue_status'] ?? 'open') === 'open');
      if (!$hasOpenStatus) {
        continue;
      }

      $linkedIssueNumbers = [];
      if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
        $linkedIssueNumbers = array_values(array_unique(array_filter(array_map('intval', $state['issue_numbers']))));
      }
      if (!empty($state['issue_number'])) {
        $linkedIssueNumbers[] = (int) $state['issue_number'];
      }

      foreach (array_values(array_unique(array_filter($linkedIssueNumbers))) as $issueNumber) {
        $issueNumbers[(int) $issueNumber] = TRUE;
      }
    }

    if ($token !== '') {
      $labelIssueNumbers = $this->fetchOpenTestingIssueNumbers($repo, $token);
      foreach ($labelIssueNumbers as $issueNumber) {
        $issueNumbers[(int) $issueNumber] = TRUE;
      }
    }

    return array_values(array_map('intval', array_keys($issueNumbers)));
  }

  /**
   * Close issues with reset notes and return summary.
   */
  public function closeIssues(string $repo, string $token, array $issueNumbers): array {
    $closed = 0;
    $failed = 0;
    $closedIssueNumbers = [];
    $failedIssueNumbers = [];

    foreach ($issueNumbers as $issueNumber) {
      $issueNumber = (int) $issueNumber;
      if ($issueNumber <= 0) {
        continue;
      }

      $ok = $this->closeIssueWithResetNote($repo, $token, $issueNumber);
      if ($ok) {
        $closed++;
        $closedIssueNumbers[] = $issueNumber;
      }
      else {
        $failed++;
        $failedIssueNumbers[] = $issueNumber;
      }
    }

    return [
      'closed' => $closed,
      'failed' => $failed,
      'closed_numbers' => $closedIssueNumbers,
      'failed_numbers' => $failedIssueNumbers,
    ];
  }

  /**
   * Return open PR numbers currently considered failed by merge state.
   */
  public function fetchFailedOpenPullRequestNumbers(string $repo, string $token): array {
    $payload = $this->githubClient->listOpenPullRequests($repo, $token, 100);
    $failedPrs = [];
    foreach ($payload as $pull) {
      if (!is_array($pull)) {
        continue;
      }

      $headRef = (string) ($pull['head']['ref'] ?? '');
      if (!str_starts_with($headRef, 'copilot/')) {
        continue;
      }

      $mergeableState = strtolower((string) ($pull['mergeable_state'] ?? ''));
      if (in_array($mergeableState, self::FAILED_PR_STATES, TRUE)) {
        $number = (int) ($pull['number'] ?? 0);
        if ($number > 0) {
          $failedPrs[] = $number;
        }
      }
    }

    return array_values(array_unique(array_filter(array_map('intval', $failedPrs))));
  }

  /**
   * Close PRs with reset notes and return summary.
   */
  public function closePullRequests(string $repo, string $token, array $prNumbers): array {
    $closed = 0;
    $failed = 0;
    $failedPrNumbers = [];

    foreach ($prNumbers as $prNumber) {
      $prNumber = (int) $prNumber;
      if ($prNumber <= 0) {
        continue;
      }

      $ok = $this->closePullRequestWithResetNote($repo, $token, $prNumber);
      if ($ok) {
        $closed++;
      }
      else {
        $failed++;
        $failedPrNumbers[] = $prNumber;
      }
    }

    return [
      'closed' => $closed,
      'failed' => $failed,
      'failed_numbers' => $failedPrNumbers,
    ];
  }

  /**
   * Apply local SDLC reset state and return cleared queue count.
   */
  public function applyLocalReset(array $stageStates, array $closedIssueNumbers, bool $forceLocalReset, string $repo): int {
    $definedStageIds = array_values(array_map(
      static fn(array $definition): string => (string) ($definition['id'] ?? ''),
      $this->stageDefinitions->getDefinitions()
    ));
    $definedStageIds = array_values(array_filter($definedStageIds));

    $normalizedStageStates = [];
    foreach ($definedStageIds as $stageId) {
      $state = $stageStates[$stageId] ?? [];
      $state['active'] = TRUE;

      $issueNumber = isset($state['issue_number']) ? (int) $state['issue_number'] : 0;
      $isClosedByReset = $issueNumber > 0 && in_array($issueNumber, $closedIssueNumbers, TRUE);

      if ($forceLocalReset || $issueNumber === 0 || $isClosedByReset) {
        unset($state['issue_number'], $state['issue_status']);
      }

      unset(
        $state['issue_numbers'],
        $state['issue_test_cases'],
        $state['failure_reason'],
        $state['failure_excerpt']
      );

      $normalizedStageStates[$stageId] = $state;
    }

    $this->state->set('dungeoncrawler_tester.stage_state', $normalizedStageStates);
    $this->state->set('dungeoncrawler_tester.runs', []);
    $this->state->set('dungeoncrawler_tester.auto_enqueue_last', []);

    $clearedQueueItems = (int) $this->database->delete('queue')
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->execute();

    $this->clearGithubDashboardCaches($repo);
    Cache::invalidateTags(['dungeoncrawler_tester.dashboard', 'dungeoncrawler_tester.queue']);

    return $clearedQueueItems;
  }

  /**
   * Close a GitHub issue and add a reset note comment.
   */
  private function closeIssueWithResetNote(string $repo, string $token, int $issueNumber): bool {
    $commentUrl = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments";
    $issueUrl = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}";

    $commented = $this->githubClient->mutate('POST', $commentUrl, [
      'body' => 'Closing issue as part of SDLC reset initiated from tester dashboard. This item should be re-opened or recreated if still actionable after reset.',
    ], $token, 10);

    if (!$commented) {
      return FALSE;
    }

    return $this->githubClient->mutate('PATCH', $issueUrl, ['state' => 'closed'], $token, 10);
  }

  /**
   * Fetch open testing-related issue numbers by label.
   */
  private function fetchOpenTestingIssueNumbers(string $repo, string $token): array {
    $issueNumbers = [];

    foreach (self::RESET_TESTING_LABELS as $label) {
      $payload = $this->githubClient->listOpenIssuesByLabel($repo, $label, $token, 100);
      foreach ($payload as $item) {
        if (!is_array($item)) {
          continue;
        }

        if (!empty($item['pull_request'])) {
          continue;
        }

        $number = (int) ($item['number'] ?? 0);
        if ($number > 0) {
          $issueNumbers[$number] = TRUE;
        }
      }
    }

    return array_values(array_map('intval', array_keys($issueNumbers)));
  }

  /**
   * Close an open pull request and add a reset note comment.
   */
  private function closePullRequestWithResetNote(string $repo, string $token, int $prNumber): bool {
    $commentUrl = "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments";
    $prUrl = "https://api.github.com/repos/{$repo}/pulls/{$prNumber}";

    $commented = $this->githubClient->mutate('POST', $commentUrl, [
      'body' => 'Closing pull request as part of SDLC reset initiated from tester dashboard because it is in failed state. Re-open or recreate if still actionable after reset.',
    ], $token, 10);

    if (!$commented) {
      return FALSE;
    }

    return $this->githubClient->mutate('PATCH', $prUrl, ['state' => 'closed'], $token, 10);
  }

  /**
   * Invalidate GitHub-derived dashboard cache entries after reset.
   */
  private function clearGithubDashboardCaches(string $repo): void {
    $issuesCacheKeys = [
      'dungeoncrawler_tester.github_issues.' . $repo . '.ci-failure',
      'dungeoncrawler_tester.github_issues.' . $repo . '.testing-defect',
      'dungeoncrawler_tester.github_issues.' . $repo . '.program-defect',
      'dungeoncrawler_tester.github_open_prs.' . $repo,
      'dungeoncrawler_tester.github_open_testing_issue_numbers.' . $repo,
      'dungeoncrawler_tester.github_pr_automation_stats.' . $repo,
      'dungeoncrawler_tester.github_workflow_summary.' . $repo . '.auto-ready-on-copilot-signal.yml',
      'dungeoncrawler_tester.github_workflow_summary.' . $repo . '.merge-issue-branches-into-testing.yml',
    ];

    foreach ($issuesCacheKeys as $key) {
      $this->cacheBackend->delete($key);
    }

    $this->cacheBackend->delete('dungeoncrawler_tester.github_open_prs.' . $repo);
  }

}
