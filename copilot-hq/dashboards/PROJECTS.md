# Projects Registry

Authoritative list of active **product lines** and **delivery projects** across the org.

The live authority page is:

- `https://forseti.life/roadmap`

That page is rendered from this file. CEO and architect seats must treat the roadmap page + this backing file as the single source of truth for what is on the active project list.

**Owned by:** ceo-copilot-2  
**Update cadence:** when a project is created, advanced, closed, or when a release picks up project-scoped work  
**Format:** one numbered portfolio registry. Every active item on the live roadmap page must have a `PROJ-*` ID. Use the `Type` column to distinguish long-lived product tracks from execution initiatives.

**Required per-project fields:** `Scope`, `Current state`, `Last scoped release`, `Progress SLA`, `Next step`, `Queue status`

## Development Node Assignment Registry

This section is the source of truth for all nodes and project assignments.
**The CEO on the master node is the sole owner of this section.**
Worker nodes identify themselves by matching `NODE_ID` in their local
`node-identity.conf` against the Node ID column below, then claim only the
projects assigned to that node.

**Used by:** `scripts/ceo-dispatch-project-task.sh`, `scripts/dev-sync-once.sh`

### Node Registry

Each row is a registered machine in the fleet. Nodes self-identify by `Node ID`.

| Node ID | Role | Hostname | Environment | Owner | Status |
|---|---|---|---|---|---|
| production | master | forseti.life | AWS EC2 Ubuntu 24.04 | ceo-copilot-2 | active |
| dev-laptop | worker | pop-os | Keith's Dev Laptop (Pop!_OS) | dev-jobhunter | active |

### Project Assignments

Which node/agent handles each project. CEO edits this to reassign work between nodes.

| Project key | Target node | Target agent | Website | Module | Execute |
|---|---|---|---|---|---|
| forseti-jobhunter-automation | dev-laptop | dev-jobhunter | forseti.life | job_hunter | dispatch-only |
| forseti-safety-application | dev-laptop | dev-forseti | forseti.life | forseti_content | dispatch-only |
| forseti-agent-tracker | dev-laptop | dev-forseti-agent-tracker | forseti.life | copilot_agent_tracker | dispatch-only |
| dungeoncrawler | dev-laptop | dev-dungeoncrawler | dungeoncrawler | dungeoncrawler_content | dispatch-only |
| infrastructure | dev-laptop | dev-infra | infrastructure | infrastructure | dispatch-only |

---

## Registry

| ID | Name | Type | Product | Status | Priority | Lead | Started |
|---|---|---|---|---|---|---|---|
| PROJ-004 | Job Hunter | product line | forseti.life | active_buildout | P1 | pm-forseti | 2026-04-12 |
| PROJ-005 | AI Conversation | product line | forseti.life | foundation_in_place | P1 | pm-forseti | 2026-04-12 |
| PROJ-006 | Community Safety | product line | forseti.life | public_platform_track | P2 | pm-forseti | 2026-04-12 |
| PROJ-007 | Dungeoncrawler Product Track | product line | dungeoncrawler | separate_product_site | P1 | pm-dungeoncrawler | 2026-04-13 |
| PROJ-008 | Forseti Accounting Pipeline | delivery project | forseti.life | in_progress | P1 | accountant-forseti | 2026-04-13 |
| PROJ-009 | Forseti Open Source Initiative | delivery project | org-wide | in_progress | P1 | pm-open-source | 2026-04-13 |
| PROJ-010 | External Integration Configuration Audit | delivery project | org-wide | in_progress | P1 | ceo-copilot-2 | 2026-04-13 |
| PROJ-011 | Forseti Community Resource Mesh | delivery project | forseti.life | in_progress | P1 | pm-forseti | 2026-04-13 |
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

**Current state (2026-04-14):** Active runtime release is now `20260412-dungeoncrawler-release-m`, with `20260412-dungeoncrawler-release-n` queued as the next release. The roadmap page remains live and pipeline-backed: requirement status is resolved from `dc_requirements` plus HQ feature status, so implemented and in-progress work still render correctly. The current release has just rolled forward and is waiting on PM scope activation; a `scope-activate` item is already queued for `pm-dungeoncrawler`. The ready backlog is 17 features deep, and 10 Dungeoncrawler features remain `in_progress` across the broader implementation pipeline. Alongside the gnome, goblin, halfling, GMG, and UI clusters, the promoted roadmap epics remain `dc-b2-bestiary2`, `dc-gng-guns-gears`, and `dc-som-secrets-of-magic`.

**Backlog coverage status (2026-04-14):**
- `core/ch01` (Chapter 1: Introduction) — 237 pending, now mapped primarily to `dc-cr-character-creation` and `dc-cr-character-leveling`
- `core/ch02` (Chapter 2: Ancestries & Backgrounds) — 371 pending, now mapped across the ancestry/background backlog (`dc-cr-human/dwarf/gnome/elf/goblin/halfling-*`, `dc-cr-ancestry-system`, `dc-cr-background-system`)
- `gng` (Guns & Gears, 5 chapters) — 30 pending, now queued in backlog via `dc-gng-guns-gears`
- `som` (Secrets of Magic, 5 chapters) — 30 pending, now queued in backlog via `dc-som-secrets-of-magic`
- `b2` (Bestiary 2) — 12 pending, now queued in backlog via `dc-b2-bestiary2`
- `b3` (Bestiary 3) — 18 pending, still intentionally deferred until `dc-b2-bestiary2` ships

