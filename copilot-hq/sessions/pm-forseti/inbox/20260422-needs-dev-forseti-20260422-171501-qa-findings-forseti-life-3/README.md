# Escalation: dev-forseti is blocked

- Website: forseti.life
- Module: 
- Role: software-developer
- Agent: dev-forseti
- Item: 20260422-171501-qa-findings-forseti-life-3
- Status: blocked
- Supervisor: pm-forseti
- Outbox file: sessions/dev-forseti/outbox/20260422-171501-qa-findings-forseti-life-3.md
- Created: 2026-04-22T17:38:54-04:00

## Decision needed
- Who will run the production deploy (git pull + drush cr)? This is the only remaining step to close all 3 QA failures.


## Recommendation
- Human owner runs `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr` — 30-second operation. Dispatch dev-infra simultaneously to enable `mod_rewrite` so this class of bug cannot recur.

---

Matrix issue type: Missing access/credentials/environment path

Commits on `origin/main`:
- `6f82d6e92` — `.htaccess` R=301 redirect (belt)
- `7b31fb415` — `hook_url_outbound_alter` stripping `index.php/` (suspenders, real fix)

QA handoff: After deploy + drush cr, QA to re-run `scripts/site-audit-run.sh forseti-life`. Expected: 0 failures.


## ROI estimate
- ROI: 9
- Rationale: All 3 live production 404s are fixed in code; a single 30-second command on the production server closes them. The root cause (`mod_rewrite` disabled) also explains why every nav link is `index.php/`-prefixed, which is a quality/SEO/crawlability issue affecting the entire site.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-171501-qa-findings-forseti-life-3
- Generated: 2026-04-22T17:38:54-04:00

## Needs from Supervisor (up-chain)
- Execution of `git pull --rebase origin main && vendor/bin/drush cr` on production by whoever has SSH access


## Blockers
- No SSH access to production (`ubuntu@forseti.life`) from this dev machine — cannot run `git pull` or `drush cr` remotely
- GitHub Actions SSH deploy key still missing (run 24789047622 failed, unresolved)


## Full outbox (context)
All nav links across the entire site are `index.php/` prefixed — this is a system-wide Drupal URL generation issue, not just the roadmap page. The root cause: Apache's `mod_rewrite` is not processing the `.htaccess` rules, so Drupal detects `SCRIPT_NAME=/index.php` and generates all route URLs with `index.php/` prefix. I can fix this without server access via `hook_url_outbound_alter()`.

The fix is clear: `hook_url_outbound_alter()` to strip `index.php/` from ALL generated URLs — this fixes every nav link, not just roadmap. Since `modules/custom` is symlinked on production, this fix will be live after `git pull`. Let me implement it.

Fix is committed (`7b31fb415`) and pushed. Note that `hook_url_outbound_alter` requires `drush cr` after deploy to register the new hook. Let me document the required post-deploy steps clearly.

---

- Status: blocked
- Summary: Root cause identified and fixed in code: production Apache does NOT have `mod_rewrite` enabled (or `AllowOverride` is blocking `.htaccess`), causing Drupal to detect `SCRIPT_NAME=/index.php` and generate ALL nav links with `index.php/` prefix site-wide. The crawler hits `/index.php/roadmap` from the nav, which puts Drupal into PATH_INFO mode, and parameterized child routes (`/index.php/roadmap/PROJ-*`) return 404. Two commits are on `origin/main`: `6f82d6e92` (`.htaccess` R=301 redirect, fallback) and `7b31fb415` (`hook_url_outbound_alter` in `forseti_content.module` — strips `index.php/` from all generated URLs, the real fix). Production needs `git pull + drush cr` to go live; until then, all 3 QA failures persist. The module change goes live via the `/modules/custom` symlink but requires cache rebuild to register the new hook.

## Next actions
- Production operator must run: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s https://forseti.life/ | grep "roadmap"` → expect `/roadmap` (no `index.php/`)
- Verify failures cleared: `curl -sI https://forseti.life/index.php/roadmap/PROJ-002` → `301`; `curl -sL` → `200`
- QA re-run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` to confirm 0 failures
- dev-infra follow-up: enable `mod_rewrite` on production (`a2enmod rewrite && systemctl reload apache2`) and confirm `AllowOverride All` is set for `/home/ubuntu/forseti.life/sites/forseti/web` in the vhost — this is the permanent server-side fix

## Blockers
- No SSH access to production (`ubuntu@forseti.life`) from this dev machine — cannot run `git pull` or `drush cr` remotely
- GitHub Actions SSH deploy key still missing (run 24789047622 failed, unresolved)

## Needs from Supervisor
- Execution of `git pull --rebase origin main && vendor/bin/drush cr` on production by whoever has SSH access

## Decision needed
- Who will run the production deploy (git pull + drush cr)? This is the only remaining step to close all 3 QA failures.

## Recommendation
- Human owner runs `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr` — 30-second operation. Dispatch dev-infra simultaneously to enable `mod_rewrite` so this class of bug cannot recur.

---

Matrix issue type: Missing access/credentials/environment path

Commits on `origin/main`:
- `6f82d6e92` — `.htaccess` R=301 redirect (belt)
- `7b31fb415` — `hook_url_outbound_alter` stripping `index.php/` (suspenders, real fix)

QA handoff: After deploy + drush cr, QA to re-run `scripts/site-audit-run.sh forseti-life`. Expected: 0 failures.

## ROI estimate
- ROI: 9
- Rationale: All 3 live production 404s are fixed in code; a single 30-second command on the production server closes them. The root cause (`mod_rewrite` disabled) also explains why every nav link is `index.php/`-prefixed, which is a quality/SEO/crawlability issue affecting the entire site.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260422-171501-qa-findings-forseti-life-3
- Generated: 2026-04-22T17:38:54-04:00
