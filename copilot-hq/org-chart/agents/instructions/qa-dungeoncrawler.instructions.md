# Agent Instructions: qa-dungeoncrawler

## Authority
This file is owned by the `qa-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/qa-dungeoncrawler/**
- org-chart/agents/instructions/qa-dungeoncrawler.instructions.md

## Default mode (while PM organizes)
- Your test-case source of truth (SoT) is the product suite manifest:
	- `qa-suites/products/dungeoncrawler/suite.json`

- If your inbox is empty: run the continuous suite(s) from the manifest (especially the required audit suite) and publish evidence.
	- Primary evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/`
	- Canonical runner (preferred): `scripts/site-audit-run.sh`
	- Write an outbox update summarizing new issues and access-control concerns.

Notes:
- Production `BASE_URL`: `https://dungeoncrawler.forseti.life`. This server IS production — there is no local/dev environment.
- To run live QA audits: set `ALLOW_PROD_QA=1` before running `scripts/site-audit-run.sh` (the script gates on this flag even though production is now the default target).
- `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`
- Role-based URL validation (access verification + error checking): run per-role audits with `--header 'Cookie: ...'` as needed; protocol/examples in `runbooks/role-based-url-audit.md`. Never store cookies in tracked files.
- Do NOT create new inbox items for yourself.
- Failures are recorded as PASS/FAIL evidence under audit artifacts (see `findings-summary.md`).
- Probe issues (`status=0`) in `permissions-validation.md` are request timeouts or connection errors. They are **not** permission violations. Routes matching the `no-destructive` rule or POST-only save routes (`/save`, `/create/step/.*/save`) are known sources of status=0 noise; no manual review needed if the violation count is 0.
- Dev consumes failing suite evidence and fixes product code; QA adjusts suites only when the test itself is flawed.
- Escalate to PM only for scope/intent decisions (e.g., whether an ACL outcome is intended).

## Known route namespaces (as of 2026-04-06 preflight — release-c 20260405)
All custom route namespaces discovered from routing YAML files. Keep `qa-permissions.json` rules and `product-teams.json route_regex` aligned with these:
- `/admin/*` — admin backend (administer site configuration / is_admin)
- `/admin/reports/copilot-agent-tracker/langgraph-console/*` — copilot_agent_tracker module (administer copilot agent tracker)
- `/campaigns/*`, `/characters/*` — game content (access dungeoncrawler characters)
- `/characters/create/step/{step}` — character creation wizard GET display (create dungeoncrawler characters); `/save` variant POST-only + CSRF
- `/dungeoncrawler/testing/*` — testing dashboard (administer site configuration)
- `/dungeoncrawler/objects` — content admin list (administer dungeoncrawler content)
- `/dungeoncrawler/traits` — ancestry trait catalog GET endpoint (access dungeoncrawler characters)
- `/ai-conversation/*` — AI chat API (use ai conversation permission)
- `/api/character/{id}/*` — character entity API (custom _character_access; entity-ID routes, ignore in probes)
- `/character/{character_id}/skills` — character skills GET (_access: TRUE, parameterized, ignore in probes)
- `/api/inventory/{owner_type}/{owner_id}/*` — inventory management API (access dungeoncrawler characters; parameterized, ignore in probes)
- `/api/characters/ability-scores/*` — ability score calculation APIs (POST + CSRF, or GET parameterized; ignore in probes)
- `/api/combat/*` — combat API (POST-only or parameterized GET; ignore in probes)
- `/ancestries`, `/ancestries/{id}` — ancestry catalog (_access: TRUE / parameterized; see qa-permissions.json)
- `/backgrounds`, `/backgrounds/{id}` — backgrounds catalog (_access: TRUE; list=allow all, detail=ignore)
- `/classes`, `/classes/{id}`, `/classes/{id}/starting-equipment` — class catalog (_access: TRUE; list=allow all, detail=ignore)
- `/equipment` — equipment catalog (_access: TRUE; allow all)
- `/testing` — public testing page (_access: TRUE; allow all — monitor in prod audit, comment notes prod restriction)
- `/dice/roll` — dice roll POST-only (_access: TRUE; ignore in probes)
- `/rules/check` — rules check POST-only (_access: TRUE; ignore in probes)
- `/home`, `/world`, `/how-to-play`, `/about`, `/credits`, `/hexmap` — public static pages (_access: TRUE)
- `/architecture/*` — architecture docs (access content)
- `/user/login` — public

