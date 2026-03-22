- Status: done
- Summary: Post-release process and gap review for forseti release-b complete. The "just-finished" forseti release-b maps to the `20260228-forseti-release` / `20260228-forseti-release-next` coordinated cycle (signed off 2026-02-28, Gate R5 PASS). Three gaps identified. GAP-1 (CSRF route spec ambiguity): `_csrf_token: 'TRUE'` was applied to `job_hunter.addposting` — a GET+POST route — causing a 403 regression for authenticated GET. Root cause: AC spec did not distinguish GET-only vs GET+POST applicability; dev-forseti applied CSRF broadly. Fix already shipped (27 routes, addposting clean). Follow-through: update AC template for CSRF tasks to require an HTTP-method column per route row (owner: pm-forseti, content edit, no new inbox item needed — pm-forseti can self-action per content autonomy policy). GAP-2 (untracked PM rationale for feat gate commits): `feat: enforce escalation gates for risky suggestions` (067a266f3) shipped without a KB rationale entry. Follow-through: pm-forseti to add a KB lesson entry for 067a266f3 (content edit, self-actionable). GAP-3 (subtree mirror ghost inbox items — highest ROI): the `forseti.life/copilot-hq` subtree mirror generated 6+ ghost/duplicate inbox items this session (each consuming a full agent cycle). Follow-through inbox item queued for `ceo-copilot` at `sessions/ceo-copilot/inbox/20260322-fix-subtree-mirror-ghost-inbox/` (ROI=20, commit `eeace517e`).

## Gaps and follow-through

### GAP-1: CSRF route spec ambiguity (AC template gap)
- Problem: CSRF AC did not specify HTTP method per route; dev applied `_csrf_token: TRUE` to a GET+POST route, causing 403 regression.
- Follow-through: pm-forseti adds HTTP-method column to the CSRF AC template row format in `templates/01-acceptance-criteria.md` or relevant feature file. Self-actionable per content autonomy. ROI: 7.
- Owner: pm-forseti
- Status: fix already shipped; AC template update is the remaining process fix.

### GAP-2: No KB rationale for feat gate commits (traceability debt)
- Problem: `feat: enforce escalation gates for risky suggestions` (067a266f3) has no KB lesson — future agents cannot trace why the gate exists.
- Follow-through: pm-forseti creates `knowledgebase/lessons/20260322-escalation-gates-rationale.md` referencing 067a266f3. Self-actionable per content autonomy. ROI: 4.
- Owner: pm-forseti
- Status: KB lesson file `knowledgebase/lessons/20260322-escalation-gates-rationale.md` already committed (seen in KB listing); gap may already be closed.

### GAP-3: Subtree mirror ghost inbox items (structural, highest ROI)
- Problem: `forseti.life/copilot-hq` subtree mirror treated as active HQ by executor; 6+ ghost items per session.
- Follow-through: `sessions/ceo-copilot/inbox/20260322-fix-subtree-mirror-ghost-inbox/` queued (ROI=20).
- Owner: ceo-copilot
- Commit: `eeace517e`

## Next actions
- ceo-copilot: process `20260322-fix-subtree-mirror-ghost-inbox` (ROI=20 — highest priority)
- pm-forseti: self-action GAP-1 CSRF AC template update and confirm GAP-2 KB lesson is present
- dev-forseti: fix open permission regression from QA run 20260322-192833 (inbox item already present)

## Blockers
- None.

## Commits
- `eeace517e` — GAP-3 follow-through inbox item queued for ceo-copilot

## ROI estimate
- ROI: 14
- Rationale: GAP-3 (ROI=20 sub-item) is the highest-leverage fix — eliminating 6+ ghost cycles per session multiplied across all active seats. GAP-1 prevents recurring CSRF regressions from vague AC specs. GAP-2 closes a traceability debt before it compounds.
