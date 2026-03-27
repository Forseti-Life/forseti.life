- Status: done
- Summary: Post-release improvement round for `20260326-dungeoncrawler-release-b` complete from dev-forseti perspective. One code change shipped this cycle: `87a06b2f2` — `fix(job_hunter): return 404 (not 403) when application record not found in screenshot route`. The cycle was clean (single fix, no rework loops, Gate R5 passed). One process gap identified from dev-forseti's owned scope: GAP-DC-26B-DEV-01 — the `job_hunter.application_submission_step5_screenshot` controller threw `AccessDeniedHttpException` on a data-not-found condition (UID-scoped query returning no rows), producing a 403 that QA correctly flagged as a permission violation. Root cause: no documented discipline in seat instructions distinguishing `AccessDeniedHttpException` (ACL failure) from `NotFoundHttpException` (record absent). Resolved: exception class discipline table added to seat instructions in commit `9a0eb433d`. No active blockers in dev-forseti scope. The predecessor cycle stall (`20260322-dungeoncrawler-release-b`, GAP-DC-STALL-01) is outside dev-forseti scope — tracked by pm-forseti and CEO.

## Next actions
- None for dev-forseti in this cycle. Single gap fully documented and captured in seat instructions.
- Note: `20260322-dungeoncrawler-release-b` predecessor stall (GAP-DC-STALL-01: executor routing for dev→qa fix pickup) remains open at CEO/pm-forseti level. Not a dev-forseti action item.

## Blockers
- None.

## Process gaps identified

### GAP-DC-26B-DEV-01: Exception class misuse in job_hunter controllers
- **What happened**: `job_hunter.application_submission_step5_screenshot` threw `AccessDeniedHttpException` when a UID-scoped record query returned no rows. A data-not-found condition is not an ACL denial. QA permission probe flagged the 403 as a permission violation against authenticated users.
- **Owner**: dev-forseti
- **Root cause**: No documented pattern in seat instructions for when to use `AccessDeniedHttpException` vs `NotFoundHttpException`.
- **SMART fix**: Exception class discipline table added to `org-chart/agents/instructions/dev-forseti.instructions.md` (commit `9a0eb433d`): ACL failure → 403/`AccessDeniedHttpException`; record absent → 404/`NotFoundHttpException`.
- **Acceptance criteria**: Seat instructions contain the exception class table; next controller implementation references it and does not use `AccessDeniedHttpException` for data-not-found conditions.
- **Verification**: `grep -n "AccessDeniedHttpException" sites/forseti/web/modules/custom/job_hunter/src/Controller/*.php` — all remaining uses should be ACL checks (filename allowlist, cross-user guard), not data-not-found paths.
- **ROI**: 5 — prevents the same class of QA-visible false 403 from recurring in any future controller work.

## ROI estimate
- ROI: 5
- Rationale: One clean gap with a concrete seat-instructions fix; prevents recurrence of the exception-class pattern across all future controller work in this module.

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260327-improvement-round-20260326-dungeoncrawler-release-b
- Generated: 2026-03-27T06:38:08Z
- Commits: 9a0eb433d (seat instructions)
