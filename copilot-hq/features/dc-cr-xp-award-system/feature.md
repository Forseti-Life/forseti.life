# Feature Brief: XP Award System

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P3
- Status: deferred
- Release: none
- Dependencies: dc-cr-encounter-creature-xp-table, dc-cr-character-leveling

## Description
Implement PF2e XP-based character advancement (REQs 2332–2335, 2337–2339).

**PM Decision 2026-03-08**: CharacterLevelingService currently uses milestone-only
leveling. XP system was explicitly removed. This feature is DEFERRED until that
decision is revisited.

When activated, covers:
- 1,000 XP threshold to level; subtract 1,000 on level-up (REQ 2332)
- Party-wide equal XP from encounters and accomplishments (REQ 2333)
- Trivial encounter = 0 XP (REQ 2334)
- Fast/Standard/Slow advancement variants: 800/1000/1200 XP (REQ 2335)
- Accomplishment XP categories: minor/moderate/major (REQ 2337)
- Accomplishment → Hero Point for instrumental PC (REQ 2338)
- Creature XP from Table 10-2; Hazard XP from Table 10-14 (REQ 2339)

Note: REQ 2336 (story/milestone leveling) is already PARTIAL via milestoneReady flag.

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2332–2335, 2337–2339
- See `runbooks/roadmap-audit.md` for audit process.
