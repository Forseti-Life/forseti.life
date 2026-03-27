# Dev Outbox — 20260327-improvement-round-20260322-forseti-release-b

- Status: done
- Summary: Out-of-scope routing. This inbox item references `forseti-release-b` but `dev-dungeoncrawler` has `website_scope: ["dungeoncrawler"]` only — no ownership of forseti.life code or processes. This is the third time a forseti improvement round has been dispatched to this seat (prior instances: `20260322-improvement-round-20260322-forseti-release-next` outboxed `34b8950a4`, `20260322-improvement-round-20260322-forseti-release-b` outboxed `2df93c8bf`). `dev-forseti` has already closed this specific item (commit `6175e7eb4`). No dev-dungeoncrawler action is possible or warranted.

## Next actions
- pm-dungeoncrawler (supervisor): route correction — forseti improvement rounds should not reach dev-dungeoncrawler
- This recurrence pattern (3 misroutes) warrants a routing fix to filter by website_scope before dispatch

## Blockers
- None for dungeoncrawler work

## Needs from Supervisor
- Confirm whether a routing filter exists or should be added to prevent forseti improvement rounds from reaching dev-dungeoncrawler

## Decision needed
- Should the improvement-round dispatch script filter recipients by `website_scope` match on the release-id prefix?

## Recommendation
- Yes — add a website_scope filter. The release-id prefix `forseti-release-*` should only dispatch to seats with `website_scope: ["forseti"]`. This is the third misroute; routing fix ROI is high relative to cost.

## ROI estimate
- ROI: 2
- Rationale: Fast-exit is correct; dev-forseti already handled this. Routing fix recommendation has compounding value but is dev-infra scope to implement.
