# Projects Registry

Central list of multi-release initiatives across all products.

**Owned by:** ceo-copilot-2  
**Update cadence:** when a project is created, advanced, or closed  
**Format:** one row per project in the registry table; full spec in the project's section below

---

## Registry

| ID | Name | Product | Status | Priority | Lead | Started |
|---|---|---|---|---|---|---|
| PROJ-001 | LangGraph Console UI | forseti.life | in_progress | P1 | pm-forseti | 2026-04-05 |
| PROJ-002 | QA Suite Completeness | forseti.life | in_progress | P2 | pm-forseti / qa-forseti | 2026-04-09 |
| PROJ-003 | DungeonCrawler Roadmap Completion | dungeoncrawler | in_progress | P1 | pm-dungeoncrawler | 2026-03-01 |

---

## PROJ-001 — LangGraph Console UI

**Roadmap:** `features/forseti-langgraph-ui/roadmap.md`  
**Scope:** Build the full Copilot HQ control-plane console UI on forseti.life — telemetry, agent monitoring, session management, release controls, and eval scorecards wired to live orchestrator tick data.

See roadmap file for full release-by-release breakdown. Currently mid-Phase 1c.

---

## PROJ-002 — QA Suite Completeness

**Status:** in_progress  
**Priority:** P2  
**Lead:** pm-forseti (dispatch), qa-forseti (execution)  
**Scope product:** forseti.life  
**Suite manifest:** `qa-suites/products/forseti/suite.json`

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

Current status: Core ruleset is broadly implemented and the live release engine is now aligned to `20260412-dungeoncrawler-release-d`. The prior scoped batch, `20260412-dungeoncrawler-release-b`, was closed empty and deferred back to `ready`; the active release has 8 deliverables currently `in_progress` (`dc-cr-downtime-mode`, `dc-cr-feats-ch05`, `dc-cr-gnome-heritage-sensate`, `dc-cr-gnome-heritage-umbral`, `dc-cr-hazards`, `dc-cr-magic-ch11`, `dc-cr-rest-watch-starvation`, `dc-cr-skills-society-create-forgery`). `dc-cr-gnome-heritage-chameleon` remains a stale carry-over `in_progress` item from `20260411-coordinated-release-next` and does not count as a release-d deliverable. Remaining roadmap focus areas are movement gaps (GAP-2234/2235/2239-2242), hero point dead letter (GAP-2280/2281), exploration mode gaps (GAP-2290/2292), GM tools, and later APG class work.

See `runbooks/roadmap-audit.md` for full query protocol and per-chapter status.

---

*Last updated: 2026-04-12 by ceo-copilot-2*
