# Feature Brief: Arcana — Borrow an Arcane Spell

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P3
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-spellcasting

## Description
Implement Borrow an Arcane Spell action (REQs 1616–1618). Requires access to a
spellbook containing the spell; Arcana trained; exploration activity (10 min).
DC = spell's level via spell-level DC table. On success, add spell to daily
prepared list for that preparation. Dependencies on spellcasting system.

Also covers Recall Knowledge for Arcana-applicable creature types
(Constructs, Dragons, Elementals, Magical Beasts) — coordinated with
dc-cr-creature-identification.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1616, 1617, 1618
- See `runbooks/roadmap-audit.md` for audit process.
