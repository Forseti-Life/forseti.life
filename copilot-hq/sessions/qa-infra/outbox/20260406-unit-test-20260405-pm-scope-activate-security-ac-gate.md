- Status: done
- Summary: Verified security AC gate in `scripts/pm-scope-activate.sh` (commits `c4b12201` + `39759a5d`). The gate at lines 93–126 blocks scope activation unless `feature.md` contains either a non-empty `## Security acceptance criteria` section or a `- Security AC exemption: <reason>` bypass field. All 4 acceptance criteria verified with live functional tests: missing section exits 1 with actionable error, empty section exits 1, valid section exits 0 (proceeds to activate), exemption field exits 0. KB lesson present at `knowledgebase/lessons/20260405-security-ac-gate-enforcement.md`. Verdict: **APPROVE**.

## Verification evidence

| Check | Result |
|---|---|
| Commit c4b12201 present | PASS |
| Commit 39759a5d (KB lesson) present | PASS |
| `bash -n scripts/pm-scope-activate.sh` | PASS |
| Gate block at lines 93–126 | CONFIRMED |
| Test 1: missing `## Security acceptance criteria` → exit 1, clear error with template | PASS |
| Test 2: empty `## Security acceptance criteria` section → exit 1 | PASS |
| Test 3: valid section with content → exit 0 (activation proceeds) | PASS |
| Test 4: `- Security AC exemption: <reason>` present → exit 0 (bypass fires) | PASS |
| Error message includes all 4 required subsections (auth surface, CSRF, input validation, PII/logging) | CONFIRMED |
| KB lesson at `knowledgebase/lessons/20260405-security-ac-gate-enforcement.md` | PRESENT |
| bash -n all 106 scripts | PASS |
| lint-scripts.sh | 0 issues |
| qa-suite-validate.py | 5 suites OK |
| site-audit-run.sh | N/A (infrastructure — no web surface) |

## Regression checklist
- Updated: `org-chart/sites/infrastructure/qa-regression-checklist.md` — entry 20260405-pm-scope-activate-security-ac-gate marked PASS.

## Next actions
- None for this item.

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: Security surface documentation enforced at scope-activation prevents CSRF/authz gaps from reaching prod; closes a known gap that persisted for multiple release cycles before enforcement.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260406-unit-test-20260405-pm-scope-activate-security-ac-gate
- Generated: 2026-04-06T04:50:00+00:00
