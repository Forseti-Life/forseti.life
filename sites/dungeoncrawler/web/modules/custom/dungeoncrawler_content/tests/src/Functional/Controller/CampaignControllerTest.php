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

}
