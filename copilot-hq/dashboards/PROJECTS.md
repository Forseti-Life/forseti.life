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
| PROJ-007 | Dungeoncrawler Product Track | product line | dungeoncrawler | separate_product_site | P1 | pm-dungeoncrawler | 2026-04-13 |
| PROJ-008 | Forseti Accounting Pipeline | delivery project | forseti.life | in_progress | P1 | accountant-forseti | 2026-04-13 |
| PROJ-001 | LangGraph Console UI | delivery project | forseti.life | in_progress | P1 | pm-forseti | 2026-04-05 |
| PROJ-002 | QA Suite Completeness | delivery project | forseti.life | in_progress | P2 | pm-forseti / qa-forseti | 2026-04-09 |
| PROJ-003 | DungeonCrawler Roadmap Completion | delivery project | dungeoncrawler | in_progress | P1 | pm-dungeoncrawler | 2026-03-01 |

---

## PROJ-004 — Job Hunter

**Scope:** Forseti's job-seeking platform covering resume intake, discovery, application prep, submission support, and tracking.

**Current state (2026-04-13):** Active buildout. Release-h carries 4 features in_progress: `forseti-jobhunter-interview-outcome-tracker` (high), `forseti-jobhunter-offer-tracker` (high), `forseti-jobhunter-application-analytics` (medium), `forseti-jobhunter-follow-up-reminders` (medium). Seven additional groomed features re-baselined to `ready` for future releases: `contact-tracker`, `company-interest-tracker`, `company-research-tracker`, `contact-referral-tracker`, `job-board-preferences`, `resume-version-labeling`, `resume-version-tracker`.

**Last scoped release:** `20260412-forseti-release-h`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Await dev-forseti completion + Gate 2 QA on 4 active release-h features. After coordinated push, activate next slice from 7 queued ready features by priority.

**Queue status:** 4 features in_progress for `20260412-forseti-release-h`. Dev + QA inbox items dispatched 2026-04-13.

---

## PROJ-005 — AI Conversation

**Scope:** Persistent assistant experience, conversation memory, model integration, and shared AI capability across Forseti products.

**Current state (2026-04-13):** All foundation features shipped. Next slice fully groomed: **Local LLM / Provider Selection** (`forseti-ai-local-llm-provider-selection`, Status: ready, Release: 20260412-forseti-release-h). AC, impl notes stub, and test plan created 2026-04-13. BA dispatched to complete impl notes (5 outstanding items). Feature is activation-ready once BA elaboration is complete and release-h in_progress count allows.

**Last scoped release:** `20260412-forseti-release-h` (targeted; not yet activated — pending BA impl notes + release slot)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** ba-forseti to complete `02-implementation-notes.md` (confirm AIApiService constructor, streaming approach, config keys, user field type, OpenAI model options). PM activates in release-h or next cycle based on slot availability.

**Queue status:** ba-forseti grooming dispatch: `sessions/ba-forseti/inbox/20260413-groom-forseti-ai-local-llm-provider-selection/` (ROI 30)

---

## PROJ-006 — Community Safety

**Scope:** Public safety content, maps, alerts, community participation, and member-support tooling.

**Current state (2026-04-13):** Foundation modules `amisafe` and `safety_calculator` are production-complete. Next slice fully groomed: **Community Incident Report** (`forseti-community-incident-report`, Status: ready, Release: 20260412-forseti-release-h targeted). AC, impl notes stub, and test plan created 2026-04-13. BA dispatched to complete impl notes (6 outstanding items including AmISafe JS integration approach). Feature is not yet activated in release-h (4 features already in_progress; will activate in next cycle unless slot opens).

**Last scoped release:** `20260412-forseti-release-h` (targeted; not yet activated — pending BA impl notes + release slot)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** ba-forseti to complete `02-implementation-notes.md` (AmISafe JS integration, taxonomy terms, form class approach). PM activates in next available release cycle after BA grooming is complete.

**Queue status:** ba-forseti grooming dispatch: `sessions/ba-forseti/inbox/20260413-groom-forseti-community-incident-report/` (ROI 25)

---

## PROJ-007 — Dungeoncrawler Product Track

**Scope:** The dedicated Dungeoncrawler product line, separate site, and its long-lived PF2E implementation program. Long-term mission: implement all PF2E rulebook requirements currently tracked in `dc_requirements` MySQL table (2033 implemented, 674 in_progress, 698 pending as of 2026-04-13).

**Current state (2026-04-13):** Active release `20260412-dungeoncrawler-release-i` (opened 2026-04-13T01:31). 14 features in_progress in dev/QA pipeline (gnome heritage sensate/umbral/chameleon, hazards, magic ch11, skills/feats/spells clusters, rest/downtime, snares, treasure, GMG hazards). 23 features at `ready` status awaiting activation: 10 gnome feat cluster (animal-accomplice, burrow-elocutionist, first-world-adept, first-world-magic, gnome-heritage-fey-touched/wellspring, gnome-obsession, gnome-weapon-expertise/familiarity/specialist), 4 goblin feats (ancestry, very-sneaky, weapon-familiarity, weapon-frenzy), halfling cluster (halfling-ancestry, heritage-gutsy, heritage-hillock, keen-eyes, vivacious-conduit), and GMG features (gods-magic, npc-gallery, running-guide, subsystems).

