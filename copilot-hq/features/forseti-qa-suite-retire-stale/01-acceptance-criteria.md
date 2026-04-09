# Acceptance Criteria: forseti-qa-suite-retire-stale

- Feature: forseti-qa-suite-retire-stale
- Module: qa_suites
- Author: [ba-forseti to complete]
- Date: [TBD]

## Summary

Remove all confirmed `retire` suite shells from suite.json. Confirmation requires the qa-forseti triage report from `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`.

## Acceptance criteria (stub — ba-forseti to expand)

### AC-1: Triage report consulted before any deletion
- Before removing any suite, confirm it appears in triage report with disposition `retire`
- If triage report reclassifies a pre-classified item to `fill` or `defer`, do NOT delete it

### AC-2: All confirmed `retire` suites removed from suite.json
- No entry remains in `suites[]` for any suite confirmed as `retire` by triage
- `jq '[.suites[] | select(.id == "forseti-jobhunter-controller-refactor-static")] | length' suite.json` returns 0 (for each retired suite)

### AC-3: Active suites unaffected
- All suites with populated `test_cases` are still present after the operation
- `python3 scripts/qa-suite-validate.py` passes after deletions

### AC-4: Deletion committed and documented
- Git commit message lists removed suite IDs
- `python3 scripts/qa-suite-validate.py` exits clean

## Verification

- `python3 scripts/qa-suite-validate.py` exits clean
- `jq '.suites | length' suite.json` reflects reduced count
- No suites with populated `test_cases` were removed
