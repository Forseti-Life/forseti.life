<?php

/**
 * @file
 * Custom bootstrap for Dungeon Crawler tests.
 *
 * This bootstrap file ensures proper file permissions for test site creation
 * by setting an appropriate umask before loading Drupal's test bootstrap.
 */

// Set umask to ensure created files and directories are readable/writable.
// This fixes "Failed to open settings.php" errors in functional tests where
// test site directories and files are created with overly restrictive permissions.
umask(0002);

// Ensure the simpletest directory exists and has proper permissions.
$simpletest_dir = __DIR__ . '/../../../../sites/simpletest';
if (!file_exists($simpletest_dir)) {
  mkdir($simpletest_dir, 0777, TRUE);
}
elseif (is_dir($simpletest_dir)) {
  chmod($simpletest_dir, 0777);
}

// Load Drupal's standard test bootstrap.
require __DIR__ . '/../../../../core/tests/bootstrap.php';
