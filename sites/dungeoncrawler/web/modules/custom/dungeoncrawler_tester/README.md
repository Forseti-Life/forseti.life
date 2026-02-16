# Dungeon Crawler Tester Module

**Module Name**: dungeoncrawler_tester  
**Purpose**: Holds the testing harness and full functional test suite for the Dungeon Crawler content module.  
**Depends on**: `dungeoncrawler_content`

## What’s inside
- PHPUnit configuration tuned for Drupal functional tests.
- Comprehensive functional test suite (routes + controllers).
- Testing module README with run commands and grouping.
- **Testing Dashboard** - A web-based dashboard for quick access to test documentation, commands, and CI status.

## Testing Dashboard

The testing dashboard provides a centralized location for developers to:
- Access all test documentation and guides
- View and copy test commands for quick execution
- Monitor CI failures and testing-related issues
- Review release testing stagegates

**Access the dashboard:**
- URL: `/dungeoncrawler/testing`
- Permission required: `administer site configuration`
- Menu location: Reports > Dungeon Crawler Testing Dashboard
- Navigation entry: `Documentation Home` appears under the Testing Dashboard menu item.

The dashboard includes:
- **Test Documentation**: Consolidated structure with Getting Started, Execution Playbook, and Failure Triage workflow pages
- **Documentation Home**: `DOCUMENTATION_HOME.md` is the canonical index for tester documentation.
- **Quick Test Commands**: Copy/paste commands for running different test suites
- **Release Testing Stagegates**: Testing workflow and checklist
- **GitHub Issues**: Live feed of CI failures and testing defects
- **Issue/PR Report Workflow**: `/dungeoncrawler/testing/issue-pr-report` now documents process and decision logic for low-to-high PR triage, no-op/superseded close decisions, and verification expectations.
- **Docs link handling**: Documentation links resolve to internal Drupal documentation pages (no direct `.md` links); only the testing issues query links to GitHub.
- **Theme compliance**: Documentation pages render with the theme-standard Bootstrap layout (`container` + `row/col`) and `card card-dungeoncrawler` sections for visual consistency.
- **TheTest route scope**: The automation flip page now lives at `/dungeoncrawler/testing/thetest` to avoid generic root-path collisions and improve discoverability.
- **TheTest status source**: PASS/FAIL status now comes from tester state (`dungeoncrawler_tester.thetest_status`) with optional env override (`TESTER_THETEST_STATUS=pass|fail`), not a hard-coded controller constant.
- **Secret token storage**: Tester settings now store `github_token` in Drupal state (`dungeoncrawler_tester.github_token`) instead of exported module config to reduce accidental token exposure.
- **POST route hardening**: Mutative tester AJAX routes require CSRF validation in routing requirements.
- **Stage command validation**: Drush stage-control commands now validate stage IDs against defined stage definitions before writing state.
- **Queue lock lease strategy**: Manual queue-run command now uses a batch-size-aware lock lease instead of a fixed 30-second window to reduce concurrent-run collisions.
- **Failure-text sanitization**: Dashboard stage-control failure reason/excerpt rendering now escapes dynamic state-derived strings before output in `#markup`/`#description` paths.
- **Payload decode hardening**: Queue/watchdog serialized payload reads now use safe decode helpers with `allowed_classes=false` and invalid-payload fallback handling.
- **Controller DI hardening**: Tester controllers now use injected cache/CSRF services instead of static `\Drupal::...` lookups for queue/dashboard AJAX settings and cache paths.
- **Repo-aware nav links**: Tester navigation block now builds the GitHub issue queue link from configured repository context (`dungeoncrawler_tester.settings`/`ai_conversation.settings`/`TESTER_GITHUB_REPO`).
- **Repo-aware sign-off link**: Stage definitions now build the release sign-off defect link from configured repository context (`dungeoncrawler_tester.settings`/`ai_conversation.settings`/`TESTER_GITHUB_REPO`) instead of a hard-coded repository URL.
- **Filesystem diagnostics**: Queue worker now validates simpletest directory creation/writability and surfaces explicit failure diagnostics in run/state output when setup fails.
- **Temp-state isolation**: GitHub client cooldown/dedupe/lock files now include repository-scoped namespaces to reduce cross-site/process collisions on shared hosts.
- **Auto-enqueue serialization**: Stage auto-enqueue now uses a cross-process lock guard to prevent duplicate queueing under concurrent cron invocations.
- **Issue-sync efficiency/observability**: Stage issue sync now preloads open issues via a paginated bulk read, deduplicates fallback per-issue checks, and writes last-run diagnostics/fetch-failure details to state (`dungeoncrawler_tester.issue_sync_last`).
- **Robust logging**: Dashboard form now lazy-loads logger service to avoid cache-induced initialization errors during command submissions.
- **Serialization-safe DI**: Dashboard form lazy-loads all injected services (state, date formatter, stage definitions, queue, uuid) to survive form cache serialization.
- **Dashboard CSS maintenance**: Removed a duplicate `.stage-grid` selector block in `css/dashboard.css` to keep layout rules single-sourced and easier to maintain.

