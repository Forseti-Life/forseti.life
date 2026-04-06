# QA Unit Test: 20260406-unit-test-20260406-orchestrator-preflight-dedup

- Status: done
- Summary: Verified dev-infra commit `0c0c9b9e` which implements GAP-QA-PREFLIGHT-DEDUP-01 in `scripts/release-cycle-start.sh`. The fix adds a time-based dedup guard before preflight dispatch: scans `sessions/<qa_agent>/outbox/` for any preflight outbox written within the last 4 hours, then queries `git log --since=<mtime>` for QA-scoped paths (`qa-suites/`, `qa-permissions.json`, `features/**/03-test-plan.md`). If no QA-scoped commits found, exits with PREFLIGHT-SUPPRESSED. If QA commits exist, dispatch proceeds. The unit test suite `orchestrator/tests/test_preflight_dedup.py` was run directly and all 4 tests PASS. Operator audit clean.

## Commit verified
- `0c0c9b9e` — GAP-QA-PREFLIGHT-DEDUP-01: time-based preflight dedup guard in `release-cycle-start.sh`

## Tests PASS

All 4 tests in `orchestrator/tests/test_preflight_dedup.py` run with `python3 orchestrator/tests/test_preflight_dedup.py -v`:

| Test | Scenario | Expected | Result |
|---|---|---|---|
| test_suppressed_when_recent_outbox_no_qa_commits | Recent outbox (< 4h) + no QA commits | PREFLIGHT-SUPPRESSED | ✓ PASS |
| test_allowed_when_no_recent_outbox | No recent preflight outbox | Dispatch proceeds | ✓ PASS |
| test_allowed_when_qa_commits_exist | Recent outbox + QA-scoped commits since mtime | Dispatch proceeds | ✓ PASS |
| test_different_agent | Different qa_agent with its own recent outbox | Suppressed per-agent scope | ✓ PASS |

```
Ran 4 tests in 0.083s
OK
```

## Operator audit
- `bash -n scripts/release-cycle-start.sh` — PASS
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `scripts/site-audit-run.sh` — N/A (infrastructure has no web surface)

## Context
- Root cause: each new release advance generated a unique `item_id`, bypassing the exact-match dedup guard; 7 redundant preflight dispatches were observed in ~2 hours during the 20260406 cycle
- Reclaims ~7 QA execution slots per release cycle with no QA signal lost
- KB ref: none found specific to time-based preflight dedup; gap identified as GAP-QA-PREFLIGHT-DEDUP-01 this cycle

## Site-audit note
Command requested `site-audit-run.sh` with `ALLOW_PROD_QA=1`. Per `org-chart/sites/infrastructure/site.instructions.md`, infrastructure has no web surface; operator-audit mode applied instead.

## Result
**APPROVE** — GAP-QA-PREFLIGHT-DEDUP-01 fix confirmed working. All 4 unit test cases PASS. Suppression fires correctly when no QA config changes; dispatch proceeds when QA-scoped commits exist; per-agent scope confirmed. No new Dev items identified. PM may proceed to release gate.
