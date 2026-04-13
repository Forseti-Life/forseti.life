# Architect Session State — architect-copilot

> **Rolling file. Overwrite this at the end of each working session (and briefly before starting each task).**
> Last updated: 2026-04-13 after Job Hunter CIO LangGraph flow monitoring slice

---

## Currently Working On

Completed the first monitorable LangGraph slice for the Job Hunter CIO pipeline:
- `copilot_agent_tracker` LangGraph Process Flow now exposes a live
  **Job-Hunter CIO Application Flow** section backed by production Job Hunter
  tables,
- the workflow registry now lists that CIO flow as `in_progress`,
- and the dashboard shows exactly where the live pipeline is moving versus
  stalled (discovery/import/queue/app-prep live; tailoring/submission blocked).

---

## Active Releases

| Site | Release ID | Status | Notes |
|---|---|---|---|
| dungeoncrawler | `20260412-dungeoncrawler-release-i` | In progress | 14 features in dev/qa pipeline; 23 ready for activation |
| forseti | `20260412-forseti-release-h` | In progress | Job Hunter release-h has 4 in_progress features; LangGraph/AI/Safety next slices are groomed but not yet activated |

---

## What Was Last Worked On

**2026-04-13 — Job Hunter CIO LangGraph flow monitoring**

- Added a live Job Hunter CIO workflow snapshot to
  `copilot_agent_tracker`'s LangGraph Process Flow page instead of creating a
  separate admin report.
- Registered a new site-level workflow entry:
  `Job-Hunter CIO Application Flow` with an in-progress status in the LangGraph
  workflow registry.
- Wired the process-flow page to read live Forseti Job Hunter state for Keith
  Aumiller (`keith.aumiller@stlouisintegration.com`) and expose stage counts for:
  - discovered CIO opportunities in staging
  - canonical imported CIO jobs
  - saved jobs bound to Keith
  - application records and apply-URL readiness
  - tailored resume / PDF readiness
  - confirmed submissions
- Added a recent canonical CIO jobs table so operators can see the latest live
  jobs, submission posture, manual-review flags, and apply targets directly from
  the LangGraph page.
- Confirmed the live bottleneck is now explicit in the dashboard:
  - 162 CIO matches discovered
  - 6 canonical CIO jobs imported
  - 6 saved jobs / 6 application records created
  - 0 tailored CIO resumes and 0 confirmed submissions
- Captured the still-open import drift directly in the dashboard warnings:
  `jobhunter_job_requirements` still exposes `source`, while
  `SearchAggregatorService` import logic expects `external_source`.

**2026-04-13 — Job Hunter contact tracker realignment**

- Realigned the contact tracker schema with a forward-only `job_hunter_update_9059`
  migration that adds `name`, `title`, and `company_id` fields while preserving
  legacy `full_name`, `job_title`, and `company_name` compatibility.
- Replaced the collation-sensitive SQL backfill with a Drupal DB row pass so the
  live update applies safely on production MySQL.
- Updated the contact list and add/edit form to use company-backed contacts,
  current referral statuses (`none/requested/pending/provided`), and a real
  POST delete action.
- Updated contact save logic to write both the current fields and legacy
  compatibility fields when present so the code stays safe before and after the
  update hook runs.
- Updated saved-job contact surfacing to match linked contacts by `company_id`
  first, then fall back to legacy `company_name` only when needed.
- Added focused static contract coverage for the contact tracker routes,
  controller expectations, and the new schema realignment hook.
- Applied the live Drupal update and confirmed `jobhunter_contacts` now exposes:
  `name`, `title`, `company_id` with no unresolved contact rows on this host.

**2026-04-13 — Job Hunter preferences/search wiring**

- Fixed the gap where saved `jobhunter_source_preferences` rows were not being
  applied to live job searches.
- Added search-parameter normalization in `SearchAggregatorService` so saved
  sources, minimum salary, and remote preference apply only when the current
  request does not explicitly override them.
