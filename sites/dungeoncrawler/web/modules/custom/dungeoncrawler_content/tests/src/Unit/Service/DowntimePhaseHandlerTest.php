<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\DowntimePhaseHandler;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\CraftingService;

/**
 * Tests for DowntimePhaseHandler service.
 *
 * Covers: earn_income, getAvailableActions, long_rest, retrain, advance_day.
 *
 * @group dungeoncrawler_content
 * @group downtime
 * @group pf2e-rules
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\DowntimePhaseHandler
 */
class DowntimePhaseHandlerTest extends UnitTestCase {

  /**
   * @var \Drupal\dungeoncrawler_content\Service\DowntimePhaseHandler
   */
  protected DowntimePhaseHandler $handler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $db     = $this->createMock(Connection::class);
    $logger = $this->createMock(LoggerInterface::class);
    $lf     = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($logger);
    $css    = $this->createMock(CharacterStateService::class);
    $craft  = $this->createMock(CraftingService::class);

    $this->handler = new DowntimePhaseHandler($db, $lf, $css, $craft);
  }

  // ---------------------------------------------------------------------------
  // getAvailableActions
  // ---------------------------------------------------------------------------

  /**
   * Without active retrain, retrain is available; advance_day is not.
   */
  public function testGetAvailableActionsDefaultIncludesEarnIncomeAndRetrain(): void {
    $game_state = ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
    $actions    = $this->handler->getAvailableActions($game_state, []);

    $this->assertContains('earn_income', $actions);
    $this->assertContains('craft', $actions);
    $this->assertContains('long_rest', $actions);
    $this->assertContains('downtime_rest', $actions);
    $this->assertContains('retrain', $actions);
    $this->assertContains('return_to_exploration', $actions);
    $this->assertNotContains('advance_day', $actions);
  }

  /**
   * With active retrain, advance_day is available; retrain is not.
   */
  public function testGetAvailableActionsWithActiveRetrain(): void {
    $game_state = [
      'phase'    => 'downtime',
      'downtime' => ['days_elapsed' => 1, 'retraining' => ['type' => 'feat', 'days_remaining' => 5]],
    ];
    $actions = $this->handler->getAvailableActions($game_state, []);

    $this->assertContains('advance_day', $actions);
    $this->assertNotContains('retrain', $actions);
  }

  // ---------------------------------------------------------------------------
  // earn_income via processIntent
  // ---------------------------------------------------------------------------

  /**
   * Helper: build a minimal game state for earn_income tests.
   */
  private function makeGameState(): array {
    return ['phase' => 'downtime', 'downtime' => ['days_elapsed' => 0]];
  }

  /**
   * earn_income success (trained, task level 3) awards correct copper.
   *
   * CRB Table 4-2: Trained success at level 3 = 50 cp/day.
   */
  public function testEarnIncomeSuccessAwardsCp(): void {
    // addCurrency calls DB — mock a character record.
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf     = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css    = $this->createMock(CharacterStateService::class);
    $craft  = $this->createMock(CraftingService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,   // Trained.
        'task_level'       => 3,   // DC 18.
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertTrue($response['success']);
    $this->assertSame(50, $response['result']['earned_cp']); // Trained success level 3 = 50 cp.
    $this->assertSame(3, $response['result']['task_level']);
    $this->assertSame(18, $response['result']['task_dc']);
  }

  /**
   * earn_income critical success earns level+1 income.
   *
   * Trained critical success at level 3 → income for level 4 = 70 cp.
   */
  public function testEarnIncomeCriticalSuccessUsesNextLevel(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 3,
        'degree'           => 'critical_success',
        'days'             => 1,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertTrue($response['success']);
    $this->assertSame(70, $response['result']['earned_cp']); // Trained success level 4 = 70 cp.
  }

  /**
   * earn_income failure earns reduced (failure) income.
   *
   * Failure at task level 3 = 8 cp.
   */
  public function testEarnIncomeFailureEarnsFailureAmount(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 3,
        'degree'           => 'failure',
        'days'             => 1,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertTrue($response['success']);
    $this->assertSame(8, $response['result']['earned_cp']); // Failure level 3 = 8 cp.
  }

  /**
   * Critical failure earns nothing and sets 7-day cooldown.
   */
  public function testEarnIncomeCriticalFailureSetsSevenDayCooldown(): void {
    $handler    = $this->handler; // Uses no-op DB mock; actor is NULL so no addCurrency call.
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => NULL,
      'params' => [
        'skill'            => 'performance',
        'proficiency_rank' => 1,
        'task_level'       => 2,
        'degree'           => 'critical_failure',
        'days'             => 1,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertTrue($response['success']);
    $this->assertSame(0, $response['result']['earned_cp']);
    $this->assertSame(7, $game_state['downtime']['earn_income_cooldown_performance']);
  }

  /**
   * earn_income is blocked by an active critical failure cooldown.
   */
  public function testEarnIncomeBlockedByCooldown(): void {
    $handler    = $this->handler;
    $game_state = [
      'phase'    => 'downtime',
      'downtime' => [
        'days_elapsed'                       => 3,
        'earn_income_cooldown_performance'   => 5,
      ],
    ];
    $intent = [
      'type'   => 'earn_income',
      'actor'  => NULL,
      'params' => [
        'skill'            => 'performance',
        'proficiency_rank' => 1,
        'task_level'       => 2,
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertFalse($response['success']);
    $this->assertSame('critical_failure_cooldown', $response['result']['error']);
  }

  /**
   * Rank insufficient for task level returns error.
   *
   * Untrained (rank 0) cannot access task level 3 Expert column.
   * Specifically: untrained CAN access level 3 (untrained column is not NULL),
   * but a rank that has NULL for that level cannot.
   * Legendary (rank 4) has NULL for task levels 0–14.
   */
  public function testEarnIncomeRankInsufficientReturnsError(): void {
    $handler    = $this->handler;
    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => NULL,
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 4,   // Legendary — NULL for task level 3.
        'task_level'       => 3,
        'degree'           => 'success',
        'days'             => 1,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertFalse($response['success']);
    $this->assertSame('rank_insufficient', $response['result']['error']);
  }

  /**
   * Multiple days multiplies income.
   *
   * Trained success level 5 = 90 cp/day × 3 days = 270 cp.
   */
  public function testEarnIncomeMultipleDaysMultipliesIncome(): void {
    $char_data = json_encode(['currency' => ['pp' => 0, 'gp' => 0, 'sp' => 0, 'cp' => 0]]);
    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchAssoc')->willReturn(['character_data' => $char_data]);

    $select = $this->createMock(\Drupal\Core\Database\Query\Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($stmt);

    $update = $this->createMock(\Drupal\Core\Database\Query\Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $update->method('execute')->willReturn(1);

    $db = $this->createMock(Connection::class);
    $db->method('select')->willReturn($select);
    $db->method('update')->willReturn($update);

    $lf    = $this->createMock(LoggerChannelFactoryInterface::class);
    $lf->method('get')->willReturn($this->createMock(LoggerInterface::class));
    $css   = $this->createMock(CharacterStateService::class);
    $craft = $this->createMock(CraftingService::class);

    $handler = new DowntimePhaseHandler($db, $lf, $css, $craft);

    $game_state = $this->makeGameState();
    $intent = [
      'type'   => 'earn_income',
      'actor'  => 'char-001',
      'params' => [
        'skill'            => 'crafting',
        'proficiency_rank' => 1,
        'task_level'       => 5,
        'degree'           => 'success',
        'days'             => 3,
      ],
    ];

    $response = $handler->processIntent($intent, $game_state, [], 42);

    $this->assertTrue($response['success']);
    $this->assertSame(270, $response['result']['earned_cp']); // 90 × 3.
    $this->assertSame(3, $response['result']['days_elapsed']);
  }

}
