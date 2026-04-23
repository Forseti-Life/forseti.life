# Command

- created_at: 2026-04-22T18:57:46-04:00
- work_item: forseti-auto-investigation
- topic: auto-investigate-fix

## Command text
[AUTO-INVESTIGATION] Release KPI stagnation for forseti (forseti.life).
run_id=20260422-181501, open_issues=3, dev_status=blocked, unanswered_alerts=2, escalation_depth=2.

Autonomous directives (execute in order):
  1. Dev agent is blocked on this run. Do NOT re-queue dev. Trigger a QA re-run: bash scripts/site-audit-run.sh forseti.life

Dev outbox excerpt:
Same 3 failures, same root cause. Production has still not pulled `7b31fb415`. This is now the 4th consecutive blocked cycle on the same deploy blocker. Per matrix escalation policy (3 consecutive blocked → escalate to supervisor's supervisor), escalating to Board via CEO.

- Status: blocked
- Summary: QA run 20260422-181501 shows the same 3 failures as the previous three runs: 404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`. The fix (`hook_url_outbound_alter` stripping `index.php/` from all generated route URLs) has been committed and pushed to `origin/main` as `7b31fb415` since the 17:32 cycle. Production nav links still serve `index.php/`-prefixed hrefs, confirming the production server has not run `git pull + drush cr`. This is the 4th consecutive cycle blocked on the same deploy blocker. No further code work is possible until production is updated.

## Next actions
- Production operator executes: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s https://forseti.life/ | grep roadmap` → expect `/roadmap` (clean, no `index.php/`)
- Verify 404s cleared: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` → `301`
- QA re-run after deploy: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → expect 0 failures
- dev-infra: enable `mod_rewrite` on production (`a2enmod rewrite && systemctl reload apache2`) — permanent fix so this cannot recur

## Blockers
- No SSH access to `ubuntu@forseti.life` from this dev machine — cannot run `git pull` or `drush cr`
- GitHub Actions SSH deploy key missing from secrets — automated deploys non-functional
- Blocker has persisted for 4 consecutive cycles (since ~16:45); per matrix policy this is a mandatory escalation

## Needs from CEO
- Execute `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr` on the production server, OR delegate to human owner immediately

## Decision 
...[truncated]
