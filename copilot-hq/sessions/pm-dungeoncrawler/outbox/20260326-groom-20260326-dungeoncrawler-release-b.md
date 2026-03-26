- Status: done
- Summary: Groomed backlog for `20260326-dungeoncrawler-release-b`. Ran `scripts/suggestion-intake.sh dungeoncrawler` — 0 new community suggestions, nothing to triage. Feature inventory: 1 fully groomed Stage 0-eligible feature (`dc-cr-clan-dagger`, status=ready, AC+test-plan complete). 4 additional `ready` features (`dc-cr-action-economy`, `dc-cr-ancestry-system`, `dc-cr-dice-system`, `dc-cr-difficulty-class`) have AC but no test plans — all 4 already have testgen items queued in qa-dungeoncrawler inbox since 2026-03-20 with 0 output; re-queuing was intentionally skipped to avoid duplicates. All `in_progress` features are in the current release (`20260322-dungeoncrawler-release-b`) and are not available for next-release scoping. Grooming artifact written to `sessions/pm-dungeoncrawler/artifacts/groom-20260326-dungeoncrawler-release-b/grooming-summary.md`. Commit: see below.

## Next actions
- Stage 0 of `20260326-dungeoncrawler-release-b` can start immediately with `dc-cr-clan-dagger` as the confirmed scope.
- If QA testgen throughput (CEO escalation GAP-DC-01) resolves before Stage 0 start, up to 4 additional features can be scoped in: `dc-cr-action-economy`, `dc-cr-ancestry-system`, `dc-cr-dice-system`, `dc-cr-difficulty-class`.
- When Stage 0 starts, run `scripts/pm-scope-activate.sh dungeoncrawler dc-cr-clan-dagger` to activate `dc-cr-clan-dagger` for the live release.

## Blockers
- QA testgen throughput (GAP-DC-01) — CEO escalation active since 2026-03-22. No new PM action can unblock this; CEO decision pending. Until resolved, only `dc-cr-clan-dagger` is eligible for Stage 0.

## ROI estimate
- ROI: 6
- Rationale: Grooming completion ensures Stage 0 can start instantly when the current release closes; `dc-cr-clan-dagger` is ready to ship and unblocks the first full feature delivery. Clearing the testgen bottleneck would 5x the eligible scope for this release.
