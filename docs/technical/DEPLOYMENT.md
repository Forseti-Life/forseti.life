# Deployment (Production)

## Deployment model (manual only)

Production does **not** pull from GitHub automatically for off-host/local development.

- All production deployments are explicit operator actions.
- Pushing to `main` records source-of-truth code in GitHub, but does not deploy by itself.
- Use `workflow_dispatch` for `.github/workflows/deploy.yml` only when you intentionally promote changes to production.

## Production-host immediate-live behavior

On the production server, the active runtime is linked directly to the production checkout (`/home/ubuntu/forseti.life`) for HQ and site custom code paths.

- Changes executed on the production host are live immediately in production runtime.
- This immediate-live behavior applies to on-server changes, not to local-dev-machine changes.
- Local/off-host work still requires explicit promotion (manual deploy path).

Workflow: [.github/workflows/deploy.yml](../../../.github/workflows/deploy.yml)

### What the workflow does (Forseti + Dungeoncrawler)

- Sparse-clones the repo on the production host
- `rsync`s custom modules/themes into `/var/www/html/<site>/web/{modules,themes}/custom/`
- Runs `composer install` only if `composer.json` changed
- Runs `drush updatedb -y` when modules/composer changed
- Runs `drush cache:rebuild` when modules/composer/config changed
- Fixes ownership/perms
- Performs basic HTTPS health checks

### Important note: config deployment is disabled

`config/sync` is intentionally not auto-deployed/imported by the workflow. Follow the manual config workflow in [script/production/README.md](../../../script/production/README.md).

## Manual deploy

### Option A: GitHub Actions (preferred)

Run `.github/workflows/deploy.yml` with **Run workflow**.

### Option B: Direct server pull (emergency or controlled maintenance)

```bash
ssh root@<prod-host>
cd /var/www/html/forseti
git pull origin main
sudo -u www-data ./vendor/bin/drush updatedb -y
sudo -u www-data ./vendor/bin/drush cache:rebuild
```

## Dual-environment development policy (local + production)

Both environments can produce commits.

- Local dev machine: primary feature development and larger refactors.
- Production server: urgent live fixes and operational changes when needed.
- Production-host changes are immediately live; capture them in Git quickly to avoid drift.

Authoritative conflict-safe process:

1. **Before starting work anywhere**
	- `git fetch origin --prune`
	- `git status`
	- `git pull --rebase origin main`
2. **Do work in a short-lived branch**
	- Local: `local/<topic>-<date>`
	- Production: `prod-hotfix/<topic>-<date>`
3. **Push branch immediately when work starts**
	- This captures in-flight work and prevents invisible divergence.
4. **Merge to `main` quickly after verification**
	- Keep branch lifetime short to reduce conflict probability.
5. **Before a second environment starts related work**
	- Pull latest `main` and rebase its branch.
6. **If both touched the same files**
	- Prefer production-tested behavior for urgent paths.
	- Re-apply local improvements on top in a follow-up commit.
	- Never force-push over shared `main`.

See detailed workflow: [DEVELOPMENT_SYNC_WORKFLOW.md](./DEVELOPMENT_SYNC_WORKFLOW.md)

## Post-deploy verification (Copilot Agent Tracker dashboards)

Recent changes live under:
- `sites/forseti/web/modules/custom/copilot_agent_tracker/**`

After a deploy (auto or manual), verify as an admin:

1) Waiting on Keith page loads and queue looks right:
- `/admin/reports/waitingonkeith`
- Paused seats do **not** appear in the Agents queue table.

2) Release Notes page loads:
- `/admin/reports/copilot-agent-tracker/releases`

3) If a new route/menu link does not appear, run:
```bash
sudo -u www-data ./vendor/bin/drush cache:rebuild
```

## HQ → Drupal data dependency

These dashboards depend on HQ publishing metadata into Drupal. If the page loads but looks empty/stale, run from the HQ repo:

```bash
cd /home/keithaumiller/copilot-sessions-hq
./scripts/publish-forseti-agent-tracker.sh
```

## Rollback

- `git revert <bad-commit>` on `main`, push, then run manual deploy workflow.
- If needed, run `drush cache:rebuild` after rollback.
