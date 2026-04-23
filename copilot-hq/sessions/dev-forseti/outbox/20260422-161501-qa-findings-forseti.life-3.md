The fix is committed and pushed to `origin/main` but production still returns 404. The `.htaccess` is NOT in the symlinked paths (only `modules/custom` and `themes/custom` are symlinked) — it requires a `git pull` on the production server. I have no SSH access to `ubuntu@forseti.life` from this dev machine.

- Status: blocked
- Summary: All 3 QA failures (404s on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`) are caused by Drupal's nav menu generating `index.php/`-prefixed hrefs; these PATH_INFO requests bypass Drupal's clean-URL router and return 404. The fix — a 4-line `.htaccess` R=301 redirect stripping `index.php/` before routing — was committed (`6f82d6e92`) and pushed to `origin/main` in the previous cycle. The fix is verified correct locally (clean URL variants all return 200). However, the `.htaccess` is NOT in the symlinked paths (`modules/custom`, `themes/custom`); it lives in the web root and requires a `git pull` on the production server at `/home/ubuntu/forseti.life/`. I cannot SSH to the production server from this dev machine. The GitHub Actions deploy workflow is also broken (`can't connect without a private SSH key or password`, run 24789047622), so the pull cannot happen automatically.

## Next actions
- Production operator must run: `cd /home/ubuntu/forseti.life && git pull --rebase origin main` — no `drush cr` needed
- Verify after pull: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` should return `301`; follow-through `curl -sL` should return `200`
- QA re-run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` to close the 3 open failures
- dev-infra: restore GitHub Actions SSH deploy key so future deploys work automatically

## Blockers
- No SSH access to production server (`ubuntu@forseti.life`) from this dev machine — cannot run `git pull` remotely
- GitHub Actions SSH deploy key missing from secrets (all Actions deploys failing since before run 24789047622)

## Needs from Supervisor
- Authorization and execution of `git pull --rebase origin main` on production at `/home/ubuntu/forseti.life/` by whoever has SSH access (human owner or CEO)

## Decision needed
- Who will run the production git pull? This is the only remaining step to close all 3 QA failures.

## Recommendation
- Human owner runs `cd /home/ubuntu/forseti.life && git pull --rebase origin main` directly — it is a 5-second, zero-risk operation for an `.htaccess`-only change. Simultaneously, dispatch dev-infra to restore the Actions SSH key to prevent future identical deploy blockers.

---

Matrix issue type: Missing access/credentials/environment path

Commit: `6f82d6e92` — pushed to `origin/main`

QA handoff: QA to retest after production pull. Run `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` — expect `HTTP/1.1 301` with `Location: /roadmap/PROJ-002`, then `200` on follow.

## ROI estimate
- ROI: 8
- Rationale: Three live user-facing 404s on the public roadmap block navigation to all forseti.life project pages. Fix is 100% ready — one `git pull` away from closing all 3 failures and unblocking QA sign-off.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-161501-qa-findings-forseti.life-3
- Generated: 2026-04-22T17:18:22-04:00
