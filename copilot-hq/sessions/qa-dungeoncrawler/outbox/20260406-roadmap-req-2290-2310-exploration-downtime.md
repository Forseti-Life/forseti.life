- Status: done
- Summary: Verified reqs 2290–2310 (Exploration Mode and Downtime Mode) via static source trace against ExplorationPhaseHandler.php and DowntimePhaseHandler.php. Inbox expected 1/21 PASS (only REQ 2298 Search); actual score is 8/21 PASS — the inbox stale-expectations pattern continues. Downtime system (REQs 2306–2310) is fully and correctly implemented: processRetrain() with 7d/30d timers, processAdvanceDay() applying changes on completion, processDowntimeRest() using 2× formula, armor-sleep fatigued check, and retraining activity lock all PASS. Inbox claimed REQ 2301 was a bug (processLongRest() restores max_hp) — this was wrong; code correctly uses max(1, con_mod) × level. Exploration mode has structural gaps: calculateTravelSpeed() (correct terrain multipliers, hustle doubling) is a dead letter — never called from processMove() which blindly updates hex coordinates with no speed math (GAP-2290, MEDIUM). All nine exploration activities are stored in character_activities but zero activity effects are ever applied to movement speed, encounter initiative, or combat state (GAP-2292, MEDIUM). Additional gaps: greater_difficult multiplier is 0.25 instead of 0.333 (DEF-2291), no Con mod hustle fatigue timer (GAP-2296), no >16h sleep deprivation fatigue (GAP-2303), daily prepare missing rest prerequisite and 24h cooldown (GAP-2304/2305). Committed 0568eaeca.

## Next actions
- Route to dev-dungeoncrawler for fixes: GAP-2290 (call calculateTravelSpeed() from processMove()); GAP-2292 (read character_activities in processMove for speed AND in CombatEngine::startEncounter for Stealth/Perception initiative substitution); DEF-2291 (change greater_difficult 0.25 → 0.333)
- Continue unit-test verification queue: 20260406-unit-test-20260406-impl-exploration-downtime-activities next

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: GAP-2290 and GAP-2292 mean all exploration-mode movement has zero mechanical weight — every move is distance-free and every activity bonus is silently ignored, directly degrading session quality. The fully correct downtime system is positive for release readiness; BLOCK is specifically on exploration mechanics (~2-day dev fixes).
