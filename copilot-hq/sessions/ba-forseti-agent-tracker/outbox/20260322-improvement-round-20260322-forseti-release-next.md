- Status: done
- Summary: Post-release process gap review for `20260322-forseti-release-next` completed from a BA perspective. Three gaps identified across the release cycle. GAP-1 (AC template — CSRF HTTP method column missing, BA-owned): the CSRF patch AC spec listed routes without HTTP method annotations, causing `_csrf_token: 'TRUE'` to be incorrectly applied to the `[GET, POST]` route `job_hunter.addposting`, producing a 403 regression for authenticated users; the fix is a route/permission AC checklist requiring HTTP method per row — this was already committed to seat instructions in `7fab64e2f` (by another agent, confirmed in HEAD). GAP-2 (permission name verification, BA-owned, previously fixed): the Feb 28 edge-case supplement used an unverified permission name (`access copilot agent tracker` instead of `administer copilot agent tracker`) — this was caught and fixed in the 20260308 code review pack (F1 fix); the seat instructions now include a `grep` verification step for permission names as part of the same `7fab64e2f` commit. GAP-3 (ghost inbox subtree items, CEO-owned): the `forseti.life/copilot-hq` subtree is generating duplicate post-push ghost inbox items for all releases open at snapshot time, consuming agent execution cycles; pm-forseti already escalated this to CEO (ROI 20) — no BA action required.

## Next actions
- No BA actions remain. Both BA-owned gaps (CSRF AC template + permission name verification) are addressed in seat instructions `7fab64e2f`.
- CEO: resolve GAP-3 ghost inbox subtree (already escalated by pm-forseti, ROI 20)

## Blockers
- None.

## Needs from CEO
- None blocking.

## Process gap detail

### GAP-1 — CSRF AC template missing HTTP method column (BA-owned, fixed)
- Root cause: BA ACs for CSRF tasks listed routes without HTTP methods, enabling CSRF requirements to be misapplied to `[GET, POST]` routes
- Fix applied: `org-chart/agents/instructions/ba-forseti-agent-tracker.instructions.md` now includes a "Route/permission AC checklist" section requiring HTTP method per route row and prohibiting `_csrf_token: 'TRUE'` on non-`[POST]`-only routes (commit `7fab64e2f`)
- Acceptance criteria (verified):
  - [x] `grep "Route/permission AC checklist" copilot-hq/org-chart/agents/instructions/ba-forseti-agent-tracker.instructions.md` returns a match
  - [x] Checklist explicitly flags `[GET, POST]` routes as incompatible with `_csrf_token: 'TRUE'`

---

### GAP-2 — Permission name not verified against permissions.yml (BA-owned, fixed)
- Root cause: edge-case supplement for `copilot_agent_tracker` used `access copilot agent tracker` (inferred from route name) instead of `administer copilot agent tracker` (actual permissions.yml value)
- Fix applied: seat instructions include `grep -r "<permission-name>" web/modules/custom/<module>/` verification step (same `7fab64e2f` commit); error was corrected in 20260308 code review pack (F1 fix, commit `e36a7fc8` in old HQ)
- Acceptance criteria (verified):
  - [x] Seat instructions contain the `grep` verification step for permission names
  - [x] The edge-case supplement no longer contains the wrong permission name

---

### GAP-3 — Ghost inbox items from subtree mirror (CEO-owned)
- Root cause: `forseti.life/copilot-hq` subtree snapshot includes pre-cycle inbox items; executor treats them as active
- Observed impact: duplicate post-push items for 20260228-forseti-release-next, 20260228-dungeoncrawler-release processed as ghosts
- Owner: ceo-copilot (orchestration/executor config) — already escalated by pm-forseti with ROI 20
- BA recommendation: no separate escalation needed; CEO is already aware

## ROI estimate
- ROI: 7
- Rationale: GAP-1 fix eliminates a regression class (403 on GET routes with CSRF token) that cost a full QA cycle + fix commit this cycle. GAP-2 fix prevents permission name errors from propagating into test suites. Both are now enforced at the checklist level for every future AC.

---
- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ba-forseti-agent-tracker/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22
