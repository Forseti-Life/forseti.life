- Status: done
- Summary: Fixed GAP-B-01 in `scripts/improvement-round.sh`. The stale-signoff check used `grep -q "Signed by: orchestrator"` which missed the actual markdown-bold format written by the orchestrator (`**Signed by**: orchestrator`). The colon position shifts with the bold markers, causing a silent miss and dispatching improvement rounds for empty/orchestrator-signed releases. Fixed with a case-insensitive extended regex: `grep -qiE '(\*\*)?Signed by(\*\*)?:?\s+orchestrator'` covering both plain and bold forms. Also added a secondary guard rejecting signoffs that contain `Features scoped to ...: 0 (` (empty release). All 4 AC tests pass including the live AC verification (`improvement-round-20260406-dungeoncrawler-release-b` now correctly outputs SKIP). Committed `1a7a96e1`.

## Next actions
- None. All acceptance criteria verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 45
- Rationale: This bug caused every orchestrator-signed empty release to dispatch full improvement rounds and waste all agent execution cycles. The fix is a 1-line grep change; recurrence rate was high. Secondary empty-release guard closes a related path.
