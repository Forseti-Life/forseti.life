<?php

/**
 * @file
 * Bootstrap file for PHPUnit tests in dungeoncrawler_tester module.
 *
 * This bootstrap ensures the simpletest directory has proper permissions
 * before running tests, then delegates to Drupal core's bootstrap.
 */

// Ensure the simpletest directory exists and is writable.
$simpletest_dir = __DIR__ . '/../../../../sites/simpletest';
if (!is_dir($simpletest_dir)) {
  if (!mkdir($simpletest_dir, 0775, TRUE)) {
    throw new \RuntimeException("Failed to create simpletest directory: $simpletest_dir");
  }
}
// Ensure the directory is writable.
if (!chmod($simpletest_dir, 0775)) {
  // Log warning but don't fail - directory might already have correct permissions
  error_log("Warning: Could not set permissions on simpletest directory: $simpletest_dir");
}

// Include Drupal core's bootstrap.
require __DIR__ . '/../../../../core/tests/bootstrap.php';
