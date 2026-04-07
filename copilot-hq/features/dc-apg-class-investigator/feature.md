# Feature Brief: Investigator Class Mechanics (APG)

- Work item id: dc-apg-class-investigator
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 2 (Investigator)
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch02/Investigator
- Depends on: dc-cr-character-class, dc-cr-character-leveling, dc-cr-skill-system

## Goal

Implement the Investigator class with Devise a Stratagem (free action that substitutes INT for attack roll), Strategic Strike (+d6 precision damage scaling per 5 levels), Methodology subclass (Empiricism/Forensic Medicine/Interrogation/Scalpel), and Pursue a Lead.

## Source reference

> "Investigators are tactical brains who use their wits and knowledge to overcome challenges." (Advanced Player's Guide, Investigator Class)

## Implementation hint

Devise a Stratagem: free action before a Strike; requires Recall Knowledge check vs creature (DC 10 + creature level); on success, replace attack roll with INT modifier for that Strike only (stored as `stratagem_pending` boolean on turn state). Strategic Strike: +1d6 at L1, +2d6 at L5, +3d6 at L9, etc. — applies when stratagem was active for that Strike. Methodology: `investigator_methodology` enum; Empiricism adds free Recall Knowledge on observation; Forensic Medicine grants Treat Wounds without healer's tools; Interrogation gives bonuses vs Lie. Pursue a Lead: designates a person/place/object as studied target; grants circumstance bonuses to related checks.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; Devise a Stratagem and class actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH class/investigator routes require `_csrf_request_header_mode: TRUE`
- Input validation: stratagem target id validated as encounter participant; methodology enum validated server-side; Strategic Strike dice count computed server-side from level
- PII/logging constraints: no PII logged; character id + target id + stratagem result + damage dice only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
