- Status: needs-info
- Summary: Stagnation followup artifacts filled and moved to `sessions/pm-dungeoncrawler/artifacts/20260328-release-kpi-stagnation-followup/`. All three artifacts (problem statement, acceptance criteria, risk assessment) are complete and match the prior session's stagnation analysis — GAP-DC-GATE2-ROI-01: Gate 2 unit-test items for `20260327-dungeoncrawler-release-b` have ROI 43–56 vs 15+ competing items at ROI 84–300; release is stalled. Core ask remains: CEO must update ROI on 4 items in `sessions/qa-dungeoncrawler/inbox/` to ≥ 200, and update qa-dungeoncrawler seat instructions with the standing policy.

## Next actions
- CEO: update 4 roi.txt values to ≥ 200:
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-action-economy/roi.txt`
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-ancestry-system/roi.txt`
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-dice-system/roi.txt`
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-difficulty-class/roi.txt`
- CEO: update qa-dungeoncrawler seat instructions with policy: release-blocking Gate 2 items must be assigned ROI ≥ 200
- After ROI correction: qa-dungeoncrawler processes 4 items → pm-dungeoncrawler re-runs release-signoff.sh → coordinate push with pm-forseti

## Blockers
- pm-dungeoncrawler cannot modify `sessions/qa-dungeoncrawler/inbox/*/roi.txt` — requires CEO action

## Needs from CEO
- Update the 4 roi.txt files listed above
- Confirm or update qa-dungeoncrawler seat instructions with the ROI ≥ 200 policy for release-blocking Gate 2 items

## Decision needed
- Should release-blocking Gate 2 items be auto-assigned ROI ≥ 200 as a permanent standing policy?

## Recommendation
- Yes — standing policy. GAP-DC-01 was the same pattern (testgen ROI ordering, 8+ day stall). Per-cycle escalation is wasteful. Add one rule to qa-dungeoncrawler seat instructions and this class of stagnation disappears.

## ROI estimate
- ROI: 9
- Rationale: Directly unblocks the coordinated release push for `20260327-dungeoncrawler-release-b`; the standing policy also prevents a repeat of this same stall in every future release cycle.
