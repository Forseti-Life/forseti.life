- Status: blocked
- Summary: QA run 20260423-011501 on forseti.life confirms the same 3 failures as 10+ prior cycles: roadmap detail pages PROJ-002, PROJ-008, and PROJ-011 return HTTP 404 when crawled via `/index.php/roadmap`. Root cause is fully diagnosed and code fix is committed (`789090d85` — graceful 200 response when `PROJECTS.md` is unreadable by www-data + absolute URLs in listing). Code fix is on `origin/main` (CEO pushed during prior escalation). Production is still returning 404 because `git pull` + `drush cr` have not been run on the production server (`/home/ubuntu/forseti.life`). This is a deploy infrastructure blocker, not a code problem.

## Product context
- Website: forseti.life
- Module: `forseti_content` (custom Drupal module) — `ForsetiPagesController.php`
- Feature: Roadmap detail pages (`/roadmap/PROJ-*`)
- Work item: Release `20260412-forseti-release-m`, QA BLOCK persisting 10+ cycles
- Fix commit: `789090d85` on `origin/main`

## Next actions
- **Board/human owner**: SSH to production and run:
  ```
  cd /home/ubuntu/forseti.life && git pull origin main
  cd sites/forseti && vendor/bin/drush cr
  ```
- After deploy: QA re-run should show 0 failures for PROJ-002, PROJ-008, PROJ-011
- **dev-infra (parallel)**: `chmod -R o+r /home/ubuntu/forseti.life/copilot-hq/dashboards/` on production to prevent recurrence when PROJECTS.md permissions drift again

## Blockers
- Production server (`/home/ubuntu/forseti.life`) needs `git pull origin main` + `drush cr` — dev-forseti has no SSH access to production
- GitHub Actions `deploy.yml` cannot run: secrets `REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY` are missing from GitHub repo settings
- Code fix is confirmed on `origin/main`; the only remaining step is production-side cache/code flush

## Needs from Supervisor
- SSH access to production OR human owner to manually run `git pull origin main && vendor/bin/drush cr` on `/home/ubuntu/forseti.life/sites/forseti`

## Decision needed
- Will the Board/human owner perform a manual SSH deploy (`git pull` + `drush cr`) to unblock immediately, OR will the GitHub Actions secrets be restored first so automated deploys can handle this and future releases?

## Recommendation
- **Option A (fastest, recommended)**: Human owner SSHs to production, runs `git pull origin main` + `vendor/bin/drush cr`. Resolves in under 2 minutes, closes 10+ QA BLOCK cycles, unblocks the forseti release immediately. No infrastructure changes needed.
- **Option B (sustainable, slower)**: Restore 3 GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) in GitHub repo settings → trigger `deploy.yml` → automated `git pull` + `drush cr` on production. Fixes the underlying automation gap but takes longer to set up.
- **Tradeoff**: Option A unblocks today; Option B prevents recurrence. Recommend Option A now + Option B in the same session.

## ROI estimate
- ROI: 99
- Rationale: Code is ready and on `origin/main`; one `drush cr` on production closes a 10+ cycle QA BLOCK and unblocks the full forseti release. Zero development work remains.
