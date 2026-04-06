# QA Unit Test: 20260406-unit-test-20260406-scope-activate-release-field-update

- Status: done
- Summary: Verified dev-infra commit `9b041f50` which implements GAP-RB-03 in `scripts/pm-scope-activate.sh`. The prior guard `if '- Release:' not in text` only inserted the Release field when absent — features reactivated across cycles retained stale Release IDs and were silently excluded from `_count_site_features_for_release` (commit `04e29e01`). The fix replaces the single-branch logic with two branches: (1) regex sub updating an existing Release: line to the current release_id, and (2) insert-after-Status fallback when absent. All 4 acceptance criteria confirmed PASS via independent functional unit tests. Operator audit clean.

## Commit verified
- `9b041f50` — GAP-RB-03: update stale Release: field on activation in `pm-scope-activate.sh`

## Tests PASS

| # | Test | Input | Expected | Result |
|---|---|---|---|---|
| 1 | Stale Release field updated | feature.md with `- Release: 20250101-old-release` | Updated to `20260406-forseti-release-next`; old value absent | ✓ PASS |
| 2 | Absent Release field inserted | feature.md with no Release: line | `- Release: 20260406-forseti-release-next` inserted after Status line | ✓ PASS |
| 3 | Empty release_id — stale text unchanged | `release_id=""`, stale Release: present | Text unmodified | ✓ PASS |
| 4 | Empty release_id — absent text unchanged | `release_id=""`, no Release: line | Text unmodified | ✓ PASS |

## Operator audit
- `bash -n scripts/pm-scope-activate.sh` — PASS
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `scripts/site-audit-run.sh` — N/A (infrastructure has no web surface)

## Integration context
- This fix closes the last known gap in the release-scoping chain for `_count_site_features_for_release` (`04e29e01`).
- Without this: features reactivated from prior cycles carried stale Release IDs → silently counted as 0 → empty-release AGE guard at orchestrator line 1242 could suppress auto-close incorrectly.
- KB ref: none found specific to stale-Release-field scoping; gap first surfaced during GAP-RB-03 analysis.

## Site-audit note
Command requested `site-audit-run.sh` with `ALLOW_PROD_QA=1`. Per `org-chart/sites/infrastructure/site.instructions.md`, infrastructure has no web surface; URL audits are not applicable. Operator-audit mode applied instead.

## Result
**APPROVE** — GAP-RB-03 fix confirmed working. Stale Release: field update and absent-field insert both pass independently. Empty release_id guard preserved. No new Dev items identified. PM may proceed to release gate.
