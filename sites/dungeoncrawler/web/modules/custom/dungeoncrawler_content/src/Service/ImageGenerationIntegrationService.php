<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provider-agnostic integration layer for image generation.
 */
class ImageGenerationIntegrationService {

  /**
   * Config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Gemini provider service.
   */
  protected GeminiImageGenerationService $geminiImageService;

  /**
   * Vertex provider service.
   */
  protected VertexImageGenerationService $vertexImageService;

  /**
   * Constructs integration service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, GeminiImageGenerationService $gemini_image_service, VertexImageGenerationService $vertex_image_service) {
    $this->configFactory = $config_factory;
    $this->geminiImageService = $gemini_image_service;
    $this->vertexImageService = $vertex_image_service;
  }

  /**
   * Generates an image with selected provider.
   *
   * @param array<string, mixed> $payload
   *   Normalized payload.
   * @param string|null $provider
   *   Provider override (gemini|vertex).
   *
   * @return array<string, mixed>
   *   Provider response.
   */
  public function generateImage(array $payload, ?string $provider = NULL): array {
    $resolved_provider = $this->resolveProvider($provider);

    return match ($resolved_provider) {
      'vertex' => $this->vertexImageService->generateImage($payload),
      default => $this->geminiImageService->generateImage($payload),
    };
  }

  /**
   * Returns dashboard status for all providers.
   *
   * @return array<string, mixed>
   *   Integration status data.
   */
  public function getIntegrationStatus(): array {
    return [
      'default_provider' => $this->resolveProvider(NULL),
      'providers' => [
        'gemini' => $this->geminiImageService->getIntegrationStatus(),
        'vertex' => $this->vertexImageService->getIntegrationStatus(),
      ],
    ];
  }

  /**
   * Resolve provider from override or configuration.
   */
  private function resolveProvider(?string $provider): string {
    $normalized = strtolower(trim((string) $provider));
    if (in_array($normalized, ['gemini', 'vertex'], TRUE)) {
      return $normalized;
    }

    $configured = strtolower(trim((string) $this->configFactory->get('dungeoncrawler_content.settings')->get('generated_image_provider')));
    return in_array($configured, ['gemini', 'vertex'], TRUE) ? $configured : 'gemini';
  }

}
