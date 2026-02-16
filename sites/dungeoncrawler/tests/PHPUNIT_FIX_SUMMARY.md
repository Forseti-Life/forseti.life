# PHPUnit Test Failure Fix - Summary

## Issue
PHPUnit tests in the `dungeoncrawler_tester` module were failing in CI (ci-gate stage) with the following error:

```
Exception: Failed to open 'sites/simpletest/42837505/settings.php'. Verify the file permissions.
```

**Test Case**: `Drupal\Tests\dungeoncrawler_tester\Functional\CampaignStateValidationTest::testInvalidJson`

**Command**: `cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage`

## Root Cause Analysis

The failure occurred because:

1. **Missing Temporary Directories**: The `phpunit.xml` configuration references `/tmp/dungeoncrawler-simpletest` for test file storage, but this directory did not exist
2. **Permission Issues**: Even when the `web/sites/simpletest` directory existed, it lacked the necessary write permissions for Drupal's test framework to create test site subdirectories

Drupal's `BrowserTestBase` creates temporary test sites under `web/sites/simpletest/{test_id}/` for each test run. When these directories cannot be created or accessed, all functional tests fail.

## Solution Implemented

### 1. PHPUnit Extension (Automatic Setup)
**File**: `web/modules/custom/dungeoncrawler_tester/tests/src/Extension/TestEnvironmentSetup.php`

Created a PHPUnit extension that automatically runs before any tests to:
- Create `/tmp/dungeoncrawler-simpletest` and `/tmp/dungeoncrawler-simpletest/browser_output` directories
- Ensure `web/sites/simpletest` exists with write permissions (775)
- Ensure `web/sites/default/files` exists with proper permissions (775)

**Why this approach?**
- Automatic: Runs before every test execution without manual intervention
- Reliable: Ensures environment is always ready
- Portable: Works in any CI/local environment
- Standard: Uses PHPUnit's native extension system

### 2. Manual Setup Script (Optional)
**File**: `tests/setup-test-environment.sh`

Provides a manual alternative for:
- Initial environment setup
- Debugging test issues
- Documentation reference

### 3. Documentation Updates
**File**: `tests/README.md`

Updated with:
- Setup instructions
- How to run tests
- Reference to setup script

### 4. PHPUnit Configuration
**File**: `phpunit.xml`

Added extension registration:
```xml
<extensions>
  <bootstrap class="Drupal\Tests\dungeoncrawler_tester\Extension\TestEnvironmentSetup"/>
</extensions>
```

## Security Considerations

Uses **775 permissions** instead of 777:
- **Owner**: read, write, execute (7)
- **Group**: read, write, execute (7)  
- **Others**: read, execute only (5)

This provides necessary write access for the test runner while being more secure than world-writable (777) permissions.

## Files Changed

1. `web/modules/custom/dungeoncrawler_tester/phpunit.xml` - Added extension registration
2. `web/modules/custom/dungeoncrawler_tester/tests/src/Extension/TestEnvironmentSetup.php` - New PHPUnit extension
3. `tests/setup-test-environment.sh` - New optional setup script
4. `tests/README.md` - Updated documentation

## Testing & Verification

### Why tests weren't run locally:
- Composer dependencies could not be installed in the development environment due to network restrictions
- The vendor/bin/phpunit executable is not available without dependencies

### Verification in CI:
The fix will be verified when:
1. Tests run in the CI environment where composer dependencies are pre-installed
2. The PHPUnit extension creates necessary directories automatically
3. All functional tests execute successfully with proper test site creation

### Expected Outcome:
- ✅ `/tmp/dungeoncrawler-simpletest` directory exists before tests
- ✅ `web/sites/simpletest` has write permissions
- ✅ Test sites can be created under `web/sites/simpletest/{test_id}/`
- ✅ `settings.php` files can be written to test site directories
- ✅ All functional tests pass

## Code Quality

- ✅ Code review completed - no blocking issues
- ✅ CodeQL security scan passed - 0 vulnerabilities
- ✅ Follows Drupal coding standards
- ✅ Uses secure permissions (775 instead of 777)
- ✅ Portable bash shebang (#!/usr/bin/env bash)

## How to Use

### In CI (Automatic)
No action needed. The PHPUnit extension runs automatically before every test execution.

### Locally (Manual)
```bash
cd sites/dungeoncrawler
./tests/setup-test-environment.sh
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

## Completion Status

**READY FOR TESTING** ✅

The minimal fix has been implemented and is ready to be tested in the CI environment. The solution:
- Addresses the root cause (missing directories and permissions)
- Uses PHPUnit's native extension system for automatic setup
- Follows security best practices
- Includes documentation and optional manual setup
- Passed code review and security scanning

**ready-for-testing**
