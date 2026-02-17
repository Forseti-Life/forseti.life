<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\dungeoncrawler_tester\Service\GithubIssuePrClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

/**
 * Import open Issues.md tracker rows into GitHub issues.
 */
class OpenIssuesImportForm extends FormBase {

  /**
   * Marker prefix for import GitHub-action watchdog logs.
   */
  private const IMPORT_LOG_PREFIX = '[import-open-issues]';

  /**
   * State key for last import summary.
   */
  private const IMPORT_LAST_RUN_KEY = 'dungeoncrawler_tester.open_issues_import_last_run';

  /**
   * State key for active import/reconcile run marker.
   */
  private const RUN_STATE_KEY = 'dungeoncrawler_tester.open_issues_import_running';

  /**
   * State key for last reconcile summary.
   */
  private const RECONCILE_STATE_KEY = 'dungeoncrawler_tester.open_issues_reconcile_last_run';

  /**
   * Lock key used to serialize run-state updates.
   */
  private const RUN_STATE_LOCK_KEY = 'dungeoncrawler_tester.open_issues_import.run_state';

  /**
   * Shared message for active-run guard.
   */
  private const MSG_RUN_IN_PROGRESS = 'An import or reconcile run is already in progress.';

  /**
   * Cache tag for import/reconcile status displays.
   */
  private const IMPORT_STATUS_CACHE_TAG = 'dungeoncrawler_tester.issue_import_status';

  /**
   * Maximum age before an active run marker is considered stale.
   */
  private const RUN_STALE_SECONDS = 10800;

  /**
   * Constructor.
   */
  public function __construct(
    private readonly GithubIssuePrClientInterface $githubClient,
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly StateInterface $state,
    private readonly LockBackendInterface $lock,
    private readonly string $appRoot,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_tester.github_issue_pr_client'),
      $container->get('logger.factory'),
      $container->get('state'),
      $container->get('lock'),
      (string) $container->getParameter('app.root'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_open_issues_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'open-issues-import-form';

    $context = $this->githubClient->resolveContext();
    $issuesFile = $this->resolveIssuesFilePath();
    $openRows = $this->parseOpenIssueRows($issuesFile);
    $openCount = count($openRows);
    $runState = $this->getActiveRunState();
    $lastRun = $this->state->get(self::IMPORT_LAST_RUN_KEY, []);
    $lastReconcile = $this->state->get(self::RECONCILE_STATE_KEY, []);

    $statusMarkup = $runState !== []
      ? $this->t('Running: Yes<br>Operation: @operation<br>Repository: @repo<br>Started: @started', [
        '@operation' => (string) ($runState['operation'] ?? 'import'),
        '@repo' => (string) ($runState['repo'] ?? ''),
        '@started' => $this->formatTimestamp((int) ($runState['started_at'] ?? 0)),
      ])
      : $this->t('Running: No');

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Importer Summary'),
      '#attributes' => ['class' => ['import-form-summary']],
      '#markup' => $this->t('Issues file: @file<br>Open tracker rows detected: @count<br>@status', [
        '@file' => $issuesFile,
        '@count' => (string) $openCount,
        '@status' => (string) $statusMarkup,
      ]),
    ];

    $form['last_runs'] = [
      '#type' => 'item',
      '#title' => $this->t('Last Run Status'),
      '#attributes' => ['class' => ['import-form-last-runs']],
      '#markup' => $this->t(
        'Last import: @import<br>Last reconcile: @reconcile',
        [
          '@import' => $this->buildLastImportSummary($lastRun),
          '@reconcile' => $this->buildLastReconcileSummary($lastReconcile),
        ]
      ),
    ];

    $form['repo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Repository (owner/repo)'),
      '#default_value' => (string) ($context['repo'] ?? 'keithaumiller/forseti.life'),
      '#required' => TRUE,
      '#description' => $this->t('Destination repository for imported issues.'),
    ];

    $form['wait_seconds'] = [
      '#type' => 'select',
      '#title' => $this->t('Wait seconds between items'),
      '#default_value' => 5,
      '#options' => [
        5 => $this->t('5 seconds'),
        30 => $this->t('30 seconds'),
        180 => $this->t('180 seconds'),
      ],
      '#required' => TRUE,
    ];

