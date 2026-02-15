<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\dungeoncrawler_tester\Service\StageDefinitionService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an SDLC reset action for dashboard operators.
 */
class SdlcResetForm extends FormBase {

  public function __construct(
    private readonly StateInterface $state,
    private readonly ConfigFactoryInterface $settingsConfigFactory,
    private readonly ClientInterface $httpClient,
    private readonly Connection $database,
    private readonly StageDefinitionService $stageDefinitions,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('state'),
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('database'),
      $container->get('dungeoncrawler_tester.stage_definitions'),
      $container->get('logger.factory')->get('dungeoncrawler_tester'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_sdlc_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $preview = $this->getResetPreviewStats();

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Closes all currently linked open tester issues with an SDLC reset note, then resets queue and stage state to ready-to-run.'),
    ];

    $form['preview'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Linked open issues to close: @count', ['@count' => $preview['open_issues']]),
        $this->t('Defined stages to reset to active: @count', ['@count' => $preview['defined_stages']]),
        $this->t('Historical stage-state records to clean: @count', ['@count' => $preview['historical_stage_states']]),
        $this->t('Total stage-state entries to reset: @count', ['@count' => $preview['total_stage_states']]),
        $this->t('Queued tester items to clear: @count', ['@count' => $preview['queue_items']]),
      ],
    ];

