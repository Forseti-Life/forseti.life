# Repository Issues Tracker

Internal issue tracker for repository work when GitHub issue creation is unavailable or rate-limited.
This file is also the backup tracker when CLI interface access is denied for creating GitHub issues.

## Status Values
- **Open**: Work is not completed.
- **Closed**: Work is completed and verified.

## Active Issues

### dungeoncrawler_tester

#### config

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### css

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0087 | Resolve identical `weight: 91` collision for `dashboard_main_docs` and `settings_under_dashboard_main` in dungeoncrawler_tester.links.menu.yml | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0009 audit; sibling menu links share identical weight, causing unstable ordering and UX drift risk. |
| DCT-0088 | Remove redundant self-referential Documentation Home link from DOCUMENTATION_HOME.md supporting references | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0005 audit; page links to itself in “Supporting References,” creating low-value duplicate navigation. |
| DCT-0089 | Add CSRF protection requirements to tester POST AJAX routes in dungeoncrawler_tester.routing.yml | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0011 audit; multiple mutative POST routes currently rely only on permission checks and should include explicit `_csrf_token` protection. |
| DCT-0090 | Re-evaluate generic public path `/thetest` for collision and discoverability risk | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0011 audit; unscoped root path may conflict with other modules and is harder to trace operationally than a namespaced route. |
#### js

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0091 | Add endpoint presence guards before fetch calls in js/queue-management.js | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0014 audit; `run/status/logs` calls assume endpoint values exist and can fail noisily when settings are partial/missing. |
| DCT-0092 | Replace ad-hoc `context.__dcQueueInit` behavior guard with `once()`-based initialization in js/queue-management.js | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0014 audit; custom context flag can be brittle across Drupal/AJAX attach cycles and risks duplicate event bindings. |
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0093 | Externalize hard-coded `SIMPLETEST_DB` credentials from phpunit.xml into environment-local test configuration | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0015 audit; committed test DB credentials create security and portability risk across environments. |
| DCT-0094 | Remove duplicated “GitHub issue automation (failures)” section from tester README to prevent documentation drift | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0017 audit; same section appears twice with overlapping guidance and can diverge over time. |
| DCT-0095 | Align tester README dashboard URL with routed path (`/dungeoncrawler/testing`) and remove stale `/testing` reference | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0017 audit; README contains inconsistent dashboard URLs that can misdirect operators. |
#### scripts

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0096 | Add pagination support to safe_close_candidates_report.sh GitHub API calls to avoid truncated >100 item analysis | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0018 audit; `per_page=100` without page iteration can silently miss issues/PRs in larger repos. |
#### src

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0097 | Validate stage identifiers in StageControlCommands against known stage definitions before state writes | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0019 audit; current commands accept arbitrary stage IDs, allowing accidental orphan state entries. |
| DCT-0098 | Increase queue runner lock lease strategy in TestingQueueCommands to cover long-running processing windows | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0020 audit; fixed 30s lock can expire while processing continues, allowing concurrent runners. |
| DCT-0099 | Replace unsafe `unserialize()` payload handling in tester controllers with safe decode strategy (`allowed_classes=false` / structured decoding) | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0021/DCT-0022 audits; queue/watchdog payload decoding uses unserialize patterns that should be hardened. |
| DCT-0100 | Refactor tester controllers to inject services instead of static `\Drupal::...` calls for cache/CSRF access | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0022 audit; static service lookups reduce testability and increase hidden coupling across the large controller. |
| DCT-0101 | Decompose oversized TestingDashboardController into focused controllers/services (docs, report, automation actions) | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0022 audit; single controller has very high responsibility breadth and maintenance risk. |
| DCT-0102 | Replace hard-coded TheTestController pass/fail constant with environment-aware or config-gated toggle strategy | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0023 audit; behavior currently requires source edits to flip test state, increasing accidental commit risk. |
| DCT-0103 | Decompose oversized DashboardRunsForm into smaller form components/services per stage/control responsibility | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0025 audit; single form class is large and multiplexes queueing, controls, and reporting concerns. |
| DCT-0104 | Sanitize dynamic status/failure strings rendered via `#markup`/`#description` in DashboardRunsForm | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0025 audit; stage-derived text (including failure excerpts) is inserted as markup and should be escaped/structured safely. |
| DCT-0105 | Decompose SdlcResetForm into dedicated reset planner/executor services to reduce high-risk orchestration complexity | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0027 audit; form class combines preview, GitHub mutation orchestration, and state-reset logic in one large surface area. |
| DCT-0106 | Move GitHub token handling out of plain module config in TesterSettingsForm to a secret-storage pattern | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0028 audit; storing token in config risks accidental exposure through config export/review workflows. |
| DCT-0107 | Resolve TheTest status source mismatch between TheTestToggleForm state writes and TheTestController constant rendering | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0029 audit; toggle form updates state key but controller output is hard-coded, so UI toggle has no effect. |
| DCT-0108 | Replace hard-coded GitHub issue URL in TesterNavBlock with configured repository context | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0030 audit; nav block links to a fixed repository query instead of using current tester GitHub repo configuration. |
| DCT-0109 | Harden TesterRunQueueWorker simpletest directory creation with explicit failure handling and operator-visible diagnostics | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0031 audit; `mkdir()` path setup is not checked for failures, which can hide environment/permission problems before test execution. |
| DCT-0110 | Namespace GitHub client cooldown/dedupe state files by site/repository to avoid cross-site `/tmp` interference | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0032 audit; global temp-file names can collide across multiple Drupal sites/processes on the same host. |
| DCT-0111 | Split GithubIssuePrClient into focused components (context resolution, transport, retry/throttle state) to reduce monolithic complexity | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0032/DCT-0033 audits; current client interface and implementation concentrate many responsibilities, increasing maintenance and test burden. |
| DCT-0112 | Add lock/serialization guard to StageAutoEnqueueService to prevent duplicate queueing under concurrent invocations | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0034 audit; enqueue flow has no explicit cross-process guard, so overlapping runs can race on state checks. |
| DCT-0113 | Replace hard-coded GitHub sign-off issue link in StageDefinitionService with repo-aware dynamic generation | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0035 audit; stage definitions contain fixed repository URL instead of deriving from configured tester repository context. |
| DCT-0114 | Optimize StageIssueSyncService issue status sync to reduce per-issue API call fan-out and improve failure observability | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0036 audit; sync loop performs serial issue GET calls and treats fetch failures as open without distinct tracking for operator diagnosis. |
#### templates

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
#### tests

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCT-0115 | Consolidate conflicting `umask` strategy in tests/bootstrap.php and document one deterministic permission model | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0039 audit; bootstrap sets `umask(0)` and later `umask(0002)`, creating ambiguous file-permission behavior. |
| DCT-0116 | Reconcile level_1_wizard fixture AC data (`equipment.ac_bonus`) with `calculated_stats.ac_breakdown` and test formulas | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0041 audit; fixture includes armor bonus in equipment while AC breakdown/formula treat armor contribution as zero. |
| DCT-0117 | Reconcile level_5_rogue fixture armor bonus semantics between `equipment.ac_bonus` and `calculated_stats.ac_breakdown.armor` | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0042 audit; fixture encodes different armor contribution values in parallel fields, risking ambiguous test expectations. |
| DCT-0118 | Standardize ancestry HP field naming across test fixtures (`hit_points` vs `hp_bonus`) for consistent calculator inputs | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0044 audit; ancestry fixture schema uses `hit_points` while character fixtures rely on `hp_bonus`, creating mapping ambiguity. |
| DCT-0119 | Standardize class key-ability field naming across fixtures (`key_ability` vs `key_abilities`) to avoid parser ambiguity | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0046 audit; class schema fixtures use singular field name while character fixtures use plural variant. |
| DCT-0120 | Reconcile contradictory setup commands and status statements in tests/README.md | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0047 audit; document lists overlapping setup scripts and mixed implementation-status claims that can mislead test operators. |
| DCT-0121 | Remove hard-coded `/tmp` dependency and add explicit filesystem error handling in TestEnvironmentSetup bootstrap extension | Open | Unassigned | 2026-02-16 | 2026-02-16 | Discovered during DCT-0048 audit; setup uses fixed temp path and unchecked `mkdir`/`chmod` calls, reducing portability and diagnosability. |
| DCT-0049 | Review file tests/src/Functional/CampaignStateAccessTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0050 | Review file tests/src/Functional/CampaignStateValidationTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0051 | Review file tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0052 | Review file tests/src/Functional/Controller/AboutControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0053 | Review file tests/src/Functional/Controller/CampaignControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0054 | Review file tests/src/Functional/Controller/CharacterApiControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0055 | Review file tests/src/Functional/Controller/CharacterCreationControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0056 | Review file tests/src/Functional/Controller/CharacterCreationStepControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0057 | Review file tests/src/Functional/Controller/CharacterListControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0058 | Review file tests/src/Functional/Controller/CharacterStateControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0059 | Review file tests/src/Functional/Controller/CharacterViewControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0060 | Review file tests/src/Functional/Controller/CombatActionControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0061 | Review file tests/src/Functional/Controller/CombatApiControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0062 | Review file tests/src/Functional/Controller/CombatControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0063 | Review file tests/src/Functional/Controller/CombatEncounterApiControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0064 | Review file tests/src/Functional/Controller/CreditsControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0065 | Review file tests/src/Functional/Controller/DashboardControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0066 | Review file tests/src/Functional/Controller/DungeonControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0067 | Review file tests/src/Functional/Controller/HexMapControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0068 | Review file tests/src/Functional/Controller/HexMapUiStageGateTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0069 | Review file tests/src/Functional/Controller/HomeControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0070 | Review file tests/src/Functional/Controller/HowToPlayControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0071 | Review file tests/src/Functional/Controller/TestingDashboardControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0072 | Review file tests/src/Functional/Controller/TestingPageControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0073 | Review file tests/src/Functional/Controller/WorldControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0074 | Review file tests/src/Functional/EntityLifecycleTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0075 | Review file tests/src/Functional/Routes/AdminRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0076 | Review file tests/src/Functional/Routes/ApiRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0077 | Review file tests/src/Functional/Routes/CampaignRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0078 | Review file tests/src/Functional/Routes/CharacterRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0079 | Review file tests/src/Functional/Routes/DemoRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0080 | Review file tests/src/Functional/Routes/PublicRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0081 | Review file tests/src/Functional/TheTestPageTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0082 | Review file tests/src/Unit/Service/CharacterCalculatorTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0083 | Review file tests/src/Unit/Service/CombatCalculatorTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0084 | Review file tests/src/Unit/Service/GithubIssuePrClientTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0085 | Review file tests/src/Unit/Traits/FixtureLoaderTrait.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCT-0086 | Review file tests/TESTING_MODULE_README.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
### dungeoncrawler_content

