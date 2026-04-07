- Status: done
- Summary: Verified dev item `20260406-impl-senses-detection-hero-points` (commits d5c5e1679 + fix 663dbd92a). Verdict: APPROVE. Roadmap QA BLOCK `3c6df079e` (18/23 PASS) identified 3 medium gaps — all resolved by fix commit `663dbd92a` before this unit-test ran: GAP-2278 (hearing branch for invisible/undetected creatures wired in `CombatEngine::resolveSensePrecision()`), GAP-2280 (`hero_point_reroll` added to `getLegalIntents()` + `processIntent()`), GAP-2281 (`heroic_recovery_all_points` action wired to `HPManager::heroicRecoveryAllPoints()`). Post-fix re-verification: 23/23 medium+ reqs PASS. Seven low-severity gaps remain (magical darkness flag, tremorsense airborne, scent wind, hero-point session reset, familiar system, PC-PC tie UX, in_world_seconds time-based expiry) — none block release. Committed `122526060`.

## Next actions
- Awaiting next inbox dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Senses and detection are core to PF2e tactical play — visibility-based targeting, stealth encounters, and hero point mechanics all now function correctly. APPROVE clears this feature group for release.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-senses-detection-hero-points
- Artifact: sessions/qa-dungeoncrawler/artifacts/20260406-unit-test-20260406-impl-senses-detection-hero-points/verification-report.md
- Generated: 2026-04-07
