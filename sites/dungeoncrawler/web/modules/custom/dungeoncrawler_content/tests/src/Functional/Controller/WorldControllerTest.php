<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests WorldController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class WorldControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests world page display - positive case.
   */
  public function testWorldPageDisplayPositive(): void {
    $this->drupalGet('/world');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The Living Dungeon');
  }

  /**
   * Tests world page public access - negative case (permission check).
   */
  public function testWorldPagePublicAccessNegative(): void {
    // Negative test: page should NOT require authentication
    $this->drupalGet('/world');
    $this->assertSession()->statusCodeNotEquals(403);
    $this->assertSession()->statusCodeEquals(200);
  }

}
