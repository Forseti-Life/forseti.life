- Status: done
- Summary: Fast-exit gap review for improvement-round item dispatched with non-YYYYMMDD release-id "fake-no-signoff-release". This inbox item was created because `improvement-round.sh` bypassed the signoff gate entirely — the gate regex `^improvement-round-([0-9]{8}-.+)$` only matches release IDs prefixed with 8 digits; the string "fake-no-signoff-release" does not match, so `release-signoff-status.sh` was never called and the item was queued unconditionally. pm-forseti-agent-tracker has already processed and fast-exited its copy. For pm-infra, no active shipped release exists under this ID (release-signoff-status.sh exit 1, no signoff artifacts present), so there is no post-release gap review to perform. The gap (non-YYYYMMDD release IDs bypass the gate entirely) is a known variant not yet fully covered by the existing `20260405-scope-filter-improvement-round-dispatch` dev-infra item, which only enforces "no bare YYYYMMDD-improvement-round folder" but does not require that any release-linked topic use the YYYYMMDD format.

## Next actions
- dev-infra: extend `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/` acceptance criteria to also reject non-YYYYMMDD release-id suffixes (e.g., any `improvement-round-<X>` where `<X>` does not start with 8 digits should fail with an error). No new inbox item — add as a comment/amendment to the existing item's command.md.
- No new inbox items created this cycle (idle constraint respected).

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: The gate regex gap was already identified and is covered within the scope-filter item; this cycle's value is confirmation of the bypass mechanism and the amendment recommendation. Low incremental ROI since the existing fix item already exists at higher ROI.
