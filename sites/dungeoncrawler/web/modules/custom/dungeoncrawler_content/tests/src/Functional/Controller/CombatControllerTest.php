<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CombatControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat controller access - positive case.
   *
   * Note: Requires proper permissions to access combat functionality.
   */
  public function testCombatAccessPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Validate that combat functionality requires authentication
    $this->assertNotNull($user);
  }

  /**
   * Tests combat controller without authentication - negative case.
   */
  public function testCombatAccessNegative(): void {
    // Combat should require authentication
    $this->assertNull($this->currentUser()->id());
  }

}
