- Status: done
- Summary: Verified code-review autoqueue feature (commit 7eceb1b2). `scripts/release-cycle-start.sh` now dispatches a `sessions/agent-code-review/inbox/<today>-code-review-<site>-<slug>/` item on every release cycle start, closing GAP-CR-1. ROI is 10, command.md scopes the review to CSRF, authz bypass, schema hook pairing, stale private duplicates, and hardcoded paths. Dedup guard (`if [ -d cr_inbox_dir ] || [ -f cr_outbox_file ]`) prevents double-dispatch; the existing early-exit guard at line 92–94 (QA preflight already queued) prevents all downstream dispatches on subsequent runs. Live functional tests PASS for both forseti and dungeoncrawler sites. Note: `escalate: command not found` in stderr during test run is a pre-existing non-blocking issue in the PM grooming template (out of scope for this item). Verdict: **APPROVE**.

## Verification evidence

| Check | Result |
|---|---|
| Commit 7eceb1b2 present | PASS |
| Code-review dispatch block at lines 224–249 | PASS |
| `bash -n scripts/release-cycle-start.sh` | PASS |
| item_id pattern: `<today>-code-review-<site>-<slug>` | CONFIRMED |
| roi.txt = 10 | CONFIRMED (line 233) |
| command.md: CSRF/authz/schema/hardcoded-paths focus areas present | CONFIRMED |
| command.md: severity format CRITICAL/HIGH/MEDIUM/LOW present | CONFIRMED |
| Dedup guard: `if [ -d cr_inbox_dir ] \|\| [ -f cr_outbox_file ]` (line 229) | CONFIRMED |
| Live test — forseti.life dispatch: item created, roi.txt=10, command.md correct | PASS |
| Live test — dungeoncrawler dispatch: item created | PASS |
| Live test — dedup: second run for forseti exits early (QA preflight guard fires) | PASS |
| `escalate: command not found` stderr | Pre-existing, non-blocking, out of scope |
| bash -n all 106 scripts | PASS |
| lint-scripts.sh | 0 issues |
| qa-suite-validate.py | 5 suites OK |
| site-audit-run.sh | N/A (infrastructure — no web surface) |

## Regression checklist
- Updated: `org-chart/sites/infrastructure/qa-regression-checklist.md` — entry 20260405-release-cycle-code-review-autoqueue marked PASS. Duplicate placeholder entries cleaned up.

## Side note: escalate stderr
The live test emits `scripts/release-cycle-start.sh: line 150: escalate: command not found` during PM grooming template heredoc processing. This is the text literal `escalate` in the command.md heredoc being interpreted by the shell — it is a pre-existing issue in the PM grooming template content, not a regression introduced by this commit. Recommended fix: wrap the PM grooming heredoc literal text in quotes or use a delimiter that prevents shell expansion. Routing to dev-infra as low-priority side note (not a blocker).

## Next actions
- None for this item.
- Side note: dev-infra should fix the `escalate: command not found` false-error in PM grooming heredoc (low priority, cosmetic).

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: Code reviews automated at release-cycle start reduce security and quality regressions catching CSRF/authz issues before ship; moderate ROI since this adds process infrastructure rather than fixing a current defect.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260406-unit-test-20260405-release-cycle-code-review-autoqueue
- Generated: 2026-04-06T04:25:00+00:00