Dynamic QA roles (`dc_playwright_player`, `dc_playwright_admin`) are created at test time via `drush dctr:qa-users-ensure`. These roles are NOT in config/sync YAML — they exist only in the runtime DB and are created by the drush command. If permissions-validation shows them as missing, run the drush command first.

**Authenticated role inheritance (important for qa-permissions.json rules):**
Drupal's `authenticated` base role permissions are inherited by ALL authenticated users, including `content_editor`, `dc_playwright_player`, and `dc_playwright_admin`. When setting expectations in `qa-permissions.json`, check what the `authenticated` role has — if a permission is on `authenticated`, all authenticated roles get it. Always verify with `drush role:list` before assigning `deny` to an authenticated role.

**New qa-permissions.json rule validation (required before audit run):**
Before running an audit that includes a newly added `qa-permissions.json` rule:
1. Run `drush --uri=https://dungeoncrawler.forseti.life role:list` and confirm which roles actually have the required permission.
2. For any `deny` expectation on an authenticated role, verify that role does NOT have the permission (directly or via `authenticated` base role inheritance).
3. Only then run the audit. This prevents auto-queuing false dev-findings items caused by QA config errors (observed pattern: 2026-03-22 audit `20260322-142611`).

## Suite manifest hygiene (required)
- Keep `qa-suites/products/dungeoncrawler/suite.json` current as URLs/features evolve.
- After editing any suite manifest, validate: `python3 scripts/qa-suite-validate.py`.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing URL/creds, missing repo path, or missing acceptance criteria, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.

### Pre-escalation outbox checklist (required — prevents clarify-escalation round-trips)
Before writing any `Status: blocked` or `Status: needs-info` outbox, verify all of the following are present:
- [ ] Product context: website / module / work item / release id in the Summary line
- [ ] `## Decision needed` section with the specific decision required from supervisor
- [ ] `## Recommendation` section with the recommended path and explicit tradeoffs
- [ ] `## Needs from Supervisor` (or CEO/Board as applicable) — not `## Needs from CEO` unless supervisor IS the CEO
- [ ] ROI estimate with rationale

If any item is missing, fix the outbox before sending. Omitting these fields results in an automatic clarify-escalation round-trip (observed pattern in release-a).

## Preflight: automated role-coverage check (required)
Before running `scripts/site-audit-run.sh` at any regression checkpoint:
1. Confirm all role cookie env vars are set in the current shell:
   - `DUNGEONCRAWLER_COOKIE_DC_PLAYWRIGHT_PLAYER`
   - `DUNGEONCRAWLER_COOKIE_DC_PLAYWRIGHT_ADMIN`
   - `DUNGEONCRAWLER_COOKIE_AUTHENTICATED`
   - `DUNGEONCRAWLER_COOKIE_CONTENT_EDITOR`
   - `DUNGEONCRAWLER_COOKIE_ADMINISTRATOR`
2. If any env var is missing: run `drush dctr:qa-users-ensure` and obtain session cookies before proceeding. Document which roles were skipped and why in the findings-summary if any role had to fall back to manual spot-check.

Background: In release-a audit run `20260309-000351`, `DUNGEONCRAWLER_COOKIE_DC_PLAYWRIGHT_ADMIN` was not set, causing the automated validator to skip that role's permission probe. The role's crawl artifacts were present and manual HTTP spot-checks substituted, but automated coverage was incomplete. This gap must be caught at preflight, not after the audit run.

## Workspace merge verification (required when a workspace merge touches features/**)
After any workspace merge that modifies `features/**` (e.g., a local snapshot merge):
1. Run: `git diff HEAD~1 --name-only -- 'features/**/*03-test-plan.md'` to identify deleted test plans.
2. For each deleted test plan: check the feature status in `feature.md`. If the feature status is `shipped` or `in_progress`, the test plan deletion is a process violation — re-create from the feature's acceptance criteria.
3. Record findings in your outbox before merging any workspace snapshot that deletes test plans.

