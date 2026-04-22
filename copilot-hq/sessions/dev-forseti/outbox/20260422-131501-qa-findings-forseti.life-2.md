- Status: blocked
- Summary: QA run `20260422-131501` confirms `https://forseti.life/` returns HTTP 500 for all anonymous traffic (1 failure, 1 permission violation, path `/`, rule `public-core`). Code fix is committed (`9180c894a` — removed duplicate `forseti.root` route from `forseti_safety_content.routing.yml`) and present on production via symlinks. Drupal router cache has not been rebuilt; only `drush cr` on `ubuntu@forseti.life` will restore service. GitHub Actions deploy.yml is broken (missing `PRIVATE_KEY` secret, run 24789047622 failed). This is the 4th consecutive blocked cycle on this issue.

## Product context
- Website: forseti.life (production)
- Module: forseti_safety_content (fix committed), forseti_content (authoritative homepage route owner)
- Release: 20260412-forseti-release-m
- Matrix issue type: Production outage / severe regression
- Prior outboxes: 20260422-064911-qa-findings-forseti-life-2.md, 20260422-fix-homepage-500-forseti.md

## Next actions
- Human operator: `ssh ubuntu@forseti.life`
- `cd /home/ubuntu/forseti.life/sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → 200
- QA retest: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → failures 0, permission_violations 0
- After restore: fix `PRIVATE_KEY` in GitHub → Settings → Secrets and variables → Actions

## Blockers
- No SSH access to production server from this agent (running on keithaumiller local machine, not ubuntu@forseti.life)
- GitHub Actions deploy.yml SSH is broken (missing PRIVATE_KEY secret)
- 4th consecutive cycle blocked; board escalation active

## Needs from Supervisor
- Human owner must SSH to `ubuntu@forseti.life` and run `drush cr` at `/home/ubuntu/forseti.life/sites/forseti`
- Human owner must restore `PRIVATE_KEY` GitHub Actions secret to re-enable automated deploy.yml for future cache rebuilds

## Decision needed
- Human owner authorizes and executes `drush cr` on production to rebuild Drupal router cache and restore homepage service

## Recommendation
- Execute `drush cr` via direct SSH immediately — approximately 2 minutes of work, instant site restoration. No code changes needed; fix `9180c894a` is confirmed correct and already on disk via symlinks. Restoring PRIVATE_KEY is the secondary action to prevent recurrence of the deploy blocker.
- Tradeoff: delaying costs ongoing homepage downtime for all anonymous visitors. There is no safe alternative to human SSH execution of `drush cr` from this agent's environment.

## ROI estimate
- ROI: 999
- Rationale: Homepage has been down 4+ hours for all anonymous traffic. Fix is on disk, verified correct. Only `drush cr` (2 minutes of human effort) separates the current 500 from full site restoration.

---
- Agent: dev-forseti
- Source inbox: sessions/dev-forseti/inbox/20260422-131501-qa-findings-forseti.life-2
- Fix commit: 9180c894a (on main, on production via symlinks)
- Deploy run (failed): https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Generated: 2026-04-22T15:28:25-04:00
