# Architect Session State — architect-copilot

> **Rolling file. Overwrite this at the end of each working session (and briefly before starting each task).**
> Last updated: 2026-04-13 during Financial Health next-phase architecture

---

## Currently Working On

Completed the next execution slice for the CEO-managed integration audit
project:
- added the integrations hub at `dashboards/integrations/README.md`
- added the first machine-readable registry at
  `dashboards/integrations/integration-registry.yaml`
- advanced `PROJ-010` from planning into runtime-audit-ready state

### Current state

- `dashboards/PROJECTS.md` now tracks `PROJ-010` under CEO ownership and points
  to the runtime truth audit as the next step.
- The inventory covers the verified external integration surfaces currently
  visible from repo evidence.
- The work now has the actual Phase 1 operator artifacts in place.

### Key decisions

1. Keep the project CEO-managed because it crosses products and storage planes.
2. Centralize the operator experience before trying to centralize every storage
   mechanism.
3. Treat runtime-secret location as the next truth-establishing pass.

### Next actions

1. Run the runtime truth audit
2. Prioritize remediation, starting with the tracked SerpAPI secret
3. Add per-integration runbooks once runtime truth is confirmed

---

## Active Releases

| Site | Release ID | Status | Notes |
|---|---|---|---|
| dungeoncrawler | `20260412-dungeoncrawler-release-i` | In progress | 14 features in dev/qa pipeline; 23 ready for activation |
| forseti | `20260412-forseti-release-h` | In progress | Job Hunter release-h has 4 in_progress features; LangGraph/AI/Safety next slices are groomed but not yet activated |

---

## What Was Last Worked On

**2026-04-13 — Job Hunter CIO backlog expansion**

- Imported six additional curated CIO-track staging rows into canonical job
  requirements:
  - `20` Solar Mason
  - `21` Resideo
  - `22` Wind Creek Hospitality
  - `23` GM Financial
  - `24` City of New York
  - `25` Ferrilli
- Bound those six jobs to Keith via `jobhunter_saved_jobs`.
- Discovered and persisted safe Step-2 application-path data for those six jobs
  without submitting anything externally:
  - created `not_started` application rows
  - resolved apply URLs
  - classified ATS / methodology types
- Fixed the coupled import bug where unknown companies were defaulting to
  `company_id = 1` (`Lincoln Investment`) instead of creating/finding the
  correct company record.
- Reconciled company mappings for imported CIO jobs `15-25` from the staging
  source-of-truth so the canonical rows now reference the right employers.
- Expanded methodology mix across the staged+ready CIO queue (`14-25`):
  - `custom` = 5
  - `aggregator` = 5
  - `workable` = 1
  - `unknown` = 1
- Updated live state after expansion:
  - 12 canonical CIO jobs
  - 12 saved jobs
  - 12 application records
  - 6 tailored CIO resumes / PDFs ready for authorization
  - 6 newly imported CIO jobs held in `not_started` pre-submit posture
  - 0 confirmed submissions

**2026-04-13 — Job Hunter CIO queue advancement**

- Fixed `SearchAggregatorService` import compatibility so live hosts that still
  expose `jobhunter_job_requirements.source` can import staged jobs without
  requiring `external_source` to exist first.
- Added focused unit coverage for that source-field fallback in
  `SearchAggregatorServiceTest`.
- Queued and processed tailored-resume generation for the active CIO batch:
  job ids `14`, `15`, `16`, `17`, `18`, `19`.
- Generated tailored resume PDFs for all six current CIO applications so they
  now sit in a true pre-submit / approval-ready state.
- Current vetted methodology mix in the queued batch:
  - `custom` = 3
  - `workable` = 1
  - `aggregator` = 1
  - `unknown` = 1
