<?php

namespace Drupal\Tests\dungeoncrawler_content\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests CombatEncounterApiController functionality.
 *
 * @group dungeoncrawler_content
 * @group controller
 * @group api
 */
class CombatEncounterApiControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests combat start API - positive case.
   */
  public function testCombatStartApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/start', [], [], [], ['Content-Type' => 'application/json']);
    // May return 400/422 without valid data
    $this->assertSession()->statusCodeNotEquals(404);
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat start API without authentication - negative case.
   */
  public function testCombatStartApiNegative(): void {
    $this->drupalPost('/api/combat/start', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests combat end turn API - positive case.
   */
  public function testCombatEndTurnApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/end-turn', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat end API - positive case.
   */
  public function testCombatEndApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/end', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat attack API - positive case.
   */
  public function testCombatAttackApiPositive(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalPost('/api/combat/attack', [], [], [], ['Content-Type' => 'application/json']);
    $this->assertSession()->statusCodeNotEquals(404);
    $this->assertSession()->statusCodeNotEquals(405);
  }

  /**
   * Tests combat attack API with GET method - negative case.
   */
  public function testCombatAttackApiNegativeGetMethod(): void {
    $user = $this->drupalCreateUser(['access dungeoncrawler characters']);
    $this->drupalLogin($user);

    $this->drupalGet('/api/combat/attack');
    $this->assertSession()->statusCodeEquals(405);
  }

}
