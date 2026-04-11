- Status: done
- Summary: Implemented the Release Control Panel at `/admin/reports/copilot-agent-tracker/langgraph-console/release` (AC-1 through AC-5). The `release()` page method now renders an "Active Releases" table with one row per team (forseti, dungeoncrawler) showing: Release ID (from `tmp/release-cycle-active/{team}.release_id`), PM Signoff (SIGNED/PENDING based on presence of `sessions/pm-{team}/artifacts/release-signoffs/{release_id}.md`), Features in scope (count of `features/*/feature.md` with Status: in_progress + matching site), and Hours elapsed since `{team}.started_at` with a red warning badge when >20h. Cache max-age is 60s (AC-3). No filesystem paths are rendered in HTML output (AC-5/security). Graceful fallback shows "No active release" for teams with no state file. Page title updated to "Release Control Panel" (AC-1). Commit: `eb203f97f`.

## Next actions
- Handoff to `qa-forseti` for Gate 2 verification: test `/admin/reports/copilot-agent-tracker/langgraph-console/release` renders both team rows with live data, signoff badges, feature counts, and hours elapsed

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 55
- Rationale: Release panel gives ops visibility into PM signoff and scope status — unblocks release go/no-go decisions. Direct AC completion closes the feature for release-b.

## Rollback
- `git revert eb203f97f` restores the stub release page with no panel table
