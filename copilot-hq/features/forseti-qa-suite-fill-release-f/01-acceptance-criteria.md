# Acceptance Criteria: forseti-qa-suite-fill-release-f

- Feature: forseti-qa-suite-fill-release-f
- Module: qa_suites
- Author: [ba-forseti to complete]
- Date: [TBD]

## Summary

Populate executable `test_cases` for 16 empty suite shells covering the 5 release-f shipped features: application-status-dashboard, google-jobs-ux, profile-completeness, resume-tailoring-display, ai-conversation-user-chat.

## Acceptance criteria (stub — ba-forseti to expand)

### AC-1: application-status-dashboard suites filled
- `forseti-jobhunter-application-status-dashboard-static`: ≥2 test_cases (static PHP/YAML checks)
- `forseti-jobhunter-application-status-dashboard-functional`: ≥2 test_cases (route/response checks)
- `forseti-jobhunter-application-status-dashboard-regression`: ≥1 test_case (regression guard)

### AC-2: google-jobs-ux suites filled
- `forseti-jobhunter-google-jobs-ux-static`: ≥2 test_cases
- `forseti-jobhunter-google-jobs-ux-functional`: ≥2 test_cases
- `forseti-jobhunter-google-jobs-ux-regression`: ≥1 test_case

### AC-3: profile-completeness suites filled
- `forseti-jobhunter-profile-completeness-static`: ≥2 test_cases
- `forseti-jobhunter-profile-completeness-functional`: ≥2 test_cases
- `forseti-jobhunter-profile-completeness-regression`: ≥1 test_case

### AC-4: resume-tailoring-display suites filled
- `forseti-jobhunter-resume-tailoring-display-static`: ≥2 test_cases
- `forseti-jobhunter-resume-tailoring-display-functional`: ≥2 test_cases
- `forseti-jobhunter-resume-tailoring-display-regression`: ≥1 test_case

### AC-5: ai-conversation-user-chat suites filled
- `forseti-ai-conversation-user-chat-static`: ≥2 test_cases
- `forseti-ai-conversation-user-chat-acl`: ≥2 test_cases (anon=403, auth=200)
- `forseti-ai-conversation-user-chat-csrf-post`: ≥2 test_cases (with-token=200, without-token=403)
- `forseti-ai-conversation-user-chat-regression`: ≥1 test_case

### AC-6: All test_cases are executable bash commands with deterministic PASS/FAIL exit codes

### AC-7: `python3 scripts/qa-suite-validate.py` passes after changes

## Verification

- Run each `test_case.command` manually; confirm exit 0 = PASS
- `python3 scripts/qa-suite-validate.py` exits clean
