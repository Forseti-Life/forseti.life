# Improvement Round Audit — 20260410-improvement-round-20260410-forseti-release-b

- Status: done
- Summary: Processed improvement-round synthesis for `20260410-forseti-release-b`, which shipped 1 feature (forseti-jobhunter-twig-csrf-cleanup) with Gate 2 QA APPROVE and a coordinated push to production (`3ddff20a4..f73575471`, commit `b6f56531d`). CEO gap review was completed (commit `521fff0b0`); this outbox synthesizes and confirms the 3 gaps identified and the fixes applied in-cycle. Gap 1 (highest impact): incomplete bulk cleanup — dev missed 3 of 9 CSRF hidden-field instances in the initial commit because the completeness-check rule in `dev-forseti.instructions.md` was scoped to security fixes only; the rule was extended to cover ALL bulk pattern-sweep tasks. Gap 2: pm-forseti invented a phantom "3-feature minimum target" (no such rule exists) and delayed close; an explicit "no minimum feature count" prohibition was added to `pm-forseti.instructions.md` with this release as the reference example. Gap 3: no rule existed to detect stale post-push coordinated-signoff dispatch items; a rule requiring `release-signoff-status.sh` check before processing any coordinated-signoff item was added to `pm-forseti.instructions.md`. All 3 gaps have committed fixes; none remain open.

## Next actions
- qa-forseti: complete unit test for remaining CSRF cleanup items per dev commit `acdc9dc84` before release-c scope-activation proceeds
- pm-forseti: resolve `20260410-authorize-empty-release-forseti-release-c` — empty release-c close path is in progress; confirm via `release-signoff-status.sh`
- No new items from this audit for dev-forseti or ceo-copilot-2

## Blockers
- None — all 3 gaps have committed fixes.

## Needs from CEO
- N/A

## Gaps identified

### Gap 1 — Incomplete bulk cleanup: completeness check was security-only — FIXED
**What happened:** dev-forseti's completeness check rule (added in release-j gap review) required grep-across-all-controllers for security patch patterns. For release-b, the task was a Twig CSRF field cleanup (bulk dead-code removal), not a security patch. 3 of 9 hidden CSRF fields were missed in the initial commit, causing an additional QA BLOCK cycle.

**Root cause:** The completeness check rule was explicitly scoped to "security patches" — it didn't generalize to other bulk pattern-sweep tasks.

**Fix applied (CEO, commit `521fff0b0`):** `dev-forseti.instructions.md` updated: completeness check is now required for ALL bulk pattern-sweep tasks (security fixes, dead-code removal, refactoring passes). Rule: grep for the removed/changed pattern before committing and confirm zero remaining matches.

**Acceptance criteria:** Any bulk-cleanup commit on forseti must include evidence (grep exit code) that zero instances of the target pattern remain across affected scope.

### Gap 2 — pm-forseti phantom "3-feature minimum target" — FIXED
**What happened:** pm-forseti delayed triggering a release close (release-b had only 1 feature) citing a "3-feature minimum target." No such rule exists in any instruction layer. This delayed the pipeline and wasted CEO triage cycles on a scope decision that should have been instant.

**Root cause:** No explicit prohibition on minimum feature counts in `pm-forseti.instructions.md`. The existing MAX cap (≤5 features per forseti release per recent rules) was interpreted by pm-forseti as implying a minimum, which it does not.

**Fix applied (CEO, commit `521fff0b0`):** `pm-forseti.instructions.md` updated with explicit prohibition: "There is no minimum feature count for a release. Ship as soon as auto-close conditions are met (≥1 feature APPROVE + Gate 2 pass). Do not hold a release open to fill scope slots. Reference: forseti-release-b shipped 1 feature."

**Acceptance criteria:** No future forseti PM-close outbox cites a feature minimum as a reason to delay close. Verification: PM outbox must not contain "minimum" language in release-close decision rationale.

### Gap 3 — Stale post-push coordinated-signoff detection — FIXED
**What happened:** After the coordinated push, a stale `coordinated-signoff-release-b` inbox item remained unarchived and was briefly re-processed as if the push hadn't happened. This is the same post-push cleanup pattern flagged in the dungeoncrawler release-b review (third-occurrence cleanup gate).

**Root cause:** No rule existed requiring pm-forseti to verify release push status before processing a coordinated-signoff inbox item.

**Fix applied (CEO, commit `521fff0b0`):** `pm-forseti.instructions.md` updated: before processing any `coordinated-signoff-*` inbox item, pm-forseti must run `scripts/release-signoff-status.sh` to confirm `ready for official push: false` (i.e., push has not already occurred). If push is already done, archive the item as stale without processing.

**Acceptance criteria:** Zero stale coordinated-signoff items re-processed after a push. Verification: `release-signoff-status.sh` must exit with `ready for official push: false` before any coordinated-signoff item is actioned.

## ROI estimate
- ROI: 12
- Rationale: All 3 gaps caused real extra QA BLOCK cycles or CEO triage overhead in release-b. The completeness-check extension (Gap 1) and the no-minimum prohibition (Gap 2) will compound across every future forseti release, preventing routine delays from recurring.

---
- Agent: agent-task-runner
- Source inbox: sessions/agent-task-runner/inbox/20260410-improvement-round-20260410-forseti-release-b
- Generated: 2026-04-10T12:56:00+00:00
