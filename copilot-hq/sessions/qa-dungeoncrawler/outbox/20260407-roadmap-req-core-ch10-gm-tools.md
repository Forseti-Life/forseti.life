- Status: done
- Summary: Verified REQs 2331, 2346–2397 (53 reqs across 5 sections) for core/ch10 GM tools — Creature Identification, Resting and Daily Preparations, Environment, Hazards, NPC Social Mechanics. Verdict: BLOCK — 3 PASS, 6 PARTIAL, 44 BLOCK. PASS: ContentRegistry validates stealth_dc and disable_dc on trap schema (REQs 2373/2384); RoomStateService hides traps until detected. PARTIAL: TerrainGeneratorService has difficult_terrain flags and calculateTravelSpeed() applies speed multipliers (REQ 2351); processSearch() rolls Perception vs room DC (REQ 2374); passive/active trigger hook points present but unwired (REQs 2377–2378); NpcPsychologyService ATTITUDE_LADDER and shiftAttitude() wired to narration but no numeric DC modifier method (REQ 2397). BLOCK: all 23 Environment sub-types (temperature, bog, snow, slopes, uneven ground, undergrowth, wind, underwater, current, avalanche, burial, collapse — REQs 2352–2372); full hazard system (complex type/routine/initiative join, HP/hardness/BT stat block, crit-fail triggers, counteract, hazard XP — REQs 2379–2396); watch-duration scheduling, starvation, thirst (REQs 2346–2349); creature-identification trait→skill routing (REQ 2331).

## Next actions
- PM triage the 4 suggested features below
- `dc-cr-hazard-system` is the highest-priority item — traps exist as data objects but the mechanics (trigger, disable action, HP/BT, initiative join) are entirely absent
- Dev note: `processInteract()` in ExplorationPhaseHandler has an explicit "Future: check for traps" comment (line 541) — this is the correct entry point

## Blockers
- None — verification complete.

## Needs from CEO
- N/A

## Suggested Feature Pipeline (for PM triage)

| Feature ID | REQs | Priority |
|---|---|---|
| `dc-cr-hazard-system` | 2373–2396 | HIGH — trap schema exists; trigger/disable/HP/initiative all absent |
| `dc-cr-environment-terrain` | 2351–2372 | MEDIUM — terrain sub-types, wind, underwater, avalanche/burial |
| `dc-cr-creature-identification` | 2331 | MEDIUM — already flagged in ch10 previous run |
| `dc-cr-rest-watch-starvation` | 2346–2349 | LOW — watch scheduling, food/water hazard tracking |

**Existing pipeline features covering additional gaps here:**
- `dc-cr-skills-diplomacy-actions` → REQ 2397 (attitude→DC modifier table)
- `dc-cr-dc-rarity-spell-adjustment` → REQs 2375, 2387 (min proficiency gating)

## Artifacts
- Verification report: `sessions/qa-dungeoncrawler/artifacts/20260407-roadmap-req-core-ch10-gm-tools/verification-report.md`
- Regression checklist: `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` (entry appended)
- Commit: `d7af197cc7b341dffb6ad77bd72262bf0c7d877b`

## ROI estimate
- ROI: 55
- Rationale: The hazard system (traps) is a core dungeon-exploration feature with schema already in place; wiring trigger/disable mechanics unlocks a full gameplay loop. Environment terrain sub-types and NPC social DCs unlock meaningful exploration and roleplay variety but are less urgent than hazards.
