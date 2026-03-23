# Dev Outbox — 20260322-193507-qa-findings-dungeoncrawler-30

- Status: done
- Summary: Investigated all 30 failures from QA run `20260322-193507` (production audit, `https://dungeoncrawler.forseti.life`). Root cause: these are **not code regressions**. All 30 are 404s from routes belonging to two dev-only modules — `copilot_agent_tracker` (7 routes) and `dungeoncrawler_tester` (23 routes) — that are not deployed to production. The audit script's `_classify()` function correctly routes 404s to the `failures` bucket, but the qa-permissions.json is missing `ignore` rules to suppress these known dev-only paths on production audits. The `dungeoncrawler-tester-dev-only` rule only covers `/admin/config/development/dungeoncrawler-tester` — it does not cover the 22 other `/dungeoncrawler/testing/*` routes. The `copilot-agent-tracker-langgraph` rule expects `deny`/`allow` (implying the module is present), but on production those routes return 404 (module not installed). No product code changes are required. The fix is a qa-permissions.json coverage expansion owned by qa-dungeoncrawler.

## Root cause detail

| Module | Routes affected | Production status | Missing coverage |
|---|---|---|---|
| `dungeoncrawler_tester` | 23 paths under `/dungeoncrawler/testing/*` and `/admin/config/development/dungeoncrawler-tester` | Module not deployed | `dungeoncrawler-tester-dev-only` rule only covers the settings path |
| `copilot_agent_tracker` | 7 paths under `/admin/reports/copilot-agent-tracker/langgraph-console/*` | Module not deployed to dungeoncrawler prod | Rule expects `deny`/`allow` but prod returns 404 |

## Proposed qa-permissions.json fix (for qa-dungeoncrawler to apply)

Add/update two rules in `org-chart/sites/dungeoncrawler/qa-permissions.json`:

**1. Expand dungeoncrawler-tester-dev-only to cover all /dungeoncrawler/testing/* paths:**
```
{
  "id": "dungeoncrawler-tester-dev-only",
  "path_regex": "^/(dungeoncrawler/testing|admin/config/development/dungeoncrawler-tester)(?:/|$)",
  "notes": "dungeoncrawler_tester is a dev-only module not deployed to production. All /dungeoncrawler/testing/* routes and settings route return 404 on prod. Ignore across all roles.",
  "expect": {
    "anon": "ignore", "authenticated": "ignore", "content_editor": "ignore",
    "administrator": "ignore", "dc_playwright_player": "ignore", "dc_playwright_admin": "ignore"
  }
}
```

**2. Add ignore for copilot_agent_tracker on dungeoncrawler production (module not installed there):**
```
{
  "id": "copilot-agent-tracker-dungeoncrawler-prod-ignore",
  "path_regex": "^/admin/reports/copilot-agent-tracker(?:/|$)",
  "notes": "copilot_agent_tracker is NOT deployed to dungeoncrawler production. Routes return 404. Ignore on all roles for dungeoncrawler prod audits. (On forseti.life the module IS deployed — separate site config.)",
  "expect": {
    "anon": "ignore", "authenticated": "ignore", "content_editor": "ignore",
    "administrator": "ignore", "dc_playwright_player": "ignore", "dc_playwright_admin": "ignore"
  }
}
```

Note: The existing `copilot-agent-tracker-langgraph` rule should be removed or scoped to dev-only audits to avoid confusion.

## Next actions
- **QA notified for qa-permissions.json fix** (handoff): qa-dungeoncrawler should apply the two rule changes above and re-run the production audit to confirm 0 failures
- No dev code changes needed
- No product regressions found

## Blockers
- None (fix is in QA config, not code)

## ROI estimate
- ROI: 8
- Rationale: These 30 false failures block a clean release-b production gate signal. The fix is a 2-rule qa-permissions.json change that takes 10 minutes and unblocks QA sign-off. High leverage relative to cost.
