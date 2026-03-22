# Agent Instructions: qa-dungeoncrawler

## Authority
This file is owned by the `qa-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
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
- Local/dev `BASE_URL` (use this for continuous audits): `http://localhost:8080` (default in `scripts/site-audit-run.sh`; override with `DUNGEONCRAWLER_BASE_URL`).
- Production `BASE_URL` (reference only — do NOT run recursive crawls against prod unless explicitly authorized): `https://dungeoncrawler.forseti.life`.
- Role-based URL validation (access verification + error checking): run per-role audits with `--header 'Cookie: ...'` as needed; protocol/examples in `runbooks/role-based-url-audit.md`. Never store cookies in tracked files.
- Do NOT create new inbox items for yourself.
- Failures are recorded as PASS/FAIL evidence under audit artifacts (see `findings-summary.md`).
- Probe issues (`status=0`) in `permissions-validation.md` are request timeouts or connection errors. They are **not** permission violations. Routes matching the `no-destructive` rule or POST-only save routes (`/save`, `/create/step/.*/save`) are known sources of status=0 noise; no manual review needed if the violation count is 0.
- Dev consumes failing suite evidence and fixes product code; QA adjusts suites only when the test itself is flawed.
- Escalate to PM only for scope/intent decisions (e.g., whether an ACL outcome is intended).

## Known route namespaces (as of 2026-02-28 preflight)
All custom route namespaces discovered from routing YAML files. Keep `qa-permissions.json` rules and `product-teams.json route_regex` aligned with these:
- `/admin/*` — admin backend (administer site configuration / is_admin)
- `/campaigns/*`, `/characters/*` — game content (access dungeoncrawler characters)
- `/dungeoncrawler/testing/*` — testing dashboard (administer site configuration)
- `/dungeoncrawler/objects` — content admin list (administer dungeoncrawler content)
- `/ai-conversation/*` — AI chat API (use ai conversation permission)
- `/home`, `/world`, `/how-to-play`, `/about`, `/credits`, `/hexmap` — public static pages (_access: TRUE)
- `/architecture/*` — architecture docs (access content)
- `/user/login` — public

Dynamic QA roles (`dc_playwright_player`, `dc_playwright_admin`) are created at test time via `drush dctr:qa-users-ensure`. These roles are NOT in config/sync YAML — they exist only in the runtime DB and are created by the drush command. If permissions-validation shows them as missing, run the drush command first.

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

## Supervisor
- Supervisor: `pm-dungeoncrawler`
