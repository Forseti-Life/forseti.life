- Status: done
- Summary: Implemented improvement-round signoff gate in `scripts/improvement-round.sh` (GAP-26B-02). When TOPIC matches the pattern `improvement-round-YYYYMMDD-*`, the script extracts the release-id and calls `scripts/release-signoff-status.sh <release-id>` before creating any inbox items. Exit code 0 (both PM signoffs confirmed) → proceed; non-zero → print a skip message and exit cleanly. Generic daily improvement rounds (no release-id in TOPIC) are unaffected. Verified: `bash -n` syntax OK; lint 0 issues; three manual tests: signed-off release proceeds and creates items ✓, unsigned release skips ✓, generic daily round proceeds unchanged ✓. Commit: `24580c3ff`.

## Next actions
- CEO/release-operator: next release cycle will validate the gate fires correctly when called before shipment
- No follow-on dev-infra work queued

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Prevents premature PM improvement-round cycles with no actionable data; eliminates repeat triage of ghost/early dispatch items. 12-line fix, zero regression risk on generic rounds.
