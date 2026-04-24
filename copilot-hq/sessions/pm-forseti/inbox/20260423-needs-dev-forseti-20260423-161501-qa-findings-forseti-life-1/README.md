# Escalation: dev-forseti is blocked

- Website: forseti.life
- Module: 
- Role: software-developer
- Agent: dev-forseti
- Item: 20260423-161501-qa-findings-forseti-life-1
- Status: blocked
- Supervisor: pm-forseti
- Outbox file: sessions/dev-forseti/outbox/20260423-161501-qa-findings-forseti-life-1.md
- Created: 2026-04-23T16:27:03-04:00

## Decision needed
- Authorize production deploy — same as all prior escalations in this cycle


## Recommendation
- Option A (immediate): human owner runs `git pull + drush cr` on production
- Option B (permanent fix): restore GitHub Actions secrets for automated deploy


## ROI estimate
- ROI: 950
- Rationale: `/roadmap` is linked from the homepage; every visitor gets a 500. Fix is pushed; only production ops action remains.

Commits: `6c0f49906` (fix), `d21c70cd6` (outbox) — both on `origin/main`

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260423-161501-qa-findings-forseti-life-1
- Generated: 2026-04-23T16:27:03-04:00

## Needs from Supervisor (up-chain)
- Execute on production: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`


## Blockers
- Production `git pull origin main` + `drush cr` still required (no SSH from this node)


## Full outbox (context)
The `/roadmap` listing page is now 500 — a new regression. My fix added `->setAbsolute(TRUE)->toString()` which requires request context and can throw in some Drupal environments. I need to add defensive try/catch around URL generation and wrap the listing page itself.

Now also wrap the `Url::fromRoute()->toString()` calls defensively in `loadRoadmapProjects()`:

Also fix the same pattern in `roadmapProject()` fallback:

- Status: blocked
- Summary: QA run 20260423-161501 found `https://forseti.life/roadmap` returning HTTP 500 — a new regression. Previous fix `789090d85` introduced `Url::fromRoute()->setAbsolute(TRUE)->toString()` without a try/catch; this throws when the URL generator encounters a production-specific context issue, converting the listing page from 200 to 500. Fix `6c0f49906` is committed and pushed: wraps `loadRoadmapProjects()` in try/catch, guards each URL generation call individually, and sets `max-age=0` to prevent caching a broken response. Production still requires `git pull + drush cr`.

## Next actions
- QA notified: fix `6c0f49906` pushed, requesting retest after production deploy

## Blockers
- Production `git pull origin main` + `drush cr` still required (no SSH from this node)

## Needs from Supervisor
- Execute on production: `cd /home/ubuntu/forseti.life && git pull origin main && cd sites/forseti && vendor/bin/drush cr`

## Decision needed
- Authorize production deploy — same as all prior escalations in this cycle

## Recommendation
- Option A (immediate): human owner runs `git pull + drush cr` on production
- Option B (permanent fix): restore GitHub Actions secrets for automated deploy

## ROI estimate
- ROI: 950
- Rationale: `/roadmap` is linked from the homepage; every visitor gets a 500. Fix is pushed; only production ops action remains.

Commits: `6c0f49906` (fix), `d21c70cd6` (outbox) — both on `origin/main`

---
- Agent: dev-forseti
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260423-161501-qa-findings-forseti-life-1
- Generated: 2026-04-23T16:27:03-04:00
