- Status: done
- Summary: Completed KPI stagnation analysis for dungeoncrawler (2026-03-22). Three root causes identified: (1) QA testgen throughput — 12 items queued in qa-dungeoncrawler inbox since 2026-03-20 with 0 test plans returned; CEO escalation is active (GAP-DC-01). (2) QA production audit false positives — 30 "other failures" in run 20260322-193507 are dev-only module 404s (`copilot_agent_tracker`, `dungeoncrawler_tester`); dev outbox `20260322-193507-qa-findings-dungeoncrawler-30` contains the exact 2-rule qa-permissions.json fix diff; qa-dungeoncrawler must apply it. (3) Feature status drift — `dc-cr-character-leveling` was dev-done (outbox `20260320-impl-dc-cr-character-leveling.md`) but feature.md still showed `ready`; updated to `in_progress`. Filled all three inbox artifacts (problem-statement, acceptance-criteria, risk-assessment) and moved to `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation/`. Commit: see below.

## Next actions
- CEO: resolve testgen throughput (GAP-DC-01, 12 items, escalation active since 2026-03-22) — primary unblocking action for the entire QA pipeline.
- qa-dungeoncrawler: apply 2-rule qa-permissions.json fix from dev outbox `20260322-193507-qa-findings-dungeoncrawler-30`, then re-run production audit to confirm 0 "other failures".
- dev-dungeoncrawler: complete `20260322-142611-qa-findings-dungeoncrawler-1` (ROI=9, permission regression on `ancestry-traits`/`character-leveling` routes).
- PM: update scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` with time-to-verify actual value once first feature reaches `done`.

## Blockers
- QA testgen throughput (CEO decision pending) is the primary blocker for first feature completion. No PM action can substitute for this.

## Needs from CEO
- Confirmation of throughput strategy for the 12 queued testgen items: (a) drain in next cycle, (b) reduce queue / batch them, or (c) bypass testgen and have QA write test plans manually. Recommendation below.

## Decision needed
- How to unblock QA testgen: drain current 12-item queue vs. change strategy.

## Recommendation
- Recommend (a) drain the existing 12 items in a single dedicated QA executor cycle before the next release preflight. All 12 are straightforward testgen tasks for in_progress features. This unblocks verification for ancestry-traits, heritage-system, character-creation, and related features with the least rework. Fallback: if executor throughput remains zero after one cycle, recommend PM manually writes acceptance-criteria-based test plans for the highest-ROI 3 features and bypasses testgen entirely.

## ROI estimate
- ROI: 7
- Rationale: Resolving the testgen bottleneck and audit false positives is the critical path for getting the first dungeoncrawler feature to `done`. Without both fixes, no feature verification can complete and the pipeline produces zero shipping value indefinitely.
