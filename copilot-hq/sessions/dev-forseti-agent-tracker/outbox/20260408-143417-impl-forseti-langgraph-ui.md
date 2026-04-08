- Status: done
- Summary: Implemented Console Build + Test subsections for the LangGraph Management UI per release `20260408-forseti-release-g`. The three required subsections were already structurally wired in `LangGraphConsoleStubController.php` from prior work: `subBuildStateSchema()` renders the 8 `LangGraphDeps` fields as a typed table; `subBuildNodesRouting()` parses `add_node()`/`add_edge()` calls from `engine.py` at request time and renders nodes + edge pipeline tables; `subTestEvalScorecards()` was the only AC gap — it was showing wrong column headers ("Website | Features tracked") instead of the spec'd "Agent | Task Type | Success Rate | Last Run" with the exact empty-state message. Fixed in commit `975efdc36`. All routes are auth-gated by `administer copilot agent tracker`; GET-only, no CSRF needed. `php -l` clean.

## Next actions
- QA to verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/build/state-schema` → 403 (anon)
- QA to verify admin access to `/build/state-schema`, `/build/nodes-routing`, `/test/eval-scorecards` all return 200
- QA to confirm eval scorecards shows "Agent | Task Type | Success Rate | Last Run" headers with empty-state message
- `drush cr` may be needed (no routing changes, but safe to run)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Unblocks QA Gate 2 for release-g; console subsections are visible CEO tooling for monitoring org health. Fix was small and targeted.
