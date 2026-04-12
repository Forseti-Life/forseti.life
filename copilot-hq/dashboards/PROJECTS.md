# Projects Registry

Authoritative list of active **product lines** and **delivery projects** across the org.

The live authority page is:

- `https://forseti.life/roadmap`

That page is rendered from this file. CEO and architect seats must treat the roadmap page + this backing file as the single source of truth for what is on the active project list.

**Owned by:** ceo-copilot-2  
**Update cadence:** when a project is created, advanced, closed, or when a release picks up project-scoped work  
**Format:** one numbered portfolio registry. Every active item on the live roadmap page must have a `PROJ-*` ID. Use the `Type` column to distinguish long-lived product tracks from execution initiatives.

**Required per-project fields:** `Scope`, `Current state`, `Last scoped release`, `Progress SLA`, `Next step`, `Queue status`

---

## Registry

| ID | Name | Type | Product | Status | Priority | Lead | Started |
|---|---|---|---|---|---|---|---|
| PROJ-004 | Job Hunter | product line | forseti.life | active_buildout | P1 | pm-forseti | 2026-04-12 |
| PROJ-005 | AI Conversation | product line | forseti.life | foundation_in_place | P1 | pm-forseti | 2026-04-12 |
| PROJ-006 | Community Safety | product line | forseti.life | public_platform_track | P2 | pm-forseti | 2026-04-12 |
| PROJ-007 | Dungeoncrawler Product Track | product line | dungeoncrawler | separate_product_site | P1 | pm-dungeoncrawler | 2026-04-12 |
| PROJ-001 | LangGraph Console UI | delivery project | forseti.life | in_progress | P1 | pm-forseti | 2026-04-05 |
| PROJ-002 | QA Suite Completeness | delivery project | forseti.life | in_progress | P2 | pm-forseti / qa-forseti | 2026-04-09 |
| PROJ-003 | DungeonCrawler Roadmap Completion | delivery project | dungeoncrawler | in_progress | P1 | pm-dungeoncrawler | 2026-03-01 |

---

## PROJ-004 — Job Hunter

**Scope:** Forseti's job-seeking platform covering resume intake, discovery, application prep, submission support, and tracking.

**Current state:** Active Forseti release-e scope candidates already exist (`forseti-jobhunter-contact-referral-tracker`, `forseti-jobhunter-resume-version-tracker`) and PM now has a Stage 0 scope-activation item queued.

**Last scoped release:** `20260412-forseti-release-e`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** PM should activate release-e scope or explicitly re-baseline the current Forseti release.

**Queue status:** `pm-forseti` inbox item queued on 2026-04-12: `20260412-scope-activate-20260412-forseti-release-e`

---

## PROJ-005 — AI Conversation

**Scope:** Persistent assistant experience, conversation memory, model integration, and shared AI capability across Forseti products.

**Current state:** All foundation features shipped (user-chat, history-browser, export, job-suggestions, service-db-refactor, service-refactor, debug-gate, tailoring-feedback). Next slice defined: **Local LLM / Provider Selection** (`forseti-ai-local-llm-provider-selection`, Status: ready, Release: 20260412-forseti-release-e). Aligns with org mission to decentralize services — adds Ollama self-hosted provider option alongside OpenAI, with per-user provider preference and org-default admin config.

**Last scoped release:** `20260412-forseti-release-e`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Activate `forseti-ai-local-llm-provider-selection` in release-e when in_progress count drops below 10 (currently 7/10). BA grooming dispatch queued.

**Queue status:** ba-forseti AC brief queued 2026-04-12: `20260412-ai-local-llm-provider-ac-brief`

---

## PROJ-006 — Community Safety

**Scope:** Public safety content, maps, alerts, community participation, and member-support tooling.

**Current state:** Foundation modules `amisafe` (crime map + H3 analytics, `/amisafe`) and `safety_calculator` (per-user risk scoring, `/safety-calculator`) are production-complete and provide data-consumption tools. No community data-contribution capability exists. Next slice defined: **Community Incident Report** (`forseti-community-incident-report`, Status: ready, Release: 20260412-forseti-release-f). Adds authenticated user-submitted safety observations, public listing at `/community-reports`, and a toggleable AmISafe map layer — advancing the "community-managed" mission. Not queued in release-e (already 7/10 in_progress; this is P2). BA AC grooming dispatched.

