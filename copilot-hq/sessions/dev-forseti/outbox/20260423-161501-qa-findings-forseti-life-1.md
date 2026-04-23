- Status: blocked
- Summary: QA run 20260423-161501 reports 1 failure: `https://forseti.life/roadmap` returning HTTP 500. This is a new regression from the previous QA runs (which showed 404s on detail pages). Root cause: when production pulled `789090d85` (fix committed earlier this cycle), the `roadmap()` controller method now calls `loadRoadmapProjects()` which includes `Url::fromRoute()->setAbsolute(TRUE)->toString()`. This can throw an uncaught exception when called for the first time (URL generator context not fully initialized, or some production-specific runtime difference), converting the previous 200 into a 500. A defensive fix (`6c0f49906`) has been committed and pushed to `origin/main`: wraps `loadRoadmapProjects()` in a try/catch, guards each `Url::fromRoute()->setAbsolute()->toString()` call individually, and sets `max-age=0` on the listing page to prevent caching a broken response. Fix is live in the repo via symlink path but requires `drush cr` on production to clear any cached 500 response.

## Product context
- Release: `20260412-forseti-release-m`
- Feature: forseti-content module / roadmap controller (`forseti_content.roadmap` route)
- Impact: the main roadmap listing page (`https://forseti.life/roadmap`) is serving HTTP 500 to all visitors — highest severity public-facing failure

## Next actions
- Production ops required: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`
- After `drush cr`: QA should re-run — `/roadmap` should return 200 with either project cards or a graceful "registry unavailable" fallback
- QA notified: fix `6c0f49906` is pushed; requesting retest after production deploy

## Blockers
- `drush cr` on production server still required — cannot be done from this node (no SSH to production)
- Without `drush cr`, the cached 500 response may persist even after `git pull`

## Needs from Supervisor
- Production server ops action: `git pull origin main` + `drush cr` in `/home/ubuntu/forseti.life/sites/forseti`
- This is the same blocker as all prior QA cycle escalations — the root cause of continued QA BLOCK is missing SSH access / no automated deploy

## Decision needed
- Authorize and execute production deploy: `git pull origin main && drush cr` on the production server at `/home/ubuntu/forseti.life`

## Recommendation
- Option A (recommended): Human owner runs `git pull + drush cr` on production immediately — resolves both the roadmap 500 and closes the QA BLOCK that has been open for multiple cycles
- Option B: Restore GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) so automated deploy fires on every push — prevents recurrence permanently
- Option A unblocks this release; Option B prevents the pattern from repeating

## ROI estimate
- ROI: 950
- Rationale: Public-facing homepage-linked page returning 500. Every visitor hitting the roadmap gets a server error. Fix is coded and pushed; production deploy is the only remaining action.
