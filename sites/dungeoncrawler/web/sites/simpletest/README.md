# Simpletest Directory

This directory is used by Drupal's PHPUnit functional tests to create temporary test sites.

## Setup

This directory must be writable by the web server and/or all users running tests.

**Before running tests, execute the setup script:**
```bash
cd sites/dungeoncrawler
./setup-tests.sh
```

The setup script will:
- Ensure this directory has proper write permissions (777)
- Create `/tmp/dungeoncrawler-simpletest` for test file storage
- Clean up any leftover test site directories

## Manual Setup

If you need to set permissions manually:
```bash
chmod 777 sites/dungeoncrawler/web/sites/simpletest
mkdir -p /tmp/dungeoncrawler-simpletest/browser_output
chmod -R 777 /tmp/dungeoncrawler-simpletest
```

## Cleanup

Temporary test site directories are automatically created and should be cleaned up after test runs. If you see leftover directories (numbered subdirectories), you can safely delete them:

```bash
rm -rf sites/dungeoncrawler/web/sites/simpletest/*
```

## Notes

- Test site directories are created with random numeric names (e.g., `42837505`)
- Each test run creates its own isolated site directory
- These directories should not be committed to version control (see `.gitignore`)
- File storage for tests is configured separately in `phpunit.xml` to use `/tmp/dungeoncrawler-simpletest`
# Drupal Test Sites Directory

This directory is used by Drupal's functional testing framework (PHPUnit BrowserTestBase) to create temporary test sites.

## Purpose

When running functional tests, Drupal:
1. Creates a new subdirectory with a random name (e.g., `sites/simpletest/12345678/`)
2. Installs a fresh Drupal instance in that directory for testing
3. Runs the tests against that isolated test site
4. Cleans up the test site after tests complete

## Permissions

This directory must be writable by the user/process running the tests. Recommended permissions:
- Directory: `chmod 775` or `chmod 777`
- Owner: The user running PHPUnit tests

## What Should Be in This Directory

- **Nothing permanent** - All subdirectories are temporary test sites
- The `.gitignore` file ensures test sites are not committed to version control

## Troubleshooting

If tests fail with "Failed to open 'sites/simpletest/*/settings.php'" errors:
1. Ensure this directory exists and is writable
2. Ensure no test site subdirectories are committed to git
3. Check that your user has write permissions to this directory
4. Try manually creating the directory with: `mkdir -p web/sites/simpletest && chmod 775 web/sites/simpletest`

## References

- [Drupal Testing Guide](https://www.drupal.org/docs/testing)
- [PHPUnit in Drupal](https://www.drupal.org/docs/automated-testing/phpunit-in-drupal)
