<?php

namespace Drupal\Tests\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the testing page functionality.
 *
 * @group dungeoncrawler_tester
 * @group functional
 */
class TheTestPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_tester', 'dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the testing dashboard page loads successfully.
   */
  public function testTestingDashboardPageLoads(): void {
    // Create a user with permission to access the testing dashboard.
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);

    // Visit the testing dashboard.
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Release Testing Stagegates');
  }

  /**
   * Tests that the testing dashboard requires proper permissions.
   */
  public function testTestingDashboardRequiresPermission(): void {
    // Anonymous user should not have access.
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(403);
  }

}
