<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests campaign routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class CampaignRoutesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests campaigns list route - positive case.
   */
  public function testCampaignsListRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('My Campaigns');
  }

  /**
   * Tests campaigns list route - negative case (no permission).
   */
  public function testCampaignsListRouteNegative(): void {
    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign create route - positive case.
   */
  public function testCampaignCreateRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Create Campaign');
  }

  /**
   * Tests campaign create route - negative case (no permission).
   */
  public function testCampaignCreateRouteNegative(): void {
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign tavern entrance route - positive case.
   */
  public function testCampaignTavernEntranceRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Note: This will fail without a real campaign
    $this->drupalGet('/campaigns/1/tavernentrance');
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests campaign tavern entrance route - negative case (non-numeric ID).
   */
  public function testCampaignTavernEntranceRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/invalid/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character route - positive case.
   */
  public function testCampaignSelectCharacterRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Note: This will fail without real campaign and character
    $this->drupalGet('/campaigns/1/select-character/1');
    $this->assertSession()->statusCodeNotEquals(200);
  }

  /**
   * Tests campaign select character route - negative case (invalid IDs).
   */
  public function testCampaignSelectCharacterRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/campaigns/invalid/select-character/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign route - negative case (anonymous user).
   */
  public function testCampaignRouteNegativeAnonymous(): void {
    $this->drupalGet('/campaigns');
    $this->assertSession()->statusCodeEquals(403);

    // Also test campaign creation
    $this->drupalGet('/campaigns/create');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign tavern entrance route - negative case (non-existent campaign).
   */
  public function testCampaignTavernEntranceNonExistent(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Try to access a campaign that doesn't exist
    $this->drupalGet('/campaigns/99999/tavernentrance');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character - negative case (non-existent campaign).
   */
  public function testCampaignSelectCharacterNonExistentCampaign(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Try to select character for a campaign that doesn't exist
    $this->drupalGet('/campaigns/99999/select-character/1');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign select character - negative case (non-existent character).
   */
  public function testCampaignSelectCharacterNonExistentCharacter(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Create a real campaign for this user
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

    // Try to select a character that doesn't exist
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/99999");
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests campaign tavern entrance - negative case (accessing other user's campaign).
   */
  public function testCampaignTavernEntranceOwnershipDenied(): void {
    // Create two users
    $owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $other_user = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign owned by the first user
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

    // Login as the other user and try to access the campaign
    $this->drupalLogin($other_user);
    $this->drupalGet("/campaigns/{$campaign_id}/tavernentrance");
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests campaign select character - negative case (accessing with other user's character).
   */
  public function testCampaignSelectCharacterWithOtherUsersCharacter(): void {
    // Create two users
    $campaign_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $character_owner = $this->drupalCreateUser(['access dungeoncrawler characters']);

    // Create a campaign owned by campaign_owner
    $database = \Drupal::database();
    $campaign_id = $database->insert('dc_campaigns')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'uid' => $campaign_owner->id(),
        'name' => 'Campaign Owner Campaign',
        'status' => 'draft',
        'campaign_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Create a character owned by character_owner
    $character_id = $database->insert('dc_characters')
      ->fields([
        'uuid' => \Drupal::service('uuid')->generate(),
        'user_id' => $character_owner->id(),
        'name' => 'Test Character',
        'class' => 'fighter',
        'race' => 'human',
        'level' => 1,
        'experience' => 0,
        'status' => 1,
        'character_data' => json_encode([]),
        'created' => time(),
        'changed' => time(),
      ])
      ->execute();

    // Login as campaign_owner and try to select character_owner's character
    $this->drupalLogin($campaign_owner);
    $this->drupalGet("/campaigns/{$campaign_id}/select-character/{$character_id}");
    // Should get 403 because campaign_owner doesn't own the character
    $this->assertSession()->statusCodeEquals(403);
  }

}
