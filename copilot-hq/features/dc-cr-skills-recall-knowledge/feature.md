# Feature Brief: Recall Knowledge Skill Action

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment

## Description
Implement the Recall Knowledge skill action as a proper 1-action encounter handler
(REQs 1591–1594, 2329). Currently `recall_knowledge` is registered in
CanonicalActionRegistryService but routes to generic applyCharacterStateChanges
with no DC resolution or skill routing logic.

Required:
1. Wire recall_knowledge into EncounterPhaseHandler::processIntent() as a 1-action
   secret skill check
2. Route to correct skill based on topic (Arcana/Nature/Occultism/Religion/Society/
   Crafting/Medicine/Lore — see dc-cr-creature-identification for creature routing)
3. DC resolution: simple DC (GM-set by obscurity); level-based for creatures/hazards;
   rarity adjustment applied (see dc-cr-dc-rarity-spell-adjustment)
4. Degree-of-success outcomes: crit=info+bonus detail, success=info,
   fail=nothing, crit fail=false information

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1591, 1592, 1593, 1594, 2329
- See `runbooks/roadmap-audit.md` for audit process.
