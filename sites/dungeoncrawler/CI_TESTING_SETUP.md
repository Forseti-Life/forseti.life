# CI/CD Testing Setup Guide

This document explains how to set up and run Dungeon Crawler tests in CI/CD environments.

## Prerequisites

1. PHP 8.3 or higher
2. Composer installed
3. MySQL/MariaDB database available
4. Write permissions for test directories

## Setup Steps for CI

### 1. Install Dependencies

```bash
cd sites/dungeoncrawler
composer install --no-interaction --prefer-dist
```

### 2. Configure Environment

Ensure these environment variables are set (already configured in `phpunit.xml`):
- `SIMPLETEST_DB`: Database connection string
- `SIMPLETEST_BASE_URL`: Base URL for functional tests
- `SIMPLETEST_FILES_DIRECTORY`: Directory for test files (default: `/tmp/dungeoncrawler-simpletest`)

### 3. Prepare Test Directories

**Run the setup script before tests:**
```bash
cd sites/dungeoncrawler
./setup-tests.sh
```

**Or manually:**
```bash
# Create and set permissions for simpletest directory
mkdir -p sites/dungeoncrawler/web/sites/simpletest
chmod 777 sites/dungeoncrawler/web/sites/simpletest

# Create temp directory for test file storage
mkdir -p /tmp/dungeoncrawler-simpletest/browser_output
chmod -R 777 /tmp/dungeoncrawler-simpletest
```

### 4. Run Tests

```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Common Issues

### Permission Errors

**Error:** `Failed to open 'sites/simpletest/*/settings.php'. Verify the file permissions.`

**Solution:** Ensure the `web/sites/simpletest` directory has 777 permissions:
```bash
chmod 777 sites/dungeoncrawler/web/sites/simpletest
```

### Database Connection Issues

**Error:** `SIMPLETEST_DB environment variable not set`

**Solution:** Check that the database connection string is correct in `phpunit.xml` or set via environment variable:
```bash
export SIMPLETEST_DB="mysql://user:pass@host:port/database"
```

### Leftover Test Sites

After test runs, clean up temporary test sites:
```bash
rm -rf sites/dungeoncrawler/web/sites/simpletest/*
```

## GitHub Actions Example

```yaml
name: Run Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, json, mysql, gd
          
      - name: Install Dependencies
        run: |
          cd sites/dungeoncrawler
          composer install --no-interaction --prefer-dist
          
      - name: Setup Test Environment
        run: |
          cd sites/dungeoncrawler
          ./setup-tests.sh
          
      - name: Run PHPUnit Tests
        run: |
          cd sites/dungeoncrawler
          ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Troubleshooting

### Tests Still Fail After Setup

1. Verify directory permissions:
   ```bash
   ls -la sites/dungeoncrawler/web/sites/simpletest
   ```
   Should show `drwxrwxrwx` (777)

2. Check that temp directory exists:
   ```bash
   ls -la /tmp/dungeoncrawler-simpletest
   ```

3. Verify phpunit.xml configuration has correct environment variables

4. Check PHP and Composer versions meet requirements

## Additional Resources

- [Drupal Testing Guide](https://www.drupal.org/docs/automated-testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Module Test README](web/modules/custom/dungeoncrawler_tester/tests/README.md)
