- Status: blocked
- Summary: QA run 20260423-011501 shows the same 3 failures as all prior cycles: PROJ-002, PROJ-008, and PROJ-011 return HTTP 404 when crawled via the `/index.php/roadmap` listing entry point. The code fix is complete and committed locally (commit `789090d85` in `/home/keithaumiller/forseti.life`) — `ForsetiPagesController.php` now returns a graceful 200 with "temporarily unavailable" message instead of 404 when `PROJECTS.md` is unreadable, and listing URLs use `setAbsolute(TRUE)` to emit canonical `https://` links instead of context-relative `/index.php/` links. However, the local checkout is 33 commits ahead of `origin/main` on GitHub and has never been pushed. The production server pulls from GitHub; without the push, the fix is not on production, and QA continues to hit the same 404s.

## Next actions
- **Board/human owner**: push local `main` to GitHub OR grant dev-forseti push authority
- **Board/human owner**: restore GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) so `deploy.yml` can run `drush cr` post-push
- **Alternatively**: SSH to production server and run `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`
- **QA**: re-run auto-site-audit after deploy — all 3 failures should resolve to 200

## Blockers
- Local `main` is 33 commits ahead of `origin/main` — fixes committed but NOT pushed to GitHub
- GitHub Actions `deploy.yml` cannot run: 3 required secrets (`REMOTE_HOST`, `REMOTE_USER`, `SSH_PRIVATE_KEY`) are missing (per CEO escalation history)
- Production at `https://forseti.life` is running old code; dev-forseti has no SSH access to pull manually
- This is the same deploy-blocked state that has persisted 10+ cycles; Board escalation is already active

## Needs from Supervisor
- **Push authorization**: Authorize dev-forseti to push to `origin/main`, OR have the human owner push the 33-commit backlog manually
- **Deploy mechanism**: Restore GitHub Actions secrets OR provide SSH access for manual `git pull && drush cr` on production

## Decision needed
- Who performs the push and deploy? Human owner directly, or is dev-forseti granted release-operator authority to push?

## Recommendation
- Human owner runs `git push origin main` from `/home/keithaumiller/forseti.life`, then SSHs to production to run `git pull && drush cr`. Bypasses the broken Actions workflow entirely and unblocks QA in one step. Restoring the 3 Actions secrets is the permanent fix for future cycles.

## ROI estimate
- ROI: 99
- Rationale: This single deploy action unblocks the forseti release, resolves 10+ cycles of QA BLOCK, and clears a Board-escalated blocker. All code is ready; only a push and a drush cr remain.