- Current live state after advancement:
  - 6 canonical CIO jobs
  - 6 saved jobs
  - 6 application records
  - 6 tailored CIO resumes
  - 6 CIO PDFs generated
  - 0 confirmed submissions (intentionally held pending Board authorization)

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
- Confirmed the live bottleneck was explicit in the dashboard before queue
  advancement:
  - 162 CIO matches discovered
  - 6 canonical CIO jobs imported
  - 6 saved jobs / 6 application records created
  - 0 tailored CIO resumes and 0 confirmed submissions

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
- import more of the 154 still-staged CIO matches into canonical job
  requirements,
- keep discovering/vetting submission methodologies as new CIO jobs are pulled
  in,
- then add the authorization gate / operator workflow that advances the current
  ready batch from PDF-ready into confirmed submission only when Board approval
  is explicit.

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
**2026-04-13 — Job Hunter Workable dry-run validation**

- Replaced `job_hunter/playwright/platforms/workable.js` with a real handler
  instead of the old `phase2_pending` stub.
- The new handler now:
  - dismisses the Workable cookie banner,
  - opens the live application form,
  - fills Keith's core contact/profile fields,
  - uploads the tailored resume PDF,
  - answers required salary and sponsorship questions,
  - optionally seeds the first education and experience entries from the
    consolidated profile,
  - and honors `dry_run` so the final submit click can be withheld safely.
- Validated the handler against the live H&H CIO Workable posting for
  canonical job `16` using Keith's prepared application payload.
- Result:
  - dry run completed successfully in ~42s,
  - pre-submit screenshot captured at `/tmp/3_pre.png`,
  - application `id=3` now has persisted `step5_cache` proof showing the
    Workable flow was vetted through the final-submit boundary without
    actually submitting.
- Practical effect:
  - CIO job `16` is now one explicit Board authorization away from final
    submission on its direct ATS path.

**2026-04-13 — CIO apply-path normalization for SmartRecruiters**

- Resolved CIO job `14` (City of Philadelphia, Deputy CIO-Chief Enterprise
  Officer) from an unresolved / blank apply path to the live SmartRecruiters
  posting:
  - `https://jobs.smartrecruiters.com/CityofPhiladelphia/744000107839863-deputy-cio-chief-enterprise-officer`
- Updated application `id=1` so its `ats_platform` is now
  `smartrecruiters` and its metadata records the direct ATS resolution steps.
- Practical effect:
  - the ready batch no longer has an `unknown` methodology placeholder for job
    `14`,
  - and future automation work can target SmartRecruiters directly instead of
    restarting discovery from scratch.

**2026-04-13 — Ready-batch ATS vetting closure**

- Replaced `job_hunter/playwright/platforms/smartrecruiters.js` with a real
  availability-check handler that:
  - loads direct SmartRecruiters ATS pages,
  - detects expired postings explicitly,
  - and returns precise manual reasons (`job_expired`,
    `apply_form_unavailable`) instead of a generic stub response.
- Updated `ApplicationSubmitterQueueWorker` so those expired/unavailable direct
  ATS outcomes are treated as routine manual cases rather than escalated
  failures.
- Validated CIO job `14` (City of Philadelphia) against the live direct ATS
  page:
  - result = `job_expired`
  - screenshot = `/tmp/1_pre.png`
  - persisted into `metadata.step5_cache`
- Persisted additional ready-batch path-vetting evidence:
  - job `15` (Discovery Life Sciences): Teal page now recorded as `410 gone`
  - job `17` (Lincoln Investment): Career.com page now recorded as `403 blocked`
  - job `18` (Trinseo): WhatJobs page now recorded as generic search results,
    not a direct job application page
  - job `19` (UnitedHealth Group): JobzMall page now recorded as an aggregator
    listing with no direct ATS found
- Practical effect:
  - ready batch status is now explicit rather than ambiguous:
    - `16` = final-click-only on Workable
    - `14` = direct ATS expired
    - `15/17/18/19` = stale or indirect aggregator paths needing new direct
      employer discoveries before they can advance further
