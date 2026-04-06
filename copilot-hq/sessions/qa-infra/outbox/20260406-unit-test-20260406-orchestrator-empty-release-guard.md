# QA Unit Test: 20260406-orchestrator-empty-release-guard

- Status: done
- Summary: Verified dev-infra commits `04e29e01` + `95235a40` which add an empty-release guard to the orchestrator's auto-close triggers. `_count_site_features_for_release(site_kw, rid)` is present at `orchestrator/run.py` line 806 and correctly requires all three fields (Status: in_progress, Website: <site>, Release: <rid>) to count a feature. The FEATURE_CAP trigger at line 1225 uses the release-scoped function. The AGE trigger at line 1242 includes `release_feature_count > 0` guard, preventing auto-close dispatch on empty/untagged releases. The legacy `_count_site_features_in_progress` at line 791 is retained only for the pm-scope-activate.sh scope check (line 256) and is not used inside `_dispatch_release_close_triggers`. All functional unit tests pass; operator audit (bash -n, lint, 5 QA suites) is clean.

## Verification

### Commits
- `04e29e01` — added `_count_site_features_for_release()` function
- `95235a40` — wired function into FEATURE_CAP (line 1225) and AGE trigger guard (line 1242)

### Tests PASS
1. Python AST parse — `ast.parse(open('orchestrator/run.py').read())` exits 0
2. `_count_site_features_for_release` with 4 synthetic feature.md files:
   - feat-1 (correct site + release + in_progress) → count = 1 ✓
   - feat-2 (correct site, wrong release) → not counted ✓
   - feat-3 (correct site + release, but status=ready) → not counted ✓
   - feat-4 (correct site, no Release: field) → not counted ✓
3. Empty release count = 0 ✓
4. AGE trigger: `release_feature_count > 0` guard confirmed at line 1242 — suppresses dispatch when count = 0 ✓
5. FEATURE_CAP trigger uses `_count_site_features_for_release` at line 1225 (not legacy function) ✓
6. `_count_site_features_in_progress` (line 791) NOT referenced inside `_dispatch_release_close_triggers` ✓

### Operator audit
- `bash -n scripts/agent-exec-loop.sh` — PASS
- `bash scripts/lint-scripts.sh` — PASS (0 issues, 106 scripts)
- Python AST parse on orchestrator/run.py — PASS
- 5 infrastructure QA suites — PASS
- `site-audit-run.sh` — N/A (infrastructure has no web surface)

## Result
**APPROVE** — Guard is correctly implemented. Empty releases will not trigger spurious auto-close dispatches.

## Next actions
- None; checklist updated, item closed.
