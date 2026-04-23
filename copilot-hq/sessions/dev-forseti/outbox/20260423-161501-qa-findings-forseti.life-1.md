- Status: blocked
- Summary: QA run 20260423-161501 (1 failure: `https://forseti.life/roadmap` returning HTTP 500) has been diagnosed and fixed. Root cause: `loadRoadmapProjects()` called from the `roadmap()` controller contained `Url::fromRoute()->setAbsolute(TRUE)->toString()` without exception handling; an uncaught exception in production context converted the listing page to 500. Fix `6c0f49906` wraps `loadRoadmapProjects()` in a top-level try/catch, guards each individual URL generation call with its own try/catch (falling back to a relative URL on failure), and sets `max-age=0` on the listing render array to prevent caching a broken response. The commit is on `origin/main`. Production requires `git pull origin main` + `drush cr` to activate — no automated deploy path is available from this node.

## Product context
- Release: `20260412-forseti-release-m`
- Route: `forseti_content.roadmap` (`/roadmap`) — homepage-linked, public
- Impact: all visitors to `/roadmap` receive HTTP 500

## Next actions
- Production ops required (cannot execute from this node): `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`
- QA notified: fix `6c0f49906` is on `origin/main`; re-run QA after production deploy

## Blockers
- Production `git pull + drush cr` still required — no SSH from this node to production server

## Needs from Supervisor
- Execute on production server: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`

## Decision needed
- Authorize and execute the production deploy — same request as all prior QA cycle escalations

## Recommendation
- Option A (immediate): human owner runs `git pull + drush cr` on production — closes this and all prior QA BLOCKs in release-m
- Option B (permanent): restore GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) so pushes auto-deploy — prevents recurrence

## ROI estimate
- ROI: 950
- Rationale: Homepage-linked public page serving 500 to all visitors; fix is coded, tested for syntax, and pushed. Single production ops action closes the entire QA BLOCK for release-m.

## Evidence
- Fix commit: `6c0f49906` on `origin/main`
- Outbox commit: `d21c70cd6` on `origin/main`
- QA findings JSON: `sessions/qa-forseti/artifacts/auto-site-audit/20260423-161501/findings-summary.json`
