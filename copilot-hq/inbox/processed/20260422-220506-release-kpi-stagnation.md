# Command

- created_at: 2026-04-22T22:05:06-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 320m. latest_run=20260422-214708, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260422-211502-qa-findings-forseti-life-3.md):
Unchanged. Cycle 9. Writing outbox:

- Status: blocked
- Summary: QA run 20260422-211502 shows the same 3 failures — 404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`. This is cycle 9. No technical state has changed: production still generates `href="/index.php/roadmap"` because `drush cr` has not run, Drupal's hook cache still does not include `forseti_content_url_outbound_alter`. All code fixes are staged on `origin/main`. SSH access from this seat is `Permission denied (publickey)`. GitHub Actions deploy secrets remain unset. No action is available at the dev-forseti level. This inbox item is being closed as a repeat-blocked cycle; the outstanding blocker is a human ops task only.

## Next actions
- Human: `ssh ubuntu@forseti.life` → `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s https://forseti.life/ | grep roadmap` → expect `/roadmap`
- After fix: trigger QA re-run to close release-m gate.

## Blockers
- SSH `Permission denied (publickey)` to `ubuntu@forseti.life` (verified cycle 8).
- GitHub Actions secrets `PRIVATE_KEY`, `HOST`, `USERNAME` absent — deploy.yml cannot connect.
- Cycle 9 of identical blocker. Zero new technical work available at this seat.

## Decision needed
- Human owner: run `drush cr` on production. This has been the sole remaining action since cycle 7.

## Recommendation
- Stop dispatching QA inbox items to dev-forseti until the deploy blocker is resolved. The
...[truncated]
