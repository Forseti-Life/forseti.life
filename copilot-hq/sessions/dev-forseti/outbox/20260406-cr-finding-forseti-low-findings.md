- Status: done
- Summary: Both LOW code-review findings from `20260406-code-review-forseti.life-20260406-forseti-release` are fixed and committed in `9bf501fe`. Finding A: replaced all 6 hardcoded `/home/keithaumiller/copilot-sessions-hq` paths in `DashboardController.php` with `/home/ubuntu/forseti.life/copilot-hq` — the `langgraphPath()` fallback, `buildLanggraphTroubleshootingPanels()` fallback, `RELEASE_CYCLE_CONTROL_FILE_LEGACY` constant, and the three hardcoded glob calls in `inferReleaseIdFromQaPreflightArtifacts()` / `inferReleaseIdFromSignoffs()` now use `$this->langgraphPath(...)`. Finding B: `ai_conversation.api_send_message` route now has `methods: [POST]` at route level and `_csrf_request_header_mode: TRUE` in requirements; the ineffective `_method: 'POST'` requirement was removed. Both files pass `php -l`.

## Next actions
- `drush cr` on production to rebuild routing cache (pending vendor/ — same infra constraint as prior tasks)
- QA verify: `grep -n 'keithaumiller' DashboardController.php` → 0 results (confirmed locally); `grep -A12 'api_send_message:' ai_conversation.routing.yml` → shows `methods: [POST]` + `_csrf_request_header_mode: TRUE`

## Blockers
- `drush cr` cannot run without `vendor/` on production host (pm-infra constraint, pre-existing)

## Needs from CEO
- None

## ROI estimate
- ROI: 25
- Rationale: Finding A causes all LangGraph dashboard sections to silently fail in production when the env var is unset — a silent runtime bug that breaks the agent tracker dashboard. Finding B closes an actual method-enforcement gap on an authenticated API endpoint. Both are low-effort, high-correctness fixes.
