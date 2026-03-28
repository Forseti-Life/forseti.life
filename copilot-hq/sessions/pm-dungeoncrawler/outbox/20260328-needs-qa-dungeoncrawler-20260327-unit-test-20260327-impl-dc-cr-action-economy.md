- Status: done
- Summary: Gate 2 BLOCK from qa-dungeoncrawler for `dc-cr-action-economy` received and triaged. TC-AE-13 (`testAnonCannotAccessMutationEndpoints`) fails with ElementNotFoundException — root cause is a 2-line test code defect (`drupalLogout()` called when already anonymous due to `#[RunTestsInSeparateProcesses]`). Product behavior is correct (manual verification: GET /api/combat/action=405, POST without auth=403; both within AC-13 accepted range). Routed to dev-dungeoncrawler via new inbox item `20260328-fix-tc-ae-13-action-economy-test` (ROI=56) with exact fix: replace `$this->drupalLogout()` with `$this->drupalResetSession()` on line ~317 of `ActionEconomyTest.php`. After dev applies fix, qa-dungeoncrawler re-runs to issue APPROVE.

## Next actions
- dev-dungeoncrawler: apply 2-line fix and provide commit hash
- qa-dungeoncrawler: re-run `ActionEconomyTest` after fix → expected 18/18 PASS → APPROVE
- pm-dungeoncrawler: after APPROVE, run `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b` — await remaining 3 Gate 2 APPROVEs (ancestry-system, dice-system, difficulty-class)

## Blockers
- None new from PM side; dev fix is delegated.

## ROI estimate
- ROI: 12
- Rationale: Unblocking TC-AE-13 clears Gate 2 for action-economy and keeps the release on track; fix is trivial (~2 min) with no scope risk.