**Last scoped release:** `20260412-forseti-release-f` (planned; not yet activated)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** BA grooms `forseti-community-incident-report` AC in release-f cycle. PM activates when release-e closes and release-f opens.

**Queue status:** ba-forseti AC brief queued 2026-04-12: `20260412-community-incident-report-ac-brief`

---

## PROJ-007 — Dungeoncrawler Product Track

**Scope:** The dedicated Dungeoncrawler product line, separate site, and its long-lived PF2E implementation program.

**Current state:** Separate product site with its own release engine, roadmap, and active delivery queue.

**Last scoped release:** `20260412-dungeoncrawler-release-e`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Use the dedicated Dungeoncrawler roadmap and the active release-e / release-f queue for current execution details.

**Queue status:** active items already queued for `pm-dungeoncrawler`, `dev-dungeoncrawler`, and `qa-dungeoncrawler`

---

## PROJ-001 — LangGraph Console UI

**Roadmap:** `features/forseti-langgraph-ui/roadmap.md`  
**Scope:** Build the full Copilot HQ control-plane console UI on forseti.life — telemetry, agent monitoring, session management, release controls, and eval scorecards wired to live orchestrator tick data.

**Current state (2026-04-12):** All foundation slices are shipped: telemetry foundation, console stubs (7 routes), context enrichment, Agent Tracker Core (`forseti-copilot-agent-tracker`), Console Build/Test sections (ahead of schedule), and Release Control Panel (read-only). Active release `20260412-forseti-release-e` carries the next slice: Run + Session panel wiring (`features/forseti-langgraph-console-run-session/feature.md`, Status: ready).

**Last scoped release:** `20260412-forseti-release-e`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** ba-forseti to elaborate AC, then dev-forseti to implement Run/Session wiring in forseti-release-e.

**Queue status:** Roadmap reconciled 2026-04-12. Feature stub created. BA dispatch queued.

---

## PROJ-002 — QA Suite Completeness

**Scope:** Build repeatable, executable QA coverage for shipped Forseti features and clean up stale suite shells so release verification is durable, automatable, and auditable.

**Status:** in_progress  
**Priority:** P2  
**Lead:** pm-forseti (dispatch), qa-forseti (execution)  
**Scope product:** forseti.life  
**Suite manifest:** `qa-suites/products/forseti/suite.json`

**Current state (2026-04-12 closeout audit):** Phase 1 (triage) is complete — the 6 PROJ-002 features shipped represent the triage classification work: 52 fill, 18 retire, 18 defer, 4 keep-as-is against the 94 suites that existed at triage time. However, Phase 2 (actually writing `test_cases` into `suite.json`) has NOT started. As of 2026-04-12, `suite.json` has **252 suites, only 2 with `test_cases`** (15 total). The suite count grew from 94 → 252 due to new feature suites added in releases since triage. The core problem (no executable regression tests) persists.

**Last scoped release:** `20260412-forseti-release-e`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** qa-forseti: execute Phase 2 — fill `test_cases` for the 52 priority suites identified in the triage report (`sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`), starting with the 27 CEO pre-classified `fill` candidates. Target: ≥2 test_cases per suite, sourced from existing QA outbox verification commands.

**Queue status:** qa-forseti Phase 2 dispatch queued 2026-04-12: `20260412-proj002-phase2-suite-fill`

### Problem

The forseti QA suite manifest has **86 registered suites but only 2 have populated `test_cases`** (15 total executable test cases). Feature verification is done inline by the qa-forseti agent during each release cycle — PASS/FAIL results live in session outboxes but are not recorded back into the manifest. This means:

- No repeatable automated runner: there is nothing to re-execute against a regression without re-reading old session outboxes
- E2E Playwright suite (`jobhunter-e2e`) has never run in automation — requires `FORSETI_COOKIE_AUTHENTICATED` env var not provisioned
- Cross-user isolation (TC-11, TC-16) is untestable with the current single-user test provisioning
- 84 empty suite shells accumulated from past releases create noise and give a false sense of coverage

### Goals

1. Every shipped forseti feature has at least one executable, re-runnable test case in `suite.json`
2. E2E Playwright pipeline unblocked — auth cookie provisioned automatically via `drush user:login`
3. Cross-user isolation covered by a second QA user (`qa_tester_authenticated_2`)
4. Stale/superseded suite shells retired or merged
5. `python3 scripts/qa-suite-validate.py` passes clean on every cycle

### Phases

