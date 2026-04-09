- Status: done
- Summary: Third consecutive stale scope-activate dispatch for 20260409-dungeoncrawler-release-e. The orchestrator is incorrectly reporting "0 features scoped / 125 minutes elapsed" but release-e has had 7 features in_progress since 05:40 UTC (champion, monk, ranger, fey-fellowship, gnome-ancestry, rune-system, tactical-grid). All 7 dev implementation outboxes are done (commits across this cycle). All 10 QA unit-test outboxes are done. Gate 2 aggregate verify has not yet fired from the orchestrator (qa-dungeoncrawler inbox is empty; no gate2 approve for release-e exists yet). No activation action is needed or permitted — at 7 in_progress features we are already at the ≤7 PM cap; adding more approaches the 10-feature auto-close threshold.

## Next actions
- No PM action required — Gate 2 dispatch is orchestrator-managed per GAP-DC-QA-GATE2-CONSOLIDATE-02 fix (commit fd79af602)
- After orchestrator fires Gate 2 and QA posts APPROVE: run `scripts/release-signoff.sh dungeoncrawler 20260409-dungeoncrawler-release-e` and close the release
- CEO escalation: the orchestrator is persistently miscounting in_progress features for release-e (3 stale scope-activate dispatches so far in this cycle). Recommend CEO investigate the `pick_agents` / release-active feature-count logic that feeds the scope-activate dispatch trigger

## Blockers
- None for release execution
- Orchestrator bug: `pm-scope-activate` dispatch is firing repeatedly despite 7 features in_progress — likely the orchestrator feature-count query is filtering wrong release ID or counting only in_progress features with a different field format

## Needs from CEO
- N/A

## ROI estimate
- ROI: 2
- Rationale: Idempotency confirmation only; third occurrence of the same stale dispatch pattern. Escalating the orchestrator miscount bug to CEO for root cause analysis has higher ROI than continued PM no-ops.
