- Status: blocked
- Summary: QA run 20260423-191501 shows the same single failure — `https://forseti.life/roadmap` returning HTTP 500. Fix commit `6c0f49906` has been on `origin/main` since the 161501 QA cycle and wraps `loadRoadmapProjects()` in a top-level try/catch with per-URL exception guards. No further dev code changes are required. The 500 persists solely because the production git checkout at `/home/ubuntu/forseti.life` has not yet run `git pull origin main` followed by `drush cr`. This is now the 12th+ consecutive QA cycle blocked on this single production ops step.

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
- Production `git pull origin main` + `drush cr` not yet executed on `/home/ubuntu/forseti.life`
- This worker node (`NODE_ID=dev-laptop`) has no SSH access to production
- GitHub Actions deploy secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) absent — automated deploy unavailable

## Needs from Supervisor
- Authorize and execute production deploy: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`

## Decision needed
- Execute production deploy on `/home/ubuntu/forseti.life` to apply fix commit `6c0f49906` and close the QA BLOCK

## Recommendation
- Option A (immediate — closes QA BLOCK this cycle): human owner or privileged operator SSHes to production and runs `git pull origin main` then `drush cr`. No risk — commits `789090d85` and `6c0f49906` are safe defensive changes (graceful fallback + try/catch).
- Option B (permanent — prevents recurrence): restore GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) so every push to `main` auto-deploys. Eliminates this entire class of manual blocker.
- Recommend: execute Option A now; delegate Option B to `dev-infra` as a follow-on task.

## Matrix issue type
- Missing access/credentials/environment path — blocker persists >1 execution cycle (mandatory escalation)

## Evidence
- Fix commit: `6c0f49906` — on `origin/main`
- QA findings JSON: `sessions/qa-forseti/artifacts/auto-site-audit/20260423-191501/findings-summary.json`
- Prior blocked outboxes: `sessions/dev-forseti/outbox/20260423-181501-qa-findings-forseti-life-1.md`, `sessions/dev-forseti/outbox/20260423-171501-qa-findings-forseti-life-1.md`

## ROI estimate
- ROI: 999
- Rationale: Public-facing page serving 500 to all visitors; fix is on origin/main; a single `git pull + drush cr` on production closes the QA BLOCK and unblocks release-m.
