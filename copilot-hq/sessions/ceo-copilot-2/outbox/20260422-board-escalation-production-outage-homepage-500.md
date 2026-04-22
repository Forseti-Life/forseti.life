- Status: blocked
- Summary: forseti.life homepage (/) is returning HTTP 500 as of this execution. Root cause is fully diagnosed and fixed in code: duplicate `path: '/'` routes in `forseti_content` and `forseti_safety_content` were causing Drupal's ControllerResolver to throw "controller not callable". Fix committed as `9180c894a` (removed duplicate route from `forseti_safety_content.routing.yml`) and pushed to `origin/main`. The fix file is already live on the production server via symlinks — only a Drupal route cache rebuild (`drush cr`) is required to restore service. GitHub Actions deploy.yml is broken (missing `PRIVATE_KEY` SSH secret, failed run 24789047622). No agent in this org has SSH credentials for `ubuntu@forseti.life`. Only the Board (Keith) can execute the production cache rebuild.

## Next actions
- Board: SSH to `ubuntu@forseti.life` and run:
  ```
  cd /home/ubuntu/forseti.life/sites/forseti
  vendor/bin/drush cr
  ```
  Then verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → must return `200`.
- Board: Restore `PRIVATE_KEY` GitHub Actions secret in `Forseti-Life/forseti.life` repository settings so automated deploys work going forward.
- CEO (after 200 confirmed): dispatch qa-forseti to re-run full site audit (`ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`).

## Blockers
- No agent has SSH access to `ubuntu@forseti.life` — `drush cr` cannot be executed programmatically.
- GitHub Actions `PRIVATE_KEY` secret missing/invalid — automated deploy path broken (run 24789047622).

## Needs from Board
- Execute `vendor/bin/drush cr` at `/home/ubuntu/forseti.life/sites/forseti` on the production server.
- Restore `PRIVATE_KEY` secret in GitHub Actions to re-enable automated deployments.

## Decision needed
- Will Keith execute the `drush cr` via direct SSH, or should we explore an alternative automated path (e.g., webhook trigger, secondary deploy key)?

## Recommendation
- Fastest path (do now): Keith SSHes directly to `ubuntu@forseti.life` and runs `vendor/bin/drush cr`. Site is back up in under 2 minutes — code is already present.
- Follow-up (same session): restore `PRIVATE_KEY` in GitHub repo Settings → Secrets → Actions → `PRIVATE_KEY`. Re-trigger `deploy.yml` to confirm the automated path is working. This prevents future deploys from silently failing to execute `drush cr`.
- Rollback plan if needed: `git revert 9180c894a` on production + `drush cr` restores prior state (500 returns, but no worse than current).

## ROI estimate
- ROI: 999
- Rationale: Homepage is down for all anonymous traffic on forseti.life. The fix is a single shell command away. Every minute of delay is lost public traffic and credibility for the site. This is the highest-priority item in the current queue.
