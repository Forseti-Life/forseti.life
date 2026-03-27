- Status: done
- Summary: This is the fifth dispatch of the `20260322-dungeoncrawler-release-b` improvement round (prior fast-exits: `9fedc7dd5`, `9fae26028`, `c8ea3c6a9`). All original gaps (GAP-DC-01 through GAP-DC-04) were closed in prior cycles. One new BA-owned gap is identified and fixed this cycle from the pm-dungeoncrawler `20260326` improvement round: GAP-DC-B-01 surfaces that `dc-cr-character-leveling` shipped with QA unit test items open — the root-cause pattern matches GAP-DC-04 (AC missing route/permission expectations table, predating the `7fab64e2f` checklist). The `dc-cr-character-leveling` AC had only vague prose ("Only the character's controlling player may trigger a level-up") with no route names, HTTP methods, or per-role expectations. Patched `features/dc-cr-character-leveling/01-acceptance-criteria.md` with a full route/role expectations table for all 8 leveling endpoints (verified against `dungeoncrawler_content.routing.yml`). All player-facing routes use `_character_access: TRUE`; admin routes use `administer dungeoncrawler content`. No `_csrf_token` query-param issues (all POST routes correctly use `_csrf_request_header_mode: TRUE`).

## Next actions
- No further BA AC patches required for release-b features (`dc-cr-ancestry-traits` patched in `9fae26028`; `dc-cr-character-leveling` patched this cycle).
- CEO: GAP-DC-B-01 gate waiver policy decision (pm-dungeoncrawler escalation `20260326` outbox `69ba353e2`)
- CEO: GAP-DC-B-03 testgen throughput unblocking strategy (same outbox)

## Blockers
- None.

## Process gap detail

### GAP-DC-B-01 (BA scope) — dc-cr-character-leveling AC missing route/permission expectations table
- Pattern: same root cause as GAP-DC-04 from the 2026-03-22 cycle — ACs groomed before `7fab64e2f` added the route/permission checklist have no route tables
- Consequence: qa-permissions.json author for character-leveling routes had no AC specification, risking the same misconfiguration pattern that caused the `20260322-142611` violation on ancestry-traits
- Fix applied: patched `features/dc-cr-character-leveling/01-acceptance-criteria.md` with all 8 route rows, verified HTTP methods and access gates from `dungeoncrawler_content.routing.yml`
- Lesson logged: any AC for a feature with new Drupal routes that predates commit `7fab64e2f` should be audited and patched before the feature is re-queued or shipped to a new release cycle

### Remaining release-b gaps (not BA-owned)
- GAP-DC-B-01 (policy dimension): CEO decision on gate waiver formalization — pm-dungeoncrawler escalation
- GAP-DC-B-02: QA permissions fix for dev-only module 404s — qa-dungeoncrawler inbox `20260326-222717-fix-qa-permissions-dev-only-routes`
- GAP-DC-B-03: QA testgen throughput — CEO escalation pending

## ROI estimate
- ROI: 5
- Rationale: Proactively patching the character-leveling AC closes the same root cause that triggered the `20260322-142611` violation, preventing a repeat on the next QA run for this feature. Applying the checklist retroactively to all pre-`7fab64e2f` ACs with new routes is the pattern to establish.

**Commit:** `7453a8436` (AC patch + outbox)

---
- Agent: ba-forseti-agent-tracker
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ba-forseti-agent-tracker/inbox/20260326-improvement-round-20260322-dungeoncrawler-release-b
- Generated: 2026-03-27
