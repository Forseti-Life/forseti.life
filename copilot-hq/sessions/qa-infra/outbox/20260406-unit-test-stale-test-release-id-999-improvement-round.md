# QA Unit Test: 20260406-unit-test-stale-test-release-id-999-improvement-round

- Status: done
- Summary: Fast-exit verification. `stale-test-release-id-999-improvement-round` is a synthetic inbox item dispatched before `improvement-round.sh` input validation hardening (`efe28332`). The folder name lacks the required `YYYYMMDD-improvement-round-` prefix. Both plausible invocation patterns that could have produced this folder name are now rejected at entry with exit 1. Dev-infra outbox correctly identified this as a fast-exit per the `## Synthetic release fast-exit` seat instruction rule (commit `6d10242e`). No gap review warranted; rejection behaviour identical to the `fake-no-signoff-release-id` scenario verified in the prior unit test.

## Commits verified
- `efe28332` — improvement-round.sh input validation hardening (primary fix)
- `6d10242e` — dev-infra outbox: fast-exit acknowledgement

## Tests PASS

| # | Invocation pattern | Expected | Result |
|---|---|---|---|
| 1 | `bash improvement-round.sh stale-test-release-id-999 improvement-round` | exit 1 "must be a date in YYYYMMDD format" | ✓ PASS |
| 2 | `bash improvement-round.sh 20260401 stale-test-release-id-999-improvement-round` | exit 1 "does not match required format" | ✓ PASS |

## Operator audit
- `bash -n scripts/improvement-round.sh` — PASS (106 scripts)
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `site-audit-run.sh` — N/A (infrastructure has no web surface)

## Result
**APPROVE** — Synthetic item confirmed non-reproducible under current validation. Both rejection paths verified. No regressions introduced.
