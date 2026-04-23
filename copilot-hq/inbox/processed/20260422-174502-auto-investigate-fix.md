# Command

- created_at: 2026-04-22T17:45:02-04:00
- work_item: forseti-auto-investigation
- topic: auto-investigate-fix

## Command text
[AUTO-INVESTIGATION] Release KPI stagnation for forseti (forseti.life).
run_id=20260422-171501, open_issues=3, dev_status=blocked, unanswered_alerts=2, escalation_depth=1.

Autonomous directives (execute in order):
  1. Dev agent is blocked on this run. Do NOT re-queue dev. Trigger a QA re-run: bash scripts/site-audit-run.sh forseti.life

Dev outbox excerpt:
All nav links across the entire site are `index.php/` prefixed — this is a system-wide Drupal URL generation issue, not just the roadmap page. The root cause: Apache's `mod_rewrite` is not processing the `.htaccess` rules, so Drupal detects `SCRIPT_NAME=/index.php` and generates all route URLs with `index.php/` prefix. I can fix this without server access via `hook_url_outbound_alter()`.

The fix is clear: `hook_url_outbound_alter()` to strip `index.php/` from ALL generated URLs — this fixes every nav link, not just roadmap. Since `modules/custom` is symlinked on production, this fix will be live after `git pull`. Let me implement it.

Fix is committed (`7b31fb415`) and pushed. Note that `hook_url_outbound_alter` requires `drush cr` after deploy to register the new hook. Let me document the required post-deploy steps clearly.

---

- Status: blocked
- Summary: Root cause identified and fixed in code: production Apache does NOT have `mod_rewrite` enabled (or `AllowOverride` is blocking `.htaccess`), causing Drupal to detect `SCRIPT_NAME=/index.php` and generate ALL nav links with `index.php/` prefix site-wide. The crawler hits `/index.php/roadmap` from the nav, which puts Drupal into PATH_INFO mode, and parameterized child routes (`/index.php/roadmap/PROJ-*`) return 404. Two commits are on `origin/main`: `6f82d6e92` (`.htaccess` R=301 redirect, fallback) and `7b31fb415` (`hook_url_outbound_alter` in `forseti_content.module` — strips `index.php/` from all generated URLs, the real fix). Production needs `git pull + drush cr` to go live; until then, all 3 QA failures persist. The module change goes live via the `/modules/custom` symlink but requires cache rebuild to register the new hook.

## Next actions
- Production operator must run: `cd /home/ubuntu/forseti.life && git pull --rebase origin main && cd sites/forseti && vendor/bin/drush cr`
- Verify: `curl -s https://forseti.life/ | grep "roadmap"` → expect `/roadmap` (no `index.php/`)
- Verify failures cleared: `curl 
...[truncated]