**Last scoped release:** `20260412-dungeoncrawler-release-l` (most recent scoped tranche; active runtime release is `20260412-dungeoncrawler-release-m`)

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** `pm-dungeoncrawler` should process the queued `scope-activate` item for `20260412-dungeoncrawler-release-m`, then pull from the ready backlog in this order: goblin/halfling cleanup → GMG/UI tranche → `dc-b2-bestiary2` → `dc-gng-guns-gears` → `dc-som-secrets-of-magic`. `dc-b3-bestiary3` remains gated behind Bestiary 2 shipping.

**Queue status:** 17 features `ready` for future activation, `scope-activate` is queued for the active runtime release `20260412-dungeoncrawler-release-m`, and 10 features are currently `in_progress` across the pipeline. Remaining true deferred gap: `dc-b3-bestiary3`.

---

## PROJ-008 — Forseti Accounting Pipeline

**Scope:** Establish Forseti's repeatable accounting operating model: daily income/expense capture, cash reconciliation, daily flash P&L, monthly close, renewal tracking, anomaly logging, and the smallest finance system stack needed to keep reporting trustworthy as volume grows.

**Owner / primary developer:** `accountant-forseti`

**Current state (2026-04-13):** Foundation documentation is in place and the active April 2026 finance workspace is open under `dashboards/finance/`, including `daily-p-and-l-2026-04.md`, `income-ledger-2026-04.md`, `expense-ledger-2026-04.md`, and `vendor-reconciliation-2026-04.md`. Expense sources are now confirmed as AWS Billing and GitHub billing, and live pull attempts have been made. GitHub org billing usage for `Forseti-Life` is now reachable and returned no April usage items. The project remains blocked because AWS denied `ce:GetCostAndUsage`, GitHub fixed-charge completeness is still unconfirmed, and income/cash sources are still not confirmed. A new backlog feature, `forseti-financial-health-home`, now captures the Drupal-side institutional finance home as the next productization layer for this project.

**Last scoped release:** `20260412-forseti-release-h` (operations/process foundation defined; no product feature activation yet)

**Progress SLA:** 7 days without a CEO/accountant update, source-system hookup decision, or April artifact population from live sources = breach

**Next step:** CEO should unblock AWS Cost Explorer access and confirm both the GitHub fixed-charge path and the income/cash sources so `accountant-forseti` can replace the April placeholders with source-backed entries and begin daily reconciliation. In parallel, `pm-forseti` can pick up `features/forseti-financial-health-home/feature.md` from the backlog to scope the internal Drupal financial-health home.

**Queue status:** Process docs and active April finance artifacts exist; AWS and GitHub expense sources are selected; GitHub usage report is live and empty for April; current blockers are AWS Cost Explorer permission, GitHub fixed-charge completeness, and missing income/cash source confirmation. Backlog now includes `forseti-financial-health-home` as the Drupal financial-health surface for PROJ-008.

---

## PROJ-009 — Forseti Open Source Initiative

**Scope:** Publish the Forseti autonomous Drupal development platform as open source under the `Forseti-Life` GitHub organization, including the platform overview repo, selected reusable component repos, contributor docs, and the release/security process needed to publish safely.

