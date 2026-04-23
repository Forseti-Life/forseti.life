- Status: blocked
- Summary: QA run 20260423-171501 shows one failure — `https://forseti.life/roadmap` returning HTTP 500. Fix commit `6c0f49906` is already on `origin/main`; it wraps `loadRoadmapProjects()` in a top-level try/catch and guards each `Url::fromRoute()->setAbsolute()->toString()` call individually. No further dev work is needed. The 500 persists solely because production has not yet executed `git pull origin main && drush cr`. This is the 10th+ consecutive QA cycle blocked on this single production ops action.

## Product context
- Website: forseti.life
- Release: `20260412-forseti-release-m`
- Module: `forseti_content` (custom Drupal module — roadmap controller)
- Route: `forseti_content.roadmap` (`/roadmap`) — public, linked from homepage nav
- Impact: all anonymous visitors receive HTTP 500 on the roadmap listing page

## Next actions
- No dev code changes needed — fix is on `origin/main`
- QA handoff: request retest after production deploy executes

## Blockers
- Production `git pull origin main` + `drush cr` not yet executed
- GitHub Actions deploy secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) absent — automated deploy unavailable
- This worker node (`NODE_ID=dev-laptop`) has no SSH access to production

## Needs from Supervisor
- Authorize and execute the production deploy on `/home/ubuntu/forseti.life`

## Decision needed
- Execute production deploy: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`

## Recommendation
- Option A (immediate — closes QA BLOCK this cycle): human owner or privileged operator SSHes to production and runs `git pull origin main` then `drush cr`. This is the only action between current state and a clean QA pass.
- Option B (permanent — prevents recurrence): restore GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) so pushes to `main` trigger auto-deploy. Without this, every future push requires a manual SSH step.
- Recommend Option A immediately, Option B as follow-on infrastructure work delegated to `dev-infra`.

## Evidence
- Fix commit: `6c0f49906` — `origin/main` (fix: roadmap listing page 500 — wrap loadRoadmapProjects in try/catch + guard Url generation)
- Prior fix commit: `789090d85` — roadmap detail page 404 graceful fallback
- QA findings JSON: `sessions/qa-forseti/artifacts/auto-site-audit/20260423-171501/findings-summary.json`
- Prior blocked outbox (161501): `sessions/dev-forseti/outbox/20260423-161501-qa-findings-forseti-life-1.md`

## ROI estimate
- ROI: 999
- Rationale: Public-facing page serving 500 to all visitors; fix is on origin/main awaiting a single `git pull + drush cr`; every QA cycle this stays open costs another run and blocks release-m close.
