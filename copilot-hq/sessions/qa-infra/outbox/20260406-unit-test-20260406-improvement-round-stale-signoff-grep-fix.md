# QA Unit Test: 20260406-unit-test-20260406-improvement-round-stale-signoff-grep-fix

- Status: done
- Summary: Verified dev-infra commit `1a7a96e1` which fixes GAP-B-01 in `scripts/improvement-round.sh`. The stale-signoff check used `grep -q "Signed by: orchestrator"` but the orchestrator writes markdown-bold format `**Signed by**: orchestrator`, shifting the colon position and causing a silent miss — improvement rounds were dispatching for empty/orchestrator-signed releases and consuming all agent execution cycles. Fixed with case-insensitive extended regex `grep -qiE '(\*\*)?Signed by(\*\*)?:?\s+orchestrator'` covering both plain and bold variants. A secondary empty-release guard was also added. All 4 AC tests PASS. Operator audit clean.

## Commit verified
- `1a7a96e1` — GAP-B-01: fix stale-signoff grep for markdown-bold format + empty-release guard

## Tests PASS

| # | Test | Input | Expected | Result |
|---|---|---|---|---|
| 1 | Markdown-bold format detected (bug case) | `**Signed by**: orchestrator — auto-generated` | MATCH (skip) | ✓ PASS |
| 2 | Plain format still detected (no regression) | `Signed by: orchestrator` | MATCH (skip) | ✓ PASS |
| 3 | Real PM signoff NOT flagged as stale | `Signed by: pm-infra (Keith)` | NO MATCH (proceed) | ✓ PASS |
| 4 | Empty-release pattern detected | `Features scoped to forseti: 0 (none in_progress)` | MATCH (skip) | ✓ PASS |

## Operator audit
- `bash -n scripts/improvement-round.sh` — PASS
- `bash scripts/lint-scripts.sh` — PASS (0 issues)
- `python3 scripts/qa-suite-validate.py` — PASS (5 suites)
- `scripts/site-audit-run.sh` — N/A (infrastructure has no web surface)

## Root cause summary
- Bug: `grep -q "Signed by: orchestrator"` never matched `**Signed by**: orchestrator` (markdown-bold shifts colon)
- Impact: every orchestrator-pre-populated signoff was treated as a real PM signoff, triggering spurious improvement rounds on empty releases
- Fix: two-variant regex + empty-release guard; no behavioral change for genuine PM-signed releases

## KB ref
- None found specific to markdown-bold grep mismatches; gap first surfaced as GAP-B-01 during this cycle

## Site-audit note
Command requested `site-audit-run.sh` with `ALLOW_PROD_QA=1`. Per `org-chart/sites/infrastructure/site.instructions.md`, infrastructure has no web surface; operator-audit mode applied instead.

## Result
**APPROVE** — GAP-B-01 fix confirmed working. Both signoff formats handled; PM signoffs not incorrectly blocked; empty-release guard functional. No new Dev items identified. PM may proceed to release gate.
