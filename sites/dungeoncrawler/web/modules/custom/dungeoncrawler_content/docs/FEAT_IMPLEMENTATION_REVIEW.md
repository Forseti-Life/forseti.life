# Feat Implementation Review (162/162)

Generated from current resolver code in `src/Service/FeatEffectManager.php`.

## Hook Chain

- `FeatEffectManager::buildEffectState()` resolves feat effects.
- `CharacterStateService::applyFeatEffectsToState()` persists derived feat effects into campaign `state_data`.
- `CharacterViewController` + `character-sheet.html.twig` surface feat effects on the sheet.

## Per-Feat Implementation

| # | Feat ID | Name | Implementation Path | Hook(s) | In-Game Impact |
|---:|---|---|---|---|---|
| 1 | `adapted-cantrip` | Adapted Cantrip | switch-case | addSelectionGrant, available_actions.at_will | Adds selection grant metadata; Adds feat action to at-will action list |
| 2 | `ancestral-longevity` | Ancestral Longevity | switch-case | addSelectionGrant, addSkillTraining | Adds selection grant metadata; Adds trained skill grants |
| 3 | `animal-accomplice` | Animal Accomplice | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 4 | `beak-adept` | Beak Adept | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 5 | `burn-it` | Burn It! | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 6 | `burrow-elocutionist` | Burrow Elocutionist | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 7 | `cat-nap` | Cat Nap | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction | Adds long-rest limited resource/action |
| 8 | `catfolk-lore` | Catfolk Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 9 | `catfolk-weapon-familiarity` | Catfolk Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 10 | `cheek-pouches` | Cheek Pouches | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 11 | `city-scavenger` | City Scavenger | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 12 | `communal-instinct` | Communal Instinct | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSaveModifier | Adds conditional save bonus |
| 13 | `cooperative-nature` | Cooperative Nature | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSaveModifier | Adds conditional save bonus |
| 14 | `cross-cultural-upbringing` | Cross-Cultural Upbringing | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 15 | `distracting-shadows` | Distracting Shadows | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 16 | `draconic-scout` | Draconic Scout | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction, addSense | Adds long-rest limited resource/action; Adds low-light vision in first-pass |
| 17 | `draconic-ties` | Draconic Ties | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 18 | `dwarven-lore` | Dwarven Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 19 | `dwarven-weapon-familiarity` | Dwarven Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 20 | `elf-atavism` | Elf Atavism | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 21 | `elven-instincts` | Elven Instincts | switch-case | derived_adjustments.initiative_bonus | Changes initiative bonus |
| 22 | `elven-lore` | Elven Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 23 | `elven-weapon-familiarity` | Elven Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 24 | `feline-eyes` | Feline Eyes | switch-case | addSense | Adds a sense/vision entry |
| 25 | `feral-endurance` | Feral Endurance | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 26 | `fey-fellowship` | Fey Fellowship | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 27 | `first-world-magic` | First World Magic | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 28 | `forest-step` | Forest Step | bulk-first-pass | applyBulkFirstPassFeat, conditional_modifiers.movement | Adds baseline first-pass movement/utility modifier |
| 29 | `forlorn` | Forlorn | switch-case | addConditionalSaveModifier, conditional_modifiers.outcome_upgrades | Adds conditional saving throw modifier; Adds degree-of-success outcome upgrade |
| 30 | `forlorn-half-elf` | Forlorn Half-Elf | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSaveModifier | Adds conditional save bonus |
| 31 | `general-training` | General Training | switch-case | addSelectionGrant | Adds selection grant metadata |
| 32 | `gnome-obsession` | Gnome Obsession | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 33 | `gnome-weapon-familiarity` | Gnome Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 34 | `goblin-lore` | Goblin Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 35 | `goblin-scuttle` | Goblin Scuttle | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 36 | `goblin-song` | Goblin Song | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 37 | `goblin-weapon-familiarity` | Goblin Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 38 | `graceful-step` | Graceful Step | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 39 | `halfling-lore` | Halfling Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 40 | `halfling-luck` | Halfling Luck | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 41 | `halfling-weapon-familiarity` | Halfling Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 42 | `haughty-obstinacy` | Haughty Obstinacy | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 43 | `hold-scarred` | Hold-Scarred Orc | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction | Adds long-rest limited resource/action |
| 44 | `illusion-sense` | Illusion Sense | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 45 | `intimidating-glare-half-orc` | Intimidating Glare | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will(reaction) | Adds reaction feat action |
| 46 | `junk-tinker` | Junk Tinker | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 47 | `kobold-lore` | Kobold Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 48 | `kobold-weapon-familiarity` | Kobold Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 49 | `leshy-lore` | Leshy Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 50 | `mixed-heritage-adaptability` | Mixed Heritage Adaptability | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 51 | `multitalented` | Multitalented | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 52 | `natural-ambition` | Natural Ambition | switch-case | addSelectionGrant | Adds selection grant metadata |
| 53 | `natural-skill` | Natural Skill | switch-case | addSelectionGrant | Adds selection grant metadata |
| 54 | `nimble-elf` | Nimble Elf | switch-case | derived_adjustments.speed_override | Overrides base speed floor |
| 55 | `one-toed-hop` | One-Toed Hop | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 56 | `orc-atavism` | Orc Atavism | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 57 | `orc-ferocity` | Orc Ferocity | switch-case | addLongRestLimitedAction | Adds long-rest action and resource tracking |
| 58 | `orc-sight` | Orc Sight | switch-case | addSense | Adds a sense/vision entry |
| 59 | `orc-superstition` | Orc Superstition | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSaveModifier | Adds conditional save bonus |
| 60 | `orc-weapon-carnage` | Orc Weapon Carnage | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 61 | `orc-weapon-familiarity` | Orc Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 62 | `orc-weapon-familiarity-half-orc` | Orc Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 63 | `otherworldly-magic` | Otherworldly Magic | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 64 | `photosynthetic-recovery` | Photosynthetic Recovery | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction | Adds long-rest limited resource/action |
| 65 | `ratfolk-lore` | Ratfolk Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 66 | `ratfolk-weapon-familiarity` | Ratfolk Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 67 | `rock-runner` | Rock Runner | switch-case | addConditionalSkillModifier | Adds conditional skill modifier |
| 68 | `rooted-resilience` | Rooted Resilience | bulk-first-pass | applyBulkFirstPassFeat, conditional_modifiers.movement | Adds baseline first-pass movement/utility modifier |
| 69 | `scar-thickened` | Scar-Thickened | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 70 | `scrounger` | Scrounger | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 71 | `seedpod` | Seedpod | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 72 | `sky-bridge-runner` | Sky-Bridge Runner | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 73 | `snare-setter` | Snare Setter | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 74 | `squawk` | Squawk | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 75 | `stonecunning` | Stonecunning | bulk-first-pass | applyBulkFirstPassFeat, derived_adjustments.perception_bonus | Adds perception bonus for stonework context |
| 76 | `sure-feet` | Sure Feet | switch-case | conditional_modifiers.outcome_upgrades | Adds degree-of-success outcome upgrade |
| 77 | `tengu-lore` | Tengu Lore | switch-case | addSkillTraining, addLoreTraining | Adds trained skill grants; Adds lore training grants |
| 78 | `tengu-weapon-familiarity` | Tengu Weapon Familiarity | switch-case | addWeaponFamiliarity | Adds weapon familiarity group training |
| 79 | `titan-slinger` | Titan Slinger | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 80 | `tunnel-runner` | Tunnel Runner | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 81 | `tunnel-vision` | Tunnel Vision | bulk-first-pass | applyBulkFirstPassFeat, conditional_modifiers.movement | Adds baseline first-pass movement/utility modifier |
| 82 | `unburdened-iron` | Unburdened Iron | switch-case | buildEffectState switch case | Custom first-pass feat effect mapping |
| 83 | `unconventional-weaponry` | Unconventional Weaponry | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 84 | `unfettered-halfling` | Unfettered Halfling | switch-case | addConditionalSkillModifier, conditional_modifiers.outcome_upgrades | Adds conditional skill modifier; Adds degree-of-success outcome upgrade |
| 85 | `unwavering-mien` | Unwavering Mien | switch-case | conditional_modifiers.outcome_upgrades | Adds degree-of-success outcome upgrade |
| 86 | `unyielding-will` | Unyielding Will | switch-case | addConditionalSaveModifier | Adds conditional saving throw modifier |
| 87 | `vengeful-hatred` | Vengeful Hatred | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSaveModifier | Adds conditional save bonus |
| 88 | `verdant-voice` | Verdant Voice | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 89 | `well-groomed` | Well-Groomed | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 90 | `animal-companion` | Animal Companion | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds animal companion selection slot |
| 91 | `counterspell` | Counterspell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 92 | `crossbow-ace` | Crossbow Ace | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 93 | `double-slice` | Double Slice | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 94 | `eschew-materials` | Eschew Materials | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 95 | `exacting-strike` | Exacting Strike | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 96 | `familiar` | Familiar | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 97 | `hand-of-the-apprentice` | Hand of the Apprentice | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 98 | `hunted-shot` | Hunted Shot | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 99 | `monster-hunter` | Monster Hunter | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 100 | `nimble-dodge` | Nimble Dodge | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will(reaction) | Adds reaction feat action |
| 101 | `point-blank-shot` | Point-Blank Shot | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 102 | `power-attack` | Power Attack | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 103 | `reach-spell` | Reach Spell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 104 | `reactive-shield` | Reactive Shield | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 105 | `snagging-strike` | Snagging Strike | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 106 | `trap-finder` | Trap Finder | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 107 | `twin-feint` | Twin Feint | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 108 | `twin-takedown` | Twin Takedown | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will | Adds at-will feat action |
| 109 | `widen-spell` | Widen Spell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 110 | `you-re-next` | You're Next | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will(reaction) | Adds reaction feat action |
| 111 | `adopted-ancestry` | Adopted Ancestry | switch-case | addSelectionGrant | Adds selection grant metadata |
| 112 | `armor-proficiency` | Armor Proficiency | switch-case | addProficiencyGrant | Adds proficiency training grant |
| 113 | `breath-control` | Breath Control | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction | Adds long-rest limited resource/action |
| 114 | `canny-acumen` | Canny Acumen | switch-case | addSelectionGrant | Adds selection grant metadata |
| 115 | `diehard` | Diehard | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction | Adds long-rest limited resource/action |
| 116 | `fast-recovery` | Fast Recovery | bulk-first-pass | applyBulkFirstPassFeat, addLongRestLimitedAction | Adds long-rest limited resource/action |
| 117 | `feather-step` | Feather Step | bulk-first-pass | applyBulkFirstPassFeat, derived_adjustments.flags | Sets difficult-terrain ignore flag |
| 118 | `fleet` | Fleet | switch-case | derived_adjustments.speed_bonus | Changes movement speed derivation |
| 119 | `incredible-initiative` | Incredible Initiative | switch-case | derived_adjustments.initiative_bonus | Changes initiative bonus |
| 120 | `ride` | Ride | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSaveModifier | Adds conditional save bonus |
| 121 | `shield-block` | Shield Block | bulk-first-pass | applyBulkFirstPassFeat, available_actions.at_will(reaction) | Adds Shield Block reaction entry |
| 122 | `toughness` | Toughness | switch-case | derived_adjustments.hp_max_bonus | Changes max HP derivation |
| 123 | `weapon-proficiency` | Weapon Proficiency | switch-case | addProficiencyGrant | Adds proficiency training grant |
| 124 | `assurance` | Assurance | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 125 | `bargain-hunter` | Bargain Hunter | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 126 | `cat-fall` | Cat Fall | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 127 | `charming-liar` | Charming Liar | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 128 | `combat-climber` | Combat Climber | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 129 | `courtly-graces` | Courtly Graces | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 130 | `experienced-smuggler` | Experienced Smuggler | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 131 | `experienced-tracker` | Experienced Tracker | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 132 | `fascinating-performance` | Fascinating Performance | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 133 | `forager` | Forager | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 134 | `group-impression` | Group Impression | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 135 | `hefty-hauler` | Hefty Hauler | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 136 | `hobnobber` | Hobnobber | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 137 | `intimidating-glare` | Intimidating Glare | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 138 | `lengthy-diversion` | Lengthy Diversion | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 139 | `lie-to-me` | Lie to Me | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 140 | `multilingual` | Multilingual | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant | Adds pending selection grant |
| 141 | `natural-medicine` | Natural Medicine | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 142 | `oddity-identification` | Oddity Identification | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 143 | `pickpocket` | Pickpocket | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 144 | `quick-identification` | Quick Identification | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 145 | `quick-jump` | Quick Jump | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 146 | `rapid-mantel` | Rapid Mantel | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 147 | `read-lips` | Read Lips | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 148 | `recognize-spell` | Recognize Spell | switch-case | available_actions.at_will | Adds feat action to at-will action list |
| 149 | `sign-language` | Sign Language | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 150 | `snare-crafting` | Snare Crafting | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 151 | `specialty-crafting` | Specialty Crafting | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant, addConditionalSkillModifier | Adds pending selection grant; Adds +1 conditional skill modifier |
| 152 | `steady-balance` | Steady Balance | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 153 | `streetwise` | Streetwise | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 154 | `student-of-the-canon` | Student of the Canon | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 155 | `subtle-theft` | Subtle Theft | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier | Adds +1 conditional skill modifier |
| 156 | `survey-wildlife` | Survey Wildlife | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 157 | `terrain-expertise` | Terrain Expertise | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant, addConditionalSkillModifier | Adds pending selection grant; Adds +1 conditional skill modifier |
| 158 | `titan-wrestler` | Titan Wrestler | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, conditional_modifiers.movement | Adds +1 conditional skill modifier; Enables larger-target grapple/shove handling |
| 159 | `train-animal` | Train Animal | bulk-first-pass | applyBulkFirstPassFeat, addConditionalSkillModifier, available_actions.at_will | Adds +1 conditional skill modifier; Adds at-will feat action |
| 160 | `trick-magic-item` | Trick Magic Item | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant, addConditionalSkillModifier, available_actions.at_will | Adds pending selection grant; Adds +1 conditional skill modifier; Adds at-will feat action |
| 161 | `underwater-marauder` | Underwater Marauder | bulk-first-pass | applyBulkFirstPassFeat, conditional_modifiers.movement | Adds underwater combat/movement modifier |
| 162 | `virtuosic-performer` | Virtuosic Performer | bulk-first-pass | applyBulkFirstPassFeat, addSelectionGrant, addConditionalSkillModifier, available_actions.at_will | Adds pending selection grant; Adds +1 conditional skill modifier; Adds at-will feat action |

## Notes

- This report is generated from code shape (helpers called + buckets touched).
- During one-by-one deep refactor, each feat can be upgraded from first-pass mappings to fully rules-authoritative mechanics.
