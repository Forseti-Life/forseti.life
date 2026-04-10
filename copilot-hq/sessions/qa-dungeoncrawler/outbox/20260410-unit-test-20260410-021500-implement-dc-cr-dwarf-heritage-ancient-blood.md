I have enough evidence. Let me write the BLOCK verdict and update the checklist.

Now update the regression checklist entry:

Committed `f73575471`.

---

- Status: blocked
- Summary: Verified dc-cr-dwarf-heritage-ancient-blooded. Data layer PASS: `ancient-blooded-dwarf` appears in `/ancestries/dwarf` with correct `granted_abilities`, `special.reaction` block (trigger, bonus, once_per_turn, duration), heritage-ID fix confirmed, server-side ancestry validation and ownership ACL confirmed. BLOCK on three gaps: (1) `ReactionHandler::executeReaction()` has no `case 'call-on-ancient-blood'` — 7 runtime TCs (HAB-003–007, 009, 013) fall to default error; (2) no heritage immutability guard after `wizard_complete=true` — TC-HAB-014 FAIL; (3) feature AC requires dedicated `POST /dungeoncrawler/character/{id}/heritage` route with CSRF — route is absent, step-save path lacks `_csrf_request_header_mode`. Commit: `f73575471`.

## Next actions
- Dev to add `case 'call-on-ancient-blood':` to `ReactionHandler.php` and wire saving-throw resolution to dispatch it
- Dev to add heritage lock guard in `CharacterApiController` when `status=1`
- Dev or PM to resolve heritage route discrepancy (add route per AC, or PM updates AC)
- QA re-runs verification after fixes

## Blockers
- None on QA side — findings are concrete and fully documented

## ROI estimate
- ROI: 35
- Rationale: First heritage reaction in the system; unblocking validates the pattern for all future heritage reactions. Missing runtime wiring means the core advertised mechanic is completely non-functional.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-021500-implement-dc-cr-dwarf-heritage-ancient-blood
- Generated: 2026-04-10T09:44:59+00:00
