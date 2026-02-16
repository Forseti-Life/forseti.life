<?php

namespace Drupal\dungeoncrawler_tester\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Button form to close dead-value PRs and optionally linked issues.
 */
class DeadValueCloseForm extends FormBase {

  /**
   * Standard close comment for dead-value PRs.
   */
  private const DEAD_VALUE_COMMENT = 'Dead value: this PR has no diff from main and no changed files. Closing this PR and associated issue.';

  /**
   * Default repo fallback.
   */
  private const DEFAULT_REPO = 'keithaumiller/forseti.life';

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactoryService,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('dungeoncrawler_tester'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_tester_dead_value_close_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $prNumber = 0, int $issueNumber = 0): array {
    $form['pr_number'] = [
      '#type' => 'hidden',
      '#value' => $prNumber,
    ];

    $form['issue_number'] = [
      '#type' => 'hidden',
      '#value' => $issueNumber,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $issueNumber > 0
        ? $this->t('Close dead PR + issue')
        : $this->t('Close dead PR'),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $prNumber = (int) $form_state->getValue('pr_number');
    $issueNumber = (int) $form_state->getValue('issue_number');

    if ($prNumber <= 0) {
      $this->messenger()->addError($this->t('Cannot close PR: missing PR number.'));
      return;
    }

    $githubContext = $this->resolveGitHubContext();
    $repo = $githubContext['repo'];
    $token = $githubContext['token'];
    if (!$token) {
      $this->messenger()->addError($this->t('Cannot close PR: GitHub token is not configured.'));
      return;
    }

    $base = 'https://api.github.com/repos/' . rawurlencode($repo);
    $base = str_replace('%2F', '/', $base);

    $prCommented = $this->request('POST', $base . '/issues/' . $prNumber . '/comments', $token, ['body' => self::DEAD_VALUE_COMMENT]);
    $prClosed = $this->request('PATCH', $base . '/pulls/' . $prNumber, $token, ['state' => 'closed']);

    $issueCommented = TRUE;
    $issueClosed = TRUE;
    if ($issueNumber > 0 && $issueNumber !== $prNumber) {
      $issueCommented = $this->request('POST', $base . '/issues/' . $issueNumber . '/comments', $token, ['body' => self::DEAD_VALUE_COMMENT]);
      $issueClosed = $this->request('PATCH', $base . '/issues/' . $issueNumber, $token, ['state' => 'closed']);
    }

    if ($prCommented && $prClosed && $issueCommented && $issueClosed) {
      $this->messenger()->addStatus($issueNumber > 0
        ? $this->t('Closed dead-value PR #@pr and associated issue #@issue.', ['@pr' => $prNumber, '@issue' => $issueNumber])
        : $this->t('Closed dead-value PR #@pr.', ['@pr' => $prNumber]));
    }
    else {
      $this->messenger()->addWarning($this->t('Close action completed with warnings for PR #@pr. Check logs for API details.', ['@pr' => $prNumber]));
    }

    $form_state->setRedirect('dungeoncrawler_tester.issue_pr_report');
  }

  /**
   * Resolve GitHub repo/token from existing settings precedence.
   */
  private function resolveGitHubContext(): array {
    $aiSettings = $this->configFactoryService->get('ai_conversation.settings');
    $testerSettings = $this->configFactoryService->get('dungeoncrawler_tester.settings');

    $repo = $aiSettings->get('copilot_default_repo')
      ?: $aiSettings->get('github_repo')
      ?: $testerSettings->get('github_repo')
      ?: (getenv('TESTER_GITHUB_REPO') ?: self::DEFAULT_REPO);

    $token = $aiSettings->get('copilot_token')
      ?: $aiSettings->get('github_token')
      ?: $testerSettings->get('github_token')
      ?: (getenv('GITHUB_TOKEN_COPILOT') ?: (getenv('GITHUB_TOKEN') ?: getenv('TESTER_GITHUB_TOKEN')));

    return [
      'repo' => (string) $repo,
      'token' => $token ? (string) $token : NULL,
    ];
  }

  /**
   * Perform a GitHub API request with JSON payload.
   */
  private function request(string $method, string $url, string $token, array $json = []): bool {
    try {
      $response = $this->httpClient->request($method, $url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Accept' => 'application/vnd.github+json',
          'User-Agent' => 'dungeoncrawler-tester-dashboard',
        ],
        'json' => $json,
        'timeout' => 10,
      ]);

      $status = $response->getStatusCode();
      return $status >= 200 && $status < 300;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Dead-value close request failed for @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

}