**DB requirement gaps (pipeline coverage missing):**
- `core/ch01` (Chapter 1: Introduction) — 237 pending, no feature stub; covers fundamental rules display and character creation flow
- `core/ch02` (Chapter 2: Ancestries & Backgrounds) — 371 pending, no feature stub; covers the ancestry/background data model and character creation enforcement (separate from the ancestry feat content already in pipeline)
- `gng` (Guns & Gears, 5 chapters) — 30 pending, no feature stub
- `som` (Secrets of Magic, 5 chapters) — 30 pending, no feature stub
- `b2/b3` (Bestiary 2 & 3) — 24 pending, stubs exist in audit TSV as `blocked`

**Last scoped release:** `20260412-dungeoncrawler-release-i` (active; 0 features activated yet)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** Activate ready features for release-i (10-feature cap; activate gnome feat cluster first as it is the deepest groomed batch). After dev/QA cycle completes, queue BA decomposition for `core/ch01` and `core/ch02` — these are the largest unaddressed pending gaps (608 requirements combined).

**Queue status:** 14 features in dev/QA pipeline (release-e/f/g); 23 features `ready` for release-i activation; BA decomposition for core/ch01 and core/ch02 not yet dispatched (next major roadmap gap to close).

---

## PROJ-008 — Forseti Accounting Pipeline

**Scope:** Establish Forseti's repeatable accounting operating model: daily income/expense capture, cash reconciliation, daily flash P&L, monthly close, renewal tracking, anomaly logging, and the smallest finance system stack needed to keep reporting trustworthy as volume grows.

**Owner / primary developer:** `accountant-forseti`

**Current state (2026-04-13):** Foundation documentation is in place and the active April 2026 finance workspace has now been opened under `dashboards/finance/`, including `daily-p-and-l-2026-04.md`, `income-ledger-2026-04.md`, `expense-ledger-2026-04.md`, and `vendor-reconciliation-2026-04.md`. The project is still blocked on live source hookup: no authoritative income, expense, or cash sources have been confirmed yet, so the new April artifacts are placeholders rather than actual reported figures.

**Last scoped release:** `20260412-forseti-release-h` (operations/process foundation defined; no product feature activation yet)

**Progress SLA:** 7 days without a CEO/accountant update, source-system hookup decision, or April artifact population from live sources = breach

**Next step:** CEO should confirm the authoritative live income, expense, and cash sources for Forseti so `accountant-forseti` can replace the April placeholders with source-backed entries and begin daily reconciliation.

**Queue status:** Project registered in roadmap; process docs and active April finance artifacts exist; blocker is the missing live source decision for income, expenses, and cash evidence.

---

## PROJ-001 — LangGraph Console UI

**Roadmap:** `features/forseti-langgraph-ui/roadmap.md`  
**Scope:** Build the full Copilot HQ control-plane console UI on forseti.life — telemetry, agent monitoring, session management, release controls, and eval scorecards wired to live orchestrator tick data.

**Current state (2026-04-13):** All foundation slices are shipped: telemetry foundation, console stubs (7 routes), context enrichment, Agent Tracker Core, Console Build/Test sections, and Release Control Panel (read-only). Active release `20260412-forseti-release-h` carries the next slice: Run + Session panel wiring (`features/forseti-langgraph-console-run-session/`, Status: ready). Artifact naming corrected (renamed to standard `01-acceptance-criteria.md`, `02-implementation-notes.md`); `03-test-plan.md` created by PM 2026-04-13. BA dispatched to confirm 4 implementation details before dev activation.

**Last scoped release:** `20260412-forseti-release-h` (targeted; not yet activated — pending BA confirmation)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** ba-forseti to confirm AC-3 glob pattern, AC-2 truncation placement, AC-7 warning banner condition, and AC-5 Session Health placement. PM activates after BA confirmation.

**Queue status:** ba-forseti grooming dispatch: `sessions/ba-forseti/inbox/20260413-groom-forseti-langgraph-console-run-session/` (ROI 40)

---

## PROJ-002 — QA Suite Completeness

**Scope:** Build repeatable, executable QA coverage for shipped Forseti features and clean up stale suite shells so release verification is durable, automatable, and auditable.

**Status:** in_progress  
**Priority:** P2  
**Lead:** pm-forseti (dispatch), qa-forseti (execution)  
**Scope product:** forseti.life  
**Suite manifest:** `qa-suites/products/forseti/suite.json`

**Current state (2026-04-13):** Phase 1 (triage) complete. Phase 2 (suite fill) dispatched to qa-forseti inbox `20260413-004107-suite-activate-*` items (4 release-h suites activated) and Phase 2 fill dispatch confirmed as `20260412-proj002-phase2-suite-fill` (check qa-forseti inbox — if not present, re-dispatch is needed). `suite.json` has 252 suites with 2 populated. Core problem (no executable regression tests) persists; Phase 2 fill work is the active priority.

**Last scoped release:** `20260412-forseti-release-h`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** qa-forseti to execute Phase 2 fill for the 52 priority suites from the triage report (`sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`). Target ≥2 test_cases per fill suite.

**Queue status:** Phase 2 dispatch: `sessions/qa-forseti/inbox/20260412-proj002-phase2-suite-fill/` (verify exists)

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

*Last updated: 2026-04-13 by accountant-forseti (PROJ-008 registered — Forseti accounting pipeline added to CEO roadmap registry)*
