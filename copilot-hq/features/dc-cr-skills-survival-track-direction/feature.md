# Feature Brief: Survival — Sense Direction, Track, Cover Tracks

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-exploration-mode

## Description
Implement Survival (Wis) action handlers (REQs 1739–1746).

- **Sense Direction** (exploration, untrained, 1 min): Survival vs DC 15 (trackless) or 10
  (open terrain); gives compass direction or landmark orientation
- **Track** (exploration, trained): Survival vs DC based on trail freshness + terrain;
  follow tracks at half Speed; losing trail resets
- **Cover Tracks** (exploration, trained): Survival vs trackers' Survival; on success
  trackers take DC penalty to follow

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1739, 1740, 1741, 1742, 1743, 1744, 1745, 1746
- See `runbooks/roadmap-audit.md` for audit process.
