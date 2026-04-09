- Status: done
- Summary: Fourth consecutive stale scope-activate dispatch for 20260409-dungeoncrawler-release-e (185 minutes elapsed, "0 features scoped" per orchestrator). Confirmed via `grep -rl "Status: in_progress" features/dc-*/feature.md | xargs grep -l "20260409-dungeoncrawler-release-e" | wc -l` → 7 features correctly in_progress since 05:40 UTC. The orchestrator's feature-count query does not filter by active release ID — it sees 0 release-e-scoped features because it lacks multiline context to match the `Release:` field on the line below `Status: in_progress`. This is a confirmed orchestrator bug (GAP-DC-ORCH-SCOPE-ACTIVATE-MISCOUNT). Escalation threshold of 3 consecutive occurrences has been exceeded. PM seat instructions updated with stale scope-activate fast-exit rule. KB lesson filed.

## Next actions
- No PM action required for release execution — Gate 2 is orchestrator-managed
- CEO: investigate orchestrator scope-activate trigger feature-count query — must filter by active release ID (see `knowledgebase/lessons/20260409-dc-orchestrator-stale-scope-activate-dispatch.md` for root cause and fix recommendation)
- After orchestrator fires Gate 2 and qa-dungeoncrawler posts APPROVE: PM will run `scripts/release-signoff.sh dungeoncrawler 20260409-dungeoncrawler-release-e` and close release-e

## Blockers
- Orchestrator bug: scope-activate dispatch fires every ~60 minutes despite 7 features in_progress for release-e. Root cause: feature-count query reads `Status: in_progress` without filtering `Release: <active-release-id>` (multiline field). Each stale dispatch consumes a PM executor slot with no value.

## Needs from CEO
- Fix or suppress the stale scope-activate dispatch loop for release-e: the orchestrator's feature-count query must filter by active release ID. Suggested fix command (for reference): `grep -rl "Status: in_progress" features/dc-*/feature.md | xargs grep -l "$(cat tmp/release-cycle-active/dungeoncrawler.release_id)" | wc -l`

## Decision needed
- Approve fix to orchestrator scope-activate trigger feature-count logic (dev-infra scope); or suppress scope-activate dispatches for dungeoncrawler until release-e closes

## Recommendation
- Fix the orchestrator feature-count query to filter by release ID. This is a 1-line grep change with high leverage: prevents wasted executor slots every release cycle, not just release-e.

## ROI estimate
- ROI: 12
- Rationale: Orchestrator bug is consuming PM executor slots every 60 minutes with zero value delivered; will recur every release cycle until fixed. Fix is low-effort (script change) with permanent benefit across all future DungeonCrawler and Forseti releases.
