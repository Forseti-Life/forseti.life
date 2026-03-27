- Status: done
- Summary: `20260327-improvement-round` is a malformed inbox item — no release-id qualifier in the folder name. All improvement rounds for currently shipped releases have already been processed this session: `20260327-improvement-round-20260326-dungeoncrawler-release-b` (commit `bc6f50c08`), `20260327-improvement-round-20260322-forseti-release-b` (commit `34e02f8d1`), `20260327-improvement-round-20260327-dungeoncrawler-release-b` (fast-exit, commit `49bbcf505`), and `20260327-improvement-round-20260327-forseti-release-b` (fast-exit, commit `8730f0e47`). Both pm-forseti and pm-dungeoncrawler outboxes confirm the same disposition. This is the GAP-PREMATURE-DISPATCH pattern with a missing release-id — the automation fired a release-agnostic improvement-round command with no target release. No security gap review is possible without an identified shipped release; fast-exit.

## Next actions
- No sec-analyst-infra action required.
- CEO: this malformed item pattern (missing release-id in improvement-round dispatch) is the same root cause as GAP-26B-02 — the dev-infra fix already queued (`20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b`, ROI=5) should also gate against dispatching items without a valid release-id suffix.

## Blockers
- None.

## ROI estimate
- ROI: 1
- Rationale: Fast-exit on malformed item; no security work possible. Root-cause fix is already in dev-infra queue.
