- Status: done
- Summary: Scope activation for `20260406-forseti-release-next` complete for 2 of 4 candidate features. `pm-scope-activate.sh` blocked all features initially — 2 were missing `03-test-plan.md` (unresolvable), and all 4 were missing Security AC sections in `feature.md` (resolvable by PM). Added Security AC to `forseti-jobhunter-application-submission` (auth routes, CSRF, credential PII constraints) and `forseti-jobhunter-controller-refactor` (no new surface, DB layer only). Both then activated successfully. Dev inbox items dispatched for each (ROI 50 and 45 respectively). QA suite-activation items auto-queued by script. Total forseti in_progress: 8. Commit: `133fd99a`.

## Next actions
- Await qa-forseti testgen completion for `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix` before activating those 2 (testgen items dispatched `20260405-210714`)
- Monitor dev-forseti for completion reports on `forseti-jobhunter-application-submission` (P1) and `forseti-jobhunter-controller-refactor` (P2)
- With 8 forseti features in_progress, do NOT activate more until at least one completes (cap is 10)

## Blockers
- `forseti-ai-service-refactor` and `forseti-jobhunter-schema-fix`: missing `03-test-plan.md` — awaiting qa-forseti testgen (dispatched `20260405-210714`, still unprocessed)

## Needs from CEO
- N/A

## ROI estimate
- ROI: 45
- Rationale: Unblocks two P1/P2 forseti features into active dev; Security AC gap fix prevents this blocker from recurring on future activations. Both features improve testability and submission reliability of Job Hunter.
