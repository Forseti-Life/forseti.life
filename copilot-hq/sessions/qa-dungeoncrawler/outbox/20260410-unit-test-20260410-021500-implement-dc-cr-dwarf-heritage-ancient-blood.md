# Verification Report — dc-cr-dwarf-heritage-ancient-blooded

- Status: blocked
- Summary: Verified dc-cr-dwarf-heritage-ancient-blooded implementation (dev outbox: sessions/dev-dungeoncrawler/outbox/20260410-021500-implement-dc-cr-dwarf-heritage-ancient-blooded.md). Data layer PASS: `ancient-blooded-dwarf` heritage is present in `/ancestries/dwarf` API with correct `id`, `granted_abilities: [call-on-ancient-blood]`, and full `special.reaction` block (trigger: saving_throw_before_roll_magical, effect: +1 circumstance, duration: end_of_turn, once_per_turn). Heritage ID fix confirmed. Server-side ancestry validation confirmed (`isValidHeritageForAncestry()` at CharacterApiController:100 blocks cross-ancestry assignment). Character ownership ACL confirmed (lines 148, 258 in CharacterApiController). BLOCK on three gaps: (1) `ReactionHandler::executeReaction()` has no `case 'call-on-ancient-blood'`—its `switch($reaction_type)` falls to `default: return ['error' => "Unknown reaction type: $reaction_type"]`—making TC-HAB-003 through TC-HAB-007 and TC-HAB-009/013 unreachable at runtime; (2) no heritage lock code found for post-wizard-completion mutation prevention (TC-HAB-014); (3) feature AC specifies a dedicated POST route `/dungeoncrawler/character/{id}/heritage` with `_csrf_request_header_mode: TRUE` that does not exist—heritage writes go through `/characters/create/step/{step}/save` which has `_permission` but no CSRF, and `/api/character/save` which has both. Site audit 20260410-090552 reused: 0 violations.

## Test results

| TC | Description | Result | Evidence |
|---|---|---|---|
| TC-HAB-001 | `ancient-blooded-dwarf` present in `/ancestries/dwarf` heritages | PASS | API response confirmed, `id` matches, `granted_abilities` present |
| TC-HAB-002 | `call-on-ancient-blood` in `granted_abilities`; `special.reaction` block correct | PASS | `trigger: saving_throw_before_roll_magical`, `bonus_value: 1`, `bonus_type: circumstance`, `duration: end_of_turn`, `once_per_turn: true` |
| TC-HAB-003 | Reaction prompt fires on magical save before roll | BLOCK | ReactionHandler has no case for `call-on-ancient-blood`; default returns error |
| TC-HAB-004 | +1 circumstance bonus applies to triggering save | BLOCK | Runtime not reached (same root cause) |
| TC-HAB-005 | Bonus expires at end of turn | BLOCK | Runtime not reached |
| TC-HAB-006 | Reaction consumed; once-per-turn enforced | BLOCK | Runtime not reached |
| TC-HAB-007 | Reaction not prompted on non-magical save | BLOCK | Runtime not reached (no trigger filter logic) |
| TC-HAB-008 | Non-Dwarf ancestry cannot select `ancient-blooded-dwarf` | PASS | `isValidHeritageForAncestry()` at CharacterApiController:100 confirmed |
| TC-HAB-009 | Circumstance bonus non-stacking with other circumstance bonuses | BLOCK | Runtime not reached |
| TC-HAB-010 | Data: trigger specifies `saving_throw_before_roll_magical` (non-magical excluded) | PASS | `special.reaction.trigger` confirmed in API and CharacterManager |
| TC-HAB-011 | Anonymous user cannot select heritage | PASS | Route `_permission: 'create dungeoncrawler characters'`; CharacterApiController line 55 enforces login |
| TC-HAB-012 | Server-side rejection for mismatched ancestry | PASS | `isValidHeritageForAncestry()` returns false, controller returns 400 error |
| TC-HAB-013 | Once-per-turn enforcement persists across multiple saves in same turn | BLOCK | Runtime not reached |
| TC-HAB-014 | Heritage immutable post-wizard-completion | FAIL | No lock code found in CharacterApiController; `wizard_complete=true` sets `status=1` but no block on heritage field mutation after publish |
| TC-HAB-015 | Reaction trigger ACL: only character owner or GM/admin | PARTIAL | No dedicated reaction route—reaction not dispatched; ownership ACL confirmed on character save routes |

## Blockers

1. **Runtime reaction not dispatched** — `ReactionHandler.php:233–251` switch has cases only for `attack_of_opportunity` and `shield_block`. No `call-on-ancient-blood` case added. Feature goal: "The AI combat engine must recognize circumstance bonuses on saving throws and trigger the reaction prompt when the condition is met." Fix: add case in `executeReaction()` for `call-on-ancient-blood` trigger type and wire saving-throw resolve path to check `reaction_available` for characters with this reaction granted.
2. **Heritage lock missing** (TC-HAB-014) — When `wizard_complete=true` or character `status=1`, no code in `CharacterApiController::saveCharacter()` or `::saveStep()` prevents overwriting the `heritage` field. Fix: add guard in saveCharacter/saveStep to reject heritage changes when character status=1.
3. **Missing dedicated heritage route** — Feature AC specifies `POST /dungeoncrawler/character/{id}/heritage` with `_csrf_request_header_mode: TRUE`. This route is absent entirely. Heritage saves fall through to `/api/character/save` (CSRF present: PASS) or `/characters/create/step/{step}/save` (no CSRF: PARTIAL FAIL vs AC). Dev should either add the dedicated route per AC or update feature AC to reflect the actual route with PM sign-off.

## Next actions
- Dev to add `case 'call-on-ancient-blood':` to `ReactionHandler::executeReaction()` and wire saving-throw resolution to dispatch this reaction (TC-HAB-003–007, TC-HAB-009, TC-HAB-013)
- Dev to add heritage immutability guard in CharacterApiController when `status=1` (TC-HAB-014)
- Dev or PM to resolve heritage route discrepancy: add `/dungeoncrawler/character/{id}/heritage` route per feature AC, or update feature AC with PM sign-off
- QA to re-run targeted verification once dev ships fixes

## Needs from Supervisor
- None (all findings are concrete and actionable)

## Site audit
- Audit ID: 20260410-090552 (reused — no new routes added by this implementation)
- Result: 0 violations, 0 permission expectation failures

## ROI estimate
- ROI: 35
- Rationale: This is the first heritage-specific reaction in the dungeoncrawler system; unblocking it validates the pattern for all future heritage reactions. Missing runtime wiring means the advertised core mechanic doesn't function at all for any heritage with a reaction.
