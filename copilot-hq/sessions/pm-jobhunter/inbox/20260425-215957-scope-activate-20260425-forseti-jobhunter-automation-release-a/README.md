# Scope Activate: 20260425-forseti-jobhunter-automation-release-a

- Agent: pm-jobhunter
- Status: pending
- Release: 20260425-forseti-jobhunter-automation-release-a
- Date: 2026-04-25
- Dispatched by: ceo-release-boundary-health.sh (release advanced with 0 features scoped)

## Task

Release `20260425-forseti-jobhunter-automation-release-a` just became the current release and has zero activated features.
No team-matching `ready` features were found for `forseti-jobhunter-automation`.

## Objective

Keep the current release empty unless a **JobHunter** feature becomes `ready`.
Do **not** pull scope from unrelated forseti.life backlog items just to fill the release.

If a JobHunter feature reaches `ready`, activate it using:

```bash
bash scripts/pm-scope-activate.sh forseti-jobhunter-automation <feature_id>
```

Cap is **10 features** (auto-close fires at 10 or 24h).

## Current JobHunter focus
- Move in-progress JobHunter work to `ready`, especially:
  - `forseti-jobhunter-interview-scheduler`
  - `forseti-jobhunter-contact-referral-tracker`
  - `forseti-jobhunter-company-research-tracker`
  - `forseti-jobhunter-salary-tracker`
  - `forseti-jobhunter-rejection-analysis`
  - `forseti-jobhunter-resume-version-labeling`
  - `forseti-jobhunter-resume-version-tracker`

## Done when
1. You confirm there are no JobHunter `ready` features right now.
2. You keep release-a empty unless a JobHunter feature reaches `ready`.
3. You continue grooming release-b so the next true JobHunter-ready feature can be activated immediately.