**Current state (2026-04-13):** The effort is active and the `Forseti-Life` GitHub org now exists, so the governance prerequisite is no longer the blocker. Readiness assets already exist: `PUBLIC_REPO_PREP.md`, publication-readiness runbooks, public positioning docs, mirror/export scripts, and community/legal files (`LICENSE`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`). Publication model is now explicit: use curated mirrors / extracted repos and keep live operational artifacts private. Current-tree AWS credentials have been stripped from the tracked Drupal config sync files; the remaining critical blocker is publication security in history and candidate packaging: credential rotation, full history scrub / sensitive-data audit, candidate freeze, and validation evidence still remain.

**Last scoped release:** `20260413-orgwide-release-baseline` (portfolio initiative baseline; not tied to product release scope)

**Progress SLA:** 7 days without a PM-open-source re-baseline, dev-open-source publication audit, or Board/org-setup step = breach

**Next step:** `drupal-ai-conversation` is now the explicit first publication candidate and the PM publication-candidate gate is written. `dev-open-source` should clear the candidate-local NO-GO findings from the Phase 1 audit (HQ/session coupling, stale absolute path, site-specific logging reference, Forseti-specific default prompt) while CEO confirms external AWS credential rotation; once both are done, `pm-open-source` freezes the sanitized extract and hands it to `qa-open-source`.

**Queue status:** Governance unblock is complete (`Forseti-Life` org verified) and publication scope is now explicit (curated mirror / extracted repos; operational artifacts remain private). Publication is still blocked on credential rotation + history rewrite/scrub, candidate freeze, packaging, and final validation evidence.

---

## PROJ-010 — External Integration Configuration Audit

**Scope:** Inventory and audit how the org stores, resolves, and governs configuration for external systems used by the server stack and adjacent production operations, including APIs, cloud providers, billing systems, deploy workflows, token files, and Drupal-backed integration settings.

**Current state (2026-04-13):** Project opened and first-pass inventory completed at `dashboards/integrations/server-integration-inventory-2026-04.md`. The Phase 1 operator entrypoint now exists at `dashboards/integrations/README.md`, and the first machine-readable registry now exists at `dashboards/integrations/integration-registry.yaml`. The centralization plan remains at `dashboards/integrations/centralized-integration-management-plan.md`. The current baseline confirms multiple integration storage planes already in use: Drupal sync config, active Drupal config, environment variables, local token files, and GitHub Actions secrets. Verified integration surfaces include AWS Bedrock, AWS Cost Explorer billing, GitHub billing APIs, GitHub deploy/push workflows, SerpAPI, Google Cloud Talent Solution config, Adzuna, USAJobs, Google Tag, Google social auth, reCAPTCHA, USFA NERIS, and Hugging Face model downloads. The first critical finding remains a tracked `serpapi_api_key` in `sites/forseti/config/sync/job_hunter.settings.yml`.

**Last scoped release:** `20260413-orgwide-release-baseline` (org-wide audit baseline; tracked outside product release scope)

**Progress SLA:** 7 days without a CEO update, inventory expansion, or remediation dispatch = breach

**Next step:** CEO should treat Phase 1 as established and dispatch the runtime truth audit next: confirm live active Drupal config, server env vars, token-file consumers, and workflow secret usage for every registry entry, then prioritize remediation starting with the tracked SerpAPI secret.

**Queue status:** Inventory, centralization plan, operator hub, and first registry are now in place. Project is ready for CEO-managed runtime truth audit and remediation prioritization.

---

## PROJ-011 — Forseti Community Resource Mesh

**Scope:** Build a community resource mesh between independent Forseti installations so any installation can identify peer installations, establish trust, exchange signed messages, advertise needs and capabilities, and initially share **agent expertise** and **institutional-management services**. Compute and storage remain future-state extensions.

**Current state (2026-04-13):** Project created and initial delivery feature stub opened as `forseti-installation-cluster-communication`. MVP architecture, daemon/runtime design, protocol schemas, state machines, pseudocode, and a sequenced roadmap now exist. No implementation is active yet. The intended MVP is autonomous-peer communication plus resource-mesh primitives, not full multi-primary data replication: each installation should have a stable installation identity, a peer registry, a trust/auth model, signed request handling, capability/need advertisement, cluster message logging, a standalone `forseti-meshd` backend, and a Drupal admin interface for peer status and operator decisions. Initial value focus is shared agent capacity and institutional-management workflows; compute and storage are intentionally deferred.

**Last scoped release:** `20260413-forseti-release-baseline` (strategic project baseline; pre-activation planning phase)

**Progress SLA:** 7 days without PM/BA decomposition, MVP scope refinement, or release-slot planning = breach

**Next step:** `pm-forseti` and `ba-forseti` should execute `90-roadmap.md` beginning with the open-source stack validation and Release A daemon-foundation slices, then dispatch dev on `forseti-meshd` scaffold, identity/key loading, and peer discovery.

**Queue status:** Project registered under the CEO portfolio and paired with an initial ready feature stub. Awaiting BA elaboration and release-slot selection.

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

Current status: The public roadmap still derives requirement state from the live pipeline, and the sync path remains intact. Active runtime release is `20260412-dungeoncrawler-release-m`, with `20260412-dungeoncrawler-release-n` already groomed as the next wave. The current runtime release has just advanced and is awaiting PM scope activation; a scope-activate item is already queued. The broader Dungeoncrawler pipeline remains active with 10 features `in_progress` and 17 features `ready`.

**Last scoped release:** `20260412-dungeoncrawler-release-l`

**Progress SLA:** 7 days without release-scoped work or a PM re-baseline/grooming update = breach

**Next step:** complete PM scope activation for `20260412-dungeoncrawler-release-m`, let that tranche execute through Gate 2, and then promote `20260412-dungeoncrawler-release-n` only after the coordinated push/post-push handoff completes.

**Queue status:** active items are queued for `pm-dungeoncrawler`, `dev-dungeoncrawler`, and `qa-dungeoncrawler`; current runtime gap is only Stage 0 activation for `20260412-dungeoncrawler-release-m`, and that PM item is already in inbox.

See `runbooks/roadmap-audit.md` for full query protocol and per-chapter status.

---

*Last updated: 2026-04-14 by ceo-copilot-2 (Dungeoncrawler live runtime/release state refreshed to match roadmap + pipeline state)*
