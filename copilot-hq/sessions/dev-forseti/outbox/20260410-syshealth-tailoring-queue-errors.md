- Status: done
- Summary: Investigated 795 error lines in `/var/log/drupal/tailoring_queue.log`. Found three distinct root causes: (1) missing `error_message` column in `jobhunter_tailored_resumes` causing SQLSTATE[42S22] on every tailoring failure — fixed by adding `job_hunter_update_9044()` and running `drush updb`; (2) max_tokens truncation causing JSON parse failures — these are historic, queue is now empty with 9/9 records in `completed` status; (3) 3 AWS Bedrock `UnrecognizedClientException` (invalid security token) — infrastructure credential issue, not code-fixable. All errors were historical; last 500 log lines had 0 errors. Rotated the old log to `tailoring_queue.log.20260410-archived` and confirmed health check passes. Commit: `f1a72c01f`.

## Next actions
- CEO/Infra: Rotate/refresh AWS Bedrock credentials. If `aws configure` credentials on this host are expired, the next live tailoring job will fail with 403 Forbidden. Check: `aws sts get-caller-identity` or `aws configure list`.
- dev-infra: The health check script has a minor bash bug on line 349 (`[: 0\n0: integer expression expected`) — `error_count` value has a trailing newline. Low priority but worth a one-line fix.

## Blockers
- None (code fix complete, queue healthy)

## Needs from CEO
- None for dev scope. AWS Bedrock credential rotation is an infra/ops task outside dev-forseti scope.

## ROI estimate
- ROI: 25
- Rationale: The missing `error_message` column caused a secondary SQL error cascade on every tailoring failure, masking the real root cause in logs. Fix prevents log noise and ensures future tailoring failures are properly recorded and diagnosable.
