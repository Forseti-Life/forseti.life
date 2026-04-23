- Status: blocked
- Summary: QA run 20260423-021501 on forseti.life confirms the same 3 failures as 10+ prior cycles: roadmap detail pages PROJ-002, PROJ-008, and PROJ-011 return HTTP 404 when crawled via `/index.php/roadmap`. Root cause is fully diagnosed: `www-data` cannot read `PROJECTS.md` from the HQ path, and the controller throws `NotFoundHttpException` instead of graceful degradation. Code fix commit `789090d85` (graceful 200 + absolute URLs) is on `origin/main`. Production is still returning 404 because `git pull` + `drush cr` have not run on the production server. Per site.instructions.md, code is live via symlinks — `git pull` makes the fix active; `drush cr` flushes the page cache. This is a deploy infrastructure blocker; no further dev work is needed.

## Product context
- Website: forseti.life
- Module: `forseti_content` (`ForsetiPagesController.php`) — custom Drupal module
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
- **dev-infra (follow-up)**: `chmod -R o+r /home/ubuntu/forseti.life/copilot-hq/dashboards/` on production to prevent recurrence if file permissions drift again

## Blockers
- Production server needs `git pull origin main` + `drush cr` — dev-forseti has no SSH access to production
- GitHub Actions `deploy.yml` cannot run: secrets `REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY` are missing from the GitHub repo settings

## Needs from Supervisor
- SSH execution on the production server: `git pull origin main` + `vendor/bin/drush cr` from `/home/ubuntu/forseti.life/sites/forseti`

## Decision needed
- Will the Board/human owner perform a manual SSH deploy (`git pull` + `drush cr`) to unblock immediately, OR will the GitHub Actions secrets be restored so `deploy.yml` handles this and all future deploys automatically?

## Recommendation
- **Option A (recommended, fastest)**: Human owner SSHs to production, runs `git pull origin main` + `vendor/bin/drush cr`. Resolves in under 2 minutes, closes 10+ QA BLOCK cycles immediately. No infrastructure changes required to unblock.
- **Option B (sustainable, slower)**: Restore 3 GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) in GitHub repo settings → trigger `deploy.yml` → automated `git pull` + `drush cr` on production. Fixes the root automation gap.
- **Tradeoff**: Option A unblocks the release today; Option B prevents the same blocker from recurring in future releases. Recommend Option A now, Option B in the same session as a follow-up.

## ROI estimate
- ROI: 99
- Rationale: Code is ready and on `origin/main`; one `drush cr` on production closes a 10+ cycle QA BLOCK and unblocks the full forseti release. Zero development work remains — this is purely an operations action.