#### Phase 1 — Triage (1 release cycle)
**Owner:** qa-forseti  
**Output:** Audit report in `sessions/qa-forseti/artifacts/suite-triage/`
**Status:** in_progress (dispatched 2026-04-09)

**Feature stubs created (2026-04-09):**
- `features/forseti-qa-suite-fill-release-f` — 16 release-f suites (ROI: 45)
- `features/forseti-qa-suite-fill-jobhunter-submission` — 2 submission suites (ROI: 45)
- `features/forseti-qa-suite-fill-agent-tracker` — 4 agent tracker suites (ROI: 45)
- `features/forseti-qa-suite-fill-controller-extraction` — 2 controller extraction suites (ROI: 45)
- `features/forseti-qa-suite-retire-stale` — 18 retire candidates (ROI: 40)
- `features/forseti-qa-e2e-auth-pipeline` — E2E Playwright auth unblock, release-h (ROI: 35)

**Dispatched (2026-04-09):**
- qa-forseti triage → `sessions/qa-forseti/inbox/20260409-proj002-suite-triage/` (ROI 60)
- ba-forseti: 6 grooming items (ROI 35–45)
- Pending: pm-qa-handoff.sh dispatch for each feature after ba-forseti delivers ACs

- Classify each of the 84 empty suites as one of:
  - `fill` — feature is shipped and actively in production; needs real test_cases
  - `retire` — feature superseded, removed, or merged into another suite; delete the shell
  - `defer` — feature exists but has no test plan yet; backlog for Phase 2
- Produce a triage table: suite ID → disposition → reason
- Target: identify the ~20–25 highest-value `fill` candidates (current shipped features)

**Priority `fill` candidates (known from recent releases):**
```
forseti-jobhunter-application-status-dashboard-static
forseti-jobhunter-application-status-dashboard-functional
forseti-jobhunter-google-jobs-ux-static
forseti-jobhunter-google-jobs-ux-functional
forseti-jobhunter-profile-completeness-static
forseti-jobhunter-profile-completeness-functional
forseti-jobhunter-resume-tailoring-display-static
forseti-jobhunter-resume-tailoring-display-functional
forseti-ai-conversation-user-chat-static
forseti-ai-conversation-user-chat-acl
forseti-ai-conversation-user-chat-csrf-post
forseti-jobhunter-application-submission-route-acl
forseti-jobhunter-application-submission-unit
forseti-copilot-agent-tracker-route-acl
forseti-copilot-agent-tracker-api
role-url-audit  (should point to site-audit-run.sh output)
```

**Retire candidates (superseded refactors):**
```
forseti-jobhunter-controller-refactor-static
forseti-jobhunter-controller-refactor-unit
forseti-jobhunter-controller-refactor-phase2-*  (6 suites — merged into split)
forseti-ai-service-refactor-*  (3 suites — superseded by db-refactor)
forseti-ai-debug-gate-*  (3 suites — debug gate removed)
```

#### Phase 2 — Fill Priority Suites (2–3 release cycles)
**Owner:** qa-forseti (with dev-forseti support for command construction)  
**Output:** `suite.json` updated with executable `test_cases` for all `fill` candidates

For each `fill` suite:
1. Read the feature's `03-test-plan.md` and prior QA outbox verification evidence
2. Extract the bash commands already run (they are in the outboxes — just needs transcription)
3. Write `test_cases` array: `id`, `description`, `type`, `command` (where automatable), `status`
4. Run `python3 scripts/qa-suite-validate.py` after each batch
5. Commit to HQ repo

**Success metric:** ≥ 40 executable test cases in `suite.json` (up from 15)

#### Phase 3 — E2E Playwright Unblock (1 release cycle)
**Owner:** dev-forseti  
**Output:** Automated auth cookie provisioning in the site-audit pipeline

Root cause: `FORSETI_COOKIE_AUTHENTICATED` env var is never set in automation because it requires a live session cookie. The `drush user:login` command CAN generate a one-time login link, and `curl -sc` CAN extract the session cookie — both already documented in the qa-forseti seat instructions.

**Fix approach:**
1. Add a helper step to `scripts/site-audit-run.sh` (or a companion script) that:
   - Runs `drush user:login --uid=<qa_tester_uid> --no-browser` to get a ULI
   - Follows the ULI with `curl -sc /tmp/forseti_qa.cookies` to capture the session cookie
   - Exports `FORSETI_COOKIE_AUTHENTICATED` from the cookie jar
