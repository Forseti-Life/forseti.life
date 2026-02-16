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
// 
// umask(0002) results in:
// - Files created with 0664 permissions (rw-rw-r--)
// - Directories created with 0775 permissions (rwxrwxr-x)
// This allows both the test runner and web server to read/write test files.
umask(0002);

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
