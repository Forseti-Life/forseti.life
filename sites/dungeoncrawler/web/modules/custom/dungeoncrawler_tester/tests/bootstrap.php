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