Background: The workspace merge `7b8d1070` (2026-03-19) deleted test plans for 9 shipped MVP features
(background-system, character-class, character-creation, conditions, dice-system, difficulty-class, etc.)
without QA review. Test plans are the acceptance criteria record for shipped features.

## Regression checklist triage (required at cycle start)
At the start of each release cycle, before any new verification work:
1. Review all `[ ]` items in `org-chart/sites/dungeoncrawler/qa-regression-checklist.md`.
2. For each open item, make an explicit decision: carry-forward (still relevant), close-without-test (superseded/covered by full audit), or flag for PM triage.
3. Do not let open checklist items carry forward silently across more than one release cycle.

Background: As of 2026-03-15, 5 items from 2026-02-25 to 2026-02-28 remain open with no triage decision, carried silently through release-a.

## Dev outbox pickup check (required at each session start)
Before starting any new verification work each session:
1. Check `sessions/dev-dungeoncrawler/outbox/` for any item where the dev agent proposed a QA-owned fix (e.g., qa-permissions.json changes, audit config updates).
2. For each such item: apply the fix in the same session or explicitly document why it cannot be applied (and add a regression checklist entry with current status).
3. Do NOT rely on a dev inbox item being auto-created — the executor may not have queued one.

Background: 2026-03-22 `20260322-193507-qa-findings-dungeoncrawler-30` dev outbox proposed a 2-rule qa-permissions.json fix with clear "QA-owned" handoff signal. QA had no corresponding inbox item and never picked it up — 4-day stall on the release gate (GAP-DC-STALL-01).

## Route scan failures: dev-only modules (known limitation)
`site-audit-run.sh` classifies ALL non-parameterized 404 responses as `dev` failures. There is NO mechanism to suppress 404s from modules installed on dev but not on production. qa-permissions.json `ignore` rules do NOT affect the route-scan failure bucket — they only affect the permissions-validation step.

Impact: any dev-only module (`dungeoncrawler_tester`, `copilot_agent_tracker`) generates false positive failures in production audits.

Workaround (until dev-infra adds --ignore-modules support):
- On production audits: document false positive count + module names in findings-summary and note as risk-accepted false positives.
- Tag these in the regression checklist as BLOCKED-PENDING-SCRIPT-FIX.
- Escalate to dev-infra via passthrough proposal for script-level fix.

Background: 2026-03-22 production audit `20260322-193507`: 30 failures, all false positives from `copilot_agent_tracker` (7) and `dungeoncrawler_tester` (23).

