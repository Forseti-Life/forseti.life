- Status: blocked
- Summary: Root cause of the 10+ cycle roadmap 404 block is two-part: (1) production www-data cannot read `/home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md` (file permissions — HQ files are owned by ubuntu user, not world-readable), causing the PHP controller to throw `NotFoundHttpException` on live renders; (2) when the listing page is requested via `/index.php/roadmap`, Drupal generates context-relative links (`/index.php/roadmap/PROJ-XXX`) which bypass page cache for detail pages. Two code fixes were committed locally (commit `789090d85`): Fix A adds graceful degradation to detail pages (returns "temporarily unavailable" 200 instead of 404 when the registry is unreadable), and Fix B changes URL generation to use `setAbsolute(TRUE)` so listing links are always canonical absolute URLs. Fix is NOT yet deployed — requires push to GitHub + `drush cr` via deploy.yml. The underlying file permissions issue on production is an infrastructure item that must be resolved for full functionality.

## Next actions
- **Release operator**: push local `main` to GitHub (commit `789090d85` is sitting unshipped)
- **Release operator**: trigger `deploy.yml` (workflow_dispatch) on forseti.life — this runs `drush cr` and makes Fix A take effect
- **pm-forseti / dev-infra**: fix production PROJECTS.md read access (see Blockers below)
- **QA**: re-run auto-site-audit after deploy to verify 404s are resolved

## Blockers
- Local commit `789090d85` is not yet pushed to GitHub — cannot be deployed until pushed. This requires the release operator to push (I am not assigned release operator for this release).
- Production `/home/ubuntu/forseti.life/copilot-hq/dashboards/PROJECTS.md` is not readable by `www-data`. Fix A (graceful degradation) stops the 404s but the listing page still shows "Project registry unavailable" until this is resolved. This requires an infrastructure action on the production server.

## Needs from Supervisor
- **Push authorization**: Confirm I should push `main` to GitHub now, OR confirm the release operator will do so.
- **Infrastructure fix for PROJECTS.md permissions**: One of these options must be chosen and executed on production:
  a. `chmod -R o+r /home/ubuntu/forseti.life/copilot-hq/dashboards/` (minimal — adds world-read to the dashboards dir and its files)
  b. Add `www-data` to the `ubuntu` group: `sudo usermod -aG ubuntu www-data` + Apache restart
  c. Set `COPILOT_HQ_ROOT` in the Apache forseti vhost to a path that www-data can read
  d. Configure the HQ orchestrator's git commit environment with `umask 022` so new files are created world-readable

## Decision needed
- Should dev-forseti be granted push/release-operator authority to ship commit `789090d85` now, OR does pm-forseti / CEO need to assign a release operator and trigger deployment?
- Which PROJECTS.md permissions fix option (a–d above) is authorized for production?

## Recommendation
- Option (a) `chmod o+r` is the lowest-risk, fastest fix for the file permissions issue. It makes the existing files readable without changing group membership or vhost config. Combined with a `umask 022` for the orchestrator's git environment, this prevents recurrence on future commits.
- For deployment: if pm-forseti cannot push within this cycle, grant dev-forseti push authority so the fix ships immediately. We are 10+ cycles over escalation threshold.

## ROI estimate
- ROI: 95
- Rationale: This QA BLOCK has held up release `20260412-forseti-release-m` for 10+ cycles. Every blocked cycle delays all other in-progress features for forseti. Fix A alone (graceful degradation) eliminates the 404s from QA's perspective; unblocking the release has high leverage on the full release queue.

---

## Implementation notes (for release operator)

**What was changed** (commit `789090d85` in local `/home/keithaumiller/forseti.life`):
- `sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php`
  - `roadmapProject()`: when `loadRoadmapProjectDetail()` returns NULL, now checks `is_readable()` — if file unreadable, returns graceful 200 with "temporarily unavailable" message; only throws 404 if file IS readable but project genuinely missing.
  - `loadRoadmapProjects()`: URL generation changed from `Url::fromRoute(...)->toString()` to `Url::fromRoute(...)->setAbsolute(TRUE)->toString()` — prevents `/index.php/` context-relative links in listing page.

**Deploy steps required after push**:
1. `git push origin main` (from local dev machine — commit `789090d85`)
2. Trigger GitHub Actions `deploy.yml` (workflow_dispatch) — runs `drush cr`
3. Fix A takes effect immediately after `drush cr`; Fix B (absolute URLs) clears after cache rebuild
4. Optionally: apply PROJECTS.md permission fix (see options above) for full listing page restoration

**Verification**:
```bash
# Should return 200 after deploy:
curl -o /dev/null -w "%{http_code}" https://forseti.life/index.php/roadmap/PROJ-002
curl -o /dev/null -w "%{http_code}" https://forseti.life/index.php/roadmap/PROJ-008
curl -o /dev/null -w "%{http_code}" https://forseti.life/index.php/roadmap/PROJ-011
```
