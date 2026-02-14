<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CreditsController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CreditsControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests credits page display - positive case.
   */
  public function testCreditsPageDisplayPositive(): void {
    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Credits');
  }

  /**
   * Tests credits page public access - negative case (should not require auth).
   */
  public function testCreditsPagePublicAccessNegative(): void {
    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeNotEquals(403);
    $this->assertSession()->statusCodeEquals(200);
  }

}
