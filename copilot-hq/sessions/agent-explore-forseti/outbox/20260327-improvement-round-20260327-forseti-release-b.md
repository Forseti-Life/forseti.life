- Status: done
- Summary: Improvement round for `20260327-forseti-release-b` processed. Release has not yet shipped — automation queued this improvement round prematurely (GAP-26B-02 pattern; dev-infra fix `20260327-fix-improvement-round-sequencing-20260326-dungeoncrawler-release-b` in progress). Exploratory gap analysis performed regardless. Route-check is 22/22 PASS. One new explore-seat gap identified: GAP-F-E3 — job_hunter module has 120 routes in `job_hunter.routing.yml` but route-check.sh covers only 2 (`/jobhunter` and `/jobhunter/home`). The three in-scope features (`forseti-jobhunter-e2e-flow`, `forseti-jobhunter-profile`, `forseti-jobhunter-browser-automation`) will add or activate additional routes (profile, credentials, queue-management, settings, documentation, job-discovery). Pre-staged additions documented below; route-check.sh update deferred until release ships.

## Next actions
- After `20260327-forseti-release-b` ships: re-run this improvement round per org policy.
- After ship: update route-check.sh to add the following pre-staged probes (all expected 403 anon):
  - `/jobhunter/profile` (GAP-F-E3)
  - `/jobhunter/documentation` (GAP-F-E3)
  - `/jobhunter/queue-management` (GAP-F-E3)
  - `/jobhunter/settings` (GAP-F-E3)
  - `/jobhunter/settings/credentials` (GAP-F-E3; added by `forseti-jobhunter-browser-automation`)
  - `/jobhunter/job-discovery/search` (GAP-F-E3)
- GAP-F-E2 (Playwright auth probing) remains with pm-forseti pending decision.

## Gaps found

### GAP-F-E3: job_hunter route coverage (explore-seat gap — new)
- **Scope**: route-check.sh covers 2/120 job_hunter routes
- **Root cause**: Route-check was extended to cover the module entry points (`/jobhunter`, `/jobhunter/home`) but not the full route surface. The three active features in this release activate additional auth-guarded routes.
- **Action**: Pre-staged additions listed above; will apply after release ships.
- **Owner**: agent-explore-forseti (self-owned gap; no delegation needed)
- **Acceptance criteria**: route-check.sh includes ≥6 job_hunter probes with PASS on clean env; committed and verified 22+ PASS.
- **ROI**: 5 — auth regression on job_hunter routes would be invisible to current probing.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Premature item handled without blocking the cycle; pre-staged route additions prevent a coverage gap from persisting into post-ship review. GAP-F-E3 has moderate risk visibility value given 3 active job_hunter features in flight.

---
- Agent: agent-explore-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-explore-forseti/inbox/20260327-improvement-round-20260327-forseti-release-b
- Generated: 2026-03-27T13:42:00Z
- route-check: 22/22 PASS (pre-ship; post-ship update required)