### Standard testing documentation structure

- `Getting Started` (`/dungeoncrawler/testing/documentation/getting-started`)
- `Test Execution Playbook` (`/dungeoncrawler/testing/documentation/execution-playbook`)
- `Failure Triage and Issue Workflow` (`/dungeoncrawler/testing/documentation/failure-triage`)
- `Automated Testing Process Flow` (`/dungeoncrawler/testing/documentation/process-flow`) - rendered process-flow page
- Source of truth: `PROCESS_FLOW.md` (canonical sync/async timing and blocking-gates documentation)
- Legacy documentation routes remain available as compatibility aliases and map to these consolidated pages.

### GitHub issue automation (failures)

- If a stage fails, the queue worker will try to open a GitHub issue and pause the stage.
- Configure via `/admin/config/development/dungeoncrawler-tester` (preferred; repo in config, token in private state), `ai_conversation.settings` (`github_repo`, `github_token`), or env vars `TESTER_GITHUB_REPO`, `TESTER_GITHUB_TOKEN` (format: `owner/repo`). The default repo, if left blank, will fall back to `keithaumiller/forseti.life`.
- Existing linked issues are respected; no new issue is opened if `issue_number` is already present in stage state.
- Copilot assignment is performed as a second API step after issue creation, trying `@copilot`, `Copilot`, then `copilot` identifiers for compatibility.
- If GitHub REST assignment does not attach Copilot, the worker falls back to `gh issue edit --add-assignee "@copilot"`.
- Copilot assignment is now guarded by tester settings:
	- `copilot_assignment_max_open` (default `0`): assignment is skipped only when this optional cap is set and reached (`0` disables throttling).
- If assignment fails, the module logs GitHub API error details on the `dungeoncrawler_tester` channel to support root-cause debugging.
- Settings form route uses `dungeoncrawler_tester.settings`; if the page fails to load after route changes, rebuild caches (`drush cr`).

### Getting a GitHub token (for issue creation)

1) Visit https://github.com/settings/tokens and create a **Fine-grained token** or **classic token** with scope `repo` (issue creation only requires `public_repo` on classic tokens).
2) Set the token in `/admin/config/development/dungeoncrawler-tester` (stored in private state, not config export) or export `TESTER_GITHUB_TOKEN` in the environment.
3) Set the repository to `keithaumiller/forseti.life` (default) or another `owner/repo`. If left blank, the system will use `keithaumiller/forseti.life`.

## Current review status
- First-pass review completed for inventory (unit + functional suites). Functional workflow test remains stubbed.
- Follow-up issues to be opened are staged in [issues_todo.md](../../../issues_todo.md) (workflow implementation, data-backed functional assertions, negative/authorization coverage, shared builders, and content-backed smoke tests).

## Running tests

**Quick Start:**
```bash
cd sites/dungeoncrawler
./tests/run-tests.sh
```

For complete run instructions, test suites, groups, and examples, see **[tests/README.md](tests/README.md)** - the canonical testing guide.

**Quick tip**: Visit the Testing Dashboard at `/dungeoncrawler/testing` for a complete list of test commands with copy/paste functionality.

