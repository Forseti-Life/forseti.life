<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RoadmapPipelineStatusResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Tests roadmap pipeline status resolution.
 *
 * @group dungeoncrawler_content
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\RoadmapPipelineStatusResolver
 */
class RoadmapPipelineStatusResolverTest extends UnitTestCase {

  /**
   * Temporary feature directory.
   */
  private string $featuresPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->featuresPath = sys_get_temp_dir() . '/dc-roadmap-pipeline-' . uniqid('', TRUE);
    mkdir($this->featuresPath, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->deleteDirectory($this->featuresPath);
    parent::tearDown();
  }

  /**
   * @covers ::resolveRoadmapStatus
   * @covers ::getPipelineStatus
   */
  public function testResolveRoadmapStatusUsesPipelineStatusWhenFeatureExists(): void {
    $this->writeFeatureStatus('dc-cr-example', 'done');
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('implemented', $resolver->resolveRoadmapStatus('dc-cr-example', 'pending'));
  }

  /**
   * @covers ::resolveRoadmapStatus
   */
  public function testResolveRoadmapStatusFallsBackToDatabaseStatus(): void {
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('in_progress', $resolver->resolveRoadmapStatus('dc-cr-missing', 'in_progress'));
    $this->assertSame('pending', $resolver->resolveRoadmapStatus(NULL, 'pending'));
  }

  /**
   * @covers ::resolveRoadmapStatus
   */
  public function testReadyAndDeferredMapToPending(): void {
    $this->writeFeatureStatus('dc-cr-ready', 'ready');
    $this->writeFeatureStatus('dc-cr-deferred', 'deferred');
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);

    $this->assertSame('pending', $resolver->resolveRoadmapStatus('dc-cr-ready', 'implemented'));
    $this->assertSame('pending', $resolver->resolveRoadmapStatus('dc-cr-deferred', 'implemented'));
  }

  /**
   * @covers ::getPipelineStatus
   * @dataProvider pathTraversalProvider
   */
  public function testGetPipelineStatusRejectsPathTraversal(string $malicious_id): void {
    $resolver = new RoadmapPipelineStatusResolver($this->featuresPath);
    $this->assertNull($resolver->getPipelineStatus($malicious_id));
  }

  /**
   * Data provider for path traversal test cases.
   */
  public static function pathTraversalProvider(): array {
    return [
      'double dot'           => ['..'],
      'double dot slash'     => ['../etc/passwd'],
      'nested traversal'     => ['foo/../bar'],
      'forward slash'        => ['foo/bar'],
      'backslash'            => ['foo\\bar'],
      'empty string'         => [''],
    ];
  }

  /**
   * Writes a minimal feature file for testing.
   */
  private function writeFeatureStatus(string $feature_id, string $status): void {
    $dir = $this->featuresPath . '/' . $feature_id;
    mkdir($dir, 0777, TRUE);
    file_put_contents($dir . '/feature.md', "# Feature\n\n- Status: {$status}\n");
  }

  /**
   * Recursively deletes a temporary directory.
   */
  private function deleteDirectory(string $path): void {
    if (!is_dir($path)) {
      return;
    }

    $items = scandir($path);
    if ($items === FALSE) {
      return;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }
      $item_path = $path . '/' . $item;
      if (is_dir($item_path)) {
        $this->deleteDirectory($item_path);
      }
      elseif (file_exists($item_path)) {
        unlink($item_path);
      }
    }

    rmdir($path);
  }

}
