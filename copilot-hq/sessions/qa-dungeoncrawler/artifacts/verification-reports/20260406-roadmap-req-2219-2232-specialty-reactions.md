# Verification Report: Specialty Basic Actions + Reactions in Encounters (Reqs 2219–2232)
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: BLOCK

## Scope
PF2e Specialty Basic Actions and Reactions in Encounters (Ch9, reqs 2219–2232, 14 requirements). Primary source: `EncounterPhaseHandler.php`.

## KB reference
None found relevant. All 14 actions were expected NOT implemented by the inbox; all 14 are in fact implemented with case handlers (identical pattern to 2190–2218 where inbox annotations were stale).

## Dev outbox reference
No specific dev outbox for this inbox item. Related: `sessions/dev-dungeoncrawler/outbox/20260406-impl-specialty-actions-reactions.md` (referenced in regression checklist as pending targeted regression check).

## Source files inspected
- `EncounterPhaseHandler.php` — getLegalIntents(), processIntent() switch (cases: arrest_fall, avert_gaze, burrow, fly, grab_edge, mount, dismount, point_out, raise_shield, attack_of_opportunity, shield_block)
- `CombatEngine.php` — resolveAttack() skip_map handling (line 592–601), DB attacks_this_turn update (line 795)

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2219-P: Arrest a Fall in getLegalIntents | STATIC-PASS | 'arrest_fall' in getLegalIntents() ✓ |
| TC-2219-P: Requires fly Speed; Acrobatics DC 15; reaction consumed | STATIC-PASS | `if (empty($entity_af['fly_speed'])) → error`; Acrobatics roll vs DC 15; `reaction_available=FALSE` ✓ |
| TC-2219-N: No fly Speed → rejected | STATIC-PASS | fly_speed check enforced ✓ |
| TC-2219-P: Four-degree outcomes (crit success=no damage, fail=partial, crit fail=full/heavy) | STATIC-PASS | degree determines `$damage_af` via floor/ceil math; falls computed by `$feet_fallen` ✓ |
| TC-2220-P: Avert Gaze in getLegalIntents; sets avert_gaze_active | STATIC-PASS | 'avert_gaze' in getLegalIntents(); `entity_data_ag['avert_gaze_active'] = TRUE` stored; 1 action ✓ |
| TC-2220-N: Expires at start of next turn | STATIC-PASS | End-of-turn cleanup in processEndTurn: `$entity_fly['avert_gaze_active'] = FALSE` ✓ |
| TC-2221-P: Burrow in getLegalIntents; requires burrow_speed > 0 | STATIC-PASS | 'burrow' in getLegalIntents(); `if ($burrow_speed <= 0) → error` ✓ |
| TC-2221-P: Tags entity as underground; conditional tunnel | STATIC-PASS | `entity_data_b['underground'] = TRUE`; `creates_tunnel` check ✓ |
| TC-2221-N: No burrow_speed → rejected | STATIC-PASS | burrow_speed check enforced ✓ |
| TC-2222-P: Fly in getLegalIntents; requires fly_speed > 0 | STATIC-PASS | 'fly' in getLegalIntents(); `if ($fly_speed <= 0) → error` ✓ |
| TC-2222-P: Falls at end of turn if no Fly used | STATIC-PASS | End-of-turn: `if (airborne && !fly_used_this_turn) → applyFallDamage`; `fly_used_this_turn=FALSE` cleared ✓ |
| TC-2222-N: Upward movement costs double (difficult terrain) | STATIC-PASS | `upward_movement = TRUE` flag passed to processStride via `movement_cost_multiplier` ✓ |
| TC-2223-P: Fly 0 = hover; 1 action; stays airborne | STATIC-PASS | `if ($fly_distance === 0) → hovered=TRUE, airborne=TRUE, fly_used_this_turn=TRUE`; actions_remaining -1 ✓ |
| TC-2224-P: Grab an Edge in getLegalIntents; Reflex DC 15; clinging=TRUE on success | STATIC-PASS | 'grab_edge' in getLegalIntents(); Reflex roll vs DC 15; `entity_ge['clinging'] = TRUE`; reaction consumed ✓ |
| TC-2224-P: Reaction spent check | STATIC-PASS | `reaction_available === FALSE` guard at top ✓ |
| TC-2225-P: Mount in getLegalIntents; adjacency check | STATIC-PASS | 'mount' in getLegalIntents(); `if ($dist_m > 1) → error` ✓ |
| TC-2225-N: Size check (≥1 size larger) | GAP | No size comparison between actor and mount target. REQ says target must be ≥1 size larger. See GAP-2225. |
| TC-2225-N: Willing check | GAP | No `willing` flag check on mount target entity. See GAP-2225. |
| TC-2226-P: Point Out in getLegalIntents; undetected→hidden for allies | STATIC-PASS | 'point_out' in getLegalIntents(); iterates allies; `if (state === 'undetected' or 'unnoticed') → 'hidden'` ✓ |
| TC-2226-N: Cannot make hidden → observed via Point Out | STATIC-PASS | Only upgrades 'undetected'/'unnoticed' → 'hidden'; no upgrade from 'hidden' ✓ |
| TC-2227-P: Raise a Shield in getLegalIntents; finds held shield; sets shield_raised | STATIC-PASS | 'raise_shield' in getLegalIntents(); findHeldShield(); `shield_raised=TRUE`; `shield_raised_ac_bonus` stored ✓ |
| TC-2227-N: No shield in hand → rejected | STATIC-PASS | `if (!$shield_rs) → error "No shield in hand."` ✓ |
| TC-2227-N: Broken shield → rejected | STATIC-PASS | `if (!empty($shield_rs['broken'])) → error "Shield is broken."` ✓ |
| TC-2227-N: Shield AC bonus expires at start of next turn | STATIC-PASS | End-of-turn cleanup: `$entity_fly['shield_raised'] = FALSE` ✓ |
| TC-2228-P: AoO in getLegalIntents; class feature check | STATIC-PASS | 'attack_of_opportunity' in getLegalIntents(); `if (!in_array('attack_of_opportunity', $class_features)) → error` ✓ |
| TC-2228-N: Non-fighter (no class feature) → rejected | STATIC-PASS | class_features check enforced ✓ |
| TC-2229-P: AoO crit + manipulate trigger → disrupt | STATIC-PASS | `if ($aoo_result['degree'] === 'critical_success' && $trigger_type === 'manipulate') → disrupted=TRUE` ✓ |
| TC-2229-N: AoO hit (non-crit) on manipulate → NOT disrupted | STATIC-PASS | Only crit_success triggers disruption ✓ |
| TC-2230-P: AoO does not apply MAP (skip_map) | STATIC-PASS | `weapon['skip_map']=TRUE` → CombatEngine uses 0 MAP penalty; DB attacks_this_turn not incremented ✓ |
| TC-2230-N: AoO should NOT change game_state attacks_this_turn | DEF-FAIL | Line 1180: `$game_state['turn']['attacks_this_turn'] = max(0, (existing ?? 1) - 1)` DECREMENTS the count instead of leaving it unchanged. processStrike does NOT increment game_state attacks_this_turn (it's DB-only via CombatEngine), so the -1 creates an off-by-one. See DEF-2230. |
| TC-2231-P: Shield Block in getLegalIntents; damage split via Hardness | STATIC-PASS | 'shield_block' in getLegalIntents(); `$reduced = incoming - hardness`; `$shield_takes = floor($reduced / 2)`; `$entity_takes = $reduced - $shield_takes` ✓ |
| TC-2231-P: Shield at 0 HP → broken flag | STATIC-PASS | `if ($shield_sb['hp'] <= 0) → broken=TRUE, shield_raised=FALSE` ✓ |
| TC-2231-N: Broken shield cannot be used for Shield Block | STATIC-PASS in raise_shield; PARTIAL in shield_block | raise_shield rejects broken shield ✓. shield_block only checks `shield_raised`; does not re-check `broken` flag at Shield Block time. Minor gap. |
| TC-2232-P: Shield must be raised to Shield Block | STATIC-PASS | `if (empty($entity_sb['shield_raised'])) → error "Shield must be raised to use Shield Block."` ✓ |
| TC-2232-N: Shield not raised → Shield Block rejected | STATIC-PASS | guard enforced ✓ |

## Defects / Gaps

### DEF-2230 (MEDIUM): AoO decrements game_state attacks_this_turn instead of leaving it unchanged
- **File:** `EncounterPhaseHandler.php`, line 1180
- **Expected (REQ 2230):** AoO does not count toward MAP. game_state `attacks_this_turn` should be unchanged after AoO.
- **Actual:** `$game_state['turn']['attacks_this_turn'] = max(0, ($game_state['turn']['attacks_this_turn'] ?? 1) - 1);`
  - processStrike only updates the DB participant record (via CombatEngine). It does NOT touch `game_state['turn']['attacks_this_turn']`.
  - The -1 at line 1180 thus decrements an already-unchanged value, net result: attacks_this_turn = N-1 instead of N.
  - Example: fighter with 1 prior strike (attacks_this_turn=1) takes AoO → game_state becomes 0 → next strike on their turn has no MAP (wrong, should be MAP -5).
- **Fix:** Remove line 1180 entirely. The comment at line 1179 says "Do NOT decrement" but the code does exactly that.

### GAP-2225 (LOW): Mount missing size and willing checks
- **File:** `EncounterPhaseHandler.php`, case 'mount'
- **Expected (REQ 2225):** Target must be willing AND ≥1 size category larger.
- **Actual:** Only adjacency (dist ≤ 1) is checked. No size comparison, no willing flag check.
- **Severity:** Low — only affects edge-case rejection; mechanics otherwise work.
- **Fix:** Load target entity_data, check `size` field comparison; check `is_willing` or `attitude` field.

### MINOR-2231-shield-block: Shield Block doesn't re-check broken flag at use time
- **File:** `EncounterPhaseHandler.php`, case 'shield_block'
- **Note:** The `raise_shield` case already rejects broken shields. If a shield breaks mid-combat (during the same turn as a prior Shield Block), a second Shield Block attempt would pass the `shield_raised` check (it may still be TRUE) but the shield is now broken. Low severity; edge case.

## Summary
12/14 PASS, 1 medium defect (DEF-2230), 1 low gap (GAP-2225), 1 minor gap (shield_block broken re-check).

**Inbox "Expected: NOT implemented" was incorrect** — all 14 specialty actions are implemented with full case handlers, mirroring the same pattern found in 2190–2218.

The critical finding is DEF-2230: the AoO "do not count toward MAP" implementation has an inverted sign error in EncounterPhaseHandler (line 1180). The MAP suppression is correctly handled in CombatEngine (resolveAttack), but the game_state counter repair in EncounterPhaseHandler is broken — it decrements when it should leave unchanged.

Verdict: **BLOCK** — DEF-2230 corrupts MAP state for the fighter's subsequent turn actions after any AoO.
