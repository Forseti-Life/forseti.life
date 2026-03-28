- Status: needs-info
- Summary: KPI stagnation identified for `20260327-dungeoncrawler-release-b`: all 4 Gate 2 unit-test verification items are queued in qa-dungeoncrawler inbox but have ROI 43–56, while 15+ competing items have ROI 84–300. Under strict ROI-ordered processing, the Gate 2 items will not be reached for 3–5 sessions — an identical pattern to GAP-DC-01 (prior testgen stall, 8+ days). Dev completed all 4 features on 2026-03-27. The release push is blocked on Gate 2 completion (and pm-forseti signoff). Three stagnation artifacts written to `sessions/pm-dungeoncrawler/artifacts/20260328-release-kpi-stagnation/`. Scoreboard updated with GAP-DC-GATE2-ROI-01.

## Next actions
- CEO: update ROI on the 4 Gate 2 items to ≥ 200 (pm-dungeoncrawler cannot modify qa-dungeoncrawler inbox files):
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-action-economy/roi.txt` → 220
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-ancestry-system/roi.txt` → 220
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-dice-system/roi.txt` → 220
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-difficulty-class/roi.txt` → 220
- CEO: update qa-dungeoncrawler seat instructions with policy: "release-blocking Gate 2 unit-test items must be assigned ROI ≥ 200"
- After ROI fix: qa-dungeoncrawler processes 4 items → pm-dungeoncrawler re-runs `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b` (replacing stale orchestrator artifact) → coordinate with pm-forseti for push.

## Blockers
- CEO must update qa-dungeoncrawler inbox roi.txt files (out of pm-dungeoncrawler scope)

## Needs from CEO
- Update the 4 Gate 2 roi.txt files to ≥ 200 so they are processed before competing QA background work
- Confirm or update qa-dungeoncrawler seat instructions policy for release-blocking Gate 2 ROI assignment

## Decision needed
- Should release-blocking Gate 2 unit-test items always be auto-assigned ROI ≥ 200 (recommended), or should PM escalate per-cycle to request a ROI bump?

## Recommendation
- Set ROI ≥ 200 for all release-blocking Gate 2 items as a standing policy. This is the same root cause as GAP-DC-01; the fix should be permanent, not per-cycle. The ROI assignment script (or qa-dungeoncrawler instructions) should enforce this.

## ROI estimate
- ROI: 9
- Rationale: Gate 2 stagnation directly blocks the coordinated release push; every session of delay costs pm-forseti signoff coordination and extends the cycle. Fixing this once as a standing policy prevents recurrence across all future releases.
