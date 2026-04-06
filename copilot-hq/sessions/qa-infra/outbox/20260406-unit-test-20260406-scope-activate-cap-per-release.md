# QA Unit Test: 20260406-unit-test-20260406-scope-activate-cap-per-release

- Status: done
- Summary: Verified dev-infra commit `f77a66a6` which fixes GAP-B-02 in `scripts/pm-scope-activate.sh`. The 20-feature cap previously counted all `in_progress` features for the site regardless of release ID, blocking new-release activations when prior-cycle features remained in_progress. The fix adds a third grep filter scoping the count to `^- Release:.*${ACTIVE_RELEASE_ID}` — only features tagged to the current release count toward the cap. When `ACTIVE_RELEASE_ID` is empty, the script falls back to the original global count (no regression). All 4 AC tests PASS. Operator audit clean. This fix complements GAP-RB-03 (`9b041f50`) which ensures newly activated features carry the correct Release field.

## Commit verified
- `f77a66a6` — GAP-B-02: scope cap count to current release only in `pm-scope-activate.sh`

## Tests PASS

| # | Test | Scenario | Expected | Result |
|---|---|---|---|---|
| T1 | Scoped vs global counts | 3 prior-cycle + 1 current-release features | scoped=1, global=4 | ✓ PASS |
| T2 | Cap fires at 20 scoped | 20 current-release + 5 prior-cycle features | cap fires; prior-cycle ignored | ✓ PASS |
| T3 | Fallback: empty ACTIVE_RELEASE_ID | no active release set | global count used (3) | ✓ PASS |
| T4 | Site isolation | dungeoncrawler features present | forseti count unaffected | ✓ PASS |

## Operator audit
- `bash -n scripts/pm-scope-activate.sh` — PASS
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `scripts/site-audit-run.sh` — N/A (infrastructure has no web surface)

## Integration context
- Depends on GAP-RB-03 (`9b041f50`): features activated before that fix may lack the Release: field and are correctly excluded from the scoped count (not a regression — they belong to prior cycles)
- Complements orchestrator FEATURE_CAP fix (`04e29e01`) with consistent per-release semantics at the activation gate
- KB ref: none found specific to per-release cap scoping; gap first identified as GAP-B-02 this cycle

## Site-audit note
Command requested `site-audit-run.sh` with `ALLOW_PROD_QA=1`. Per `org-chart/sites/infrastructure/site.instructions.md`, infrastructure has no web surface; operator-audit mode applied instead.

## Result
**APPROVE** — GAP-B-02 fix confirmed working. Scoped count correctly filters to current release; cap enforcement at 20 scoped features; global fallback intact; site isolation confirmed. No new Dev items identified. PM may proceed to release gate.