- Added a hidden `sources_submitted` marker on the job discovery form so an
  intentional "no sources checked" submit is not mistaken for "use saved
  defaults."
- Updated discovery-page defaults so source checkboxes reflect saved
  preferences instead of always defaulting to the old hardcoded selections.
- Added canonical routes:
  - `/jobhunter/preferences`
  - `/jobhunter/preferences/save`
  while keeping the legacy `/jobhunter/preferences/sources` routes for
  compatibility.
- Replaced stale source-preference keys (`linkedin`, `indeed`, `glassdoor`,
  `ziprecruiter`) with the live search-source keys (`forseti`, `serpapi`,
  `adzuna`, `usajobs`) in the preferences UI.
- Added focused unit coverage for saved-preference normalization and rebuilt the
  live Drupal cache from `/var/www/html/forseti`.

**2026-04-12 — HQ active-agent capacity alignment**

- Audited live host headroom for the previous 24 hours using current process state,
  tick logs, and live VM activity sampling.
- Confirmed the scheduler had already been running at `--agent-cap 6` via the
  orchestrator reboot cron, while the executor-side global concurrency guard still
  defaulted to `5`.
- Observed that:
  - CPU pressure remained low relative to the 8-core host
  - available memory remained high despite ~3.3 GiB of swap in use
  - live `vmstat` sampling showed no active swap-in / swap-out churn during the
    check
  - tick logs over the last 24 hours already included multiple full `6`-agent
    selections
- Raised the durable default active-agent cap to `6` end-to-end by aligning:
  - `scripts/orchestrator-loop.sh`
  - `scripts/agent-exec-next.sh`
  - `scripts/install-crons.sh`
  - `scripts/systemd/copilot-sessions-hq-orchestrator.service`
- Restarted the live orchestrator loop and confirmed it resumed on
  `orchestrator/run.py --once --agent-cap 6`.
- Decision: keep the cap at `6` for now; do **not** raise beyond that until we
  have stronger 24-hour historical memory / IO telemetry, because swap occupancy
  is already high even though active churn was not observed.

**2026-04-12 — Forseti roadmap subpages**

- Added a synced detail route at `/roadmap/{project_id}` for HQ registry-backed
  Forseti projects.
- Added a dedicated template for individual project roadmap pages.
- Updated the main roadmap page cards so Forseti projects now link to their own
  subpages:
  - `/roadmap/PROJ-001`
  - `/roadmap/PROJ-002`
- Kept Dungeoncrawler as an external roadmap link to
  `https://dungeoncrawler.forseti.life/roadmap`.
- Rebuilt live Drupal caches from `/var/www/html/forseti` and confirmed the
  index page plus both new project pages render in production.

**2026-04-12 — Forseti project roadmap page**

- Added a new public route at `/roadmap` via `forseti_content`.
- Added a main-menu Roadmap link under `How It Works`.
- Added a themed Forseti roadmap page template and controller method using the
  existing `forseti_content` page pattern.
- Published roadmap sections for:
  - Job Hunter
  - AI Conversation
  - Community Safety
  - Dungeoncrawler (external roadmap link only)
- Rebuilt live Drupal caches from `/var/www/html/forseti` and confirmed the page
  renders in production with the expected project cards and Dungeoncrawler link.

**2026-04-12 — Job Hunter release-artifact backfill**

- Backfilled missing release-gate docs for current Job Hunter `ready` features:
  - `application-analytics`
  - `contact-referral-tracker`
  - `follow-up-reminders`
  - `interview-outcome-tracker`
  - `offer-tracker`
  - `resume-version-tracker`
- Added `02-implementation-notes.md` for current `in_progress` Job Hunter
  features:
  - `company-interest-tracker`
  - `company-research-tracker`
  - `contact-tracker`
  - `job-board-preferences`
  - `resume-version-labeling`
- Verified the active Job Hunter queue now has `01-acceptance-criteria.md`,
  `02-implementation-notes.md`, and `03-test-plan.md` present for the audited
  feature set.
