# dev-infra outbox — 20260406-orchestrator-empty-release-guard

- Status: done
- Summary: Fixed the orchestrator empty-release auto-close deadlock (GAP-IR-20260405). Added `_count_site_features_for_release(site_kw, rid)` to `orchestrator/run.py` that counts only features matching `Status: in_progress` + `Website: <site>` + `Release: <rid>`. Replaced the unscoped `_count_site_features_in_progress` call in the FEATURE_CAP trigger with the release-scoped variant so stale in_progress features from prior releases cannot fire a close trigger on a new empty release. Added a `release_feature_count > 0` guard to the AGE trigger for the same reason. GAP-IR-20260405-1 (NameError) was verified as non-issue in the current codebase — `_dispatch_release_close_triggers` is defined at L1144 and its only call site is at L1494. Committed `04e29e01`.

## What was done

### GAP-IR-20260405-1 (NameError) — non-issue confirmed
- `_dispatch_release_close_triggers` defined at line 1144, called only at line 1494.
- No forward-reference risk in Python (function bodies look up names at call time).
- The NameError in the KB lesson was from an earlier version — no code change needed.

### GAP-IR-20260405-2 (FEATURE_CAP unscoped) — fixed
- Added `_count_site_features_for_release(site_kw, rid)` at line 806.
- Requires all three: `Status: in_progress`, `Website: <site>`, `Release: <rid>` in feature.md.
- FEATURE_CAP trigger now uses `_count_site_features_for_release(site_kw, rid)` instead of the site-only variant.
- Features without a `Release:` field are excluded (treated as untracked; not attributed to the current release).

### AGE trigger zero-feature guard — fixed
- Before firing AGE trigger, `_count_site_features_for_release(site_kw, rid)` is called.
- If result is 0, the AGE trigger is skipped — no release-close-now dispatched for an empty release.

## Verification
- `python3 -m py_compile orchestrator/run.py` — SYNTAX OK
- Manual diff review: both trigger blocks updated correctly; FEATURE_CAP log message now includes release_id for traceability.

## Commit hashes
- `04e29e01` — fix(orchestrator): scope FEATURE_CAP to current release_id, guard AGE trigger on empty releases

## Next actions
- None. This inbox item is complete.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: The deadlock wasted 5+ hours of CEO+PM time and required a manual waiver. This fix prevents the same class of incident from recurring on every release cycle transition. High recurring prevention value.
