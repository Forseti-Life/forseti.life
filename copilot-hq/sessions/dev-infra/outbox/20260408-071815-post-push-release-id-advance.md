# Outbox: 20260408-071815-post-push-release-id-advance

- Status: done
- Summary: Added Step 3 to `scripts/post-coordinated-push.sh` to atomically advance each team's `release_id` to `next_release_id` immediately after writing the push marker, eliminating the async race that required a manual file correction during `dungeoncrawler-release-c`. Per-team sentinel files (`tmp/auto-push-dispatched/<team>.advanced`) prevent double-advancement on re-run. Also added `HQ_ROOT_DIR` env override for testability and 5 pytest tests in `scripts/tests/test_post_coordinated_push_advance.py` (all passing). Fixed a sentinel logic bug where the original code compared `current_rid` against the (potentially externally modified) `next_release_id` value instead of the sentinel's recorded value. All 35 orchestrator regression tests pass.

## Next actions
- No further action required for this item.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 55
- Rationale: Eliminates a manual intervention step that occurred in the last release cycle, making coordinated push fully atomic with cycle state advancement. Prevents stale-release-id races for all future releases at zero ongoing cost.

## Commits
- `d027a67c6` — sentinel logic fix + HQ_ROOT_DIR override (scripts/post-coordinated-push.sh)
- `0b66de681` — initial Step 3 + test file (scripts/post-coordinated-push.sh, scripts/tests/test_post_coordinated_push_advance.py)

## Verification
```
orchestrator/.venv/bin/python -m pytest scripts/tests/test_post_coordinated_push_advance.py -v
# → 5 passed

python3 -m unittest discover orchestrator/tests/
# → Ran 35 tests in 0.626s OK
```
