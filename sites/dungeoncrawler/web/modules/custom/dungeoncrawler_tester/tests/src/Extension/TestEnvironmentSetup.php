<?php

namespace Drupal\Tests\dungeoncrawler_tester\Extension;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension to set up the test environment.
 *
 * This extension runs before any tests and ensures that necessary
 * directories for Drupal's BrowserTestBase exist and are writable.
 *
 * Security Note: This extension uses 0777 permissions for test directories
 * (/tmp and sites/simpletest) to ensure compatibility across different CI
 * environments where the test runner and web server may run as different users.
 * These are temporary test directories that are:
 * - Created only during test execution
 * - Cleaned up after tests complete
 * - Never contain production data
 * - Located in test-specific paths that are gitignored
 *
 * For production deployments, use more restrictive permissions (0755 or 0775)
 * with proper user/group ownership.
 */
final class TestEnvironmentSetup implements Extension {

  /**
   * {@inheritdoc}
   */
  public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void {
    // Create temporary directories for simpletest
    // Uses 0777 for test directories to ensure CI compatibility across different
    // user contexts (test runner, web server, etc.)
    $tmpDir = '/tmp/dungeoncrawler-simpletest';
    $browserOutputDir = $tmpDir . '/browser_output';
    
    if (!is_dir($tmpDir)) {
      mkdir($tmpDir, 0777, TRUE);
    }
    else {
      chmod($tmpDir, 0777);
    }
    
    if (!is_dir($browserOutputDir)) {
      mkdir($browserOutputDir, 0777, TRUE);
    }
    else {
      chmod($browserOutputDir, 0777);
    }

    // Ensure simpletest directory in web root exists and is writable
    // Note: This path is relative to where phpunit is run from (sites/dungeoncrawler)
    // Uses 0777 to match CI test environment expectations
    $simpletestDir = 'web/sites/simpletest';
    if (!is_dir($simpletestDir)) {
      mkdir($simpletestDir, 0777, TRUE);
    }
    else {
      chmod($simpletestDir, 0777);
    }
    // Ensure the directory has full write permissions for test subdirectories
    chmod($simpletestDir, 0777);

    // Ensure default site files directory exists
    // Uses 0775 as this may persist beyond test execution
    $defaultFilesDir = 'web/sites/default/files';
    if (!is_dir($defaultFilesDir)) {
      mkdir($defaultFilesDir, 0775, TRUE);
    }
  }

}
