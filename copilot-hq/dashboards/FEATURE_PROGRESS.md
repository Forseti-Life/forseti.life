# Feature Progress

Generated: 2026-04-05T21:29:14Z

| Work item | Website | Module | Status | Priority | PM | Dev | QA |
|-----------|---------|--------|--------|----------|----|-----|----|
| dc-cr-action-economy | dungeoncrawler | dungeoncrawler_content | ready | P0 (foundation for all encounter-mode gameplay; all class features, spells, and skill actions depend on this) |  |  |  |
| dc-cr-alchemical-items | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-ancestry-feat-schedule | dungeoncrawler | dungeoncrawler_content | deferred | P3 (depends on dc-cr-character-leveling which is deferred; ancestry feat slots blocked until leveling system exists) |  |  |  |
| dc-cr-ancestry-system | dungeoncrawler | dungeoncrawler_content | ready | P0 (required dependency for character creation; enables ancestry feat trees and heritage selection downstream) |  |  |  |
| dc-cr-ancestry-traits | dungeoncrawler | dungeoncrawler_content | in_progress | P2 (spell/ability targeting prerequisite; deferred from current release — no spellcasting in scope yet) |  |  |  |
| dc-cr-animal-companion | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-background-system | dungeoncrawler | dungeoncrawler_content | ready | high (required prerequisite for character creation workflow; provides ability boosts, skill training, and skill feat to character) |  |  |  |
| dc-cr-character-class | dungeoncrawler | dungeoncrawler_content | ready | high (core pillar of character building; defines proficiencies, HP/level, class features, and class feats — required for character creation workflow) |  |  |  |
| dc-cr-character-creation | dungeoncrawler | dungeoncrawler_content | ready | high (first end-to-end player journey; onboarding experience for every new dungeoncrawler player; depends on ancestry, background, and class all being implemented first) |  |  |  |
| dc-cr-character-leveling | dungeoncrawler | dungeoncrawler_content | in_progress | unset (PM will set at triage) |  |  |  |
| dc-cr-clan-dagger | dungeoncrawler | dungeoncrawler_content | shipped | P3 (note: dependency on dc-cr-equipment-system and dc-cr-dwarf-ancestry was overridden by dev; all AC verified via drush ev) |  |  |  |
| dc-cr-conditions | dungeoncrawler | dungeoncrawler_content | ready | P1 (combat dependency; ConditionManager partial impl exists) |  |  |  |
| dc-cr-crafting | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-darkvision | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-dice-system | dungeoncrawler | dungeoncrawler_content | ready | P0 (foundational — every resolution system depends on this) |  |  |  |
| dc-cr-difficulty-class | dungeoncrawler | dungeoncrawler_content | ready | P0 (core check resolution mechanic — encounter and skill systems depend on this) |  |  |  |
| dc-cr-downtime-mode | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-dwarf-ancestry | dungeoncrawler | dungeoncrawler_content | deferred | P2 (extends ancestry-system with specific stat block; blocked on dc-cr-ancestry-system, dc-cr-clan-dagger, and dc-cr-heritage-system shipping first) |  |  |  |
| dc-cr-dwarf-heritage-ancient-blooded | dungeoncrawler | dungeoncrawler_content | ready | P3 (depends on dc-cr-heritage-system and dc-cr-dwarf-ancestry, neither yet shipped; deferred to next cycle) |  |  |  |
| dc-cr-dwarf-heritage-death-warden | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-dwarf-heritage-forge | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-dwarf-heritage-rock | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-dwarf-heritage-strong-blooded | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-dwarven-weapon-familiarity | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-encounter-rules | dungeoncrawler | dungeoncrawler_content | ready | P1 (primary gameplay loop; depends on dice and DC) |  |  |  |
| dc-cr-equipment-system | dungeoncrawler | dungeoncrawler_content | ready | P1 (combat and character creation dependency; InventoryManagementService partial impl exists) |  |  |  |
| dc-cr-exploration-mode | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-familiar | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-focus-spells | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-general-feats | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-gm-narrative-engine | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-gm-tools | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-hazards | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-heritage-system | dungeoncrawler | dungeoncrawler_content | ready | P1 (character creation dependency; heritage selection step immediately follows ancestry in creation wizard) |  |  |  |
| dc-cr-languages | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-magic-items | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-mountains-stoutness | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-multiclass-archetype | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-npc-system | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-rituals | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-rock-runner | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-session-structure | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-skill-feats | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-skill-system | dungeoncrawler | dungeoncrawler_content | ready | P1 (core activity resolution; exploration and social gameplay) |  |  |  |
| dc-cr-spellcasting | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-tactical-grid | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-cr-unburdened-iron | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-vengeful-hatred | dungeoncrawler | dungeoncrawler_content | pre-triage | unset (PM will set at triage) |  |  |  |
| dc-cr-xp-rewards | dungeoncrawler | dungeoncrawler_content | deferred | unset (PM will set at triage) |  |  |  |
| dc-home-suggestion-notice | dungeoncrawler.life | dungeoncrawler_content (home page / front page block) | in_progress | P2 |  |  |  |
| forseti-ai-debug-gate | forseti.life | ai_conversation | in_progress | P1 |  |  |  |
| forseti-ai-service-refactor | forseti.life | ai_conversation | in_progress | P2 |  |  |  |
| forseti-copilot-agent-tracker | forseti.life | copilot_agent_tracker | in_progress | P1 |  |  |  |
| forseti-csrf-fix | forseti.life | job_hunter | in_progress | P0 |  |  |  |
| forseti-jobhunter-application-submission | forseti.life | job_hunter | in_progress | P1 |  |  |  |
| forseti-jobhunter-browser-automation | forseti.life | job_hunter | ready | P1 |  |  |  |
| forseti-jobhunter-controller-refactor | forseti.life | job_hunter | in_progress | P2 |  |  |  |
| forseti-jobhunter-e2e-flow | forseti.life | job_hunter | in_progress | P0 (ROI 1000) |  |  |  |
| forseti-jobhunter-profile | forseti.life | job_hunter | in_progress | P0 |  |  |  |
| forseti-jobhunter-schema-fix | forseti.life | job_hunter | in_progress | P2 |  |  |  |
| local-llm-integration |  |  |  |  |  |  |  |

