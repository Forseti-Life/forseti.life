# Feature Brief: Wizard Class Mechanics

- Work item id: dc-cr-class-wizard
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch03
- Category: class
- Created: 2026-04-07
- DB sections: core/ch03/Wizard
- Depends on: dc-cr-character-class, dc-cr-spellcasting

## Goal

Implement the Wizard class with arcane spellbook (prepared casting from written spells), arcane school subclass (grants extra spell slot + school spells), arcane thesis (4 research paths), and Drain Bonded Item.

## Source reference

> "Wizards study arcane magic, gaining incredible power through years of study and practice." (Chapter 3: Classes, PF2E Core Rulebook)

## Implementation hint

Spellbook: `character.spellbook_spells[]` list; `WizardService::learnSpell(character, spell_id)` adds after Crafting/Arcana check + material cost (level × 10gp). Prepared slots: `prepared_spells[level][slot]` must reference a spellbook spell. Arcane School: `wizard_school` enum; grants `school_slot` (extra slot per level) and 2 school focus spells. Arcane Thesis: `arcane_thesis` enum (experimental_spellshaping/improved_familiar_attunement/spell_blending/spell_substitution) — each modifies daily prep behavior. Drain Bonded Item: once/day, recall a spell just cast; tracked as boolean reset on daily prep.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; spellbook access and Drain Bonded Item require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/wizard routes require `_csrf_request_header_mode: TRUE`
- Input validation: spellbook spell ids validated as arcane spells; prepared slot entries validated against spellbook contents; Drain Bonded Item validates spell was cast this encounter
- PII/logging constraints: no PII logged; character id + spell id + slot level only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
