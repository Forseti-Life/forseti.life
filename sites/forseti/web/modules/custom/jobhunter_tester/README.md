# Job Hunter Tester Module

## Overview
This module provides comprehensive testing functionality for the Job Hunter module, including:
- Automated PHPUnit test execution via web interface
- Route testing and validation
- Test case implementation dashboard

## Features

### 1. Route Testing Dashboard (`/jobhunter_testing`)
Tests all Job Hunter module routes via HTTP requests to verify:
- Routes are accessible
- Permissions are configured correctly
- Pages return appropriate HTTP status codes
- Access control works as expected

### 2. Unit Tests Dashboard (`/jobhunter_testing/unit-tests`)
Interactive web interface for running PHPUnit unit tests:
- View all implemented test suites
- Run individual test files
- Run all tests at once
- View test results in real-time
- Color-coded output for pass/fail status

### 3. Implemented Test Suites

#### JobSeekerService Tests (14 tests)
**Test File**: `tests/src/Unit/Service/JobSeekerServiceTest.php`
**Coverage**: JSS-001 through JSS-006 from TEST_CASES.md

Tests include:
- Load job seeker by user ID (valid, invalid, non-existent, with errors)
- Create job seeker profile (with all fields, with defaults)
- Update job seeker profile
- Delete job seeker profile (existing, non-existent)
- User has profile check (true, false, invalid)
- Current user profile access (authenticated, anonymous)

#### UserProfileService Extended Tests (8 tests)
**Test File**: `tests/src/Unit/Service/UserProfileServiceExtendedTest.php`
**Coverage**: UPS-006 from TEST_CASES.md

Tests include:
- Profile statistics field counts
- Profile completeness percentage calculation
- Profile completeness status classification
- Profile recommendations generation
- Empty profile statistics
- Fully completed profile statistics
- Profile completeness edge cases
- Recommendation priority verification

## Usage

### Accessing the Testing Dashboard

1. **Route Testing**:
   ```
   https://your-site.com/jobhunter_testing
   ```
   - Tests all Job Hunter routes
   - Select environment (current, production, localhost)
   - View HTTP status codes and access control

2. **Unit Tests Dashboard**:
   ```
   https://your-site.com/jobhunter_testing/unit-tests
   ```
   - View implemented test suites
   - Click "Run Tests" to execute individual test file
   - Click "Run All Tests" to execute all test suites
   - View real-time test output

### Running Tests via Command Line

```bash
# Run all jobhunter_tester tests
phpunit web/modules/custom/jobhunter_tester/tests/

# Run specific test file
phpunit web/modules/custom/jobhunter_tester/tests/src/Unit/Service/JobSeekerServiceTest.php

# Run with verbose output
phpunit --verbose web/modules/custom/jobhunter_tester/tests/

# Run with code coverage (requires Xdebug)
phpunit --coverage-html coverage web/modules/custom/jobhunter_tester/tests/
```

## Test Implementation Status

### ✅ Implemented (22 tests)
- JobSeekerService: 14 tests
- UserProfileService Extended: 8 tests

### 🔄 Planned (see job_hunter/tests/TEST_CASES.md)
- ResumePdfService tests (RPS-001 to RPS-005)
- AbbVieJobScrapingService tests (AJSS-001 to AJSS-004)
- Integration tests (workflow testing)
- Functional tests (UI testing)
- Security tests
- Performance tests
- Queue worker tests
- AI service tests

## Test Organization

```
jobhunter_tester/
├── jobhunter_tester.info.yml
├── jobhunter_tester.routing.yml
├── jobhunter_tester.libraries.yml
├── README.md (this file)
├── src/
│   └── Controller/
│       └── JobHunterTesterController.php
├── js/
│   └── test-runner.js
└── tests/
    └── src/
        ├── Unit/
        │   └── Service/
        │       ├── JobSeekerServiceTest.php
        │       └── UserProfileServiceExtendedTest.php
        ├── Kernel/ (integration tests - planned)
        └── Functional/ (functional tests - planned)
```

## Development

### Adding New Tests

1. Create test class in appropriate directory:
   - `tests/src/Unit/` for unit tests
   - `tests/src/Kernel/` for integration tests
   - `tests/src/Functional/` for functional tests

2. Follow Drupal testing standards:
   - Extend appropriate base class (UnitTestCase, KernelTestBase, BrowserTestBase)
   - Use `@group` annotations
   - Follow naming convention: `{ClassName}Test.php`

3. Update controller to include new test in dashboard:
   - Edit `JobHunterTesterController::getTestCategories()`
   - Add entry with test file information

4. Reference TEST_CASES.md for test case specifications

### Test Writing Guidelines

- **Mock External Dependencies**: Use PHPUnit mocks for database, services, etc.
- **Test One Thing**: Each test method should test one specific behavior
- **Clear Assertions**: Use descriptive assertion messages
- **Follow AAA Pattern**: Arrange, Act, Assert
- **Document Test Cases**: Reference TEST_CASES.md IDs in docblocks

## Permissions

Requires: `administer site configuration`

Users with this permission can:
- Access testing dashboards
- Run tests via web interface
- View test results

## Dependencies

- Drupal Core 10 or 11
- job_hunter module
- PHPUnit (for test execution)

## Related Documentation

- [job_hunter/tests/TEST_CASES.md](../job_hunter/tests/TEST_CASES.md) - Complete test case documentation
- [job_hunter/tests/README.md](../job_hunter/tests/README.md) - Job Hunter testing guide
- [Drupal Testing Documentation](https://www.drupal.org/docs/testing)

## Troubleshooting

### Tests Not Running
- Ensure PHPUnit is installed: `which phpunit`
- Check file permissions on test files
- Verify module is enabled: `drush pm:list --status=enabled | grep jobhunter_tester`

### Database Connection Errors
- Tests use mocked database connections
- Check that service mocks are properly configured
- Ensure test extends correct base class

### JavaScript Not Working
- Clear Drupal cache: `drush cr`
- Check browser console for errors
- Verify library is attached in controller

## Support

For issues or questions:
- Review TEST_CASES.md for test specifications
- Check Drupal logs: `drush watchdog:show`
- View test output in dashboard for detailed error messages

## Version

- **Current Version**: 1.0.0
- **Status**: Active Development
- **Last Updated**: 2026-02-07
