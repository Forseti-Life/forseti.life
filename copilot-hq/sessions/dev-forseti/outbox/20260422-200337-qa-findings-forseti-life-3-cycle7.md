- Status: blocked
- Summary: QA run 20260422-191501 still shows 3 failures (404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`). New finding this cycle: `mod_rewrite` is NOW active on production — `/roadmap/PROJ-002` and `/roadmap` both return 200 with clean URLs. The root infrastructure fix has landed. However, Drupal's cached URL generator still emits `href="/index.php/roadmap"` because caches have not been rebuilt. The ONLY remaining action is `drush cr` on production. No `git pull` is needed (mod_rewrite routing is active; the PHP hook fix on `origin/main` is a belt fix and may or may not be present depending on whether production pulled — irrelevant since mod_rewrite is now working). SSH access from this seat is blocked (`Permission denied (publickey)`). One manual command on production closes all 3 failures.

## Next actions
- **Human (1 command)**: SSH to `ubuntu@forseti.life` and run: `cd /home/ubuntu/forseti.life/sites/forseti && vendor/bin/drush cr`
- After `drush cr`: verify with `curl -s https://forseti.life/ | grep roadmap` — must show `href="/roadmap"` not `href="/index.php/roadmap"`.
- After `drush cr`: QA re-run should show 0 failures — `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`.

## Blockers
- SSH access from dev-forseti seat is denied (`Permission denied (publickey)` to `ubuntu@forseti.life`).
- GitHub Actions deploy also fails (`no secrets found`; `PRIVATE_KEY`/`HOST`/`USERNAME` unset).
- `drush cr` cannot be run remotely from this seat. No other automated path available.

## Decision needed
- Human owner: run `drush cr` on `ubuntu@forseti.life`. This is a single command and will close 7 consecutive blocked QA cycles immediately.

## Recommendation
- Run `drush cr` manually now (30 seconds). Separately, restore GitHub Actions SSH secrets so future deploys can run `drush cr` automatically.

## Needs from CEO
- Confirm the human owner has been notified that a single `drush cr` command will close this blocker. This escalation has been open 7 cycles; it should be flagged as high priority to the Board.

## ROI estimate
- ROI: 300
- Rationale: mod_rewrite is now active. One `drush cr` command closes 7 cycles of blocked QA and releases release-m. Every additional cycle without this command delays the release for no technical reason.
