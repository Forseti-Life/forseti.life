- Status: blocked
- Summary: QA run 20260423-021501 confirms the same 3 failures as prior cycles: PROJ-002, PROJ-008, and PROJ-011 return HTTP 404 via `/index.php/roadmap`. Code fix commit `789090d85` is now on `origin/main` (CEO pushed it during escalation). However, production is still returning 404 — the fix requires either OPcache revalidation or `drush cr` on the production server (`/home/ubuntu/forseti.life/sites/forseti`) to take effect. This is now purely a production server action: `git pull origin main` (to sync the checkout) followed by `vendor/bin/drush cr`.

## Next actions
- **Board/human owner**: SSH to production and run:
  ```
  cd /home/ubuntu/forseti.life && git pull origin main
  cd sites/forseti && vendor/bin/drush cr
  ```
- **QA**: re-run auto-site-audit immediately after — all 3 failures should resolve to 200

## Blockers
- Production server needs `git pull` + `drush cr` — dev-forseti has no SSH access to production
- GitHub Actions `deploy.yml` remains blocked: 3 secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) missing
- Code fix is on `origin/main`; only the production-side cache flush is missing

## Needs from Supervisor
- **Production server access**: Human owner must SSH to production and run `git pull origin main && vendor/bin/drush cr` from `/home/ubuntu/forseti.life/sites/forseti`

## Decision needed
- Will the human owner SSH to production to run `drush cr`, OR will the 3 GitHub Actions secrets be restored so `deploy.yml` can handle this automatically?

## Recommendation
- Manual SSH and `drush cr` is the fastest unblock: 1 minute, resolves 10+ cycles of QA BLOCK immediately. Restore GitHub Actions secrets in parallel to prevent recurrence.

## ROI estimate
- ROI: 99
- Rationale: Code is live on GitHub; one `drush cr` on production closes this 10+ cycle QA BLOCK and unblocks the full forseti release.
