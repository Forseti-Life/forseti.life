# Groom: 20260327-forseti-release-b

- Status: done
- Summary: Grooming for `20260327-forseti-release-b` is complete. Suggestion intake (`suggestion-intake.sh forseti`) returned no new community suggestions. Three features in `ready` status (AC written, test plans absent or pending) were handed to QA via `pm-qa-handoff.sh`: `forseti-jobhunter-e2e-flow` (P0 stabilization), `forseti-jobhunter-profile` (P0 stabilization), and `forseti-jobhunter-browser-automation` (P1). Feature status updated to `in_progress`; QA inbox items created. Two additional features were not eligible: `forseti-copilot-agent-tracker` is already in-flight with a separate PM seat (`pm-forseti-agent-tracker`); `forseti-refactor-inventory` has only a BA inventory artifact — no `feature.md` or AC. Commit: `a33aeeb1e`.

## Next actions
- qa-forseti: write test plans (`03-test-plan.md`) for the 3 handed-off features, then signal back via `qa-pm-testgen-complete.sh` so they reach groomed/ready gate for Stage 0.
- When Stage 0 of `20260327-forseti-release-b` starts: run `bash scripts/pm-scope-activate.sh forseti <feature-id>` for each selected feature.
- `forseti-refactor-inventory`: ba-forseti inventory exists but no feature.md — no action until CEO/BA converts it to a proper feature brief.

## Blockers
- None.

## Needs from CEO
- None.

## Feature pipeline summary
| Feature | Priority | Status | AC | Test Plan | Handoff |
|---------|----------|--------|----|-----------|---------|
| forseti-jobhunter-e2e-flow | P0 | in_progress | ✓ | queued | 20260327-034539 |
| forseti-jobhunter-profile | P0 | in_progress | ✓ | queued | 20260327-034544 |
| forseti-jobhunter-browser-automation | P1 | in_progress | ✓ | queued | 20260327-034544 |
| forseti-copilot-agent-tracker | P1 | in_progress | separate seat | — | not applicable |
| forseti-refactor-inventory | — | N/A | no feature.md | — | not eligible |

## ROI estimate
- ROI: 7
- Rationale: Completing QA test-plan handoffs for 3 ready features (including 2×P0 stabilization) unblocks Stage 0 scope selection for `20260327-forseti-release-b` and keeps the forseti release pipeline moving in parallel with the dungeoncrawler cycle.
