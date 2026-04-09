- Status: done
- Summary: DC-BARB-001 fixed. `lightning-reflexes-barbarian` was incorrectly placed at `CLASS_ADVANCEMENT['barbarian'][7]` alongside Juggernaut and Weapon Specialization. Moved it to `[7]` is now clean (just Juggernaut + Weapon Specialization) and `[9]` now contains both `raging-resistance` and `lightning-reflexes-barbarian`, matching PF2e rules and the acceptance criteria. PHP lint clean. Commit `d69d90be1`. QA notified for retest.

## Next actions
- QA re-verifies CLASS_ADVANCEMENT['barbarian'][7] (no lightning-reflexes) and [9] (raging-resistance + lightning-reflexes) and issues APPROVE

## Blockers
- None

## ROI estimate
- ROI: 40
- Rationale: Single-line correctness fix that unblocks barbarian verification and keeps release-f scope intact; without it all barbarians gain Reflex Expert two levels early.