    $form['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Max items this run'),
      '#default_value' => 1,
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Use small batches to avoid PHP request timeout. Increase as needed.'),
    ];

    $form['dry_run'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Dry run (no GitHub mutations)'),
      '#default_value' => FALSE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['#attributes']['class'][] = 'import-form-actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run import batch'),
      '#button_type' => 'primary',
      '#disabled' => $runState !== [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $repo = trim((string) $form_state->getValue('repo'));
    $waitSeconds = max(0, (int) $form_state->getValue('wait_seconds'));
    $maxItems = max(1, (int) $form_state->getValue('max_items'));
    $dryRun = !empty($form_state->getValue('dry_run'));

    if (!$this->beginRunState('import', $repo)) {
      $this->messenger()->addError($this->t(self::MSG_RUN_IN_PROGRESS));
      return;
    }

    $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' import started repo=@repo max_items=@max wait_seconds=@wait dry_run=@dry', [
      '@repo' => $repo,
      '@max' => (string) $maxItems,
      '@wait' => (string) $waitSeconds,
      '@dry' => $dryRun ? 'yes' : 'no',
    ]);

    $issuesFile = $this->resolveIssuesFilePath();
    $rows = $this->parseOpenIssueRows($issuesFile);
    $openRowsDetected = count($rows);
    try {
      if (empty($rows)) {
        $this->persistLastRunStatus($this->buildImportRunStatus(
          $repo,
          $issuesFile,
          $openRowsDetected,
          $waitSeconds,
          $maxItems,
          $dryRun,
          0,
          0,
          0,
          0,
        ));
        $this->messenger()->addStatus($this->t('No open rows found in @file.', ['@file' => $issuesFile]));
        $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' import completed no-op repo=@repo (no open rows in Issues.md)', [
          '@repo' => $repo,
        ]);
        return;
      }

      $created = 0;
      $skipped = 0;
      $failed = 0;
      $handled = 0;

      $token = $this->resolveGithubToken();

      foreach ($rows as $row) {
        if ($handled >= $maxItems) {
          break;
        }

        $result = $this->processImportRow($row, $repo, $issuesFile, $token, $dryRun);
        $handled += (int) ($result['handled'] ?? 0);
        $created += (int) ($result['created'] ?? 0);
        $skipped += (int) ($result['skipped'] ?? 0);
        $failed += (int) ($result['failed'] ?? 0);
        $this->pauseBetweenItems($waitSeconds, $handled, $maxItems);
      }

      $this->messenger()->addStatus($this->t('Batch complete. Handled: @handled, Created: @created, Skipped: @skipped, Failed: @failed.', [
        '@handled' => (string) $handled,
        '@created' => (string) $created,
        '@skipped' => (string) $skipped,
        '@failed' => (string) $failed,
      ]));

      $this->persistLastRunStatus($this->buildImportRunStatus(
        $repo,
        $issuesFile,
        $openRowsDetected,
        $waitSeconds,
        $maxItems,
        $dryRun,
        $handled,
        $created,
        $skipped,
        $failed,
      ));

      $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' import completed repo=@repo handled=@handled created=@created skipped=@skipped failed=@failed', [
        '@repo' => $repo,
        '@handled' => (string) $handled,
        '@created' => (string) $created,
        '@skipped' => (string) $skipped,
        '@failed' => (string) $failed,
      ]);
    }
    finally {
      $this->endRunState();
    }
  }

  /**
   * Reconcile local open tracker rows against open GitHub issues.
   */
  public function submitReconcile(array &$form, FormStateInterface $form_state): void {
    $repo = trim((string) $form_state->getValue('repo'));
    $issuesFile = $this->resolveIssuesFilePath();

    if ($repo === '') {
      $this->messenger()->addError($this->t('Repository is required.'));
      return;
    }

    if (!$this->beginRunState('reconcile', $repo)) {
      $this->messenger()->addError($this->t(self::MSG_RUN_IN_PROGRESS));
      return;
    }

    try {
      $localOpenRows = $this->parseOpenIssueRows($issuesFile);
      $localOpenCount = count($localOpenRows);

      $githubOpenIssues = $this->fetchOpenGithubIssues($repo);
      if ($githubOpenIssues === NULL) {
        $this->messenger()->addError($this->t('Unable to fetch open GitHub issues for @repo.', ['@repo' => $repo]));
        return;
      }

      $githubIssueIds = [];
      foreach ($githubOpenIssues as $issue) {
        $title = trim((string) ($issue['title'] ?? ''));
        if ($title === '') {
          continue;
        }
        if (preg_match('/^([A-Z]+-\d+)\b/', $title, $matches) === 1) {
          $githubIssueIds[(string) $matches[1]] = TRUE;
        }
      }

      $localIssueIds = [];
      $deleteIds = [];
      foreach ($localOpenRows as $row) {
        $issueId = trim((string) ($row['id'] ?? ''));
        if ($issueId === '') {
          continue;
        }
        $localIssueIds[$issueId] = TRUE;
        if (!empty($githubIssueIds[$issueId])) {
          $deleteIds[$issueId] = TRUE;
        }
      }

      $removedCount = $this->removeOpenIssueRowsById($issuesFile, array_keys($deleteIds));

      $localOnlyCount = max(0, count($localIssueIds) - count($deleteIds));
      $githubOnlyCount = 0;
      foreach ($githubIssueIds as $issueId => $present) {
        if (empty($localIssueIds[$issueId])) {
          $githubOnlyCount++;
        }
      }

      $status = [
        'timestamp' => time(),
        'repo' => $repo,
        'issues_file' => $issuesFile,
        'local_open_rows' => $localOpenCount,
        'github_open_issues' => count($githubOpenIssues),
        'matched_rows_removed' => $removedCount,
        'local_only_rows' => $localOnlyCount,
        'github_only_rows' => $githubOnlyCount,
      ];

      $this->state->set(self::RECONCILE_STATE_KEY, $status);
      Cache::invalidateTags([self::IMPORT_STATUS_CACHE_TAG]);

      $this->messenger()->addStatus($this->t('Reconcile complete. Removed @removed local open row(s) already open in GitHub. Local-only: @local_only. GitHub-only: @github_only.', [
        '@removed' => (string) $removedCount,
        '@local_only' => (string) $localOnlyCount,
        '@github_only' => (string) $githubOnlyCount,
      ]));
    }
    finally {
      $this->endRunState();
    }
  }

  /**
   * Persist last import run status for dashboard visibility.
   */
  private function persistLastRunStatus(array $status): void {
    $this->state->set(self::IMPORT_LAST_RUN_KEY, $status);
    Cache::invalidateTags([self::IMPORT_STATUS_CACHE_TAG]);
  }

  /**
   * Process one open issue row for import and return stat deltas.
   *
   * @return array{handled:int,created:int,skipped:int,failed:int}
   *   Per-row counter deltas.
   */
  private function processImportRow(array $row, string $repo, string $issuesFile, string $token, bool $dryRun): array {
    $issueId = (string) ($row['id'] ?? '');
    $title = (string) ($row['title'] ?? '');
    $fullTitle = $issueId . ' ' . $title;

    if ($dryRun) {
      $this->messenger()->addStatus($this->t('[Dry run] Would process @title', ['@title' => $fullTitle]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' dry-run repo=@repo issue_id=@issue_id title="@title"', [
        '@repo' => $repo,
        '@issue_id' => $issueId,
        '@title' => $fullTitle,
      ]);
      return ['handled' => 1, 'created' => 0, 'skipped' => 1, 'failed' => 0];
    }

    $existingIssueNumber = $this->findExistingOpenIssueNumberForTracker($repo, $issueId, $token);
    if ($existingIssueNumber > 0) {
      $removedCount = $this->removeOpenIssueRowsById($issuesFile, [$issueId]);
      if ($removedCount > 0) {
        $this->messenger()->addStatus($this->t('Skipped existing open issue: @title (GitHub #@number). Removed @count matching Open row(s) from Issues.md.', [
          '@title' => $fullTitle,
          '@number' => (string) $existingIssueNumber,
          '@count' => (string) $removedCount,
        ]));
        $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' github existing-open hit repo=@repo issue_id=@issue_id title="@title" github_issue=@number local_removed=@removed', [
          '@repo' => $repo,
          '@issue_id' => $issueId,
          '@title' => $fullTitle,
          '@number' => (string) $existingIssueNumber,
          '@removed' => (string) $removedCount,
        ]);
      }
      else {
        $this->messenger()->addWarning($this->t('Skipped existing open issue: @title (GitHub #@number), but did not remove matching Open row from Issues.md.', [
          '@title' => $fullTitle,
          '@number' => (string) $existingIssueNumber,
        ]));
        $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' github existing-open hit repo=@repo issue_id=@issue_id title="@title" github_issue=@number but local removal failed', [
          '@repo' => $repo,
          '@issue_id' => $issueId,
          '@title' => $fullTitle,
          '@number' => (string) $existingIssueNumber,
        ]);
      }
      return ['handled' => 1, 'created' => 0, 'skipped' => 1, 'failed' => 0];
    }

    $body = $this->buildIssueBody($row);
    $payload = $this->githubClient->createIssue($repo, [
      'title' => $fullTitle,
      'body' => $body,
    ], $token !== '' ? $token : NULL);

    if (!is_array($payload) || empty($payload['number'])) {
      $this->messenger()->addError($this->t('Failed creating issue for @id.', ['@id' => $issueId]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' github create failed repo=@repo issue_id=@issue_id title="@title"', [
        '@repo' => $repo,
        '@issue_id' => $issueId,
        '@title' => $fullTitle,
      ]);
      return ['handled' => 1, 'created' => 0, 'skipped' => 0, 'failed' => 1];
    }

    $issueNumber = (int) $payload['number'];
    $assigned = $this->assignCopilot($repo, $issueNumber, $token);
    $confirmedInGithub = $this->confirmGithubIssueExists($repo, $issueNumber, $fullTitle, $token);
    $removedLocally = FALSE;
    $removedCount = 0;
    if ($confirmedInGithub) {
      $removedCount = $this->removeOpenIssueRowsById($issuesFile, [$issueId]);
      $removedLocally = $removedCount > 0;
    }

    if ($assigned) {
      $this->messenger()->addStatus($this->t('Created #@number and assigned Copilot for @id.', [
        '@number' => (string) $issueNumber,
        '@id' => $issueId,
      ]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' github create+assign success repo=@repo issue_id=@issue_id github_issue=@number assignee=@assignee', [
        '@repo' => $repo,
        '@issue_id' => $issueId,
        '@number' => (string) $issueNumber,
        '@assignee' => 'copilot',
      ]);
    }
    else {
      $this->messenger()->addWarning($this->t('Created #@number for @id but Copilot assignment did not confirm.', [
        '@number' => (string) $issueNumber,
        '@id' => $issueId,
      ]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' github create success but assignment not confirmed repo=@repo issue_id=@issue_id github_issue=@number', [
        '@repo' => $repo,
        '@issue_id' => $issueId,
        '@number' => (string) $issueNumber,
      ]);
    }

    if (!$confirmedInGithub) {
      $this->messenger()->addWarning($this->t('Created #@number for @id, but could not confirm the issue from GitHub API; local row was not removed.', [
        '@number' => (string) $issueNumber,
        '@id' => $issueId,
      ]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' github create success but confirmation failed repo=@repo issue_id=@issue_id github_issue=@number; local row retained', [
        '@repo' => $repo,
        '@issue_id' => $issueId,
        '@number' => (string) $issueNumber,
      ]);
    }
    elseif ($removedLocally) {
      $this->messenger()->addStatus($this->t('Removed @count Open row(s) for @id from Issues.md.', [
        '@count' => (string) $removedCount,
        '@id' => $issueId,
      ]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->notice(self::IMPORT_LOG_PREFIX . ' local tracker row removed issue_id=@issue_id github_issue=@number removed=@count', [
        '@issue_id' => $issueId,
        '@number' => (string) $issueNumber,
        '@count' => (string) $removedCount,
      ]);
    }
    else {
      if (!is_writable($issuesFile)) {
        $this->messenger()->addWarning($this->t('Created #@number for @id, but Issues.md is not writable by the web process at @file.', [
          '@number' => (string) $issueNumber,
          '@id' => $issueId,
          '@file' => $issuesFile,
        ]));
        $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' local tracker removal blocked by file permissions issue_id=@issue_id github_issue=@number file=@file', [
          '@issue_id' => $issueId,
          '@number' => (string) $issueNumber,
          '@file' => $issuesFile,
        ]);
      }
      $this->messenger()->addWarning($this->t('Created #@number for @id, but did not remove matching Open row from Issues.md.', [
        '@number' => (string) $issueNumber,
        '@id' => $issueId,
      ]));
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' local tracker removal failed issue_id=@issue_id github_issue=@number', [
        '@issue_id' => $issueId,
        '@number' => (string) $issueNumber,
      ]);
    }

    return ['handled' => 1, 'created' => 1, 'skipped' => 0, 'failed' => 0];
  }

  /**
   * Build standardized last-run status payload for import runs.
   */
  private function buildImportRunStatus(
    string $repo,
    string $issuesFile,
    int $openRowsDetected,
    int $waitSeconds,
    int $maxItems,
    bool $dryRun,
    int $handled,
    int $created,
    int $skipped,
    int $failed,
  ): array {
    return [
      'timestamp' => time(),
      'repo' => $repo,
      'issues_file' => $issuesFile,
      'open_rows_detected' => $openRowsDetected,
      'wait_seconds' => $waitSeconds,
      'max_items' => $maxItems,
      'dry_run' => $dryRun,
      'handled' => $handled,
      'created' => $created,
      'skipped' => $skipped,
      'failed' => $failed,
    ];
  }

  /**
   * Resolve configured GitHub token from client context.
   */
  private function resolveGithubToken(): string {
    $context = $this->githubClient->resolveContext();
    return trim((string) ($context['token'] ?? ''));
  }

  /**
   * Pause between item operations when configured.
   */
  private function pauseBetweenItems(int $waitSeconds, int $handled, int $maxItems): void {
    if ($waitSeconds > 0 && $handled < $maxItems) {
      sleep($waitSeconds);
    }
  }

  /**
   * Return currently active run state, if any and not stale.
   */
  private function getActiveRunState(): array {
    $state = $this->state->get(self::RUN_STATE_KEY, []);
    if (!is_array($state) || $state === []) {
      return [];
    }

    $startedAt = (int) ($state['started_at'] ?? 0);
    if ($startedAt <= 0 || (time() - $startedAt) > self::RUN_STALE_SECONDS) {
      $this->endRunState();
      return [];
    }

    return $state;
  }

  /**
   * Mark import/reconcile as running.
   */
  private function beginRunState(string $operation, string $repo): bool {
    if (!$this->lock->acquire(self::RUN_STATE_LOCK_KEY, 5.0)) {
      return FALSE;
    }

    try {
      $active = $this->getActiveRunState();
      if ($active !== []) {
        return FALSE;
      }

      $this->state->set(self::RUN_STATE_KEY, [
        'operation' => $operation,
        'repo' => $repo,
        'started_at' => time(),
      ]);
      Cache::invalidateTags([self::IMPORT_STATUS_CACHE_TAG]);

      return TRUE;
    }
    finally {
      $this->lock->release(self::RUN_STATE_LOCK_KEY);
    }
  }

  /**
   * Clear active import/reconcile marker.
   */
  private function endRunState(): void {
    $this->state->delete(self::RUN_STATE_KEY);
    Cache::invalidateTags([self::IMPORT_STATUS_CACHE_TAG]);
  }

  /**
   * Format timestamp for status output.
   */
  private function formatTimestamp(int $timestamp): string {
    if ($timestamp <= 0) {
      return 'n/a';
    }

    return date('Y-m-d H:i:s', $timestamp);
  }

  /**
   * Build last import summary text.
   */
  private function buildLastImportSummary(array $lastRun): string {
    if (empty($lastRun)) {
      return 'never';
    }

    return sprintf(
      '%s (repo: %s, handled: %d, created: %d, skipped: %d, failed: %d)',
      $this->formatTimestamp((int) ($lastRun['timestamp'] ?? 0)),
      (string) ($lastRun['repo'] ?? ''),
      (int) ($lastRun['handled'] ?? 0),
      (int) ($lastRun['created'] ?? 0),
      (int) ($lastRun['skipped'] ?? 0),
      (int) ($lastRun['failed'] ?? 0),
    );
  }

  /**
   * Build last reconcile summary text.
   */
  private function buildLastReconcileSummary(array $lastRun): string {
    if (empty($lastRun)) {
      return 'never';
    }

    return sprintf(
      '%s (repo: %s, removed: %d, local-only: %d, github-only: %d)',
      $this->formatTimestamp((int) ($lastRun['timestamp'] ?? 0)),
      (string) ($lastRun['repo'] ?? ''),
      (int) ($lastRun['matched_rows_removed'] ?? 0),
      (int) ($lastRun['local_only_rows'] ?? 0),
      (int) ($lastRun['github_only_rows'] ?? 0),
    );
  }

  /**
   * Resolve Issues.md path from Drupal web root.
   */
  private function resolveIssuesFilePath(): string {
    $candidate = $this->appRoot . '/../../../Issues.md';
    $resolved = realpath($candidate);
    return $resolved !== FALSE ? $resolved : $candidate;
  }

  /**
   * Parse open issue rows from tracker markdown.
   *
   * @return array<int, array<string, string>>
   *   Parsed rows keyed by zero-based index.
   */
  private function parseOpenIssueRows(string $issuesFile): array {
    if (!is_file($issuesFile)) {
      return [];
    }

    $rows = [];
    $handle = fopen($issuesFile, 'r');
    if ($handle === FALSE) {
      return [];
    }

    while (($line = fgets($handle)) !== FALSE) {
      $line = rtrim($line, "\r\n");
      if (!str_starts_with($line, '|')) {
        continue;
      }

      $parts = array_map('trim', explode('|', $line));
      if (count($parts) < 9) {
        continue;
      }

      $id = (string) ($parts[1] ?? '');
      $title = (string) ($parts[2] ?? '');
      $status = (string) ($parts[3] ?? '');

      if ($id === '' || $id === 'ID' || $id === '---' || preg_match('/^[A-Z]+-\d+$/', $id) !== 1) {
        continue;
      }
      if ($status !== 'Open') {
        continue;
      }

      $rows[] = [
        'id' => $id,
        'title' => $title,
        'owner' => (string) ($parts[4] ?? ''),
        'created' => (string) ($parts[5] ?? ''),
        'updated' => (string) ($parts[6] ?? ''),
        'notes' => (string) ($parts[7] ?? ''),
      ];
    }

    fclose($handle);
    return $rows;
  }

  /**
   * Fetch all open GitHub issues for repository (excluding pull requests).
   */
  private function fetchOpenGithubIssues(string $repo): ?array {
    $url = 'https://api.github.com/repos/' . $repo . '/issues?state=open&per_page=100';
    $response = $this->githubClient->requestJson($url, NULL, [], TRUE);
    if (!empty($response['error'])) {
      return NULL;
    }

    $items = $response['items'] ?? [];
    if (!is_array($items)) {
      return [];
    }

    $issues = [];
    foreach ($items as $item) {
      if (!is_array($item)) {
        continue;
      }
      if (isset($item['pull_request'])) {
        continue;
      }
      $issues[] = $item;
    }

    return $issues;
  }

  /**
   * Remove open tracker rows from Issues.md by ID.
   */
  private function removeOpenIssueRowsById(string $issuesFile, array $issueIds): int {
    if (empty($issueIds)) {
      return 0;
    }

    if (!is_file($issuesFile)) {
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' local tracker removal skipped: Issues.md not found at @file', [
        '@file' => $issuesFile,
      ]);
      return 0;
    }

    if (!is_writable($issuesFile)) {
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning(self::IMPORT_LOG_PREFIX . ' local tracker removal skipped: Issues.md not writable at @file', [
        '@file' => $issuesFile,
      ]);
      return 0;
    }

    $issueIdMap = [];
    foreach ($issueIds as $issueId) {
      $normalized = trim((string) $issueId);
      if ($normalized !== '') {
        $issueIdMap[$normalized] = TRUE;
      }
    }

    if ($issueIdMap === []) {
      return 0;
    }

    $lines = file($issuesFile);
    if (!is_array($lines) || $lines === []) {
      return 0;
    }

    $removed = 0;
    $kept = [];
    foreach ($lines as $line) {
      $trimmed = rtrim((string) $line, "\r\n");
      if (!str_starts_with($trimmed, '|')) {
        $kept[] = $line;
        continue;
      }

      $parts = array_map('trim', explode('|', $trimmed));
      if (count($parts) < 9) {
        $kept[] = $line;
        continue;
      }

      $id = (string) ($parts[1] ?? '');
      $status = (string) ($parts[3] ?? '');
      if ($status === 'Open' && !empty($issueIdMap[$id])) {
        $removed++;
        continue;
      }

      $kept[] = $line;
    }

    if ($removed > 0) {
      $written = file_put_contents($issuesFile, implode('', $kept));
      if ($written === FALSE) {
        $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning('Reconcile could not persist Issues.md updates after matching @count row(s).', [
          '@count' => (string) $removed,
        ]);
        return 0;
      }
    }

    return $removed;
  }

  /**
   * Build GitHub issue body from tracker row.
   */
  private function buildIssueBody(array $row): string {
    return implode("\n", [
      'Source: Issues.md',
      '',
      'Tracker ID: ' . (string) ($row['id'] ?? ''),
      'Owner: ' . (string) ($row['owner'] ?? ''),
      'Created: ' . (string) ($row['created'] ?? ''),
      'Last Updated: ' . (string) ($row['updated'] ?? ''),
      '',
      'Notes:',
      (string) ($row['notes'] ?? ''),
      '',
      'Imported via dungeoncrawler tester import page.',
    ]);
  }

  /**
   * Confirm a newly created GitHub issue exists before local row removal.
   */
  private function confirmGithubIssueExists(string $repo, int $issueNumber, string $expectedTitle, string $token): bool {
    if ($issueNumber <= 0) {
      return FALSE;
    }

    $issue = $this->githubClient->getIssue($repo, $issueNumber, $token !== '' ? $token : NULL);
    if (!is_array($issue) || !empty($issue['pull_request'])) {
      return FALSE;
    }

    $confirmedNumber = (int) ($issue['number'] ?? 0);
    $confirmedTitle = trim((string) ($issue['title'] ?? ''));
    return $confirmedNumber === $issueNumber && $confirmedTitle === $expectedTitle;
  }

  /**
   * Find an existing open issue by tracker ID and return GitHub issue number.
   */
  private function findExistingOpenIssueNumberForTracker(string $repo, string $issueId, string $token): int {
    if ($issueId === '') {
      return 0;
    }

    $query = sprintf('repo:%s is:issue is:open in:title "%s"', $repo, str_replace('"', '\\"', $issueId));
    $url = 'https://api.github.com/search/issues?q=' . rawurlencode($query) . '&per_page=20';
    $response = $this->githubClient->requestJson($url, $token !== '' ? $token : NULL, [], FALSE);
    $items = is_array($response['items'] ?? NULL) ? $response['items'] : [];

    foreach ($items as $item) {
      if (!is_array($item) || !empty($item['pull_request'])) {
        continue;
      }

      $title = trim((string) ($item['title'] ?? ''));
      if (preg_match('/^' . preg_quote($issueId, '/') . '\\b/', $title) !== 1) {
        continue;
      }

      $number = (int) ($item['number'] ?? 0);
      if ($number > 0) {
        return $number;
      }
    }

    return 0;
  }

  /**
   * Assign Copilot using REST identifiers then gh fallback.
   */
  private function assignCopilot(string $repo, int $issueNumber, string $token): bool {
    foreach (['@copilot', 'Copilot', 'copilot'] as $identifier) {
      try {
        $payload = $this->githubClient->addIssueAssignees($repo, $issueNumber, [$identifier], $token !== '' ? $token : NULL) ?: [];
        $assignees = $payload['assignees'] ?? [];
        $assignedLogins = array_map(
          static fn(array $assignee): string => strtolower((string) ($assignee['login'] ?? '')),
          is_array($assignees) ? $assignees : [],
        );
        if (in_array('copilot', $assignedLogins, TRUE)) {
          return TRUE;
        }
      }
      catch (\Throwable) {
      }
    }

    if ($token === '') {
      return FALSE;
    }

    try {
      $process = new Process([
        'gh',
        'issue',
        'edit',
        (string) $issueNumber,
        '--repo',
        $repo,
        '--add-assignee',
        '@copilot',
      ]);
      $process->setEnv(array_merge($_ENV, [
        'GH_TOKEN' => $token,
        'GITHUB_TOKEN' => $token,
      ]));
      $process->setTimeout(20);
      $process->run();

      return $process->isSuccessful();
    }
    catch (\Throwable $exception) {
      $this->loggerChannelFactory->get('dungeoncrawler_tester')->warning('Copilot assignment fallback failed for issue #@issue: @message', [
        '@issue' => (string) $issueNumber,
        '@message' => $exception->getMessage(),
      ]);
      return FALSE;
    }
  }

}
