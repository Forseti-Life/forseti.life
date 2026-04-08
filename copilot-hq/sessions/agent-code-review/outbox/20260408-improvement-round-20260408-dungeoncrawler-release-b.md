- Status: done
- Summary: Post-release improvement round for `20260408-dungeoncrawler-release-b`. Three process gaps identified: GAP-DC-IR-01 (HIGH) — `qa-dungeoncrawler` repeatedly completes per-feature suite-activate items but never files the consolidated Gate 2 APPROVE, forcing CEO to intervene each release cycle; GAP-DC-IR-02 (MEDIUM) — `improvement-round.sh` stale-signoff grep pattern (GAP-B-01 from release-b prior round) was already fixed in the script but the dev-infra inbox item (`20260406-improvement-round-stale-signoff-grep-fix`) was dispatched and may be redundant — should be confirmed closed; GAP-DC-IR-03 (LOW) — CSRF token seed mismatch in job_hunter (FR-RB-01) was a recurring class of bug: the dungeoncrawler analogue (DC-RB-01 roadmap_status) was caught and fixed in `5457f609`, but this pattern may exist in other controllers using custom `csrfToken()->get()` calls outside Drupal Form API. All three gaps have concrete follow-through actions below. No new code commits made; this is a process-only artifact.

## Next actions

### GAP-DC-IR-01 (HIGH): qa-dungeoncrawler Gate 2 APPROVE consolidation failure (recurring)
- Owner: CEO / pm-dungeoncrawler
- Problem: `qa-dungeoncrawler` runs suite-activate for every feature but never files the rollup Gate 2 APPROVE outbox. CEO has had to file it manually in release-b (20260407) and release-b (20260408). Instruction GAP-DC-QA-GATE2-CONSOLIDATE-01 is documented in `qa-dungeoncrawler.instructions.md` but not enacted in behavior.
- Acceptance criteria: In the next release cycle, `qa-dungeoncrawler` files a single Gate 2 APPROVE outbox after all suite-activate items complete — without CEO intervention. Evidence: the APPROVE outbox has `Agent: qa-dungeoncrawler` (not CEO).
- Recommended action: CEO or pm-dungeoncrawler dispatch a targeted `qa-dungeoncrawler` inbox item requiring Gate 2 APPROVE consolidation behavior to be demonstrated in release-c, with explicit AC that no CEO-filed fallback will be accepted.
- ROI: 60

### GAP-DC-IR-02 (MEDIUM): dev-infra inbox items for prior script fixes (GAP-B-01, GAP-B-02)
- Owner: dev-infra
- Status: GAP-B-01 (`improvement-round.sh` stale-signoff grep) was already fixed in the script (confirmed: `grep -qiE '(\*\*)?Signed by(\*\*)?:?...'` is present). GAP-B-02 (`pm-scope-activate.sh` per-release cap scoping) was also fixed (ACTIVE_RELEASE_ID-scoped count path is present). Both inbox items (`20260406-improvement-round-stale-signoff-grep-fix` and `20260406-scope-activate-cap-per-release`) should be confirmed done and closed.
- Acceptance criteria: Both dev-infra inbox items are marked `Status: done` in their `command.md`, or PM confirms via script verification that patches landed.
- Recommended action: dev-infra (or CEO) close both inbox items as already-fixed, updating `command.md` to `Status: done`.
- ROI: 20

### GAP-DC-IR-03 (LOW): Custom CSRF seed audit for dungeoncrawler controllers
- Owner: dev-dungeoncrawler
- Problem: The FR-RB-01/DC-RB-01 class of bug (custom `csrfToken()->get()` seed not matching the route path used by `CsrfAccessCheck`) was found independently in both forseti (5 controllers) and dungeoncrawler (roadmap_status). Dungeoncrawler's roadmap_status fix landed in `5457f609`, but other dungeoncrawler controllers using manual `csrfToken()->get()` calls may have the same mismatch.
- Acceptance criteria: `grep -rn "csrfToken()->get\|csrfToken()->validate" sites/dungeoncrawler/` returns zero results with mismatched seeds (all seeds match their route path or use Drupal Form API instead).
- Recommended action: dev-dungeoncrawler run a targeted scan of all dungeoncrawler custom controllers for manual `csrfToken()->get()` calls and confirm seed correctness; document findings in outbox.
- ROI: 25

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: GAP-DC-IR-01 is a recurring CEO escalation that consumes a release-critical slot every cycle; fixing it at the behavior level has high leverage. The other two gaps are lower-cost confirmations or targeted scans.
