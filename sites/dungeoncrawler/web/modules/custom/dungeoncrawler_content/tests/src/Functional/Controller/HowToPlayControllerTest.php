<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HowToPlayController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HowToPlayControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests how to play page display - positive case.
   */
  public function testHowToPlayPageDisplayPositive(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('How to Play');
  }

  /**
   * Tests how to play page public access - negative case (no auth required).
   */
  public function testHowToPlayPagePublicAccessNegative(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeNotEquals(403);
    $this->assertSession()->statusCodeEquals(200);
  }

}
