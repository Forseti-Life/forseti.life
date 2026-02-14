<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests campaign state schema validation.
 *
 * @group dungeoncrawler_content
 * @group api
 */
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

    $response = $this->drupalPost(
      "/api/campaign/{$campaign_id}/state",
      json_encode($valid_payload),
      ['Content-Type' => 'application/json']
    );
    
    $result = json_decode($response, TRUE);
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

    $response = $this->drupalPost(
      "/api/campaign/{$campaign_id}/state",
      json_encode($invalid_payload),
      ['Content-Type' => 'application/json']
    );
    
    $result = json_decode($response, TRUE);
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
    $response = $this->drupalPost(
      "/api/campaign/{$campaign_id}/state",
      '{invalid json}',
      ['Content-Type' => 'application/json']
    );
    
    $result = json_decode($response, TRUE);
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

    $response = $this->drupalPost(
      "/api/campaign/{$campaign_id}/state",
      json_encode($invalid_payload),
      ['Content-Type' => 'application/json']
    );
    
    $result = json_decode($response, TRUE);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Missing state payload', $result['error']);
  }

}
