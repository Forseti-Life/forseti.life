- command: |
    Verify current state of copilot_agent_tracker module with the 22-case test suite.

    Context:
    - The EXTEND work items (CSRF, hook_uninstall, upsert dedup) are confirmed done in git:
      - CSRF: commit 74a4a6633
      - hook_uninstall: commit 2c673f559
      - upsert dedup: commit 2edeecdd0
    - Prior suite delegation (20260322-recover-suite-copilot-agent-tracker) was wiped by auto-checkpoint and never executed.
    - QA audit 20260227-213912 is PASS (0 violations).

    Task:
    1) Run the 22-case test suite for copilot_agent_tracker.
    2) Use updated AC: anonymous requests to admin routes may return 403 OR 302 (both accepted).
    3) Report APPROVE if 22/22 pass, BLOCK with specific failing cases if any fail.
    4) Include commit hash and verification evidence in outbox.

    Acceptance criteria:
    - All 22 cases pass with updated AC.
    - No regressions on public-facing routes.
    - Outbox includes APPROVE/BLOCK verdict with evidence.

    KB reference: check knowledgebase/ for any prior copilot_agent_tracker QA lessons.

    Verification method:
    - Run test suite against http://localhost (dev environment).
    - Check Apache/Drupal logs for unexpected errors.
    - Confirm anon admin route AC: 403 or 302 both accepted.
