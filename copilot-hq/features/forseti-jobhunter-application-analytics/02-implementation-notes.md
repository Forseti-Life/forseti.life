# Implementation Notes: forseti-jobhunter-application-analytics

- Feature: forseti-jobhunter-application-analytics
- Author: ba-forseti
- Date: 2026-04-12
- Status: draft for dev-forseti

## Approach

Build a read-only analytics page on top of existing Job Hunter status data
instead of adding new persistence. Prefer a small controller/service pair:

1. Add route `/jobhunter/analytics` requiring authenticated access.
2. Create an analytics query helper/service that aggregates:
   - total saved jobs / applications
   - funnel counts by status from `jobhunter_saved_jobs`
   - response-rate by source from `jobhunter_saved_jobs`
   - optional interview-stage enrichment from `jobhunter_interview_rounds`
   - weekly activity buckets from application or saved-job timestamps
3. Render a Twig template with simple markup sections:
   - `analytics-summary`
   - `analytics-funnel`
   - `source-breakdown`
   - `weekly-activity`
   - `analytics-empty-state`

## Data model notes

- Primary source should be `jobhunter_saved_jobs` because status and source
  already live there.
- `jobhunter_applications` can provide application dates / submission counts if
  the saved-jobs table is missing an equivalent date.
- `jobhunter_interview_rounds` is optional enrichment only. If the table does
  not exist yet, the dashboard should still render using saved-job statuses.
- No new tables are required for this feature.

## Funnel mapping

Dev should define a deterministic mapping from saved-job status values to funnel
buckets. Use the existing status vocabulary in the module first; only normalize
for display labels in the controller/view layer.

Recommended buckets:
- applied
- phone screen
- technical
- offer
- accepted
- rejected

If multiple raw statuses map to one display bucket, keep the mapping in one
helper method so QA can verify it.

## UI / rendering notes

- Keep the page server-rendered; no JavaScript dependency is required.
- Empty state should appear when the user has no saved jobs.
- Weekly activity can be a simple list or bar-style markup; no chart library is
  required for v1.
- Do not surface company names or freeform notes in the analytics widgets.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/analytics
drush sql:query "SELECT status, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY status"
drush sql:query "SELECT source, COUNT(*) FROM jobhunter_saved_jobs WHERE uid=<uid> GROUP BY source"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.

## Risks / notes

- Avoid hard-coding status strings in multiple places; centralize the display
  mapping.
- Prefer graceful degradation if `jobhunter_interview_rounds` is absent or empty.
- This feature depends on Step-5 tracking data being stable, but it can ship
  with partial data as long as the page is accurate about what it is counting.
