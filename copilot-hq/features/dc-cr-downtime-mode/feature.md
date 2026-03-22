# Feature Brief: Downtime Mode

- Work item id: dc-cr-downtime-mode
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: deferred
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement downtime mode for activities that take days or weeks between adventures: Crafting items, Earning Income via skill checks, Retraining feats/skills, and other long-duration activities. Downtime gives players meaningful choices between sessions and allows character development beyond leveling, supporting long-form dungeoncrawler campaigns.

## Source reference

> "This includes rules on encounters, exploration, and downtime, as well as everything you need for combat." (Chapter 9: Playing the Game)

## Implementation hint

Session state mode: downtime. Downtime activities content type with fields for duration (days), required skill/proficiency, and outcome table (income earned, progress toward craft goal, etc.). API: start downtime activity, advance time, resolve outcomes. Crafting downtime interacts with the equipment and magic items systems. Retraining interacts with the feat/skill systems.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
