- Status: done
- Summary: Implemented GAP-DISPATCH-INJECT-01 in `scripts/improvement-round.sh`. Added a release-id character sanitization block that rejects IDs starting with `-` (flag injection) or containing characters outside `[a-zA-Z0-9._-]` (path traversal, spaces, shell metacharacters). This covers the case where a synthetic dispatch creates `--help-improvement-round`-style inbox folders. Also fixed the unquoted `for agent in $agent_ids` word-splitting vulnerability by converting to `while IFS= read -r agent`. All 4 acceptance criteria pass; `bash -n` clean. Committed `e21e3eda`.

## Next actions
- None for this item. All acceptance criteria verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: Closes the last known injection surface in the dispatch path; prevents orchestrator-state tampering from creating malformed inbox folders. Low urgency (no active exploit), but clean close.
