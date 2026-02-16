<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin GitHub API client for issue/PR operations.
 */
class GithubIssuePrClient implements GithubIssuePrClientInterface {

  /**
   * State key for tester GitHub token.
   */
  private const TOKEN_STATE_KEY = 'dungeoncrawler_tester.github_token';

  /**
   * Default repository fallback used for context and local state namespacing.
   */
  private const DEFAULT_REPO = 'keithaumiller/forseti.life';

  /**
   * Minimum delay between serialized mutative API requests.
   */
  private const MUTATION_MIN_INTERVAL_SECONDS = 1.0;

  /**
   * Maximum retry attempts for rate-limited requests.
   */
  private const RETRY_MAX_ATTEMPTS = 5;

  /**
   * Base delay used for exponential backoff.
   */
  private const RETRY_BASE_DELAY_SECONDS = 1.0;

  /**
   * Maximum delay cap used for backoff calculations.
   */
  private const RETRY_MAX_DELAY_SECONDS = 30.0;

  /**
   * Lock file name used to serialize mutative calls.
   */
  private const MUTATION_LOCK_FILE = 'dungeoncrawler_tester_github_mutation.lock';

  /**
   * State file for mutative rate-limit failure/cooldown tracking.
   */
  private const COOLDOWN_STATE_FILE = 'dungeoncrawler_tester_github_cooldown.json';

  /**
   * Consecutive mutative rate-limit failures before cooldown activates.
   */
  private const COOLDOWN_FAILURE_THRESHOLD = 3;

  /**
   * Cooldown duration (seconds) once threshold is reached.
   */
  private const COOLDOWN_SECONDS = 300;

  /**
   * State file for recently applied mutative dedupe keys.
   */
  private const MUTATION_DEDUPE_STATE_FILE = 'dungeoncrawler_tester_github_mutation_dedupe.json';

  /**
   * Time window (seconds) for mutative dedupe suppression.
   */
  private const MUTATION_DEDUPE_WINDOW_SECONDS = 300;

