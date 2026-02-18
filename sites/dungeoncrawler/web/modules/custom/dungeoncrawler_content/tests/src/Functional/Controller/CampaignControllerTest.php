<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CampaignController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CampaignControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests campaign list display - positive case.
   */
  public function testCampaignListDisplayPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Campaigns');
  }

  /**
   * Tests campaign list access control - negative case (no permission).
   */
  public function testCampaignListAccessControlNegative(): void {
    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign creation page - positive case.
   */
  public function testCampaignCreationPagePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Campaign');
  }

  /**
   * Tests campaign creation access - negative case (anonymous user).
   */
  public function testCampaignCreationAccessNegative(): void {
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests tavern entrance - negative case (non-existent campaign).
   */
  public function testTavernEntranceNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/99999/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests tavern entrance - negative case (other user's campaign).
   */
  public function testTavernEntranceOwnershipCheck(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign for owner
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $owner->id(),
        'name' => 'Owner Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Try to access as other_user
    $this->drupalLogin($other_user);
    $this->drupalGet("/campaigns/{$campaign_id}/tavernentrance");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests select character - negative case (non-existent character).
   */
  public function testSelectCharacterNonExistentCharacter(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a real campaign
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $user->id(),
        'name' => 'Test Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Try to select non-existent character
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/99999");
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests select character - negative case (other user's character).
   */
  public function testSelectCharacterOwnershipCheck(): void {
    $campaign_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $character_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create campaign for campaign_owner
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $campaign_owner->id(),
        'name' => 'Test Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Create character for character_owner
    $character_id = $database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => \Drupal::service('uuid')->generate(),
        'uid' => $character_owner->id(),
        'name' => 'Test Character',
        'class' => 'fighter',
        'ancestry' => 'human',
        'level' => 1,
        'hp_current' => 10,
        'hp_max' => 10,
        'armor_class' => 10,
        'experience_points' => 0,
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'type' => 'pc',
        'status' => 1,
        'character_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Try to select character_owner's character as campaign_owner
    $this->drupalLogin($campaign_owner);
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/{$character_id}");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests select character - negative case (non-existent campaign).
   */
  public function testSelectCharacterNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/99999/select-character/1');
    $this->assertSession()->statusCodeEquals(404);
  }

}