- Preserved the explicit note that
  `forseti-jobhunter-interview-outcome-tracker` appears live already and likely
  needs PM metadata reconciliation instead of duplicate dev work.

**2026-04-12 — Job Hunter flow + release pipeline audit**

- Loaded the architect instruction stack and current architect session state.
- Confirmed the latest completed architect work was the live Job Hunter CIO
  application sprint.
- Flagged interrupted improvement-round artifacts as known architect-scope
  misroutes rather than active implementation work.
- Mapped Job Hunter product state from module docs:
  - Step 1 (resume upload/profile cleanup) is implemented.
  - Step 2 (target companies) is partial.
  - Steps 3-6 (AI discovery, submission, interview/follow-up, analytics) are
    still documented as planned.
- Confirmed `PROCESS_FLOW.md` still marks company management, error queue
  management, user profile review, job discovery/scraping, and async processing
  as planned/future work.
- Audited current Job Hunter feature briefs for release readiness:
  - `ready` features missing full release artifacts:
    `application-analytics`, `contact-referral-tracker`,
    `follow-up-reminders`, `interview-outcome-tracker`, `offer-tracker`,
    `resume-version-tracker`
  - `in_progress` features missing implementation notes:
    `company-interest-tracker`, `company-research-tracker`,
    `contact-tracker`, `job-board-preferences`,
    `resume-version-labeling`
- Found backlog metadata drift: `forseti-jobhunter-interview-outcome-tracker`
  remains `ready` in HQ even though the architect session already records the
  feature as implemented live on 2026-04-12.
- Produced an implementation sequence for the Forseti PM/Dev release process:
  finish Step-2 company/research/preferences work, then Step-5 interview /
  follow-up / offer tracking, then Step-6 analytics.

**2026-04-12 — Job Hunter CIO application sprint**

- Audited the live Job Hunter state for user `1` and confirmed three stored resume
  variants, with resume id `6` (`KeithAumillerP`) as the best executive/CIO fit.
- Fixed a live controller bug caused by incorrect plural table references
  (`jobhunter_job_seekers` -> `jobhunter_job_seeker`) that blocked resume-related
  flows.
- Repaired `UserProfileService` completeness evaluation so live profile readiness is
  computed from the real schema plus consolidated profile JSON fallbacks. Result:
  completeness moved from `7` to `95`, clearing the application gate.
- Created or confirmed saved-job coverage and queued CIO-track applications for:
  - job `14` — `Deputy CIO-Chief Enterprise Officer` (City of Philadelphia)
  - job `15` — `Chief Information Officer` (Discovery Life Sciences)
  - job `16` — `Chief Information Officer` (H&H)
- Bound resume id `6` to all queued application rows.
- Resolver outcome snapshot:
  - job `14`: no direct apply URL resolved; application recorded as pending with
    fallback metadata
  - job `15`: resolved to Teal aggregator URL with low-confidence routing
  - job `16`: resolved to direct Workable URL with high-confidence routing

**2026-04-12 — Job Hunter interview outcome tracker**

- Added `jobhunter_interview_rounds` table support to the Job Hunter module for
  per-user, per-saved-job interview round outcomes.
- Added the authenticated POST route
  `/jobhunter/interview-rounds/{job_id}/save` with CSRF enforcement.
- Extended the saved-job detail page to render an interview outcome tracker with
  add/edit form, chronological round log, outcome badges, and AJAX updates.
- Added focused static route-contract coverage and repaired the existing CSRF
  seed test paths so the unit checks run against the actual module layout.
- Applied Drupal update `job_hunter_update_9053` on the live Forseti site and
  rebuilt caches. The route is active and the new table is present.

**2026-04-12 — Forseti stale schema cleanup**

- Verified the lingering update notices were stale rows in
  `key_value.collection = system.schema`, not active module state.
