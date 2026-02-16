<?php

/**
 * @file
 * Custom PHPUnit bootstrap for dungeoncrawler_tester module.
 *
 * This bootstrap file ensures that PHPUnit tests can locate the correct
 * Composer autoloader and sets DRUPAL_ROOT appropriately for a Composer-based
 * Drupal installation where the web root is in a subdirectory.
 */

// Define the path to Composer's autoloader.
// When running from sites/dungeoncrawler, the vendor directory is at the
// project root, not in the web directory.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
  define('PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../../../../../vendor/autoload.php');
}

// Define DRUPAL_ROOT to point to the web directory.
// This is required for functional tests to properly locate Drupal core files
// and create test site directories.
if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', dirname(__DIR__, 4));
}

// Now include Drupal's core test bootstrap which will handle the rest of the
// initialization.
require_once DRUPAL_ROOT . '/core/tests/bootstrap.php';
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

// Ensure the simpletest directory exists and has proper permissions.
// 0777 is required here because:
// - Drupal's test runner creates subdirectories dynamically with random IDs
// - The web server process needs full access to create/modify test sites
// - This directory only contains temporary test data, not production code
// - The directory is cleaned up after tests complete
$simpletest_dir = __DIR__ . '/../../../../sites/simpletest';
if (!file_exists($simpletest_dir)) {
  mkdir($simpletest_dir, 0777, TRUE);
}
elseif (is_dir($simpletest_dir)) {
  chmod($simpletest_dir, 0777);
}

// Load Drupal's standard test bootstrap.
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
