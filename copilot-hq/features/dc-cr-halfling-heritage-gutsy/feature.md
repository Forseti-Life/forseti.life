# Feature Brief: Halfling Heritage — Gutsy Halfling

- Work item id: dc-cr-halfling-heritage-gutsy
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: pre-triage
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7684–7983
- Category: game-mechanic
- Release: (set by PM at activation)
- Created: 2026-04-12

## Goal

Implements the Gutsy Halfling heritage: when a character with this heritage rolls a success on a saving throw against an emotion effect, the result is upgraded to a critical success. This provides a meaningful defensive niche against fear/emotion-based abilities and makes gutsy halflings resilient party members in encounters heavy with such effects.

## Source reference

> Gutsy Halfling
> Your family line is known for keeping a level head and staving off fear when the chips were down, making them wise leaders and sometimes even heroes. When you roll a success on a saving throw against an emotion effect, you get a critical success instead.

## Implementation hint

- Saving throw result handler: when character has this heritage and saves with Trait = emotion, and result = success, upgrade to critical success.
- Requires a trait tagging system on spells/effects (emotion tag) so the upgrade condition can be evaluated.
- One of 6 halfling heritage options; all share the dc-cr-halfling-ancestry + dc-cr-heritage-system prerequisites.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
