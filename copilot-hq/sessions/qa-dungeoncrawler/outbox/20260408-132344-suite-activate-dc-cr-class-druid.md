- Status: done
- Summary: Suite activation complete for dc-cr-class-druid. Added 30 TCs to qa-suites/products/dungeoncrawler/suite.json covering WIS key ability, HP 8+CON, proficiencies (light/medium armor; metal armor and shields forbidden), Druidic language auto-grant, Wild Empathy, metal armor failure mode, anathema/atone (primal spellcasting + order benefits suspended), all four orders (Animal/Leaf/Storm/Wild, permanent, each grants 1 focus spell), focus pool starting values (Leaf/Storm=2, Animal/Wild=1), focus pool empty blocks cast, prepared primal spellcasting, WIS spell attack/DC, spell slot scaling, Primal Hierophant (level 19, no manipulation), level-gated features, feat/ability-boost schedules, Order Explorer, Wild Shape (unlocked-forms blocked), Form Control (duration + level -2 min 1), ACL (403), and QA audit clean. TCs DRU-15/16/17/18 flagged pending-dev-confirmation: dc-cr-spellcasting. Validation confirmed OK (5 manifests). Committed 51479cfe9.

## Next actions
- Write outboxes for remaining 9 batch suite activations (rarity, skills ×6, human-ancestry/session-structure/spellcasting confirm)
- Write verification reports for unit test items gap-2093-range-cap-hardening and load-core-ancestry-requirements
- Update qa-regression-checklist.md for the two unit test items

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Druid is a core class; 30 TCs covering orders, focus pool, and forbidden-equipment rules de-risk the class character-creation flow for Release-C. Pending spellcasting TCs will auto-activate when dc-cr-spellcasting ships.