### Test Environment Setup

The test suite uses a custom bootstrap (`tests/bootstrap.php`) that ensures the `web/sites/simpletest` directory exists with proper permissions before running tests. This is required for Drupal's BrowserTestBase, which creates temporary test site directories during functional test execution.

If you encounter permission errors like "Failed to open 'sites/simpletest/XXXXXX/settings.php'", verify that:
1. The `web/sites/simpletest` directory exists
2. The directory has write permissions (chmod 775 or 777)
3. The web server user has access to create subdirectories

The custom bootstrap handles this automatically, but manual intervention may be needed in restricted environments.

## Notes
- Tests enable `dungeoncrawler_content`; this module only houses the test code and config.
- No content types, controllers, or assets are defined here—those stay in the main content module.

## GitHub client architecture (centralized)

- A thin integration layer now exists at [src/Service/GithubIssuePrClient.php](src/Service/GithubIssuePrClient.php) with contract [src/Service/GithubIssuePrClientInterface.php](src/Service/GithubIssuePrClientInterface.php).
- This service centralizes GitHub context resolution (`repo` + token fallback chain) and core issue/PR request methods.
- The client now enforces mutative request serialization with a cross-process lock and a minimum 1-second spacing between POST/PATCH/PUT/DELETE operations.
- The client now applies rate-limit retry handling for 403/429 responses, honoring `Retry-After` and `X-RateLimit-Reset`, with exponential backoff + jitter fallback.
- The client now includes a mutative circuit breaker: repeated 403/429 rate-limit failures trigger a temporary cooldown window that pauses further mutative automation attempts.
- The client now adds mutation dedupe guards for repeated issue/PR close and comment operations, suppressing duplicate mutative calls within a short time window.
- Migration slices completed:
	- [src/Service/StageIssueSyncService.php](src/Service/StageIssueSyncService.php)
	- [src/Form/SdlcResetForm.php](src/Form/SdlcResetForm.php)
	- [src/Form/DeadValueCloseForm.php](src/Form/DeadValueCloseForm.php)
	- [src/Plugin/QueueWorker/TesterRunQueueWorker.php](src/Plugin/QueueWorker/TesterRunQueueWorker.php) for GitHub issue create/comment/assign/search flows
	- [src/Controller/TestingDashboardController.php](src/Controller/TestingDashboardController.php) for centralized GitHub context resolution + mutation + read/list helper paths
	now consume the centralized client instead of direct `http_client` + local config/token resolution logic.
- Remaining direct GitHub callsites are minimal and mostly internal legacy helper surface that can be removed in a cleanup pass; operational read/mutation flows now route through the centralized client.

## GitHub rate-limit runbook

- Core limits reference (subject to GitHub policy updates):
	- Unauthenticated REST: 60 requests/hour per IP.
	- Authenticated REST: 5,000 requests/hour per user/token.
	- Authenticated GraphQL: 5,000 points/hour.
	- GitHub Actions `GITHUB_TOKEN`: 1,000 requests/hour per repository (15,000/hour for Enterprise Cloud resources).
	- GitHub App installations: baseline 5,000/hour, scalable up to 12,500/hour (15,000/hour on Enterprise Cloud).
- Check current limits from CLI:
	- `gh api rate_limit`
- Secondary rate-limit handling expectations:
	- Honor `Retry-After` and `X-RateLimit-Reset` before retrying.
	- Use exponential backoff and avoid high-frequency polling loops.
	- Prefer conditional/cached reads and max page sizes to reduce repetitive calls.
	- Keep mutative operations serialized to avoid anti-spam triggering.
- Recovery workflow when mutative cooldown is active:
	1. Review recent `dungeoncrawler_tester` logs for repeated 403/429 failures.
	2. Wait for cooldown to expire; avoid restarting high-frequency mutation loops.
	3. Verify quota with `gh api rate_limit` before resuming manual or automated runs.
	4. Resume stage/queue processing and confirm normal mutation success in logs.

