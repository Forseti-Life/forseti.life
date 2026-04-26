#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT_DIR"

release_id="${1:-}"
pm_agent="${2:-pm-jobhunter}"

if [ -z "$release_id" ]; then
  echo "Usage: $0 <release-id> [pm-agent]" >&2
  exit 2
fi

inbox_dir="sessions/${pm_agent}/inbox"
mkdir -p "$inbox_dir"

item_id="$(date -u +%Y%m%d-%H%M%S)-scope-activate-${release_id}"
item_dir="${inbox_dir}/${item_id}"
mkdir -p "$item_dir"

cat >"${item_dir}/README.md" <<MD
# Scope Activate: ${release_id}

- Agent: ${pm_agent}
- Status: pending
- Release: ${release_id}
- Date: $(date -u +%Y-%m-%d)
- Dispatched by: jobhunter-empty-release-directive.sh (empty release submission directive)

## Primary KPI

- **submission_count**
- Source of truth: count of rows in \`jobhunter_applications\` for Keith Aumiller where \`submission_status = 'submitted'\`

## Task

Release \`${release_id}\` is an empty JobHunter release.
Do **not** leave this cycle as generic idle time. Convert it into one real submission outcome.

## Objective

Complete **one full JobHunter submission workflow** for **Keith Aumiller** on **one CIO-role job that has never been submitted before**.

This means:
1. find one matching job,
2. tailor the resume,
3. execute the full submission path,
4. finish with a real submitted application when possible.

## Workflow

1. Establish the KPI baseline:

\`\`\`sql
SELECT COUNT(*)
FROM jobhunter_applications
WHERE uid = <keith_uid>
  AND submission_status = 'submitted';
\`\`\`

2. Find one candidate job that matches **CIO** / **Chief Information Officer** and has **never been submitted before** for Keith:

\`\`\`sql
SELECT jr.id, jr.job_title
FROM jobhunter_job_requirements jr
LEFT JOIN jobhunter_applications ja
  ON ja.job_id = jr.id
 AND ja.uid = <keith_uid>
 AND ja.submission_status = 'submitted'
WHERE ja.id IS NULL
  AND (
    LOWER(jr.job_title) LIKE '%cio%'
    OR LOWER(jr.job_title) LIKE '%chief information officer%'
  )
ORDER BY jr.id DESC
LIMIT 1;
\`\`\`

3. Run the full workflow for that job:
   - save/select the job
   - generate or verify the tailored resume
   - enter the application-submission flow
   - submit through the ATS/manual flow to completion

4. If any part of the workflow is blocked:
   - existing behavior broken -> create a **defect fix**
   - missing capability/process step -> create an **enhancement request**
   - continue driving the submission path until the real submission can complete

## Guardrails

- Do **not** fill the empty release with unrelated backlog just to create motion.
- Do **not** swap the KPI to “attempts”; top-line success is **submitted applications**.
- Use existing JobHunter submission surfaces as the canonical path, not ad hoc one-off steps.

## References

- \`features/forseti-jobhunter-application-submission/feature.md\`
- \`qa-suites/products/forseti/suite.json\`
- \`sites/forseti/web/modules/custom/job_hunter/docs/STEP3_APPLICATION_SUBMISSION_DESIGN.md\`

## Done when

1. \`submission_count\` increases by **1** for Keith Aumiller, and
2. the submission is for a CIO-role job that had never previously been submitted, and
3. any workflow blockers discovered along the way are captured as enhancement/defect work and routed back into JobHunter execution.
MD

printf '2500\n' >"${item_dir}/roi.txt"
printf '%s\n' "${item_dir}"
