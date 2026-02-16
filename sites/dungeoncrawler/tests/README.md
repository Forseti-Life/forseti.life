# Dungeon Crawler Root-Level Tests

This directory contains tests that are run directly from the `sites/dungeoncrawler` root, outside of the main testing module structure.

## Purpose

These tests are primarily used for automated test runs that reference test files directly by path, rather than through PHPUnit test suites.

## Test Files

### TheTestPageTest.php

Tests the Dungeon Crawler testing dashboard functionality:
- Verifies that the testing dashboard page loads successfully for authorized users
- Ensures that unauthorized users receive appropriate access denied responses

## Setup

Before running tests for the first time, you need to set up the test environment:

```bash
# From the sites/dungeoncrawler directory
./tests/setup-test-environment.sh
```

This script will:
- Create necessary temporary directories for test file storage
- Set appropriate permissions on simpletest directories
- Ensure default site directories exist

## Running Tests

From the `sites/dungeoncrawler` directory:

```bash
# Run the specific test file
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/TheTestPageTest.php

# Run all tests
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Note

The main test suite is located in `web/modules/custom/dungeoncrawler_tester/tests/`. This root-level tests directory is supplementary and contains tests that are referenced by specific automated testing workflows.
