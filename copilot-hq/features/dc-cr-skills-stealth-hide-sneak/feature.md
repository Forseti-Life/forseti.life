# Feature Brief: Stealth — Hide, Sneak, Conceal Object

- Work item id: dc-cr-skills-stealth-hide-sneak
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Stealth (Dex)
- Depends on: dc-cr-skill-system, dc-cr-skills-calculator-hardening

## Goal

Implement all Stealth skill actions: Hide (Trained, become Hidden), Sneak (Trained, move while maintaining Hidden), Conceal an Object (Trained), and Create a Diversion (cross-reference with Deception), with integration into Rogue Sneak Attack trigger.

## Source reference

> "Stealth allows you to avoid detection by others." (Chapter 4: Skills, PF2E Core Rulebook)

## Implementation hint

`StealthActionResolver`: Hide: Stealth vs all observers' Perception; success = character becomes Hidden to those who failed; Hidden means attacker doesn't know exact square (−2 to attack, can't use flat-footed bonus yet). Sneak: move half Speed while Hidden; Stealth vs Perception at end of movement; failure = Observed. Conceal Object: opposed Stealth vs Perception; success = object hidden on person. Create a Diversion: delegates to `DeceptionActionResolver::createDiversion()`. Rogue integration: `EncounterEngine::resolveStrike(attacker, target)` checks if target is Hidden or Undetected to attacker for Sneak Attack bonus trigger.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Stealth actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH skill/stealth routes require `_csrf_request_header_mode: TRUE`
- Input validation: observer ids validated as encounter participants; hidden state stored server-side, not client-supplied
- PII/logging constraints: no PII logged; character id + observer id + hidden state + outcome only

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1721, 1722, 1723, 1724, 1725, 1726, 1727, 1728, 1729, 1730
- See `runbooks/roadmap-audit.md` for audit process.
