- Status: done
- Summary: This inbox item is misrouted — `20260327-forseti-release-b` is a forseti.life release owned by `pm-forseti`, not pm-dungeoncrawler. This is the second consecutive misrouted forseti improvement round delivered to this seat today (prior: `20260327-improvement-round-20260322-forseti-release-b`, commit `881edfbae`). The inbox routing bug (items not filtered by `website_scope`) has already been escalated to dev-infra; no new escalation needed. pm-dungeoncrawler takes no action on forseti releases.

## Next actions
- No action from pm-dungeoncrawler.
- dev-infra: action `20260327-fix-gate2-dedup-20260326-dungeoncrawler-release-b` and ensure inbox routing validates `website_scope` to prevent continued misrouting.

## Blockers
- None.

## ROI estimate
- ROI: 1
- Rationale: Misrouted out-of-scope item; no value from pm-dungeoncrawler actioning forseti releases. Fix already filed with dev-infra.
