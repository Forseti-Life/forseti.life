<?php

/**
 * @file
 * Custom bootstrap for Dungeon Crawler tests.
 *
 * This bootstrap file ensures proper file permissions for test site creation
 * by setting an appropriate umask before loading Drupal's test bootstrap.
 */

// Set umask to 0 to ensure created files and directories have the most permissive
// permissions possible. This fixes "Failed to open settings.php" errors in functional
// tests where test site directories and files are created with overly restrictive
// permissions.
// 
// umask(0) results in:
// - Files created with 0666 permissions (rw-rw-rw-)
// - Directories created with 0777 permissions (rwxrwxrwx)
//
// Note: This is appropriate for CI/testing environments where test directories are
// temporary and cleaned after test runs. For production environments, use umask(0002)
// and ensure the user is in the web server group.
// See phpunit.xml line 101-106 for more details.
umask(0);

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

// Ensure the simpletest directory exists and is writable.
// This is required for Drupal's BrowserTestBase which creates temporary
// test site directories under sites/simpletest/.
// Using 0777 permissions as recommended in phpunit.xml (line 101) for CI/testing.
$simpletest_dir = __DIR__ . '/../../../../sites/simpletest';
if (!is_dir($simpletest_dir)) {
  if (!mkdir($simpletest_dir, 0777, TRUE)) {
    throw new \RuntimeException("Failed to create simpletest directory: $simpletest_dir");
  }
}
// Ensure the directory has full write permissions.
if (!chmod($simpletest_dir, 0777)) {
  throw new \RuntimeException("Failed to set permissions on simpletest directory: $simpletest_dir");
}

// Include the standard Drupal test bootstrap
require __DIR__ . '/../../../../core/tests/bootstrap.php';
