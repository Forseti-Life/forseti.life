# Shipping Gates (Checks & Balances)

Master process flow (authoritative): `runbooks/release-cycle-process-flow.md`

## Gate 0 — Intake (Any role)
Required artifacts:
- Problem Statement
- Acceptance Criteria
- Risk Assessment (initial)

Release-cycle rule:
- Intake is always allowed, but once a release cycle starts and scope is frozen, new intake is for the **next** release cycle (or deferred) unless PM explicitly re-baselines the cycle.

Exit criteria:
- Scope and non-goals are explicit.
- Permissions and failure modes are defined.

## Gate 1 — Implementation Ready (Dev)
Required artifacts:
- Implementation Notes (draft)

Exit criteria:
- Approach matches acceptance criteria.
- Identified tests to run.

## Gate 1b — Code Review Finding Dispatch (PM, required before Gate 2)

After each `agent-code-review` run for a release cycle, PM must:
1. Read the code-review outbox for that release: `sessions/agent-code-review/outbox/<date>-code-review-<site>-<release-id>.md`
2. For every finding rated **MEDIUM or higher**, create a dev-seat inbox item **within the same release cycle**:
   - Folder: `sessions/<dev-seat>/inbox/<date>-cr-finding-<finding-id>/`
   - Required fields in `command.md`: finding ID, file path, severity, description, fix approach (if known), acceptance criteria
   - Required: `roi.txt` (use severity as proxy: CRITICAL→10, HIGH→8, MEDIUM→6)
3. If risk acceptance is chosen instead of a fix, record the decision explicitly in `sessions/pm-<site>/artifacts/risk-acceptances/` with rationale and sign-off owner.

**Exit criteria (Gate 1b):**
- All MEDIUM+ findings either have a dev-seat inbox item OR an explicit risk-acceptance record.
- No MEDIUM+ finding may be left unrouted (i.e., visible only in the code-review outbox).

**Gate sequencing:** Gate 1b must complete before PM may record a release signoff (`scripts/release-signoff.sh`).

**Lesson (2026-03-19):** In dungeoncrawler release-a, finding F-DC-A-1 (MEDIUM: CAST LIKE on LONGTEXT columns, `copilot_agent_tracker`) went untracked from Mar 9 to Mar 19 — triggering an unplanned extra QA cycle at Gate 2 (8 violations, commit `175b7c3b4`).

## Gate 2 — Verification (Tester)
Required artifacts:
- Test Plan
- Verification Report
- Methodology reference (required): `runbooks/role-based-url-audit.md` (URL/access validation by role; localhost-first)

Test-case source of truth requirement:
- Test cases must reside in a central executable automation suite with PASS/FAIL outcomes.
- The release candidate must record which automated suites were run and the results (see `templates/release/02-test-evidence.md`).

Exit criteria:
- Evidence attached.
- Explicit APPROVE or BLOCK.

## Gate 3 — Release (Release coordinator + Dev + Tester)
Required artifacts:
- Release Notes

Exit criteria:
- Release coordinator confirms coordinated-window readiness (when applicable).
- Tester approves.
- Dev confirms deploy steps/rollback and that all changes are committed (commit hash(es) recorded).

Coordinated release rule (Forseti + Dungeoncrawler):
- All required coordinated PM seats must sign off before the official push:
	- `./scripts/release-signoff.sh <site-or-team-alias> <release-id>`
- Required seats are resolved from `org-chart/products/product-teams.json` where `active=true` and `coordinated_release_default=true`.
- Release operator (`pm-forseti`) verifies:
	- `./scripts/release-signoff-status.sh <release-id>`
- Per-team release ID registration (required): each coordinated PM seat must also record a per-team signoff for their own release ID in addition to the shared coordinated ID:
	- `./scripts/release-signoff.sh dungeoncrawler <per-team-release-id>`
	- This ensures improvement-round.sh detects the release at the correct time and avoids retroactive signoff artifacts being created later by workspace merges.

## Gate 4 — Post-release verification (Tester, production)
Required artifacts:
- Post-release verification note (may reuse Verification Report format)

Exit criteria:
- Tester runs the same audit protocol against production base URL(s).
- If clean: Tester explicitly reports “post-release QA clean” and “no new items identified for Dev”.
- If unclean: Tester records the unclean signal with evidence.

Policy:
- If post-release is unclean, the next release cycle is remediation-only (no new features).
- PM escalates to CEO if there are 3 unclean releases in a row for a product/site.

## Coordinated Release (optional: CEO)
Additional rules for shipping:
- Forseti and Dungeoncrawler pushes happen in the same release window.
- CEO may review the complete list of features/defects fixed for coordination/risk.

Runbook: `runbooks/coordinated-release.md`
