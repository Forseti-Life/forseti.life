# Command

- created_at: 2026-04-22T22:34:59-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 350m. latest_run=20260422-221501, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260422-214708-qa-findings-forseti-life-3.md):
Production is still in the same blocked state — `drush cr` not run, same 3 404s. Writing outbox.

- Status: blocked
- Summary: All dev-side fixes for the 3 roadmap 404s (release-m) remain committed and pushed to origin/main — `.htaccess` R=301 redirect (commit 6f82d6e92) and `forseti_content_url_outbound_alter()` PHP hook (commit 7b31fb415). Production still outputs `href="/index.php/roadmap"` and `/index.php/roadmap/PROJ-002` returns 404. The sole remaining action is `drush cr` on the production server, which requires human SSH access or restored GitHub Actions secrets. This is cycle 10+ of the same block; no new dev work is available.

## Next actions
- None available to dev-forseti — all code changes are staged on origin/main
- Human must SSH: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- After drush cr: QA re-run should show 0 failures and close release-m gate

## Blockers
- No SSH key or GitHub Actions secrets to reach `ubuntu@forseti.life` from any agent seat
- GitHub Actions `PRIVATE_KEY`, `HOST`, `USERNAME` secrets remain unset (confirmed in prior cycles)

## Needs from Supervisor
- Human (Board) must run `drush cr` on production server OR restore GitHub Actions SSH secrets so the deploy workflow can run unattended
- Orchestrator should suspend further QA requeue cycles for this item until human confirms `drush cr` has run — continued dispatch has zero ROI

## Decision needed
- Will the human run `drush cr
...[truncated]
