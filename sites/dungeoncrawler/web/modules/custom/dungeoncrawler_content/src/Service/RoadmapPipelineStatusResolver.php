<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Site\Settings;

/**
 * Resolves roadmap requirement status from feature pipeline metadata.
 */
class RoadmapPipelineStatusResolver {

  /**
   * Maps feature pipeline statuses to roadmap display statuses.
   */
  private const PIPELINE_TO_ROADMAP = [
    'pre-triage' => 'pending',
    'planned' => 'pending',
    'pending' => 'pending',
    'ready' => 'pending',
    'deferred' => 'pending',
    'in_progress' => 'in_progress',
    'done' => 'implemented',
    'shipped' => 'implemented',
  ];

  /**
   * Absolute path to the HQ features directory.
   */
  private string $featuresPath;

  /**
   * Request-local cache of parsed feature statuses.
   *
   * @var array<string, string|null>
   */
  private array $statusCache = [];

  /**
   * Constructs the resolver.
   */
  public function __construct(?string $features_path = NULL) {
    $this->featuresPath = rtrim(
      $features_path ?: Settings::get('dungeoncrawler_pipeline_features_path', '/home/ubuntu/forseti.life/copilot-hq/features'),
      DIRECTORY_SEPARATOR
    );
  }

  /**
   * Resolves the roadmap status for a requirement.
   */
  public function resolveRoadmapStatus(?string $feature_id, string $fallback_status): string {
    if (empty($feature_id)) {
      return $fallback_status;
    }

    $pipeline_status = $this->getPipelineStatus($feature_id);
    if ($pipeline_status === NULL) {
      return $fallback_status;
    }

    return self::PIPELINE_TO_ROADMAP[$pipeline_status] ?? $fallback_status;
  }

  /**
   * Returns the raw pipeline status for a feature, if available.
   */
  public function getPipelineStatus(string $feature_id): ?string {
    if (array_key_exists($feature_id, $this->statusCache)) {
      return $this->statusCache[$feature_id];
    }

    $feature_path = $this->featuresPath . DIRECTORY_SEPARATOR . $feature_id . DIRECTORY_SEPARATOR . 'feature.md';
    if (!is_readable($feature_path)) {
      $this->statusCache[$feature_id] = NULL;
      return NULL;
    }

    $contents = file_get_contents($feature_path);
    if ($contents === FALSE || !preg_match('/^- Status:\s*(.+)$/m', $contents, $matches)) {
      $this->statusCache[$feature_id] = NULL;
      return NULL;
    }

    $status = mb_strtolower(trim($matches[1]));
    $this->statusCache[$feature_id] = $status;
    return $status;
  }

}
