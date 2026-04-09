- Status: done
- Summary: Verified dev-infra outbox `bef7fd909` for the angelicafeliciano.com security audit. The site was already fully compliant before dispatch — `drupal/core` is 11.3.6 (required ≥ 11.2.8 for SA-CORE-2025-005/006/007/008 CVEs), `psy/psysh` is v0.12.22, `symfony/process` is v7.4.8; `drupal/captcha` and `aws/aws-sdk-php` are not installed on this site (those packages were patched on forseti.life in commit `166628b67`). `drush updb` confirms no pending database updates. No code changes were made by dev-infra; no regression risk. Infrastructure structural checks: `bash scripts/lint-scripts.sh` 0 issues; `python3 scripts/qa-suite-validate.py` 5 manifests PASS. APPROVE. Finding: pre-existing orchestrator regression — commit `fb5a842a9` added a release_id format guard to `release-signoff.sh` that invalidates all 6 test IDs in `orchestrator/tests/test_release_signoff_cross_team_qa.py` (test IDs like `test-cross-team-qa-20260408-forseti-release-x` no longer match the required `YYYYMMDD-<team>-release-<letter>` format). These 6 tests were PASS at last known baseline (`be3b5ead6`); regression introduced between sessions and unrelated to this inbox item. Dev-infra needs to update test IDs to valid-format release IDs.

## Next actions
- Inbox empty — awaiting next dispatch

## Blockers
- None (the angelicafeliciano audit itself is clean)

## Needs from CEO
- None

## Infrastructure regression finding (dev-infra attention needed)
- File: `orchestrator/tests/test_release_signoff_cross_team_qa.py`
- Root cause: commit `fb5a842a9` added release_id format guard to `release-signoff.sh` rejecting IDs not matching `YYYYMMDD-<team>-release-<letter>` pattern
- Fix: rename the 6 test release IDs to strip the `test-cross-team-qa-` prefix (e.g. `test-cross-team-qa-20260408-forseti-release-x` → `20260408-forseti-release-x`)
- Current state: 29/35 PASS (was 35/35 at baseline `be3b5ead6`)
- Impact: release-signoff cross-team QA coverage is dark until fixed

## ROI estimate
- ROI: 50
- Rationale: Restoring the 6 failing regression tests closes a dark coverage window on the release-signoff cross-team flow; fixing test IDs is a targeted change with no functional risk.

---
- Agent: qa-infra
- Source inbox: sessions/qa-infra/inbox/20260409-unit-test-20260409-security-updates-angelicafeliciano
- Generated: 2026-04-09T20:33:51+00:00
