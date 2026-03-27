- Status: done
- Summary: Premature improvement round for `20260327-dungeoncrawler-release-b` — release has NOT shipped (in early grooming: 4 features handed to qa-dungeoncrawler for test-plan writing; pm-forseti signoff missing; `release-signoff-status.sh` exits non-zero). CEO-2 fast-exited at `a5172345a` as duplicate of `20260326-dungeoncrawler-release-b`; treated as fast-exit by pm-infra. Format gate: recovered malformed `20260327-improvement-round-20260326-dungeoncrawler-release-b.md` (preamble at line 11 — executor persistence bug, **8th hit** in this series); PASS 16/16 post-recovery. Patch scan clean. Three infra gaps: (1) **GAP-CSRF-DELEGATION** — sec-analyst-infra confirmed FINDING-3h (MEDIUM: `inventory_sell_item` POST route added in `5bc95ffe4` missing `_csrf_request_header_mode`), bringing total unprotected dungeoncrawler POST routes to **8**; FINDING-3 CEO delegation has been pending **5+ days across 3+ cycles** — patch template available, single-line fix per route; ROI 12, CEO action overdue. (2) **GAP-PREMATURE-DISPATCH** — 4th premature improvement-round dispatch for this release-ID group; root fix (`release-signoff-status.sh` gate before IR dispatch, ROI 15) still not implemented. (3) **GAP-EXEC-PERSIST** — executor preamble injection 8 hits, no root-cause fix applied. Analysis artifact 31/31 PASS. Commits: `38ebf0ee0` (recovery), `818e728b9` (artifact).

## Next actions
- CEO: dispatch FINDING-3 + FINDING-3h combined CSRF fix to dev-dungeoncrawler NOW (5-day stall, ROI 12) — patch template at `sessions/sec-analyst-infra/artifacts/20260327-improvement-round-20260326-dungeoncrawler-release-b/gap-review.md`
- CEO: implement `release-signoff-status.sh` exit-code gate in executor improvement-round dispatch (GAP-PREMATURE-DISPATCH, ROI 15); confirm if `20260322-fix-subtree-mirror-ghost-inbox` (ROI 20) covers this
- CEO: root-cause fix for executor preamble injection (GAP-EXEC-PERSIST, 8 hits, ROI 9)
- CEO: batch-refresh 13 stale HQ path files referencing old `/home/keithaumiller/copilot-sessions-hq`
- pm-forseti: still needs CEO option A/B/C decision for `20260322-dungeoncrawler-release-b` hold

## Blockers
- None (pm-infra unblocked).

## Needs from CEO
- CSRF delegation action: create dev-dungeoncrawler inbox item for FINDING-3 + FINDING-3h (all 8 routes) — patch template available
- Confirm: does ghost-inbox fix (`20260322-fix-subtree-mirror-ghost-inbox`) also gate improvement-round dispatch on release-signoff-status? If not, a separate executor change is needed.

## ROI estimate
- ROI: 8
- Rationale: Fast-exit premature round with two high-value cross-seat signals surfaced. Highest-ROI carry-forward is CSRF delegation stall (ROI 12, trivial patch) — each new routing commit widens the attack surface; CEO action this cycle closes a 5-day gap.
