<?php

/**
 * @file
 * Custom bootstrap for Dungeon Crawler tests.
 *
 * This file runs before the standard Drupal test bootstrap to ensure
 * the test environment is properly set up.
 */

// Ensure the simpletest directory exists and is writable.
// This is required for Drupal's BrowserTestBase which creates temporary
// test site directories under sites/simpletest/.
$simpletest_dir = __DIR__ . '/../../../../sites/simpletest';
if (!is_dir($simpletest_dir)) {
  if (!mkdir($simpletest_dir, 0775, TRUE)) {
    throw new \RuntimeException("Failed to create simpletest directory: $simpletest_dir");
  }
}
// Ensure the directory is writable.
if (!chmod($simpletest_dir, 0775)) {
  throw new \RuntimeException("Failed to set permissions on simpletest directory: $simpletest_dir");
}

// Include the standard Drupal test bootstrap
require __DIR__ . '/../../../../core/tests/bootstrap.php';
