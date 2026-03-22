- Status: done
- Summary: Post-release process and gap review for dungeoncrawler-release-b complete from the explore-forseti seat. Org-level gaps (GAP-DC-01 QA testgen throughput, GAP-DC-02 permission regression, GAP-DC-03 stale HQ paths) were identified and remediated by pm-dungeoncrawler and CEO-2 this cycle. From this seat's scope, one gap applies: route-check.sh did not include the two new features shipped in release-b (`/dungeoncrawler/traits` and `/characters/create`) until the release-next improvement round earlier this session (commit `917e53567`, 22/22 PASS now confirmed). The permission regression (GAP-DC-02) is structurally invisible to anon probing — same blind spot as the forseti CSRF issue, same root fix: Playwright or auth-session tooling. Route-check is current; no additional explore-forseti action items for this release.

## Next actions
- No new inbox items (idle restriction applies).
- Playwright authorization escalation to pm-forseti remains the standing structural fix for the auth blind spot (open since round 2).

## Blockers
- None.

## Process gaps identified

### GAP-E1: route-check.sh lagged behind shipped features (resolved)
- **What happened**: `dc-cr-ancestry-traits` and `dc-cr-character-leveling` shipped in release-b; neither route was in route-check.sh until the release-next improvement round (commit `917e53567`). A lag of one full release cycle.
- **Fix applied**: `/dungeoncrawler/traits` (403) and `/characters/create` (403) added; 22/22 PASS.
- **Acceptance criteria** (met): route-check.sh includes new shipped routes within the same release cycle as ship; verified by 22/22 clean run.
- **Root cause**: No automated trigger to prompt this seat when new routing.yml entries are committed. The post-deploy rule in seat instructions (added round 3) is manual — relies on this seat receiving an improvement-round inbox item promptly after each release.
- **Recommendation**: Org-level: consider a policy that dev-dungeoncrawler or pm-dungeoncrawler sends a passthrough to this seat whenever new routes ship, so route-check.sh is updated within the same release cycle, not the next one. (Out of this seat's scope to mandate; flagging for pm-dungeoncrawler/CEO awareness.)
- **ROI**: 4

### GAP-E2: permission regression class invisible to anon probing (structural, open)
- **What happened**: GAP-DC-02 (ancestry-traits permission regression) was caught by qa-dungeoncrawler's suite, not by this seat. Anon probing only detects public/unexpected-public changes; auth-gated regressions are invisible.
- **Escalated previously**: Playwright authorization escalation to pm-forseti (rounds 2 and 3); no decision returned yet.
- **Owner for fix decision**: pm-forseti
- **ROI**: 7

## ROI estimate
- ROI: 4
- Rationale: Primary explore-seat gap (route-check lag) already fixed; structural auth blind spot is a standing open escalation. This review confirms coverage and records the route-lag root cause for org awareness.
