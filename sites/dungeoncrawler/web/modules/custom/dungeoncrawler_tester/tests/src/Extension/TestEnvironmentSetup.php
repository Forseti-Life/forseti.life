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
 */
final class TestEnvironmentSetup implements Extension {

  /**
   * {@inheritdoc}
   */
  public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void {
    // Create temporary directories for simpletest
    $tmpDir = '/tmp/dungeoncrawler-simpletest';
    $browserOutputDir = $tmpDir . '/browser_output';
    
    if (!is_dir($tmpDir)) {
      mkdir($tmpDir, 0777, TRUE);
      chmod($tmpDir, 0777);
    }
    
    if (!is_dir($browserOutputDir)) {
      mkdir($browserOutputDir, 0777, TRUE);
      chmod($browserOutputDir, 0777);
    }

    // Ensure simpletest directory in web root exists and is writable
    // Note: This path is relative to where phpunit is run from (sites/dungeoncrawler)
    $simpletestDir = 'web/sites/simpletest';
    if (!is_dir($simpletestDir)) {
      mkdir($simpletestDir, 0777, TRUE);
    }
    chmod($simpletestDir, 0777);

    // Ensure default site files directory exists
    $defaultFilesDir = 'web/sites/default/files';
    if (!is_dir($defaultFilesDir)) {
      mkdir($defaultFilesDir, 0777, TRUE);
    }
    chmod($defaultFilesDir, 0777);
  }

}
