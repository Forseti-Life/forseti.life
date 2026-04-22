# Board Escalation: forseti.life Production Outage (Homepage 500)

- Agent: ceo-copilot-2
- Item: 20260422-board-escalation-production-outage-homepage-500
- Escalated-at: 2026-04-22T18:39:00Z
- Priority: CRITICAL / ROI 999

## Situation

forseti.life homepage (`/`) is returning HTTP 500. The site has been down for all anonymous traffic since approximately 2026-04-22T10:00Z.

**Root cause (confirmed):** Duplicate `path: '/'` route registration in `forseti_safety_content.routing.yml` conflicted with the authoritative `forseti_content.routing.yml` route, causing Drupal's ControllerResolver to throw "controller not callable".

**Fix status:** Code fix committed as `9180c894a`, pushed to `origin/main`, and present on the production server via symlinks. Only a Drupal route cache rebuild (`drush cr`) is needed to restore service.

**Deploy blocker:** GitHub Actions `deploy.yml` SSH step is failing — `PRIVATE_KEY` secret is missing or invalid in `Forseti-Life/forseti.life` repo settings (failed run: 24789047622). No agent in this org has SSH access to `ubuntu@forseti.life`.

## Action required from Board (Keith)

**Step 1 — Restore service immediately (2 minutes):**
```bash
ssh ubuntu@forseti.life
cd /home/ubuntu/forseti.life/sites/forseti
vendor/bin/drush cr
```
Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` → must return `200`.

**Step 2 — Fix automated deploy (prevents recurrence):**
Restore `PRIVATE_KEY` secret in GitHub → `Forseti-Life/forseti.life` → Settings → Secrets → Actions.
Re-trigger `deploy.yml` to confirm the automated path works.

## Decision needed
- Will Keith execute `drush cr` via direct SSH, or is there another available access path for the CEO to use?
- Should the `PRIVATE_KEY` be restored now, or is there a different deploy strategy preferred?

## Recommendation
- SSH directly now (fastest path, 2 minutes to restore service).
- Then restore `PRIVATE_KEY` for automated future deploys.

## Rollback plan
If `drush cr` reveals a different error after the cache clears:
```bash
git revert 9180c894a  # on production
vendor/bin/drush cr
```
This restores prior state (500 returns, but no worse than current).

## Evidence
- dev-forseti outbox: `sessions/dev-forseti/outbox/20260422-fix-homepage-500-forseti.md`
- CEO outbox: `sessions/ceo-copilot-2/outbox/20260422-board-escalation-production-outage-homepage-500.md`
- Failed deploy run: https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622
- Fix commit: `9180c894a`
