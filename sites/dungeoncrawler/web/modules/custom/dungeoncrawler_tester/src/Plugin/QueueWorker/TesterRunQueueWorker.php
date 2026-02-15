<?php

namespace Drupal\dungeoncrawler_tester\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

/**
 * Processes queued test runs for the dashboard.
 *
 * @QueueWorker(
 *   id = "dungeoncrawler_tester_runs",
 *   title = @Translation("Dungeon Crawler tester runs"),
 *   cron = {"time" = 60}
 * )
 */
class TesterRunQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * State storage for run metadata.
   */
  private StateInterface $state;

  /**
   * Logger channel.
   */
  private $logger;

  /**
   * HTTP client for GitHub API calls.
   */
  private ClientInterface $httpClient;

  /**
   * Config factory to read repo/token settings.
   */
  private ConfigFactoryInterface $configFactory;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, LoggerChannelFactoryInterface $logger_factory, ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->logger = $logger_factory->get('dungeoncrawler_tester');
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('http_client'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $stage_id = $data['stage_id'] ?? NULL;
    $job_id = $data['job_id'] ?? NULL;
    $args = $data['args'] ?? [];
    $cwd = $data['cwd'] ?? NULL;
    $display = $data['display'] ?? implode(' ', $args);

    if (!$stage_id || !$job_id || empty($args)) {
      $this->logger->warning('Queue item skipped: missing metadata (job @job, stage @stage).', ['@job' => $job_id ?: 'unknown', '@stage' => $stage_id ?: 'unknown']);
      return;
    }

    $this->updateRun($stage_id, [
      'job_id' => $job_id,
      'command' => $display,
      'status' => 'running',
      'started' => time(),
    ]);
    $this->logger->notice('Queue job @job started for stage @stage: @cmd', ['@job' => $job_id, '@stage' => $stage_id, '@cmd' => $display]);

    $start = microtime(TRUE);
    $exit_code = -1;
    $output = '';

    try {
      $process = new Process($args, $cwd, NULL, NULL, 1800);
      $process->run();
      $exit_code = $process->getExitCode();
      $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
    }
    catch (\Throwable $e) {
      $exit_code = -1;
      $output = 'Process failed: ' . $e->getMessage();
      $this->logger->error('Queue job @job failed (stage @stage): @msg', ['@job' => $job_id, '@stage' => $stage_id, '@msg' => $e->getMessage()]);
    }

    $end = microtime(TRUE);
    $status = $exit_code === 0 ? 'succeeded' : 'failed';
    $this->updateRun($stage_id, [
      'job_id' => $job_id,
      'command' => $display,
      'status' => $status,
      'exit_code' => $exit_code,
      'started' => $this->getExisting($stage_id, 'started') ?? (int) $start,
      'ended' => (int) $end,
      'duration' => $end - $start,
      'output' => mb_strimwidth($output, 0, 4000, "\n…"),
    ]);

    // Auto-pause on failure to prevent further runs until triaged.
    $issue_number = NULL;
    if ($status === 'failed') {
      $issue_number = $this->maybeCreateIssue($stage_id, $display, $exit_code, $output);
    }
    $this->updateStageState($stage_id, $status, $exit_code, $output, $end, $issue_number);

    $this->logger->notice('Queue job @job finished (stage @stage, exit @code, duration @duration s)', [
      '@job' => $job_id,
      '@stage' => $stage_id,
      '@code' => $exit_code,
      '@duration' => sprintf('%.2f', $end - $start),
    ]);
  }

  /**
   * Get a single run field without reloading it multiple times.
   */
  private function getExisting(string $stage_id, string $key): mixed {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    return $runs[$stage_id][$key] ?? NULL;
  }

  /**
   * Persist run metadata.
   */
  private function updateRun(string $stage_id, array $data): void {
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $current = $runs[$stage_id] ?? [];
    $runs[$stage_id] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.runs', $runs);
  }

  /**
   * Auto-pause a stage on failure, and clear pause on success if no issue linked.
   */
  private function updateStageState(string $stage_id, string $status, int $exit_code, string $output, float $ended, ?int $issue_number = NULL): void {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stage_id] ?? [];

    if ($status === 'failed') {
      $excerpt = mb_strimwidth($output, 0, 600, '…');
      $current['active'] = FALSE;
      $current['failure_reason'] = sprintf('Failed at %s (exit %d)', date('Y-m-d H:i', (int) $ended), $exit_code);
      $current['failure_excerpt'] = $excerpt;
      if ($issue_number) {
        $current['issue_number'] = $issue_number;
        $current['issue_status'] = 'open';
      }
    }
    else {
      // On success, clear failure reason but keep any issue linkage and explicit pauses.
      unset($current['failure_reason'], $current['failure_excerpt']);
    }

    $states[$stage_id] = $current;
    $this->state->set('dungeoncrawler_tester.stage_state', $states);
  }

  /**
   * Create a GitHub issue for a failure if repo and token are configured.
   */
  private function maybeCreateIssue(string $stage_id, string $display, int $exit_code, string $output): ?int {
    $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stage_id] ?? [];

    $tester_config = $this->configFactory->get('dungeoncrawler_tester.settings');
    $repo = $tester_config->get('github_repo');
    $token = $tester_config->get('github_token');
    $assignee = $tester_config->get('github_assignee');

    // Fall back to ai_conversation settings or environment variables if unset.
    if (!$repo || !$token) {
      $ai_config = $this->configFactory->get('ai_conversation.settings');
      $repo = $repo ?: $ai_config->get('github_repo');
      $token = $token ?: $ai_config->get('github_token');
      $assignee = $assignee ?: $ai_config->get('github_assignee');
    }

    $repo = $repo ?: getenv('TESTER_GITHUB_REPO');
    $token = $token ?: getenv('TESTER_GITHUB_TOKEN');
    $assignee = $assignee ?: getenv('TESTER_GITHUB_ASSIGNEE');
    if (!$repo || !$token) {
      return NULL;
    }

    $existing_issue_map = is_array($current['issue_test_cases'] ?? NULL) ? $current['issue_test_cases'] : [];
    $existing_issue_numbers = is_array($current['issue_numbers'] ?? NULL) ? $current['issue_numbers'] : [];
    if (!empty($current['issue_number'])) {
      $existing_issue_numbers[] = (int) $current['issue_number'];
    }
    $existing_issue_numbers = array_values(array_unique(array_filter(array_map('intval', $existing_issue_numbers))));

    $failed_test_cases = $this->extractFailedTestCases($output);
    if (empty($failed_test_cases)) {
      $failed_test_cases = [$stage_id . '::UnknownFailure'];
    }

    $created_issue_numbers = [];
    foreach ($failed_test_cases as $test_case) {
      if (isset($existing_issue_map[$test_case]) && !empty($existing_issue_map[$test_case])) {
        continue;
      }

      $title = sprintf('[Tester] %s failed in stage %s (exit %d)', $test_case, $stage_id, $exit_code);
      $body = "Automated failure capture from DungeonCrawler tester.\n\n";
      $body .= "- Stage: " . $stage_id . "\n";
      $body .= "- Test case: " . $test_case . "\n";
      $body .= "- Command: " . $display . "\n";
      $body .= "- Exit code: " . $exit_code . "\n\n";
      $body .= "Latest output (truncated):\n\n";
      $body .= "```\n" . mb_strimwidth($output, 0, 3000, "\n…") . "\n```\n";

      $issue_data = [
        'title' => $title,
        'body' => $body,
        'labels' => ['automated', 'tester'],
      ];

      // Only add assignees if a valid assignee is configured.
      if (!empty($assignee)) {
        $issue_data['assignees'] = [$assignee];
      }

      try {
        $response = $this->httpClient->request('POST', "https://api.github.com/repos/{$repo}/issues", [
          'headers' => [
            'Authorization' => 'token ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'dungeoncrawler-tester',
          ],
          'json' => $issue_data,
          'timeout' => 10,
        ]);

        $payload = json_decode((string) $response->getBody(), TRUE);
        if (!empty($payload['number'])) {
          $issue_number = (int) $payload['number'];
          $existing_issue_map[$test_case] = $issue_number;
          $existing_issue_numbers[] = $issue_number;
          $created_issue_numbers[] = $issue_number;

          $created_labels = array_values(array_filter(array_map(
            static fn(array $label): string => strtolower(trim((string) ($label['name'] ?? ''))),
            is_array($payload['labels'] ?? NULL) ? $payload['labels'] : []
          )));

          $this->logger->notice('Opened GitHub issue #@number for test case @test in stage @stage failure.', [
            '@number' => $issue_number,
            '@test' => $test_case,
            '@stage' => $stage_id,
          ]);

          // Assign @copilot only when issue is explicitly Copilot-ready and cap allows.
          $this->maybeAssignCopilotToIssue($repo, $issue_number, $token, $created_labels);
        }
      }
      catch (\Throwable $e) {
        $this->logger->warning('Could not auto-create GitHub issue for test case @test in stage @stage: @msg', [
          '@test' => $test_case,
          '@stage' => $stage_id,
          '@msg' => $e->getMessage(),
        ]);
      }
    }

    if (!empty($existing_issue_numbers) || !empty($existing_issue_map)) {
      $states = $this->state->get('dungeoncrawler_tester.stage_state', []);
      $stage_state = $states[$stage_id] ?? [];
      $stage_state['issue_numbers'] = array_values(array_unique(array_filter(array_map('intval', $existing_issue_numbers))));
      $stage_state['issue_test_cases'] = $existing_issue_map;
      if (!empty($stage_state['issue_numbers'])) {
        $stage_state['issue_number'] = (int) $stage_state['issue_numbers'][0];
        $stage_state['issue_status'] = 'open';
      }
      $states[$stage_id] = $stage_state;
      $this->state->set('dungeoncrawler_tester.stage_state', $states);
    }

    if (!empty($created_issue_numbers)) {
      return (int) $created_issue_numbers[0];
    }

    if (!empty($existing_issue_numbers)) {
      return (int) $existing_issue_numbers[0];
    }

    return NULL;
  }

  /**
   * Conditionally assign Copilot to a newly created issue.
   */
  private function maybeAssignCopilotToIssue(string $repo, int $issue_number, string $token, array $issue_labels): void {
    $tester_config = $this->configFactory->get('dungeoncrawler_tester.settings');

    $configured_label = $tester_config->get('copilot_assignment_required_label');
    if ($configured_label === NULL) {
      $env_label = getenv('TESTER_COPILOT_REQUIRED_LABEL');
      $required_label = trim((string) ($env_label !== FALSE ? $env_label : 'copilot-ready'));
    }
    else {
      $required_label = trim((string) $configured_label);
    }

    if ($required_label !== '') {
      $normalized = array_values(array_unique(array_map(static fn(string $label): string => strtolower(trim($label)), $issue_labels)));
      if (!in_array(strtolower($required_label), $normalized, TRUE)) {
        $this->postIssueComment(
          $repo,
          $issue_number,
          $token,
          sprintf(
            "Copilot auto-assignment skipped: required label '%s' is missing. Add the label and re-run assignment when this issue is ready for implementation.",
            $required_label
          )
        );
        $this->logger->notice('Skipped Copilot auto-assignment for issue #@number: missing required label "@label".', [
          '@number' => $issue_number,
          '@label' => $required_label,
        ]);
        return;
      }
    }

    $configured_max_open = $tester_config->get('copilot_assignment_max_open');
    if ($configured_max_open === NULL) {
      $env_max_open = getenv('TESTER_COPILOT_MAX_OPEN');
      $max_open = (int) ($env_max_open !== FALSE ? $env_max_open : 2);
    }
    else {
      $max_open = (int) $configured_max_open;
    }

    if ($max_open > 0) {
      $open_count = $this->countOpenCopilotAssignedIssues($repo, $token);
      if ($open_count >= $max_open) {
        $this->postIssueComment(
          $repo,
          $issue_number,
          $token,
          sprintf(
            'Copilot auto-assignment skipped: open Copilot-assigned issues (%d) reached configured cap (%d). Re-try after active Copilot queue decreases.',
            $open_count,
            $max_open
          )
        );
        $this->logger->warning('Skipped Copilot auto-assignment for issue #@number: open Copilot-assigned issues (@count) reached cap (@cap).', [
          '@number' => $issue_number,
          '@count' => $open_count,
          '@cap' => $max_open,
        ]);
        return;
      }
    }

    $this->assignCopilotToIssue($repo, $issue_number, $token);
  }

  /**
   * Post a comment to a GitHub issue.
   */
  private function postIssueComment(string $repo, int $issue_number, string $token, string $message): void {
    try {
      $this->httpClient->request('POST', "https://api.github.com/repos/{$repo}/issues/{$issue_number}/comments", [
        'headers' => [
          'Authorization' => 'token ' . $token,
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester',
        ],
        'json' => [
          'body' => $message,
        ],
        'timeout' => 10,
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not post assignment-skip comment to issue #@number: @msg', [
        '@number' => $issue_number,
        '@msg' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Count open issues currently assigned to Copilot in a repository.
   */
  private function countOpenCopilotAssignedIssues(string $repo, string $token): int {
    try {
      $query = rawurlencode('repo:' . $repo . ' is:issue is:open assignee:Copilot');
      $response = $this->httpClient->request('GET', "https://api.github.com/search/issues?q={$query}&per_page=1", [
        'headers' => [
          'Authorization' => 'token ' . $token,
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester',
        ],
        'timeout' => 10,
      ]);

      $payload = json_decode((string) $response->getBody(), TRUE) ?: [];
      return (int) ($payload['total_count'] ?? 0);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not determine open Copilot-assigned issue count for @repo: @msg', [
        '@repo' => $repo,
        '@msg' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Extract failed PHPUnit test case identifiers from process output.
   */
  private function extractFailedTestCases(string $output): array {
    if ($output === '') {
      return [];
    }

    $matches = [];
    preg_match_all('/^\s*\d+\)\s+([A-Za-z0-9_\\\\]+::[A-Za-z0-9_]+)/m', $output, $matches);

    $cases = [];
    foreach ($matches[1] ?? [] as $test_case) {
      $normalized = trim((string) $test_case);
      if ($normalized !== '') {
        $cases[$normalized] = TRUE;
      }
    }

    return array_keys($cases);
  }

  /**
   * Assign @copilot to an issue to trigger the Copilot agent.
   * 
   * @param string $repo
   *   Repository in format owner/repo.
   * @param int $issue_number
   *   Issue number.
   * @param string $token
   *   GitHub token.
   */
  private function assignCopilotToIssue(string $repo, int $issue_number, string $token): void {
    $copilot_identifiers = ['@copilot', 'Copilot', 'copilot'];
    $last_error = NULL;

    foreach ($copilot_identifiers as $identifier) {
      try {
        $response = $this->httpClient->request('POST', "https://api.github.com/repos/{$repo}/issues/{$issue_number}/assignees", [
          'headers' => [
            'Authorization' => 'token ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'dungeoncrawler-tester',
          ],
          'json' => [
            'assignees' => [$identifier],
          ],
          'timeout' => 10,
        ]);

        $payload = json_decode((string) $response->getBody(), TRUE) ?: [];
        $assigned = array_map(static fn(array $assignee): string => strtolower((string) ($assignee['login'] ?? '')), $payload['assignees'] ?? []);
        if (in_array('copilot', $assigned, TRUE)) {
          $this->logger->notice('Assigned Copilot to issue #@number using identifier "@identifier".', [
            '@number' => $issue_number,
            '@identifier' => $identifier,
          ]);
          return;
        }

        $last_error = 'GitHub response did not include Copilot in assignees.';
      }
      catch (\Throwable $e) {
        $last_error = $e->getMessage();
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
          $error_body = trim((string) $e->getResponse()->getBody());
          if ($error_body !== '') {
            $last_error .= ' | API: ' . mb_strimwidth($error_body, 0, 500, '…');
          }
        }
      }
    }

    try {
      $process = new Process([
        'gh',
        'issue',
        'edit',
        (string) $issue_number,
        '--repo',
        $repo,
        '--add-assignee',
        '@copilot',
      ]);
      $env = array_merge($_ENV, [
        'GH_TOKEN' => $token,
        'GITHUB_TOKEN' => $token,
      ]);
      $process->setEnv($env);
      $process->setTimeout(20);
      $process->run();

      if ($process->isSuccessful()) {
        $this->logger->notice('Assigned Copilot to issue #@number using GitHub CLI fallback.', [
          '@number' => $issue_number,
        ]);
        return;
      }

      $last_error = trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'gh issue edit failed without output.';
    }
    catch (\Throwable $e) {
      $last_error = 'GitHub CLI fallback failed: ' . $e->getMessage();
    }

    $this->logger->warning('Could not assign Copilot to issue #@number after trying all identifiers: @msg', [
      '@number' => $issue_number,
      '@msg' => $last_error ?: 'Unknown error',
    ]);
  }

}
