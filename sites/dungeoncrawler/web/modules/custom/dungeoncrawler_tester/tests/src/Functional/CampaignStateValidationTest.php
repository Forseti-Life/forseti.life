<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests campaign state schema validation.
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class CampaignStateValidationTest extends BrowserTestBase {

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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Test Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $user->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Test Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $user->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

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
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Test Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $user->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Send invalid JSON.
    $result = $this->requestRaw('POST', "/api/campaign/{$campaign_id}/state", '{invalid json}');
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Invalid JSON', $result['error']);
  }

  /**
   * Test missing state payload returns 400.
   */
  public function testMissingStatePayload() {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a campaign.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Test Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $user->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Payload without state field.
    $invalid_payload = [
      'expectedVersion' => 1,
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $invalid_payload);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Missing state payload', $result['error']);
  }

  /**
   * Issue a JSON request with the given method and payload array.
   */
  private function requestJson(string $method, string $path, array $payload): array {
    return $this->requestRaw($method, $path, json_encode($payload));
  }

  /**
   * Issue a JSON request with raw body content.
   */
  private function requestRaw(string $method, string $path, string $body): array {
    $this->getSession()->getDriver()->getClient()->request(
      $method,
      $this->buildUrl($path),
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      $body
    );

    $content = $this->getSession()->getPage()->getContent();
    return json_decode($content, TRUE) ?? [];
  }

}