## Key metric: auto-remediation rate
Track how often the CEO health monitor self-heals stalled execution.

- Event source: `inbox/responses/ceo-health-YYYYMMDD.log`
- Event marker: `AUTO-REMEDIATE:`
- Goal: non-zero when queues are stuck, near-zero during steady state.

### Quick checks
- Total today:
  - `grep -c 'AUTO-REMEDIATE' inbox/responses/ceo-health-$(date +%Y%m%d).log`
- Per-hour breakdown today:
  - `grep 'AUTO-REMEDIATE' inbox/responses/ceo-health-$(date +%Y%m%d).log | sed -E 's/^\[([0-9-]+T[0-9]{2}).*/\1/' | sort | uniq -c`

### Interpretation
- Spikes mean monitor-driven recovery is actively compensating for stalled execution.
- Persistent spikes across many hours indicate a deeper routing/executor issue and should trigger a CEO-level follow-up item.

## Key metric: handoff-gap recoveries
Track when release monitor detects Dev-complete/open-issues handoff gaps.

- Event source: `inbox/responses/ceo-health-YYYYMMDD.log`
- Event marker: `AUTO-HANDOFF:`

### Quick checks
- Total today:
  - `grep -c 'AUTO-HANDOFF' inbox/responses/ceo-health-$(date +%Y%m%d).log`
- Per-hour breakdown today:
  - `grep 'AUTO-HANDOFF' inbox/responses/ceo-health-$(date +%Y%m%d).log | sed -E 's/^\[([0-9-]+T[0-9]{2}).*/\1/' | sort | uniq -c`

## Key metric: Stage 3 velocity (issues resolved per 15 minutes)
Track implementation throughput from QA finding closure deltas.

- Source of truth: `sessions/<qa-seat>/artifacts/auto-site-audit/*/findings-summary.json`
- Defect total per run: `missing_assets_404s + permission_violations + failures`
- Resolved count: positive drop between consecutive QA runs

### Quick checks
- Current team (15-minute window):
  - `python3 scripts/stage3-velocity.py --team forseti --window-minutes 15`
- Portfolio view (JSON):
  - `python3 scripts/stage3-velocity.py --team all --window-minutes 15 --json`

### Interpretation
- Higher `resolved per 15m` means faster Stage 3 execution throughput.
- `handoff signal=strong` indicates Dev→QA notify/retest loop is actively closing items.
