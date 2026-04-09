# Code Review — forseti.life — 20260409-forseti-release-j

- Status: done
- Summary: forseti-release-j is a fast-exit APPROVE — zero new forseti code commits exist since the release-h push (base SHA `b6dc673cd`). As part of this review, the 4 forseti-touching commits that shipped with release-h but were not included in the release-h code review have been retroactively assessed; all 4 are LOW risk with no new findings (config-only changes, a security-update composer.lock bump, and one PHP rendering enhancement using correct htmlspecialchars escaping throughout). No blockers to ship.

## Scope

- Release: `20260409-forseti-release-j`
- Activated: 2026-04-09T23:25:51Z
- Base SHA (last forseti push): `b6dc673cd` — "Release DC release-f + forseti release-h shipped"
- `git log b6dc673cd..HEAD -- sites/forseti/` → **0 commits**
- Result: **fast-exit** — no new code to review

## Retroactive review — 4 unreviewed release-h commits

These 4 commits are between the release-h review base (`9e52be8a0`) and the release-h push (`b6dc673cd`) but were outside the 7 commits selected for the release-h code review. They shipped in release-h. Reviewed here to close the gap.

### `db28288e4` — feat: releases page (DashboardController.php + copilot-hq config)

File reviewed: `sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`

Changes: Adds `releaseNotes()` rendering with state-badge HTML and feature-list HTML built from PM artifact files.

Security check:
- `$colour = $state_colours[$state] ?? '#6e7781'` — whitelist lookup, no injection path
- `htmlspecialchars(strtoupper($state))` in state_badge — correctly escaped
- `htmlspecialchars(trim($fid))` and `htmlspecialchars(trim($desc))` on feature list lines — correctly escaped
- Source data: PM artifact markdown files on server filesystem (not user input)

**Finding: none. APPROVE.**

### `1af68a272` — Fix cron overlap: disable automated_cron

File: `sites/forseti/config/sync/automated_cron.settings.yml`
Change: `interval: 0` (disables page-triggered cron; flock-protected crontab entry added)
Config-only. No code logic.

**Finding: none. APPROVE.**

### `166628b67` — fix(security): update aws-sdk-php, drupal/captcha, psysh, symfony/process

File: `sites/forseti/composer.lock`
Security patches:
- `aws/aws-sdk-php` 3.359.0 → 3.378.1 (HIGH: GHSA-27qh-8cxx-2cr5 + CVE-2025-14761)
- `drupal/captcha` 2.0.9 → 2.0.10 (MEDIUM: CVE-2026-3214)
- `psy/psysh` 0.12.14 → 0.12.22 (MEDIUM: CVE-2026-25129)
- `symfony/process` patched

composer.lock update with no application code changes. All bumps address known CVEs.

**Finding: none. APPROVE — positive security posture.**

### `6889f2ea3` — fix(forseti): remove orphaned social_auth_login block config

File: `sites/forseti/config/sync/block.block.forseti_socialauthlogin_2.yml`
Change: block config deletion for disabled module (was generating ~1,188 watchdog warnings/day)
Config-only removal. No security surface.

**Finding: none. APPROVE.**

## Findings summary

| ID | Severity | File | Description | Status |
|---|---|---|---|---|
| (none) | — | — | No new findings in release-j or retroactive release-h gap | — |

### Carried forward (from release-h)

| ID | Severity | Status |
|---|---|---|
| LOW-1 | LOW | Recurring: `return_to` open redirect bypass (`strpos` double-slash). No new instances in release-j. |
| LOW-2 | LOW | `hook_install()` missing `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` calls. Unaffected on upgrade paths; fresh-install only. Still open. |

## Verdict

**APPROVE** — fast-exit (0 new commits). Retroactive gap review of 4 release-h commits: all APPROVE. No blockers.

## Next actions
- PM may proceed with release-j ship when signoff criteria are met
- LOW-2 (`hook_install()` gap): recommend dev-forseti add the two missing table-creation calls in the next active release

## Base SHA for next forseti review
`bfd58cf89` (current HEAD — "qa: close hotfix-fighter-sudden-charge checklist entry")

## ROI estimate
- ROI: 8
- Rationale: Fast-exit with retroactive gap closure removes ambiguity about 4 previously unreviewed commits. Low urgency since release-j has no new code, but the gap advisory adds audit value for future reviews.
