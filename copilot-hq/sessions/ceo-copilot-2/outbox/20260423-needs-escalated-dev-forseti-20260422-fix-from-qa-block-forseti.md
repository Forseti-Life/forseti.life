- Status: blocked
- Summary: CEO pushed dev-forseti's fix commit `789090d85` to GitHub (`origin/main`) directly — the commit adds graceful 200 degradation on roadmap detail pages when `PROJECTS.md` is unreadable by www-data, replacing the persistent `NotFoundHttpException` 404. The push succeeded (`7b31fb415..0e87621ba`). However, deployment to production still requires `drush cr` on the production server — the same infrastructure blocker as prior cycles. The GitHub Actions `deploy.yml` cannot trigger because secrets `PRIVATE_KEY`, `HOST`, `USERNAME` remain missing. Production must still run `git pull --rebase origin main && drush cr` manually, OR the Actions secrets must be restored.

## Next actions
- Board/Keith: SSH to production and run `git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- OR: restore GitHub Actions secrets `PRIVATE_KEY`, `HOST`, `USERNAME` so deploy.yml can automate future deploys
- Infrastructure: fix PROJECTS.md permissions on production: `chmod -R o+r /home/ubuntu/forseti.life/copilot-hq/dashboards/`
- After deploy + drush cr: re-enable QA requeue for forseti-release-m

## Blockers
- `deploy.yml` still cannot run — GitHub Actions secrets `PRIVATE_KEY`, `HOST`, `USERNAME` all missing from `Forseti-Life/forseti.life`
- Production not yet updated — manual `drush cr` still required
- Production file permissions: `www-data` cannot read `/home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md`

## Needs from Board
1. Run `git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr` on production
2. OR restore 3 GitHub Actions secrets for automated future deploys
3. Run `chmod -R o+r /home/ubuntu/forseti.life/copilot-hq/dashboards/` to fix PROJECTS.md permissions

## Decision needed
- Will Board do manual deploy + chmod now, or restore Actions secrets for permanent fix?

## Recommendation
- Manual deploy first (unblocks this release today), then restore Actions secrets (prevents future blocked cycles). chmod on dashboards/ is also required to make the listing page functional once deployed.

## ROI estimate
- ROI: 950
- Rationale: Fix is now on origin/main; only a 3-command deploy stands between current state and QA PASS. The production file permissions issue is a fast follow that makes the full listing functional.
