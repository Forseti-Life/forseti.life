# Command

- created_at: 2026-04-22T12:32:41-04:00
- work_item: forseti
- topic: release-kpi-stagnation
- pm: pm-forseti

## Command text
Release KPI stagnation investigation for forseti (forseti.life). No KPI movement for 311m. latest_run=20260422-121501, open_issues=2, release_id=20260412-forseti-release-m, dev_inbox=4, findings_items=1, dev_latest_status=blocked

Dev agent outbox (20260422-fix-homepage-500-forseti.md):
---

- Status: blocked
- Summary: Root cause isolated, fix committed (`9180c894a`) and pushed to GitHub. Two modules (`forseti_content` and `forseti_safety_content`) both registered `path: '/'` — the duplicate `forseti.root` route in `forseti_safety_content.routing.yml` was removed. The deploy workflow failed (run [24789047622](https://github.com/Forseti-Life/forseti.life/actions/runs/24789047622)) — missing `PRIVATE_KEY` SSH secret. Production still returns 500. A human with SSH access must run `git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr` on `ubuntu@forseti.life` to complete the fix.

## Next actions
- **Human owner (Keith)**: SSH to `ubuntu@forseti.life`:
  ```
  cd /home/ubuntu/forseti.life
  git pull --rebase origin main
  cd sites/forseti
  vendor/bin/drush cr
  ```
- OR restore the `PRIVATE_KEY` GitHub Actions secret → re-trigger `deploy.yml`
- After cache rebuild: verify `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/` returns `200`

## Blockers
- GitHub Actions `PRIVATE_KEY` secret missing/invalid — deploy SSH fails
- No SSH access from this local machine to `ubuntu@forseti.life`

## Needs from Supervisor
- Production SSH execution of `git pull` + `drush cr` on `ubuntu@forseti.life`

## ROI estimate
- ROI: 999
- Rationale: Homepage down for all anonymous users. Fix is pushed; only a 2-minute manual cache rebuild on production stands between current 500 and restored service.

---
- Agent: dev-forseti
- Source inbox: /home/kei
...[truncated]
