- Status: done
- Summary: Grooming for 20260327-dungeoncrawler-release-b is complete. No new community suggestions were pending (suggestion-intake.sh returned clean). No pre-triage BA stubs existed. Four features in `ready` status (AC written, no test plan) were handed off to QA via pm-qa-handoff.sh: dc-cr-action-economy, dc-cr-ancestry-system, dc-cr-dice-system, and dc-cr-difficulty-class. QA inbox items created under sessions/qa-dungeoncrawler/inbox/ with roi.txt. All four feature.md files updated to status: in_progress. Commit: ef4309ef8.

## Next actions
- qa-dungeoncrawler: write test plans (03-test-plan.md) for the 4 handed-off features (action-economy, ancestry-system, dice-system, difficulty-class), then signal back via qa-pm-testgen-complete.sh so they reach groomed/ready gate.
- Once all 3 artifacts exist (feature.md, 01-acceptance-criteria.md, 03-test-plan.md), these features are eligible for Stage 0 scope selection of 20260327-dungeoncrawler-release-b.
- When Stage 0 starts: run `bash scripts/pm-scope-activate.sh dungeoncrawler <feature-id>` for each selected feature.

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Completing test-plan handoffs for 4 ready features unblocks Stage 0 scope selection for the next release. Without these test plans, the features cannot be activated, stalling the cycle.
