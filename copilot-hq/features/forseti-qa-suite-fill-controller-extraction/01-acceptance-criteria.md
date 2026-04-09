# Acceptance Criteria: forseti-qa-suite-fill-controller-extraction

- Feature: forseti-qa-suite-fill-controller-extraction
- Module: qa_suites
- Author: [ba-forseti to complete]
- Date: [TBD]

## Summary

Populate executable test_cases for 2 controller-extraction-phase1 suite shells: static structural checks and regression guard.

## Acceptance criteria (stub — ba-forseti to expand)

### AC-1: static suite filled
- `forseti-jobhunter-controller-extraction-phase1-static`: ≥3 test_cases covering:
  - No direct DB calls in controller files (grep check)
  - Repository class file exists
  - Repository is injected via services.yml (not instantiated directly)

### AC-2: regression suite filled
- `forseti-jobhunter-controller-extraction-phase1-regression`: ≥2 test_cases covering:
  - Key job hunter routes return expected status codes after extraction
  - No 500 errors on standard authenticated requests

### AC-3: All test_cases are executable bash commands with deterministic PASS/FAIL

### AC-4: `python3 scripts/qa-suite-validate.py` passes after changes

## Verification

- Run each `test_case.command`; confirm exit 0 = PASS
- `python3 scripts/qa-suite-validate.py` exits clean