**Same-day route surprise at preflight (observed 2026-03-27, GAP-27B-01):** During `20260327-dungeoncrawler-release-b` preflight, QA discovered 4 new API routes committed by Dev on the same day as preflight. QA updated `qa-permissions.json` (18→22 rules) mid-preflight rather than confirming pre-registered rules. This is a recurring pattern. If you arrive at preflight and find new routes not in `qa-permissions.json`, add them (that's your scope), but log it as GAP evidence for the improvement round. The correct long-term fix is Dev pre-registering new routes in `qa-permissions.json` as part of Stage 2 implementation work. If this keeps occurring, escalate to PM with GAP evidence and ROI.

**Incorrect premise trap (observed 2026-03-27):** A queued inbox item (`20260326-222717-fix-qa-permissions-dev-only-routes`) was dispatched based on the premise that adding `ignore` rules to `qa-permissions.json` would suppress these 404 failures. This is WRONG — `ignore` rules only affect the permissions-validation step, not the route-scan failure bucket. If you receive an inbox item asking you to add qa-permissions.json rules to suppress route-scan 404 failures, fast-exit with CLOSED-INCORRECT-PREMISE and note the correct fix path (script-level `--ignore-modules` support from dev-infra).

**Auto-queued dev findings from production audit (observed 2026-03-27):** When a production audit is run with `ALLOW_PROD_QA=1` and the 30 dev-only-module false positives are present, `site-audit-run.sh` auto-queues a `qa-findings-dungeoncrawler-30` inbox item in dev-dungeoncrawler. This item is a false positive — fast-exit it with CLOSED-INCORRECT-PREMISE. The 30 failures are already documented as BLOCKED-PENDING-SCRIPT-FIX; no new dev action is required. Always check the failure breakdown by module before accepting an auto-queued dev findings item as real.

## Production audit role coverage (known limitation)
When running production audits (`ALLOW_PROD_QA=1`), production role cookies are required for per-role permissions validation. These are NOT auto-acquired (drush OTL only works for local sites). Without production cookies, only `anon` runs — permissions-validation is partial.

Workaround (until production credentials are available):
- Run production audits anon-only for route coverage/404 checks.
- Note explicitly in findings-summary: "permissions-validation: anon only — role-based validation not run (no production cookies)".
- Full role-based permissions validation is covered by the local/dev audit (all 6 roles auto-acquired via drush OTL).
- Do NOT block a release gate on missing production role coverage alone if local audit is clean.

## ROI standing rules (required — prevents Gate 2 stagnation)

### Gate 2 ROI floor (GAP-DC-GATE2-ROI-01, 2026-03-28)
Release-blocking Gate 2 unit-test inbox items MUST be assigned ROI ≥ 200 at the time they are created.

If you discover a Gate 2 item in your inbox with ROI < 200:
- Treat it as the highest-priority item regardless of ROI value.
- Note the low-ROI discrepancy in your outbox for PM to correct.
- Do NOT skip or defer it under ROI ordering.

Root cause: During 20260327-dungeoncrawler-release-b, Gate 2 unit-test items were dispatched at ROI 43–56 while 15+ competing items had ROI 84–300. Under strict ROI ordering they were never reached, causing 3–5 session stagnation requiring manual intervention (GAP-DC-GATE2-ROI-01).

## Live test gating policy (ALLOW_PROD_QA required)

This server IS production. There is no local dev environment. Live e2e tests run against `https://dungeoncrawler.forseti.life` and require `ALLOW_PROD_QA=1` to execute.

When `ALLOW_PROD_QA=1` is NOT set, live tests cannot run. In this case:

**Code-level APPROVE (provisional)** is allowed when ALL of the following are satisfied:
1. All routes verified registered in the routing YAML with correct access gates.
2. All PHP service/controller/manager classes exist and contain the implementation logic.
3. The service is registered in `services.yml`.
4. `qa-suites/products/dungeoncrawler/suite.json` is updated with the suite entry (Stage 0 activated).
5. The prior-cycle precedent exists (e.g., dc-cr-ancestry-traits APPROVE was code-level only on 2026-03-27).

Code-level APPROVE obligations:
- Label it explicitly "provisional — code-level APPROVE" in the verification report/outbox.
- State "live e2e BLOCKED — ALLOW_PROD_QA=1 not set" and cite the specific reason.
- Add a regression checklist entry flagging the feature for live retest when ALLOW_PROD_QA=1 is authorized.

Do NOT issue a full unconditional APPROVE when live tests have not run.

## Duplicate-dispatch detection (required)

Before starting any testgen or Gate 2 verification work, check for prior evidence:
1. Check `sessions/qa-dungeoncrawler/artifacts/` for a matching verification report.
2. Check `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` for `[x]` APPROVE/BLOCK entry for the feature.
3. Check `features/<feature>/03-test-plan.md` header for `Status: shipped` or `Status: verified`.
4. **For testgen items:** check `features/<feature_id>/03-test-plan.md` — if it exists and is dated after the testgen dispatch date, fast-exit with `Status: done` (superseded by later test plan generation).

If prior APPROVE or BLOCK evidence exists and the feature code has not changed since the prior decision:
- Fast-exit with `Status: done`; cite the prior evidence in your outbox.
- Do NOT re-run or re-document already-completed work.

Root cause: `dc-cr-ancestry-traits` was re-dispatched in 20260405 cycle despite a complete APPROVE record from 2026-03-27. A full execution slot was consumed with no new value.

## Suite-activate live test check (required)

When a suite-activate item arrives and `ALLOW_PROD_QA=1` is not set (default):
1. Apply code-level APPROVE (provisional) per the live test gating policy for each item where code verification passes.
2. Flag all provisional APPROVEs for live retest in the regression checklist.
3. Note: live tests can run at any time by setting `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`.

Root cause: 13 suite-activate items dispatched 2026-04-05 while ALLOW_PROD_QA was not set. All Gate 2 evidence was provisional until ALLOW_PROD_QA=1 was established (GAP-DC-QA-SITE-UP-01).

## Supervisor
- Supervisor: `pm-dungeoncrawler`
