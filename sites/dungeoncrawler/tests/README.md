# Dungeon Crawler Root-Level Tests

This directory contains tests that are run directly from the `sites/dungeoncrawler` root, outside of the main testing module structure.

## Purpose

These tests are primarily used for automated test runs that reference test files directly by path, rather than through PHPUnit test suites.

## Test Files

### TheTestPageTest.php

Tests the Dungeon Crawler testing dashboard functionality:
- Verifies that the testing dashboard page loads successfully for authorized users
- Ensures that unauthorized users receive appropriate access denied responses

## Setting Up the Test Environment

Before running tests, you need to prepare the test environment to ensure proper directory permissions:

```bash
cd sites/dungeoncrawler
bash prepare-test-env.sh
```

This script:
- Creates and sets proper permissions (777) for `web/sites/simpletest/` directory
- Creates the `/tmp/dungeoncrawler-simpletest/` directory for test files
- Cleans any stale test directories from previous runs

### Why is this needed?

Drupal functional tests (BrowserTestBase) create temporary test sites in `web/sites/simpletest/`. Each test run creates a new subdirectory with its own `settings.php` file. Without proper write permissions, tests will fail with errors like:

```
Exception: Failed to open 'sites/simpletest/*/settings.php'. Verify the file permissions.
```

## Running Tests

From the `sites/dungeoncrawler` directory:

```bash
# Run the specific test file
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/TheTestPageTest.php

# Run all tests with coverage
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage
```

## Troubleshooting

If tests fail with permission errors:
1. Run `bash prepare-test-env.sh` to reset the environment
2. Ensure the web server user has write access to `web/sites/simpletest/`
3. Check that `/tmp/dungeoncrawler-simpletest/` is writable

## Note

The main test suite is located in `web/modules/custom/dungeoncrawler_tester/tests/`. This root-level tests directory is supplementary and contains tests that are referenced by specific automated testing workflows.
