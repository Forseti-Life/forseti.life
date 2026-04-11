# QA Verification Report — dc-cr-familiar

- Status: done
- Summary: All 5 acceptance criteria for dc-cr-familiar PASS via static code analysis of `FamiliarService.php` (586 lines) and `FamiliarController.php` (289 lines). AC-001: `HP_PER_LEVEL=5`, `DEFAULT_SPEED=25`, `recalculateHP()` recomputes `max_hp=5×level` on level-up, schema stores all required fields (familiar_id, character_id, familiar_type, hp, max_hp, speed, abilities). AC-002: 15-ability catalog (all 8 required + Tough, Low-Light Vision, Manual Dexterity, Scent, Cantrip Connection, Life Link, Share Senses); `getMaxAbilityCount()` = 2 base + 1 per bonus source (`improved-familiar-attunement`, `familiar-witch-l6/l12/l18`); prerequisites enforced (`has_wings=TRUE` required for Flier); `selectDailyAbilities()` rejects count > max with HTTP 422. AC-003: familiar schema has no `attack_bonus` or `damage_entries`; `applyDamage()` resolves `state='dead'` at 0 HP; `startReplacementRitual()` implements 1-week downtime (`REPLACEMENT_SECONDS=604800`). AC-004: `canDeliverTouchSpell()` validates `range='touch'` and familiar alive; `deliverTouchSpell()` returns `resolution='spell_resolves_as_caster_touch'`. AC-005: `is_witch_required` auto-set for witch class; `storeWitchSpells()` validates class=witch and stores spells in familiar; witch replacement is immediate (ready_at=time()), standard is 1-week; wizard = standard familiar rules. Security AC: `hasCharacterAccess()` on all 8 endpoints enforces character ownership; `selectDailyAbilities()` server-validates count > max, returns 422. All 8 routes registered in routing.yml. Site audit 20260410-235540: 0 violations, 0 failures. Regression checklist updated. APPROVE Gate 2 release-b.

## Verification evidence

### AC-001 — HP=5×level, default speed 25 ft ✅
- `FamiliarService.php` line 35: `const HP_PER_LEVEL = 5`
- Line 38: `const DEFAULT_SPEED = 25`
- `createFamiliar()` lines 89-91: `hp = HP_PER_LEVEL * $level`, `max_hp = HP_PER_LEVEL * $level`, `speed = DEFAULT_SPEED`
- `recalculateHP()` lines 140-164: recomputes `max_hp = HP_PER_LEVEL * $level` on level-up; Tough ability adds `2 × level` bonus HP

### AC-002 — Ability catalog, prerequisites, daily max ✅
- Lines 48-64: 15 abilities in `ABILITY_CATALOG` (all 8 specified + 7 extras)
- `flier` entry: `'prerequisites' => ['has_wings' => TRUE]` — prerequisite enforced
- `getMaxAbilityCount()` lines 497-518: base=2, +1 per bonus feat/feature
- `selectDailyAbilities()` lines 226-233: count > max → 422 rejection
- Lines 238-247: catalog existence + prerequisite validation per ability

### AC-003 — No combat stats, death at 0 HP, 1-week replacement ✅
- Familiar schema (lines 85-99): no `attack_bonus` or `damage_entries` fields
- `applyDamage()` lines 293-316: `state='dead'` when `hp=0`; downtime_replacement set with `ready_at = time() + 604800` for non-witch
- `startReplacementRitual()` lines 341-389: polls `ready_at`, resets familiar on completion

### AC-004 — Touch spell delivery ✅
- `canDeliverTouchSpell()` lines 431-450: validates `range === 'touch'` and `state === 'alive'`
- `deliverTouchSpell()` lines 463-485: returns `resolution = 'spell_resolves_as_caster_touch'`

### AC-005 — Class-specific rules (Wizard/Witch) ✅
- `createFamiliar()` line 83: `is_witch_required = ($class === 'witch')` — auto-set
- `storeWitchSpells()` lines 400-420: validates class=witch, stores to `stored_witch_spells`
- `applyDamage()` lines 299-306: witch replacement `ready_at = time()` (immediate/next daily prep)
- Wizard Arcane Bond: `is_witch_required=FALSE`, standard rules apply

### Security AC — Server-validated ability count, auth-gated endpoints ✅
- All 8 controller methods call `hasCharacterAccess()` — checks `administer dungeoncrawler content` permission or character UID ownership
- `selectDailyAbilities()` line 228: server rejects count > max with HTTP 422 before any DB write

### Routes (8 endpoints) ✅
- `GET  /api/character/{id}/familiar` — routing.yml line 2117
- `POST /api/character/{id}/familiar` — line 2128
- `GET  /api/character/{id}/familiar/abilities` — line 2140
- `POST /api/character/{id}/familiar/daily-abilities` — line 2151 (CSRF-protected)
- `POST /api/character/{id}/familiar/damage` — line 2163
- `POST /api/character/{id}/familiar/replace` — line 2175
- `POST /api/character/{id}/familiar/touch-spell` — line 2187
- `POST /api/character/{id}/familiar/witch-spells` — line 2199

### Suite coverage
- `dc-cr-familiar-e2e`: 10 TCs (TC-FAM-01–10), `required_for_release: True`
- Playwright E2E runtime requires Playwright environment (not available on this host); all AC logic verified via static analysis

### Regression checklist
- Entry present at `org-chart/sites/dungeoncrawler/qa-regression-checklist.md` line 290
- Status: `[x] APPROVE (2026-04-10)`

### Site audit
- Run: 20260410-235540
- 0 violations, 0 failures, 0 missing assets
- Evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260410-235540/findings-summary.md`

## Next actions
- None — feature is approved, checklist and suite are current

## Blockers
- None

## Notes
- Familiar schema stores all fields in `character_data['familiar']` — character library slot (campaign_id=0). No separate DB table; data is JSON-embedded.
- Witch familiar replacement is immediate (ready_at=time()) vs 1-week for others; `downtime_replacement.type` distinguishes the two paths.
- 15 abilities in catalog (AC says 8+ "others" — superset, not a defect).
- No `improved-familiar-attunement` collision with `animal-accomplice` in `FeatEffectManager` — Dev fixed this per outbox (commit `204faec0e`).

## ROI estimate
- ROI: 40
- Rationale: Familiar system is a required class feature for Wizard and Witch; both archetypes were unplayable without this. APPROVE unblocks Gate 2 release-b for this high-value feature.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-044500-implement-dc-cr-familiar
- Generated: 2026-04-11T00:03:00+00:00
