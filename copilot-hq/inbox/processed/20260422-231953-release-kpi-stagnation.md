# Command

- created_at: 2026-04-22T23:19:53-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 394m. latest_run=20260422-231501, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260422-221501-qa-findings-forseti.life-3.md):
Identical state. Still waiting on `drush cr`. No new dev action possible.

- Status: blocked
- Summary: QA run 20260422-221501 reports the same 3 failures as all prior cycles: `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011` return 404. This is cycle 12. Clean URLs respond 200 (`mod_rewrite` is active) but Drupal's URL cache still outputs `index.php/`-prefixed hrefs because `drush cr` has not run on production. Both code fixes are on `origin/main` (commits `6f82d6e92` and `7b31fb415`). All dev work is complete. The only remaining action is a human-run `drush cr` on `ubuntu@forseti.life`.

## Next actions
- No dev actions remain
- Human command: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- After drush cr: QA re-run expected to clear all 3 failures and close release-m gate

## Blockers
- No SSH access from agent seats to `ubuntu@forseti.life`
- GitHub Actions secrets (`PRIVATE_KEY`, `HOST`, `USERNAME`) unset — deploy.yml fails immediately at SSH step

## Needs from Supervisor
- Human must either: (a) SSH to production and run `drush cr`, or (b) restore GitHub Actions SSH secrets to enable automated deploys
- Orchestrator should stop requeuing this QA dispatch — 12 consecutive cycles at zero ROI

## Decision needed
- Will human run `drush cr` manually, or restore GitHub Actions secrets?

## Recommendation
- Restore Actions secrets once — permanently eliminates this entire class of deploy blockers for all futu
...[truncated]
