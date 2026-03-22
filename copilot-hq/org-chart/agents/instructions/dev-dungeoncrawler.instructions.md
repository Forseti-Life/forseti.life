# Agent Instructions: dev-dungeoncrawler

## Authority
This file is owned by the `dev-dungeoncrawler` seat.

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/dev-dungeoncrawler/**
- org-chart/agents/instructions/dev-dungeoncrawler.instructions.md
- features/*/02-implementation-notes.md  ← your artifact in every feature's living doc

## Target repo
- If the dungeoncrawler repo path is not explicitly provided in the inbox item, escalate to `pm-dungeoncrawler` and include your best guess.

## Task types — how to read a QA findings inbox item

Every QA findings item you receive is one of two types. Check the command.md header:

### Type A: NEW FEATURE IMPLEMENTATION
**Signal:** command.md contains a `## NEW FEATURE IMPLEMENTATIONS REQUIRED` section with a `feature_id`.

**What it means:** QA added tests for a groomed feature that has **never been implemented**. The tests fail because the feature doesn't exist yet — not a regression.

**How to handle:**
1. Go to `features/<feature_id>/` — the **living requirements document** (shared by PM, QA, and you).
   - `feature.md` — PM brief, goals, mission alignment
   - `01-acceptance-criteria.md` — **what to build** (PM-owned, do not edit)
   - `03-test-plan.md` — **what QA will verify** (QA-owned, do not edit)
   - `02-implementation-notes.md` — **your artifact** (create/update this)
2. Read `01-acceptance-criteria.md` fully before writing a line of code.
3. **Perform impact analysis** (see below) for any major functionality changes.
4. Implement the feature to satisfy the AC.
5. Create `features/<feature_id>/02-implementation-notes.md` documenting what you built, files touched, schema changes, and any deviations from the AC (with justification). **Required section:** `## New routes introduced` (see below).
6. **Pre-QA checklist (new routes):** If you added any new routes, before the first QA audit run notify `qa-dungeoncrawler` with the route paths and expected permission matrix so `qa-permissions.json` can be updated. Missing permission rules generate avoidable QA violations — this is a recurring pattern (2026-03-19 release-b cycle: 8 violations from `copilot_agent_tracker`).
7. Notify QA with specific paths/behaviors implemented, for targeted retest.

#### Required section in `02-implementation-notes.md`: New routes introduced
If the feature introduces any routes, include this section:
```
## New routes introduced
| Route | Permission | administrator | dc_playwright_admin |
|---|---|---|---|
| /path/to/route | access xyz | allow | allow |
```
If no new routes: include `## New routes introduced\nNone.` to confirm this was checked.

### Type B: REGRESSION REPAIR
**Signal:** `## REGRESSION FIXES REQUIRED` section (no feature_id), or general QA findings with no NEW FEATURE section.

**What it means:** Something that previously worked is now broken. Identify the regression, restore correct behavior.

**How to handle:**
1. Read findings summary, identify root cause.
2. Fix product code (or propose suite correction to QA if the test is flawed).
3. Notify QA with specific paths fixed for targeted retest.
4. Do not change `suite.json` or `qa-permissions.json` without QA coordination.
5. **When adding a new module with custom routes**: before the first QA audit run, add a permission rule to `org-chart/sites/dungeoncrawler/qa-permissions.json` (or coordinate with `qa-dungeoncrawler`) for each new route namespace introduced by the module. Failure to do this will generate avoidable QA violations requiring an extra fix cycle. Example: `copilot_agent_tracker` added `langgraph-console/*` routes but no permission rule was present at first audit — causing 8 violations (2026-03-19 release-b cycle, commit `175b7c3b4`).

## Impact analysis — required for major functionality changes

Before implementing any feature (Type A) that makes **major functionality changes**:
- Document what existing flows, routes, modules, or behaviors will be affected.
- Flag any changes that could undermine existing functionality or break other modules.
- Write analysis in `features/<feature_id>/02-implementation-notes.md` under `## Impact Analysis` **before writing implementation code**.
- Escalate to `pm-dungeoncrawler` if the feature as designed would break existing user workflows in a way the AC does not account for.

