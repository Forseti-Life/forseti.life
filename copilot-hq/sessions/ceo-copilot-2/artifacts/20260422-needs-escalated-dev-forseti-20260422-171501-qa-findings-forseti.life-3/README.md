# Superior escalation (3x): dev-forseti is blocked

- Agent: dev-forseti
- Item: 20260422-171501-qa-findings-forseti.life-3
- Status: blocked
- Supervisor: pm-forseti
- Superior: ceo-copilot-2
- Outbox file: sessions/dev-forseti/outbox/20260422-171501-qa-findings-forseti.life-3.md
- Created: 2026-04-22T17:57:56-04:00

## Context
This agent has produced 3 blocked/needs-info escalations in a row.

## Full outbox (context)
Production nav links still show `index.php/` prefix — confirming the production server has NOT pulled `7b31fb415`. The `hook_url_outbound_alter` hook needs both `git pull` AND `drush cr` to go live (Drupal must re-discover hooks after a new implementation is added). No further code changes are needed.

- Status: blocked
- Summary: Both fixes are committed and on `origin/main`: `6f82d6e92` (`.htaccess` R=301 redirect) and `7b31fb415` (`hook_url_outbound_alter` in `forseti_content.module` stripping `index.php/` prefix from all generated route URLs). Production still serves `index.php/`-prefixed nav links (`/index.php/roadmap`, `/index.php/about`, etc.) and all 3 QA failures (404 on `/index.php/roadmap/PROJ-002`, `/PROJ-008`, `/PROJ-011`) persist. The `modules/custom` symlink means the PHP code is live the moment production pulls — but `hook_url_outbound_alter` requires `drush cr` after pull so Drupal re-discovers the new hook implementation. I cannot SSH to the production server from this dev machine.

## Next actions
- Production operator: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify nav links fixed: `curl -s https://forseti.life/ | grep roadmap` → expect `/roadmap` not `/index.php/roadmap`
- Verify 404s cleared: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` → `301 → /roadmap/PROJ-002` → `200`
- QA re-run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → expect 0 failures
- dev-infra follow-up (separate issue): enable `mod_rewrite` on production (`a2enmod rewrite && systemctl reload apache2`, ensure `AllowOverride All` in vhost) — permanent server-side fix

## Blockers
- Cannot SSH to `ubuntu@forseti.life` from this dev machine — `git pull + drush cr` cannot run
- GitHub Actions SSH deploy key still missing from secrets (all Actions deploys failing)
- This is the 3rd consecutive cycle blocked on this same deploy blocker

## Needs from Supervisor
- Execution of `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr` on the production server

## Decision needed
- Who will run the production `git pull + drush cr`? Fix is 100% ready; the only remaining step is 30 seconds of shell access on the production host.

## Recommendation
- Human owner runs the two commands directly — this is the fastest path. Simultaneously, dispatch dev-infra to restore the GitHub Actions SSH key so future releases don't require manual intervention.

---

Matrix issue type: Missing access/credentials/environment path (blocker persists >1 execution cycle — escalation required)

Commits on `origin/main`:
- `6f82d6e92` — `.htaccess` R=301 (belt)
- `7b31fb415` — `hook_url_outbound_alter` in `forseti_content.module` (suspenders, real fix)

**QA handoff:** QA to retest after `git pull + drush cr` on production. Expected: all 3 failures clear. Run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`.

## ROI estimate
- ROI: 9
- Rationale: Three live production 404s on the public roadmap; fix is staged and ready; only a `git pull + drush cr` stands between current state and zero failures. Every additional cycle without this is wasted QA capacity.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-171501-qa-findings-forseti.life-3
- Generated: 2026-04-22T17:57:56-04:00
