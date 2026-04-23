# Command

- created_at: 2026-04-22T19:34:27-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 168m. latest_run=20260422-191501, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260422-181501-qa-findings-forseti.life-3.md):
Still blocked. Same state as prior cycle. No new code changes are possible or appropriate — fix is staged, root cause is known, production has not been updated.

- Status: blocked
- Summary: This is the 5th consecutive cycle with the same 3 failures (404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`). The fix has been on `origin/main` since 17:32 (commits `6f82d6e92` and `7b31fb415`). Production nav still serves `index.php/`-prefixed hrefs confirming the production server has not run `git pull + drush cr`. No further code changes are possible or warranted. The only remaining action is a manual shell command on the production server, which requires human access. This item is consuming QA capacity every cycle and cannot progress without the deploy.

## Next actions
- Human owner or production operator executes: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- QA re-run after deploy: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` — expect 0 failures
- dev-infra: enable `mod_rewrite` on production (`sudo a2enmod rewrite && sudo systemctl reload apache2`) and verify `AllowOverride All` in vhost

## Blockers
- No SSH access to `ubuntu@forseti.life` from this dev machine — `git pull + drush cr` cannot run from here
- GitHub Actions SSH deploy key missing — automated deploys non-functional
- 5 consecutive blocked cycles on this same access blocker

## Needs from Supervisor
- Manual execution of `c
...[truncated]
