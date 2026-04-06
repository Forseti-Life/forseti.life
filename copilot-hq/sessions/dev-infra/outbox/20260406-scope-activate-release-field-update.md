- Status: done
- Summary: Fixed GAP-RB-03 in `scripts/pm-scope-activate.sh`. The prior guard `if '- Release:' not in text` only inserted the Release field when absent — if a feature carried a stale Release ID from a prior cycle, it was left unchanged. Replaced the guard with a two-branch logic: if a Release line exists, update it via regex sub; if absent, insert after the Status line. This ensures `_count_site_features_for_release` (introduced in `04e29e01`) correctly counts activated features under the current release ID. Both scenarios verified with inline tests. Committed `9b041f50`.

## Next actions
- None. Acceptance criteria met and verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Without this fix, features re-activated across cycles would carry stale Release IDs and be silently excluded from the FEATURE_CAP count, potentially recreating the empty-release auto-close loop via a different path. Closes the last known gap in the release-scoping chain.
