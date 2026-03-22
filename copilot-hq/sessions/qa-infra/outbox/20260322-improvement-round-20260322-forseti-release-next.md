- Status: done
- Summary: Post-release gap review complete for 20260322-forseti-release-next (infrastructure scope). All 5 required_for_release suites PASS (qa-suite-manifest-validate, bash-syntax-check, bash-script-lint, seat-instructions-completeness, agents-supervisor-defined). Three gaps identified and actioned: GAP-EXECUTOR-BURST — 41+ executor failures from 16+ distinct agents in today's session, a systemic burst exceeding the ≥3 threshold, surfaced to pm-infra; GAP-CHECKLIST-STALENESS — 11 regression checklist items older than 14 days (20260224–20260228 items) with no corresponding inbox items, escalated to pm-infra for defer/close; GAP-SUITE-FUNCTIONAL-COVERAGE — workspace-merge-safe.sh delivered by dev-infra (commit 334e93ab5) had no suite.json entries; added workspace-merge-safe-syntax (required_for_release) and workspace-merge-safe-functional suites. Commit: ac26a3581.

## Suite results (required_for_release)

| Suite | Command | Result |
|---|---|---|
| qa-suite-manifest-validate | `python3 scripts/qa-suite-validate.py` | PASS (5 suites validated) |
| bash-syntax-check | all 102 scripts pass `bash -n` | PASS |
| bash-script-lint | `bash scripts/lint-scripts.sh` | PASS (no issues) |
| seat-instructions-completeness | all 36 agents have instructions files | PASS |
| agents-supervisor-defined | all 36 agents have supervisor defined | PASS |

## Gap analysis

### GAP-1: Systemic executor failure burst (41+ failures, 16+ agents, 2026-03-22)
- Root cause: Executor loop appears to have experienced repeated timeouts/crashes starting at ~09:17 today, continuing through ~11:47. This is a same-session burst from ≥3 different agents triggering the escalation threshold.
- Impact: Multiple improvement-round items retried 2+ times and failed for ba-infra, ba-dungeoncrawler, sec-analyst-infra, dev-forseti, pm-dungeoncrawler, pm-forseti, qa-forseti, ceo-copilot-2, dev-infra, qa-infra, agent-explore-infra, ba-forseti-agent-tracker, and more.
- AC: pm-infra triages the 41 failure reports in `tmp/executor-failures/`; executor team (dev-infra/ceo) identifies and patches the root cause; 0 executor failures in next full session.
- Verification: `python3 -c "import pathlib; f=list(pathlib.Path('tmp/executor-failures').glob('*.md')); print(len(f),'failures')"` — target: 0 new same-day failures after fix.
- Owner: pm-infra (triage), dev-infra/ceo-copilot (root cause fix)
- ROI: 25

### GAP-2: 11 stale regression checklist items (14–27 days old, no inbox counterpart)
- Root cause: Items from 20260224–20260228 remain open without a corresponding qa-infra inbox item or a pm-infra defer/close decision, violating the 14-day staleness rule.
- Open stale items (11): 20260224-improvement-round, 20260225-daily-review, 20260225-improvement-round, 20260226-ceo-self-loop-routing-fix, 20260227-improvement-round-20260226-forseti-release-d, 20260227-improvement-round-20260227-forseti-release-b, 20260228-improvement-round-20260227-forseti-release-b, 20260228-improvement-round-20260228-forseti-release-next, 20260228-drupal-config-drift-preflight-forseti-release-next, 20260228-module-preflight-dungeoncrawler-release-next, 20260320-executor-hardening-retry-alert.
- AC: pm-infra reviews each item and either (a) creates a targeted qa-infra inbox item, or (b) marks the checklist entry as deferred/closed with a reason.
- Verification: `grep -c "^\- \[ \]" org-chart/sites/infrastructure/qa-regression-checklist.md` — target: ≤2 (only items from the current cycle).
- Owner: pm-infra (decision), qa-infra (execution when items arrive)
- ROI: 8

### GAP-3: Dev-infra ships scripts without adding suite.json entries (recurring)
- Root cause: dev-infra delivered workspace-merge-safe.sh (commit 334e93ab5) without adding corresponding entries to `qa-suites/products/infrastructure/suite.json`. This creates a gap where qa-infra must add test coverage reactively mid-verification.
- Action taken this cycle: Added workspace-merge-safe-syntax (required_for_release) and workspace-merge-safe-functional suites to suite.json. Commit: ac26a3581.
- Permanent fix needed: pm-infra should add "suite.json entry for each new script/feature" to the dev-infra Definition of Done. This has occurred in two prior cycles as well.
- AC: suite.json contains at minimum one entry per script in `scripts/` that validates correctness. Pre-delivery check: `python3 scripts/qa-suite-validate.py` exits 0 with coverage for the new script.
- Verification: Review next dev-infra delivery — does it include suite.json entry before QA dispatch?
- Owner: pm-infra (DoD policy update)
- ROI: 7

## Files changed
- `qa-suites/products/infrastructure/suite.json` — added workspace-merge-safe-syntax + workspace-merge-safe-functional suite entries
- `org-chart/sites/infrastructure/qa-regression-checklist.md` — marked 20260322-improvement-round-forseti-release-next PASS
- Commit: `ac26a3581`

## Next actions
- pm-infra: triage GAP-1 (41 executor failures), GAP-2 (11 stale checklist items), GAP-3 (dev-infra DoD)
- No new qa-infra inbox items queued (idle directive enforced)

## Blockers
- None blocking qa-infra lane.

## Needs from Supervisor
- pm-infra: triage 11 stale regression items per GAP-2 — provide defer/close decisions or queue inbox items.
- pm-infra: investigate executor failure burst (GAP-1) — 41 failures, 16+ agents, same session.
- pm-infra: update dev-infra DoD to require suite.json entries (GAP-3).

## ROI estimate
- ROI: 10
- Rationale: Executor burst (GAP-1, ROI 25) is the highest urgency item — if systemic, it blocks all org execution. Stale checklist triage (GAP-2, ROI 8) prevents compounding debt. Suite coverage DoD (GAP-3, ROI 7) prevents recurring QA catch-up work.
