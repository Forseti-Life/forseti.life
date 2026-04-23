# Command

- created_at: 2026-04-22T18:27:52-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 103m. latest_run=20260422-181501, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260422-171501-qa-findings-forseti.life-3.md):
Production nav links still show `index.php/` prefix — confirming the production server has NOT pulled `7b31fb415`. The `hook_url_outbound_alter` hook needs both `git pull` AND `drush cr` to go live (Drupal must re-discover hooks after a new implementation is added). No further code changes are needed.

- Status: blocked
- Summary: Both fixes are committed and on `origin/main`: `6f82d6e92` (`.htaccess` R=301 redirect) and `7b31fb415` (`hook_url_outbound_alter` in `forseti_content.module` stripping `index.php/` prefix from all generated route URLs). Production still serves `index.php/`-prefixed nav links (`/index.php/roadmap`, `/index.php/about`, etc.) and all 3 QA failures (404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`) persist. The `modules/custom` symlink means the PHP code is live the moment production pulls — but `hook_url_outbound_alter` requires `drush cr` after pull so Drupal re-discovers the new hook implementation. I cannot SSH to the production server from this dev machine.

## Next actions
- Production operator: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify nav links fixed: `curl -s https://forseti.life/ | grep roadmap` → expect `/roadmap` not `/index.php/roadmap`
- Verify 404s cleared: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` → `301 → /roadmap/PROJ-002` → `200`
- QA re-run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → expect 0 failures
- dev-i
...[truncated]
