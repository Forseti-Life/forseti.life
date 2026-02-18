<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Vertex image generation integration service.
 */
class VertexImageGenerationService {

  /**
   * Logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Time service.
   */
  protected TimeInterface $time;

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * Constructs VertexImageGenerationService.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, TimeInterface $time, ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
  }

  /**
   * Return integration status for dashboard display.
   *
   * @return array<string, mixed>
   *   Integration status values.
   */
  public function getIntegrationStatus(): array {
    $config = $this->getSettings();
    $api_key = $this->resolveApiKey($config);

    return [
      'enabled' => (bool) $config->get('vertex_image_enabled'),
      'has_api_key' => $api_key !== '',
      'api_key_source' => $config->get('vertex_image_api_key') ? 'config' : (getenv('VERTEX_API_KEY') ? 'env' : 'none'),
      'project_id' => $this->resolveProjectId($config),
      'location' => $this->resolveLocation($config),
      'model' => $this->resolveModel($config),
      'endpoint' => $this->resolveEndpointTemplate($config),
      'timeout' => $this->resolveTimeout($config),
    ];
  }

  /**
   * Generates an image using Vertex live mode or stub fallback.
   *
   * @param array<string, mixed> $payload
   *   Input request payload.
   *
   * @return array<string, mixed>
   *   Normalized generation result.
   */
  public function generateImage(array $payload): array {
    $timestamp = $this->time->getCurrentTime();
    $request_id = sprintf('vertex-stub-%d-%d', $timestamp, random_int(1000, 9999));
    $config = $this->getSettings();
    $status = $this->getIntegrationStatus();

    $normalized_payload = [
      'prompt' => trim((string) ($payload['prompt'] ?? '')),
      'style' => trim((string) ($payload['style'] ?? 'fantasy')),
      'aspect_ratio' => trim((string) ($payload['aspect_ratio'] ?? '1:1')),
      'negative_prompt' => trim((string) ($payload['negative_prompt'] ?? '')),
      'campaign_context' => trim((string) ($payload['campaign_context'] ?? '')),
      'requested_by_uid' => (int) ($payload['requested_by_uid'] ?? 0),
      'requested_at' => $timestamp,
    ];

    if (!$status['enabled'] || !$status['has_api_key']) {
      $mode = !$status['enabled'] ? 'stub' : 'stub_missing_api_key';
      $message = !$status['enabled']
        ? 'Stub accepted. External Vertex API call is not enabled in settings.'
        : 'Stub accepted. Vertex live mode enabled but no API key was found.';

      $this->loggerFactory->get('dungeoncrawler_content')->notice('Vertex image generation stub invoked.', [
        'request_id' => $request_id,
        'mode' => $mode,
        'prompt_length' => strlen($normalized_payload['prompt']),
        'style' => $normalized_payload['style'],
        'aspect_ratio' => $normalized_payload['aspect_ratio'],
        'requested_by_uid' => $normalized_payload['requested_by_uid'],
      ]);

      return [
        'success' => TRUE,
        'provider' => 'vertex',
        'mode' => $mode,
        'request_id' => $request_id,
        'status' => 'accepted_for_integration_stub',
        'message' => $message,
        'payload' => $normalized_payload,
      ];
    }

    $request_id = sprintf('vertex-live-%d-%d', $timestamp, random_int(1000, 9999));
    $api_key = $this->resolveApiKey($config);
    $project_id = $this->resolveProjectId($config);
    $location = $this->resolveLocation($config);
    $model = $this->resolveModel($config);
    $endpoint = $this->buildEndpoint($this->resolveEndpointTemplate($config), $project_id, $location, $model, $api_key);
    $timeout = $this->resolveTimeout($config);
    $request_body = $this->buildVertexRequestBody($normalized_payload);

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
        'json' => $request_body,
        'timeout' => $timeout,
      ]);

      $decoded = json_decode((string) $response->getBody(), TRUE);
      if (!is_array($decoded)) {
        throw new \RuntimeException('Vertex response was not valid JSON.');
      }

      $parsed_output = $this->extractOutput($decoded);

      $this->loggerFactory->get('dungeoncrawler_content')->notice('Vertex image generation live request completed.', [
        'request_id' => $request_id,
        'http_status' => $response->getStatusCode(),
        'has_image' => $parsed_output['image_data_uri'] !== NULL || $parsed_output['image_url'] !== NULL,
      ]);

      return [
        'success' => TRUE,
        'provider' => 'vertex',
        'provider_model' => $model,
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'completed',
        'message' => 'Vertex API request completed.',
        'payload' => $normalized_payload,
        'output' => $parsed_output,
      ];
    }
    catch (GuzzleException | \RuntimeException $exception) {
      $this->loggerFactory->get('dungeoncrawler_content')->error('Vertex image generation request failed.', [
        'request_id' => $request_id,
        'message' => $exception->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'provider' => 'vertex',
        'provider_model' => $model,
        'mode' => 'live',
        'request_id' => $request_id,
        'status' => 'failed',
        'message' => 'Vertex request failed: ' . $exception->getMessage(),
        'payload' => $normalized_payload,
      ];
    }
  }

  /**
   * Return module settings config.
   */
  private function getSettings(): ImmutableConfig {
    return $this->configFactory->get('dungeoncrawler_content.settings');
  }

  /**
   * Resolve API key from config first, then environment.
   */
  private function resolveApiKey(ImmutableConfig $config): string {
    $configured_key = trim((string) $config->get('vertex_image_api_key'));
    if ($configured_key !== '') {
      return $configured_key;
    }

    $env_key = getenv('VERTEX_API_KEY');
    if (is_string($env_key)) {
      return trim($env_key);
    }

    return '';
  }

  /**
   * Resolve configured project id.
   */
  private function resolveProjectId(ImmutableConfig $config): string {
    return trim((string) $config->get('vertex_image_project_id'));
  }

  /**
   * Resolve configured location.
   */
  private function resolveLocation(ImmutableConfig $config): string {
    $location = trim((string) $config->get('vertex_image_location'));
    return $location !== '' ? $location : 'us-central1';
  }

  /**
   * Resolve configured model name.
   */
  private function resolveModel(ImmutableConfig $config): string {
    $model = trim((string) $config->get('vertex_image_model'));
    return $model !== '' ? $model : 'imagen-3.0-generate-002';
  }

  /**
   * Resolve configured endpoint template.
   */
  private function resolveEndpointTemplate(ImmutableConfig $config): string {
    $endpoint = trim((string) $config->get('vertex_image_endpoint'));
    return $endpoint !== ''
      ? $endpoint
      : 'https://{location}-aiplatform.googleapis.com/v1/projects/{project_id}/locations/{location}/publishers/google/models/{model}:predict';
  }

  /**
   * Resolve configured request timeout.
   */
  private function resolveTimeout(ImmutableConfig $config): int {
    $timeout = (int) $config->get('vertex_image_timeout');
    return $timeout >= 5 ? $timeout : 30;
  }

  /**
   * Build endpoint URL with location, project, model and API key.
   */
  private function buildEndpoint(string $template, string $project_id, string $location, string $model, string $api_key): string {
    $endpoint = str_replace('{project_id}', rawurlencode($project_id), $template);
    $endpoint = str_replace('{location}', rawurlencode($location), $endpoint);
    $endpoint = str_replace('{model}', rawurlencode($model), $endpoint);

    if (strpos($endpoint, 'key=') === FALSE) {
      $separator = strpos($endpoint, '?') === FALSE ? '?' : '&';
      $endpoint .= $separator . 'key=' . rawurlencode($api_key);
    }

    return $endpoint;
  }

  /**
   * Build Vertex request body from normalized payload.
   */
  private function buildVertexRequestBody(array $normalized_payload): array {
    return [
      'instances' => [
        [
          'prompt' => $normalized_payload['prompt'],
        ],
      ],
      'parameters' => [
        'sampleCount' => 1,
        'aspectRatio' => $normalized_payload['aspect_ratio'],
        'style' => $normalized_payload['style'],
        'negativePrompt' => $normalized_payload['negative_prompt'],
      ],
    ];
  }

  /**
   * Extract text/image output from Vertex response payload.
   *
   * @return array<string, string|null>
   *   Parsed output values.
   */
  private function extractOutput(array $response): array {
    $output = [
      'text' => NULL,
      'image_data_uri' => NULL,
      'image_url' => NULL,
    ];

    $predictions = $response['predictions'] ?? [];
    if (!is_array($predictions)) {
      return $output;
    }

    foreach ($predictions as $prediction) {
      if (!is_array($prediction)) {
        continue;
      }

      if ($output['image_data_uri'] === NULL && !empty($prediction['bytesBase64Encoded']) && is_string($prediction['bytesBase64Encoded'])) {
        $output['image_data_uri'] = 'data:image/png;base64,' . $prediction['bytesBase64Encoded'];
      }

      if ($output['image_url'] === NULL && !empty($prediction['imageUri']) && is_string($prediction['imageUri'])) {
        $output['image_url'] = $prediction['imageUri'];
      }

      if ($output['text'] === NULL && !empty($prediction['text']) && is_string($prediction['text'])) {
        $output['text'] = $prediction['text'];
      }
    }

    return $output;
  }

}
