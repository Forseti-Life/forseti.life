- Status: blocked
- Summary: QA run 20260423-041501 on forseti.life confirms the same 3 failures as all prior cycles — PROJ-002, PROJ-008, and PROJ-011 return HTTP 404 via `/index.php/roadmap`. No regression in any other area; all other routes pass. Code fix `789090d85` (graceful 200 when `PROJECTS.md` unreadable + absolute URLs in listing) is on `origin/main`. Production is still running old code because the production git checkout at `/home/ubuntu/forseti.life` has not had `git pull` run since the fix landed. Per site.instructions.md, module code is live via symlinks — the fix takes effect the moment `git pull origin main` is run on the production server, followed by `drush cr` to flush page cache. Zero dev work remains.

## Product context
- Website: forseti.life
- Module: `forseti_content` — `ForsetiPagesController.php`
- Feature: Roadmap detail pages (`/roadmap/PROJ-*`)
- Work item: Release `20260412-forseti-release-m`, QA BLOCK persisting 10+ cycles
- Fix commit: `789090d85` on `origin/main`

## Next actions
- **Board/human owner**: SSH to production and run:
  ```
  cd /home/ubuntu/forseti.life && git pull origin main
  cd sites/forseti && vendor/bin/drush cr
  ```
- After deploy: QA re-run should show 0 failures — all 3 routes return 200
- **QA notified**: ready for immediate retest once production pull+cache-flush is done

## Blockers
- Production server needs `git pull origin main` + `drush cr` — dev-forseti has no SSH access to production
- GitHub Actions `deploy.yml` remains broken: secrets `REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY` missing from GitHub repo settings

## Needs from Supervisor
- SSH execution on the production server to run `git pull origin main` + `vendor/bin/drush cr`

## Decision needed
- Will the Board/human owner SSH to production for a manual pull+cache-flush now, OR will GitHub Actions secrets be restored so `deploy.yml` handles this automatically?

## Recommendation
- **Option A (recommended, fastest)**: Human owner SSHs to production, runs `git pull origin main` + `vendor/bin/drush cr` from `/home/ubuntu/forseti.life/sites/forseti`. Resolves in under 2 minutes, closes 10+ QA BLOCK cycles, unblocks the forseti release immediately.
- **Option B (sustainable)**: Restore 3 GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) in GitHub repo settings → automated `git pull` + `drush cr` on future pushes. Prevents recurrence.
- **Tradeoff**: Option A unblocks today with zero infrastructure work. Option B prevents this class of blocker in all future releases. Recommend Option A now + Option B as a follow-up in the same session.

## ROI estimate
- ROI: 99
- Rationale: Code is on `origin/main`; one `drush cr` on production ends a 10+ cycle QA BLOCK and unblocks the full forseti release. No development work remains outstanding.
