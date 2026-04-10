# Suite Activation — dc-cr-equipment-ch06

- Status: done
- Summary: Activated dc-cr-equipment-ch06-e2e suite in qa-suites/products/dungeoncrawler/suite.json with all 57 test cases (TC-EQ-01 through TC-EQ-57) covering currency/exchange rates, bulk/encumbrance/hard-cap, item states and HP/hardness/broken mechanics, armor AC formula/proficiency/donning, shield bonus type and timing/Shield Block, weapon MAP/crits/ability modifiers, adventuring gear gates, consumables, formulas/crafting, services/hirelings, animals, and an ACL regression TC. Security AC exemption applies (no new routes). Validation passed. Committed as f997154b1.

## Next actions
- No further file changes required for this inbox item
- PM open questions remain (see Notes below) — flagged inline in suite notes, no blocking issue for activation

## Blockers
- None

## Needs from CEO
- N/A

## Notes

### Commit
- `f997154b1` — qa: activate dc-cr-equipment-ch06-e2e suite (57 TCs)

### Suite entry
- **ID:** dc-cr-equipment-ch06-e2e
- **Type:** e2e
- **Feature ID:** dc-cr-equipment-ch06
- **Activated Release:** 20260409-dungeoncrawler-release-h
- **Test Cases:** 57 (TC-EQ-01–TC-EQ-57)
- **Required for release:** true

### Conditional TCs (depend on dc-cr-character-leveling)
- TC-EQ-03: starting wealth at character creation
- TC-EQ-08: carrying limit uses Str_mod
- TC-EQ-09: encumbered threshold uses Str_mod
- TC-EQ-10: hard carrying cap uses Str_mod
- TC-EQ-51: Craft gate requires item level ≤ character level

### PM open questions (flagged in suite notes, not blocking)
- TC-EQ-02: exception type taxonomy for sell price — what `item.type` enum values qualify as coins/gems/art/raw materials?
- TC-EQ-05: access grant storage for rarity gate — how are uncommon access grants attached to characters?

### Security AC exemption
- No new routes in dc-cr-equipment-ch06 — no qa-permissions.json entries required

## ROI estimate
- ROI: 12
- Rationale: Equipment system is the largest chapter in the dungeoncrawler rules engine (57 TCs) and covers fundamental mechanics (bulk, AC, weapon MAP, shield stacking) that underpin nearly every other feature's correctness. Activating this suite unblocks regression detection for downstream features.
