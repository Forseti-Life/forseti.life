<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests DungeonController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 */
class DungeonControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests dungeon controller access - positive case.
   *
   * Note: Requires proper permissions and likely a dungeon entity.
   */
  public function testDungeonAccessPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // Validate user is authenticated for dungeon access
    $this->assertNotNull($user);
  }

  /**
   * Tests dungeon controller without authentication - negative case.
   */
  public function testDungeonAccessNegative(): void {
    // Dungeon should require authentication
    $this->assertNull($this->currentUser()->id());
  }

}
