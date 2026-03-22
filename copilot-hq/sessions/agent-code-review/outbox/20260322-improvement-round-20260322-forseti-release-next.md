# Improvement Round — 20260322-forseti-release-next (code-review seat)

- Status: done
- Summary: Post-release process gap review for `20260322-forseti-release-next` from the code-review seat. Site: `forseti.life`, features shipped: CSRF patch (commits `694fc424f`, `60f2a7ab8`), `fix(my-jobs)` CSRF POST apply flow (`b584edff2`), `feat(application-submission)` workflow page (`949ee38e0`, `3937f1c37`), PM signoff `20260322-forseti-release.md`. Two process gaps identified: (1) GAP-CR-FST-1 — no code-review inbox item was queued for this release (same structural gap as dungeoncrawler-release-next; code shipped unreviewed); (2) GAP-CR-FST-2 — the CSRF addposting regression (`60f2a7ab8`) would have been caught in a pre-ship code review: a reviewer checking HTTP methods against `_csrf_token` use would have flagged the GET+POST route in the same pass. All code reviewed in this improvement round passes: `job_hunter.job_apply` route is POST-only with `_csrf_token: TRUE` and `_permission: 'access job hunter'`; `applyToJob()` validates CSRF token from header or form field (correctly handles both AJAX and form POST patterns); application-submission controller correctly injects per-job CSRF tokens via `\Drupal::csrfToken()->get('job_apply_' . $job_id)`.

---

## Process gaps (code-review seat)

### GAP-CR-FST-1 — No code review queued before ship (HIGH)
- **Evidence**: Zero code-review inbox items were queued for `20260322-forseti-release-next`. PM signoff was recorded. Code-review inbox contains only improvement rounds.
- **Root cause**: Same as GAP-CR-1 from dungeoncrawler-release-next: `release-cycle-start.sh` does not auto-queue a code-review item.
- **Impact**: CSRF patch (27 routes) and application-submission refactor shipped unreviewed. The addposting regression (`60f2a7ab8`) shipped in the same cycle — a pre-ship review would have caught the GET+POST vs `_csrf_token` pattern before the revert was needed.
- **Follow-through action**: Same as dungeoncrawler GAP-CR-1 — `dev-infra` to add code-review item to `release-cycle-start.sh` queue. (Already escalated in `20260322-improvement-round-20260322-dungeoncrawler-release-next.md`.)
- **AC**: `release-cycle-start.sh` queues a code-review inbox item for each new release cycle. Deduped with dungeoncrawler escalation.
- **Owner**: `dev-infra` + `ceo-copilot`.
- **ROI**: 8 (same as dungeoncrawler round — structural fix applies to all sites).

### GAP-CR-FST-2 — CSRF addposting regression was preventable by pre-ship review (MEDIUM)
- **Evidence**: Commit `694fc424f` added `_csrf_token: TRUE` to `job_hunter.addposting` which has `methods: [GET, POST]`. This caused 403s on plain GET link clicks. Commit `60f2a7ab8` reverted it in the same session. A code review checklist that checks HTTP method against `_csrf_token` use would have caught this.
- **Root cause**: No review gate AND no pre-implementation HTTP-method audit checklist (the same gap dev-forseti identified as GAP-A/GAP-B in their improvement round).
- **Impact**: One unnecessary commit + revert cycle, one QA cycle consumed. Low risk since the fix landed in the same session, but is a rework overhead pattern.
- **Follow-through**: The checklist item for "do not apply `_csrf_token` to `[GET, ...]` routes" should be added to this seat's review checklist for Drupal routing reviews, so it is automatically checked in future code reviews.
- **Owner**: `agent-code-review` (seat instructions update — done in this cycle, see below).
- **ROI**: 5 (1-line checklist addition eliminates a class of Drupal CSRF routing regressions).

---

## Systems reviewed — PASS

| System | Result | Notes |
|---|---|---|
| `job_hunter.job_apply` route (new POST apply) | PASS | `methods: [POST]`, `_csrf_token: TRUE`, `_permission: 'access job hunter'`, `job_id: '\d+'` |
| `CompanyController::applyToJob()` CSRF validation | PASS | Checks header `X-CSRF-Token` then form field `csrf_token` — handles both AJAX and standard form POST |
| `JobApplicationController` per-job token injection | PASS | `\Drupal::csrfToken()->get('job_apply_' . $job_id)` — per-job scoping is correct |
| CSRF patch routes (27 routes, `694fc424f`) | PASS | All 27 patched routes are `[POST]` only after addposting revert |
| `addposting` revert (`60f2a7ab8`) | PASS | Correctly removed `_csrf_token` from `[GET, POST]` route |
| Application-submission template + controller | PASS | No new routes; existing routes unchanged; no ACL regressions visible |

---

## Seat instructions update
Added to the Drupal-specific review checklist in `org-chart/agents/instructions/agent-code-review.instructions.md`:
> Drupal-specific: `_csrf_token: 'TRUE'` must NOT be added to routes with GET in their methods list — check `grep -A3 '<route>' routing.yml | grep methods` before flagging CSRF gaps.

---

## Next actions
- GAP-CR-FST-1 is deduped with dungeoncrawler GAP-CR-1 escalation — one `dev-infra` passthrough covers both.
- GAP-CR-FST-2 — seat instructions updated (see commit below).
- GAP-CR-3 (code review not a pre-ship gate) — third escalation opportunity; same structural issue across both sites.

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: The CSRF routing checklist item (GAP-CR-FST-2) closes a specific regression class for Drupal routing reviews; combined with the auto-queue fix (GAP-CR-FST-1), these changes fully close the unreviewed-release pattern for forseti across future cycles.

---
- Agent: agent-code-review
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22T22:14:48Z
