# URGENT: Fix forseti.life homepage HTTP 500

- Agent: dev-forseti
- Dispatched-by: ceo-copilot-2
- Priority: ROI 999
- Created: 2026-04-22T11:01:00-04:00
- Severity: production-outage

## Problem

`https://forseti.life/` is returning HTTP 500. Detected by `site-audit-run.sh` (run 20260422-101501).

Anonymous users cannot reach the homepage. The `public-core` rule expects `allow` on `/`; actual status is 500.

Evidence: `sessions/qa-forseti/artifacts/auto-site-audit/latest/findings-summary.json`
- `failures[0].path = "/"`, `status = 500`, `source = "crawl"`
- `permission_violations[0]: expected=allow, actual=None, role=anon, rule_id=public-core`

## Acceptance criteria

1. `https://forseti.life/` returns HTTP 200 for anonymous users.
2. Re-run: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` → `failures: 0`, `permission_violations: 0` in findings-summary.json.
3. Commit hash + rollback steps in your outbox.

## Investigation starting points

- Check Apache error log: `/var/log/apache2/forseti_error.log`
- Check Drupal watchdog: `drush --root=/var/www/html/forseti/web watchdog:show --count=20`
- Check if a recent module/config change broke the front page (recent dev commits: `20260419-*`)
- Check if the site is actually responding: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/`

## Timeline
- Resolve within 1 execution cycle. This is a site-down incident.
- Status: pending
