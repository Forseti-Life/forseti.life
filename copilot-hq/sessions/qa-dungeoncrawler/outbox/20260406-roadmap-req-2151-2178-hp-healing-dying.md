Now I have enough data. Let me write the verification report.

- Status: blocked
- Summary: Verified reqs 2151–2178 (HP, Healing, and Dying) against production. 22 of 28 test cases pass. Several inbox-anticipated failures are already fixed in current code (recovery DC = 10+dying ✓, stabilize stays at 0 HP ✓, wounded+1 on stabilize ✓, doomed subtracted from threshold ✓, unconscious −4 AC/Perception/Reflex in catalog ✓, massive damage check in applyDamage ✓). Two high-severity defects found: DEF-2151 — `HPManager::applyDamage` stores `$new_hp = $base_hp - $remaining_damage` without clamping to 0, so HP can be negative in the DB; DEF-2154/2155 — `applyDamage` calls `applyCondition('dying', 1)` directly (bypassing `applyDyingCondition`), so normal kills skip the wounded adjustment entirely, and crit kills result in dying 3 instead of 2 because `resolveAttack` then calls `applyDyingCondition(2)` on top of the existing dying 1 (applyCondition accumulates: 1+2=3). Two medium gaps: GAP-2166 — doomed instant-death is only evaluated at `processDying` (start of next turn), not at dying-application time; GAP-2178 — `regeneration_bypassed` flag in entity_data is read but never auto-set by `applyDamage`/`resolveAttack` when fire/acid damage is dealt to a regenerating creature. Committed `c42dbb089`.

## Next actions
- Route DEF-2151 to dev-dungeoncrawler: add `$new_hp = max(0, $base_hp - $remaining_damage)` in `HPManager::applyDamage`.
- Route DEF-2154/2155 to dev-dungeoncrawler: remove direct `applyCondition('dying', 1)` from `applyDamage` — replace with call to `applyDyingCondition($participant_id, 1, $encounter_id, FALSE)`. Remove redundant `applyDyingCondition` call in `resolveAttack` for crits (let `applyDamage` route all dying through `applyDyingCondition` with is_critical flag passed in).
- Route GAP-2166 and GAP-2178 as medium-severity follow-ups.
- Continue with next inbox items: `20260406-roadmap-req-2179-2189-actions` (ROI 17), `20260406-unit-test-20260406-impl-flat-check-system` (ROI 17).

## Blockers
- DEF-2151: HP floor missing — affects every damage application.
- DEF-2154/2155: Dying application bypasses wounded adjustment for all normal kills; crits result in dying 3 instead of 2+wounded.

## ROI estimate
- ROI: 75
- Rationale: HP, dying, and wounded are the core survival loop for every encounter — defects here affect every combat interaction. DEF-2154/2155 causes incorrect dying values on every kill, which cascades into wrong recovery check DCs, wrong death thresholds, and broken resurrection-point tracking.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-roadmap-req-2151-2178-hp-healing-dying
- Generated: 2026-04-06T23:20:54+00:00
