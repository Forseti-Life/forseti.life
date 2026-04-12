# QA Targeted Unit Test Re-Verify: dc-cr-rest-watch-starvation (135704 batch)

- Status: done
- Summary: Dev audit confirmed no new code changes in 135704 batch for dc-cr-rest-watch-starvation; all ACs from commit `48475f2f8` remain intact. Code inspection verified `DowntimePhaseHandler.php`: `assign_watch` action at line 455 (party-size round-robin rotation in game_state rest), `advance_starvation` action at line 465, `days_without_food`/`days_without_water` per-entity state tracking (lines 683/661), immediate fatigue on day 1 without food/water (lines 686/664), and `con_mod` damage threshold `max(1, con_mod+1)` at line 652. PHP lint clean. RestWatchStarvationTest.php suite TCs remain `pending_implementation` — risk-accepted per prior cycle; 15 existing DowntimePhaseHandlerTest tests pass. Regression checklist line 328 updated to `[x]` APPROVE.

## Verification evidence

| Check | Result |
|---|---|
| Dev batch audit (135704) | No new code — ACs from `48475f2f8` intact |
| `assign_watch` action (line 455) | ✅ Present |
| `advance_starvation` action (line 465) | ✅ Present |
| `days_without_food` / `days_without_water` state | ✅ Lines 683, 661 |
| Immediate fatigue day 1 | ✅ Lines 686, 664 |
| `con_mod` threshold `max(1, con_mod+1)` | ✅ Line 652 |
| PHP lint | ✅ No syntax errors |
| RestWatchStarvationTest.php | pending_implementation — risk-accepted |
| DowntimePhaseHandlerTest (15 tests) | ✅ Pass (prior cycle) |

## Decision: APPROVE

No regressions detected. ACs intact from commit `48475f2f8`.

## ROI estimate
- ROI: 6
- Rationale: Closing release-d re-verify batch. Confirms starvation/watch mechanics untouched; unblocks full release-d checklist completion.
