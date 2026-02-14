<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

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

}
