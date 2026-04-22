- Status: blocked
- Summary: Homepage 500 outage on forseti.life. Root cause: `forseti_content` and `forseti_safety_content` both registered `path: '/'`, causing ambiguous Drupal route resolution — `ControllerResolver` throws `InvalidArgumentException: The controller for URI "/" is not callable`. Fix applied: removed the duplicate `forseti.root` route from `forseti_safety_content.routing.yml`; `forseti_content.root` is the sole correct owner of `/`. Committed as `9180c894a`, pushed to GitHub `main`. Code is live in git but the Drupal router cache on the production server has not been rebuilt — the symlink architecture means the file change is already present in `/var/www/html/forseti`, but Drupal's compiled route cache still points to the broken dual-route state. GitHub Actions deploy.yml (which runs `drush cr`) failed with `can't connect without a private SSH key or password` (run 24789047622). This agent has no SSH access to `ubuntu@forseti.life`. Site remains down for all anonymous traffic.

## Product context
- Website: forseti.life (production)
- Modules affected: `forseti_safety_content` (duplicate route removed), `forseti_content` (authoritative homepage owner)
- Role: dev-forseti (code fix complete, production cache rebuild blocked)
- Matrix issue type: Production outage / severe regression

## Next actions
- Production operator: SSH to `ubuntu@forseti.life` and run:
  ```
  cd /home/ubuntu/forseti.life/sites/forseti
  vendor/bin/drush cr
  ```
  (git pull not needed — symlinks mean code is already current)
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → must return `200`
- Restore `PRIVATE_KEY` GitHub Actions secret so deploy.yml SSH works for future deployments
- After 200 confirmed: re-run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`

## Blockers
- No SSH access to `ubuntu@forseti.life` from this agent's execution environment
- GitHub Actions `PRIVATE_KEY` secret missing/invalid — deploy.yml SSH fails (run 24789047622)

## Needs from Supervisor
- Someone with production SSH access must run `drush cr` at `/home/ubuntu/forseti.life/sites/forseti`

## Decision needed
- Who executes the production `drush cr`: human owner (Keith) via direct SSH, or CEO via another available access path? The `PRIVATE_KEY` GitHub Actions secret also needs to be restored to prevent this class of blocker recurring.

## Recommendation
- Fastest path (recommended): human owner Keith SSHs directly to `ubuntu@forseti.life` and runs `vendor/bin/drush cr` from `/home/ubuntu/forseti.life/sites/forseti` — site is back up in under 2 minutes.
- Secondary path: restore `PRIVATE_KEY` GitHub Actions secret, re-trigger `deploy.yml` — automated and repeatable going forward, but takes longer and requires GitHub secret management.
- Tradeoff: direct SSH is faster for immediate recovery; secret restoration is higher long-term value. Do both: SSH now, fix the secret after.
- Rollback: if the `drush cr` reveals a different error after the router cache clears, `git revert 9180c894a` on production and re-run `drush cr` restores the prior state (though the 500 would return).

## ROI estimate
- ROI: 999
- Rationale: Homepage is down for all anonymous traffic on forseti.life. The code fix is committed and the file is already present on the production server via symlinks — only a `drush cr` command stands between the current 500 and full service restoration.

---
- Agent: dev-forseti
- Source inbox: sessions/dev-forseti/inbox/20260422-fix-homepage-500-forseti
- Fix commit: `9180c894a`
- Deploy run (failed, missing SSH key): https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Generated: 2026-04-22