#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0001 | Review file API_DOCUMENTATION.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### characters

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0002 | Review file characters/gribbles-rindsworth.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### config

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0003 | Review file config/examples/level-1-goblin-warrens.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0004 | Review file config/examples/tavern-entrance-dungeon.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0005 | Review file config/examples/tavern-obstacle-objects.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0006 | Review file config/schemas/campaign.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0007 | Review file config/schemas/character.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0008 | Review file config/schemas/character_options_step1.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0009 | Review file config/schemas/character_options_step2.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0010 | Review file config/schemas/character_options_step3.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0011 | Review file config/schemas/character_options_step4.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0012 | Review file config/schemas/character_options_step5.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0013 | Review file config/schemas/character_options_step6.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0014 | Review file config/schemas/character_options_step7.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0015 | Review file config/schemas/character_options_step8.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0016 | Review file config/schemas/creature.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0017 | Review file config/schemas/dungeon_level.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0018 | Review file config/schemas/encounter.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0019 | Review file config/schemas/entity_instance.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0020 | Review file config/schemas/hazard.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0021 | Review file config/schemas/hexmap.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0022 | Review file config/schemas/item.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0023 | Review file config/schemas/obstacle.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0024 | Review file config/schemas/obstacle_object_catalog.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0025 | Review file config/schemas/party.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0026 | Review file config/schemas/README.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0027 | Review file config/schemas/room.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0028 | Review file config/schemas/trap.schema.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### content

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0029 | Review file content/creatures/goblin_warrior.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0030 | Review file content/items/healing_potion_minor.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0031 | Review file content/items/longsword.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0032 | Review file content/traps/arrow_trap_simple.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### css

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0033 | Review file css/character-creation.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0034 | Review file css/character-sheet.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0035 | Review file css/character-steps.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0036 | Review file css/credits.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0037 | Review file css/dungeoncrawler-content.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0038 | Review file css/game-cards.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0039 | Review file css/hexmap.css for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0040 | Review file dungeoncrawler_content.info.yml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0041 | Review file dungeoncrawler_content.install for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0042 | Review file dungeoncrawler_content.libraries.yml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0043 | Review file dungeoncrawler_content.links.menu.yml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0044 | Review file dungeoncrawler_content.module for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0045 | Review file dungeoncrawler_content.permissions.yml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0046 | Review file dungeoncrawler_content.routing.yml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0047 | Review file dungeoncrawler_content.services.yml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0048 | Review file ENHANCED_CHARACTER_SHEET_STUBS.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0049 | Review file HEXMAP_ARCHITECTURE.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0050 | Review file IMPLEMENTATION_SUMMARY.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### js

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0051 | Review file js/character-creation-schema.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0052 | Review file js/character-creation.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0053 | Review file js/character-sheet.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0054 | Review file js/character-state-service.ts for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0055 | Review file js/character-step-1.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0056 | Review file js/character-step-2.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0057 | Review file js/character-step-3.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0058 | Review file js/character-step-4.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0059 | Review file js/character-step-5.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0060 | Review file js/character-step-6.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0061 | Review file js/character-step-7.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0062 | Review file js/character-step-8.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0063 | Review file js/ecs/Component.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0064 | Review file js/ecs/components/ActionsComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0065 | Review file js/ecs/components/CombatComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0066 | Review file js/ecs/components/IdentityComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0067 | Review file js/ecs/components/MovementComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0068 | Review file js/ecs/components/PositionComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0069 | Review file js/ecs/components/RenderComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0070 | Review file js/ecs/components/StatsComponent.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0071 | Review file js/ecs/Entity.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0072 | Review file js/ecs/EntityManager.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0073 | Review file js/ecs/index.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0074 | Review file js/ecs/System.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0075 | Review file js/ecs/systems/CombatSystem.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0076 | Review file js/ecs/systems/MovementSystem.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0077 | Review file js/ecs/systems/RenderSystem.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0078 | Review file js/ecs/systems/TurnManagementSystem.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0079 | Review file js/game-cards.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0080 | Review file js/hexmap-api.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0081 | Review file js/hexmap.js for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0082 | Review file js/types/character-state.types.ts for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### root docs/meta

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0083 | Review file phpunit.xml for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0084 | Review file README.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### src

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0085 | Review file src/Access/CampaignAccessCheck.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0086 | Review file src/Access/CharacterAccessCheck.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0087 | Review file src/Controller/AboutController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0088 | Review file src/Controller/CampaignController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0089 | Review file src/Controller/CampaignEntityController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0090 | Review file src/Controller/CampaignStateController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0091 | Review file src/Controller/CharacterApiController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0092 | Review file src/Controller/CharacterCreationController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0093 | Review file src/Controller/CharacterCreationStepController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0094 | Review file src/Controller/CharacterListController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0095 | Review file src/Controller/CharacterStateController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0096 | Review file src/Controller/CharacterViewController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0097 | Review file src/Controller/CombatActionController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0098 | Review file src/Controller/CombatApiController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0099 | Review file src/Controller/CombatController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0100 | Review file src/Controller/CombatEncounterApiController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0101 | Review file src/Controller/ControllerArchitectureController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0102 | Review file src/Controller/CreditsController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0103 | Review file src/Controller/DashboardController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0104 | Review file src/Controller/DungeonController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0105 | Review file src/Controller/DungeonStateController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0106 | Review file src/Controller/HexMapController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0107 | Review file src/Controller/HomeController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0108 | Review file src/Controller/HowToPlayController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0109 | Review file src/Controller/RoomStateController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0110 | Review file src/Controller/TestingDashboardController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0111 | Review file src/Controller/TestingPageController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0112 | Review file src/Controller/WorldController.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0113 | Review file src/Exception/CharacterException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0114 | Review file src/Exception/CharacterNotFoundException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0115 | Review file src/Exception/DungeonCrawlerException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0116 | Review file src/Exception/InvalidCharacterDataException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0117 | Review file src/Exception/RulesValidationException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0118 | Review file src/Exception/SchemaException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0119 | Review file src/Exception/SchemaLoadException.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0120 | Review file src/Form/CampaignCreateForm.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0121 | Review file src/Form/CharacterCreateForm.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0122 | Review file src/Form/CharacterCreationStepForm.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0123 | Review file src/Form/CharacterDeleteForm.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0124 | Review file src/Form/DungeonCrawlerSettingsForm.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0125 | Review file src/Service/ActionProcessor.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0126 | Review file src/Service/Calculator.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0127 | Review file src/Service/CampaignContentService.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0128 | Review file src/Service/CampaignStateService.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0129 | Review file src/Service/CharacterCalculator.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0130 | Review file src/Service/CharacterManager.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0131 | Review file src/Service/CharacterStateService.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0132 | Review file src/Service/CombatCalculator.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0133 | Review file src/Service/CombatEncounterStore.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0134 | Review file src/Service/CombatEngine.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0135 | Review file src/Service/ConditionManager.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0136 | Review file src/Service/CONTENT_SYSTEM_README.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0137 | Review file src/Service/ContentGenerator.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0138 | Review file src/Service/ContentQuery.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0139 | Review file src/Service/ContentRegistry.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0140 | Review file src/Service/DungeonCache.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0141 | Review file src/Service/DungeonGenerationEngine.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0142 | Review file src/Service/DungeonStateService.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0143 | Review file src/Service/EncounterBalancer.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0144 | Review file src/Service/GameContentManager.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0145 | Review file src/Service/HPManager.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0146 | Review file src/Service/ReactionHandler.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0147 | Review file src/Service/RoomConnectionAlgorithm.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0148 | Review file src/Service/RoomStateService.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0149 | Review file src/Service/RulesEngine.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0150 | Review file src/Service/SchemaLoader.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0151 | Review file src/Service/StateManager.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0152 | Review file src/Service/StateValidationService.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### templates

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0153 | Review file templates/campaign-list.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0154 | Review file templates/campaign-tavernentrance.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0155 | Review file templates/character-class-card.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0156 | Review file templates/character-creation-step.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0157 | Review file templates/character-creation-wizard.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0158 | Review file templates/character-list.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0159 | Review file templates/character-sheet.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0160 | Review file templates/character-step-1.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0161 | Review file templates/character-step-2.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0162 | Review file templates/character-step-3.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0163 | Review file templates/character-step-4.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0164 | Review file templates/character-step-5.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0165 | Review file templates/character-step-6.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0166 | Review file templates/character-step-7.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0167 | Review file templates/character-step-8.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0168 | Review file templates/credits-page.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0169 | Review file templates/dungeon-card.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0170 | Review file templates/hexmap-demo.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0171 | Review file templates/item-card.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0172 | Review file templates/management-form-page.html.twig for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
#### tests

