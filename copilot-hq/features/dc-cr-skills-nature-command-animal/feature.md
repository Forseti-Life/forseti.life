# Feature Brief: Nature — Command an Animal

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-npc-system

## Description
Implement Nature (Wis) action handlers (REQs 1705–1714). Core action is
Command an Animal, plus Recall Knowledge for Nature-applicable types.

- **Command an Animal** (1 action, auditory, concentrate, trained): Nature vs animal
  Will DC; animal attitude system (hostile/unfriendly/indifferent/friendly/helpful);
  crit success=friendly attitude shift; success=performs action; fail=no effect;
  crit fail=attitude decreases
- Animal action set: the commanded animal must have defined legal actions
  (Move, Strike, specific trained actions)
- Recall Knowledge (Nature): Animals, Beasts, Fungi, Plants, Spirits, Elementals —
  coordination with dc-cr-creature-identification

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1705, 1706, 1707, 1708, 1709, 1710, 1711, 1712, 1713, 1714
- See `runbooks/roadmap-audit.md` for audit process.
