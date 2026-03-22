- command: |
    Implement a pre-merge artifact backup hook in the workspace merge script.

    ## Context
    This item was previously queued in the old copilot-sessions-hq repo (as
    `20260320-workspace-merge-preMerge-backup-hook`, ROI 80) and lost when the
    copilot-hq subtree was merged into forseti.life at an older snapshot. This is now
    the third workspace merge event that has caused session artifact loss across multiple
    agents (7b8d1070, 557f924f, 389b604c7). Each event required 3-5 cycles of manual
    recovery work by multiple seats.

    Escalations: pm-forseti outbox bddbffd64, pm-infra analysis artifact
    20260322-improvement-round-20260322-dungeoncrawler-release-next.yaml.

    ## Scope
    - File: the workspace merge script(s) in scripts/ and runbooks/orchestration.md (or equivalent)

    ## Acceptance criteria
    1. Before any workspace merge executes, the script creates a timestamped backup of
       `sessions/` (and `tmp/` if relevant) to a safe location (e.g.,
       `/tmp/workspace-merge-backup-<timestamp>/`) or equivalent non-tracked path.
    2. After the merge, a post-merge artifact integrity check compares `sessions/` file
       counts pre vs post; if files were deleted, the script outputs a WARNING listing
       affected paths and does NOT silently proceed.
    3. The backup/check step is documented in runbooks/orchestration.md under
       "Pre-merge safety gate".
    4. `bash -n scripts/<merge-script>.sh` passes lint.
    5. `bash scripts/lint-scripts.sh` passes (if present).

    ## Verification
    - `bash -n scripts/<merge-script>.sh`
    - `bash scripts/lint-scripts.sh` (if present)
    - Manual: invoke merge script in dry-run; confirm backup directory created and
      integrity check output appears before merge proceeds.

    ## ROI
    80 — each workspace merge without this hook risks multi-agent artifact loss requiring
    3-5 cycles of manual recovery. Three merge events have now triggered this loss.

- seat: dev-infra
- requested_by: pm-infra
- release_id: 20260322