| ID | Title | Current Status | Owner | Created | Last Updated | Notes |
|---|---|---|---|---|---|---|
| DCC-0173 | Review file tests/fixtures/campaigns/active_campaign_state.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0174 | Review file tests/fixtures/campaigns/basic_campaign_state.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0175 | Review file tests/fixtures/characters/level_1_fighter.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0176 | Review file tests/fixtures/characters/level_1_wizard.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0177 | Review file tests/fixtures/characters/level_5_rogue.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0178 | Review file tests/fixtures/entities/goblin_warrior.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0179 | Review file tests/fixtures/entities/skeleton_archer.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0180 | Review file tests/fixtures/pf2e_reference/core_mechanics.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0181 | Review file tests/fixtures/schemas/ancestries_test.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0182 | Review file tests/fixtures/schemas/backgrounds_test.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0183 | Review file tests/fixtures/schemas/classes_test.json for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0184 | Review file tests/FUNCTIONAL_TEST_BEST_PRACTICES.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0185 | Review file tests/README.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0186 | Review file tests/src/Functional/CampaignStateAccessTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0187 | Review file tests/src/Functional/CampaignStateValidationTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0188 | Review file tests/src/Functional/CharacterCreation/CharacterCreationWorkflowTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0189 | Review file tests/src/Functional/Controller/AboutControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0190 | Review file tests/src/Functional/Controller/CampaignControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0191 | Review file tests/src/Functional/Controller/CharacterApiControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0192 | Review file tests/src/Functional/Controller/CharacterCreationControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0193 | Review file tests/src/Functional/Controller/CharacterCreationStepControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0194 | Review file tests/src/Functional/Controller/CharacterListControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0195 | Review file tests/src/Functional/Controller/CharacterStateControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0196 | Review file tests/src/Functional/Controller/CharacterViewControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0197 | Review file tests/src/Functional/Controller/CombatActionControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0198 | Review file tests/src/Functional/Controller/CombatApiControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0199 | Review file tests/src/Functional/Controller/CombatControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0200 | Review file tests/src/Functional/Controller/CombatEncounterApiControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0201 | Review file tests/src/Functional/Controller/CreditsControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0202 | Review file tests/src/Functional/Controller/DashboardControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0203 | Review file tests/src/Functional/Controller/DungeonControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0204 | Review file tests/src/Functional/Controller/HexMapControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0205 | Review file tests/src/Functional/Controller/HomeControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0206 | Review file tests/src/Functional/Controller/HowToPlayControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0207 | Review file tests/src/Functional/Controller/TestingPageControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0208 | Review file tests/src/Functional/Controller/WorldControllerTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0209 | Review file tests/src/Functional/EntityLifecycleTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0210 | Review file tests/src/Functional/Routes/AdminRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0211 | Review file tests/src/Functional/Routes/ApiRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0212 | Review file tests/src/Functional/Routes/CampaignRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0213 | Review file tests/src/Functional/Routes/CharacterRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0214 | Review file tests/src/Functional/Routes/DemoRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0215 | Review file tests/src/Functional/Routes/PublicRoutesTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0216 | Review file tests/src/Functional/Traits/JsonRequestTrait.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0217 | Review file tests/src/Functional/Traits/TestDataBuilderTrait.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0218 | Review file tests/src/Functional/Traits/TestDataFactoryTrait.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0219 | Review file tests/src/Functional/Traits/TestFixtureTrait.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0220 | Review file tests/src/Unit/Service/CharacterCalculatorTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0221 | Review file tests/src/Unit/Service/CombatCalculatorTest.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0222 | Review file tests/src/Unit/Traits/FixtureLoaderTrait.php for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |
| DCC-0223 | Review file tests/TESTING_MODULE_README.md for opportunities for improvment and refactoring | Open | Unassigned | 2026-02-16 | 2026-02-16 | Auto-generated per-file review/refactor tracking issue. |

