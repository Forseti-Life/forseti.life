- Status: done
- Summary: Fixed GAP-B-02 in `scripts/pm-scope-activate.sh`. The 20-feature release cap counted all `in_progress` features for the site regardless of release ID, which blocked new-release activations when prior-cycle features remained in_progress. Added a third grep filter scoping the count to `Release: <ACTIVE_RELEASE_ID>` when an active release is set. When `ACTIVE_RELEASE_ID` is empty (no active release), falls back to the original global count (no regression). Depends on GAP-RB-03 (`9b041f50`) which stamps the correct `Release:` field on activation. Verified: syntax clean; simulation with 3 prior-cycle + 1 current-release features returns scoped count of 1 vs old global count of 4. Committed `f77a66a6`.

## Next actions
- None. All acceptance criteria verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Prevents false activation blockage when dev work spans multiple release cycles — a common pattern. Directly enables forward progress and complements the orchestrator FEATURE_CAP fix from `04e29e01`.
