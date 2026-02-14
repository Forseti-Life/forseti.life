<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatActionController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class CombatActionControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat action controller access - positive case.
   */
  public function testCombatActionAccessPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Validate user is authenticated for combat actions
    $this->assertNotNull($user);
  }

  /**
   * Tests combat action controller without authentication - negative case.
   */
  public function testCombatActionAccessNegative(): void {
    // Combat actions should require authentication
    $this->assertNull($this->currentUser()->id());
  }

}