  /**
   * Logger channel.
   */
  private LoggerChannelInterface $logger;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * {@inheritdoc}
   */
  public function resolveContext(): array {
    $testerConfig = $this->configFactory->get('dungeoncrawler_tester.settings');
    $repo = (string) ($testerConfig->get('github_repo') ?: '');

    $stateToken = trim((string) $this->state->get(self::TOKEN_STATE_KEY, ''));
    $legacyConfigToken = trim((string) ($testerConfig->get('github_token') ?: ''));

    $tokenCandidates = [
      $stateToken,
      $legacyConfigToken,
    ];

    $aiConfig = $this->configFactory->get('ai_conversation.settings');
    if ($repo === '') {
      $repo = (string) ($aiConfig->get('github_repo') ?: $aiConfig->get('copilot_default_repo') ?: '');
    }

    $tokenCandidates[] = (string) ($aiConfig->get('github_token') ?: '');
    $tokenCandidates[] = (string) ($aiConfig->get('copilot_token') ?: '');

    if ($repo === '') {
      $repo = (string) (getenv('TESTER_GITHUB_REPO') ?: self::DEFAULT_REPO);
    }

    $tokenCandidates[] = (string) (getenv('TESTER_GITHUB_TOKEN') ?: '');
    $tokenCandidates[] = (string) (getenv('GITHUB_TOKEN_COPILOT') ?: '');
    $tokenCandidates[] = (string) (getenv('GITHUB_TOKEN') ?: '');

    $tokenCandidates = array_values(array_unique(array_filter(array_map('trim', $tokenCandidates))));

    return [
      'repo' => $repo,
      'token' => $tokenCandidates[0] ?? NULL,
      'token_candidates' => $tokenCandidates,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIssue(string $repo, int $number, ?string $token = NULL): ?array {
    if ($number <= 0) {
      return NULL;
    }

    $url = "https://api.github.com/repos/{$repo}/issues/{$number}";
    return $this->requestJsonObject($url, $token, 8);
  }

  /**
   * {@inheritdoc}
   */
  public function getPullRequest(string $repo, int $number, ?string $token = NULL): ?array {
    if ($number <= 0) {
      return NULL;
    }

    $url = "https://api.github.com/repos/{$repo}/pulls/{$number}";
    return $this->requestJsonObject($url, $token, 8);
  }

  /**
   * {@inheritdoc}
   */
  public function listOpenIssuesByLabel(string $repo, string $label, ?string $token = NULL, int $perPage = 100): array {
    $encodedLabel = rawurlencode($label);
    $url = "https://api.github.com/repos/{$repo}/issues?state=open&labels={$encodedLabel}&per_page={$perPage}";
    return $this->requestJsonList($url, $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function listOpenPullRequests(string $repo, ?string $token = NULL, int $perPage = 100): array {
    $url = "https://api.github.com/repos/{$repo}/pulls?state=open&per_page={$perPage}";
    return $this->requestJsonList($url, $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function createIssue(string $repo, array $issueData, ?string $token = NULL): ?array {
    $url = "https://api.github.com/repos/{$repo}/issues";
    return $this->requestMutationPayload('POST', $url, $issueData, $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function addIssueAssignees(string $repo, int $issueNumber, array $assignees, ?string $token = NULL): ?array {
    if ($issueNumber <= 0 || empty($assignees)) {
      return NULL;
    }

    $url = "https://api.github.com/repos/{$repo}/issues/{$issueNumber}/assignees";
    return $this->requestMutationPayload('POST', $url, ['assignees' => array_values($assignees)], $token, 10);
  }

  /**
   * {@inheritdoc}
   */
  public function searchIssuesTotalCount(string $query, ?string $token = NULL): int {
    $encoded = rawurlencode($query);
    $url = "https://api.github.com/search/issues?q={$encoded}&per_page=1";
    $payload = $this->requestJsonObject($url, $token, 10);
    return (int) (($payload['total_count'] ?? 0));
  }

  /**
   * {@inheritdoc}
   */
  public function requestJson(string $url, ?string $token = NULL, array $extraHeaders = [], bool $paginate = FALSE): array {
    $resolvedToken = $this->resolveToken($token);
    if (!$resolvedToken) {
      return ['items' => [], 'error' => 'No GitHub token configured.'];
    }

    $headers = $this->buildHeaders($resolvedToken);
    foreach ($extraHeaders as $name => $value) {
      $headers[(string) $name] = (string) $value;
    }

    return $this->requestJsonInternal($url, $headers, $paginate);
  }

  /**
   * {@inheritdoc}
   */
  public function requestJsonWithFallback(string $url, array $tokenCandidates, array $extraHeaders = [], bool $paginate = FALSE): array {
    if (empty($tokenCandidates)) {
      return ['items' => [], 'error' => 'No GitHub token configured.'];
    }

    $lastError = 'GitHub request failed.';
    foreach ($tokenCandidates as $tokenCandidate) {
      $tokenCandidate = trim((string) $tokenCandidate);
      if ($tokenCandidate === '') {
        continue;
      }

      $headers = $this->buildHeaders($tokenCandidate);
      foreach ($extraHeaders as $name => $value) {
        $headers[(string) $name] = (string) $value;
      }

      $response = $this->requestJsonInternal($url, $headers, $paginate);
      if (empty($response['error'])) {
        return $response;
      }

      $lastError = (string) ($response['error'] ?? $lastError);
      if (stripos($lastError, 'rate limit') === FALSE) {
        continue;
      }
    }

    return ['items' => [], 'error' => $lastError];
  }

  /**
   * {@inheritdoc}
   */
  public function mutate(string $method, string $url, array $json, ?string $token = NULL, int $timeout = 10): bool {
    $resolvedToken = $this->resolveToken($token);
    if (!$resolvedToken) {
      return FALSE;
    }

    $dedupeKey = $this->buildMutationDedupeKey($method, $url, $json);
    if ($dedupeKey !== NULL && $this->isRecentMutationDuplicate($dedupeKey)) {
      $this->logger->notice('Skipping duplicate GitHub mutation (@method @url) within dedupe window.', [
        '@method' => strtoupper($method),
        '@url' => $url,
      ]);
      return TRUE;
    }

    $response = $this->sendRequestWithRateLimitHandling($method, $url, [
      'headers' => $this->buildHeaders($resolvedToken),
      'json' => $json,
      'timeout' => $timeout,
    ], TRUE);

    if (!$response) {
      return FALSE;
    }

    $status = $response->getStatusCode();
    $success = $status >= 200 && $status < 300;
    if ($success && $dedupeKey !== NULL) {
      $this->rememberAppliedMutation($dedupeKey);
    }

    return $success;
  }

  /**
   * Execute a JSON GET request.
   */
  private function requestJsonObject(string $url, ?string $token = NULL, int $timeout = 8): ?array {
    $resolvedToken = $this->resolveToken($token);
    if (!$resolvedToken) {
      return NULL;
    }

    $response = $this->sendRequestWithRateLimitHandling('GET', $url, [
      'headers' => $this->buildHeaders($resolvedToken),
      'timeout' => $timeout,
    ], FALSE);

    if (!$response) {
      return NULL;
    }

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      return NULL;
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    return is_array($payload) ? $payload : NULL;
  }

  /**
   * Execute a JSON-list GET request.
   */
  private function requestJsonList(string $url, ?string $token = NULL, int $timeout = 10): array {
    $payload = $this->requestJsonObject($url, $token, $timeout);
    if (!is_array($payload) || !array_is_list($payload)) {
      return [];
    }

    return $payload;
  }

  /**
   * Execute a mutation and return decoded payload for successful responses.
   */
  private function requestMutationPayload(string $method, string $url, array $json, ?string $token = NULL, int $timeout = 10): ?array {
    $resolvedToken = $this->resolveToken($token);
    if (!$resolvedToken) {
      return NULL;
    }

    $response = $this->sendRequestWithRateLimitHandling($method, $url, [
      'headers' => $this->buildHeaders($resolvedToken),
      'json' => $json,
      'timeout' => $timeout,
    ], TRUE);

    if (!$response) {
      return NULL;
    }

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
      return NULL;
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    return is_array($payload) ? $payload : [];
  }

  /**
   * Execute GitHub JSON request with optional pagination.
   */
  private function requestJsonInternal(string $url, array $headers, bool $paginate = FALSE): array {
    $items = [];
    $nextUrl = $url;
    $pages = 0;

    while ($nextUrl !== '' && $nextUrl !== NULL) {
      $response = $this->sendRequestWithRateLimitHandling('GET', $nextUrl, [
        'headers' => $headers,
        'timeout' => 10,
      ], FALSE);

      if (!$response) {
        return [
          'items' => [],
          'error' => 'GitHub request failed.',
        ];
      }

      $status = $response->getStatusCode();
      if ($status < 200 || $status >= 300) {
        return [
          'items' => [],
          'error' => 'GitHub API status: ' . $status,
        ];
      }

      $payload = json_decode((string) $response->getBody(), TRUE);
      if (is_array($payload) && array_is_list($payload)) {
        $items = array_merge($items, $payload);
      }
      else {
        return [
          'items' => is_array($payload) ? $payload : [],
          'error' => NULL,
        ];
      }

      $pages++;
      if (!$paginate || $pages >= 20) {
        break;
      }

      $nextUrl = $this->extractNextLink((string) $response->getHeaderLine('Link'));
    }

    return [
      'items' => $items,
      'error' => NULL,
    ];
  }

  /**
   * Send request with rate-limit handling and retries.
   */
  private function sendRequestWithRateLimitHandling(string $method, string $url, array $options, bool $isMutation): ?ResponseInterface {
    if ($isMutation) {
      return $this->withMutationLock(function (float &$lastMutationAt) use ($method, $url, $options): ?ResponseInterface {
        if ($this->isMutationCooldownActive()) {
          $cooldownState = $this->loadCooldownState();
          $cooldownUntil = (int) ($cooldownState['cooldown_until'] ?? 0);
          $remaining = max(0, $cooldownUntil - time());
          $this->logger->warning('GitHub mutative request skipped due to cooldown (@method @url). Remaining cooldown: @seconds second(s).', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@seconds' => $remaining,
          ]);
          return NULL;
        }

        return $this->sendWithRetry($method, $url, $options, TRUE, $lastMutationAt);
      });
    }

    $lastMutationAt = 0.0;
    return $this->sendWithRetry($method, $url, $options, FALSE, $lastMutationAt);
  }

  /**
   * Execute request with retries and optional mutative throttling.
   */
  private function sendWithRetry(string $method, string $url, array $options, bool $isMutation, float &$lastMutationAt): ?ResponseInterface {
    $sawRateLimit = FALSE;

    for ($attempt = 0; $attempt < self::RETRY_MAX_ATTEMPTS; $attempt++) {
      if ($isMutation) {
        $this->enforceMutationThrottle($lastMutationAt);
      }

      try {
        $requestOptions = $options;
        $requestOptions['http_errors'] = FALSE;

        $response = $this->httpClient->request($method, $url, $requestOptions);
        if ($isMutation) {
          $lastMutationAt = microtime(TRUE);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($this->shouldRetryRateLimitResponse($status, $response, $body) && $attempt < self::RETRY_MAX_ATTEMPTS - 1) {
          $sawRateLimit = TRUE;
          $delay = $this->computeRetryDelaySeconds($attempt, $response);
          $this->logger->warning('GitHub rate limit encountered (@method @url, status @status). Retrying in @delay seconds (attempt @attempt/@max).', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@status' => $status,
            '@delay' => number_format($delay, 2),
            '@attempt' => $attempt + 1,
            '@max' => self::RETRY_MAX_ATTEMPTS,
          ]);
          $this->sleepSeconds($delay);
          continue;
        }

        if ($this->shouldRetryRateLimitResponse($status, $response, $body) && $attempt >= self::RETRY_MAX_ATTEMPTS - 1) {
          $sawRateLimit = TRUE;
          if ($isMutation) {
            $this->recordMutativeRateLimitFailure($method, $url);
          }
          return $response;
        }

        if ($isMutation && $status >= 200 && $status < 300) {
          $this->resetMutativeRateLimitFailures();
        }

        return $response;
      }
      catch (GuzzleException $e) {
        if ($attempt >= self::RETRY_MAX_ATTEMPTS - 1) {
          $this->logger->warning('GitHub request failed after retries (@method @url): @message', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@message' => $e->getMessage(),
          ]);
          return NULL;
        }

        $delay = $this->computeFallbackDelaySeconds($attempt);
        $this->logger->warning('GitHub request exception (@method @url): @message. Retrying in @delay seconds (attempt @attempt/@max).', [
          '@method' => strtoupper($method),
          '@url' => $url,
          '@message' => $e->getMessage(),
          '@delay' => number_format($delay, 2),
          '@attempt' => $attempt + 1,
          '@max' => self::RETRY_MAX_ATTEMPTS,
        ]);
        $this->sleepSeconds($delay);
      }
      catch (\Throwable $e) {
        if ($attempt >= self::RETRY_MAX_ATTEMPTS - 1) {
          $this->logger->warning('Unexpected GitHub request error (@method @url): @message', [
            '@method' => strtoupper($method),
            '@url' => $url,
            '@message' => $e->getMessage(),
          ]);
          return NULL;
        }

        $delay = $this->computeFallbackDelaySeconds($attempt);
        $this->sleepSeconds($delay);
      }
    }

    if ($isMutation && $sawRateLimit) {
      $this->recordMutativeRateLimitFailure($method, $url);
    }

    return NULL;
  }

  /**
   * Execute operation under a cross-process mutation lock.
   */
  private function withMutationLock(callable $operation): ?ResponseInterface {
    $lockPath = $this->getTempStatePath(self::MUTATION_LOCK_FILE);
    $handle = @fopen($lockPath, 'c+');

    if ($handle === FALSE) {
      $lastMutationAt = 0.0;
      return call_user_func_array($operation, [&$lastMutationAt]);
    }

    $locked = @flock($handle, LOCK_EX);
    $lastMutationAt = $locked ? $this->readLastMutationTimestamp($handle) : 0.0;

    try {
      return call_user_func_array($operation, [&$lastMutationAt]);
    }
    finally {
      if ($locked) {
        $this->writeLastMutationTimestamp($handle, $lastMutationAt);
        @flock($handle, LOCK_UN);
      }
      @fclose($handle);
    }
  }

  /**
   * Enforce minimum interval between mutative API calls.
   */
  private function enforceMutationThrottle(float $lastMutationAt): void {
    if ($lastMutationAt <= 0) {
      return;
    }

    $elapsed = microtime(TRUE) - $lastMutationAt;
    $remaining = self::MUTATION_MIN_INTERVAL_SECONDS - $elapsed;
    if ($remaining > 0) {
      $this->sleepSeconds($remaining);
    }
  }

  /**
   * Determine whether a response should trigger rate-limit retry handling.
   */
  private function shouldRetryRateLimitResponse(int $status, ResponseInterface $response, string $body): bool {
    if ($status === 429) {
      return TRUE;
    }

    if ($status !== 403) {
      return FALSE;
    }

    $retryAfter = trim((string) $response->getHeaderLine('Retry-After'));
    if ($retryAfter !== '') {
      return TRUE;
    }

    $remaining = trim((string) $response->getHeaderLine('X-RateLimit-Remaining'));
    if ($remaining === '0') {
      return TRUE;
    }

    $bodyLower = strtolower($body);
    return str_contains($bodyLower, 'rate limit') || str_contains($bodyLower, 'secondary rate limit');
  }

  /**
   * Compute retry delay honoring Retry-After and rate-limit reset headers.
   */
  private function computeRetryDelaySeconds(int $attempt, ResponseInterface $response): float {
    $retryAfter = trim((string) $response->getHeaderLine('Retry-After'));
    if ($retryAfter !== '') {
      if (is_numeric($retryAfter)) {
        return min(self::RETRY_MAX_DELAY_SECONDS, max(1.0, (float) $retryAfter));
      }

      $retryAfterTs = strtotime($retryAfter);
      if (is_int($retryAfterTs)) {
        return min(self::RETRY_MAX_DELAY_SECONDS, max(1.0, (float) ($retryAfterTs - time())));
      }
    }

    $resetHeader = trim((string) $response->getHeaderLine('X-RateLimit-Reset'));
    if ($resetHeader !== '' && is_numeric($resetHeader)) {
      $untilReset = (float) $resetHeader - time();
      if ($untilReset > 0) {
        return min(self::RETRY_MAX_DELAY_SECONDS, max(1.0, $untilReset));
      }
    }

    return $this->computeFallbackDelaySeconds($attempt);
  }

  /**
   * Compute exponential backoff delay with jitter.
   */
  private function computeFallbackDelaySeconds(int $attempt): float {
    $baseDelay = self::RETRY_BASE_DELAY_SECONDS * (2 ** $attempt);
    $jitter = mt_rand(0, 1000) / 1000;
    return min(self::RETRY_MAX_DELAY_SECONDS, $baseDelay + $jitter);
  }

  /**
   * Sleep for a floating-point number of seconds.
   */
  private function sleepSeconds(float $seconds): void {
    if ($seconds <= 0) {
      return;
    }

    usleep((int) round($seconds * 1000000));
  }

  /**
   * Read last mutation timestamp from lock file.
   *
   * @param resource $handle
   *   Lock file handle.
   */
  private function readLastMutationTimestamp($handle): float {
    rewind($handle);
    $raw = stream_get_contents($handle);
    if (!is_string($raw)) {
      return 0.0;
    }

    $raw = trim($raw);
    return is_numeric($raw) ? (float) $raw : 0.0;
  }

  /**
   * Persist last mutation timestamp to lock file.
   *
   * @param resource $handle
   *   Lock file handle.
   */
  private function writeLastMutationTimestamp($handle, float $timestamp): void {
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, sprintf('%.6F', $timestamp));
    fflush($handle);
  }

  /**
   * Determine whether mutation cooldown is active.
   */
  private function isMutationCooldownActive(): bool {
    $state = $this->loadCooldownState();
    $cooldownUntil = (int) ($state['cooldown_until'] ?? 0);

    if ($cooldownUntil <= 0) {
      return FALSE;
    }

    if ($cooldownUntil <= time()) {
      $state['cooldown_until'] = 0;
      $state['failures'] = 0;
      $this->saveCooldownState($state);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Record a mutative rate-limit failure and activate cooldown if threshold met.
   */
  private function recordMutativeRateLimitFailure(string $method, string $url): void {
    $state = $this->loadCooldownState();
    $failures = (int) ($state['failures'] ?? 0) + 1;
    $state['failures'] = $failures;

    if ($failures >= self::COOLDOWN_FAILURE_THRESHOLD) {
      $state['cooldown_until'] = time() + self::COOLDOWN_SECONDS;
      $state['failures'] = 0;
      $this->logger->warning('GitHub mutation cooldown activated after repeated rate-limit failures (@method @url). Cooldown: @seconds second(s).', [
        '@method' => strtoupper($method),
        '@url' => $url,
        '@seconds' => self::COOLDOWN_SECONDS,
      ]);
    }

    $this->saveCooldownState($state);
  }

  /**
   * Clear mutative rate-limit failure counters after successful mutation.
   */
  private function resetMutativeRateLimitFailures(): void {
    $state = $this->loadCooldownState();
    $state['failures'] = 0;
    $state['cooldown_until'] = 0;
    $this->saveCooldownState($state);
  }

  /**
   * Load cooldown state from local storage.
   */
  private function loadCooldownState(): array {
    $path = $this->getCooldownStatePath();
    if (!is_file($path)) {
      return ['failures' => 0, 'cooldown_until' => 0];
    }

    $json = @file_get_contents($path);
    if (!is_string($json) || $json === '') {
      return ['failures' => 0, 'cooldown_until' => 0];
    }

    $decoded = json_decode($json, TRUE);
    if (!is_array($decoded)) {
      return ['failures' => 0, 'cooldown_until' => 0];
    }

    return [
      'failures' => max(0, (int) ($decoded['failures'] ?? 0)),
      'cooldown_until' => max(0, (int) ($decoded['cooldown_until'] ?? 0)),
    ];
  }

  /**
   * Persist cooldown state to local storage.
   */
  private function saveCooldownState(array $state): void {
    $path = $this->getCooldownStatePath();
    $payload = [
      'failures' => max(0, (int) ($state['failures'] ?? 0)),
      'cooldown_until' => max(0, (int) ($state['cooldown_until'] ?? 0)),
    ];

    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Get cooldown state file path.
   */
  private function getCooldownStatePath(): string {
    return $this->getTempStatePath(self::COOLDOWN_STATE_FILE);
  }

  /**
   * Build dedupe key for idempotent mutation patterns.
   */
  private function buildMutationDedupeKey(string $method, string $url, array $json): ?string {
    $normalizedMethod = strtoupper(trim($method));

    if ($normalizedMethod === 'PATCH' && str_contains($url, '/issues/')) {
      $state = strtolower(trim((string) ($json['state'] ?? '')));
      if ($state === 'closed') {
        return sha1($normalizedMethod . '|' . $url . '|state=closed');
      }
    }

    if ($normalizedMethod === 'POST' && str_contains($url, '/comments')) {
      $body = trim((string) ($json['body'] ?? ''));
      if ($body !== '') {
        return sha1($normalizedMethod . '|' . $url . '|body=' . $body);
      }
    }

    return NULL;
  }

  /**
   * Determine whether a mutation key is a recent duplicate.
   */
  private function isRecentMutationDuplicate(string $key): bool {
    $state = $this->loadMutationDedupeState();
    $lastApplied = (int) ($state[$key] ?? 0);
    if ($lastApplied <= 0) {
      return FALSE;
    }

    return (time() - $lastApplied) < self::MUTATION_DEDUPE_WINDOW_SECONDS;
  }

  /**
   * Persist successful mutation key.
   */
  private function rememberAppliedMutation(string $key): void {
    $state = $this->loadMutationDedupeState();
    $state[$key] = time();
    $this->saveMutationDedupeState($state);
  }

  /**
   * Load mutation dedupe state and prune expired keys.
   */
  private function loadMutationDedupeState(): array {
    $path = $this->getMutationDedupeStatePath();
    $now = time();
    $state = [];

    if (is_file($path)) {
      $json = @file_get_contents($path);
      if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, TRUE);
        if (is_array($decoded)) {
          foreach ($decoded as $key => $timestamp) {
            $key = (string) $key;
            $timestamp = (int) $timestamp;
            if ($key === '' || $timestamp <= 0) {
              continue;
            }
            if (($now - $timestamp) < self::MUTATION_DEDUPE_WINDOW_SECONDS) {
              $state[$key] = $timestamp;
            }
          }
        }
      }
    }

    return $state;
  }

  /**
   * Save mutation dedupe state.
   */
  private function saveMutationDedupeState(array $state): void {
    $path = $this->getMutationDedupeStatePath();
    @file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES));
  }

  /**
   * Get mutation dedupe state file path.
   */
  private function getMutationDedupeStatePath(): string {
    return $this->getTempStatePath(self::MUTATION_DEDUPE_STATE_FILE);
  }

  /**
   * Build a namespaced temp-state file path for this repository context.
   */
  private function getTempStatePath(string $baseFilename): string {
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $this->getNamespacedStateFilename($baseFilename);
  }

  /**
   * Create a repository-scoped file name from a base state/lock file name.
   */
  private function getNamespacedStateFilename(string $baseFilename): string {
    $namespace = $this->getStateNamespace();
    $dotPos = strrpos($baseFilename, '.');

    if ($dotPos === FALSE) {
      return $baseFilename . '_' . $namespace;
    }

    $name = substr($baseFilename, 0, $dotPos);
    $ext = substr($baseFilename, $dotPos + 1);
    return $name . '_' . $namespace . '.' . $ext;
  }

  /**
   * Build a stable repository-derived namespace for local temp state files.
   */
  private function getStateNamespace(): string {
    $repo = strtolower(trim((string) ($this->resolveContext()['repo'] ?? self::DEFAULT_REPO)));
    if ($repo === '') {
      $repo = self::DEFAULT_REPO;
    }

    return substr(sha1($repo), 0, 12);
  }

  /**
   * Extract next-page URL from GitHub Link header.
   */
  private function extractNextLink(string $linkHeader): ?string {
    if ($linkHeader === '') {
      return NULL;
    }

    foreach (explode(',', $linkHeader) as $part) {
      if (stripos($part, 'rel="next"') === FALSE) {
        continue;
      }

      if (preg_match('/<([^>]+)>/', $part, $matches) === 1) {
        return (string) ($matches[1] ?? NULL);
      }
    }

    return NULL;
  }

  /**
   * Resolve token or use first configured candidate.
   */
  private function resolveToken(?string $token): ?string {
    if (!empty($token)) {
      return $token;
    }

    $context = $this->resolveContext();
    $resolved = $context['token'] ?? NULL;
    return is_string($resolved) && $resolved !== '' ? $resolved : NULL;
  }

  /**
   * Build GitHub API headers.
   */
  private function buildHeaders(string $token): array {
    return [
      'Authorization' => "Bearer {$token}",
      'Accept' => 'application/vnd.github+json',
      'User-Agent' => 'dungeoncrawler-tester-github-client',
    ];
  }

}
