- Status: blocked
- Summary: dev-forseti has been blocked 3 consecutive cycles on the same root cause: GitHub Actions SSH deploy is broken (missing `PRIVATE_KEY` secret, run 24789047622), so code fixes cannot reach production automatically. The specific fix in this escalation is commit `6f82d6e92` (4-line `.htaccess` mod_rewrite redirect stripping `index.php/` prefix), which resolves 3 QA failures — 404s on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, and `/PROJ-011`. The fix is on `origin/main`. Production needs `git pull --rebase origin main` at `/home/ubuntu/forseti.life/`; no `drush cr` required. Good news: the homepage 500 (separate earlier blocker) is now resolved — Keith's `drush cr` worked. Board inbox item updated to reflect current single remaining ask (`babeb8b3a`).

## Next actions
- Board (Keith): `ssh ubuntu@forseti.life`, then `cd /home/ubuntu/forseti.life && git pull --rebase origin main`
- Verify: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` must return `301`
- After Board confirms pull: dispatch qa-forseti to re-run full site audit (`ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`) to close out the 3 QA failures
- Board: Restore `PRIVATE_KEY` GitHub Actions secret to re-enable automated deploys and permanently unblock this class of failure

## Blockers
- GitHub Actions `PRIVATE_KEY` secret missing — all automated production deploys are failing
- Production has not yet pulled commit `6f82d6e92` — roadmap 404s remain live

## Needs from Board
- Execute `git pull --rebase origin main` at `/home/ubuntu/forseti.life/` on the production server
- Restore `PRIVATE_KEY` in GitHub Actions secrets (`Forseti-Life/forseti.life` → Settings → Secrets → Actions)

## Decision needed
- Will Keith execute the production `git pull` now, or is there another deploy path available for the CEO to use?

## Recommendation
- Direct SSH `git pull` is the fastest path (2 minutes, zero risk for `.htaccess`-only change). Restore the PRIVATE_KEY secret in the same session to permanently fix the deploy pipeline.

## ROI estimate
- ROI: 40
- Rationale: Live user-facing 404s on roadmap links affect product credibility. Fix is ready and production-safe; only a `git pull` stands between current state and zero QA failures on this cycle. Fixing the deploy pipeline has high recurring value — every future code change is blocked without it.
