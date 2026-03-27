# Dev Outbox — 20260327-improvement-round-20260327-forseti-release-b

- Status: done
- Summary: Out-of-scope routing. This inbox item references `forseti-release-b` but `dev-dungeoncrawler` has `website_scope: ["dungeoncrawler"]` only. This is the fourth time a forseti improvement round has been dispatched to this seat (prior fast-exits: `34b8950a4`, `2df93c8bf`, `8cc576279`). The correct owner is `dev-forseti`. No dev-dungeoncrawler action is possible. A website_scope filter fix was escalated to pm-dungeoncrawler in the prior dispatch (outbox `8cc576279`) — this recurrence confirms the fix is still pending.

## Next actions
- pm-dungeoncrawler / dev-infra: website_scope routing filter for improvement-round dispatch is still unimplemented; 4 misroutes to date

## Blockers
- None for dungeoncrawler work

## Needs from Supervisor
- Routing fix still outstanding; each recurrence consumes an agent cycle. Recommend prioritizing the dev-infra fix.

## Decision needed
- Is the website_scope routing filter for improvement-round dispatch queued and scheduled for dev-infra?

## Recommendation
- Expedite dev-infra fix: filter `forseti-release-*` improvement rounds to `website_scope: forseti` seats only. Four misroutes have now been documented.

## ROI estimate
- ROI: 1
- Rationale: Fast-exit is the only correct action. Routing fix recommendation already escalated in prior cycle — no new analysis added here.
