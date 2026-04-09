# Feature Brief: Gnome Weapon Familiarity (Gnome Ancestry Feat 1)

- Work item id: dc-cr-gnome-weapon-familiarity
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: pre-triage
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 6784–7083
- Category: game-mechanic
- Created: 2026-04-09

## Goal

Gnome ancestry feat granting trained proficiency in glaive and kukri, plus access to all uncommon gnome weapons. Martial gnome weapons count as simple weapons for proficiency purposes. Gates the higher-level Gnome Weapon Specialist and Gnome Weapon Expertise feats.

## Source reference

> You favor unusual weapons tied to your people, such as blades with curved and peculiar shapes. You are trained with the glaive and kukri. In addition, you gain access to kukris and all uncommon gnome weapons. For the purpose of determining your proficiency, martial gnome weapons are simple weapons and...

## Implementation hint

Ancestry feat that adds glaive and kukri to the character's trained weapon list, grants access flag for uncommon gnome weapons, and registers martial gnome weapons as simple-tier for proficiency resolution. Depends on the equipment/weapon-proficiency system supporting trait-based re-classification (gnome martial → simple).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
