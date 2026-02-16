# Fix Summary: Drupal Test Path Resolution Issue

## Issue
Tests in `Drupal\Tests\dungeoncrawler_tester\Functional\CampaignStateAccessTest` (and 156 other tests) were failing with:
```
Exception: Failed to open 'sites/simpletest/76074665/settings.php'. Verify the file permissions.
```

## Root Cause
The test command was running PHPUnit from `sites/dungeoncrawler/` directory:
```bash
cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

However, Drupal's test framework (`FunctionalTestSetupTrait::writeSettings()`) uses relative paths that are resolved relative to the **current working directory**, not DRUPAL_ROOT. This caused:
- File created at: `/path/to/sites/dungeoncrawler/web/sites/simpletest/[id]/settings.php` (correct location)
- File accessed at: `/path/to/sites/dungeoncrawler/sites/simpletest/[id]/settings.php` (wrong location - missing `web/`)
- Result: "Failed to open" error because the file doesn't exist at the expected location

## Solution
Changed the working directory from `sites/dungeoncrawler/` to `sites/dungeoncrawler/web/` (DRUPAL_ROOT). Now PHPUnit must be run as:
```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml
```

This ensures all relative paths resolve correctly relative to DRUPAL_ROOT.

## Changes Made

### 1. sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/phpunit.xml
- Updated usage instructions (lines 5-11)
- Changed bootstrap path from `../../../core/tests/bootstrap.php` to `core/tests/bootstrap.php` (line 23)
- Added clear warning about working directory requirement

### 2. sites/dungeoncrawler/web/sites/.gitignore
- Added `simpletest/*` to prevent committing test artifacts

### 3. sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/TESTING.md (new file)
- Comprehensive testing documentation
- Explains root cause and solution
- Provides usage examples
- Includes troubleshooting guide
- Documents CI/CD integration

### 4. docs/dungeoncrawler/testing/examples/test-workflow-example.yml
- Updated all phpunit commands to use correct working directory
- Added explanatory notes about the requirement

## Impact
- **All 157 failing tests should now pass** (once dependencies are installed and environment is set up)
- No changes to actual test code required
- No changes to Drupal core required
- Minimal configuration change with clear documentation

## Testing & Validation
- ✅ Code review: No issues found
- ✅ CodeQL security scan: No vulnerabilities detected
- ⏳ Functional testing: Requires CI environment with composer dependencies installed

## CI/CD Integration
Update any CI/CD workflows that run these tests to use the correct working directory:
```yaml
- name: Run Tests
  working-directory: sites/dungeoncrawler/web
  run: ../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Migration Guide for Existing Test Runs
**Old command:**
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit -c web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

**New command:**
```bash
cd sites/dungeoncrawler/web
../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Security Notes
No security issues introduced or discovered during this fix. All changes are configuration and documentation updates.

## References
- Drupal Issue: This is a known limitation of Drupal's test framework where relative paths in `FunctionalTestSetupTrait` assume the working directory is DRUPAL_ROOT
- Affected Code: `web/core/lib/Drupal/Core/Test/FunctionalTestSetupTrait.php:197-205`
- Stack Trace: `SettingsEditor.php:190` → `FunctionalTestSetupTrait.php:204` → `BrowserTestBase.php:553`
