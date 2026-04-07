# Feature Brief: Creature Identification (Recall Knowledge Routing)

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P2
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment

## Description
Implement creature-trait → Recall Knowledge skill routing (REQ 2331). The
`recall_knowledge` action is registered in CanonicalActionRegistryService but routes
to generic applyCharacterStateChanges with no DC resolution or skill selection.

Routing table to implement:
- Aberration, Ooze, Undead → Occultism
- Animal, Beast, Fungus, Plant → Nature
- Construct, Dragon, Elemental → Arcana
- Celestial, Fiend → Religion
- Humanoid → Society
- Other → GM discretion

Also wire DC resolution: simple DC based on general recall; level-based for
creatures/hazards; rarity adjustment applied. Covers REQs 2329 and 2331.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2329, 2331
- See `runbooks/roadmap-audit.md` for audit process.
