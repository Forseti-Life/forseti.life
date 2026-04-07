# Feature Brief: Survival — Sense Direction, Track, Cover Tracks

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-exploration-mode

## Description
Implement Survival (Wis) action handlers and the Subsist general skill action
(REQs 1572–1573, 1595–1598, 1739–1746).

- **Subsist** (downtime, untrained): Nature (wilderness) or Society (urban) vs DC 15
  (higher in harsh environments; +2 DC per additional creature fed).
  Crit Success = full provisions for group; Success = self fed; Fail = meager;
  Crit Fail = starvation begins.
- **Sense Direction** (exploration, untrained, 1 min): Survival vs DC 15 (trackless) or 10
  (open terrain); gives compass direction or landmark orientation
- **Track** (exploration, trained): Survival vs DC based on trail freshness + terrain;
  follow tracks at half Speed; losing trail resets
- **Cover Tracks** (exploration, trained): Survival vs trackers' Survival; on success
  trackers take DC penalty to follow
- REQs 1572–1573 define the general skill action framework (untrained vs trained gating);
  covered here as Subsist is the primary untrained general action.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1572, 1573, 1595, 1596, 1597, 1598, 1739, 1740, 1741, 1742, 1743, 1744, 1745, 1746
- See `runbooks/roadmap-audit.md` for audit process.
