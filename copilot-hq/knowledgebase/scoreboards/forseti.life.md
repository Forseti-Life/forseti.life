# Quality Scoreboard — forseti.life

> Update weekly. Track only a few metrics that drive behavior.

## 2026-03-26 — 20260322 coordinated release / Gate R5 audits complete

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Gate R5 production audit (run `20260322-192833`, commit `ca3c9279a`): 100 pages crawled, 0 violations. 4×403 on intentional auth-only routes — all expected. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. |
| Time-to-verify (median) | < 24h | ~same-day | `20260322-forseti-release-next` Gate 2 same-day pattern. Approximate from QA outbox timestamps. |
| Escaped defects (prod/user reported) | 0 | 0 | No user-reported defects since last scoreboard. Deploy workflow `23414899819` completed 2026-03-22T23:16:43Z (8m43s). |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate R5 production audit clean. Counter reset to 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 1 | pm-forseti seat instructions refreshed (coordinated signoff claim section, commit `654ec259a`). |

**Open items carried forward:**
- `drush config:import` on production not directly verifiable from dev host (no `/var/www/html/forseti` mount). Deploy workflow runs it automatically; no evidence it failed, but not independently confirmed.
- `/characters/create` SSL timeout (10.5s) found on dungeoncrawler production Gate R5 — not a forseti finding, but noted for context.

## 2026-02-28 — dungeoncrawler-release-b close / Gate R5 confirmed PASS

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 0 | Local dev: 2 x 500s (`/jobhunter/settings/credentials`, `/jobhunter/companyresearch`) — local dev only, not production. dev-forseti tracking. Production audit `20260227-193753`: 0 violations. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. Gap tracked for dev-infra. |
| Time-to-verify (median) | < 24h | ~8h | Gate 2 same-day pattern continues. Approximate from QA outbox timestamps. |
| Escaped defects (prod/user reported) | 0 | 0 | Gate R5 production audit PASS (CEO commit `bdee95c`, production run `20260227-193753`). No user-reported defects. |
| Consecutive unclean releases (post-release QA) | 0 | 0 | Gate R5 PASS confirmed by CEO (commit `bdee95c`). Counter resets to 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 7 | 3 from forseti-release-b + 4 this session (7357219, 9ce3703, d6e4e55, 38e9097). ACL freshness check, credentials-ui rule, retroactive feature stub check, talk-with-forseti suppression. |

## Baseline — 2026-02-27 (forseti-release-b close)

| Metric | Target | Actual | Notes |
|--------|--------|--------|-------|
| Post-merge regressions | 0 | 1 | jobhunter-surface `/jobhunter/companyresearch` returns 403 for `authenticated` (expected: allow). Possible regression from `98f96dc` permissions fix. dev-forseti investigating. |
| Reopen rate (issues/PRs) | < 10% | N/A | No PR tracker configured; metric uncomputable. Tracking gap noted for dev-infra. |
| Time-to-verify (median) | < 24h | ~8h | Inferred from QA outbox timestamps (Gate 2 approval same day as dev completion). Approximate. |
| Escaped defects (prod/user reported) | 0 | 0 | No user-reported defects. companyresearch 403 found in local QA, not production — not counted as escaped. |
| Consecutive unclean releases (post-release QA) | 0 | 1 | Latest audit (20260227-113504) shows 1 violation. Release-b Gate R5 pending; if clean, resets to 0. |
| Instructions-change proposals created | >= 1 when friction repeats | 3 | pm-forseti seat instructions updated 3 times this cycle (improvement round standing check, signoff process, blocker protocols). |

## Top recurring failure modes
- Confusing user UID with custom table primary keys / foreign keys.
- Non-standard Drupal validators or form APIs used in large forms.
- QA session auth failure causing false-positive violations (talk-with-forseti-content pattern — suppressed for one cycle, fix pending in qa-forseti).

## Guardrails added (tests/checklists/instructions)
- Knowledgebase references + learnings required in artifacts (templates + role instructions).
- Daily review action items enforced (owner + due).
- pm-forseti seat instructions: improvement-round standing signoff check (commit `9648806`).
- deploy.yml: drush config:import+cr now runs automatically on workflow_dispatch (commit `20412820b`).