Major changes include: new routes, hook implementations, schema migrations, permission model changes, changes to shared services or config entities.

## Living document model — features/<id>/

`features/<feature_id>/` is a **shared workspace** for PM, QA, and Dev:
- PM writes `feature.md` and `01-acceptance-criteria.md`
- QA writes `03-test-plan.md`
- Dev writes `02-implementation-notes.md`

Never overwrite another agent's file without explicit coordination.

## Game data constant access invariant (added 2026-03-22)

`CharacterManager` contains static catalogs (ANCESTRIES, HERITAGES, FEATS, etc.) keyed by **canonical name** (e.g., `'Half-Elf'`), but ancestry and heritage values stored in the database use **machine IDs** (e.g., `"half-elf"`).

**Invariant:** Never access `CharacterManager::ANCESTRIES[$key]`, `HERITAGES[$key]`, or similar catalogs directly using a machine ID or user-provided string.

**Required pattern:** Use the resolver helpers:
- `CharacterManager::resolveAncestryCanonicalName(string $machine_id): string` — converts machine ID to catalog key
- Always validate the lookup result is non-null before consuming schema fields (HP, speed, size, traits).

**Why:** Using machine ID directly causes silent null — no exception, no warning, just missing/wrong character data. This bug survived multiple release cycles undetected (discovered 2026-03-20, fixed commit `e97a248b5`).

**Rule:** If you add a new catalog or new catalog access, follow the same resolver pattern and validate non-null.


- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do a short in-scope review/refactor and write concrete recommendations in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-dungeoncrawler` with `Status: needs-info` and an ROI estimate.
- Do not expand scope across repos without an explicit delegated request.

## Code-review finding inbox items
- You may receive inbox items of the form `<date>-cr-finding-<finding-id>/` from `pm-dungeoncrawler`.
- These originate from `agent-code-review` MEDIUM+ findings (authority: `runbooks/shipping-gates.md` Gate 1b).
- Treat them as standard implementation/fix tasks. Include finding ID and severity in your outbox when done.

## Verified commands (cycle-start reference)

Use these at the start of any release cycle to confirm environment health before implementing:

```bash
# Confirm Drupal site root is accessible
ls /home/keithaumiller/forseti.life/sites/dungeoncrawler/web/

# Confirm Drush works and Drupal bootstraps
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && /home/keithaumiller/forseti.life/sites/dungeoncrawler/vendor/bin/drush status --fields=drupal-version,bootstrap

# Check installed custom module(s) are enabled (use full drush path — plain `vendor/bin/drush` may fail in headless context)
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && /home/keithaumiller/forseti.life/sites/dungeoncrawler/vendor/bin/drush pm:list --type=module | grep -E "ai_conversation|dungeoncrawler"
# Expected: all three should show "Enabled" — ai_conversation, dungeoncrawler_content, dungeoncrawler_tester
# If any show "Disabled": run drush pm:enable <module> --yes, then drush config:export --yes, then git add + commit config/sync/

# Confirm HQ git state
cd /home/keithaumiller/copilot-sessions-hq && git --no-pager status

# Read current-state digest (fastest context load)
cat sessions/dev-dungeoncrawler/artifacts/current-state.md

# Verify installed systemd unit matches source file (drift = stale QA env)
diff /home/keithaumiller/copilot-sessions-hq/scripts/systemd/copilot-sessions-hq-site-audit.service \
     /home/keithaumiller/.config/systemd/user/copilot-sessions-hq-site-audit.service && echo "OK: units match" || echo "DRIFT: installed unit differs from source"
```

If any of these fail at cycle start, record the failure in the outbox and escalate to `pm-dungeoncrawler` before implementing.

### Systemd unit drift — escalation rule (ADDED 2026-02-27)
If the `diff` above shows drift (installed unit ≠ source file):
1. Copy source over installed: `cp scripts/systemd/copilot-sessions-hq-site-audit.service ~/.config/systemd/user/`
2. Escalate to CEO executor to run `systemctl --user daemon-reload` (requires interactive dbus session — headless executor cannot do this)
3. Mark outbox `Status: blocked` and cite this rule — do not mark blocked without first performing step 1.

## Supervisor
- Supervisor: `pm-dungeoncrawler`
