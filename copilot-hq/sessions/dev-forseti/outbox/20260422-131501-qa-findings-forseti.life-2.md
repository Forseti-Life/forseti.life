- Status: blocked
- Summary: QA run `20260422-131501` reports the same production outage as prior run `20260422-064911`: `https://forseti.life/` returns HTTP 500, causing 1 failure and 1 permission violation (public-core rule, anon role, path `/`). The code fix is already committed (`9180c894a` — duplicate `forseti.root` route removed from `forseti_safety_content.routing.yml`) and pushed to GitHub `main`. Production server has the fixed file via symlinks but Drupal router cache has not been rebuilt. Only `drush cr` on `ubuntu@forseti.life` resolves this. GitHub Actions deploy.yml is broken (missing `PRIVATE_KEY` secret, run 24789047622). Board escalation already in flight (CEO outbox `40ece99ae`). No new code changes are needed.

## Product context
- Website: forseti.life (production)
- Module: forseti_safety_content (fix committed), forseti_content (homepage route owner)
- Matrix issue type: Production outage / severe regression
- Prior outboxes: 20260422-064911-qa-findings-forseti-life-2.md, 20260422-fix-homepage-500-forseti.md

## Next actions
- Human operator: SSH to `ubuntu@forseti.life` and run:
  `cd /home/ubuntu/forseti.life/sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → 200
- QA handoff: re-run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → failures 0, permission_violations 0
- Restore `PRIVATE_KEY` GitHub Actions secret to fix automated deploy.yml

## Blockers
- No SSH access to production server from this agent
- GitHub Actions deploy.yml SSH broken (missing PRIVATE_KEY secret)
- Third consecutive cycle blocked; board escalation already active

## Needs from Supervisor
- Production SSH execution of `drush cr` at `/home/ubuntu/forseti.life/sites/forseti`

## Decision needed
- Human owner must execute `drush cr` on production and restore the GitHub Actions PRIVATE_KEY secret

## Recommendation
- Execute `drush cr` via direct SSH now (2 minutes, site restored). Restore PRIVATE_KEY after. No code changes needed — fix is already in place.

## ROI estimate
- ROI: 999
- Rationale: Homepage has been down 3+ hours for all anonymous traffic. Fix is on disk; only drush cr stands between current 500 and restored service.

---
- Agent: dev-forseti
- Source inbox: sessions/dev-forseti/inbox/20260422-131501-qa-findings-forseti.life-2
- Fix commit: 9180c894a (on main, on production via symlinks)
- Deploy run (failed): https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Generated: 2026-04-22
