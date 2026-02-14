<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests campaign state API access control.
 *
 * @group dungeoncrawler_content
 * @group api
 */
#[RunTestsInSeparateProcesses]
class CampaignStateAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * Test campaign owner can access their campaign state.
   */
  public function testCampaignOwnerAccess() {
    // Create a user with dungeoncrawler permissions.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($owner);

    // Create a campaign owned by this user.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Test Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $owner->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Test GET /api/campaign/{id}/state - should succeed.
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($response['success']);
    $this->assertEquals($campaign_id, $response['data']['campaignId']);

    // Test POST /api/campaign/{id}/state - should succeed.
    $state_payload = [
      'expectedVersion' => 1,
      'state' => [
        'created_by' => $owner->id(),
        'started' => TRUE,
        'progress' => [
          ['type' => 'test_event', 'timestamp' => time()],
        ],
      ],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $state_payload);
    $this->assertTrue($result['success']);
    $this->assertEquals(2, $result['version']);
  }

  /**
   * Test non-owner gets 403 forbidden.
   */
  public function testNonOwnerDenied() {
    // Create owner and another user.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign owned by owner.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $owner->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as other_user and try to access.
    $this->drupalLogin($other_user);
    
    // Test GET - should get 403.
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(403);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertFalse($response['success']);
    $this->assertStringContainsString('Access denied', $response['error']);

    // Test POST - should get 403.
    $state_payload = [
      'expectedVersion' => 1,
      'state' => ['created_by' => $other_user->id(), 'started' => TRUE, 'progress' => []],
    ];

    $result = $this->requestJson('POST', "/api/campaign/{$campaign_id}/state", $state_payload);
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Access denied', $result['error']);
  }

  /**
   * Test admin can access any campaign.
   */
  public function testAdminAccess() {
    // Create owner and admin.
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $admin = $this->drupalCreateUser([
      'access dungeoncrawler characters',
      'administer dungeoncrawler content',
    ]);

    // Create a campaign owned by owner.
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Campaign',
        'status' => 'active',
        'campaign_data' => json_encode([
          'state' => ['created_by' => $owner->id(), 'started' => TRUE, 'progress' => []],
          'state_meta' => ['version' => 1, 'updatedAt' => date('c')],
        ]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as admin and access should succeed.
    $this->drupalLogin($admin);
    
    $this->drupalGet("/api/campaign/{$campaign_id}/state");
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertTrue($response['success']);
  }

  /**
   * Issue a JSON request with the given method and payload.
   */
  private function requestJson(string $method, string $path, ?array $payload = NULL): array {
    $body = $payload !== NULL ? json_encode($payload) : NULL;
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
