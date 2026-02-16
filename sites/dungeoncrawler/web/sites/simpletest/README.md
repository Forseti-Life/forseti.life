# Drupal Test Sites Directory

This directory is used by Drupal functional tests (`BrowserTestBase`) to create temporary test sites.

## Purpose

During functional test runs, Drupal creates short-lived subdirectories under `sites/simpletest/` for isolated installs.

## Setup

Before running tests, use the project setup helper:

```bash
cd sites/dungeoncrawler
./setup-tests.sh
```

If you need a manual fallback:

```bash
mkdir -p sites/dungeoncrawler/web/sites/simpletest
chmod 775 sites/dungeoncrawler/web/sites/simpletest
mkdir -p /tmp/dungeoncrawler-simpletest/browser_output
chmod -R 775 /tmp/dungeoncrawler-simpletest
```

## Cleanup

Temporary test site directories can be removed safely after runs:

```bash
rm -rf sites/dungeoncrawler/web/sites/simpletest/*
```

## Notes

- Do not commit generated test-site subdirectories.
- `.gitignore` and `.gitkeep` keep this directory tracked but clean.
