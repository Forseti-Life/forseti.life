# Feature Brief: Decipher Writing, Identify Magic, Learn a Spell

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: in_progress
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-dc-rarity-spell-adjustment, dc-cr-spellcasting

## Goal

Implement the three knowledge-acquisition skill actions: Decipher Writing (skill-based, reading obscure/magical texts), Identify Magic (10-minute activity, determines spell or item properties), and Learn a Spell (adds spell to repertoire/spellbook at material cost).

## Source reference

> "You can attempt to decipher or identify magic from writing or an item using the appropriate skill." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`KnowledgeActionResolver`: Decipher Writing: skill varies (Arcana for arcane, Occultism for occult, Religion for divine, Nature for primal); DC = 10 simple / 20 scholarly / 30 magical / 40 esoteric; action = 10 minutes. Identify Magic: same skill routing; DC = 20 + spell/item level; reveals school, traits, activation, and effects on crit success vs fewer details on success. Learn a Spell: same skill routing; DC = 20 + spell level; material cost = level × 10gp; success adds spell to repertoire/spellbook permanently; crit success costs half; failure costs half materials; crit failure costs all materials.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Learn a Spell requires character ownership; material cost deducted server-side atomically
- CSRF expectations: all POST/PATCH knowledge-action routes require `_csrf_request_header_mode: TRUE`
- Input validation: item/spell id validated as valid entity; material gold deducted atomically with spell grant; skill id validated against correct tradition routing
- PII/logging constraints: no PII logged; character id + spell/item id + gold delta + knowledge tier only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1574, 1575, 1576, 1577, 1578, 1583, 1584, 1585, 1586, 1587, 1588, 1589, 1590
- See `runbooks/roadmap-audit.md` for audit process.
