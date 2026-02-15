<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Single dashboard form handling all stage runs.
 */
class DashboardRunsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * State storage.
   */
  private ?StateInterface $state = NULL;

  /**
   * Date formatter service.
   */
  private ?DateFormatterInterface $dateFormatter = NULL;

  public function __construct(?StateInterface $state = NULL, ?DateFormatterInterface $dateFormatter = NULL) {
    // Fallback for non-container instantiation paths.
    $this->state = $state ?: \Drupal::state();
    $this->dateFormatter = $dateFormatter ?: \Drupal::service('date.formatter');
  }

  public static function create(ContainerInterface $container): static {
    $instance = new static(
      $container->get('state'),
      $container->get('date.formatter')
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
    if (!$this->state) {
      $this->state = \Drupal::state();
    }
    $definitions = $this->getStageDefinitions();
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);

    $form['#tree'] = TRUE;
    $form['#attributes']['class'][] = 'stage-grid';

    foreach ($definitions as $stage) {
      $stage_id = $stage['id'];
      $run = $runs[$stage_id] ?? NULL;

      $form[$stage_id] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['stage-card'], 'id' => 'stage-' . $stage_id],
      ];

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
    if (!$this->state) {
      $this->state = \Drupal::state();
    }
    $trigger = $form_state->getTriggeringElement();
    $stage_id = $trigger['#stage_id'] ?? ($trigger['#parents'][0] ?? '');

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
    $job_id = \Drupal::service('uuid')->generate();

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
    $queue = \Drupal::queue('dungeoncrawler_tester_runs');
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
   * Persist last run metadata per stage.
   */
  private function storeRun(string $stage_id, array $data): void {
    if (!$this->state) {
      $this->state = \Drupal::state();
    }
    $runs = $this->state->get('dungeoncrawler_tester.runs', []);
    $current = $runs[$stage_id] ?? [];
    $runs[$stage_id] = array_merge($current, $data);
    $this->state->set('dungeoncrawler_tester.runs', $runs);
  }

  /**
   * Render last run status block.
   */
  private function buildRunStatus(?array $run): array {
    if (!$this->dateFormatter) {
      $this->dateFormatter = \Drupal::service('date.formatter');
    }
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
    $time = !empty($run['ended']) ? $this->dateFormatter->format($run['ended'], 'short') : $this->t('in progress');
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
   * Stage definitions.
   */
  private function getStageDefinitions(): array {
    $root = dirname(\Drupal::root());

    return [
      [
        'id' => 'precommit',
        'title' => $this->t('Pre-commit: lint/format + unit'),
        'description' => $this->t('Keep fast checks green before pushing.'),
        'commands' => [
          [
            'label' => $this->t('Unit suite'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--testsuite=unit'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --testsuite=unit',
          ],
        ],
      ],
      [
        'id' => 'functional-routes',
        'title' => $this->t('Functional routes/controllers'),
        'description' => $this->t('Public, admin, character, campaign, API endpoints.'),
        'commands' => [
          [
            'label' => $this->t('Routes'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/',
          ],
          [
            'label' => $this->t('Controllers'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/',
          ],
          [
            'label' => $this->t('API group'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=api'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=api',
          ],
        ],
      ],
      [
        'id' => 'character-workflow',
        'title' => $this->t('Character creation workflow'),
        'description' => $this->t('8-step wizard, validation, persistence.'),
        'commands' => [
          [
            'label' => $this->t('Workflow group'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=character-creation'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=character-creation',
          ],
        ],
      ],
      [
        'id' => 'entity-campaign',
        'title' => $this->t('Entity/campaign APIs'),
        'description' => $this->t('State validation, access, lifecycle.'),
        'commands' => [
          [
            'label' => $this->t('Entity lifecycle trio'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'tests/src/Functional/CampaignStateAccessTest.php', 'tests/src/Functional/CampaignStateValidationTest.php', 'tests/src/Functional/EntityLifecycleTest.php'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/CampaignStateAccessTest.php tests/src/Functional/CampaignStateValidationTest.php tests/src/Functional/EntityLifecycleTest.php',
          ],
        ],
      ],
      [
        'id' => 'fixtures',
        'title' => $this->t('Cross-check fixtures'),
        'description' => $this->t('PF2e reference + character fixtures up to date.'),
        'commands' => [
          [
            'label' => $this->t('PF2e rules group'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=pf2e-rules'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=pf2e-rules',
          ],
        ],
      ],
      [
        'id' => 'ci-gate',
        'title' => $this->t('CI gate'),
        'description' => $this->t('All suites green; failures auto-filed.'),
        'commands' => [
          [
            'label' => $this->t('Full suite with coverage'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--coverage-html', 'tests/coverage'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage',
          ],
        ],
      ],
      [
        'id' => 'signoff',
        'title' => $this->t('Release sign-off'),
        'description' => $this->t('No open ci-failure/testing-defect blocking issues.'),
        'commands' => [
          [
            'label' => $this->t('Review open defects'),
            'args' => [],
            'cwd' => $root,
            'display' => 'Open GitHub issues (ci-failure, testing-defect)',
            'link' => 'https://github.com/keithaumiller/forseti.life/issues?q=is%3Aissue+is%3Aopen+label%3Aci-failure+label%3Atesting-defect',
          ],
        ],
      ],
    ];
  }

}
