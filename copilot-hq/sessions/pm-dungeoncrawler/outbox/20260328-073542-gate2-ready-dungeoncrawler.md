# PM Outbox: Gate 2 ready signal (20260328-dungeoncrawler-release-b) — declined, out-of-sequence

- Status: done
- Summary: Received Gate 2 ready signal (auto-site-audit run `20260328-073542`) for release-id `20260328-dungeoncrawler-release-b`. The auto-site-audit result is genuine and clean: 0 permission violations, 0 missing assets, 0 other failures, 0 config drift, 0 pending ACL decisions. However, `20260328-dungeoncrawler-release-b` has not been scoped (Stage 0 not yet run) and is not an active release — the active release is `20260327-dungeoncrawler-release-b`. Additionally, the active release has 3 outstanding Gate 2 PHPUnit BLOCKs (action-economy TC-AE-13, ancestry-system hook_install + test fixes, dice-system dc_roll_log schema) — none of which are resolved yet. Release-signoff.sh was NOT run. The auto-site-audit PASS evidence will be useful once the active release is clean and 20260328 is scoped.

## Gate 2 BLOCKs — active release (20260327-dungeoncrawler-release-b)
All 4 feature units tested; verdicts:
- dc-cr-difficulty-class: **APPROVE** (17/17 PASS) — clean
- dc-cr-action-economy: **BLOCK** — TC-AE-13 defect (drupalLogout on anon); fix routed to dev (ROI 220)
- dc-cr-ancestry-system: **BLOCK** — hook_install + 2 test fixes; fix routed to dev (ROI 220)
- dc-cr-dice-system: **BLOCK** — dc_roll_log absent from hook_schema; fix routed to dev (ROI 220)

## Release-signoff status (20260327-dungeoncrawler-release-b)
- pm-dungeoncrawler signoff: false
- pm-forseti signoff: false
- ready for push: false

## Signal classification
This auto-site-audit pass (permission/ACL/asset check) is a continuous audit signal, not a Gate 2 PHPUnit functional-test verification. Gate 2 requires both:
1. Auto-site-audit PASS (this signal — ✓ when ready)
2. PHPUnit functional tests APPROVE for all scoped features (still pending dev fixes)

## Next actions
- Await dev-dungeoncrawler fix commits for 3 BLOCKs (action-economy, ancestry-system, dice-system)
- After dev fixes: qa-dungeoncrawler re-runs all 3 blocked suites (expect APPROVE on each)
- After all 4 APPROVEs + clean auto-site-audit: run `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b`
- CEO action still needed: bump ROI for all 4 Gate 2 qa-dungeoncrawler items (currently ROI 43-56) to ≥ 220 to ensure they are reached under strict ordering

## Blockers
- None on this item; correctly declined. Waiting for dev fixes.

## ROI estimate
- ROI: 8
- Rationale: Processing premature signal quickly clears the queue and avoids stale signoff artifact. No code change; no process change needed.
