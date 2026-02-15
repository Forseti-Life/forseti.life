<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Single dashboard form handling all stage runs.
 */
class DashboardRunsForm extends FormBase implements ContainerInjectionInterface {

  use DependencySerializationTrait;

  /**
   * State storage.
   */
  private ?StateInterface $state = NULL;

  /**
   * Date formatter service.
   */
  private ?DateFormatterInterface $dateFormatter = NULL;

  /**
   * Stage definitions provider.
   */
  private ?StageDefinitionService $stageDefinitions = NULL;

  /**
   * Queue factory for tester runs.
   */
  private ?QueueFactory $queueFactory = NULL;

  /**
   * UUID generator.
   */
  private ?UuidInterface $uuid = NULL;

  /**
   * Logger channel.
   */
  private ?LoggerChannelInterface $logger = NULL;

  public function __construct(StateInterface $state, DateFormatterInterface $dateFormatter, StageDefinitionService $stageDefinitions, QueueFactory $queueFactory, UuidInterface $uuid, LoggerChannelFactoryInterface $loggerFactory) {
    $this->state = $state;
    $this->dateFormatter = $dateFormatter;
    $this->stageDefinitions = $stageDefinitions;
    $this->queueFactory = $queueFactory;
    $this->uuid = $uuid;
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  public static function create(ContainerInterface $container): static {
    $instance = new static(
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('dungeoncrawler_tester.stage_definitions'),
      $container->get('queue'),
      $container->get('uuid'),
      $container->get('logger.factory'),
    );
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_dashboard_runs_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $definitions = $this->getStageDefinitions()->getDefinitions();
    $runs = $this->getState()->get('dungeoncrawler_tester.runs', []);
    $stage_states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
    $regression_stage_id = 'regression_suite';
    $regression_batch_active = (bool) $this->getState()->get('dungeoncrawler_tester.regression_batch_active', FALSE);
    $non_regression_in_progress_stages = $this->getInProgressStageIds($runs, NULL);
    $non_regression_in_progress = !empty($non_regression_in_progress_stages);

    if ($regression_batch_active && !$non_regression_in_progress) {
      $this->getState()->set('dungeoncrawler_tester.regression_batch_active', FALSE);
      $regression_batch_active = FALSE;
    }

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'stage-grid';

    $form[$regression_stage_id] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-card'], 'id' => 'stage-' . $regression_stage_id],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Regression Test Suite'),
      ],
      'desc' => [
        '#markup' => '<p>' . $this->t('Queues the primary command of each active/runnable stage gate from StageDefinitionService.') . '</p>',
      ],
      'run' => [
        '#type' => 'submit',
        '#value' => $this->t('Run Regression Suite'),
        '#name' => 'run_regression_suite',
        '#submit' => ['::submitRegressionSuite'],
        '#limit_validation_errors' => [],
        '#disabled' => $regression_batch_active || $non_regression_in_progress,
        '#attributes' => ($regression_batch_active || $non_regression_in_progress)
          ? [
            'title' => $regression_batch_active
              ? (string) $this->t('Regression batch is already queued/running.')
              : (string) $this->t('Another stage run is pending/running: @stages', ['@stages' => implode(', ', $non_regression_in_progress_stages)]),
          ]
          : [],
      ],
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $regression_batch_active
          ? $this->t('Regression batch status: running (stage-gate commands queued).')
          : $this->t('Regression batch status: idle.'),
      ],
    ];

    foreach ($definitions as $stage) {
      $stage_id = $stage['id'];
      $run = $runs[$stage_id] ?? NULL;
      $stage_state = $stage_states[$stage_id] ?? [];
      $block_reason = $this->getBlockReason($stage_state);

      $form[$stage_id] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-card'], 'id' => 'stage-' . $stage_id],
      ];

      if ($block_reason) {
        $form[$stage_id]['state_badge'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $block_reason,
          '#attributes' => ['class' => ['stage-state-badge', 'is-blocked']],
        ];
      }

      $form[$stage_id]['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $stage['title'],
      ];
      $form[$stage_id]['desc'] = [
        '#markup' => '<p>' . $stage['description'] . '</p>',
      ];

      $form[$stage_id]['commands'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-command-list']],
      ];

      foreach ($stage['commands'] as $index => $cmd) {
        $form[$stage_id]['commands']['cmd_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['stage-command']],
          'label' => [
            '#markup' => '<strong>' . $cmd['label'] . '</strong>',
          ],
          'display' => [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#value' => $cmd['display'],
            '#attributes' => ['class' => ['command-snippet']],
          ],
          'run' => [
            '#type' => 'submit',
            '#value' => $this->t('Run'),
            '#name' => $stage_id . '_run_' . $index,
            '#stage_id' => $stage_id,
            '#command_meta' => $cmd,
            '#submit' => ['::submitCommand'],
            // No validation gates; just run.
            '#limit_validation_errors' => [],
            '#disabled' => !$this->isStageRunnable($stage_state) || $regression_batch_active,
            '#attributes' => (!$this->isStageRunnable($stage_state) || $regression_batch_active)
              ? ['title' => $block_reason ?: (string) $this->t('Regression batch is active. Stage runs are temporarily locked.')]
              : [],
          ],
        ];
      }

      // Hidden fields to keep context.
      $form[$stage_id]['stage_id'] = [
        '#type' => 'hidden',
        '#value' => $stage_id,
      ];
      $form[$stage_id]['command_meta'] = [
        '#type' => 'value',
        '#value' => $stage['commands'],
      ];

      $form[$stage_id]['last_run'] = $this->buildRunStatus($run);

      // Inline stage controls (pause/resume, issue link) so admins can manage gating.
      $form[$stage_id]['controls'] = [
        '#type' => 'details',
        '#title' => $this->t('Stage controls'),
        '#open' => FALSE,
        'active' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Active (allowed to run)'),
          '#default_value' => $stage_state['active'] ?? TRUE,
        ],
        'auto_resume' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Auto-resume when linked issue closes'),
          '#default_value' => $stage_state['auto_resume'] ?? FALSE,
        ],
        'failure_reason' => [
          '#type' => 'item',
          '#title' => $this->t('Last failure'),
          '#markup' => !empty($stage_state['failure_reason']) ? $stage_state['failure_reason'] : $this->t('None'),
          '#description' => !empty($stage_state['failure_excerpt']) ? '<pre class="command-snippet command-log">' . $stage_state['failure_excerpt'] . '</pre>' : '',
        ],
        'issue_number' => [
          '#type' => 'textfield',
          '#title' => $this->t('Linked issue # (blocks if open)'),
          '#default_value' => $stage_state['issue_number'] ?? '',
          '#size' => 10,
        ],
        'issue_status' => [
          '#type' => 'select',
          '#title' => $this->t('Issue status'),
          '#options' => [
            'open' => $this->t('Open'),
            'closed' => $this->t('Closed'),
          ],
          '#default_value' => $stage_state['issue_status'] ?? 'open',
          '#states' => [
            'visible' => [
              ':input[name="' . $stage_id . '[controls][issue_number]"]' => ['filled' => TRUE],
            ],
          ],
        ],
        'save' => [
          '#type' => 'submit',
          '#value' => $this->t('Save stage controls'),
          '#name' => $stage_id . '_save_controls',
          '#stage_id' => $stage_id,
          '#submit' => ['::submitStageControls'],
          '#limit_validation_errors' => [[$stage_id, 'controls']],
        ],
      ];
    }

    // Keep the action on the same page, anchor back to last clicked stage.
    $form['#action'] = Url::fromRoute('<current>')->toString();

    return $form;
  }

  /**
   * Default submit (unused because buttons use submitCommand).
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addWarning($this->t('Use the Run buttons to execute stage commands.'));
  }

  /**
   * Submit handler for any stage command.
   */
  public function submitCommand(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $stage_id = $trigger['#stage_id'] ?? ($trigger['#parents'][0] ?? '');

    $regression_batch_active = (bool) $this->getState()->get('dungeoncrawler_tester.regression_batch_active', FALSE);
    if ($regression_batch_active) {
      $this->messenger()->addWarning($this->t('Regression batch is active. Stage runs are temporarily locked.'));
      return;
    }

    $stage_state = $this->getStageState($stage_id);
    if (!$this->isStageRunnable($stage_state)) {
      $reason = $this->getBlockReason($stage_state) ?: $this->t('Stage is paused.');
      $this->messenger()->addWarning($reason);
      return;
    }

    // Retrieve command meta either from the button or from stored value.
    $cmd = $trigger['#command_meta'] ?? NULL;
    if (!$cmd) {
      $values = $form_state->getValues();
      if (!empty($values[$stage_id]['command_meta'])) {
        $index = 0;
        if (!empty($trigger['#name']) && preg_match('/_run_(\d+)/', $trigger['#name'], $m)) {
          $index = (int) $m[1];
        }
        $commands = $values[$stage_id]['command_meta'];
        if (isset($commands[$index])) {
          $cmd = $commands[$index];
        }
      }
    }

    if (!$stage_id || !$cmd || empty($cmd['args'])) {
      $this->messenger()->addWarning($this->t('No runnable command for this action.'));
      return;
    }

    // Trace that the submit actually fired.
    $this->getLogger('dungeoncrawler_tester')->notice('Dashboard run triggered', [
      '@stage' => $stage_id,
      '@cmd' => $cmd['display'] ?? implode(' ', $cmd['args'] ?? []),
      '@trigger' => $trigger['#name'] ?? 'unknown',
      '@parents' => implode('/', $trigger['#parents'] ?? []),
    ]);

    $display_cmd = $cmd['display'] ?? implode(' ', $cmd['args'] ?? []);
    $job_id = $this->getUuid()->generate();

    $this->storeRun($stage_id, [
      'job_id' => $job_id,
      'command' => $display_cmd,
      'status' => 'pending',
      'exit_code' => NULL,
      'started' => NULL,
      'ended' => NULL,
      'duration' => NULL,
      'output' => '',
    ]);

    // Enqueue for background processing.
    $queue = $this->getQueueFactory()->get('dungeoncrawler_tester_runs');
    $queue->createItem([
      'job_id' => $job_id,
      'stage_id' => $stage_id,
      'args' => $cmd['args'],
      'cwd' => $cmd['cwd'] ?? NULL,
      'display' => $display_cmd,
    ]);

    $this->messenger()->addStatus($this->t('Queued stage @stage run. Job: @job', ['@stage' => $stage_id, '@job' => $job_id]));
    $this->getLogger('dungeoncrawler_tester')->notice('Stage @stage queued: @cmd (job @job)', [
      '@stage' => $stage_id,
      '@cmd' => $display_cmd,
      '@job' => $job_id,
    ]);

    // Rebuild to refresh the last-run block and scroll back to the stage.
    $form_state->setRebuild(TRUE);
    $form_state->setRedirectUrl(Url::fromRoute('<current>', [], ['fragment' => 'stage-' . $stage_id]));
  }

  /**
   * Submit handler for dashboard-wide regression test suite run.
   */
  public function submitRegressionSuite(array &$form, FormStateInterface $form_state): void {
    $stage_id = 'regression_suite';
    $runs = $this->getState()->get('dungeoncrawler_tester.runs', []);
    $regression_batch_active = (bool) $this->getState()->get('dungeoncrawler_tester.regression_batch_active', FALSE);
    if ($regression_batch_active) {
      $this->messenger()->addWarning($this->t('Regression batch is already queued or running.'));
      return;
    }

    $in_progress = $this->getInProgressStageIds($runs, NULL);
    if (!empty($in_progress)) {
      $this->messenger()->addWarning($this->t('Cannot queue regression while stage runs are pending/running: @stages', ['@stages' => implode(', ', $in_progress)]));
      return;
    }

    $definitions = $this->getStageDefinitions()->getDefinitions();
    $stage_states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
    $queue = $this->getQueueFactory()->get('dungeoncrawler_tester_runs');

    $queued_stage_ids = [];
    foreach ($definitions as $stage) {
      $current_stage_id = $stage['id'] ?? '';
      if ($current_stage_id === '' || $current_stage_id === $stage_id) {
        continue;
      }

      $stage_state = $stage_states[$current_stage_id] ?? [];
      if (!$this->isStageRunnable($stage_state)) {
        continue;
      }

      $primary = $stage['commands'][0] ?? NULL;
      if (!$primary || empty($primary['args'])) {
        continue;
      }

      $job_id = $this->getUuid()->generate();
      $display_cmd = $primary['display'] ?? implode(' ', $primary['args']);

      $this->storeRun($current_stage_id, [
        'job_id' => $job_id,
        'command' => $display_cmd,
        'status' => 'pending',
        'exit_code' => NULL,
        'started' => NULL,
        'ended' => NULL,
        'duration' => NULL,
        'output' => '',
      ]);

      $queue->createItem([
        'job_id' => $job_id,
        'stage_id' => $current_stage_id,
        'args' => $primary['args'],
        'cwd' => $primary['cwd'] ?? NULL,
        'display' => $display_cmd,
      ]);

      $queued_stage_ids[] = $current_stage_id;
    }

    if (empty($queued_stage_ids)) {
      $this->messenger()->addWarning($this->t('No active/runnable stage-gate commands were eligible for regression queueing.'));
      return;
    }

    $this->getState()->set('dungeoncrawler_tester.regression_batch_active', TRUE);
    $this->messenger()->addStatus($this->t('Queued regression batch for @count stage gate(s): @stages', [
      '@count' => count($queued_stage_ids),
      '@stages' => implode(', ', $queued_stage_ids),
    ]));
    $this->getLogger('dungeoncrawler_tester')->notice('Regression batch queued for @count stage gate(s): @stages', [
      '@count' => count($queued_stage_ids),
      '@stages' => implode(', ', $queued_stage_ids),
    ]);

    $form_state->setRebuild(TRUE);
    $form_state->setRedirectUrl(Url::fromRoute('<current>', [], ['fragment' => 'stage-' . $stage_id]));
  }

  /**
   * Return stage ids that are currently pending/running.
   */
  private function getInProgressStageIds(array $runs, ?string $excludeStageId = NULL): array {
    $stageIds = [];

    foreach ($runs as $stageId => $run) {
      if ($excludeStageId !== NULL && $stageId === $excludeStageId) {
        continue;
      }

      $status = $run['status'] ?? '';
      if (in_array($status, ['pending', 'running'], TRUE)) {
        $stageIds[] = (string) $stageId;
      }
    }

    return $stageIds;
  }

  /**
   * Submit handler for per-stage control updates (active/issue linkage).
   */
  public function submitStageControls(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $stage_id = $trigger['#stage_id'] ?? ($trigger['#parents'][0] ?? '');
    if (!$stage_id) {
      $this->messenger()->addError($this->t('Unable to determine stage for controls update.'));
      return;
    }

    $values = $form_state->getValues();
    $controls = $values[$stage_id]['controls'] ?? [];

    $active = !empty($controls['active']);
    $issue_number_raw = trim((string) ($controls['issue_number'] ?? ''));
    $issue_number = $issue_number_raw === '' ? NULL : (int) $issue_number_raw;
    $issue_status = $issue_number ? ($controls['issue_status'] ?? 'open') : NULL;

    $this->saveStageState($stage_id, [
      'active' => $active,
      'auto_resume' => !empty($controls['auto_resume']),
      'issue_number' => $issue_number,
      'issue_status' => $issue_status,
      // Clearing failure markers when saving controls is useful after manual triage.
      'failure_reason' => NULL,
      'failure_excerpt' => NULL,
    ]);

    $msg = $active ? $this->t('Stage @stage is active.', ['@stage' => $stage_id]) : $this->t('Stage @stage paused.', ['@stage' => $stage_id]);
    if ($issue_number) {
      $msg .= ' ' . $this->t('Linked issue: #@n (@s).', ['@n' => $issue_number, '@s' => $issue_status]);
    }

    $this->messenger()->addStatus($msg);
    $form_state->setRebuild(TRUE);
    $form_state->setRedirectUrl(Url::fromRoute('<current>', [], ['fragment' => 'stage-' . $stage_id]));
  }

  /**
   * Persist last run metadata per stage.
   */
  private function storeRun(string $stage_id, array $data): void {
    $runs = $this->getState()->get('dungeoncrawler_tester.runs', []);
    $current = $runs[$stage_id] ?? [];
    $runs[$stage_id] = array_merge($current, $data);
    $this->getState()->set('dungeoncrawler_tester.runs', $runs);
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
    $status_key = $run['status'] ?? (isset($run['exit_code']) ? ($run['exit_code'] === 0 ? 'succeeded' : 'failed') : 'unknown');
    $status_label = [
      'pending' => $this->t('Pending'),
      'running' => $this->t('Running'),
      'succeeded' => $this->t('Passed'),
      'failed' => $this->t('Failed'),
    ][$status_key] ?? $this->t('Unknown');
    $time = !empty($run['ended']) ? $this->getDateFormatter()->format($run['ended'], 'short') : $this->t('in progress');
    $duration = isset($run['duration']) && $run['duration'] !== NULL ? sprintf('%.1fs', $run['duration']) : '';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stage-run-status']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $this->t('Last run'),
      ],
      'content' => [
        '#markup' => '<p><strong>' . $status_label . '</strong> · ' . $time . ' ' . ($duration ? '· ' . $duration : '') . '</p>',
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
   * Fetch per-stage state with defaults.
   */
  private function getStageState(string $stage_id): array {
    $states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
    return $states[$stage_id] ?? [];
  }

  /**
   * Lazy-load state service.
   */
  private function getState(): StateInterface {
    if (!$this->state) {
      $this->state = \Drupal::state();
    }
    return $this->state;
  }

  /**
   * Lazy-load date formatter service.
   */
  private function getDateFormatter(): DateFormatterInterface {
    if (!$this->dateFormatter) {
      $this->dateFormatter = \Drupal::service('date.formatter');
    }
    return $this->dateFormatter;
  }

  /**
   * Lazy-load stage definitions service.
   */
  private function getStageDefinitions(): StageDefinitionService {
    if (!$this->stageDefinitions) {
      $this->stageDefinitions = \Drupal::service('dungeoncrawler_tester.stage_definitions');
    }
    return $this->stageDefinitions;
  }

  /**
   * Lazy-load queue factory service.
   */
  private function getQueueFactory(): QueueFactory {
    if (!$this->queueFactory) {
      $this->queueFactory = \Drupal::service('queue');
    }
    return $this->queueFactory;
  }

  /**
   * Lazy-load UUID service.
   */
  private function getUuid(): UuidInterface {
    if (!$this->uuid) {
      $this->uuid = \Drupal::service('uuid');
    }
    return $this->uuid;
  }

  /**
   * Persist per-stage state.
   */
  private function saveStageState(string $stage_id, array $data): void {
    $states = $this->getState()->get('dungeoncrawler_tester.stage_state', []);
    $current = $states[$stage_id] ?? [];
    $states[$stage_id] = array_merge($current, $data);
    $this->getState()->set('dungeoncrawler_tester.stage_state', $states);
  }

  /**
   * Determine if a stage is allowed to run.
   */
  private function isStageRunnable(array $stage_state): bool {
    if (array_key_exists('active', $stage_state) && !$stage_state['active']) {
      return FALSE;
    }
    if (!empty($stage_state['issue_number'])) {
      $status = $stage_state['issue_status'] ?? 'open';
      if ($status !== 'closed') {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Human-friendly block reason for UI/messaging.
   */
  private function getBlockReason(array $stage_state): ?string {
    if (array_key_exists('active', $stage_state) && !$stage_state['active']) {
      if (!empty($stage_state['failure_reason'])) {
        return $this->t('Stage paused after failure: @r', ['@r' => $stage_state['failure_reason']]);
      }
      return $this->t('Stage is paused.');
    }
    if (!empty($stage_state['issue_number'])) {
      $status = $stage_state['issue_status'] ?? 'open';
      if ($status !== 'closed') {
        return $this->t('Blocked by issue #@n (@s).', ['@n' => $stage_state['issue_number'], '@s' => $status]);
      }
    }
    return NULL;
  }

}
