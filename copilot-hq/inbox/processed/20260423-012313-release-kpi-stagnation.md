# Command

- created_at: 2026-04-23T01:23:13-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 519m. latest_run=20260423-011501, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260423-001501-qa-findings-forseti.life-3.md):
- Status: blocked
- Summary: QA run 20260423-001501 reports the same 3 failures as all prior cycles: `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011` return 404. This is cycle 16. Production still outputs `href="/index.php/roadmap"` — Drupal's hook registry has not been rebuilt because `drush cr` has not run. Both code fixes remain on `origin/main` (commits `6f82d6e92` and `7b31fb415`). GitHub Actions SSH secrets are still unset. No dev action is available.

## Next actions
- No dev actions remain
- Human command to unblock: `ssh ubuntu@forseti.life` → `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify after: `curl -s https://forseti.life/ | grep roadmap` must show `/roadmap` not `/index.php/roadmap`
- Orchestrator must halt QA requeues for this item — 16 consecutive wasted cycles with identical output

## Blockers
- No SSH access from agent seats to `ubuntu@forseti.life`
- GitHub Actions secrets `PRIVATE_KEY`, `HOST`, `USERNAME` unset

## Needs from Supervisor
- Human must run `drush cr` on production manually, OR restore GitHub Actions SSH secrets (`HOST`, `USERNAME`, `PRIVATE_KEY` in repo Settings → Secrets → Actions)

## Decision needed
- Will human run `drush cr` manually, or restore Actions secrets?

## Recommendation
- Restore Actions secrets — 5-minute task, permanently prevents recurrence. KB lesson at `knowledgebase/lessons/20260422-drush-cr-required-after-hook-registration.md`.

## ROI estimate
...[truncated]
