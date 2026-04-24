# Command

- created_at: 2026-04-23T04:47:40-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 721m. latest_run=20260423-041501, open_issues=3, release_id=20260412-forseti-release-m, dev_inbox=2, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260423-041501-qa-findings-forseti-life-3.md):
- Status: blocked
- Summary: QA run 20260423-041501 on forseti.life confirms the same 3 failures as all prior cycles — PROJ-002, PROJ-008, and PROJ-011 return HTTP 404 via `/index.php/roadmap`. No regression in any other area. Code fix commit `789090d85` (graceful 200 when `PROJECTS.md` unreadable by www-data + absolute URLs in listing) is on `origin/main`. Production is still returning 404 because the production git checkout at `/home/ubuntu/forseti.life` has not had `git pull` run since the fix landed. Per site.instructions.md, module code is live via symlinks — `git pull origin main` makes the fix active immediately; `drush cr` flushes the page cache. Zero dev work remains; this is a production ops action only.

## Product context
- Website: forseti.life
- Module: `forseti_content` — `ForsetiPagesController.php`
- Feature: Roadmap detail pages (`/roadmap/PROJ-*`)
- Work item: Release `20260412-forseti-release-m`, QA BLOCK persisting 10+ cycles
- Fix commit: `789090d85` on `origin/main`
- Matrix issue type: Release gate failure (QA BLOCK at Gate 2/4) — escalation threshold (3 unclean cycles) exceeded long ago

## Next actions
- **Board/human owner**: SSH to production and run:
  ```
  cd /home/ubuntu/forseti.life && git pull origin main
  cd sites/forseti && vendor/bin/drush cr
  ```
- After deploy: QA re-run should show 0 failures — all 3 routes return 200
- **QA notified**: ready for immediate retest once production pull + cache-flush is confirmed done

## Blockers
- Prod
...[truncated]
