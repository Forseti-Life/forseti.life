# Dungeon Crawler Root-Level Tests

This directory contains tests that are run directly from the `sites/dungeoncrawler` root, outside of the main testing module structure.

## Setup

Before running tests for the first time, run the setup script to ensure the test environment is properly configured:

```bash
cd sites/dungeoncrawler
./tests/setup.sh
```

This script will:
- Create the `web/sites/simpletest/` directory with proper permissions
- Install composer dependencies if needed

## Purpose

These tests are primarily used for automated test runs that reference test files directly by path, rather than through PHPUnit test suites.

## Test Files

### TheTestPageTest.php

Tests the Dungeon Crawler testing dashboard functionality:
- Verifies that the testing dashboard page loads successfully for authorized users
- Ensures that unauthorized users receive appropriate access denied responses

## Running Tests

From the `sites/dungeoncrawler` directory:

```bash
# Run the specific test file
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml tests/src/Functional/TheTestPageTest.php
```

## Note

The main test suite is located in `web/modules/custom/dungeoncrawler_tester/tests/`. This root-level tests directory is supplementary and contains tests that are referenced by specific automated testing workflows.
