<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * Constructor.
   */
  public function __construct(
    private readonly GithubIssuePrClientInterface $githubClient,
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly StateInterface $state,
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
    $context = $this->githubClient->resolveContext();
    $issuesFile = $this->resolveIssuesFilePath();
    $openCount = count($this->parseOpenIssueRows($issuesFile));

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Importer Summary'),
      '#markup' => $this->t('Issues file: @file<br>Open tracker rows detected: @count', [
        '@file' => $issuesFile,
        '@count' => (string) $openCount,
      ]),
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
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run import batch'),
      '#button_type' => 'primary',
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

    $issuesFile = $this->resolveIssuesFilePath();
    $rows = $this->parseOpenIssueRows($issuesFile);
    $openRowsDetected = count($rows);
    if (empty($rows)) {
      $this->persistLastRunStatus([
        'timestamp' => time(),
        'repo' => $repo,
        'issues_file' => $issuesFile,
        'open_rows_detected' => $openRowsDetected,
        'wait_seconds' => $waitSeconds,
        'max_items' => $maxItems,
        'dry_run' => $dryRun,
        'handled' => 0,
        'created' => 0,
        'skipped' => 0,
        'failed' => 0,
      ]);
      $this->messenger()->addStatus($this->t('No open rows found in @file.', ['@file' => $issuesFile]));
      return;
    }

    $created = 0;
    $skipped = 0;
    $failed = 0;
    $handled = 0;

    $context = $this->githubClient->resolveContext();
    $token = trim((string) ($context['token'] ?? ''));

    foreach ($rows as $row) {
      if ($handled >= $maxItems) {
        break;
      }

      $issueId = (string) $row['id'];
      $title = (string) $row['title'];
      $fullTitle = $issueId . ' ' . $title;

      if ($dryRun) {
        $skipped++;
        $handled++;
        $this->messenger()->addStatus($this->t('[Dry run] Would process @title', ['@title' => $fullTitle]));
        if ($waitSeconds > 0 && $handled < $maxItems) {
          sleep($waitSeconds);
        }
        continue;
      }

      $quotedTitle = '"' . str_replace('"', '\\"', $fullTitle) . '"';
      $existing = $this->githubClient->searchIssuesTotalCount('repo:' . $repo . ' is:issue in:title ' . $quotedTitle, $token !== '' ? $token : NULL);
      if ($existing > 0) {
        $skipped++;
        $handled++;
        $this->messenger()->addStatus($this->t('Skipped existing issue: @title', ['@title' => $fullTitle]));
        if ($waitSeconds > 0 && $handled < $maxItems) {
          sleep($waitSeconds);
        }
        continue;
      }

      $body = $this->buildIssueBody($row);
      $payload = $this->githubClient->createIssue($repo, [
        'title' => $fullTitle,
        'body' => $body,
      ], $token !== '' ? $token : NULL);

      if (!is_array($payload) || empty($payload['number'])) {
        $failed++;
        $handled++;
        $this->messenger()->addError($this->t('Failed creating issue for @id.', ['@id' => $issueId]));
        if ($waitSeconds > 0 && $handled < $maxItems) {
          sleep($waitSeconds);
        }
        continue;
      }

      $issueNumber = (int) $payload['number'];
      $assigned = $this->assignCopilot($repo, $issueNumber, $token);
      $closedLocally = $this->markIssueRowClosed($issuesFile, $issueId, $issueNumber);
      $created++;
      $handled++;

      if ($assigned) {
        $this->messenger()->addStatus($this->t('Created #@number and assigned Copilot for @id.', [
          '@number' => (string) $issueNumber,
          '@id' => $issueId,
        ]));
      }
      else {
        $this->messenger()->addWarning($this->t('Created #@number for @id but Copilot assignment did not confirm.', [
          '@number' => (string) $issueNumber,
          '@id' => $issueId,
        ]));
      }

      if ($closedLocally) {
        $this->messenger()->addStatus($this->t('Marked @id as Closed in Issues.md.', ['@id' => $issueId]));
      }
      else {
        $this->messenger()->addWarning($this->t('Created #@number for @id, but did not update Issues.md row to Closed.', [
          '@number' => (string) $issueNumber,
          '@id' => $issueId,
        ]));
      }

      if ($waitSeconds > 0 && $handled < $maxItems) {
        sleep($waitSeconds);
      }
    }

    $this->messenger()->addStatus($this->t('Batch complete. Handled: @handled, Created: @created, Skipped: @skipped, Failed: @failed.', [
      '@handled' => (string) $handled,
      '@created' => (string) $created,
      '@skipped' => (string) $skipped,
      '@failed' => (string) $failed,
    ]));

    $this->persistLastRunStatus([
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
    ]);
  }

  /**
   * Persist last import run status for dashboard visibility.
   */
  private function persistLastRunStatus(array $status): void {
    $this->state->set('dungeoncrawler_tester.open_issues_import_last_run', $status);
    Cache::invalidateTags(['dungeoncrawler_tester.issue_import_status']);
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

      if ($id === '' || $id === 'ID' || $id === '---' || !str_contains($id, '-')) {
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
   * Mark a matching tracker row as Closed after successful issue creation.
   */
  private function markIssueRowClosed(string $issuesFile, string $issueId, int $githubIssueNumber): bool {
    if (!is_file($issuesFile) || !is_writable($issuesFile)) {
      return FALSE;
    }

    $lines = file($issuesFile);
    if (!is_array($lines) || $lines === []) {
      return FALSE;
    }

    $today = date('Y-m-d');
    $updated = FALSE;

    foreach ($lines as $index => $line) {
      $trimmedLine = rtrim((string) $line, "\r\n");
      if (!str_starts_with($trimmedLine, '|')) {
        continue;
      }

      $parts = explode('|', $trimmedLine);
      if (count($parts) < 9) {
        continue;
      }

      $rowId = trim((string) ($parts[1] ?? ''));
      $status = trim((string) ($parts[3] ?? ''));
      if ($rowId !== $issueId || $status !== 'Open') {
        continue;
      }

      $parts[3] = ' Closed ';
      $parts[6] = ' ' . $today . ' ';

      $existingNotes = trim((string) ($parts[7] ?? ''));
      $githubRef = 'Imported to GitHub issue #' . $githubIssueNumber . '.';
      if ($existingNotes === '' || $existingNotes === '-') {
        $parts[7] = ' ' . $githubRef . ' ';
      }
      elseif (!str_contains($existingNotes, 'GitHub issue #' . $githubIssueNumber)) {
        if (!preg_match('/[.!?]$/', $existingNotes)) {
          $existingNotes .= '.';
        }
        $parts[7] = ' ' . $existingNotes . ' ' . $githubRef . ' ';
      }

      $lines[$index] = implode('|', $parts) . PHP_EOL;
      $updated = TRUE;
      break;
    }

    if (!$updated) {
      return FALSE;
    }

    return file_put_contents($issuesFile, implode('', $lines)) !== FALSE;
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
