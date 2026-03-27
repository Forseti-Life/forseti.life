- command: |
    Fix `scripts/pm-qa-handoff.sh` to atomically update `feature.md` status to `in_progress` when creating QA testgen inbox items.

    ## Problem
    `pm-qa-handoff.sh forseti <feature-id>` creates a QA inbox item but does NOT update the feature's `feature.md` status field.
    Result: PM groom commits can claim "status → in_progress" but the file is never actually changed (discovered via commit `a33aeeb1e`/`241207a70`).

    ## Acceptance criteria
    1. After `pm-qa-handoff.sh <site> <feature-id>` runs, `features/<feature-id>/feature.md` has `- Status: in_progress` (sed in-place or equivalent).
    2. Script exits non-zero if the feature.md status update fails.
    3. Running the script twice on the same feature-id is idempotent (already `in_progress` → no error, no duplicate change).
    4. Add a `grep` verification step at the end: `grep "Status: in_progress" features/<feature-id>/feature.md` with exit 1 on failure.

    ## Verification
    - Run `pm-qa-handoff.sh forseti forseti-jobhunter-e2e-flow` on a feature with `status: ready`.
    - Confirm `feature.md` shows `- Status: in_progress` immediately after.
    - Run again; confirm no error and status unchanged.

    ## Files to change
    - `scripts/pm-qa-handoff.sh`

    ## ROI rationale
    Prevents silent state drift where QA inbox items are created but features appear still in "ready" pool. Could cause duplicate testgen items if automation re-runs intake and finds `status: ready` features already in QA.
