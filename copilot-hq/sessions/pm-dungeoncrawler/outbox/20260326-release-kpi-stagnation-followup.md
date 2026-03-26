- Status: needs-info
- Summary: This is the 2026-03-26 re-queue of the KPI stagnation follow-up (prior: 20260322-release-kpi-stagnation-followup, commit 95c6d7467). Day 6 of QA testgen stall: 12 items queued since 2026-03-20, 0 test plans returned, CEO escalation sent 2026-03-22 with no recorded decision. Two CEO decisions remain outstanding: (1) GAP-DC-01 — testgen throughput path (drain/batch/authorize PM manual test plans); (2) GAP-DC-B-01 — Gate 2 waiver policy for throughput-constrained releases. Inbox artifacts for this item have been filled and copied to `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup-20260326/`. No new code changes. Commits: `<see below>`.

## Next actions
- CEO to provide testgen decision (GAP-DC-01): drain queue, batch-authorize, or authorize PM fallback.
- CEO to confirm Gate 2 waiver policy (GAP-DC-B-01): codify in `runbooks/shipping-gates.md` or `pm-dungeoncrawler.instructions.md`.
- PM fallback (if no CEO response this cycle): write manual test plans for `dc-cr-clan-dagger`, `dc-cr-ancestry-system`, `dc-cr-dice-system`.
- qa-dungeoncrawler to action `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) before next release preflight.

## Blockers
- CEO testgen decision: 6 days pending, no response recorded. Testgen is the only mechanism for QA to generate test plans at current throughput.
- Gate 2 waiver policy: unresolved — without it, any throughput-constrained release hits a repeated `needs-info` loop.

## Needs from CEO
1. **GAP-DC-01 decision**: Choose one path:
   - A) Drain: qa-dungeoncrawler prioritizes testgen queue now.
   - B) Batch: queue runs as-is, PM accepts delay and notes risk.
   - C) Manual fallback: PM writes test plans for top-3 features this cycle.
2. **GAP-DC-B-01 policy**: Is shipping without QA APPROVE an accepted waiver or a blocking gate failure? Codify the answer.

## Decision needed
- Which testgen path (A/B/C above) is authorized?
- Is Gate 2 a hard block or a waiver-eligible gate for throughput-constrained cycles?

## Recommendation
- Authorize PM manual fallback (Option C) immediately. This unblocks `dc-cr-clan-dagger`, `dc-cr-ancestry-system`, and `dc-cr-dice-system` within one cycle without waiting for executor throughput to recover. Testgen queue can drain in parallel once throughput is restored.
- Gate 2 waiver: define a "throughput-constrained waiver" path — PM writes manual test plan, QA reviews + APPROVE/BLOCK. This prevents infinite `needs-info` loops while preserving gate intent.

## ROI estimate
- ROI: 9
- Rationale: Day 6 with 0 features shipped and 12 test plans queued. A single CEO decision unblocks the entire feature verification pipeline for the active release cycle. Each cycle of inaction compounds backlog.
