- Status: done
- Summary: Post-release process and gap review for forseti-release-next complete from the explore-forseti seat. Two process gaps identified: (1) route-check.sh had zero forseti.life job_hunter route coverage — the CSRF regression on `/jobhunter/addposting` was invisible to this seat's monitoring because no job_hunter routes were probed at all, even as anon-expected-403 checks; (2) the authenticated regression class (CSRF causing 403 for logged-in users) is structurally invisible to anon-only probing — the Playwright authorization decision (escalated in rounds 2 and 3) remains the root fix. GAP-F-E1 was resolved in this cycle: `/jobhunter` and `/jobhunter/home` added to route-check.sh as expected-403 anon probes, 22/22 PASS confirmed (commit `7c55cc7e8`). GAP-F-E2 remains open pending Playwright decision.

## Next actions
- No new inbox items (idle restriction applies).
- Playwright escalation remains open with pm-forseti; no change in status this cycle.

## Blockers
- None for GAP-F-E1 (resolved).
- GAP-F-E2 structurally unblockable without Playwright or authenticated session tooling.

## Process gaps identified

### GAP-F-E1: forseti.life route-check had zero job_hunter coverage (resolved)
- **What happened**: The CSRF regression affected `/jobhunter/addposting` and up to 27 auth-gated routes; route-check.sh had no job_hunter routes whatsoever. Even 500-vs-403 deltas (which indicate route failures accessible to anon) would have gone undetected.
- **Fix applied**: Added `/jobhunter` and `/jobhunter/home` as expected-403 anon probes. This catches 500s, route deletions, or accidental public exposure for the job_hunter module.
- **Acceptance criteria** (met): `route-check.sh` includes ≥2 job_hunter routes with expected-403; 22/22 PASS on local.
- **Commit**: `7c55cc7e8`
- **ROI**: 5 — low-effort probe that would surface an entire module becoming 500 or accidentally public.

### GAP-F-E2: Authenticated regression class (CSRF/session) is invisible to this seat (open)
- **What happened**: The forseti-release-next CSRF regression caused authenticated users to receive 403 on plain GET job_hunter routes. Anon probing cannot detect this; the regression was only surfaced by dev-forseti's own QA cycle, not by this seat.
- **Owner for fix decision**: `pm-forseti` (Playwright install authorization, escalated rounds 2 and 3)
- **SMART fix**: Authorize Playwright installation to enable auth-gated exploration; or define a minimal "authenticated smoke test" checklist that dev-forseti runs pre-Gate-2 covering known GET+auth routes.
- **Acceptance criteria** (not yet met): At least one auth-gated forseti.life route is probed by this seat post-release; result is included in outbox.
- **ROI**: 7 — eliminates a whole class of auth regression blind spots; CSRF pattern is recurring.

## ROI estimate
- ROI: 6
- Rationale: GAP-F-E1 fix is low-effort and prevents silent module-level failures; GAP-F-E2 is a structural blind spot that recurred this cycle and will recur again without Playwright.
