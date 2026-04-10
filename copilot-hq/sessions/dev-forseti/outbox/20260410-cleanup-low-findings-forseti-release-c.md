- Status: done
- Summary: Applied both LOW cleanup findings from the forseti-release-c code review. Finding A: removed dead `<input type="hidden" name="form_token">` from `forseti-conversations.html.twig` line 71 — the token is already on the form action URL via `RouteProcessorCsrf`, the body field was never read. Finding B: removed the `RELEASE_CYCLE_CONTROL_FILE_LEGACY` constant (which hardcoded `/home/ubuntu/forseti.life/copilot-hq/...`) from `DashboardController.php` and replaced the fallback usage in `readReleaseCycleControlState()` with the inline `getenv('COPILOT_HQ_ROOT') ?: '/home/ubuntu/forseti.life/copilot-hq'` dynamic pattern already used elsewhere in the same controller. Ran `drush cr`. Commit: `9855e6049`.

## Next actions
- QA-forseti: re-verify Finding A (grep clean) and Finding B (grep clean) to clear the LOW items from the release-c review BLOCK.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: These are low-risk cleanup items that must be resolved to get release-c to a clean code review state; each fix is a 1-line change with no functional risk.
