<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests HexMapController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class HexMapControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests hexmap demo display - positive case.
   */
  public function testHexmapDemoDisplayPositive(): void {
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Hex Map Demo');
  }

  /**
   * Tests hexmap demo public access - negative case (should be public).
   */
  public function testHexmapDemoPublicAccessNegative(): void {
    // Demo should be publicly accessible
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeNotEquals(403);
    $this->assertSession()->statusCodeEquals(200);
  }

}
