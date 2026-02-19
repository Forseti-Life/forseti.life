<?php

namespace Drupal\dungeoncrawler_content\Service;

use InvalidArgumentException;

/**
 * Service for Pathfinder-compatible number and dice generation.
 */
class NumberGenerationService {

  /**
   * Pathfinder dice sizes used by this module.
   */
  public const PATHFINDER_DICE = [4, 6, 8, 10, 12, 20, 100];

  /**
   * Roll a Pathfinder die.
   *
   * Supported: d4, d6, d8, d10, d12, d20, d100.
   *
   * @param int $sides
   *   Die sides.
   *
   * @return int
   *   Roll result.
   */
  public function rollPathfinderDie(int $sides): int {
    if (!in_array($sides, self::PATHFINDER_DICE, TRUE)) {
      throw new InvalidArgumentException(sprintf('Unsupported Pathfinder die: d%d', $sides));
    }

    return random_int(1, $sides);
  }

  /**
   * Roll percentile (1-100).
   */
  public function rollPercentile(): int {
    return $this->rollPathfinderDie(100);
  }

  /**
   * Roll a random integer inside an inclusive range.
   *
   * @param int $minimum
   *   Lower bound (inclusive).
   * @param int $maximum
   *   Upper bound (inclusive).
   *
   * @return int
   *   Random integer in range.
   */
  public function rollRange(int $minimum, int $maximum): int {
    if ($minimum > $maximum) {
      throw new InvalidArgumentException('Minimum must be less than or equal to maximum.');
    }

    return random_int($minimum, $maximum);
  }

  /**
   * Alias for rollRange() to match older service expectations.
   *
   * @param int $minimum
   *   Lower bound (inclusive).
   * @param int $maximum
   *   Upper bound (inclusive).
   *
   * @return int
   *   Random integer in range.
   */
  public function randomInt(int $minimum, int $maximum): int {
    return $this->rollRange($minimum, $maximum);
  }

  /**
   * Roll one or more dice in standard notation.
   *
   * Examples: 1d20, 2d6+3, 4d8-1, 1d100.
   *
   * @param string $notation
   *   Dice notation.
   *
   * @return array
   *   Array with keys: notation, count, sides, modifier, rolls, subtotal, total.
   */
  public function rollNotation(string $notation): array {
    $notation = strtolower(trim($notation));
    $pattern = '/^(\d+)d(\d+)([+-]\d+)?$/';
    if (!preg_match($pattern, $notation, $matches)) {
      throw new InvalidArgumentException(sprintf('Invalid dice notation: %s', $notation));
    }

    $count = (int) $matches[1];
    $sides = (int) $matches[2];
    $modifier = isset($matches[3]) ? (int) $matches[3] : 0;

    if ($count < 1) {
      throw new InvalidArgumentException('Dice count must be at least 1.');
    }
    if ($count > 100) {
      throw new InvalidArgumentException('Dice count cannot exceed 100.');
    }

    $rolls = $this->rollMultiple($sides, $count);
    $subtotal = array_sum($rolls);

    return [
      'notation' => $notation,
      'count' => $count,
      'sides' => $sides,
      'modifier' => $modifier,
      'rolls' => $rolls,
      'subtotal' => $subtotal,
      'total' => $subtotal + $modifier,
    ];
  }

  /**
   * Roll multiple dice with the same number of sides.
   *
   * Supports side ranges from 1 to 100.
   *
   * @param int $sides
   *   Number of sides.
   * @param int $count
   *   Number of dice to roll.
   *
   * @return int[]
   *   Individual roll results.
   */
  public function rollMultiple(int $sides, int $count = 1): array {
    if ($sides < 1 || $sides > 100) {
      throw new InvalidArgumentException('Supported die sides range is 1-100.');
    }
    if ($count < 1) {
      throw new InvalidArgumentException('Dice count must be at least 1.');
    }

    $results = [];
    for ($index = 0; $index < $count; $index++) {
      $results[] = random_int(1, $sides);
    }

    return $results;
  }

}
