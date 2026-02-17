<?php

namespace Drupal\dungeoncrawler_tester\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Form\CronAgentsControlForm;
use Drupal\dungeoncrawler_tester\Form\DashboardRunsForm;
use Drupal\dungeoncrawler_tester\Form\SdlcResetForm;
use Drupal\dungeoncrawler_tester\Service\GithubIssuePrClientInterface;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Testing dashboard with stagegates and GitHub failure surfacing.
 */
class TestingDashboardController extends ControllerBase {

  /**
   * Labels treated as testing issues for lifecycle status.
   */
  private const TESTING_ISSUE_LABELS = [
    'testing',
    'testing-defect',
    'ci-failure',
    'program-defect',
    'tester',
  ];

  /**
   * Staleness cutoff (days) for bulk stale-issue cleanup query.
   */
  private const BULK_STALE_DAYS = 60;

  /**
   * State service for persisting last run metadata.
   */
  protected StateInterface $state;

  /**
   * Queue factory for reading queue status/items.
   */
  protected QueueFactory $queueFactory;

  /**
   * Database connection for watchdog reads.
   */
  protected Connection $database;

  /**
   * Date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Stage definitions service.
   */
  protected StageDefinitionService $stageDefinitions;

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  /**
   * Centralized GitHub issue/PR client.
   */
  protected GithubIssuePrClientInterface $githubClient;

  /**
   * Cache backend for dashboard query caching.
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * CSRF token generator for dashboard AJAX settings.
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * Default repository for issue lookups.
   */
  private string $defaultRepo = 'keithaumiller/forseti.life';

  /**
   * GitHub API timeout in seconds.
   */
  private const GITHUB_API_TIMEOUT = 10;

  /**
   * Maximum issues to fetch per request.
   */
  private const GITHUB_MAX_ISSUES = 10;

  /**
   * Maximum queue items to display.
   */
  private const MAX_QUEUE_ITEMS = 50;

