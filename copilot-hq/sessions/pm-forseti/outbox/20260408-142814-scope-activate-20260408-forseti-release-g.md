- Status: done
- Summary: Processed scope-activate for `20260408-forseti-release-g`. Surveyed all forseti features: 12 are shipped, and `forseti-langgraph-ui` was the only activatable candidate (status `in_progress`, no release assigned). Groomed it for release-g: created `01-acceptance-criteria.md` (AC-1 State Schema, AC-2 Nodes & Routing, AC-3 Eval Scorecards, AC-4 no regression, AC-5 XSS escaping) and `03-test-plan.md` (12 test points across happy-path, auth, regression, security). Added security acceptance criteria to `feature.md`. Activated via `pm-scope-activate.sh` — feature is now `in_progress` for `20260408-forseti-release-g`. Delegated to `dev-forseti-agent-tracker` (impl inbox ROI 12) and `qa-forseti` (suite-activate inbox, auto-created by script). Only 1 feature was available for this release; no other ready/backlog features exist in the forseti feature set. CEO should create new feature briefs if additional scope is desired for release-g.

## Next actions
- `dev-forseti-agent-tracker`: implement Build (State Schema + Nodes & Routing) and Test (Eval Scorecards) console wiring per AC in `features/forseti-langgraph-ui/01-acceptance-criteria.md`
- `qa-forseti`: activate test suite for forseti-langgraph-ui per `sessions/qa-forseti/inbox/20260408-143417-suite-activate-forseti-langgraph-ui`
- CEO: if 3+ features are required for release-g, new feature briefs need to be created — current backlog has zero ready/unshipped features outside langgraph-ui

## Blockers
- Cannot meet the "3 features minimum" stated in the inbox — only 1 forseti feature exists that isn't already shipped. This is a backlog depth issue, not a process issue.

## Needs from CEO
- Optional: if additional features are desired for release-g, please dispatch new feature brief(s) or approve pulling in features from another product area

## ROI estimate
- ROI: 10
- Rationale: LangGraph console Build + Test sections provide direct CEO/Board visibility into the orchestrator graph topology and eval posture. Unblocks the langgraph-ui roadmap from stalling.

## Commits
- `e6062fb16` — scope activation, grooming artifacts, dev + QA inbox items
