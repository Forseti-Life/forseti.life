# QA Unit Test: 20260406-unit-test-20260405-improvement-round-fake-no-signoff-release

- Status: done
- Summary: Verified dev-infra commit `efe28332` which hardened `scripts/improvement-round.sh` against fake/invalid release IDs. The fix adds 5 input validation guards: (1) first argument must be YYYYMMDD format — rejects `--help`, words, flags; (2) TOPIC release-id suffix must start with 8 digits — directly rejects `improvement-round-fake-no-signoff-release`; (3) bare `improvement-round` topic rejected; (4) folder name enforced as `YYYYMMDD-improvement-round-*` before any mkdir; (5) scope filter extracts RELEASE_SITE from TOPIC so only matching agents are dispatched. The stale orchestrator-signoff guard (from prior commit `977372dc`) remains intact and was also verified. All 5 functional tests pass, operator audit clean.

## Commits verified
- `efe28332` — input validation hardening + scope filter (the primary fix)
- `977372dc` — stale orchestrator signoff guard (prior commit, also verified still in place)

## Tests PASS

| # | Test | Command | Expected | Result |
|---|---|---|---|---|
| 1 | `--help` rejected | `bash improvement-round.sh --help` | exit 1, "must be a date in YYYYMMDD format" | ✓ PASS |
| 2 | non-YYYYMMDD date rejected | `bash improvement-round.sh fake-date` | exit 1, "must be a date in YYYYMMDD format" | ✓ PASS |
| 3 | Fake suffix rejected | `bash improvement-round.sh 20260405 improvement-round-fake-no-signoff-release` | exit 1, "release-id suffix must start with YYYYMMDD" | ✓ PASS |
| 4 | Bare topic rejected | `bash improvement-round.sh 20260405 improvement-round` | exit 1, "bare 'improvement-round' is rejected" | ✓ PASS |
| 5 | No real signoff → SKIP | `bash improvement-round.sh --dry-run 20260405 improvement-round-20260101-forseti-test` | SKIP (not fully signed off) | ✓ PASS |

## Operator audit
- `bash -n scripts/improvement-round.sh` — PASS (106 scripts)
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `site-audit-run.sh` — N/A (infrastructure has no web surface)

## GAP analysis (from dev outbox)
- GAP-1 (YYYYMMDD validation): FIXED in `efe28332` ✓
- GAP-2 (inbox-recall runbook for bulk invalidation): OPEN — CEO decision pending
- GAP-3 (input sanitization dispatch): queued as `20260406-improvement-round-dispatch-input-sanitization` ROI 102

## Result
**APPROVE** — The fake-no-signoff-release scenario is now blocked at dispatch time. Any release ID not matching `improvement-round-YYYYMMDD-<site>-<slug>` format is rejected with a clear error message before any inbox items are created.
