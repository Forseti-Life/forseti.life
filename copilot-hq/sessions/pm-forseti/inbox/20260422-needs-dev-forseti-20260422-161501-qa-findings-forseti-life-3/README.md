# Escalation: dev-forseti is blocked

- Website: forseti.life
- Module: 
- Role: software-developer
- Agent: dev-forseti
- Item: 20260422-161501-qa-findings-forseti-life-3
- Status: blocked
- Supervisor: pm-forseti
- Outbox file: sessions/dev-forseti/outbox/20260422-161501-qa-findings-forseti-life-3.md
- Created: 2026-04-22T16:54:22-04:00

## Decision needed
- Who runs the production manual pull? (pm-forseti dispatching to deploy operator, or CEO/human directly) — and should dev-infra restore the Actions SSH key as a P1 infrastructure fix?


## Recommendation
- Manual pull is the fastest path (2-minute operation, zero risk for an `.htaccess`-only change). Fix the Actions SSH key as a parallel infra task. Both should happen this cycle.

---

**Matrix issue type:** Release gate failure (QA BLOCK at Gate 2/4) — fix is ready, deployment mechanism is broken.

**Touched files:**
- `sites/forseti/web/.htaccess` — commit `6f82d6e92` (pushed to origin/main)

**QA handoff:** QA notified — retest after production pulls `6f82d6e92`. Verify `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` returns `301`, then `curl -sL https://forseti.life/index.php/roadmap/PROJ-002` returns `200`.


## ROI estimate
- ROI: 8
- Rationale: Live user-facing 404s on roadmap project links. Fix is deployed to GitHub and takes effect immediately after a `git pull` on production — single low-risk operation unblocks the entire QA cycle. The Actions SSH key failure is a secondary infrastructure blocker affecting all future deploys.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-161501-qa-findings-forseti-life-3
- Generated: 2026-04-22T16:54:22-04:00

## Needs from Supervisor (up-chain)
- Authorization + execution of manual `git pull --rebase origin main` on the production server at `/home/ubuntu/forseti.life/`, OR coordination with dev-infra to restore the GitHub Actions SSH deploy key so the workflow can run automatically.


## Blockers
- GitHub Actions deploy is broken: `Error: can't connect without a private SSH key or password` (run 24789047622, triggered 2026-04-22T16:07). All production deploys via Actions are failing.
- Without a manual `git pull` on production, commit `6f82d6e92` cannot take effect.


## Full outbox (context)
Root cause confirmed: PROJECTS.md can't be read when served uncached via `/index.php/` paths (`COPILOT_HQ_ROOT` defaults to `/home/ubuntu/forseti.life/copilot-hq`—a path that only exists on the production server). The previous cycle's htaccess fix (redirecting `index.php/roadmap/*` → `/roadmap/*`) is committed locally but NOT pushed. That's why production still has 404s.

Push succeeded. Now I need to verify production has the fix deployed. Per site.instructions.md, production auto-deploys Apache-linked changes when production pulls, but that pull still needs to happen on the production server.

Push confirmed on `origin/main`. The GitHub Actions deploy is broken (`can't connect without a private SSH key or password`). The fix can't reach production automatically. Writing the blocked outbox now.

- Status: blocked
- Summary: QA run 20260422-161501 shows 3 failures — all 404s on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, and `/PROJ-011`. Root cause: Drupal's nav menu generates `index.php/`-prefixed hrefs; these paths hit the controller uncached, and `loadRoadmapProjectDetail()` fails because `COPILOT_HQ_ROOT` defaults to `/home/ubuntu/forseti.life/copilot-hq` which is not set or not readable by `www-data` on production for PATH_INFO requests. The fix — a 4-line `.htaccess` R=301 redirect stripping `index.php/` prefix (commit `6f82d6e92`) — was committed in the previous cycle and is now pushed to `origin/main`. However, the GitHub Actions deploy workflow is failing with `can't connect without a private SSH key or password` (SSH deploy key missing from Actions secrets). The fix cannot reach the production server automatically. A manual `git pull --rebase origin main` on the production server (`ubuntu@forseti.life`, at `/home/ubuntu/forseti.life/`) is all that is needed to make the fix live.

## Next actions
- Production deploy: `cd /home/ubuntu/forseti.life && git pull --rebase origin main` — no `drush cr` required (`.htaccess` change only)
- Verify after deploy: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` should return `301` → then `200`
- QA re-run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` to close out the 3 failures
- Separately: dev-infra should fix the GitHub Actions SSH deploy key (missing from secrets → all deploys failing)

## Blockers
- GitHub Actions deploy is broken: `Error: can't connect without a private SSH key or password` (run 24789047622, triggered 2026-04-22T16:07). All production deploys via Actions are failing.
- Without a manual `git pull` on production, commit `6f82d6e92` cannot take effect.

## Needs from Supervisor
- Authorization + execution of manual `git pull --rebase origin main` on the production server at `/home/ubuntu/forseti.life/`, OR coordination with dev-infra to restore the GitHub Actions SSH deploy key so the workflow can run automatically.

## Decision needed
- Who runs the production manual pull? (pm-forseti dispatching to deploy operator, or CEO/human directly) — and should dev-infra restore the Actions SSH key as a P1 infrastructure fix?

## Recommendation
- Manual pull is the fastest path (2-minute operation, zero risk for an `.htaccess`-only change). Fix the Actions SSH key as a parallel infra task. Both should happen this cycle.

---

**Matrix issue type:** Release gate failure (QA BLOCK at Gate 2/4) — fix is ready, deployment mechanism is broken.

**Touched files:**
- `sites/forseti/web/.htaccess` — commit `6f82d6e92` (pushed to origin/main)

**QA handoff:** QA notified — retest after production pulls `6f82d6e92`. Verify `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` returns `301`, then `curl -sL https://forseti.life/index.php/roadmap/PROJ-002` returns `200`.

## ROI estimate
- ROI: 8
- Rationale: Live user-facing 404s on roadmap project links. Fix is deployed to GitHub and takes effect immediately after a `git pull` on production — single low-risk operation unblocks the entire QA cycle. The Actions SSH key failure is a secondary infrastructure blocker affecting all future deploys.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-161501-qa-findings-forseti-life-3
- Generated: 2026-04-22T16:54:22-04:00
