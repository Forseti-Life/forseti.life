- Status: blocked
- Summary: QA run 20260423-171501 shows the same single failure: `https://forseti.life/roadmap` returning HTTP 500. This is the same regression as run 20260423-161501. The fix (`6c0f49906`) is already committed and on `origin/main` — it wraps `loadRoadmapProjects()` in try/catch and guards each `Url::fromRoute()->setAbsolute()->toString()` call individually. The 500 persists because production has not yet run `git pull + drush cr`. No new code changes are needed. This is now the second successive QA run confirming the same 500; the only remaining action is the production deploy.

## Product context
- Release: `20260412-forseti-release-m`
- Route: `forseti_content.roadmap` (`/roadmap`) — public, homepage-linked
- Impact: all anonymous visitors receive HTTP 500 on the roadmap page

## Next actions
- No further dev work required — fix is on `origin/main`
- QA handoff: same blocker as 161501 run; requesting retest after production deploy

## Blockers
- Production `git pull origin main` + `drush cr` not yet executed
- This is the same blocker reported in every QA cycle for release-m

## Needs from Supervisor
- Execute on production: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`

## Decision needed
- Authorize and execute the production deploy — this is a persistent, repeated blocker requiring Board/human action

## Recommendation
- Option A (immediate): human owner SSHes to production and runs `git pull + drush cr` — closes QA BLOCK immediately
- Option B (permanent): restore GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) so all future pushes auto-deploy — prevents this class of blocker recurring

## ROI estimate
- ROI: 999
- Rationale: Public page serving 500; fix is on origin/main; this is the 10+ consecutive cycle blocked on the same production ops action. Every cycle this is open costs another QA run and blocks release-m close.

## Evidence
- Fix commit: `6c0f49906` on `origin/main`
- QA findings JSON: `sessions/qa-forseti/artifacts/auto-site-audit/20260423-171501/findings-summary.json`
- Prior outbox (161501): `sessions/dev-forseti/outbox/20260423-161501-qa-findings-forseti-life-1.md`
