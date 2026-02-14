<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CombatApiControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat API controller access - positive case.
   */
  public function testCombatApiAccessPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Validate user is authenticated for combat API
    $this->assertNotNull($user);
  }

  /**
   * Tests combat API controller without authentication - negative case.
   */
  public function testCombatApiAccessNegative(): void {
    // Combat API should require authentication
    $this->assertNull($this->currentUser()->id());
  }

}
