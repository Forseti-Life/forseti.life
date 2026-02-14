<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\JsonRequestTrait;
use Drupal\Tests\dungeoncrawler_content\Functional\Traits\TestDataBuilderTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests campaign state schema validation.
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class CampaignStateValidationTest extends BrowserTestBase {

  use JsonRequestTrait;
  use TestDataBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * Test valid campaign state payload succeeds.
   */
  public function testValidStateAccepted() {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaignWithState($user);

    // Valid state payload.
    $valid_payload = [
      'expectedVersion' => 1,
      'state' => [
        'created_by' => $user->id(),
        'started' => TRUE,
        'progress' => [
          ['type' => 'dungeon_entered', 'timestamp' => time()],
        ],
        'active_hex' => 'q0r0',
        'metadata' => ['test' => 'value'],
      ],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $valid_payload);
    $this->assertTrue($result['success'], 'Valid payload should be accepted');
    $this->assertEquals(2, $result['version']);
  }

  /**
   * Test missing required fields returns 400.
   */
  public function testMissingRequiredFields() {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaignWithState($user);

    // Invalid payload - missing required 'started' field.
    $invalid_payload = [
      'expectedVersion' => 1,
      'state' => [
        'created_by' => $user->id(),
        'progress' => [],
      ],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $invalid_payload);
    $this->assertFalse($result['success'], 'Invalid payload should be rejected');
    $this->assertStringContainsString('Invalid state payload', $result['error']);
    $this->assertNotEmpty($result['validation_errors']);
  }

  /**
   * Test invalid JSON returns 400.
   */
  public function testInvalidJson() {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaignWithState($user);

    // Send invalid JSON.
    $result = $this->requestRaw('POST', "/api/campaign/{$campaign_id}/state", '{invalid json}');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid JSON', $result['error']);
  }

  /**
   * Test missing state payload returns 400.
   */
  public function testMissingStatePayload() {
    $user = $this->createTestUser();
    $this->drupalLogin($user);

    $campaign_id = $this->createTestCampaignWithState($user);

    // Payload without state field.
    $invalid_payload = [
      'expectedVersion' => 1,
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $invalid_payload);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Missing state payload', $result['error']);
  }

}
