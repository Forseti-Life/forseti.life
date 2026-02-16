<?php

/**
 * @file
 * Bootstrap file for PHPUnit tests in dungeoncrawler_tester module.
 *
 * This bootstrap ensures the simpletest directory has proper permissions
 * before running tests, then delegates to Drupal core's bootstrap.
 */

// Ensure the simpletest directory exists and is writable.
$simpletest_dir = __DIR__ . '/../../sites/simpletest';
if (!is_dir($simpletest_dir)) {
  mkdir($simpletest_dir, 0777, TRUE);
}
// Ensure the directory is writable.
chmod($simpletest_dir, 0777);

// Include Drupal core's bootstrap.
require __DIR__ . '/../../../core/tests/bootstrap.php';
