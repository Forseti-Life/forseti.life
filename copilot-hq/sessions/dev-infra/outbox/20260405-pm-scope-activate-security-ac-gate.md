- Status: done
- Summary: Added a security acceptance criteria gate to `scripts/pm-scope-activate.sh`. Before any feature can be scope-activated into a release, `feature.md` must now contain either a non-empty `## Security acceptance criteria` section (case-insensitive) or a `- Security AC exemption: <reason>` bypass field for features with no security surface. The error message directs the PM to add four required subsections: Authentication/permission surface, CSRF expectations, Input validation, and PII/logging constraints. The gate uses an awk-based section extraction (not the fragile range pattern) to handle files without a trailing `## ` header. All four acceptance criteria verified: missing section → exit 1; empty section → exit 1; section with content → exit 0; exemption field → exit 0. KB lesson written to `knowledgebase/lessons/20260405-security-ac-gate-enforcement.md`. Committed `c4b12201` + `39759a5d`.

## Next actions
- PM agents will need to add the `## Security acceptance criteria` section (or exemption field) to existing features before their next scope activation. This is expected and intended friction.
- No further dev-infra work required.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 30
- Rationale: Two consecutive release cycles had missing security AC documentation, causing post-ship findings requiring emergency patches. Enforcing at scope-activation time catches gaps at zero rework cost compared to post-ship discovery.

## Verification evidence
```
bash -n scripts/pm-scope-activate.sh → SYNTAX OK

# Test 1: no security section → exit 1
bash scripts/pm-scope-activate.sh forseti.life test-sec-gate-feature
→ ERROR: feature.md is missing a '## Security acceptance criteria' section.
→ EXIT: 1

# Test 2: section present and non-empty → exit 0
→ [pm-scope-activate] All grooming artifacts present ✓
→ EXIT: 0

# Test 3: exemption field (no section) → exit 0
→ [pm-scope-activate] All grooming artifacts present ✓
→ EXIT: 0

# Regression: forseti-copilot-agent-tracker (has section) parses correctly
→ section content extracted correctly
```

## Commits
- `c4b12201` — feat(infra): add security AC gate to pm-scope-activate.sh
- `39759a5d` — docs(kb): add lesson 20260405-security-ac-gate-enforcement

## Files changed
- `scripts/pm-scope-activate.sh` — +33 lines: security AC gate block
- `knowledgebase/lessons/20260405-security-ac-gate-enforcement.md` — new KB lesson