  /**
   * GitHub API cache TTL in seconds (10 minutes).
   */
  private const GITHUB_CACHE_TTL = 600;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->configFactory = $container->get('config.factory');
    $instance->state = $container->get('state');
    $instance->queueFactory = $container->get('queue');
    $instance->database = $container->get('database');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->stageDefinitions = $container->get('dungeoncrawler_tester.stage_definitions');
    $instance->githubClient = $container->get('dungeoncrawler_tester.github_issue_pr_client');
    $instance->cacheBackend = $container->get('cache.default');
    $instance->csrfToken = $container->get('csrf_token');
    $instance->logger = $container->get('logger.factory')->get('dungeoncrawler_tester');
    return $instance;
  }

  /**
   * Render the testing dashboard.
   */
  public function dashboard(): array {
    $githubContext = $this->resolveGitHubContext();
    $repo = $githubContext['repo'];
    $token = $githubContext['token'];
    $cronAgentsEnabled = (bool) ($this->configFactory->get('dungeoncrawler_tester.settings')->get('cron_agents_enabled') ?? TRUE);
    $cronPausedNotice = [];
    if (!$cronAgentsEnabled) {
      $cronPausedNotice = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'content' => [
          '#markup' => (string) $this->t('Tester cron agents are currently paused. Scheduled issue sync and auto-enqueue are disabled until re-enabled below.'),
        ],
      ];
    }

    $queue_items = $this->loadQueueItems();
    $queue_status = $this->getQueueStatus();

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dungeoncrawler-testing-dashboard']],
      '#cache' => [
        'tags' => ['dungeoncrawler_tester.dashboard', 'dungeoncrawler_tester.queue'],
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'queue' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dungeoncrawler-queue-embedded']],
        'ui' => [
          '#theme' => 'dungeoncrawler_tester_queue_management',
          '#queue_items' => $queue_items,
          '#queue_status' => $queue_status,
        ],
      ],
      'cron_paused_notice' => $cronPausedNotice,
      'cron_agents_control' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dashboard-cron-agents']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Tester Cron Agents'),
        ],
        'form' => $this->formBuilder()->getForm(CronAgentsControlForm::class),
      ],
      'flow_tracking' => $this->buildLifecycleTrackingSection($repo, $token, $queue_status),
      'stages' => $this->formBuilder()->getForm(DashboardRunsForm::class),
      'documentation' => $this->buildDocumentationSection(),
      '#attached' => [
        'library' => [
          'dungeoncrawler_tester/dashboard',
          'dungeoncrawler_tester/queue-management',
        ],
        'drupalSettings' => [
          'dungeoncrawlerTester' => [
            'csrfToken' => $this->csrfToken->get('rest'),
            'routes' => [
              'run' => Url::fromRoute('dungeoncrawler_tester.queue_run')->toString(),
              'status' => Url::fromRoute('dungeoncrawler_tester.queue_status')->toString(),
              'logs' => Url::fromRoute('dungeoncrawler_tester.queue_logs')->toString(),
              'delete' => Url::fromRoute('dungeoncrawler_tester.queue_item_delete')->toString(),
              'rerun' => Url::fromRoute('dungeoncrawler_tester.queue_item_rerun')->toString(),
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Build a URL from route name with a safe path fallback.
   */
  protected function safeRouteUrl(string $routeName, string $fallbackPath): string {
    try {
      return Url::fromRoute($routeName)->toString();
    }
    catch (RouteNotFoundException $exception) {
      $this->logger->warning('Missing route @route while building dashboard URL. Falling back to @path. Error: @message', [
        '@route' => $routeName,
        '@path' => $fallbackPath,
        '@message' => $exception->getMessage(),
      ]);
      return Url::fromUserInput($fallbackPath)->toString();
    }
  }

  /**
   * Build process and decision logic guidance for issue-pr-report triage.
   */
  protected function buildIssuePrReportDecisionLogicSection(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['issue-pr-report-decision-logic', 'issue-report-item']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Process & Decision Logic'),
      ],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Use this sequence to review open PRs from lowest number upward and make consistent close/keep decisions.'),
      ],
      'steps_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Triage Steps'),
      ],
      'steps' => [
        '#theme' => 'item_list',
        '#items' => [
          (string) $this->t('Process PRs in ascending number order to keep operational cleanup deterministic.'),
          (string) $this->t('Inspect PR state, draft status, merge state, linked issues, checks, and changed files before mutation.'),
          (string) $this->t('Treat no-file-change PRs as no-op candidates; close PRs with rationale comments and keep/open linked issues for separate issue triage when needed.'),
          (string) $this->t('Use bulk close queries only for review-safe classes (for example dead-value PRs, merged-resolution issues, and explicit non-action labels).'),
          (string) $this->t('After each close action, verify resulting PR/issue state via GitHub API before proceeding to the next item.'),
        ],
      ],
      'decisions_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Decision Rules'),
      ],
      'decisions' => [
        '#theme' => 'item_list',
        '#items' => [
          (string) $this->t('Close PR + linked issue when the PR is clearly superseded and linked issue scope is already resolved by merged code.'),
          (string) $this->t('Close PR only when the PR is a no-op (no file changes) but linked issue still needs independent review.'),
          (string) $this->t('Keep PR open when there is actionable code and unresolved blockers (failing checks, unresolved conflicts, or missing review signal).'),
        ],
      ],
    ];
  }

  /**
   * Build top-of-page bulk-close query section.
   */
  protected function buildBulkCloseQuerySection(string $repo, array $issues, array $prs, array $tokenCandidates): array {
    $definitions = $this->buildBulkCloseQueryDefinitions($repo, $issues, $prs, $tokenCandidates);

    $cards = [];
    foreach ($definitions as $definition) {
      $cards[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['issue-card', 'issue-report-item', 'bulk-query-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => (string) ($definition['title'] ?? ''),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted-light']],
          '#value' => (string) ($definition['summary'] ?? ''),
        ],
        'query_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => (string) $this->t('Query'),
        ],
        'query' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#attributes' => ['class' => ['command-snippet']],
          '#value' => (string) ($definition['query'] ?? ''),
        ],
        'impact_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => (string) $this->t('Expected Impact'),
        ],
        'impact' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted-light']],
          '#value' => (string) ($definition['expected_impact'] ?? ''),
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['issue-report-actions']],
          'run' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'type' => 'button',
              'class' => ['button', 'button--small', 'dc-bulk-query-run-btn'],
              'data-query-id' => (string) ($definition['id'] ?? ''),
              'data-query-title' => (string) ($definition['title'] ?? ''),
            ],
            '#value' => (string) $this->t('Run close query'),
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bulk-close-queries']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Bulk Close Queries (No-Action Candidates)'),
      ],
      'help' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Run these review-safe queries to bulk close stale/no-action issue and PR candidates. Validate results in GitHub after each run.'),
      ],
      'cards' => [
        '#theme' => 'item_list',
        '#items' => $cards,
      ],
    ];
  }

  /**
   * Build bulk-close query definitions with live expected impact counts.
   */
  protected function buildBulkCloseQueryDefinitions(string $repo, array $issues, array $prs, array $tokenCandidates): array {
    $openIssueNumbers = [];
    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber > 0) {
        $openIssueNumbers[$issueNumber] = TRUE;
      }
    }

    $deadValueCandidates = $this->collectDeadValuePrCandidates($repo, $prs, $tokenCandidates, $openIssueNumbers);
    $mergedLinkedIssues = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
    $nonActionIssues = $this->collectNonActionOpenIssues($issues);
    $openPrsClosedRefs = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
    $staleTestingIssues = $this->collectStaleUnassignedTestingIssues($issues);
    $staleCutoffDate = date('Y-m-d', strtotime('-' . self::BULK_STALE_DAYS . ' days'));

    return [
      [
        'id' => 'dead_value_prs',
        'title' => (string) $this->t('Dead-value PRs (no diff from main)'),
        'summary' => (string) $this->t('Closes open PRs that have zero changed files and zero additions/deletions against main.'),
        'query' => 'is:pr is:open base:main changed-files:0',
        'expected_impact' => (string) $this->t('Will close @count PR(s). Linked open issues referenced in PR text will also be closed when present.', ['@count' => count($deadValueCandidates)]),
      ],
      [
        'id' => 'issues_resolved_by_merged_pr',
        'title' => (string) $this->t('Open issues referenced by merged PRs'),
        'summary' => (string) $this->t('Closes open issues that are already referenced by merged pull requests.'),
        'query' => 'is:issue is:open linked:pr + merged PR reference check',
        'expected_impact' => (string) $this->t('Will close @count open issue(s) that appear already resolved by merged code.', ['@count' => count($mergedLinkedIssues)]),
      ],
      [
        'id' => 'non_action_labeled_issues',
        'title' => (string) $this->t('Open issues labeled duplicate/invalid/wontfix'),
        'summary' => (string) $this->t('Closes open issues already labeled as non-action outcomes.'),
        'query' => 'is:issue is:open (label:duplicate OR label:invalid OR label:wontfix)',
        'expected_impact' => (string) $this->t('Will close @count issue(s) with non-action resolution labels.', ['@count' => count($nonActionIssues)]),
      ],
      [
        'id' => 'open_prs_with_only_closed_issue_refs',
        'title' => (string) $this->t('Open PRs referencing only closed issues'),
        'summary' => (string) $this->t('Closes open PRs whose referenced issue numbers are all already closed.'),
        'query' => 'is:pr is:open "fixes #" + all referenced issues closed',
        'expected_impact' => (string) $this->t('Will close @count PR(s) with only closed issue references.', ['@count' => count($openPrsClosedRefs)]),
      ],
      [
        'id' => 'stale_unassigned_testing_issues',
        'title' => (string) $this->t('Stale unassigned testing issues'),
        'summary' => (string) $this->t('Closes stale, unassigned testing-defect operational issues that have no active owner.'),
        'query' => 'is:issue is:open no:assignee updated:<' . $staleCutoffDate . ' (label:testing OR label:testing-defect OR label:ci-failure OR label:program-defect OR label:tester)',
        'expected_impact' => (string) $this->t('Will close @count stale issue(s) with no assignee and testing-defect labels.', ['@count' => count($staleTestingIssues)]),
      ],
    ];
  }

  /**
   * Collect dead-value PR candidates and referenced open issues.
   */
  protected function collectDeadValuePrCandidates(string $repo, array $prs, array $tokenCandidates, array $openIssueNumbers): array {
    $candidates = [];

    foreach ($prs as $pr) {
      $prNumber = (int) ($pr['number'] ?? 0);
      if ($prNumber <= 0) {
        continue;
      }

      $details = $this->fetchPullRequestDetails($repo, $tokenCandidates, $prNumber);
      if (empty($details)) {
        continue;
      }

      $normalized = [
        'base_ref' => (string) ($details['base']['ref'] ?? ''),
        'changed_files' => (int) ($details['changed_files'] ?? 0),
        'additions' => (int) ($details['additions'] ?? 0),
        'deletions' => (int) ($details['deletions'] ?? 0),
      ];

      if (!$this->isDeadValuePr($normalized)) {
        continue;
      }

      $issueRefs = [];
      $refs = $this->extractIssueReferencesFromPr([
        'title' => (string) ($details['title'] ?? ''),
        'body' => (string) ($details['body'] ?? ''),
      ]);
      foreach ($refs as $issueNumber) {
        if (!empty($openIssueNumbers[$issueNumber])) {
          $issueRefs[$issueNumber] = TRUE;
        }
      }

      $candidates[] = [
        'pr_number' => $prNumber,
        'issue_numbers' => array_values(array_map('intval', array_keys($issueRefs))),
      ];
    }

    return $candidates;
  }

  /**
   * Collect open issue numbers referenced by merged PRs.
   */
  protected function collectOpenIssuesReferencedByMergedPrs(string $repo, array $issues, array $tokenCandidates): array {
    $openIssueNumbers = [];
    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber > 0) {
        $openIssueNumbers[$issueNumber] = TRUE;
      }
    }

    $payload = $this->fetchClosedPullRequestsForReport($repo, $tokenCandidates, FALSE);
    $closedPrs = $payload['items'] ?? [];
    $candidates = [];

    foreach ($closedPrs as $pr) {
      if (empty($pr['merged_at'])) {
        continue;
      }
      $refs = $this->extractIssueReferencesFromPr($pr);
      foreach ($refs as $issueNumber) {
        if (!empty($openIssueNumbers[$issueNumber])) {
          $candidates[$issueNumber] = TRUE;
        }
      }
    }

    return array_values(array_map('intval', array_keys($candidates)));
  }

  /**
   * Collect open issue numbers already marked duplicate/invalid/wontfix.
   */
  protected function collectNonActionOpenIssues(array $issues): array {
    $candidates = [];
    $nonActionLabels = ['duplicate', 'invalid', 'wontfix'];

    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber <= 0) {
        continue;
      }

      $labels = array_map(static fn(string $label): string => strtolower(trim($label)), (array) ($issue['labels'] ?? []));
      if (!empty(array_intersect($labels, $nonActionLabels))) {
        $candidates[$issueNumber] = TRUE;
      }
    }

    return array_values(array_map('intval', array_keys($candidates)));
  }

  /**
   * Collect open PR numbers where every referenced issue is already closed.
   */
  protected function collectOpenPrsReferencingOnlyClosedIssues(array $prs, array $openIssueNumbers): array {
    $candidates = [];

    foreach ($prs as $pr) {
      $prNumber = (int) ($pr['number'] ?? 0);
      if ($prNumber <= 0) {
        continue;
      }

      $refs = $this->extractIssueReferencesFromPr($pr);
      if (empty($refs)) {
        continue;
      }

      $allClosed = TRUE;
      foreach ($refs as $issueNumber) {
        if (!empty($openIssueNumbers[$issueNumber])) {
          $allClosed = FALSE;
          break;
        }
      }

      if ($allClosed) {
        $candidates[$prNumber] = TRUE;
      }
    }

    return array_values(array_map('intval', array_keys($candidates)));
  }

  /**
   * Collect stale unassigned testing-related open issues.
   */
  protected function collectStaleUnassignedTestingIssues(array $issues): array {
    $candidates = [];

    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber <= 0) {
        continue;
      }

      $assignees = (array) ($issue['assignees'] ?? []);
      $labels = array_map(static fn(string $label): string => strtolower(trim($label)), (array) ($issue['labels'] ?? []));
      $staleDays = (int) ($issue['stale_days'] ?? 0);

      if (!empty($assignees)) {
        continue;
      }
      if ($staleDays < self::BULK_STALE_DAYS) {
        continue;
      }
      if (empty(array_intersect($labels, self::TESTING_ISSUE_LABELS))) {
        continue;
      }

      $candidates[$issueNumber] = TRUE;
    }

    return array_values(array_map('intval', array_keys($candidates)));
  }

  /**
   * Fetch full PR details by number.
   */
  protected function fetchPullRequestDetails(string $repo, array $tokenCandidates, int $prNumber): ?array {
    if ($prNumber <= 0) {
      return NULL;
    }

    $response = $this->requestGitHubJsonWithFallback("https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $tokenCandidates, [], FALSE);
    if (!empty($response['error']) || !is_array($response['items'])) {
      return NULL;
    }

    return $response['items'];
  }

  /**
   * Build concise process flow for stagegate overview.
   */
  private function buildProcessFlowSection(): array {
    $definitions = $this->stageDefinitions->getDefinitions();
    $items = [];
    foreach ($definitions as $stage) {
      $items[] = $stage['title'] ?? $stage['id'];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-flow']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Release Testing Stagegates'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
      'note' => [
        '#markup' => '<p>' . $this->t('Each stage below includes run buttons (commands) and a reports placeholder.') . '</p>',
      ],
    ];
  }

  /**
   * Build live SDLC/Release flow tracking from current system signals.
   */
  private function buildLifecycleTrackingSection(string $repo, ?string $token, array $queue_status): array {
    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $definedStageIds = array_values(array_map(
      static fn(array $definition): string => (string) ($definition['id'] ?? ''),
      $this->stageDefinitions->getDefinitions()
    ));
    $definedStageIds = array_values(array_filter($definedStageIds));
    $definedStageIdSet = array_fill_keys($definedStageIds, TRUE);

    $normalizedStageStates = [];
    foreach ($stageStates as $stageId => $state) {
      if (!isset($definedStageIdSet[(string) $stageId])) {
        continue;
      }
      if (!is_array($state)) {
        $state = [];
      }
      $normalizedStageStates[(string) $stageId] = $state;
    }

    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $queueItems = (int) ($queue_status['dungeoncrawler_tester_runs']['items'] ?? 0);

    $openTestingIssueSet = [];
    foreach ($this->fetchOpenTestingIssueNumbers($repo, $token, FALSE) as $issueNumber) {
      $openTestingIssueSet[(int) $issueNumber] = TRUE;
    }

    $openLinkedIssues = [];
    $blockedStages = 0;
    foreach ($normalizedStageStates as $state) {
      $linkedIssueNumbers = [];
      if (!empty($state['issue_numbers']) && is_array($state['issue_numbers'])) {
        $linkedIssueNumbers = array_values(array_unique(array_filter(array_map('intval', $state['issue_numbers']))));
      }
      if (!empty($state['issue_number'])) {
        $linkedIssueNumbers[] = (int) $state['issue_number'];
      }
      $linkedIssueNumbers = array_values(array_unique(array_filter($linkedIssueNumbers)));

      $hasOpenIssue = FALSE;
      if (!empty($linkedIssueNumbers) && (($state['issue_status'] ?? 'open') === 'open')) {
        if ($token) {
          foreach ($linkedIssueNumbers as $issueNumber) {
            if (isset($openTestingIssueSet[(int) $issueNumber])) {
              $openLinkedIssues[(int) $issueNumber] = TRUE;
              $hasOpenIssue = TRUE;
            }
          }
        }
        else {
          foreach ($linkedIssueNumbers as $issueNumber) {
            $openLinkedIssues[(int) $issueNumber] = TRUE;
          }
          $hasOpenIssue = TRUE;
        }
      }
      $isInactive = array_key_exists('active', $state) && $state['active'] === FALSE;
      if ($hasOpenIssue || $isInactive) {
        $blockedStages++;
      }
    }

    $pendingRuns = 0;
    $runningRuns = 0;
    $passedRuns = 0;
    $failedRuns = 0;
    foreach ($definedStageIds as $stageId) {
      $run = $runs[$stageId] ?? [];
      $status = $run['status'] ?? '';
      if ($status === 'pending') {
        $pendingRuns++;
      }
      elseif ($status === 'running') {
        $runningRuns++;
      }

      if (array_key_exists('exit_code', $run) && $run['exit_code'] !== NULL) {
        if ((int) $run['exit_code'] === 0) {
          $passedRuns++;
        }
        else {
          $failedRuns++;
        }
      }
    }

    $prSummary = $this->fetchOpenPullRequestSummary($repo, $token, FALSE);
    $openPrs = (int) ($prSummary['open_count'] ?? 0);
    $draftPrs = (int) ($prSummary['draft_count'] ?? 0);

    $prAutomation = $this->fetchPrAutomationStats($repo, $token, FALSE);
    $latestAutoReadyRun = $this->fetchWorkflowRunSummary($repo, $token, 'auto-ready-on-copilot-signal.yml', FALSE);
    $latestMergeRun = $this->fetchWorkflowRunSummary($repo, $token, 'merge-issue-branches-into-testing.yml', FALSE);

    $openTestingIssues = $openTestingIssueSet;
    foreach (array_keys($openLinkedIssues) as $issueNumber) {
      $openTestingIssues[(int) $issueNumber] = TRUE;
    }
    $openIssueCount = count($openTestingIssues);

    $signals = [
      'open_prs' => $openPrs,
      'draft_prs' => $draftPrs,
      'queue_items' => $queueItems,
      'pending_runs' => $pendingRuns,
      'running_runs' => $runningRuns,
      'passed_runs' => $passedRuns,
      'failed_runs' => $failedRuns,
      'open_linked_issues' => $openIssueCount,
      'blocked_stages' => $blockedStages,
    ];

    $sdlcCheckpoint = $this->inferSdlcCheckpoint($signals);
    $releaseCheckpoint = $this->inferReleaseCheckpoint($signals);
    $releasePendingItem = $this->inferReleasePendingItem($releaseCheckpoint, $signals);
    $isReleaseBlockedBySdlc = ($signals['blocked_stages'] ?? 0) > 0 || ($signals['open_linked_issues'] ?? 0) > 0;
    $releaseBlockedStep = $isReleaseBlockedBySdlc
      ? $sdlcCheckpoint
      : (string) $this->t('None (release is not currently blocked by SDLC).');

    $signalItems = [
      $this->t('Open PRs: @count (draft: @draft)', ['@count' => $openPrs, '@draft' => $draftPrs]),
      $this->t('Queue depth: @count', ['@count' => $queueItems]),
      $this->t('Runs pending/running: @pending/@running', ['@pending' => $pendingRuns, '@running' => $runningRuns]),
      $this->t('Latest run outcomes (tracked): pass @pass / fail @fail', ['@pass' => $passedRuns, '@fail' => $failedRuns]),
      $this->t('Blocked stages: @count', ['@count' => $blockedStages]),
      $this->t('Open testing issues: @count', ['@count' => $openIssueCount]),
    ];

    if (!empty($prSummary['error'])) {
      $signalItems[] = $this->t('PR signal warning: @msg', ['@msg' => $prSummary['error']]);
    }

    if (!empty($prAutomation['error'])) {
      $signalItems[] = $this->t('PR automation signal warning: @msg', ['@msg' => $prAutomation['error']]);
    }

    $workflowStatusItems = [];
    $workflowStatusItems[] = $this->formatWorkflowSummaryLine($this->t('Auto-ready workflow'), $latestAutoReadyRun);
    $workflowStatusItems[] = $this->formatWorkflowSummaryLine($this->t('Merge-to-testing workflow'), $latestMergeRun);

    $automationMetricItems = [
      $this->t('Copilot-managed open PRs: @count', ['@count' => (int) ($prAutomation['copilot_open_prs'] ?? 0)]),
      $this->t('Eligible to auto-merge into testing now: @count', ['@count' => (int) ($prAutomation['eligible_now'] ?? 0)]),
      $this->t('Skipped now (draft/base/check-state): @count', ['@count' => (int) ($prAutomation['skipped_now'] ?? 0)]),
      $this->t('Skipped drafts: @count', ['@count' => (int) ($prAutomation['skipped_draft'] ?? 0)]),
      $this->t('Skipped non-main base: @count', ['@count' => (int) ($prAutomation['skipped_non_main'] ?? 0)]),
      $this->t('Skipped unclean/unknown merge state: @count', ['@count' => (int) ($prAutomation['skipped_merge_state'] ?? 0)]),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-flow-tracking']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Process Flow Tracking (Live Inference)'),
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Current SDLC and release checkpoints inferred from queue/runs/stage-state + GitHub PR signals.'),
      ],
      'release_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Release Flow Status'),
        ],
        'checkpoint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Current checkpoint: @cp', ['@cp' => $releaseCheckpoint]),
        ],
        'pending' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Pending item: @item', ['@item' => $releasePendingItem]),
        ],
        'blocked_step' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Blocked on SDLC step: @step', ['@step' => $releaseBlockedStep]),
        ],
        'link' => Link::fromTextAndUrl($this->t('View Release Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow'))->toRenderable(),
      ],
      'sdlc_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('SDLC Flow Status'),
        ],
        'checkpoint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Open issue count: @count', ['@count' => $openIssueCount]),
        ],
        'reset_form' => $this->formBuilder()->getForm(SdlcResetForm::class),
        'link' => Link::fromTextAndUrl($this->t('View SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow'))->toRenderable(),
      ],
      'signals_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Signals Used for Inference'),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $signalItems,
        ],
      ],
      'pr_automation_card' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['flow-status-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('PR Automation Status'),
        ],
        'workflow_items' => [
          '#theme' => 'item_list',
          '#items' => $workflowStatusItems,
        ],
        'metrics_items' => [
          '#theme' => 'item_list',
          '#items' => $automationMetricItems,
        ],
        'note' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted-light']],
          '#value' => $this->t('Auto-merge automation merges eligible Copilot-managed PR heads into testing (not main). Main-branch PR merges remain manual unless explicitly executed.'),
        ],
      ],
    ];
  }

  /**
   * Build a one-line workflow status summary.
   */
  private function formatWorkflowSummaryLine(string $label, array $summary): string {
    if (!empty($summary['error'])) {
      return (string) $this->t('@label: unavailable (@error)', [
        '@label' => $label,
        '@error' => (string) $summary['error'],
      ]);
    }

    if (empty($summary['latest'])) {
      return (string) $this->t('@label: no runs found', ['@label' => $label]);
    }

    $latest = is_array($summary['latest']) ? $summary['latest'] : [];
    $status = (string) ($latest['status'] ?? 'unknown');
    $conclusion = (string) ($latest['conclusion'] ?? 'n/a');
    $event = (string) ($latest['event'] ?? 'unknown');
    $updated = (string) ($latest['updated_at'] ?? 'unknown');

    return (string) $this->t('@label: @status / @conclusion (event: @event, updated: @updated)', [
      '@label' => $label,
      '@status' => $status,
      '@conclusion' => $conclusion,
      '@event' => $event,
      '@updated' => $updated,
    ]);
  }

  /**
   * Infer current SDLC checkpoint from live execution signals.
   */
  private function inferSdlcCheckpoint(array $signals): string {
    if (($signals['blocked_stages'] ?? 0) > 0 || ($signals['open_linked_issues'] ?? 0) > 0) {
      return (string) $this->t('BLOCKED');
    }

    if (($signals['running_runs'] ?? 0) > 0 || ($signals['pending_runs'] ?? 0) > 0) {
      return (string) $this->t('CI_VALIDATING');
    }

    if (($signals['open_prs'] ?? 0) > 0) {
      if (($signals['draft_prs'] ?? 0) > 0) {
        return (string) $this->t('IN_DEVELOPMENT / PR_OPEN (drafts present)');
      }
      return (string) $this->t('PR_OPEN / REVIEW_GATE');
    }

    if (($signals['passed_runs'] ?? 0) > 0 && ($signals['failed_runs'] ?? 0) === 0) {
      return (string) $this->t('POST_MERGE_RETEST / DONE-CANDIDATE');
    }

    return (string) $this->t('ISSUE_CREATED / TRIAGED (no active PR/runs detected)');
  }

  /**
   * Infer current Release checkpoint from live execution signals.
   */
  private function inferReleaseCheckpoint(array $signals): string {
    if (($signals['blocked_stages'] ?? 0) > 0 || ($signals['open_linked_issues'] ?? 0) > 0) {
      return (string) $this->t('RESET_REQUIRED');
    }

    if (($signals['open_prs'] ?? 0) > 1) {
      return (string) $this->t('RELEASE_QUEUE_ACTIVE');
    }

    if (($signals['open_prs'] ?? 0) === 1) {
      if (($signals['running_runs'] ?? 0) > 0 || ($signals['pending_runs'] ?? 0) > 0) {
        return (string) $this->t('MERGE_WINDOW / MAINLINE_VALIDATION');
      }
      return (string) $this->t('MERGE_WINDOW (single PR candidate)');
    }

    if (($signals['running_runs'] ?? 0) > 0 || ($signals['pending_runs'] ?? 0) > 0) {
      return (string) $this->t('MAINLINE_VALIDATION');
    }

    if (($signals['passed_runs'] ?? 0) > 0 && ($signals['failed_runs'] ?? 0) === 0) {
      return (string) $this->t('RELEASE_CANDIDATE');
    }

    return (string) $this->t('RELEASE_INTAKE');
  }

  /**
   * Infer current release pending item from release checkpoint and signals.
   */
  private function inferReleasePendingItem(string $releaseCheckpoint, array $signals): string {
    if ($releaseCheckpoint === (string) $this->t('RESET_REQUIRED')) {
      return (string) $this->t('Run controlled reset and reconcile blocker/drift state.');
    }

    if ($releaseCheckpoint === (string) $this->t('RELEASE_QUEUE_ACTIVE')) {
      return (string) $this->t('Select next green PR, update to latest main, and run merge checks.');
    }

    if ($releaseCheckpoint === (string) $this->t('MERGE_WINDOW / MAINLINE_VALIDATION')) {
      return (string) $this->t('Wait for active validation to complete before next serialized merge.');
    }

    if ($releaseCheckpoint === (string) $this->t('MERGE_WINDOW (single PR candidate)')) {
      return (string) $this->t('Complete final merge checks and merge the candidate PR.');
    }

    if ($releaseCheckpoint === (string) $this->t('MAINLINE_VALIDATION')) {
      return (string) $this->t('Complete post-merge validation on main and reconcile results.');
    }

    if ($releaseCheckpoint === (string) $this->t('RELEASE_CANDIDATE')) {
      return (string) $this->t('Approve and promote release candidate.');
    }

    return (string) $this->t('Assemble candidate PR set for the next release window.');
  }

  /**
   * Highlight the /dungeoncrawler/testing/thetest flip hook for automation verification.
   */
  private function buildTheTestCallout(): array {
    $link = Link::fromTextAndUrl(
      $this->t('Open /dungeoncrawler/testing/thetest page'),
      Url::fromRoute('dungeoncrawler_tester.thetest')
    )->toRenderable();
    $link['#attributes']['class'][] = 'queue-link';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['thetest-callout']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Automation flip test (/dungeoncrawler/testing/thetest)'),
      ],
      'body' => [
        '#markup' => '<p>' . $this->t('This page drives the pre-commit stage “Pre-commit: thetest toggle”. Status is controlled by tester state (and optional `TESTER_THETEST_STATUS` env override), so you can switch PASS/FAIL without editing source code. Use this to validate auto-pause, issue linking, and resume flows.') . '</p>',
      ],
      'cta' => $link,
    ];
  }

  /**
   * Build documentation links section.
   */
  private function buildDocumentationSection(): array {
    $links = [
      Link::fromTextAndUrl(
        $this->t('Documentation Home (all tester docs)'),
        Url::fromRoute('dungeoncrawler_tester.documentation_home')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Getting Started'),
        Url::fromRoute('dungeoncrawler_tester.docs_getting_started')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Test Execution Playbook'),
        Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Failure Triage and Issue Workflow'),
        Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Automated Testing Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('SDLC Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Release Process Flow'),
        Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('Tester Queue Management'),
        Url::fromRoute('dungeoncrawler_tester.queue_management')
      )->toRenderable(),
      Link::fromTextAndUrl(
        $this->t('GitHub Issues (testing-related)'),
        Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')
      )->toRenderable(),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['documentation-links']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Test Documentation'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $links,
      ],
    ];
  }

  /**
   * Build test commands section.
   */
  private function buildTestCommandsSection(): array {
    $commands = [
      [
        'title' => $this->t('Run All Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml',
      ],
      [
        'title' => $this->t('Unit Tests Only'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --testsuite=unit',
      ],
      [
        'title' => $this->t('Functional Tests Only'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --testsuite=functional',
      ],
      [
        'title' => $this->t('Route Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/',
      ],
      [
        'title' => $this->t('Controller Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/',
      ],
      [
        'title' => $this->t('API Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=api',
      ],
      [
        'title' => $this->t('Campaign/Entity Tests'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateAccessTest.php web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateValidationTest.php web/modules/custom/dungeoncrawler_tester/tests/src/Functional/EntityLifecycleTest.php',
      ],
      [
        'title' => $this->t('Character Creation Workflow'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=character-creation',
      ],
      [
        'title' => $this->t('PF2e Rules Validation'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --group=pf2e-rules',
      ],
      [
        'title' => $this->t('With Coverage Report'),
        'command' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --coverage-html tests/coverage',
      ],
    ];

    $items = [];
    foreach ($commands as $cmd) {
      $items[] = [
        '#type' => 'container',
        'title' => [
          '#markup' => '<strong>' . $cmd['title'] . '</strong>',
        ],
        'command' => [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => $cmd['command'],
          '#attributes' => ['class' => ['command-snippet']],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['test-commands']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Quick Test Commands'),
      ],
      'description' => [
        '#markup' => '<p>' . $this->t('Copy and paste these commands to run different test suites:') . '</p>',
      ],
      'commands' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['command-list']],
      ],
    ];
  }

  /**
   * Build roadmap list anchored to the dashboard.
   */
  private function buildRoadmapSection(): array {
    $items = [
      $this->t('Add kernel and unit suites for service/business logic.'),
      $this->t('Add integration flows (character + campaign + combat) with real entity fixtures.'),
      $this->t('Surface CI status and last test run results directly on the dashboard.'),
      $this->t('Add quick actions (drush/phpunit presets) and environment checks (config status, tokens present).'),
      $this->t('Add performance and load probes for API endpoints.'),
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-roadmap']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Roadmap (Dashboard as Anchor)'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Fetch issues with specific label filters (with caching).
   */
  private function fetchIssues(string $repo, ?string $token, string $kind): array {
    $labelMap = [
      'ci_failures' => 'ci-failure',
      'testing_defects' => 'testing-defect',
      'program_defects' => 'program-defect',
    ];

    $label = $labelMap[$kind] ?? 'ci-failure';
    
    if (!$token) {
      $this->logger->warning('No GitHub token configured for issue fetching.');
      return ['items' => [], 'error' => $this->t('No GitHub token configured.')];
    }

    // Check cache first
    $cache_key = 'dungeoncrawler_tester.github_issues.' . $repo . '.' . $label;
    $cache = $this->cacheBackend->get($cache_key);
    if ($cache && !empty($cache->data)) {
      return $cache->data;
    }

    $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels=" . urlencode($label) . "&per_page=" . self::GITHUB_MAX_ISSUES;

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return ['items' => [], 'error' => (string) $response['error']];
    }

    $data = is_array($response['items']) ? $response['items'] : [];
    $items = [];
    foreach ($data as $issue) {
      if (!is_array($issue) || empty($issue['title']) || empty($issue['html_url'])) {
        continue;
      }
      $items[] = Link::fromTextAndUrl((string) $issue['title'], Url::fromUri((string) $issue['html_url']))->toRenderable();
    }

    $result = ['items' => $items, 'error' => NULL];
    $this->cacheBackend->set($cache_key, $result, time() + self::GITHUB_CACHE_TTL);
    return $result;
  }

  /**
   * Fetch open pull request summary (with caching).
   */
  private function fetchOpenPullRequestSummary(string $repo, ?string $token, bool $useCache = TRUE): array {
    if (!$token) {
      return [
        'open_count' => 0,
        'draft_count' => 0,
        'error' => $this->t('No GitHub token configured.'),
      ];
    }

    $cache_key = 'dungeoncrawler_tester.github_open_prs.' . $repo;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cache_key);
      if ($cache && !empty($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page=100";

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return [
        'open_count' => 0,
        'draft_count' => 0,
        'error' => (string) $response['error'],
      ];
    }

    $payload = is_array($response['items']) ? $response['items'] : [];
    $open = count($payload);
    $draft = 0;
    foreach ($payload as $pr) {
      if (!empty($pr['draft'])) {
        $draft++;
      }
    }

    $result = [
      'open_count' => $open,
      'draft_count' => $draft,
      'error' => NULL,
    ];
    if ($useCache) {
      $this->cacheBackend->set($cache_key, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Fetch latest workflow run summary by workflow file.
   */
  private function fetchWorkflowRunSummary(string $repo, ?string $token, string $workflowFile, bool $useCache = TRUE): array {
    if (!$token) {
      return [
        'latest' => NULL,
        'error' => (string) $this->t('No GitHub token configured.'),
      ];
    }

    $cacheKey = 'dungeoncrawler_tester.github_workflow_summary.' . $repo . '.' . $workflowFile;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cacheKey);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/actions/workflows/{$workflowFile}/runs?per_page=1";

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return [
        'latest' => NULL,
        'error' => (string) $response['error'],
      ];
    }

    $payload = is_array($response['items']) ? $response['items'] : [];
    $run = $payload['workflow_runs'][0] ?? NULL;

    $result = [
      'latest' => is_array($run) ? [
        'status' => (string) ($run['status'] ?? ''),
        'conclusion' => (string) ($run['conclusion'] ?? ''),
        'event' => (string) ($run['event'] ?? ''),
        'updated_at' => (string) ($run['updated_at'] ?? ''),
        'html_url' => (string) ($run['html_url'] ?? ''),
      ] : NULL,
      'error' => NULL,
    ];

    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Estimate live PR automation eligibility counts for merge-into-testing.
   */
  private function fetchPrAutomationStats(string $repo, ?string $token, bool $useCache = TRUE): array {
    if (!$token) {
      return [
        'copilot_open_prs' => 0,
        'eligible_now' => 0,
        'skipped_now' => 0,
        'skipped_draft' => 0,
        'skipped_non_main' => 0,
        'skipped_merge_state' => 0,
        'error' => (string) $this->t('No GitHub token configured.'),
      ];
    }

    $cacheKey = 'dungeoncrawler_tester.github_pr_automation_stats.' . $repo;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cacheKey);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page=100";

    $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
    if (!empty($response['error'])) {
      return [
        'copilot_open_prs' => 0,
        'eligible_now' => 0,
        'skipped_now' => 0,
        'skipped_draft' => 0,
        'skipped_non_main' => 0,
        'skipped_merge_state' => 0,
        'error' => (string) $response['error'],
      ];
    }

    $payload = is_array($response['items']) ? $response['items'] : [];

    $copilotOpenPrs = 0;
    $eligibleNow = 0;
    $skippedDraft = 0;
    $skippedNonMain = 0;
    $skippedMergeState = 0;

    foreach ($payload as $pr) {
      if (!is_array($pr)) {
        continue;
      }

      $assignees = array_map(
        static fn(array $a): string => strtolower((string) ($a['login'] ?? '')),
        is_array($pr['assignees'] ?? NULL) ? $pr['assignees'] : []
      );
      $reviewers = array_map(
        static fn(array $a): string => strtolower((string) ($a['login'] ?? '')),
        is_array($pr['requested_reviewers'] ?? NULL) ? $pr['requested_reviewers'] : []
      );

      $copilotInvolved = in_array('copilot', $assignees, TRUE) || in_array('copilot', $reviewers, TRUE);
      if (!$copilotInvolved) {
        continue;
      }

      $copilotOpenPrs++;

      if (!empty($pr['draft'])) {
        $skippedDraft++;
        continue;
      }

      $baseRef = (string) ($pr['base']['ref'] ?? '');
      if ($baseRef !== 'main') {
        $skippedNonMain++;
        continue;
      }

      $mergeState = strtolower((string) ($pr['mergeable_state'] ?? 'unknown'));
      if (!in_array($mergeState, ['clean', 'has_hooks'], TRUE)) {
        $skippedMergeState++;
        continue;
      }

      $eligibleNow++;
    }

    $result = [
      'copilot_open_prs' => $copilotOpenPrs,
      'eligible_now' => $eligibleNow,
      'skipped_now' => max(0, $copilotOpenPrs - $eligibleNow),
      'skipped_draft' => $skippedDraft,
      'skipped_non_main' => $skippedNonMain,
      'skipped_merge_state' => $skippedMergeState,
      'error' => NULL,
    ];

    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Fetch open testing-related issue numbers across known labels.
   */
  private function fetchOpenTestingIssueNumbers(string $repo, ?string $token, bool $useCache = TRUE): array {
    if (!$token) {
      return [];
    }

    $cache_key = 'dungeoncrawler_tester.github_open_testing_issue_numbers.' . $repo;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cache_key);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $issueNumbers = [];

    foreach (self::TESTING_ISSUE_LABELS as $label) {
      $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels=" . rawurlencode($label) . '&per_page=' . self::GITHUB_MAX_ISSUES;

      $response = $this->requestGitHubJsonWithFallback($url, [$token], [], FALSE);
      if (!empty($response['error'])) {
        $this->logger->warning('Failed loading open issues for label @label: @message', [
          '@label' => $label,
          '@message' => (string) $response['error'],
        ]);
        continue;
      }

      $payload = is_array($response['items']) ? $response['items'] : [];
      foreach ($payload as $item) {
        if (!is_array($item) || !empty($item['pull_request'])) {
          continue;
        }

        $number = (int) ($item['number'] ?? 0);
        if ($number > 0) {
          $issueNumbers[$number] = TRUE;
        }
      }
    }

    $numbers = array_values(array_map('intval', array_keys($issueNumbers)));
    if ($useCache) {
      $this->cacheBackend->set($cache_key, $numbers, time() + self::GITHUB_CACHE_TTL);
    }

    return $numbers;
  }

  /**
   * Load active queue items for display (limited).
   */
  private function loadQueueItems(): array {
    $queue_items = [];

    $query = $this->database->select('queue', 'q')
      ->fields('q', ['item_id', 'data', 'expire', 'created'])
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->orderBy('created', 'DESC')
      ->range(0, self::MAX_QUEUE_ITEMS);
    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $data = $this->safeUnserializeArray($row->data);
      $preview = $this->getQueueItemPreview($data);
      $queue_items[] = [
        'item_id' => $row->item_id,
        'queue_name' => 'dungeoncrawler_tester_runs',
        'queue_label' => $this->t('Testing Runs'),
        'created' => $row->created,
        'expire' => $row->expire,
        'data' => $data,
        'data_preview' => $preview,
      ];
    }

    usort($queue_items, fn($a, $b) => $b['created'] <=> $a['created']);
    return $queue_items;
  }

  private function getQueueItemPreview($data): array {
    $preview = [];
    if (is_array($data)) {
      if (!empty($data['stage_id'])) {
        $preview['stage'] = $data['stage_id'];
      }
      if (!empty($data['display'])) {
        $preview['command'] = $data['display'];
      }
      if (!empty($data['job_id'])) {
        $preview['job_id'] = $data['job_id'];
      }
    }
    return $preview;
  }

  /**
   * Safely decode a serialized payload into an array.
   */
  private function safeUnserializeArray(mixed $value): array {
    if (!is_string($value) || $value === '') {
      return [];
    }

    set_error_handler(static function (): bool {
      return TRUE;
    });

    try {
      $decoded = unserialize($value, ['allowed_classes' => FALSE]);
    }
    finally {
      restore_error_handler();
    }

    if (!is_array($decoded)) {
      return [];
    }

    return $decoded;
  }

  /**
   * Build queue status for UI.
   */
  private function getQueueStatus(): array {
    $queue_id = 'dungeoncrawler_tester_runs';
    $queue = $this->queueFactory->get($queue_id);

    return [
      $queue_id => [
        'id' => $queue_id,
        'name' => $this->t('Testing Runs'),
        'description' => $this->t('Background execution of dashboard run jobs.'),
        'icon' => '🧪',
        'items' => $queue->numberOfItems(),
      ],
    ];
  }

  /**
   * Fetch last run metadata for a stage.
   */
  private function getLastRun(string $stage_id): ?array {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    return $runs[$stage_id] ?? NULL;
  }

  /**
   * Render last run status block.
   */
  private function buildRunStatus(?array $run): array {
    if (!$run) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-run-status']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Last run'),
        ],
        'content' => [
          '#markup' => '<p>' . $this->t('No runs yet.') . '</p>',
        ],
      ];
    }

    $status = $run['exit_code'] === 0 ? $this->t('Passed') : $this->t('Failed');
    $time = $this->dateFormatter->format($run['ended'], 'short');
    $duration = isset($run['duration']) ? sprintf('%.1fs', $run['duration']) : '';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-run-status']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Last run'),
      ],
      'content' => [
        '#markup' => '<p><strong>' . $status . '</strong> · ' . $time . ' · ' . $duration . '</p>',
      ],
      'log' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $run['output'] ?? '',
        '#attributes' => ['class' => ['command-snippet', 'command-log']],
      ],
    ];
  }

  /**
   * Render issue list container.
   */
  private function renderIssueList(array $data): array {
    if (!empty($data['error'])) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['issue-card']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $data['label'] ?? $this->t('Issues'),
        ],
        'error' => [
          '#markup' => '<div class="messages messages--error">' . $data['error'] . '</div>',
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['issue-card']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $data['label'] ?? $this->t('Issues'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $data['items'] ?? [],
        '#empty' => $this->t('No open issues for this category.'),
      ],
    ];
  }

  /**
   * Resolve GitHub repo/token from existing tester settings precedence.
   */
  protected function resolveGitHubContext(): array {
    $context = $this->githubClient->resolveContext();
    $repo = (string) ($context['repo'] ?? $this->defaultRepo);
    $tokenCandidates = array_values((array) ($context['token_candidates'] ?? []));
    $token = $tokenCandidates[0] ?? NULL;

    return [
      'repo' => $repo,
      'token' => $token ? (string) $token : NULL,
      'token_candidates' => $tokenCandidates,
    ];
  }

  /**
   * Fetch open issues for reporting.
   */
  protected function fetchOpenIssuesForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
    if (empty($tokenCandidates)) {
      return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
    }

    $cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.open_issues.' . $repo;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cacheKey);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/issues?state=open&per_page=100";
    $response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
    if (!empty($response['error'])) {
      return ['items' => [], 'error' => $response['error']];
    }

    $items = [];
    foreach ($response['items'] as $issue) {
      if (!is_array($issue) || !empty($issue['pull_request'])) {
        continue;
      }

      $labels = [];
      foreach ((array) ($issue['labels'] ?? []) as $label) {
        if (is_array($label) && !empty($label['name'])) {
          $labels[] = (string) $label['name'];
        }
      }

      $assignees = [];
      foreach ((array) ($issue['assignees'] ?? []) as $assignee) {
        if (is_array($assignee) && !empty($assignee['login'])) {
          $assignees[] = (string) $assignee['login'];
        }
      }

      $updatedAt = (string) ($issue['updated_at'] ?? '');
      $updatedTs = $updatedAt !== '' ? strtotime($updatedAt) : FALSE;
      $staleDays = is_int($updatedTs) ? (int) floor((time() - $updatedTs) / 86400) : 0;

      $items[] = [
        'number' => (int) ($issue['number'] ?? 0),
        'title' => (string) ($issue['title'] ?? ''),
        'html_url' => (string) ($issue['html_url'] ?? ''),
        'labels' => $labels,
        'assignees' => $assignees,
        'updated_at' => $updatedAt,
        'stale_days' => max(0, $staleDays),
      ];
    }

    $result = ['items' => $items, 'error' => NULL];
    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }
    return $result;
  }

  /**
   * Fetch open pull requests for reporting.
   */
  protected function fetchOpenPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
    if (empty($tokenCandidates)) {
      return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
    }

    $cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.open_prs.' . $repo;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cacheKey);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page=100";
    $response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
    if (!empty($response['error'])) {
      return ['items' => [], 'error' => $response['error']];
    }

    $items = [];
    foreach ($response['items'] as $pr) {
      if (!is_array($pr)) {
        continue;
      }

      $items[] = [
        'number' => (int) ($pr['number'] ?? 0),
        'title' => (string) ($pr['title'] ?? ''),
        'html_url' => (string) ($pr['html_url'] ?? ''),
        'draft' => !empty($pr['draft']),
        'base_ref' => (string) ($pr['base']['ref'] ?? ''),
        'head_ref' => (string) ($pr['head']['ref'] ?? ''),
        'mergeable_state' => strtolower((string) ($pr['mergeable_state'] ?? 'unknown')),
        'changed_files' => (int) ($pr['changed_files'] ?? 0),
        'additions' => (int) ($pr['additions'] ?? 0),
        'deletions' => (int) ($pr['deletions'] ?? 0),
        'body' => (string) ($pr['body'] ?? ''),
      ];
    }

    $result = ['items' => $items, 'error' => NULL];
    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }
    return $result;
  }

  /**
   * Fetch closed pull requests for merged-reference analysis.
   */
  protected function fetchClosedPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
    if (empty($tokenCandidates)) {
      return ['items' => [], 'error' => (string) $this->t('No GitHub token configured.')];
    }

    $cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.closed_prs.' . $repo;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cacheKey);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/pulls?state=closed&per_page=100";
    $response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [], TRUE);
    if (!empty($response['error'])) {
      return ['items' => [], 'error' => $response['error']];
    }

    $items = [];
    foreach ($response['items'] as $pr) {
      if (!is_array($pr)) {
        continue;
      }

      $items[] = [
        'number' => (int) ($pr['number'] ?? 0),
        'title' => (string) ($pr['title'] ?? ''),
        'body' => (string) ($pr['body'] ?? ''),
        'merged_at' => (string) ($pr['merged_at'] ?? ''),
      ];
    }

    $result = ['items' => $items, 'error' => NULL];
    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Fetch linked open PR numbers from an issue timeline.
   */
  protected function fetchLinkedOpenPrNumbersForIssueFromTimeline(string $repo, array $tokenCandidates, int $issueNumber, array $openPrByNumber, bool $useCache = TRUE): array {
    if (empty($tokenCandidates) || $issueNumber <= 0) {
      return [];
    }

    $cacheKey = 'dungeoncrawler_tester.github_issue_pr_report.issue_timeline_links.' . $repo . '.' . $issueNumber;
    if ($useCache) {
      $cache = $this->cacheBackend->get($cacheKey);
      if ($cache && !empty($cache->data) && is_array($cache->data)) {
        return $cache->data;
      }
    }

    $url = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/timeline?per_page=100";
    $response = $this->requestGitHubJsonWithFallback($url, $tokenCandidates, [
      'Accept' => 'application/vnd.github+json',
      'X-GitHub-Api-Version' => '2022-11-28',
    ], TRUE);

    if (!empty($response['error'])) {
      return [];
    }

    $linkedPrNumbers = [];
    foreach ($response['items'] as $event) {
      if (!is_array($event)) {
        continue;
      }

      $eventType = (string) ($event['event'] ?? '');
      if ($eventType !== 'cross-referenced' && $eventType !== 'connected') {
        continue;
      }

      $sourceIssue = $event['source']['issue'] ?? NULL;
      if (!is_array($sourceIssue) || empty($sourceIssue['pull_request'])) {
        continue;
      }

      $prNumber = (int) ($sourceIssue['number'] ?? 0);
      if ($prNumber > 0 && isset($openPrByNumber[$prNumber])) {
        $linkedPrNumbers[$prNumber] = TRUE;
      }
    }

    $result = array_values(array_map('intval', array_keys($linkedPrNumbers)));
    if ($useCache) {
      $this->cacheBackend->set($cacheKey, $result, time() + self::GITHUB_CACHE_TTL);
    }

    return $result;
  }

  /**
   * Execute a GitHub API JSON request and normalize response shape.
   */
  protected function requestGitHubJson(string $url, ?string $token, array $extraHeaders = []): array {
    return $this->githubClient->requestJson($url, $token, $extraHeaders, FALSE);
  }

  /**
   * Execute GitHub JSON request with token failover.
   */
  protected function requestGitHubJsonWithFallback(string $url, array $tokenCandidates, array $extraHeaders = [], bool $paginate = FALSE): array {
    return $this->githubClient->requestJsonWithFallback($url, $tokenCandidates, $extraHeaders, $paginate);
  }

  /**
   * Extract issue number references from a PR title/body.
   */
  protected function extractIssueReferencesFromPr(array $pr): array {
    $references = [];
    $text = trim(((string) ($pr['title'] ?? '')) . "\n" . ((string) ($pr['body'] ?? '')));
    if ($text === '') {
      return [];
    }

    preg_match_all('/#(\d+)/', $text, $matches);
    foreach ($matches[1] ?? [] as $value) {
      $number = (int) $value;
      if ($number > 0) {
        $references[$number] = TRUE;
      }
    }

    return array_values(array_map('intval', array_keys($references)));
  }

  /**
   * Check whether a PR is already linked in an issue group.
   */
  protected function isPrAlreadyLinkedToIssue(array $linkedPrs, array $candidatePr): bool {
    $candidateNumber = (int) ($candidatePr['number'] ?? 0);
    if ($candidateNumber <= 0) {
      return FALSE;
    }

    foreach ($linkedPrs as $existingPr) {
      if ((int) ($existingPr['number'] ?? 0) === $candidateNumber) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Determine blocking conditions for a PR.
   */
  protected function describePrBlockers(array $pr): array {
    $blockers = [];

    if (!empty($pr['draft'])) {
      $blockers[] = (string) $this->t('Draft PR');
    }

    $baseRef = (string) ($pr['base_ref'] ?? '');
    if ($baseRef !== '' && $baseRef !== 'main') {
      $blockers[] = (string) $this->t('Base branch is @base (expected main)', ['@base' => $baseRef]);
    }

    $mergeableState = (string) ($pr['mergeable_state'] ?? 'unknown');
    if ($mergeableState !== '' && $mergeableState !== 'unknown' && !in_array($mergeableState, ['clean', 'has_hooks'], TRUE)) {
      $blockers[] = (string) $this->t('Merge state is @state', ['@state' => $mergeableState]);
    }

    return $blockers;
  }

  /**
   * Suggest next step for PR progression based on blockers.
   */
  protected function suggestPrNextStep(array $pr, array $blockers): string {
    if (!empty($pr['draft'])) {
      return (string) $this->t('Move PR out of draft when ready for review.');
    }

    $baseRef = (string) ($pr['base_ref'] ?? '');
    if ($baseRef !== '' && $baseRef !== 'main') {
      return (string) $this->t('Retarget or rebase PR onto main before merge queue checks.');
    }

    if (!empty($blockers)) {
      return (string) $this->t('Resolve blockers, rerun checks, and re-evaluate mergeability.');
    }

    return (string) $this->t('Request/complete review and merge when checks are green.');
  }

  /**
   * Determine if PR has no effective code value compared to main.
   */
  protected function isDeadValuePr(array $pr): bool {
    $baseRef = (string) ($pr['base_ref'] ?? '');
    $changedFiles = (int) ($pr['changed_files'] ?? 0);
    $additions = (int) ($pr['additions'] ?? 0);
    $deletions = (int) ($pr['deletions'] ?? 0);

    return $baseRef === 'main'
      && $changedFiles === 0
      && $additions === 0
      && $deletions === 0;
  }

  /**
   * Execute a GitHub mutation request with JSON payload.
   */
  protected function requestGitHubMutation(string $method, string $url, string $token, array $json): bool {
    $ok = $this->githubClient->mutate($method, $url, $json, $token, self::GITHUB_API_TIMEOUT);
    if (!$ok) {
      $this->logger->error('Dead-value close mutation failed for @url.', [
        '@url' => $url,
      ]);
    }
    return $ok;
  }

}
