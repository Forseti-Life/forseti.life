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
  mkdir($simpletest_dir, 0775, TRUE);
}
// Ensure the directory is writable
chmod($simpletest_dir, 0775);

// Include the standard Drupal test bootstrap
require __DIR__ . '/../../../../core/tests/bootstrap.php';
