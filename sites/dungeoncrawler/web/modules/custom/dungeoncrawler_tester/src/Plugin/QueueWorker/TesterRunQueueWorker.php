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

    // Skip if already linked or auto-issue explicitly disabled.
    if (!empty($current['issue_number'])) {
      return NULL;
    }

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

    $title = sprintf('[Tester] Stage %s failed (exit %d)', $stage_id, $exit_code);
    $body = "Automated failure capture from DungeonCrawler tester.\n\n";
    $body .= "- Stage: " . $stage_id . "\n";
    $body .= "- Command: " . $display . "\n";
    $body .= "- Exit code: " . $exit_code . "\n\n";
    $body .= "Latest output (truncated):\n\n";
    $body .= "```\n" . mb_strimwidth($output, 0, 3000, "\n…") . "\n```\n";

    $issue_data = [
      'title' => $title,
      'body' => $body,
      'labels' => ['automated', 'tester'],
    ];
    
    // Only add assignees if a valid assignee is configured
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
        $this->logger->notice('Opened GitHub issue #@number for stage @stage failure.', ['@number' => $issue_number, '@stage' => $stage_id]);
        
        // Assign @copilot to trigger the agent (must be done after creation)
        $this->assignCopilotToIssue($repo, $issue_number, $token);
        
        return $issue_number;
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not auto-create GitHub issue for stage @stage: @msg', ['@stage' => $stage_id, '@msg' => $e->getMessage()]);
    }

    return NULL;
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
    try {
      $this->httpClient->request('POST', "https://api.github.com/repos/{$repo}/issues/{$issue_number}/assignees", [
        'headers' => [
          'Authorization' => 'token ' . $token,
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester',
        ],
        'json' => [
          'assignees' => ['copilot'],
        ],
        'timeout' => 10,
      ]);
      
      $this->logger->notice('Assigned @copilot to issue #@number to trigger agent.', ['@number' => $issue_number]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not assign @copilot to issue #@number: @msg', [
        '@number' => $issue_number,
        '@msg' => $e->getMessage(),
      ]);
    }
  }

}
