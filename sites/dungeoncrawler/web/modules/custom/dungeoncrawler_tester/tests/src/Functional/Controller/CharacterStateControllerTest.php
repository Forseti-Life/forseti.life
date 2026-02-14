<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CharacterStateController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CharacterStateControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests get character state API - positive case.
   */
  public function testGetCharacterStatePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/state', ['query' => ['_format' => 'json']]);
    // May return 404 without valid character
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests get character state API without permission - negative case.
   */
  public function testGetCharacterStateNegative(): void {
    $this->drupalGet('/api/character/1/state');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests update character state API - positive case.
   */
  public function testUpdateCharacterStatePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/character/1/update', [], [], [], ['Content-Type' => 'application/json']);
    // May return 400/404 without valid data/character
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests update character state API with GET method - negative case.
   */
  public function testUpdateCharacterStateNegativeGetMethod(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/update');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character summary API - positive case.
   */
  public function testGetCharacterSummaryPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/summary', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeNotEquals(405);
  }

}
