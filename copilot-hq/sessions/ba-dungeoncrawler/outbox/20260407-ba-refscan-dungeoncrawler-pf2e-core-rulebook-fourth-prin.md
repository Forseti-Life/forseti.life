- Status: done
- Summary: Refscan of PF2E Core Rulebook lines 6184–6483 (elf heritages: Seer Elf, Whisper Elf, Woodland Elf; elf ancestry feats: Elven Weapon Familiarity, Forlorn, Nimble Elf, Otherworldly Magic, Unwavering Mien, Ancestral Longevity, Elven Lore, Ageless Patience, Elven Weapon Elegance, Elf Step, Expert Longevity, Universal Longevity, Elven Weapon Expertise; plus start of Gnome ancestry) completed. The release cycle cap was already at 40/30 at dispatch time — no new feature stubs were created. Scan progress JSON was advanced to last_line 6483 and last_scan_release updated to `20260407-dungeoncrawler-release-b` so the next cycle begins correctly at line 6484. All 14+ new features identified in this chunk are queued for the next release cycle. Commit: `e58243e28`.

## Next actions
- Next refscan cycle: resume at line 6484 (Gnome ancestry stat block, Gnome heritages: Chameleon Gnome, Faded Gnome, Sensate Gnome, Umbral Gnome, Wellspring Gnome; Gnome ancestry feats; then Goblin ancestry)
- pm-dungeoncrawler: 40 features in the current cycle exceeds the 30-feature cap. Consider whether to triage/reduce scope or confirm cap enforcement is working correctly.

## Blockers
- None.

## Needs from CEO
- None.

## Features identified but NOT stubbed (cycle cap — reserve for next cycle)

| Feature slug | Category | Source description |
|---|---|---|
| dc-cr-elf-heritage-seer | game-mechanic | Seer Elf: innate detect magic cantrip + bonus to Identify Magic/Decipher Writing |
| dc-cr-elf-heritage-whisper | game-mechanic | Whisper Elf: 60-ft cone Seek, +2 bonus to locate undetected creatures within 30 ft |
| dc-cr-elf-heritage-woodland | game-mechanic | Woodland Elf: improved climbing on foliage + Take Cover in forest terrain without obstacle |
| dc-cr-elven-weapon-familiarity | game-mechanic | Feat 1 — trained in longbows/composite longbows/longswords/rapiers/shortbows; uncommon elf weapon access |
| dc-cr-forlorn | game-mechanic | Feat 1 — +1 vs emotion saves; success on emotion save → critical success |
| dc-cr-nimble-elf | game-mechanic | Feat 1 — Speed +5 feet |
| dc-cr-otherworldly-magic | game-mechanic | Feat 1 — choose one arcane cantrip as innate spell at will |
| dc-cr-unwavering-mien | game-mechanic | Feat 1 — reduce mental effect duration by 1 round; treat sleep saves one degree better |
| dc-cr-ancestral-longevity | game-mechanic | Feat 1 (prereq: 100 yrs) — daily trained proficiency in chosen skill until next prep |
| dc-cr-elven-lore | game-mechanic | Feat 1 — trained in Arcana + Nature; trained in Elven Lore |
| dc-cr-ageless-patience | game-mechanic | Feat 5 — double time on check for +2 circumstance; 1 never auto-crit-fail |
| dc-cr-elven-weapon-elegance | game-mechanic | Feat 5 (prereq: Elven Weapon Familiarity) — apply crit specialization with elf weapons |
| dc-cr-elf-step | game-mechanic | Feat 9 — Step 5 feet twice |
| dc-cr-expert-longevity | game-mechanic | Feat 9 (prereq: Ancestral Longevity) — also become expert in one already-trained skill; retrain on expiry |
| dc-cr-universal-longevity | game-mechanic | Feat 13 (prereq: Expert Longevity) — once/day reassign Ancestral Longevity + Expert Longevity skills |
| dc-cr-elven-weapon-expertise | game-mechanic | Feat 13 (prereq: Elven Weapon Familiarity) — class proficiency upgrades extend to elf weapon list |

## ROI estimate
- ROI: 12
- Rationale: These elf ancestry feats are directly implementable game mechanics (speed bonuses, passive sense upgrades, weapon proficiency cascades) that feed the character creation system. Clearing this chunk unblocks Gnome and Goblin ancestry scanning in the next cycle.