## File Inventory
| File | Purpose | First pass |
| --- | --- | --- |
| [README.md](README.md) | Module overview and usage notes | Reviewed |
| [dungeoncrawler_tester.info.yml](dungeoncrawler_tester.info.yml) | Module metadata and dependency on dungeoncrawler_content | Reviewed |
| [phpunit.xml](phpunit.xml) | PHPUnit configuration (suites, coverage, env, custom bootstrap) | Updated |
| [tests/bootstrap.php](tests/bootstrap.php) | Custom bootstrap ensuring simpletest directory permissions | New |
| [tests/README.md](tests/README.md) | Test suite structure and quick commands | Updated |
| [tests/TESTING_MODULE_README.md](tests/TESTING_MODULE_README.md) | Detailed test instructions and grouping | Updated |
| [tests/fixtures/characters/level_1_fighter.json](tests/fixtures/characters/level_1_fighter.json) | Character fixture: level 1 fighter | Updated |
| [tests/fixtures/characters/level_1_wizard.json](tests/fixtures/characters/level_1_wizard.json) | Character fixture: level 1 wizard | Updated |
| [tests/fixtures/characters/level_5_rogue.json](tests/fixtures/characters/level_5_rogue.json) | Character fixture: level 5 rogue | Updated |
| [tests/fixtures/pf2e_reference/core_mechanics.json](tests/fixtures/pf2e_reference/core_mechanics.json) | PF2e reference data | Reviewed |
| [tests/fixtures/schemas/ancestries_test.json](tests/fixtures/schemas/ancestries_test.json) | Schema fixture: ancestries | Reviewed |
| [tests/fixtures/schemas/backgrounds_test.json](tests/fixtures/schemas/backgrounds_test.json) | Schema fixture: backgrounds | Reviewed |
| [tests/fixtures/schemas/classes_test.json](tests/fixtures/schemas/classes_test.json) | Schema fixture: classes | Reviewed |
| [tests/src/Functional/CampaignStateAccessTest.php](tests/src/Functional/CampaignStateAccessTest.php) | Functional: campaign state access | Reviewed |
| [tests/src/Functional/CampaignStateValidationTest.php](tests/src/Functional/CampaignStateValidationTest.php) | Functional: campaign state validation | Reviewed |
| [tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php](tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php) | Functional: character creation workflow | Reviewed (tests incomplete) |
| [tests/src/Functional/Controller/AboutControllerTest.php](tests/src/Functional/Controller/AboutControllerTest.php) | Functional: About controller | Reviewed |
| [tests/src/Functional/Controller/CampaignControllerTest.php](tests/src/Functional/Controller/CampaignControllerTest.php) | Functional: campaign controller | Reviewed |
| [tests/src/Functional/Controller/CharacterApiControllerTest.php](tests/src/Functional/Controller/CharacterApiControllerTest.php) | Functional: character API controller | Reviewed |
| [tests/src/Functional/Controller/CharacterCreationControllerTest.php](tests/src/Functional/Controller/CharacterCreationControllerTest.php) | Functional: character creation controller | Reviewed |
| [tests/src/Functional/Controller/CharacterCreationStepControllerTest.php](tests/src/Functional/Controller/CharacterCreationStepControllerTest.php) | Functional: character creation step controller | Reviewed |
| [tests/src/Functional/Controller/CharacterListControllerTest.php](tests/src/Functional/Controller/CharacterListControllerTest.php) | Functional: character list controller | Reviewed |
| [tests/src/Functional/Controller/CharacterStateControllerTest.php](tests/src/Functional/Controller/CharacterStateControllerTest.php) | Functional: character state controller | Reviewed |
| [tests/src/Functional/Controller/CharacterViewControllerTest.php](tests/src/Functional/Controller/CharacterViewControllerTest.php) | Functional: character view controller | Reviewed |
| [tests/src/Functional/Controller/CombatActionControllerTest.php](tests/src/Functional/Controller/CombatActionControllerTest.php) | Functional: combat actions controller | Reviewed |
| [tests/src/Functional/Controller/CombatApiControllerTest.php](tests/src/Functional/Controller/CombatApiControllerTest.php) | Functional: combat API controller | Reviewed |
| [tests/src/Functional/Controller/CombatControllerTest.php](tests/src/Functional/Controller/CombatControllerTest.php) | Functional: combat controller | Reviewed |
| [tests/src/Functional/Controller/CombatEncounterApiControllerTest.php](tests/src/Functional/Controller/CombatEncounterApiControllerTest.php) | Functional: combat encounter API controller | Reviewed |
| [tests/src/Functional/Controller/CreditsControllerTest.php](tests/src/Functional/Controller/CreditsControllerTest.php) | Functional: credits controller | Reviewed |
| [tests/src/Functional/Controller/DashboardControllerTest.php](tests/src/Functional/Controller/DashboardControllerTest.php) | Functional: dashboard controller | Reviewed |
| [tests/src/Functional/Controller/DungeonControllerTest.php](tests/src/Functional/Controller/DungeonControllerTest.php) | Functional: dungeon controller | Reviewed |
| [tests/src/Functional/Controller/HexMapControllerTest.php](tests/src/Functional/Controller/HexMapControllerTest.php) | Functional: hex map controller | Reviewed |
| [tests/src/Functional/Controller/HomeControllerTest.php](tests/src/Functional/Controller/HomeControllerTest.php) | Functional: home controller | Reviewed |
| [tests/src/Functional/Controller/HowToPlayControllerTest.php](tests/src/Functional/Controller/HowToPlayControllerTest.php) | Functional: how-to-play controller | Reviewed |
| [tests/src/Functional/Controller/TestingPageControllerTest.php](tests/src/Functional/Controller/TestingPageControllerTest.php) | Functional: testing dashboard controller | Reviewed |
| [tests/src/Functional/Controller/WorldControllerTest.php](tests/src/Functional/Controller/WorldControllerTest.php) | Functional: world controller | Reviewed |
| [tests/src/Functional/EntityLifecycleTest.php](tests/src/Functional/EntityLifecycleTest.php) | Functional: entity lifecycle | Reviewed |
| [tests/src/Functional/Routes/AdminRoutesTest.php](tests/src/Functional/Routes/AdminRoutesTest.php) | Functional: admin routes | Reviewed |
| [tests/src/Functional/Routes/ApiRoutesTest.php](tests/src/Functional/Routes/ApiRoutesTest.php) | Functional: API routes | Reviewed |
| [tests/src/Functional/Routes/CampaignRoutesTest.php](tests/src/Functional/Routes/CampaignRoutesTest.php) | Functional: campaign routes | Reviewed |
| [tests/src/Functional/Routes/CharacterRoutesTest.php](tests/src/Functional/Routes/CharacterRoutesTest.php) | Functional: character routes | Reviewed |
| [tests/src/Functional/Routes/DemoRoutesTest.php](tests/src/Functional/Routes/DemoRoutesTest.php) | Functional: demo routes | Reviewed |
| [tests/src/Functional/Routes/PublicRoutesTest.php](tests/src/Functional/Routes/PublicRoutesTest.php) | Functional: public routes | Reviewed |
| [tests/src/Unit/Service/CharacterCalculatorTest.php](tests/src/Unit/Service/CharacterCalculatorTest.php) | Unit: character calculator | Updated |
| [tests/src/Unit/Service/CombatCalculatorTest.php](tests/src/Unit/Service/CombatCalculatorTest.php) | Unit: combat calculator | Updated |
| [tests/src/Unit/Service/GithubIssuePrClientTest.php](tests/src/Unit/Service/GithubIssuePrClientTest.php) | Unit: GitHub client dedupe/idempotency guards | New |
| [tests/src/Unit/Traits/FixtureLoaderTrait.php](tests/src/Unit/Traits/FixtureLoaderTrait.php) | Shared fixture helper trait | Updated |

## Dashboard / Testing Page

Access the testing dashboard at: `/dungeoncrawler/testing`

This page provides the primary testing workflow hub for documentation, stagegates, commands, and issue triage.

## Content Module Documentation

For installation, configuration, routes, permissions, database schema, and other runtime/product details, see the [dungeoncrawler_content module README](../dungeoncrawler_content/README.md).

## License

Proprietary - Forseti Life
