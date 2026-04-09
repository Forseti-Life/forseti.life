# Acceptance Criteria: forseti-qa-suite-fill-jobhunter-submission

- Feature: forseti-qa-suite-fill-jobhunter-submission
- Module: qa_suites
- Author: [ba-forseti to complete]
- Date: [TBD]

## Summary

Populate executable test_cases for the application-submission route ACL and unit suites.

## Acceptance criteria (stub — ba-forseti to expand)

### AC-1: route-acl suite filled
- `forseti-jobhunter-application-submission-route-acl`: ≥3 test_cases covering auth=200, anon=403 for all 7 submission POST routes

### AC-2: unit suite filled
- `forseti-jobhunter-application-submission-unit`: ≥3 test_cases covering PHPUnit WorkdayWizardService (TC-01..TC-11 range)

### AC-3: All test_cases are executable bash commands with deterministic PASS/FAIL

### AC-4: `python3 scripts/qa-suite-validate.py` passes after changes

## Verification

- Run each `test_case.command`; confirm exit 0 = PASS
- `python3 scripts/qa-suite-validate.py` exits clean
