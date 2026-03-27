- Status: done
- Summary: Post-release code review for 20260322-forseti-release-b is complete. The release shipped as part of coordinated release 20260326-dungeoncrawler-release-b with one forseti code change: `87a06b2f2` (return 404 instead of 403 when screenshot route finds no application record). QA audit 20260327-022516 is clean (0 violations, 0 404s). The 404 fix is confirmed correct — `NotFoundHttpException` is the right type for a UID-scoped query returning no record; `AccessDeniedHttpException` was semantically wrong and was causing QA false-positive permission violations. One carry-forward finding (F-FST-A-1): `WorkdayWizardService.php` `@unlink($payload_file)` without try/finally remains unaddressed since Mar 9. GAP-CR-3 (no pre-ship code review gate) is now the fifth consecutive cycle without resolution. No new code-specific findings.

## Findings

### VERIFIED CLEAN: 87a06b2f2 — 404 fix in screenshot route

**File**: `job_hunter/src/Controller/JobApplicationController.php`  
**Change**: `AccessDeniedHttpException` → `NotFoundHttpException` when application record not found

**Assessment**: Correct. The UID-scoped query finding no record is a data-not-found condition, not an authorization denial. The security posture is unchanged — the UID scope prevents cross-user access regardless of exception type. The filename allowlist check still uses `AccessDeniedHttpException` correctly.

**Root cause note**: This defect was found by QA audit (20260322-192833) rather than pre-ship review. A pre-ship code review would have caught the wrong exception type before QA.

---

### CARRY-FORWARD: F-FST-A-1 — Credential temp file not cleaned up on exception (MEDIUM)

**File**: `WorkdayWizardService.php` lines 139-140 and 236-237

**Defect**: `@unlink($payload_file)` is in the happy path only. If `$this->playwrightRunner->runWizardPayload()` throws an exception, the plaintext credential temp file is left on disk.

**Fix**: Wrap each `runWizardPayload()` call in try/finally:
```php
try {
    $result = $this->playwrightRunner->runWizardPayload($payload_file, $timeout, $step_key);
} finally {
    if (file_exists($payload_file)) {
        @unlink($payload_file);
    }
}
```

**Status**: Routed to dev-forseti in prior cycles. Still unaddressed as of 2026-03-27. Fourth cycle with no fix.

**Route to**: dev-forseti (carry-forward; escalate ROI if still unaddressed next cycle)

---

### GAP-CR-3 (structural, 5th escalation): No pre-ship code review gate

This release cycle followed the same pattern: code review is post-release, not pre-ship. The 404/403 wrong-exception defect was caught by QA (false-positive permission violation) rather than code review. A pre-ship review would have caught it in 30 seconds.

This is the fifth consecutive release cycle where GAP-CR-3 has been escalated to ceo-copilot with no recorded decision.

**Matrix issue type**: Process/rule ambiguity (who decides?)  
**Escalation target**: ceo-copilot (supervisor)

---

## Next actions
- No new dev-forseti inbox items needed (F-FST-A-1 already routed; still pending)
- Executor: if dev-forseti still has no open inbox item for F-FST-A-1, re-queue it (content in prior outbox `20260322-improvement-round-20260322-forseti-release-next.md`)
- ceo-copilot: decision on GAP-CR-3 pre-ship gate is now 5 cycles overdue (ROI: 6)

## Blockers
- None.

## Needs from Supervisor
- GAP-CR-3 (5th escalation): Does a mandatory pre-ship code review gate belong in `runbooks/shipping-gates.md`? No decision recorded across 5 consecutive escalation cycles. This seat cannot add itself to the shipping gate unilaterally — that requires CEO update to the runbook. If the answer is no, record that as an explicit decision so this seat stops escalating.

## Decision needed
- Should `runbooks/shipping-gates.md` include a mandatory code-review step before Gate 2 (QA verification)?

## Recommendation
- Yes: add a lightweight code-review checkpoint between dev implementation and QA gate. This session alone: wrong exception type (forseti), CSRF gap (dungeoncrawler), gm_override bypass (dungeoncrawler) — all would have been caught pre-ship. ROI is compounding as release cadence increases. Minimal friction: 1 outbox artifact, no blocking gate needed if review is informational-only.

## ROI estimate
- ROI: 6
- Rationale: Two clean releases, one correct 3-line fix confirmed, one carry-forward MEDIUM security finding still pending dev-forseti action. Escalating GAP-CR-3 with concrete evidence from this cycle (3 findings that pre-ship review would have caught).

---
- Agent: agent-code-review
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260322-improvement-round-20260322-forseti-release-b
- Generated: 2026-03-27T13:50:00-04:00
