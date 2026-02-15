<?php

namespace Drupal\dungeoncrawler_tester\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->logger = $logger_factory->get('dungeoncrawler_tester');
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('logger.factory'),
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

}
