# Feature Brief: Goblin Ancestry Feat — Very Sneaky

- Work item id: dc-cr-goblin-very-sneaky
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: pre-triage
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 7384–7683
- Category: game-mechanic
- Release: (set by PM at activation)
- Created: 2026-04-11

## Goal

Implements the Goblin ancestry Feat 1 "Very Sneaky": characters can move 5 feet farther than normal when taking the Sneak action (up to their Speed), and avoid becoming Observed at the end of a Sneak action as long as they maintain cover or concealment by end of turn. This gives goblin characters a meaningful stealth niche that rewards consecutive Sneak actions and positional play.

## Source reference

> Taller folk rarely pay attention to the shadows at their feet, and you take full advantage of this. You can move 5 feet farther when you take the Sneak action, up to your Speed. In addition, as long as you continue to use Sneak actions and succeed at your Stealth check, you don't become observed if you don't have cover or greater cover and aren't concealed at the end of the Sneak action, as long as you have cover or greater cover or are concealed at the end of your turn.
>
> Note: Feat name "Very Sneaky" is inferred from PF2e rules knowledge; the name was not visible in the source text excerpt (likely at end of prior chunk before line 7384).

## Implementation hint

- Modify Sneak action handler to accept a +5 ft movement bonus when the acting character has this feat.
- Modify the Sneak action "end of action" visibility check: if character has cover/concealment at end of *turn* (not just end of action), suppress the Observed transition.
- Requires feat prerequisite check: character must be a Goblin with this feat selected.
- Ancestry feat unlock: add dc-cr-goblin-very-sneaky to the Goblin feat tree in dungeoncrawler_content.

## Mission alignment

- [ ] Aligns with democratized community game experience
- [ ] Does not add surveillance or restrict community access
