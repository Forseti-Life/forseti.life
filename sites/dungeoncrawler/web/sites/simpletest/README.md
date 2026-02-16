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