2. Gate the helper behind `ALLOW_PROD_QA=1` (already present)
3. Wire the cookie into the role-matrix audit passes
4. Verify TC-12 (CSRF send-message) and TC-13 (route static) are machine-executable

**Acceptance criteria:**
- `bash scripts/site-audit-run.sh forseti-life` completes an authenticated-role pass without manual cookie injection
- `jobhunter-e2e` Playwright suite runs at least steps 1–5 end-to-end (step 6 = job submission, may require seed data)

#### Phase 4 — Cross-User Isolation Coverage (1 release cycle)
**Owner:** dev-forseti (infra), qa-forseti (test authoring)  
**Output:** `jhtr:qa-users-ensure` supports a second test user; TC-11 and TC-16 executable

- Extend `jhtr:qa-users-ensure` drush command to provision `qa_tester_authenticated_2`
- Add second-user session cookie provisioning to the E2E pipeline
- Write TC-11 (profile cross-user block) and TC-16 (e2e cross-user isolation) as executable suite entries
- These are HIGH severity as the bulk-archive MEDIUM finding this cycle demonstrates cross-user data risks exist

#### Phase 5 — Retire Stale Shells & Housekeeping (1 release cycle)
**Owner:** qa-forseti  
**Output:** Clean `suite.json` with no empty shells; `qa-suite-validate.py` passes

- Delete all `retire`-classified suite entries
- Ensure all remaining entries have at minimum `id`, `label`, `type`, `feature_id`, and at least 1 `test_cases` entry
- Update `role-url-audit` suite to reference `scripts/site-audit-run.sh` output directly
- Run final validation: `python3 scripts/qa-suite-validate.py`

### Success Criteria (project complete)

- [ ] 0 empty suite shells in `suite.json`
- [ ] ≥ 50 executable test cases across all suites
- [ ] E2E Playwright runs without manual cookie injection in CI/automated context
- [ ] Cross-user isolation (TC-11, TC-16) executable
- [ ] `qa-suite-validate.py` passes clean
- [ ] All release-f and later features have test_cases populated in the manifest

### KPI impact

- **Escaped defects**: executable regression suite means regressions are caught before Gate 2, not after
- **Audit freshness**: authenticated-role pass means ACL coverage includes job_hunter routes (currently skipped)
- **Post-merge regressions**: cross-user isolation tests catch the class of bug found this cycle (bulk-archive)

### Risks

| Risk | Mitigation |
|---|---|
| drush ULI cookie expires mid-run | Re-provision cookie at start of each site-audit invocation |
| Phase 2 requires reading 20+ old outboxes — high agent effort | Batch 5 suites per release cycle; prioritize by active use |
| qa-suite-validate.py may reject new command formats | Run validate after each batch; fix before committing |

### Related work

- **dev-forseti inbox:** `20260409-bulk-archive-global-status-mutation-release-f` — per-user archive fix (Phase 4 prerequisite)
- **KB lesson:** `knowledgebase/lessons/20260227-jobhunter-e2e-csrf-token-empty-save-job.md`
- **QA seat instructions:** `org-chart/agents/instructions/qa-forseti.instructions.md` (CSRF smoke check + E2E run steps already documented)

---

## PROJ-003 — DungeonCrawler Roadmap Completion

**Roadmap audit runbook:** `runbooks/roadmap-audit.md`  
**Scope:** Systematically implement all `pending` requirements in `dc_requirements` table until every requirement is either `implemented` or has a `feature_id` pointing to an active pipeline feature.

Current status: The public roadmap now derives requirement state from the live pipeline, and the release-cycle drift defect has been fixed. `20260412-dungeoncrawler-release-e` is the active runtime release with 5 scoped features in progress, while `20260412-dungeoncrawler-release-f` is groomed as the next wave. PM, Dev, and QA all have active queue items for release-e / release-f work, so the project currently has no queue gap.

**Last scoped release:** `20260412-dungeoncrawler-release-e`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** let release-e execution continue, watch for Gate 2 on the 5 active features, and then promote release-f only after the coordinated push/post-push handoff completes.

**Queue status:** active items already queued for `pm-dungeoncrawler`, `dev-dungeoncrawler`, and `qa-dungeoncrawler`

See `runbooks/roadmap-audit.md` for full query protocol and per-chapter status.

---

*Last updated: 2026-04-12 by ceo-copilot-2*
