- Status: blocked
- Summary: Root cause of the homepage 500 was diagnosed, fixed in code, committed, and pushed to GitHub — but the fix has not taken effect on production because the deploy workflow (`deploy.yml`) failed with `can't connect without a private SSH key or password` (GitHub Actions run 24789047622). The fix removes a duplicate `path: '/'` route from `forseti_safety_content.routing.yml` that conflicts with `forseti_content.root`, causing Drupal's `ControllerResolver` to throw `InvalidArgumentException: The controller for URI "/" is not callable`. Fix commit `9180c894a` is on `main` and pushed. Production requires a manual `git pull --rebase origin main` + `drush cr` from the production server (`/home/ubuntu/forseti.life/sites/forseti`). This agent runs on the local dev machine and cannot SSH to the production server. The deploy workflow SSH credentials (`PRIVATE_KEY` secret) are missing or invalid in GitHub Actions. This is a hard blocker requiring human owner action.

## Next actions
- Human owner (Keith): SSH to `ubuntu@forseti.life`, run:
  ```
  cd /home/ubuntu/forseti.life
  git pull --rebase origin main
  cd sites/forseti
  vendor/bin/drush cr
  ```
- After cache rebuild: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` should return `200`
- CEO (if GitHub Actions key can be fixed): restore `PRIVATE_KEY` secret in GitHub → Settings → Secrets → `PRIVATE_KEY`, then re-run `deploy.yml`
- Once site is 200: re-run QA audit to close the findings: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`

## Blockers
- GitHub Actions deploy.yml is missing `PRIVATE_KEY` secret — SSH connection to production server fails with `can't connect without a private SSH key or password` (run 24789047622)
- This agent has no SSH access to `ubuntu@forseti.life` — cannot run `git pull` or `drush cr` on production

## Needs from Supervisor
- Production server access: someone must SSH to `ubuntu@forseti.life` and run `git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- OR: restore the `PRIVATE_KEY` GitHub Actions secret so deploy.yml can SSH to production and run the cache rebuild automatically

## ROI estimate
- ROI: 999
- Rationale: Production homepage is still down (500) for all anonymous users. Fix is committed and pushed; the remaining step is a 2-minute cache rebuild that only a human with production SSH access can execute. Every minute of delay is lost user traffic.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-fix-homepage-500-forseti
- Fix commit: `9180c894a` (pushed to GitHub main)
- Deploy run (failed): https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Generated: 2026-04-22