- Confirmed `backup_migrate`, `google_tag`, `social_api`, `social_auth`,
  `social_auth_google`, `twig_tweak`, `webform`, and `webform_ui` are not
  installed in `core.extension`.
- Deleted only those eight stale rows from the live Forseti database.
- Final state: `cd /var/www/html/forseti && vendor/bin/drush updatedb:status`
  returns clean with no module-not-installed notices.

**2026-04-11 — Drupal site maintenance / security notifications**

- Checked both live Drupal roots: `/var/www/html/forseti` and
  `/var/www/html/dungeoncrawler`.
- Confirmed both sites bootstrap successfully and are on **Drupal 11.3.6**.
- Ran Composer security audits on both live sites: **no security vulnerability
  advisories found**.
- Updated pending patch/minor dependencies in both the tracked source tree under
  `sites/` and the matching live Composer installs under `/var/www/html/`.
- Ran live `drush updatedb` and `drush cache:rebuild` after updates.
- Final state: `composer outdated --direct --minor-only` and `--patch-only`
  return clean on both sites.

---

## Open Threads / Pending Decisions

No blocking bug remains for the new CIO monitoring slice itself.

Current Job Hunter open threads are now:
- fix the `source` vs `external_source` import drift so more staged CIO
  opportunities can become canonical job requirements,
- generate tailored CIO resume artifacts / PDFs so the monitored flow can move
  from application-prep into submission,
- then decide whether to add a second automation slice that advances
  manual-review applications toward confirmed submission.

Release-process follow-through is still a main parallel thread:
- PM should correct stale feature status for
  `forseti-jobhunter-interview-outcome-tracker`
- PM can now use the backfilled release-gate artifacts for current `ready` /
  `in_progress` Job Hunter features
- Dev should sequence remaining work as: Step 2 company/research/preferences →
  Step 5 tracking → Step 6 analytics
- Forseti public project navigation now includes a synced roadmap landing page
  plus project detail subpages

Operational follow-through is still useful:
- watch downstream submission processing for applications `1`, `2`, and `3`
- consider importing additional staged CIO roles if the user wants a broader batch
- monitor HQ host swap / IO behavior before considering any agent cap above `6`

---

## Key Decisions Made (recent sessions)

- Treat `/var/www/html/<site>` as the authoritative live Composer install for
  validation and remediation, while also updating the matching tracked source
  tree under `/home/ubuntu/forseti.life/sites/<site>` so future deployments do
  not drift.
- For operational Drupal verification on this host, use the live site roots
  under `/var/www/html/<site>`; the tracked source tree under `/home/ubuntu`
  is not the reliable Drush bootstrap path for production checks.
- The Job Hunter module's controller-focused unit tests should resolve module
  paths relative to `tests/src/Unit/Controller/` using four `..` segments, not
  five; otherwise the tests silently point outside the module root.
- Leave dev-only major upgrades (`phpunit/phpunit`, `drupal/coder`) untouched
  during this pass because the task was to clear live security/update
  notifications, not to take risky major-version jumps in tooling.

---

## Next Priority Actions (pick up here next session)

1. If the user wants release-pipeline execution, hand PM the artifact-backed
   queue from this audit and start with the now-backfilled Job Hunter feature
   docs plus the single stale-status correction.
2. If the user wants roadmap refinement, expand the synced project subpages with
   more detailed HQ section parsing or richer milestone presentation.
3. If the user wants direct implementation, finish the active Step-2 company /
   research / contact / preferences work before starting more reporting views.
4. After Step-2 tracking data is stable, build `forseti-jobhunter-offer-tracker`
   and `forseti-jobhunter-follow-up-reminders`, then land
   `forseti-jobhunter-application-analytics`.
5. If HQ throughput is still constrained, add real 24-hour host telemetry capture
   before considering any active-agent cap above `6`.
5. If the user wants more live Job Hunter ops, import and queue additional staged
   CIO roles already cached in `jobhunter_job_search_results`.

---

## No Pipeline Health Snapshot (architect does not run hq-status.sh)
