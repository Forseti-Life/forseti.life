# Lesson: Gate 2 backstop filed for stale release (master/worker divergence)

- Date: 2026-04-22
- Site: forseti
- Release affected: 20260412-forseti-release-m (stale), active was 20260419-forseti-release-c
- Root cause class: GAP-MASTER-WORKER-SYNC

## What happened
The clean-audit Gate 2 backstop (`ceo-ops-once.sh` at 2026-04-22 04:00) filed a Gate 2 APPROVE
for `20260412-forseti-release-m` — a release that had already advanced to `20260419-forseti-release-c`
on the worker node. The master HQ repo (`/home/keithaumiller/forseti.life/copilot-hq`) was
behind the worker repo (`/home/keithaumiller/forseti-repos/copilot-hq`): specifically, the
`tmp/release-cycle-active/forseti.release_id` file still contained `20260412-forseti-release-m`
because `git pull` had not been run since the release advanced.

`ceo-ops-once.sh` does not call `git pull` before running the backstop, so it operated on
stale state, producing a Gate 2 artifact for the wrong (already-past) release.

The Gate 2 APPROVE for the correct active release (`20260419-forseti-release-c`) had already
been filed on the worker at 2026-04-21T00:00:30.

## Root cause
- `ceo-ops-once.sh` (runs on master via cron) does not call `git fetch` / `git pull` before running.
- `tmp/release-cycle-active/` contains runtime state committed by the worker. If master falls behind,
  it will operate on stale release IDs.
- No check guards against "this release has already been superseded."

## Fix applied
- Added `git fetch origin --prune --quiet && git pull --rebase origin main --quiet` at the top of
  `scripts/ceo-ops-once.sh` before the Clean-audit Gate 2 backstop section.
- Note: the stale Gate 2 artifact is harmless (it targets a superseded release, not the current one),
  but the root-cause review item it spawned creates unnecessary CEO queue noise.

## Prevention
- `ceo-ops-once.sh` should always pull latest before reading runtime state files.
- Alternatively, `gate2-clean-audit-backstop.py` should verify that the release being approved
  matches the *latest* release ID, not just the active file at read time — adding a staleness guard.

## Impact
- Low: a Gate 2 APPROVE was filed for a completed/advanced release. No production impact.
- Medium: spawned an unnecessary CEO root-cause review inbox item, consuming an execution slot.