## Closed Issues

| ID | Title | Current Status | Owner | Closed | Notes |
|---|---|---|---|---|---|
| DCT-0048 | Review file tests/src/Extension/TestEnvironmentSetup.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0121 for temp-path portability and filesystem operation hardening. |
| DCT-0045 | Review file tests/fixtures/schemas/backgrounds_test.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; schema fixture entries are consistent for current test usage, no immediate refactor issue identified. |
| DCT-0046 | Review file tests/fixtures/schemas/classes_test.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0119 for key-ability field-name consistency across fixtures. |
| DCT-0047 | Review file tests/README.md for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0120 for setup/status documentation consistency. |
| DCT-0042 | Review file tests/fixtures/characters/level_5_rogue.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0117 for armor contribution field consistency in fixture data. |
| DCT-0043 | Review file tests/fixtures/pf2e_reference/core_mechanics.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; reference content is internally consistent for current test usage, no immediate refactor issue identified. |
| DCT-0044 | Review file tests/fixtures/schemas/ancestries_test.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0118 for HP field naming consistency across fixtures. |
| DCT-0039 | Review file tests/bootstrap.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0115 for conflicting bootstrap permission strategy (`umask`) setup. |
| DCT-0040 | Review file tests/fixtures/characters/level_1_fighter.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; fixture structure and expected-stat test data are consistent, no immediate refactor issue identified. |
| DCT-0041 | Review file tests/fixtures/characters/level_1_wizard.json for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0116 for AC/equipment fixture consistency mismatch. |
| DCT-0037 | Review file templates/dungeoncrawler-tester-queue-management.html.twig for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; queue-management template structure and escaping behavior are consistent, no immediate refactor issue identified. |
| DCT-0038 | Review file TESTING.md for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; testing guidance is consistent with current workflow and no immediate refactor issue was identified. |
| DCT-0034 | Review file src/Service/StageAutoEnqueueService.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0112 for concurrent enqueue race hardening. |
| DCT-0035 | Review file src/Service/StageDefinitionService.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0113 for repository-aware sign-off link generation. |
| DCT-0036 | Review file src/Service/StageIssueSyncService.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0114 for API fan-out reduction and sync-failure visibility improvements. |
| DCT-0031 | Review file src/Plugin/QueueWorker/TesterRunQueueWorker.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0109 for simpletest directory creation failure handling. |
| DCT-0032 | Review file src/Service/GithubIssuePrClient.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issues logged as DCT-0110 (temp-file state isolation) and DCT-0111 (service decomposition). |
| DCT-0033 | Review file src/Service/GithubIssuePrClientInterface.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0111 for interface/service responsibility decomposition. |
| DCT-0030 | Review file src/Plugin/Block/TesterNavBlock.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0108 for hard-coded repository issue-link URL. |
| DCT-0029 | Review file src/Form/TheTestToggleForm.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0107 for toggle-state/controller-status mismatch. |
| DCT-0027 | Review file src/Form/SdlcResetForm.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0105 for separating reset planning/execution concerns from form handling. |
| DCT-0028 | Review file src/Form/TesterSettingsForm.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0106 for token secret-storage hardening beyond plain config persistence. |
| DCT-0025 | Review file src/Form/DashboardRunsForm.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issues logged as DCT-0103 (form decomposition) and DCT-0104 (dynamic markup sanitization). |
| DCT-0026 | Review file src/Form/DeadValueCloseForm.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; GitHub client usage and close-flow handling are consistent, no immediate refactor issue identified. |
| DCT-0023 | Review file src/Controller/TheTestController.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0102 for hard-coded status toggle requiring source-code edits. |
| DCT-0024 | Review file src/Form/CronAgentsControlForm.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; form wiring and config save flow are straightforward with no immediate refactor issue identified. |
| DCT-0021 | Review file src/Controller/QueueManagementController.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0099 for safer payload decoding in queue/log data handling. |
| DCT-0022 | Review file src/Controller/TestingDashboardController.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issues logged as DCT-0099 (payload decode hardening), DCT-0100 (static service calls), and DCT-0101 (controller decomposition). |
| DCT-0017 | Review file README.md for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issues logged as DCT-0094 (duplicate section) and DCT-0095 (dashboard URL mismatch). |
| DCT-0018 | Review file scripts/safe_close_candidates_report.sh for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0096 for missing pagination across GitHub API list/search queries. |
| DCT-0019 | Review file src/Commands/StageControlCommands.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0097 for missing stage-ID validation before state mutation. |
| DCT-0020 | Review file src/Commands/TestingQueueCommands.php for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0098 for queue-runner lock lease duration/concurrency risk. |
| DCT-0015 | Review file phpunit.xml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0093 for hard-coded test database credential externalization. |
| DCT-0016 | Review file PROCESS_FLOW.md for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; process-flow documentation is structurally consistent with current automation model, no immediate refactor issue identified. |
| DCT-0013 | Review file js/dead-value-actions.js for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; request/CSRF and action handling are coherent, no immediate safe refactor issue identified. |
| DCT-0014 | Review file js/queue-management.js for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issues logged as DCT-0091 (missing endpoint guards) and DCT-0092 (non-standard init guard). |
| DCT-0011 | Review file dungeoncrawler_tester.routing.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issues logged as DCT-0089 (missing CSRF requirements on POST routes) and DCT-0090 (unscoped `/thetest` path risk). |
| DCT-0012 | Review file dungeoncrawler_tester.services.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; service definitions and aliases are internally consistent, no immediate refactor issue identified. |
| DCT-0005 | Review file DOCUMENTATION_HOME.md for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0088 for redundant self-referential documentation link. |
| DCT-0006 | Review file drush.services.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; service definitions and command wiring are consistent, no immediate refactor issue identified. |
| DCT-0007 | Review file dungeoncrawler_tester.info.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; metadata and dependency declarations are valid for current module scope. |
| DCT-0008 | Review file dungeoncrawler_tester.libraries.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; asset library definitions are coherent and aligned with module usage. |
| DCT-0009 | Review file dungeoncrawler_tester.links.menu.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; follow-up issue logged as DCT-0087 for sibling menu-weight collision in main menu links. |
| DCT-0010 | Review file dungeoncrawler_tester.module for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Audit completed; hook implementations are operationally consistent, no immediate safe refactor issue identified. |
| DCT-0003 | Review file css/dashboard.css for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Removed duplicate `.stage-grid` selector declaration to keep dashboard layout rules single-sourced without behavior changes. |
| DCT-0004 | Review file css/queue-management.css for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Reviewed queue page styles; selector structure and responsive behavior are consistent, so no safe refactor was required this pass. |
| DCT-0001 | Review file config/install/dungeoncrawler_tester.settings.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Reviewed install defaults; configuration values and types are consistent with module schema and require no functional refactor. |
| DCT-0002 | Review file config/schema/dungeoncrawler_tester.schema.yml for opportunities for improvment and refactoring | Closed | Unassigned | 2026-02-16 | Reviewed schema mapping; field definitions correctly match installed config keys and types with no safe simplification needed. |
| CLI-006 | Add runbook and automated tests for rate-limit handling | Closed | Unassigned | 2026-02-16 | Focused unit coverage executed successfully via `../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml modules/custom/dungeoncrawler_tester/tests/src/Unit/Service/GithubIssuePrClientTest.php` (2 tests, 17 assertions). Non-blocking environment warnings: `chmod(): Operation not permitted` from test environment setup and PHPUnit deprecations. |
| CLI-003 | Add idempotency and dedupe guards for issue/PR mutations | Closed | Unassigned | 2026-02-16 | Implemented in `GithubIssuePrClient`: duplicate issue/PR close/comment mutation calls are suppressed within a short dedupe window. |
| CLI-007 | Document GitHub API and CLI rate limits in-module | Closed | Unassigned | 2026-02-16 | Added authoritative GitHub API/CLI rate-limit documentation and `gh api rate_limit` guidance in tester module runbook docs (`README.md` + `TESTING.md`). |
| CLI-004 | Centralize GitHub API calls behind rate-limit-safe client | Closed | Unassigned | 2026-02-16 | Centralized client now powers active tester GitHub integration flows (service/forms/queue worker/controller) with shared request/mutation handling. |
| CLI-005 | Add circuit breaker and cooldown mode after rate-limit failures | Closed | Unassigned | 2026-02-16 | Implemented in `GithubIssuePrClient`: repeated mutative 403/429 responses trigger cooldown mode that temporarily pauses mutative automation attempts. |
| CLI-001 | Serialize and throttle GitHub mutative requests | Closed | Unassigned | 2026-02-16 | Implemented in `GithubIssuePrClient`: cross-process mutation lock + minimum 1-second spacing for mutative API calls. |
| CLI-002 | Handle secondary rate limits with Retry-After and backoff | Closed | Unassigned | 2026-02-16 | Implemented in `GithubIssuePrClient`: 403/429 retry handling honoring `Retry-After` and `X-RateLimit-Reset` with exponential backoff + jitter fallback. |

## Update Workflow
1. Add new items under **Active Issues** with status **Open**.
2. Keep **Last Updated** current when scope/status changes.
3. When complete, move row to **Closed Issues** and set status to **Closed**.
4. Link related commits/PRs/issues in **Notes** when available.
