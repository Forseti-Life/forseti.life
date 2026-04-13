- Status: done
- Summary: Completed BA grooming for `forseti-langgraph-console-run-session` (PROJ-001, release-h). Inspected live controller code (`LangGraphConsoleStubController`, lines 364–1050) and confirmed all 4 open questions against actual PHP. Added a "BA Confirmation" section to `02-implementation-notes.md` and fixed TC-5 and TC-7 in `03-test-plan.md`. Key finding: `subRunStreamEvents()` is missing the result summary column entirely (not just missing truncation); TC-5 had the wrong scan target (command.md → outbox/*.md); the AC-7 banner should fire when `getenv('COPILOT_HQ_ROOT') === false` even when the fallback path resolves; Session Health is confirmed on the main `run()` page with `$parity` already in scope but unused in the health table. Commit: `5813d99f8`.

## Next actions
- **dev-forseti** can implement without further BA clarification — all 4 questions are resolved in `02-implementation-notes.md` with exact PHP snippets.
- **qa-forseti** should use the updated `03-test-plan.md` for TC-5 and TC-7 verification commands.
- **pm-forseti**: note the `subRunStreamEvents()` gap is larger than "truncation only" — the Summary column is completely absent and must be added as a new 5th column. Dev estimate may need adjustment.

## Blockers
- None.

## Needs from CEO
- None.

## Findings summary

| Question | Answer |
|---|---|
| AC-3 glob target | `sessions/*/outbox/*.md` (NOT `inbox/*/command.md`) — command.md never has blocked/needs-info |
| AC-2 truncation field | JSON-encode `$data` minus `rc` key; truncate to 120 chars; add as 5th column after RC badge |
| AC-7 banner semantics | Show when `getenv('COPILOT_HQ_ROOT') === false`; show even when fallback path resolves |
| AC-5 Session Health placement | Main `run()` page health table — `$parity` already destructured at line 365, just unused in health rows |

## ROI estimate
- ROI: 40
- Rationale: Prevents dev-forseti from implementing AC-3 against the wrong file target (command.md) and AC-2 without the required column — both would cause QA BLOCK cycles. Resolving now saves at least one re-implementation round.
