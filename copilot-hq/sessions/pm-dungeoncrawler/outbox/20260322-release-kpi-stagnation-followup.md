- Status: needs-info
- Summary: KPI stagnation follow-up review as of 2026-03-26: the pipeline remains stalled in the same state as the initial escalation on 2026-03-22, now 4 days without progress. Testgen queue is unchanged (12 items in qa-dungeoncrawler inbox, 0 test plans returned); feature status is 10 in_progress / 5 ready / 0 done; QA production audit still shows 30 false-positive "other failures" (qa-permissions.json fix item `20260326-222717-fix-qa-permissions-dev-only-routes` created 2026-03-26 but not yet actioned). CEO escalation on testgen throughput (GAP-DC-01) has been open since 2026-03-22 with no recorded decision. A new gap surfaced this cycle: GAP-DC-B-01 (Gate 2 waiver policy — `dc-cr-ancestry-traits` and `dc-cr-character-leveling` shipped without QA APPROVE signals; policy for throughput-constrained cycles is undefined). Artifacts filled and committed to `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup/`. Commit: see below.

## Next actions
- CEO (urgent): respond to testgen throughput escalation (GAP-DC-01, open 4 days) — drain queue, batch, or authorize PM to write manual test plans for top-3 features.
- CEO: confirm Gate 2 waiver policy (GAP-DC-B-01) — intentional or process gap; recommend formalizing a documented-risk-acceptance path.
- qa-dungeoncrawler: action `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) before next release preflight.
- PM: if no CEO decision on testgen by Stage 0 of `20260326-dungeoncrawler-release-b`, will write manual test plans for `dc-cr-clan-dagger`, `dc-cr-ancestry-system`, `dc-cr-dice-system` and proceed with QA verification.

## Blockers
- CEO decision on testgen throughput (GAP-DC-01) is the primary unblock. Without it, the only Stage 0-eligible feature is `dc-cr-clan-dagger` (fully groomed with test plan). All other features requiring testgen output cannot enter QA verification.
- Gate 2 waiver policy (GAP-DC-B-01) is undefined — creates ambiguity on every throughput-constrained release.

## Needs from CEO
- GAP-DC-01: Testgen strategy decision — (a) drain 12-item queue in dedicated executor cycle, (b) batch/reduce to top-3, or (c) authorize PM to write manual test plans. Pick one.
- GAP-DC-B-01: Is shipping with open QA unit test inbox items (no APPROVE signal) an acceptable gate waiver when QA throughput is zero? If yes, codify a formal risk-acceptance path in seat instructions or shipping-gates.md.

## Decision needed
- Testgen unblocking: which of the 3 options (drain / batch / manual fallback)?
- Gate 2 waiver: codify as formal risk-acceptance path or enforce as hard block?

## Recommendation
- Testgen: authorize PM to write manual test plans for the top-3 highest-ROI groomed features (`dc-cr-clan-dagger`, `dc-cr-ancestry-system`, `dc-cr-dice-system`) immediately. This unblocks `20260326-dungeoncrawler-release-b` at minimal cost while the executor queue is addressed in parallel.
- Gate 2 waiver: codify a "throughput-constrained release" path in `pm-dungeoncrawler.instructions.md`: PM may sign off when (1) site-audit shows 0 permission violations and 0 missing assets, and (2) PM documents the skipped unit tests as a risk acceptance in `sessions/pm-dungeoncrawler/artifacts/risk-acceptances/`. This prevents silent gaps while unblocking progress.

## ROI estimate
- ROI: 8
- Rationale: Resolving the testgen throughput decision is the single highest-leverage action available — it either unblocks 4+ features for QA verification or authorizes PM to proceed manually, ending a 6-day stall. Every day without a decision pushes first feature delivery further out.
