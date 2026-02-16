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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Testing dashboard with stagegates and GitHub failure surfacing.
 */
class TestingDashboardController extends ControllerBase {

  /**
   * Standard close comment for dead-value PR cleanup.
   */
  private const DEAD_VALUE_COMMENT = 'Dead value: this PR has no diff from main and no changed files. Closing this PR and associated issue.';

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
   * Standard close comment for bulk no-action cleanup.
   */
  private const BULK_CLOSE_COMMENT = 'Bulk close from testing issue/PR report: no additional implementation action required.';

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
   * Render open issue/PR report grouped by issue with orphaned PR section.
   */
  public function issuePrReport(): array {
    $githubContext = $this->resolveGitHubContext();
    $repo = $githubContext['repo'];
    $tokenCandidates = $githubContext['token_candidates'] ?? [];

    $issuePayload = $this->fetchOpenIssuesForReport($repo, $tokenCandidates, FALSE);
    $prPayload = $this->fetchOpenPullRequestsForReport($repo, $tokenCandidates, FALSE);

    $issues = $issuePayload['items'] ?? [];
    $prs = $prPayload['items'] ?? [];

    usort($issues, static fn(array $left, array $right): int => ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0)));
    usort($prs, static fn(array $left, array $right): int => ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0)));

    $openIssueNumbers = [];
    foreach ($issues as $issue) {
      $openIssueNumbers[(int) ($issue['number'] ?? 0)] = TRUE;
    }

    $openPrByNumber = [];
    foreach ($prs as $pr) {
      $prNumber = (int) ($pr['number'] ?? 0);
      if ($prNumber > 0) {
        $openPrByNumber[$prNumber] = $pr;
      }
    }

    $linkedPrsByIssue = [];
    $strictIssueNumbersByPr = [];

    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber <= 0) {
        continue;
      }

      $timelineLinkedPrs = $this->fetchLinkedOpenPrNumbersForIssueFromTimeline($repo, $tokenCandidates, $issueNumber, $openPrByNumber, FALSE);
      foreach ($timelineLinkedPrs as $prNumber) {
        if (isset($openPrByNumber[$prNumber])) {
          $linkedPrsByIssue[$issueNumber][] = $openPrByNumber[$prNumber];
          $strictIssueNumbersByPr[$prNumber][$issueNumber] = TRUE;
        }
      }
    }

    $orphanedPrs = [];
    foreach ($prs as $pr) {
      $prNumber = (int) ($pr['number'] ?? 0);
      $references = [];
      if ($prNumber > 0 && !empty($strictIssueNumbersByPr[$prNumber])) {
        $references = array_values(array_map('intval', array_keys($strictIssueNumbersByPr[$prNumber])));
      }
      else {
        $references = $this->extractIssueReferencesFromPr($pr);
      }

      $linked = [];
      foreach ($references as $issueNumber) {
        if (!empty($openIssueNumbers[$issueNumber])) {
          $linked[$issueNumber] = TRUE;
        }
      }

      if (!empty($linked)) {
        foreach (array_keys($linked) as $issueNumber) {
          if (!$this->isPrAlreadyLinkedToIssue($linkedPrsByIssue[$issueNumber] ?? [], $pr)) {
            $linkedPrsByIssue[$issueNumber][] = $pr;
          }
        }
      }
      else {
        $orphanedPrs[] = $pr;
      }
    }

    $issueItems = [];
    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber <= 0) {
        continue;
      }

      $issueUrl = (string) ($issue['html_url'] ?? '');
      $issueTitle = (string) ($issue['title'] ?? '');

      $linkedPrs = $linkedPrsByIssue[$issueNumber] ?? [];
      $linkedPrItems = [];
      $issueConcerns = [];
      $issueNextSteps = [];

      if (empty($linkedPrs)) {
        $issueConcerns[] = (string) $this->t('No linked open PR.');
        $issueNextSteps[] = (string) $this->t('Create or relink a PR and include "Fixes #@issue" in the PR description.', ['@issue' => $issueNumber]);
      }

      if (($issue['stale_days'] ?? 0) >= 14) {
        $issueConcerns[] = (string) $this->t('Issue has not been updated in @days days.', ['@days' => (int) $issue['stale_days']]);
        $issueNextSteps[] = (string) $this->t('Reconfirm owner and post status update.');
      }

      foreach ($linkedPrs as $pr) {
        $prNumber = (int) ($pr['number'] ?? 0);
        $prTitle = (string) ($pr['title'] ?? '');
        $prUrl = (string) ($pr['html_url'] ?? '');

        $blockers = $this->describePrBlockers($pr);
        $nextStep = $this->suggestPrNextStep($pr, $blockers);
        $baseRef = (string) ($pr['base_ref'] ?? '');
        $headRef = (string) ($pr['head_ref'] ?? '');
        $changeSummary = $this->t('@files files, +@add/-@del', [
          '@files' => (int) ($pr['changed_files'] ?? 0),
          '@add' => (int) ($pr['additions'] ?? 0),
          '@del' => (int) ($pr['deletions'] ?? 0),
        ]);

        $line = [
          '#type' => 'container',
          '#attributes' => ['class' => ['issue-report-pr-item']],
          'pr' => Link::fromTextAndUrl($this->t('PR #@number: @title', ['@number' => $prNumber, '@title' => $prTitle]), Url::fromUri($prUrl))->toRenderable(),
          'details' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => ['text-muted-light']],
            '#value' => (string) $this->t('base: @base · head: @head · diff: @diff', ['@base' => $baseRef, '@head' => $headRef, '@diff' => $changeSummary]),
          ],
        ];

        if (!empty($blockers)) {
          $line['blockers'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => ['class' => ['text-muted-light']],
            '#value' => (string) $this->t('Blockers: @blockers', ['@blockers' => implode('; ', $blockers)]),
          ];
          foreach ($blockers as $blocker) {
            $issueConcerns[] = $blocker;
          }
        }

        $line['next'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['text-muted-light']],
          '#value' => (string) $this->t('Next: @next', ['@next' => $nextStep]),
        ];

        if ($this->isDeadValuePr($pr)) {
          $line['dead_close_action'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['issue-report-actions', 'issue-report-item-actions']],
            'button' => [
              '#type' => 'html_tag',
              '#tag' => 'button',
              '#attributes' => [
                'type' => 'button',
                'class' => ['button', 'button--small', 'dc-dead-close-btn'],
                'data-pr-number' => (string) $prNumber,
                'data-issue-number' => (string) $issueNumber,
              ],
              '#value' => (string) $this->t('Close dead PR + issue'),
            ],
          ];
        }

        $linkedPrItems[] = $line;
      }

      if (empty($issueNextSteps) && !empty($linkedPrs)) {
        $issueNextSteps[] = (string) $this->t('Advance linked PR through review and merge checks.');
      }

      $issueItems[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['issue-card', 'issue-report-item']],
        'issue' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          'link' => Link::fromTextAndUrl($this->t('#@number @title', ['@number' => $issueNumber, '@title' => $issueTitle]), Url::fromUri($issueUrl))->toRenderable(),
        ],
        'linked_prs_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Linked open PRs'),
        ],
        'linked_prs' => [
          '#theme' => 'item_list',
          '#items' => $linkedPrItems,
          '#empty' => $this->t('No linked open PRs.'),
        ],
        'state_blockers_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('State / Blockers'),
        ],
        'state_blockers' => [
          '#theme' => 'item_list',
          '#items' => !empty($issueConcerns) ? array_values(array_unique($issueConcerns)) : [(string) $this->t('No immediate blockers detected.')],
        ],
        'next_steps_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Next Step'),
        ],
        'next_steps' => [
          '#theme' => 'item_list',
          '#items' => !empty($issueNextSteps) ? array_values(array_unique($issueNextSteps)) : [(string) $this->t('No action required.')],
        ],
      ];
    }

    $orphanedItems = [];
    foreach ($orphanedPrs as $pr) {
      $blockers = $this->describePrBlockers($pr);

      $orphanedItems[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['issue-card', 'issue-report-item']],
        'pr' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          'link' => Link::fromTextAndUrl($this->t('PR #@number: @title', [
            '@number' => (int) ($pr['number'] ?? 0),
            '@title' => (string) ($pr['title'] ?? ''),
          ]), Url::fromUri((string) ($pr['html_url'] ?? '')))->toRenderable(),
        ],
        'base_head' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['text-muted-light']],
          '#value' => (string) $this->t('base: @base · head: @head · diff: @files files, +@add/-@del', [
            '@base' => (string) ($pr['base_ref'] ?? ''),
            '@head' => (string) ($pr['head_ref'] ?? ''),
            '@files' => (int) ($pr['changed_files'] ?? 0),
            '@add' => (int) ($pr['additions'] ?? 0),
            '@del' => (int) ($pr['deletions'] ?? 0),
          ]),
        ],
        'blockers_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Blockers'),
        ],
        'blockers' => [
          '#theme' => 'item_list',
          '#items' => !empty($blockers) ? $blockers : [(string) $this->t('No immediate blockers detected.')],
        ],
        'next_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h4',
          '#value' => $this->t('Next Step'),
        ],
        'next' => [
          '#theme' => 'item_list',
          '#items' => [(string) $this->suggestPrNextStep($pr, $blockers)],
        ],
      ];

      if ($this->isDeadValuePr($pr)) {
        $orphanedItems[array_key_last($orphanedItems)]['actions'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['issue-report-actions', 'issue-report-item-actions']],
          'button' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#attributes' => [
              'type' => 'button',
              'class' => ['button', 'button--small', 'dc-dead-close-btn'],
              'data-pr-number' => (string) ((int) ($pr['number'] ?? 0)),
              'data-issue-number' => '0',
            ],
            '#value' => (string) $this->t('Close dead PR'),
          ],
        ];
      }
    }

    $metaItems = [
      $this->t('Repository: @repo', ['@repo' => $repo]),
      $this->t('Open issues: @count', ['@count' => count($issues)]),
      $this->t('Open PRs: @count', ['@count' => count($prs)]),
      $this->t('Orphaned PRs: @count', ['@count' => count($orphanedPrs)]),
      $this->t('Linking strategy: issue timeline cross-references first, PR text fallback second.'),
      $this->t('Generated: @time', ['@time' => $this->dateFormatter->format(time(), 'short')]),
    ];

    if (!empty($issuePayload['error'])) {
      $metaItems[] = $this->t('Issue fetch warning: @msg', ['@msg' => (string) $issuePayload['error']]);
    }
    if (!empty($prPayload['error'])) {
      $metaItems[] = $this->t('PR fetch warning: @msg', ['@msg' => (string) $prPayload['error']]);
    }

    $bulkQuerySection = $this->buildBulkCloseQuerySection($repo, $issues, $prs, $tokenCandidates);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['tester-issue-pr-report', 'dungeoncrawler-testing-dashboard']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      '#attached' => [
        'library' => [
          'dungeoncrawler_tester/dashboard',
        ],
        'drupalSettings' => [
          'dungeoncrawlerTester' => [
            'csrfToken' => $this->csrfToken->get('rest'),
            'routes' => [
              'deadClose' => $this->safeRouteUrl('dungeoncrawler_tester.dead_value_close', '/dungeoncrawler/testing/issue-pr-report/dead-value-close'),
              'bulkCloseQuery' => $this->safeRouteUrl('dungeoncrawler_tester.bulk_close_query_run', '/dungeoncrawler/testing/issue-pr-report/bulk-close-query-run'),
            ],
          ],
        ],
      ],
      'bulk_queries' => $bulkQuerySection,
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Open issue-first report with linked PRs, blockers, and next steps. Uses existing GitHub repo issue/pull endpoints already used by dashboard signals.'),
      ],
      'decision_logic' => $this->buildIssuePrReportDecisionLogicSection(),
      'meta' => [
        '#theme' => 'item_list',
        '#items' => $metaItems,
      ],
      'issues_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Open Issues (with linked PRs)'),
      ],
      'issues_list' => [
        '#theme' => 'item_list',
        '#items' => $issueItems,
        '#empty' => $this->t('No open issues found.'),
      ],
      'orphaned_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Orphaned Open PRs'),
      ],
      'orphaned_help' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['text-muted-light']],
        '#value' => $this->t('Orphaned PRs are open PRs without a detected reference to any currently open issue.'),
      ],
      'orphaned_list' => [
        '#theme' => 'item_list',
        '#items' => $orphanedItems,
        '#empty' => $this->t('No orphaned open PRs found.'),
      ],
    ];
  }

  /**
   * Build a URL from route name with a safe path fallback.
   */
  private function safeRouteUrl(string $routeName, string $fallbackPath): string {
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
  private function buildIssuePrReportDecisionLogicSection(): array {
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
  private function buildBulkCloseQuerySection(string $repo, array $issues, array $prs, array $tokenCandidates): array {
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
  private function buildBulkCloseQueryDefinitions(string $repo, array $issues, array $prs, array $tokenCandidates): array {
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
   * AJAX: run one bulk-close query and execute close mutations.
   */
  public function runBulkCloseQueryAjax(Request $request): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      $payload = [];
    }

    $queryId = trim((string) ($payload['query_id'] ?? ''));
    if ($queryId === '') {
      return new JsonResponse(['success' => FALSE, 'message' => 'Missing query id.'], 400);
    }

    $githubContext = $this->resolveGitHubContext();
    $repo = $githubContext['repo'];
    $token = $githubContext['token'];
    $tokenCandidates = $githubContext['token_candidates'] ?? [];

    if (!$token || empty($tokenCandidates)) {
      return new JsonResponse(['success' => FALSE, 'message' => 'GitHub token is not configured.'], 400);
    }

    $issuePayload = $this->fetchOpenIssuesForReport($repo, $tokenCandidates, FALSE);
    $prPayload = $this->fetchOpenPullRequestsForReport($repo, $tokenCandidates, FALSE);
    $issues = $issuePayload['items'] ?? [];
    $prs = $prPayload['items'] ?? [];

    $openIssueNumbers = [];
    foreach ($issues as $issue) {
      $issueNumber = (int) ($issue['number'] ?? 0);
      if ($issueNumber > 0) {
        $openIssueNumbers[$issueNumber] = TRUE;
      }
    }

    $result = [
      'prs_closed' => 0,
      'issues_closed' => 0,
      'errors' => [],
    ];

    switch ($queryId) {
      case 'dead_value_prs':
        $candidates = $this->collectDeadValuePrCandidates($repo, $prs, $tokenCandidates, $openIssueNumbers);
        foreach ($candidates as $candidate) {
          $prNumber = (int) ($candidate['pr_number'] ?? 0);
          if ($prNumber <= 0) {
            continue;
          }

          $prCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
          $prClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token, ['state' => 'closed']);
          if ($prCommented && $prClosed) {
            $result['prs_closed']++;
          }
          else {
            $result['errors'][] = "PR #{$prNumber}";
          }

          foreach ($candidate['issue_numbers'] ?? [] as $issueNumber) {
            $issueNumber = (int) $issueNumber;
            if ($issueNumber <= 0) {
              continue;
            }
            $issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
            $issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
            if ($issueCommented && $issueClosed) {
              $result['issues_closed']++;
            }
            else {
              $result['errors'][] = "Issue #{$issueNumber}";
            }
          }
        }
        break;

      case 'issues_resolved_by_merged_pr':
        $issueNumbers = $this->collectOpenIssuesReferencedByMergedPrs($repo, $issues, $tokenCandidates);
        foreach ($issueNumbers as $issueNumber) {
          $issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
          $issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
          if ($issueCommented && $issueClosed) {
            $result['issues_closed']++;
          }
          else {
            $result['errors'][] = "Issue #{$issueNumber}";
          }
        }
        break;

      case 'non_action_labeled_issues':
        $issueNumbers = $this->collectNonActionOpenIssues($issues);
        foreach ($issueNumbers as $issueNumber) {
          $issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
          $issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
          if ($issueCommented && $issueClosed) {
            $result['issues_closed']++;
          }
          else {
            $result['errors'][] = "Issue #{$issueNumber}";
          }
        }
        break;

      case 'open_prs_with_only_closed_issue_refs':
        $prNumbers = $this->collectOpenPrsReferencingOnlyClosedIssues($prs, $openIssueNumbers);
        foreach ($prNumbers as $prNumber) {
          $prCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$prNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
          $prClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token, ['state' => 'closed']);
          if ($prCommented && $prClosed) {
            $result['prs_closed']++;
          }
          else {
            $result['errors'][] = "PR #{$prNumber}";
          }
        }
        break;

      case 'stale_unassigned_testing_issues':
        $issueNumbers = $this->collectStaleUnassignedTestingIssues($issues);
        foreach ($issueNumbers as $issueNumber) {
          $issueCommented = $this->requestGitHubMutation('POST', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments", $token, ['body' => self::BULK_CLOSE_COMMENT]);
          $issueClosed = $this->requestGitHubMutation('PATCH', "https://api.github.com/repos/{$repo}/issues/{$issueNumber}", $token, ['state' => 'closed']);
          if ($issueCommented && $issueClosed) {
            $result['issues_closed']++;
          }
          else {
            $result['errors'][] = "Issue #{$issueNumber}";
          }
        }
        break;

      default:
        return new JsonResponse(['success' => FALSE, 'message' => 'Unknown bulk query id.'], 400);
    }

    $errorCount = count($result['errors']);
    $message = "Bulk query complete. Closed {$result['prs_closed']} PR(s) and {$result['issues_closed']} issue(s).";
    if ($errorCount > 0) {
      $message .= " {$errorCount} item(s) had errors; check logs.";
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => $message,
      'prs_closed' => $result['prs_closed'],
      'issues_closed' => $result['issues_closed'],
      'errors' => $result['errors'],
    ]);
  }

  /**
   * Collect dead-value PR candidates and referenced open issues.
   */
  private function collectDeadValuePrCandidates(string $repo, array $prs, array $tokenCandidates, array $openIssueNumbers): array {
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
  private function collectOpenIssuesReferencedByMergedPrs(string $repo, array $issues, array $tokenCandidates): array {
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
  private function collectNonActionOpenIssues(array $issues): array {
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
  private function collectOpenPrsReferencingOnlyClosedIssues(array $prs, array $openIssueNumbers): array {
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
  private function collectStaleUnassignedTestingIssues(array $issues): array {
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
  private function fetchPullRequestDetails(string $repo, array $tokenCandidates, int $prNumber): ?array {
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
   * Render tester documentation home page.
   */
  public function documentationHome(): array {
    $coreLinks = [
      Link::fromTextAndUrl($this->t('Getting Started'), Url::fromRoute('dungeoncrawler_tester.docs_getting_started')),
      Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
      Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
      Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
      Link::fromTextAndUrl($this->t('SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')),
      Link::fromTextAndUrl($this->t('Release Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_release_process_flow')),
    ];

    $strategyLinks = [
      Link::fromTextAndUrl($this->t('Legacy: Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_module_readme')),
      Link::fromTextAndUrl($this->t('Legacy: Testing Module README page'), Url::fromRoute('dungeoncrawler_tester.docs_testing_module_readme')),
      Link::fromTextAndUrl($this->t('Legacy: Tests README page'), Url::fromRoute('dungeoncrawler_tester.docs_tests_readme')),
    ];

    $liveLinks = [
      Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
      Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
      Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
      Link::fromTextAndUrl($this->t('Copilot Issue Automation page'), Url::fromRoute('dungeoncrawler_tester.docs_issue_automation')),
      Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
    ];

    $coreItems = $this->renderLinkItems($coreLinks);
    $strategyItems = $this->renderLinkItems($strategyLinks);
    $liveItems = $this->renderLinkItems($liveLinks);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-home']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'header_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Tester Documentation Home'),
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Central entry point for all Dungeon Crawler tester documentation, testing strategy references, and live workflow links.'),
            ],
          ],
          'core_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'core_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Standard Testing Documentation'),
            ],
            'core_list' => [
              '#theme' => 'item_list',
              '#items' => $coreItems,
            ],
          ],
          'strategy_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'strategy_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Legacy Route Aliases (Compatibility)'),
            ],
            'strategy_list' => [
              '#theme' => 'item_list',
              '#items' => $strategyItems,
            ],
          ],
          'live_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'live_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Live Workflow Links'),
            ],
            'live_list' => [
              '#theme' => 'item_list',
              '#items' => $liveItems,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Render consolidated getting started documentation page.
   */
  public function docsGettingStarted(): array {
    return $this->buildDocPage(
      $this->t('Getting Started'),
      $this->t('Standard onboarding path for the Dungeon Crawler testing module.'),
      [
        $this->t('Scope: this module owns test harnesses, test suites, and testing dashboard integrations.'),
        $this->t('Entry points: start at /dungeoncrawler/testing for dashboard controls and linked documentation.'),
        $this->t('Prerequisites: tester settings configured with repository/token when failure issue automation is needed.'),
        $this->t('First run: execute focused tests first, then broader suites as confidence increases.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Test Execution Playbook'), Url::fromRoute('dungeoncrawler_tester.docs_execution_playbook')),
        Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
      ]
    );
  }

  /**
   * Render consolidated test execution playbook.
   */
  public function docsExecutionPlaybook(): array {
    return $this->buildDocPage(
      $this->t('Test Execution Playbook'),
      $this->t('Standard structure for planning and running test stages.'),
      [
        $this->t('Plan by stage: pre-commit checks, focused functional coverage, then full confidence runs.'),
        $this->t('Use dashboard command snippets to keep local runs aligned with expected workflows.'),
        $this->t('On failure, capture output and stage context before rerunning to preserve root-cause evidence.'),
        $this->t('Use queue management to pause, resume, and verify stage progression intentionally.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Testing Dashboard'), Url::fromRoute('dungeoncrawler_tester.dashboard')),
        Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
      ]
    );
  }

  /**
   * Render consolidated failure triage and issue workflow page.
   */
  public function docsFailureTriage(): array {
    return $this->buildDocPage(
      $this->t('Failure Triage and Issue Workflow'),
      $this->t('Standard response flow for failed stages and GitHub issue automation.'),
      [
        $this->t('Triage sequence: identify failing stage, inspect output, validate reproducibility, and scope impact.'),
        $this->t('Issue lifecycle: open issue on failure, attach stage context, and track remediation until stage pass.'),
        $this->t('Assignment behavior: Copilot assignment attempts API identifiers then CLI fallback for compatibility.'),
        $this->t('Operational controls: keep labels consistent for CI failures, testing defects, and program defects.'),
      ],
      [
        Link::fromTextAndUrl($this->t('Tester Settings'), Url::fromRoute('dungeoncrawler_tester.settings')),
        Link::fromTextAndUrl($this->t('GitHub Issues (testing-related)'), Url::fromUri('https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Atesting')),
      ]
    );
  }

  /**
   * Render process flow documentation page.
   */
  public function docsProcessFlow(): array {
    $states = [
      $this->t('INACTIVE (blocked/pause state)'),
      $this->t('READY (eligible to enqueue)'),
      $this->t('PENDING (queued but not yet claimed)'),
      $this->t('RUNNING (worker executing command)'),
      $this->t('SUCCEEDED (completed, no active failure metadata)'),
      $this->t('FAILED (failed run recorded)'),
      $this->t('ISSUE_OPEN (failed + linked open issue)'),
      $this->t('RESUMED (issue closed and state reactivated by sync)'),
    ];

    $events = [
      $this->t('CronTick'),
      $this->t('IssueSyncClosedDetected'),
      $this->t('EnqueueEligibilityPassed'),
      $this->t('WorkerClaimedItem'),
      $this->t('CommandSucceeded'),
      $this->t('CommandFailed'),
      $this->t('IssueCreateSucceeded / IssueCreateFailed'),
      $this->t('CopilotAssignAttempted (REST then CLI fallback)'),
      $this->t('ManualQueueRunRequested'),
      $this->t('TimeoutOccurred (worker/API/CLI budgets)'),
    ];

    $transitions = [
      $this->t('READY + EnqueueEligibilityPassed -> PENDING'),
      $this->t('PENDING + WorkerClaimedItem -> RUNNING'),
      $this->t('RUNNING + CommandSucceeded -> SUCCEEDED'),
      $this->t('RUNNING + CommandFailed -> FAILED'),
      $this->t('FAILED + IssueCreateSucceeded -> ISSUE_OPEN + INACTIVE'),
      $this->t('FAILED + IssueCreateFailed -> INACTIVE'),
      $this->t('ISSUE_OPEN + IssueSyncClosedDetected -> RESUMED -> READY'),
      $this->t('Any eligible queued state + ManualQueueRunRequested -> accelerated claim/process path'),
    ];

    $actions = [
      $this->t('Create queue item in dungeoncrawler_tester_runs and persist pending run metadata.'),
      $this->t('Execute command process with 1800s timeout and store output/duration.'),
      $this->t('On failure, attempt GitHub issue creation and Copilot assignment.'),
      $this->t('Set active=FALSE and failure metadata to block forward progression.'),
      $this->t('Issue sync reactivates stage and clears failure metadata when closed.'),
    ];

    $raceConditionControls = [
      $this->t('Pre-queue guard: stage with pending/running status is not re-enqueued.'),
      $this->t('Queue runner lock prevents concurrent Drush runner collisions.'),
      $this->t('Claim/delete semantics ensure single worker owns a queue item at a time.'),
    ];

    $outOfOrderControls = [
      $this->t('Issue-sync step executes before enqueue in cron hook, reducing stale-open issue ordering errors.'),
      $this->t('Enqueue gate rejects transitions when open issue state has not yet moved to closed.'),
      $this->t('Invalid/early payloads fail gate checks and do not advance state.'),
    ];

    $reliabilityControls = [
      $this->t('Persistent state keys record current run/stage lifecycle for restart recovery.'),
      $this->t('Failure-to-issue linking enables deterministic resume point once issue closes.'),
      $this->t('Timeout budgets bound stuck subprocesses and return control to worker loop.'),
    ];

    $timingItems = [
      $this->t('Cron cadence: module cron integration runs every 10800 seconds (site cron frequency dependent).'),
      $this->t('Issue sync executes first in each cron cycle to reconcile closed issues before enqueue checks.'),
      $this->t('Enqueue cooldown: stage is not re-queued more than once per 3600 seconds unless manually triggered.'),
      $this->t('Worker execution budget: command process timeout is capped at 1800 seconds.'),
      $this->t('Network/API budgets: GitHub calls use short timeouts (8-10s) with CLI fallback budget around 20s.'),
      $this->t('Blocking gate: active open-failure issue keeps a stage paused until issue sync marks it resolved.'),
    ];

    $analysisSteps = [
      $this->t('Enumerate statuses from dungeoncrawler_tester.runs and dungeoncrawler_tester.stage_state.'),
      $this->t('Map happy path: READY -> PENDING -> RUNNING -> SUCCEEDED.'),
      $this->t('Map edge cases: failure without issue creation, issue remains open, timeout while running.'),
      $this->t('Define illegal transitions explicitly (examples below).'),
      $this->t('Classify transitions as deterministic or non-deterministic for testing strategy.'),
    ];

    $illegalTransitions = [
      $this->t('SUCCEEDED -> RUNNING without a fresh enqueue event.'),
      $this->t('INACTIVE/ISSUE_OPEN -> PENDING while open issue lock still exists.'),
      $this->t('PENDING -> SUCCEEDED without RUNNING execution stage.'),
      $this->t('RUNNING -> READY without terminal result (success/failure).'),
    ];

    $transitionRows = [
      [$this->t('READY'), $this->t('EnqueueEligibilityPassed'), $this->t('PENDING'), $this->t('Create queue item + set pending metadata')],
      [$this->t('PENDING'), $this->t('WorkerClaimedItem'), $this->t('RUNNING'), $this->t('Set running + start process')],
      [$this->t('RUNNING'), $this->t('CommandSucceeded'), $this->t('SUCCEEDED'), $this->t('Persist success result + clear failure metadata')],
      [$this->t('RUNNING'), $this->t('CommandFailed'), $this->t('FAILED'), $this->t('Persist failure output + enter failure branch')],
      [$this->t('FAILED'), $this->t('IssueCreateSucceeded'), $this->t('ISSUE_OPEN / INACTIVE'), $this->t('Link issue + pause stage')],
      [$this->t('ISSUE_OPEN'), $this->t('IssueSyncClosedDetected'), $this->t('RESUMED -> READY'), $this->t('Auto-reactivate stage + clear failure state')],
    ];

    $transitionItems = [];
    foreach ($transitionRows as $row) {
      [$currentState, $eventTrigger, $newState, $actionPerformed] = $row;
      $transitionItems[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-3', 'mb-3']],
        'path' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-2']],
          '#value' => '<strong>' . $currentState . '</strong> &nbsp;→&nbsp; <em>' . $eventTrigger . '</em> &nbsp;→&nbsp; <strong>' . $newState . '</strong>',
        ],
        'action' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['mb-0', 'text-muted-light']],
          '#value' => $this->t('Action: @action', ['@action' => $actionPerformed]),
        ],
      ];
    }

    $related = $this->renderLinkItems([
      Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
      Link::fromTextAndUrl($this->t('Tester Queue Management'), Url::fromRoute('dungeoncrawler_tester.queue_management')),
      Link::fromTextAndUrl($this->t('Back to Documentation Home'), Url::fromRoute('dungeoncrawler_tester.documentation_home')),
    ]);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'diagram_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Process Flow Diagram'),
            ],
            'subtitle' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light']],
              '#value' => $this->t('End-to-end flow with blocking gates, queue lifecycle, and resume path.'),
            ],
            'diagram' => [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#attributes' => ['class' => ['command-snippet']],
              '#value' => "[Cron Tick]\n    |\n    v\n[Issue Sync] --(closed issue detected)--> [RESUMED -> READY]\n    |\n    v\n[Enqueue Gate]\n  (active, no open issue, 3600s cooldown passed)\n    |\n    +-- no --> [READY/INACTIVE (no-op)]\n    |\n    +-- yes --> [PENDING (queue item created)]\n                    |\n                    v\n               [RUNNING (worker claim)]\n                    |\n        +-----------+-----------+\n        |                       |\n        v                       v\n [SUCCEEDED]              [FAILED]\n  (clear fail state)         |\n                              v\n                    [Create GitHub Issue]\n                              |\n                      +-------+-------+\n                      |               |\n                      v               v\n            [ISSUE_OPEN/INACTIVE]  [INACTIVE]\n              (paused until closed)  (manual/next sync recovery)\n                      |\n                      v\n        [Issue Sync detects closed issue]\n                      |\n                      v\n                [RESUMED -> READY]",
            ],
          ],
          'summary_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Automated Testing Process Flow'),
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Detailed timeline of scheduler cadence, queue lifecycles, sync/async boundaries, and blocking gates.'),
            ],
          ],
          'timeline_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('2) Core Components (State Machine Model)'),
            ],
            'states_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('States'),
            ],
            'states' => [
              '#theme' => 'item_list',
              '#items' => $states,
            ],
            'events_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Events (Triggers)'),
            ],
            'events' => [
              '#theme' => 'item_list',
              '#items' => $events,
            ],
            'transitions_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Transitions'),
            ],
            'transitions' => [
              '#theme' => 'item_list',
              '#items' => $transitions,
            ],
            'actions_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Actions (Side Effects)'),
            ],
            'actions' => [
              '#theme' => 'item_list',
              '#items' => $actions,
            ],
          ],
          'table_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('3) State Transition Table (Tester Automation)'),
            ],
            'subtitle' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light']],
              '#value' => $this->t('Readable transition blocks: Current State → Event → New State, with side effect action.'),
            ],
            'blocks' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['transition-blocks']],
              'items' => $transitionItems,
            ],
          ],
          'reliability_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('4) Why It Matters for Async Reliability'),
            ],
            'race_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Race Conditions'),
            ],
            'race' => [
              '#theme' => 'item_list',
              '#items' => $raceConditionControls,
            ],
            'order_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Out-of-Order Events'),
            ],
            'order' => [
              '#theme' => 'item_list',
              '#items' => $outOfOrderControls,
            ],
            'recovery_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Reliability and Recovery'),
            ],
            'recovery' => [
              '#theme' => 'item_list',
              '#items' => $reliabilityControls,
            ],
          ],
          'timing_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('5) Timing, Cron, and Queue Timeline Windows'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $timingItems,
            ],
            'flowline' => [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#attributes' => ['class' => ['command-snippet']],
              '#value' => 'CronTick (10800s) -> IssueSync -> EnqueueCheck (3600s gate) -> QueueClaim -> Run (<=1800s) -> Success|Failure -> Pause/Resume Gate',
            ],
          ],
          'blocking_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('6) Illegal Transitions and Determinism'),
            ],
            'analysis_steps_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Analysis Steps Applied'),
            ],
            'analysis_steps' => [
              '#theme' => 'item_list',
              '#items' => $analysisSteps,
            ],
            'illegal_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Illegal Transitions'),
            ],
            'illegal' => [
              '#theme' => 'item_list',
              '#items' => $illegalTransitions,
            ],
            'determinism' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Deterministic segments: core queue state progression. Non-deterministic segments: external GitHub API outcomes, cron invocation timing, and network timeout branches.'),
            ],
          ],
          'related_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Related Links'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $related,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Render SDLC process flow documentation page.
   */
  public function docsSdlcProcessFlow(): array {
    $states = [
      $this->t('ISSUE_CREATED (work item captured in GitHub)'),
      $this->t('TRIAGED (scope, priority, and acceptance criteria confirmed)'),
      $this->t('ASSIGNED (Copilot assignment confirmed by automation)'),
      $this->t('IN_DEVELOPMENT (branch + implementation in progress)'),
      $this->t('PR_OPEN (changes proposed for integration)'),
      $this->t('CI_VALIDATING (automated test and quality gates running)'),
      $this->t('REVIEW_GATE (human review and merge readiness)'),
      $this->t('MERGED_MAIN (approved changes integrated to main)'),
      $this->t('POST_MERGE_RETEST (main branch regression validation)'),
      $this->t('DONE (tests pass and issue closed)'),
      $this->t('BLOCKED (failing gate with linked open issue)'),
    ];

    $events = [
      $this->t('IssueCreated'),
      $this->t('IssueTriaged'),
      $this->t('CopilotAssignmentSucceeded (REST/CLI fallback path)'),
      $this->t('FeatureBranchCreated'),
      $this->t('PullRequestOpened'),
      $this->t('CIPipelinePassed / CIPipelineFailed'),
      $this->t('ReviewApproved / ChangesRequested'),
      $this->t('PRMergedToMain'),
      $this->t('PostMergeRetestPassed / PostMergeRetestFailed'),
      $this->t('IssueClosed'),
    ];

    $transitions = [
      $this->t('ISSUE_CREATED + IssueTriaged -> TRIAGED'),
      $this->t('TRIAGED + CopilotAssignmentSucceeded -> ASSIGNED'),
      $this->t('ASSIGNED + FeatureBranchCreated -> IN_DEVELOPMENT'),
      $this->t('IN_DEVELOPMENT + PullRequestOpened -> PR_OPEN'),
      $this->t('PR_OPEN + CIPipelinePassed -> REVIEW_GATE'),
      $this->t('PR_OPEN + CIPipelineFailed -> BLOCKED'),
      $this->t('BLOCKED + fix commit + PullRequestOpened/CI rerun -> PR_OPEN'),
      $this->t('REVIEW_GATE + ReviewApproved -> MERGED_MAIN'),
      $this->t('MERGED_MAIN + PostMergeRetestPassed -> DONE'),
      $this->t('MERGED_MAIN + PostMergeRetestFailed -> BLOCKED'),
      $this->t('DONE + IssueClosed -> terminal state complete'),
    ];

    $actions = [
      $this->t('Create and maintain a single source-of-truth issue for each SDLC unit of work.'),
      $this->t('Assign to Copilot automatically from tester failure/automation workflow where configured.'),
      $this->t('Use branch-based development and PR-based integration to protect main.'),
      $this->t('Require CI pass before review approval and merge eligibility.'),
      $this->t('Require post-merge re-testing on main before final issue closure.'),
      $this->t('On failure, retain BLOCKED state with traceable issue context until remediation passes all gates.'),
    ];

    $multiPrControls = [
      $this->t('One issue per PR: each PR must link to a single primary issue to avoid mixed lifecycle ownership.'),
      $this->t('Branch isolation: each Copilot task runs on its own feature branch; no direct writes to shared integration branches.'),
      $this->t('Fresh-main requirement: before merge, each PR must be up to date with main (rebase or merge-main) and re-run CI.'),
      $this->t('Serialized merge policy: merge only one approved/green PR at a time, then revalidate remaining open PRs against updated main.'),
      $this->t('Conflict gate: if overlap/conflicts exist, PR returns to IN_DEVELOPMENT and cannot merge until conflict resolution + CI pass.'),
      $this->t('No overwrite rule: protected branch settings require PR merge commits/rebases and block force-push to main.'),
    ];

    $validationRules = [
      $this->t('Open blocker issue or failing PR status keeps state in BLOCKED and prevents merge-to-main transition.'),
      $this->t('PR-level validation must pass first (CI + required checks) before review approval can be applied.'),
      $this->t('After merge, automated tester re-runs on main; only passing post-merge validation allows issue closure.'),
      $this->t('If post-merge validation fails, create/link remediation issue and move flow back to BLOCKED -> PR_OPEN loop.'),
      $this->t('Issue closes only after both PR state is merged and mainline re-test state is green.'),
    ];

    $timingItems = [
      $this->t('Issue creation and triage are usually synchronous human/system events at intake time.'),
      $this->t('Copilot assignment can be asynchronous and should be retried deterministically (REST then CLI fallback).'),
      $this->t('CI and tester queue validations are asynchronous; branch status must gate merge actions.'),
      $this->t('Post-merge retest is a mandatory quality window before issue closure on main.'),
      $this->t('Failure states remain blocking until a successful re-run confirms remediation.'),
    ];

    $analysisSteps = [
      $this->t('Define canonical SDLC gates from issue intake through closure.'),
      $this->t('Map Copilot automation as actor behavior inside standard gates, not a separate lifecycle.'),
      $this->t('Treat CI/retest outcomes as gate conditions for merge and close transitions.'),
      $this->t('For multiple open PRs, enforce merge serialization and mandatory revalidation after each merge.'),
      $this->t('Enforce explicit failure loops back to development/PR instead of shortcut transitions.'),
      $this->t('Classify asynchronous checkpoints and define deterministic retry rules for each.'),
    ];

    $illegalTransitions = [
      $this->t('ISSUE_CREATED -> IN_DEVELOPMENT without triage and assignment.'),
      $this->t('IN_DEVELOPMENT -> MERGED_MAIN without PR, CI pass, and review approval.'),
      $this->t('REVIEW_GATE -> MERGED_MAIN while another PR merged first and this branch has not been revalidated on latest main.'),
      $this->t('MERGED_MAIN -> DONE without post-merge retest pass.'),
      $this->t('BLOCKED -> DONE while failure issue remains unresolved.'),
    ];

    $related = $this->renderLinkItems([
      Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
      Link::fromTextAndUrl($this->t('Failure Triage and Issue Workflow'), Url::fromRoute('dungeoncrawler_tester.docs_failure_triage')),
      Link::fromTextAndUrl($this->t('Back to Documentation Home'), Url::fromRoute('dungeoncrawler_tester.documentation_home')),
    ]);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'diagram_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('SDLC Process Flow Diagram'),
            ],
            'subtitle' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light']],
              '#value' => $this->t('Best-practice SDLC lifecycle with Copilot automation integrated into assignment, implementation, and remediation loops.'),
            ],
            'diagram' => [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#attributes' => ['class' => ['command-snippet']],
              '#value' => "[Issue Created] -> [TRIAGED] -> [ASSIGNED] -> [IN_DEVELOPMENT] -> [PR_OPEN]\n                                      |\n                                      v\n                                [CI_VALIDATING]\n                           +----------+-----------+\n                           | pass                 | fail\n                           v                      v\n                      [REVIEW_GATE]            [BLOCKED]\n                           | approved              | fix + push\n                           v                      +-------> [PR_OPEN]\n                    [MERGE_QUEUE_CHECK]\n             (latest-main? required checks? blocker issue closed?)\n                     +-----+----------------------+\n                     | yes | no\n                     v     v\n               [MERGED_MAIN]   [PR_OPEN / BLOCKED]\n                     |\n                     v\n             [POST_MERGE_RETEST on main]\n                  +----------+-----------+\n                  | pass                 | fail\n                  v                      v\n         [DONE + issue closed]      [BLOCKED -> remediation PR loop]\n\nMultiple open PRs: merge one green PR at a time; all remaining PRs must rebase/update and re-run CI before merge.",
            ],
          ],
          'summary_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('SDLC Process Flow (Best Practice + Copilot Automation)'),
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Standard SDLC state machine: intake, assignment, branch/PR workflow, CI/review gates, merge to main, re-test, and issue closure.'),
            ],
          ],
          'timeline_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('1) Core Components (State Machine Model)'),
            ],
            'states_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('States'),
            ],
            'states' => [
              '#theme' => 'item_list',
              '#items' => $states,
            ],
            'events_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Events (Triggers)'),
            ],
            'events' => [
              '#theme' => 'item_list',
              '#items' => $events,
            ],
            'transitions_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Transitions'),
            ],
            'transitions' => [
              '#theme' => 'item_list',
              '#items' => $transitions,
            ],
            'actions_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Actions (Side Effects)'),
            ],
            'actions' => [
              '#theme' => 'item_list',
              '#items' => $actions,
            ],
          ],
          'timing_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('2) Timing and Blocking Windows'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $timingItems,
            ],
            'flowline' => [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#attributes' => ['class' => ['command-snippet']],
              '#value' => 'IssueCreated -> Triaged -> Assigned -> Branch -> PR -> CI -> Review -> MergeQueueCheck -> MergeMain -> RetestMain -> CloseIssue',
            ],
          ],
          'concurrency_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('3) Multiple Open PRs, Merge Safety, and Validation Gates'),
            ],
            'multi_pr_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Concurrency and No-Overwrite Controls'),
            ],
            'multi_pr_items' => [
              '#theme' => 'item_list',
              '#items' => $multiPrControls,
            ],
            'validation_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('PR Validation vs Open Issue/PR Blocking'),
            ],
            'validation_items' => [
              '#theme' => 'item_list',
              '#items' => $validationRules,
            ],
          ],
          'analysis_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('4) Illegal Transitions and Determinism'),
            ],
            'analysis_steps_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Analysis Steps Applied'),
            ],
            'analysis_steps' => [
              '#theme' => 'item_list',
              '#items' => $analysisSteps,
            ],
            'illegal_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Illegal Transitions'),
            ],
            'illegal' => [
              '#theme' => 'item_list',
              '#items' => $illegalTransitions,
            ],
            'determinism' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Deterministic segments: explicitly gated SDLC progression steps. Non-deterministic segments: external API outcomes, environment variability, and cron/runner timing.'),
            ],
          ],
          'related_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Related Links'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $related,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Render release governance process flow documentation page.
   */
  public function docsReleaseProcessFlow(): array {
    $states = [
      $this->t('RELEASE_INTAKE (candidate PR set identified)'),
      $this->t('RELEASE_QUEUE_ACTIVE (open PRs under merge governance)'),
      $this->t('MERGE_WINDOW (serialized merge execution)'),
      $this->t('MAINLINE_VALIDATION (post-merge test suite running on main)'),
      $this->t('RECONCILIATION (state drift checks and corrections)'),
      $this->t('RELEASE_CANDIDATE (quality gates green, ready to promote)'),
      $this->t('RELEASED (promoted/deployed)'),
      $this->t('RESET_REQUIRED (controlled reset required due to drift/failures)'),
    ];

    $events = [
      $this->t('ReleaseWindowStarted'),
      $this->t('PRSelectedForQueue'),
      $this->t('MergeQueueCheckPassed / MergeQueueCheckFailed'),
      $this->t('PRMergedToMain'),
      $this->t('MainlineRetestPassed / MainlineRetestFailed'),
      $this->t('StateReconcilerRunCompleted'),
      $this->t('DriftThresholdExceeded'),
      $this->t('ControlledResetCompleted'),
      $this->t('ReleaseApproved'),
    ];

    $transitions = [
      $this->t('RELEASE_INTAKE + ReleaseWindowStarted -> RELEASE_QUEUE_ACTIVE'),
      $this->t('RELEASE_QUEUE_ACTIVE + PRSelectedForQueue -> MERGE_WINDOW'),
      $this->t('MERGE_WINDOW + MergeQueueCheckPassed -> PRMergedToMain -> MAINLINE_VALIDATION'),
      $this->t('MERGE_WINDOW + MergeQueueCheckFailed -> RELEASE_QUEUE_ACTIVE (rebase/revalidate required)'),
      $this->t('MAINLINE_VALIDATION + MainlineRetestPassed -> RECONCILIATION'),
      $this->t('MAINLINE_VALIDATION + MainlineRetestFailed -> RESET_REQUIRED'),
      $this->t('RECONCILIATION + StateReconcilerRunCompleted (no drift) -> RELEASE_CANDIDATE'),
      $this->t('RECONCILIATION + DriftThresholdExceeded -> RESET_REQUIRED'),
      $this->t('RESET_REQUIRED + ControlledResetCompleted -> RELEASE_QUEUE_ACTIVE'),
      $this->t('RELEASE_CANDIDATE + ReleaseApproved -> RELEASED'),
    ];

    $actions = [
      $this->t('Serialize merges to one PR at a time to prevent overwrite collisions on main.'),
      $this->t('After each merge, mark remaining open PRs stale until updated to latest main and revalidated.'),
      $this->t('Run automated post-merge mainline testing before any release promotion decision.'),
      $this->t('Execute a deterministic state reconciler across issue, PR, and tester queue metadata.'),
      $this->t('Trigger controlled reset workflow when drift or instability exceeds defined thresholds.'),
      $this->t('Promote only from RELEASE_CANDIDATE when quality and reconciliation checks are green.'),
    ];

    $timingItems = [
      $this->t('Merge window cadence should be fixed (for example hourly or daily) rather than continuous free-for-all merging.'),
      $this->t('State reconciler should run on a short interval (for example every 15-60 minutes) and before release approval.'),
      $this->t('Mainline retest is mandatory after every merge in queue to detect cross-PR interaction defects early.'),
      $this->t('Controlled reset should pause new queue claims, reconcile links/state, rebuild pending queue from latest main, then resume.'),
      $this->t('Release cutoff can define final stabilization period where only remediation PRs are allowed.'),
    ];

    $driftControls = [
      $this->t('Single source-of-truth mapping: issue_id <-> pr_id <-> branch <-> last_known_gate_state.'),
      $this->t('Webhook + cron dual-sync pattern: event-driven updates plus periodic correction pass.'),
      $this->t('Idempotent transitions only; reject stale/out-of-order updates based on last_transition_at.'),
      $this->t('Auto-remediation for orphaned states (open issue with closed PR, merged PR with unresolved issue, etc.).'),
      $this->t('Escalation if unreconciled mismatches exceed threshold for two consecutive reconciler runs.'),
    ];

    $resetSteps = [
      $this->t('Enter RESET_REQUIRED and pause merge queue + background queue claims.'),
      $this->t('Snapshot current PR/issue/tester state and identify drift buckets.'),
      $this->t('Re-link orphan records and close/retag invalid blockers.'),
      $this->t('Rebase remaining release PRs onto latest main and force revalidation.'),
      $this->t('Resume queue in RELEASE_QUEUE_ACTIVE and continue serialized merge flow.'),
    ];

    $illegalTransitions = [
      $this->t('MERGE_WINDOW -> RELEASED without mainline validation and reconciliation.'),
      $this->t('RELEASE_CANDIDATE -> RELEASED when drift mismatches still exist.'),
      $this->t('RELEASE_QUEUE_ACTIVE -> MERGED_MAIN bypassing merge checks and CI gating.'),
      $this->t('RESET_REQUIRED -> RELEASED without ControlledResetCompleted and revalidation.'),
    ];

    $related = $this->renderLinkItems([
      Link::fromTextAndUrl($this->t('SDLC Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_sdlc_process_flow')),
      Link::fromTextAndUrl($this->t('Automated Testing Process Flow'), Url::fromRoute('dungeoncrawler_tester.docs_process_flow')),
      Link::fromTextAndUrl($this->t('Back to Documentation Home'), Url::fromRoute('dungeoncrawler_tester.documentation_home')),
    ]);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'diagram_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Release Process Flow Diagram'),
            ],
            'subtitle' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light']],
              '#value' => $this->t('Umbrella release governance process that prevents drift and multi-PR contention while preserving deterministic promotion gates.'),
            ],
            'diagram' => [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#attributes' => ['class' => ['command-snippet']],
              '#value' => "[RELEASE_INTAKE]\n      | select candidate PR set\n      v\n[RELEASE_QUEUE_ACTIVE] -> [MERGE_WINDOW]\n      |                 (one green PR merged at a time)\n      |                          |\n      |                          v\n      |                   [MAINLINE_VALIDATION]\n      |                   (post-merge tests on main)\n      |                     | pass         | fail\n      |                     v              v\n      |               [RECONCILIATION]  [RESET_REQUIRED]\n      |                  | no drift         | controlled reset\n      |                  v                  v\n      |            [RELEASE_CANDIDATE] <---+\n      |                  | approve\n      +-----------------> v\n                      [RELEASED]\n\nAfter each merge: remaining open PRs must update to latest main and re-run CI before merge eligibility.",
            ],
          ],
          'summary_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $this->t('Release Process Flow (Governance Layer)'),
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $this->t('Release-level controls over SDLC/PR automation to manage contention, detect state drift, and provide deterministic reset/recovery behavior.'),
            ],
          ],
          'core_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('1) Core Components (State Machine Model)'),
            ],
            'states_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('States'),
            ],
            'states' => [
              '#theme' => 'item_list',
              '#items' => $states,
            ],
            'events_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Events (Triggers)'),
            ],
            'events' => [
              '#theme' => 'item_list',
              '#items' => $events,
            ],
            'transitions_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Transitions'),
            ],
            'transitions' => [
              '#theme' => 'item_list',
              '#items' => $transitions,
            ],
            'actions_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Actions (Side Effects)'),
            ],
            'actions' => [
              '#theme' => 'item_list',
              '#items' => $actions,
            ],
          ],
          'timing_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('2) Timing, Cadence, and Release Windows'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $timingItems,
            ],
            'flowline' => [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#attributes' => ['class' => ['command-snippet']],
              '#value' => 'ReleaseIntake -> MergeWindow(serialized) -> MainlineRetest -> Reconcile -> Candidate -> ApproveRelease',
            ],
          ],
          'drift_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('3) Drift Prevention and Reconciliation Controls'),
            ],
            'drift_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Preventive Controls'),
            ],
            'drift_items' => [
              '#theme' => 'item_list',
              '#items' => $driftControls,
            ],
            'reset_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Controlled Reset Procedure'),
            ],
            'reset_items' => [
              '#theme' => 'item_list',
              '#items' => $resetSteps,
            ],
          ],
          'analysis_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('4) Illegal Transitions and Determinism'),
            ],
            'illegal_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h4',
              '#value' => $this->t('Illegal Transitions'),
            ],
            'illegal' => [
              '#theme' => 'item_list',
              '#items' => $illegalTransitions,
            ],
          ],
          'related_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Related Links'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $related,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Legacy route alias for module overview docs.
   */
  public function docsModuleReadme(): array {
    return $this->docsGettingStarted();
  }

  /**
   * Legacy route alias for testing module guide docs.
   */
  public function docsTestingModuleReadme(): array {
    return $this->docsGettingStarted();
  }

  /**
   * Legacy route alias for tests README docs.
   */
  public function docsTestsReadme(): array {
    return $this->docsExecutionPlaybook();
  }

  /**
   * Legacy route alias for strategy design docs.
   */
  public function docsStrategyDesign(): array {
    return $this->docsExecutionPlaybook();
  }

  /**
   * Legacy route alias for quick start docs.
   */
  public function docsQuickStart(): array {
    return $this->docsExecutionPlaybook();
  }

  /**
   * Legacy route alias for issues directory docs.
   */
  public function docsIssuesDirectory(): array {
    return $this->docsFailureTriage();
  }

  /**
   * Legacy route alias for issue automation docs.
   */
  public function docsIssueAutomation(): array {
    return $this->docsFailureTriage();
  }

  /**
   * Build a shared docs page layout.
   *
   * @param string $title
   *   Page title.
   * @param string $intro
   *   Intro text.
   * @param array $items
   *   Primary bullet items.
   * @param array $relatedLinks
   *   Related links as Link objects.
   */
  private function buildDocPage(string $title, string $intro, array $items, array $relatedLinks = []): array {
    $related = $this->renderLinkItems($relatedLinks);

    $backToHome = Link::fromTextAndUrl(
      $this->t('Back to Documentation Home'),
      Url::fromRoute('dungeoncrawler_tester.documentation_home')
    );
    $related = array_merge($related, $this->renderLinkItems([$backToHome]));

    $itemMarkup = [];
    foreach ($items as $item) {
      $itemMarkup[] = ['#markup' => $item];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['container', 'py-4', 'tester-documentation-page']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'max-age' => self::GITHUB_CACHE_TTL,
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['row', 'justify-content-center']],
        'col' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-lg-10']],
          'summary_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'title' => [
              '#type' => 'html_tag',
              '#tag' => 'h2',
              '#value' => $title,
            ],
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#attributes' => ['class' => ['text-muted-light', 'mb-0']],
              '#value' => $intro,
            ],
          ],
          'details_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4', 'mb-4']],
            'items_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Key Points'),
            ],
            'items' => [
              '#theme' => 'item_list',
              '#items' => $itemMarkup,
            ],
          ],
          'related_card' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['card', 'card-dungeoncrawler', 'p-4']],
            'related_title' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('Related Links'),
            ],
            'related' => [
              '#theme' => 'item_list',
              '#items' => $related,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Convert links to themed render arrays.
   *
   * @param array $links
   *   Array of Link objects.
   *
   * @return array
   *   Renderable link items.
   */
  private function renderLinkItems(array $links): array {
    $items = [];

    foreach ($links as $link) {
      $render = $link->toRenderable();
      $render['#attributes']['class'][] = 'link-cyan';
      $items[] = $render;
    }

    return $items;
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
  private function resolveGitHubContext(): array {
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
  private function fetchOpenIssuesForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
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
  private function fetchOpenPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
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
  private function fetchClosedPullRequestsForReport(string $repo, array $tokenCandidates, bool $useCache = TRUE): array {
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
  private function fetchLinkedOpenPrNumbersForIssueFromTimeline(string $repo, array $tokenCandidates, int $issueNumber, array $openPrByNumber, bool $useCache = TRUE): array {
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
  private function requestGitHubJson(string $url, ?string $token, array $extraHeaders = []): array {
    return $this->githubClient->requestJson($url, $token, $extraHeaders, FALSE);
  }

  /**
   * Execute GitHub JSON request with token failover.
   */
  private function requestGitHubJsonWithFallback(string $url, array $tokenCandidates, array $extraHeaders = [], bool $paginate = FALSE): array {
    return $this->githubClient->requestJsonWithFallback($url, $tokenCandidates, $extraHeaders, $paginate);
  }

  /**
   * Extract issue number references from a PR title/body.
   */
  private function extractIssueReferencesFromPr(array $pr): array {
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
  private function isPrAlreadyLinkedToIssue(array $linkedPrs, array $candidatePr): bool {
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
  private function describePrBlockers(array $pr): array {
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
  private function suggestPrNextStep(array $pr, array $blockers): string {
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
  private function isDeadValuePr(array $pr): bool {
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
   * AJAX: close dead-value PR and optionally linked issue without page reload.
   */
  public function closeDeadValueAjax(Request $request): JsonResponse {
    if (!$this->currentUser()->hasPermission('administer site configuration')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied'], 403);
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      $payload = [];
    }

    $prNumber = (int) ($payload['pr_number'] ?? 0);
    $issueNumber = (int) ($payload['issue_number'] ?? 0);

    if ($prNumber <= 0) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Missing PR number.'], 400);
    }

    $githubContext = $this->resolveGitHubContext();
    $repo = $githubContext['repo'];
    $token = $githubContext['token'];
    if (!$token) {
      return new JsonResponse(['success' => FALSE, 'message' => 'GitHub token is not configured.'], 400);
    }

    $prResponse = $this->requestGitHubJson("https://api.github.com/repos/{$repo}/pulls/{$prNumber}", $token);
    if (!empty($prResponse['error']) || !is_array($prResponse['items'])) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Unable to load PR details.'], 500);
    }

    $pr = [
      'base_ref' => (string) (($prResponse['items']['base']['ref'] ?? '')),
      'changed_files' => (int) ($prResponse['items']['changed_files'] ?? 0),
      'additions' => (int) ($prResponse['items']['additions'] ?? 0),
      'deletions' => (int) ($prResponse['items']['deletions'] ?? 0),
    ];

    if (!$this->isDeadValuePr($pr)) {
      return new JsonResponse(['success' => FALSE, 'message' => 'PR is no longer dead-value; refresh and review.'], 409);
    }

    $base = "https://api.github.com/repos/{$repo}";

    $prCommented = $this->requestGitHubMutation('POST', $base . "/issues/{$prNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
    $prClosed = $this->requestGitHubMutation('PATCH', $base . "/pulls/{$prNumber}", $token, ['state' => 'closed']);

    $issueCommented = TRUE;
    $issueClosed = TRUE;
    if ($issueNumber > 0 && $issueNumber !== $prNumber) {
      $issueCommented = $this->requestGitHubMutation('POST', $base . "/issues/{$issueNumber}/comments", $token, ['body' => self::DEAD_VALUE_COMMENT]);
      $issueClosed = $this->requestGitHubMutation('PATCH', $base . "/issues/{$issueNumber}", $token, ['state' => 'closed']);
    }

    if (!$prCommented || !$prClosed || !$issueCommented || !$issueClosed) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Close action completed with warnings. Check logs for details.'], 500);
    }

    return new JsonResponse([
      'success' => TRUE,
      'message' => $issueNumber > 0
        ? "Closed dead-value PR #{$prNumber} and issue #{$issueNumber}."
        : "Closed dead-value PR #{$prNumber}.",
    ]);
  }

  /**
   * Execute a GitHub mutation request with JSON payload.
   */
  private function requestGitHubMutation(string $method, string $url, string $token, array $json): bool {
    $ok = $this->githubClient->mutate($method, $url, $json, $token, self::GITHUB_API_TIMEOUT);
    if (!$ok) {
      $this->logger->error('Dead-value close mutation failed for @url.', [
        '@url' => $url,
      ]);
    }
    return $ok;
  }

}
