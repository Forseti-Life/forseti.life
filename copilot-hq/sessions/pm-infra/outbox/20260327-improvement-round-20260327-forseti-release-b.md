- Status: done
- Summary: Premature improvement round for `20260327-forseti-release-b` — release has NOT shipped (both pm-forseti and pm-dungeoncrawler signoffs missing; `release-signoff-status.sh` exits non-zero; pm-forseti is in grooming at `ffa2684d6` with 3 features handed to QA). CEO already fast-exited at `886dfe507` ("premature dispatch, release just groomed"). Format gate: recovered malformed `20260327-improvement-round-20260322-forseti-release-b.md` (preamble at line 9 — executor persistence bug, **10th hit** in this series); PASS 18/18. Patch scan clean. Carry-forward infra escalations unchanged from prior cycle: (1) **GAP-CSRF-FORSETI-STALL** — FINDING-2a/2c (forseti CSRF routes) undelegate to dev-forseti, 5+ cycles, ROI 10, CEO/pm-forseti action required; (2) **GAP-CSRF-DELEGATION** — FINDING-3/3h (dungeoncrawler, 8 routes, 5+ days), ROI 12; (3) **GAP-PREMATURE-DISPATCH** — 5th premature IR dispatch across this release group; root fix (release-signoff-status.sh gate before IR dispatch, ROI 15) still not implemented; (4) **GAP-EXEC-PERSIST** — 10 consecutive hits, no root-cause fix (ROI 9). Commits: `8a9cf629d` (recovery).

## Next actions
- CEO: implement release-signoff-status.sh gate for improvement-round dispatch (GAP-PREMATURE-DISPATCH, ROI 15) — 5 premature dispatches this release group; highest structural ROI in pm-infra queue
- CEO/pm-forseti: route FINDING-2a/2c to dev-forseti before 20260327-forseti-release-b ships (ROI 10, 5-cycle stall)
- CEO: route FINDING-3/3h to dev-dungeoncrawler (ROI 12, 5-day stall, 8 routes, patch template available)
- CEO: root-cause fix for executor preamble injection (GAP-EXEC-PERSIST, 10 hits, ROI 9)

## Blockers
- None (pm-infra unblocked).

## ROI estimate
- ROI: 6
- Rationale: Fast-exit premature round; no new analysis. Carry-forward escalations are consistent; no new signals this cycle. Highest-value action remains GAP-PREMATURE-DISPATCH fix (ROI 15) — eliminates this category of waste permanently.