    $form['confirm_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this will close linked open issues and reset tester execution state.'),
      '#required' => TRUE,
    ];

    $form['force_local_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force local reset even if issue closure fails'),
      '#description' => $this->t('Use only for emergency recovery. This can create state drift if GitHub issues stay open.'),
      '#default_value' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'reset' => [
        '#type' => 'submit',
        '#value' => $this->t('Reset and close all issues'),
        '#button_type' => 'primary',
        '#attributes' => ['class' => ['button--danger']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('confirm_reset')) {
      $form_state->setErrorByName('confirm_reset', $this->t('You must confirm the reset action before continuing.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$repo, $token] = $this->getRepoToken();
    $forceLocalReset = (bool) $form_state->getValue('force_local_reset');

    $stageStates = $this->state->get('dungeoncrawler_tester.stage_state', []);
    $issueNumbers = [];
    $issueToStages = [];

    foreach ($stageStates as $stageId => $state) {
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
        $issueToStages[(int) $issueNumber][] = $stageId;
      }
    }

    $closed = 0;
    $failed = 0;
    $failedIssueNumbers = [];
    $closedIssueNumbers = [];

    if (!empty($issueNumbers) && !$token && !$forceLocalReset) {
      $this->messenger()->addError($this->t('Reset aborted: GitHub token is missing and linked issues cannot be closed. Enable force local reset only if you intentionally want local state reset without closing GitHub issues.'));
      return;
    }

    if (!empty($issueNumbers) && $token) {
      foreach (array_keys($issueNumbers) as $issueNumber) {
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
    }

    if ($failed > 0 && !$forceLocalReset) {
      $this->messenger()->addError($this->t('Reset aborted after issue close failures. @failed issue(s) could not be closed. No local reset changes were applied.', ['@failed' => $failed]));
      $this->logger->warning('SDLC reset aborted due to issue close failures. Failed issues: @issues', [
        '@issues' => implode(', ', $failedIssueNumbers),
      ]);
      return;
    }

    foreach ($stageStates as $stageId => $state) {
      $state['active'] = TRUE;

      $issueNumber = isset($state['issue_number']) ? (int) $state['issue_number'] : 0;
      $isClosedByReset = $issueNumber > 0 && in_array($issueNumber, $closedIssueNumbers, TRUE);

      if ($forceLocalReset || $issueNumber === 0 || $isClosedByReset) {
        unset($state['issue_number'], $state['issue_status']);
      }

      unset($state['failure_reason'], $state['failure_excerpt']);
      $stageStates[$stageId] = $state;
    }

    $this->state->set('dungeoncrawler_tester.stage_state', $stageStates);
    $this->state->set('dungeoncrawler_tester.runs', []);
    $this->state->set('dungeoncrawler_tester.auto_enqueue_last', []);

    $clearedQueueItems = $this->database->delete('queue')
      ->condition('name', 'dungeoncrawler_tester_runs')
      ->execute();

    $this->clearGithubDashboardCaches($repo);
    Cache::invalidateTags(['dungeoncrawler_tester.dashboard', 'dungeoncrawler_tester.queue']);

    if (!$token && !empty($issueNumbers) && $forceLocalReset) {
      $this->messenger()->addWarning($this->t('SDLC reset forced without GitHub closure. Linked issues may still be open remotely.'));
    }

    if ($failed > 0 && $forceLocalReset) {
      $this->messenger()->addWarning($this->t('Forced local reset completed with @failed issue close failure(s). Remaining open issue numbers: @issues', [
        '@failed' => $failed,
        '@issues' => implode(', ', $failedIssueNumbers),
      ]));
    }

    $account = $this->currentUser();
    $this->logger->notice('SDLC reset executed by uid @uid (@name). Issues closed: @closed. Issue close failures: @failed. Queue items cleared: @queue. Force local reset: @force.', [
      '@uid' => $account->id(),
      '@name' => $account->getAccountName(),
      '@closed' => $closed,
      '@failed' => $failed,
      '@queue' => $clearedQueueItems,
      '@force' => $forceLocalReset ? 'yes' : 'no',
    ]);

    $this->messenger()->addStatus($this->t('SDLC reset completed. Issues closed: @closed. Issue close failures: @failed. Queue items cleared: @queue. Test states reset to ready.', [
      '@closed' => $closed,
      '@failed' => $failed,
      '@queue' => $clearedQueueItems,
    ]));

    $form_state->setRedirect('dungeoncrawler_tester.dashboard');
  }

  /**
   * Build a quick impact preview for the reset action.
   */
  private function getResetPreviewStats(): array {
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

    $openIssues = [];
    foreach ($stageStates as $state) {
      $hasOpenIssue = !empty($state['issue_number']) && (($state['issue_status'] ?? 'open') === 'open');
      if ($hasOpenIssue) {
        $openIssues[(int) $state['issue_number']] = TRUE;
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
      'historical_stage_states' => $historicalStageStateCount,
      'total_stage_states' => $totalStageStateCount,
      'queue_items' => $queueItems,
    ];
  }

  /**
   * Resolve GitHub repo and token from settings/env fallback chain.
   */
  private function getRepoToken(): array {
    $testerConfig = $this->settingsConfigFactory->get('dungeoncrawler_tester.settings');
    $repo = (string) ($testerConfig->get('github_repo') ?: '');
    $token = (string) ($testerConfig->get('github_token') ?: '');

    $aiConfig = $this->settingsConfigFactory->get('ai_conversation.settings');
    $repo = $repo ?: (string) ($aiConfig->get('github_repo') ?: $aiConfig->get('copilot_default_repo') ?: '');
    $token = $token ?: (string) ($aiConfig->get('github_token') ?: $aiConfig->get('copilot_token') ?: '');

    $repo = $repo ?: (string) (getenv('TESTER_GITHUB_REPO') ?: 'keithaumiller/forseti.life');
    $token = $token ?: (string) (getenv('TESTER_GITHUB_TOKEN') ?: (getenv('GITHUB_TOKEN_COPILOT') ?: getenv('GITHUB_TOKEN') ?: ''));

    return [$repo, $token];
  }

  /**
   * Close a GitHub issue and add a reset note comment.
   */
  private function closeIssueWithResetNote(string $repo, string $token, int $issueNumber): bool {
    $commentUrl = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/comments";
    $issueUrl = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}";

    try {
      $headers = [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'dungeoncrawler-tester-sdlc-reset',
      ];

      $this->httpClient->request('POST', $commentUrl, [
        'headers' => $headers,
        'json' => [
          'body' => 'Closing issue as part of SDLC reset initiated from tester dashboard. This item should be re-opened or recreated if still actionable after reset.',
        ],
        'timeout' => 10,
      ]);

      $this->httpClient->request('PATCH', $issueUrl, [
        'headers' => $headers,
        'json' => ['state' => 'closed'],
        'timeout' => 10,
      ]);

      return TRUE;
    }
    catch (GuzzleException $e) {
      $this->logger->warning('Failed closing issue #@issue during SDLC reset: @message', [
        '@issue' => $issueNumber,
        '@message' => $e->getMessage(),
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->warning('Unexpected SDLC reset close error for issue #@issue: @message', [
        '@issue' => $issueNumber,
        '@message' => $e->getMessage(),
      ]);
    }

    return FALSE;
  }

  /**
   * Invalidate GitHub-derived dashboard cache entries after reset.
   */
  private function clearGithubDashboardCaches(string $repo): void {
    $issuesCacheKeys = [
      'dungeoncrawler_tester.github_issues.' . $repo . '.ci-failure',
      'dungeoncrawler_tester.github_issues.' . $repo . '.testing-defect',
      'dungeoncrawler_tester.github_issues.' . $repo . '.program-defect',
    ];

    foreach ($issuesCacheKeys as $key) {
      \Drupal::cache()->delete($key);
    }

    \Drupal::cache()->delete('dungeoncrawler_tester.github_open_prs.' . $repo);
  }

}
