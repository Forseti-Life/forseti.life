# Roadmap — forseti-financial-health-home

- Feature: `forseti-financial-health-home`
- Website: `forseti.life`
- Project: `PROJ-008`
- Owner: `pm-forseti`
- Source owner: `accountant-forseti`
- Architecture: `features/forseti-financial-health-home/03-next-phase-architecture.md`

## Objective

Ship an internal Drupal **Financial Health** home that surfaces Forseti's institutional financial state from the accountant book of record.

## Why now

Forseti's accounting workspace now exists and has a single current dashboard in `dashboards/finance/current-dashboard-2026-04.md`, but leadership still has to leave the product and inspect HQ markdown to understand financial health. This roadmap turns that operational accounting state into a first-class internal product surface.

## Current Forseti delivery pipeline context

### In progress now
- `forseti-jobhunter-interview-outcome-tracker`
- `forseti-jobhunter-offer-tracker`
- `forseti-jobhunter-application-analytics`
- `forseti-jobhunter-follow-up-reminders`

### Ready backlog now
- `forseti-ai-local-llm-provider-selection`
- `forseti-community-incident-report`
- `forseti-financial-health-home`
- `forseti-jobhunter-company-interest-tracker`
- `forseti-jobhunter-company-research-tracker`
- `forseti-jobhunter-contact-referral-tracker`
- `forseti-jobhunter-contact-tracker`
- `forseti-jobhunter-job-board-preferences`
- `forseti-jobhunter-resume-version-labeling`
- `forseti-jobhunter-resume-version-tracker`
- `forseti-langgraph-console-run-session`

## Recommended sequencing

### Position in pipeline
- Do **not** interrupt the 4 active Job Hunter features already in progress.
- Keep `forseti-financial-health-home` as a **ready** backlog item for the next available Forseti release cycle after current release-h pressure eases.
- It is a good candidate once PM wants to diversify beyond Job Hunter-only backlog slices and begin productizing the accounting system.

### Suggested activation window
- After current release-h in-progress work clears enough slots
- After PM confirms whether the MVP should remain in `institutional_management` or later split into a dedicated finance module
- Prefer activation once AWS billing access and income/cash source decisions are at least partially resolved, so the page does not launch with only blocked values

## Delivery phases

### Phase 1 — Internal MVP
- Create route `/internal/financial-health`
- Render executive health band
- Render source coverage
- Render active blockers
- Render current-month roll-up
- Link to underlying accountant artifacts

### Phase 2 — richer operational value
- Add anomaly highlights
- Add renewal calendar summary
- Add month-over-month trend deltas
- Add explicit last-refresh and data-age indicators

### Phase 3 — structured finance feed
- Replace markdown-derived rendering with a structured summary feed if needed
- Add more granular drilldowns behind internal permissions

## Dependencies

### Hard dependencies
- Internal access model decision
- Source-of-truth contract between Drupal rendering and accountant artifacts

### Soft dependencies
- AWS billing pull access
- Income source confirmation
- Cash evidence source confirmation
- GitHub fixed-charge completeness decision

## Risks

- If activated too early, the page may be structurally correct but financially sparse because source coverage is still incomplete.
- If Drupal becomes a second manual edit surface, accounting truth will drift from HQ.
- If access controls are too loose, internal institutional finance data may become visible too broadly.

## Recommended next action

`pm-forseti` should track the MVP implementation now underway in `institutional_management`, keep the feature in the active queue, and decide later whether a dedicated finance module is justified after structured data feeds mature.
