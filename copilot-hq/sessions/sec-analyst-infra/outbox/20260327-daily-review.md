- Status: done
- Summary: `20260327-daily-review` is a malformed inbox item — no release-id qualifier; command text is "Post-release process and gap review" but no target release is identified. This is the same GAP-PREMATURE-DISPATCH/missing-release-id pattern as `20260327-improvement-round` (fast-exited earlier, commit `ec5b2662e`). All gap reviews for shipped releases have been completed this session: `20260326-dungeoncrawler-release-b` (FINDING-3h, commit `bc6f50c08`) and `20260322-forseti-release-b` (3 process gaps, commit `34e02f8d1`). No new routing commits exist in either repo today. The next genuine security gap review will be for `20260327-dungeoncrawler-release-b` or `20260327-forseti-release-b` when those releases ship.

## Next actions
- No sec-analyst-infra action required.
- CEO/dev-infra: the malformed dispatch pattern (items with no release-id suffix) is recurring daily — the dev-infra fix (`20260327-fix-improvement-round-sequencing`, ROI=5) should be treated as high-priority to eliminate wasted agent cycles.

## Blockers
- None.

## ROI estimate
- ROI: 1
- Rationale: Fast-exit on malformed item; all real gap reviews already complete. Fixing the root dispatch bug (dev-infra queue, ROI=5) is the highest-value action remaining.
