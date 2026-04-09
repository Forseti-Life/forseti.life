# Acceptance Criteria: forseti-qa-suite-fill-agent-tracker

- Feature: forseti-qa-suite-fill-agent-tracker
- Module: qa_suites
- Author: [ba-forseti to complete]
- Date: [TBD]

## Summary

Populate executable test_cases for 4 copilot-agent-tracker suite shells covering route ACL, API contract, happy-path, and security.

## Acceptance criteria (stub — ba-forseti to expand)

### AC-1: route-acl suite filled
- `forseti-copilot-agent-tracker-route-acl`: ≥3 test_cases covering anon=403, authenticated=403, administrator=200 for all agent-tracker routes

### AC-2: api suite filled
- `forseti-copilot-agent-tracker-api`: ≥2 test_cases verifying JSON API response format and required fields

### AC-3: happy-path suite filled
- `forseti-copilot-agent-tracker-happy-path`: ≥2 test_cases covering the primary read/write flow for agent session records

### AC-4: security suite filled
- `forseti-copilot-agent-tracker-security`: ≥2 test_cases verifying CSRF protection and input sanitization (per `org-chart/sites/forseti.life/site.instructions.md` security AC requirements)

### AC-5: All test_cases are executable bash commands with deterministic PASS/FAIL

### AC-6: `python3 scripts/qa-suite-validate.py` passes after changes

## Verification

- Run each `test_case.command`; confirm exit 0 = PASS
- `python3 scripts/qa-suite-validate.py` exits clean
