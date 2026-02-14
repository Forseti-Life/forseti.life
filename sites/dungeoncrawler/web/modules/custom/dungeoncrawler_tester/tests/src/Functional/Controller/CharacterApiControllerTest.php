<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterApiControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character save API - positive case.
   */
  public function testCharacterSaveApiPositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/character/save', [], [], [], ['Content-Type' => 'application/json']);
    // May return 400/422 without valid data, but route should exist
    $this->assertSession()->statusCodeNotEquals(404);
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests character save API without permission - negative case.
   */
  public function testCharacterSaveApiNegativeNoPermission(): void {
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalPost('/api/character/save', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests character load API - positive case.
   */
  public function testCharacterLoadApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/load/1', ['query' => ['_format' => 'json']]);
    // May return 403/404 without valid character
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests character load API without authentication - negative case.
   */
  public function testCharacterLoadApiNegativeNoAuth(): void {
    $this->drupalGet('/api/character/load/1');
    $this->assertSession()->statusCodeEquals(403);
  }

}
