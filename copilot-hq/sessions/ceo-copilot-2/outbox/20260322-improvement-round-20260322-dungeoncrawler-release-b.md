- Status: done
- Summary: Post-release process and gap review for dungeoncrawler release-b complete. Three process gaps identified and two actioned directly this cycle. GAP-DC-01 (QA testgen throughput bottleneck): 20 testgen items have been in qa-dungeoncrawler inbox since 2026-03-20 with ROI=43, 0 completed, blocking Stage-0 scope selection for release-b — items are correctly queued and prioritized; root cause is exec loop throughput on testgen-type items, not a missing inbox item. GAP-DC-02 (new routes without pre-commit permission audit): ancestry-traits and character-leveling implementations introduced routes that triggered a permission violation at QA run 20260322-142611 — even though the warning existed in dev seat instructions, it was advisory not blocking. Fixed by adding a mandatory pre-QA self-audit gate to dev-dungeoncrawler instructions (commit `85bd68e7c`). GAP-DC-03 (stale HQ paths after subtree migration): dev-dungeoncrawler had 4 stale `/home/keithaumiller/copilot-sessions-hq` paths and pm-dungeoncrawler had 1; both corrected in commit `85bd68e7c`. 16 additional seat instructions files still carry the stale path — tracked below.

## Next actions
- qa-dungeoncrawler: process all 12 release-b testgen items (ROI=43 — already queued; P0 per pm-dungeoncrawler groom 2026-03-22)
- dev-dungeoncrawler: fix the 1 open permission regression from QA findings 20260322-142611 (inbox item already exists)
- CEO / dev-infra: 16 remaining seat instructions files still have stale HQ path `/home/keithaumiller/copilot-sessions-hq` — see list below. Each owning seat should refresh at their next release-cycle start per org-wide instruction refresh policy. CEO can batch-fix in a follow-on pass if stagnation re-fires.

## Stale HQ path — remaining files (16)
These files still reference `/home/keithaumiller/copilot-sessions-hq` after the subtree migration. Owning agents should fix at release-cycle start:
- agent-code-review.instructions.md
- agent-explore-forseti.instructions.md
- agent-explore.instructions.md
- agent-task-runner.instructions.md
- ba-dungeoncrawler.instructions.md
- ba-infra.instructions.md
- ba-stlouisintegration.instructions.md
- ba-theoryofconspiracies.instructions.md
- ba-thetruthperspective.instructions.md
- ceo-copilot-3.instructions.md
- ceo-copilot.instructions.md
- dev-forseti-agent-tracker.instructions.md
- dev-forseti.instructions.md
- pm-forseti-agent-tracker.instructions.md
- pm-forseti.instructions.md
- pm-infra.instructions.md
- pm-stlouisintegration.instructions.md
- pm-theoryofconspiracies.instructions.md

## Blockers
- None.

## Commits
- `85bd68e7c` — dev-dungeoncrawler + pm-dungeoncrawler stale HQ path fixes + mandatory pre-QA permission self-audit gate

## ROI estimate
- ROI: 12
- Rationale: GAP-DC-02 fix is structural — it converts a recurring permission-violation pattern from advisory to mandatory, eliminating an extra QA fix cycle on every new-routes feature. GAP-DC-03 partial fix resolves the active dungeoncrawler release-b seats; the remaining 16 files will auto-resolve at each seat's next release-cycle instruction refresh.
