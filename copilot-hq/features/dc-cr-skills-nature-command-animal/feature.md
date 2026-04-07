# Feature Brief: Nature — Command an Animal

- Work item id: dc-cr-skills-nature-command-animal
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Nature (Wis)
- Depends on: dc-cr-skill-system, dc-cr-npc-system

## Goal

Implement the Nature skill's Command an Animal action (Trained) and Identify Magic (Trained, primal magic only), with integration into animal companion management for ranger/druid characters.

## Source reference

> "Nature measures your understanding of the natural world." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`NatureActionResolver`: Command an Animal: standard action; DC = 15 + animal's level (or Will DC if trained/smart); success = animal follows command for 1 minute; crit success = no check needed for that command type again; failure = no response; crit failure = hostile. Animal companion integration: if animal is character's companion, base DC is 10 (reduced). Identify Magic (Primal): 10-minute activity; identifies primal spell/item properties; DC = 20 + item/spell level. Recall Knowledge (Nature): DC by creature type (beast/plant/fungus/animal).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Command an Animal requires encounter or companion ownership
- CSRF expectations: all POST/PATCH skill/nature routes require `_csrf_request_header_mode: TRUE`
- Input validation: animal target id validated as encounter participant or character's companion; magic item id validated as valid entity for identification
- PII/logging constraints: no PII logged; character id + animal id + command type + outcome only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1705, 1706, 1707, 1708, 1709, 1710, 1711, 1712, 1713, 1714
- See `runbooks/roadmap-audit.md` for audit process.
