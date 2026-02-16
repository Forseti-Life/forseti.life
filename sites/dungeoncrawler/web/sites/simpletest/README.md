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
