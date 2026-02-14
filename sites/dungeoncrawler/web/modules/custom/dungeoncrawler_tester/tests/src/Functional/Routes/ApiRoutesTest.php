<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests API routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 * @group api
 */
class ApiRoutesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests character save API route - positive case.
   */
  public function testCharacterSaveApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    // POST request with valid data
    $this->drupalPost('/api/character/save', [], [], [], ['Content-Type' => 'application/json']);
    // May return 400/422 without valid data, but route exists
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests character save API route - negative case (GET method not allowed).
   */
  public function testCharacterSaveApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['create dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/save');
    // Should return 405 Method Not Allowed for GET
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character load API route - positive case.
   */
  public function testCharacterLoadApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    // GET request
    $this->drupalGet('/api/character/load/1', ['query' => ['_format' => 'json']]);
    // May return 403/404 without valid character
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests character load API route - negative case (non-numeric ID).
   */
  public function testCharacterLoadApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/load/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests character state API route - positive case.
   */
  public function testCharacterStateApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/state', ['query' => ['_format' => 'json']]);
    // May return 403/404 without valid character
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests character state API route - negative case (POST not allowed).
   */
  public function testCharacterStateApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/character/1/state', [], []);
    // Should return 405 for POST on GET-only route
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests character summary API route - positive case.
   */
  public function testCharacterSummaryApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/summary', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests character state update API route - positive case.
   */
  public function testCharacterStateUpdateApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/character/1/update', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests character state update API route - negative case (GET not allowed).
   */
  public function testCharacterStateUpdateApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/character/1/update');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat start API route - positive case.
   */
  public function testCombatStartApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/start', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests combat start API route - negative case (GET not allowed).
   */
  public function testCombatStartApiRouteNegative(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/start');
    $this->assertSession()->statusCodeEquals(405);
  }

  /**
   * Tests combat end turn API route - positive case.
   */
  public function testCombatEndTurnApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/end-turn', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests combat end API route - positive case.
   */
  public function testCombatEndApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/end', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests combat attack API route - positive case.
   */
  public function testCombatAttackApiRoutePositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/attack', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
  }

  /**
   * Tests API routes - negative case (no authentication).
   */
  public function testApiRoutesNegativeNoAuth(): void {
    $this->drupalGet('/api/character/load/1');
    $this->assertSession()->statusCodeEquals(403);
  }

}
