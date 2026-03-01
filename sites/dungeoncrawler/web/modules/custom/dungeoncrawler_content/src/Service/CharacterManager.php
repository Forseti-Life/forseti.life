<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Manages PF2e character CRUD operations and JSON storage.
 */
class CharacterManager {

  protected Connection $database;
  protected AccountProxyInterface $currentUser;
  protected UuidInterface $uuid;

  /**
   * PF2e ancestries with base stats.
   */
  const ANCESTRIES = [
    'Human' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common'], 'traits' => ['Human', 'Humanoid'], 'vision' => 'normal'],
    'Elf' => ['hp' => 6, 'size' => 'Medium', 'speed' => 30, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Elven'], 'traits' => ['Elf', 'Humanoid'], 'vision' => 'low-light vision'],
    'Dwarf' => ['hp' => 10, 'size' => 'Medium', 'speed' => 20, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Charisma', 'languages' => ['Common', 'Dwarven'], 'traits' => ['Dwarf', 'Humanoid'], 'vision' => 'darkvision'],
    'Gnome' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Charisma'], 'flaw' => 'Strength', 'languages' => ['Common', 'Gnomish', 'Sylvan'], 'traits' => ['Gnome', 'Humanoid'], 'vision' => 'low-light vision'],
    'Goblin' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom', 'languages' => ['Common', 'Goblin'], 'traits' => ['Goblin', 'Humanoid'], 'vision' => 'darkvision'],
    'Halfling' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Wisdom'], 'flaw' => 'Strength', 'languages' => ['Common', 'Halfling'], 'traits' => ['Halfling', 'Humanoid'], 'vision' => 'normal'],
    'Half-Elf' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Elven'], 'traits' => ['Human', 'Elf', 'Humanoid', 'Half-Elf'], 'vision' => 'low-light vision'],
    'Half-Orc' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Free', 'Free'], 'languages' => ['Common', 'Orcish'], 'traits' => ['Human', 'Orc', 'Humanoid', 'Half-Orc'], 'vision' => 'low-light vision'],
    'Leshy' => ['hp' => 8, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Constitution', 'Wisdom'], 'flaw' => 'Intelligence', 'languages' => ['Common', 'Sylvan'], 'traits' => ['Leshy', 'Plant', 'Humanoid'], 'vision' => 'low-light vision'],
    'Orc' => ['hp' => 10, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Strength', 'Free'], 'languages' => ['Common', 'Orcish'], 'traits' => ['Orc', 'Humanoid'], 'vision' => 'darkvision'],
    'Catfolk' => ['hp' => 8, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Wisdom', 'languages' => ['Common', 'Amurrun'], 'traits' => ['Catfolk', 'Humanoid'], 'vision' => 'low-light vision'],
    'Kobold' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Charisma'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Draconic'], 'traits' => ['Kobold', 'Humanoid'], 'vision' => 'darkvision'],
    'Ratfolk' => ['hp' => 6, 'size' => 'Small', 'speed' => 25, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Strength', 'languages' => ['Common', 'Ysoki'], 'traits' => ['Ratfolk', 'Humanoid'], 'vision' => 'low-light vision'],
    'Tengu' => ['hp' => 6, 'size' => 'Medium', 'speed' => 25, 'boosts' => ['Dexterity', 'Free'], 'languages' => ['Common', 'Tengu'], 'traits' => ['Tengu', 'Humanoid'], 'vision' => 'low-light vision'],
  ];

  /**
   * PF2e heritages for each ancestry.
   */
  const HERITAGES = [
    'Dwarf' => [
      ['id' => 'ancient-blooded', 'name' => 'Ancient-Blooded Dwarf', 'benefit' => 'Resistance to magic'],
      ['id' => 'forge', 'name' => 'Forge Dwarf', 'benefit' => 'Fire resistance'],
      ['id' => 'rock', 'name' => 'Rock Dwarf', 'benefit' => 'Extended darkvision'],
      ['id' => 'strong-blooded', 'name' => 'Strong-Blooded Dwarf', 'benefit' => 'Poison resistance'],
    ],
    'Elf' => [
      ['id' => 'arctic', 'name' => 'Arctic Elf', 'benefit' => 'Cold resistance'],
      ['id' => 'cavern', 'name' => 'Cavern Elf', 'benefit' => 'Darkvision'],
      ['id' => 'seer', 'name' => 'Seer Elf', 'benefit' => 'Detect magic cantrip'],
      ['id' => 'woodland', 'name' => 'Woodland Elf', 'benefit' => 'Climb speed'],
    ],
    'Gnome' => [
      ['id' => 'chameleon', 'name' => 'Chameleon Gnome', 'benefit' => 'Change colors'],
      ['id' => 'fey-touched', 'name' => 'Fey-Touched Gnome', 'benefit' => 'First World magic'],
      ['id' => 'sensate', 'name' => 'Sensate Gnome', 'benefit' => 'Enhanced senses'],
      ['id' => 'umbral', 'name' => 'Umbral Gnome', 'benefit' => 'Darkvision'],
    ],
    'Goblin' => [
      ['id' => 'charhide', 'name' => 'Charhide Goblin', 'benefit' => 'Fire resistance'],
      ['id' => 'irongut', 'name' => 'Irongut Goblin', 'benefit' => 'Eat anything'],
      ['id' => 'razortooth', 'name' => 'Razortooth Goblin', 'benefit' => 'Bite attack'],
      ['id' => 'snow', 'name' => 'Snow Goblin', 'benefit' => 'Cold resistance'],
    ],
    'Halfling' => [
      ['id' => 'gutsy', 'name' => 'Gutsy Halfling', 'benefit' => 'Bonus vs fear'],
      ['id' => 'hillock', 'name' => 'Hillock Halfling', 'benefit' => 'Faster healing'],
      ['id' => 'nomadic', 'name' => 'Nomadic Halfling', 'benefit' => 'Extra languages'],
      ['id' => 'twilight', 'name' => 'Twilight Halfling', 'benefit' => 'Low-light vision'],
    ],
    'Human' => [
      ['id' => 'versatile', 'name' => 'Versatile Heritage', 'benefit' => 'Extra general feat'],
    ],
  ];

  /**
   * PF2e Ancestry Feats (Level 1 feats available at character creation).
   * Organized by ancestry with feat traits, prerequisites, and effects.
   */
  const ANCESTRY_FEATS = [
    'Human' => [
      ['id' => 'adapted-cantrip', 'name' => 'Adapted Cantrip', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Choose one cantrip from the arcane, divine, occult, or primal spell list. You can cast this spelled as an innate spell at will.'],
      ['id' => 'cooperative-nature', 'name' => 'Cooperative Nature', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Aid grants a +5 circumstance bonus to skill checks instead of +2, and a +2 circumstance bonus to attack rolls or AC instead of +1.'],
      ['id' => 'general-training', 'name' => 'General Training', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain one 1st-level general feat.'],
      ['id' => 'haughty-obstinacy', 'name' => 'Haughty Obstinacy', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus to Will saves against mental effects. On a success, the effect source is temporarily immune to further attempts for 10 minutes.'],
      ['id' => 'natural-ambition', 'name' => 'Natural Ambition', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain a 1st-level class feat for your class.'],
      ['id' => 'natural-skill', 'name' => 'Natural Skill', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'You gain training in two skills of your choice.'],
      ['id' => 'unconventional-weaponry', 'name' => 'Unconventional Weaponry', 'level' => 1, 'traits' => ['Human'], 'prerequisites' => '',
        'benefit' => 'Choose one uncommon weapon. You gain access to that weapon and become trained in that weapon.'],
    ],
    'Dwarf' => [
      ['id' => 'dwarven-lore', 'name' => 'Dwarven Lore', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Trained in Crafting and Religion. Gain Crafting Lore and Dwarven Lore.'],
      ['id' => 'dwarven-weapon-familiarity', 'name' => 'Dwarven Weapon Familiarity', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'You are trained with the battle axe, pick, and warhammer, and all dwarf weapons. For proficiency, treat martial dwarf weapons as simple, and advanced dwarf weapons as martial.'],
      ['id' => 'rock-runner', 'name' => 'Rock Runner', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'You can ignore difficult terrain caused by rubble and uneven ground made of stone and earth. Acrobatics DC to Balance on narrow surfaces and uneven ground made of stone or earth reduced by 2.'],
      ['id' => 'stonecunning', 'name' => 'Stonecunning', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => '+2 circumstance bonus on Perception checks to notice unusual stonework. When not Seeking, get a check to find unusual stonework anyway.'],
      ['id' => 'unburdened-iron', 'name' => 'Unburdened Iron', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Ignore the reduction to Speed from wearing armor and reduce the encumbered speed penalty from 5 feet to only 0 feet.'],
      ['id' => 'vengeful-hatred', 'name' => 'Vengeful Hatred', 'level' => 1, 'traits' => ['Dwarf'], 'prerequisites' => '',
        'benefit' => 'Choose drow, duergar, giant, or orc when you take this feat. +1 circumstance damage per weapon die against creatures with that trait.'],
    ],
    'Elf' => [
      ['id' => 'ancestral-longevity', 'name' => 'Ancestral Longevity', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'You become trained in one skill of your choice. Once per day after rest, you can switch which skill you are trained in.'],
      ['id' => 'elven-lore', 'name' => 'Elven Lore', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Trained in Arcana and Nature. Gain Elven Lore skill.'],
      ['id' => 'elven-weapon-familiarity', 'name' => 'Elven Weapon Familiarity', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'You are trained with longbows, composite longbows, longswords, rapiers, shortbows, and composite shortbows. For proficiency, treat martial elf weapons as simple, and advanced elf weapons as martial.'],
      ['id' => 'forlorn', 'name' => 'Forlorn', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus on saving throws against emotion effects. If you roll a success on a save against an emotion effect, you get a critical success instead.'],
      ['id' => 'nimble-elf', 'name' => 'Nimble Elf', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Your Speed increases to 35 feet.'],
      ['id' => 'otherworldly-magic', 'name' => 'Otherworldly Magic', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'Choose one cantrip from the primal spell list. You can cast it as a primal innate spell at will.'],
      ['id' => 'unwavering-mien', 'name' => 'Unwavering Mien', 'level' => 1, 'traits' => ['Elf'], 'prerequisites' => '',
        'benefit' => 'When you roll a success on a saving throw against a mental effect, you critically succeed instead.'],
    ],
    'Gnome' => [
      ['id' => 'animal-accomplice', 'name' => 'Animal Accomplice', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You gain a familiar. If you retrain this feat, you lose the familiar.'],
      ['id' => 'burrow-elocutionist', 'name' => 'Burrow Elocutionist', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You can speak with burrowing animals (badgers, moles, rabbits, etc.). This doesn\'t make them friendly.'],
      ['id' => 'fey-fellowship', 'name' => 'Fey Fellowship', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Fey creatures of your level or lower automatically improve their attitude toward you by one step (hostile becomes unfriendly, unfriendly becomes indifferent, etc.).'],
      ['id' => 'first-world-magic', 'name' => 'First World Magic', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Choose one primal cantrip. You can cast it as a primal innate spell at will.'],
      ['id' => 'gnome-obsession', 'name' => 'Gnome Obsession', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Choose a Lore skill. You become trained in that skill and gain the Assurance skill feat with it.'],
      ['id' => 'gnome-weapon-familiarity', 'name' => 'Gnome Weapon Familiarity', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'Trained with glaive and kukri. For proficiency, treat martial gnome weapons as simple, advanced gnome weapons as martial.'],
      ['id' => 'illusion-sense', 'name' => 'Illusion Sense', 'level' => 1, 'traits' => ['Gnome'], 'prerequisites' => '',
        'benefit' => 'You automatically get a Perception check to disbelieve illusions you can see, with a +2 circumstance bonus.'],
    ],
    'Goblin' => [
      ['id' => 'burn-it', 'name' => 'Burn It!', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Fire damage you deal with non-magical weapons and alchemical items gains a +1 status bonus. Resistance to your fire damage is reduced by an amount equal to half your level (minimum 1).'],
      ['id' => 'city-scavenger', 'name' => 'City Scavenger', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You know the urban environment intimately. You can Subsist using Society or Survival in a settlement. You can use Society in place of Survival to Track and Seek in urban environments.'],
      ['id' => 'goblin-lore', 'name' => 'Goblin Lore', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained in Nature and Stealth. Gain Goblin Lore skill.'],
      ['id' => 'goblin-scuttle', 'name' => 'Goblin Scuttle', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'When an ally ends a move action adjacent to you, you can Step as a reaction.'],
      ['id' => 'goblin-song', 'name' => 'Goblin Song', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'You sing annoying songs. Attempt a Performance check against the Will DC of a single enemy within 30 feet. Success imposes frightened 1, critical success frightened 2. Target is then temporarily immune for 1 hour.'],
      ['id' => 'goblin-weapon-familiarity', 'name' => 'Goblin Weapon Familiarity', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained with dogslicers and horsechoppers. For proficiency, treat martial goblin weapons as simple, advanced goblin weapons as martial.'],
      ['id' => 'junk-tinker', 'name' => 'Junk Tinker', 'level' => 1, 'traits' => ['Goblin'], 'prerequisites' => '',
        'benefit' => 'Trained in Crafting. You can Craft nonmagical items from junk. Crafting DCs for such items are 5 easier, but items are shoddy (break on failed attack/check).'],
    ],
    'Halfling' => [
      ['id' => 'distracting-shadows', 'name' => 'Distracting Shadows', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'You have a knack for avoiding notice. You can use creatures one or more sizes larger than you as cover for Hide and Sneak checks.'],
      ['id' => 'halfling-lore', 'name' => 'Halfling Lore', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Trained in Acrobatics and Stealth. Gain Halfling Lore skill.'],
      ['id' => 'halfling-luck', 'name' => 'Halfling Luck', 'level' => 1, 'traits' => ['Halfling', 'Fortune'], 'prerequisites' => '',
        'benefit' => 'You can reroll a failed skill check or save once per day. Must use second result even if worse.'],
      ['id' => 'halfling-weapon-familiarity', 'name' => 'Halfling Weapon Familiarity', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Trained with sling and halfling sling staff. For proficiency, treat martial halfling weapons as simple, advanced halfling weapons as martial.'],
      ['id' => 'sure-feet', 'name' => 'Sure Feet', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'You can attempt Acrobatics checks to Balance on narrow surfaces and uneven ground without rolling. On a critical failure, you succeed instead.'],
      ['id' => 'titan-slinger', 'name' => 'Titan Slinger', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Your thrown weapons and sling range increment increased by 10 feet. Increases to 20 feet at 13th level.'],
      ['id' => 'unfettered-halfling', 'name' => 'Unfettered Halfling', 'level' => 1, 'traits' => ['Halfling'], 'prerequisites' => '',
        'benefit' => 'Success on a check to Escape is automatically a critical success. +2 circumstance bonus to checks to Escape.'],
    ],
    'Orc' => [
      ['id' => 'hold-scarred', 'name' => 'Hold-Scarred Orc', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Trained in Stealth. Gain the Terrain Stalker feat for underground terrain. If you retrain out of this feat, you lose Terrain Stalker.'],
      ['id' => 'orc-ferocity', 'name' => 'Orc Ferocity', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Once per day when reduced to 0 HP, you remain at 1 HP and become wounded 1 (or increase your wounded by 1).'],
      ['id' => 'orc-sight', 'name' => 'Orc Sight', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => 'Low-light vision',
        'benefit' => 'Your low-light vision is replaced with darkvision.'],
      ['id' => 'orc-superstition', 'name' => 'Orc Superstition', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => '+1 circumstance bonus to saving throws against magic. If you succeed at a save against a magical effect, treat it as a critical success instead (once per day).'],
      ['id' => 'orc-weapon-familiarity', 'name' => 'Orc Weapon Familiarity', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => '',
        'benefit' => 'Trained with the falchion and greataxe. For proficiency, treat martial orc weapons as simple, advanced orc weapons as martial.'],
      ['id' => 'orc-weapon-carnage', 'name' => 'Orc Weapon Carnage', 'level' => 1, 'traits' => ['Orc'], 'prerequisites' => 'Orc Weapon Familiarity',
        'benefit' => 'When you critically succeed at an attack roll with an orc weapon, you apply the weapon\'s critical specialization effect.'],
    ],
  ];

  /**
   * PF2e backgrounds with mechanical benefits.
   * Each background grants: 2 free ability boosts (player choice), 1 skill training, 1 lore, and 1 skill feat.
   */
  const BACKGROUNDS = [
    'acolyte' => [
      'id' => 'acolyte',
      'name' => 'Acolyte',
      'description' => 'You spent your early days in a religious monastery or cloister.',
      'ability_boosts' => 2, // Player chooses 2
      'skill' => 'Religion',
      'feat' => 'Student of the Canon',
      'lore' => 'Scribing Lore',
    ],
    'criminal' => [
      'id' => 'criminal',
      'name' => 'Criminal',
      'description' => 'You have a history of breaking the law and living in the criminal underworld.',
      'ability_boosts' => 2,
      'skill' => 'Stealth',
      'feat' => 'Experienced Smuggler',
      'lore' => 'Underworld Lore',
    ],
    'entertainer' => [
      'id' => 'entertainer',
      'name' => 'Entertainer',
      'description' => 'You performed before crowds, earning your coin through art and panache.',
      'ability_boosts' => 2,
      'skill' => 'Performance',
      'feat' => 'Fascinating Performance',
      'lore' => 'Theater Lore',
    ],
    'farmhand' => [
      'id' => 'farmhand',
      'name' => 'Farmhand',
      'description' => 'You grew up in a rural area, working the land and tending livestock.',
      'ability_boosts' => 2,
      'skill' => 'Athletics',
      'feat' => 'Assurance (Athletics)',
      'lore' => 'Farming Lore',
    ],
    'guard' => [
      'id' => 'guard',
      'name' => 'Guard',
      'description' => 'You served in a military, guard force, or city watch, protecting others.',
      'ability_boosts' => 2,
      'skill' => 'Intimidation',
      'feat' => 'Quick Coercion',
      'lore' => 'Legal Lore',
    ],
    'merchant' => [
      'id' => 'merchant',
      'name' => 'Merchant',
      'description' => 'You come from a family of traders, or you worked in commerce yourself.',
      'ability_boosts' => 2,
      'skill' => 'Diplomacy',
      'feat' => 'Bargain Hunter',
      'lore' => 'Mercantile Lore',
    ],
    'noble' => [
      'id' => 'noble',
      'name' => 'Noble',
      'description' => 'You were born into nobility or achieved a position of privilege.',
      'ability_boosts' => 2,
      'skill' => 'Society',
      'feat' => 'Courtly Graces',
      'lore' => 'Heraldry Lore',
    ],
    'scholar' => [
      'id' => 'scholar',
      'name' => 'Scholar',
      'description' => 'You spent years studying in libraries, academies, or under mentors.',
      'ability_boosts' => 2,
      'skill' => 'Arcana', // Or Nature, Occultism, Religion - player choice
      'feat' => 'Assurance',
      'lore' => 'Academia Lore',
    ],
    'warrior' => [
      'id' => 'warrior',
      'name' => 'Warrior',
      'description' => 'You have a history of fighting, whether through military service or personal conflict.',
      'ability_boosts' => 2,
      'skill' => 'Intimidation',
      'feat' => 'Intimidating Glare',
      'lore' => 'Warfare Lore',
    ],
  ];

  /**
   * PF2e classes with base stats.
   */
  const CLASSES = [
    'fighter' => [
      'id' => 'fighter',
      'name' => 'Fighter',
      'description' => 'A master of martial combat, skilled with a variety of weapons and armor.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Trained',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Expert in simple and martial weapons, trained in advanced weapons',
      'trained_skills' => 3,
    ],
    'rogue' => [
      'id' => 'rogue',
      'name' => 'Rogue',
      'description' => 'You are skilled and opportunistic. Using your sharp wits and quick reactions, you take advantage of your opponents\' missteps.',
      'hp' => 8,
      'key_ability' => 'Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 7 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons, rapier, sap, shortbow, and shortsword',
      'trained_skills' => 7,
    ],
    'wizard' => [
      'id' => 'wizard',
      'name' => 'Wizard',
      'description' => 'You are an eternal student of the arcane secrets of the universe, using your mastery of magic to cast powerful spells.',
      'hp' => 6,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in club, crossbow, dagger, heavy crossbow, and staff',
      'spellcasting' => 'Arcane spellcasting, Intelligence',
      'trained_skills' => 2,
    ],
    'cleric' => [
      'id' => 'cleric',
      'name' => 'Cleric',
      'description' => 'Deities work their will upon the world in infinite ways, and you serve as one of their most stalwart mortal servants.',
      'hp' => 8,
      'key_ability' => 'Wisdom',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and the favored weapon of your deity',
      'spellcasting' => 'Divine spellcasting, Wisdom',
      'trained_skills' => 2,
    ],
    'ranger' => [
      'id' => 'ranger',
      'name' => 'Ranger',
      'description' => 'Some rangers believe civilization wears down the soul, but still needs to be protected. Others say nature needs to be protected from the greedy.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Expert',
        'will' => 'Trained',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 4,
    ],
    'bard' => [
      'id' => 'bard',
      'name' => 'Bard',
      'description' => 'You are a master of artistry, a scholar of hidden secrets, and a captivating persuader.',
      'hp' => 8,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons, longsword, rapier, sap, shortbow, shortsword, and whip',
      'spellcasting' => 'Occult spellcasting, Charisma',
      'trained_skills' => 4,
    ],
    'barbarian' => [
      'id' => 'barbarian',
      'name' => 'Barbarian',
      'description' => 'Rage consumes you in battle. You delight in wreaking havoc and using powerful weapons to carve through your enemies.',
      'hp' => 12,
      'key_ability' => 'Strength',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 3,
    ],
    'champion' => [
      'id' => 'champion',
      'name' => 'Champion',
      'description' => 'You are a divine fighting servant, an instrument of your deity\'s will.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Expert',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons, and the favored weapon of your deity',
      'trained_skills' => 2,
    ],
    'druid' => [
      'id' => 'druid',
      'name' => 'Druid',
      'description' => 'You hold a deep commitment to nature and natural order. You gain primal magic through communion with nature.',
      'hp' => 8,
      'key_ability' => 'Wisdom',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Primal spellcasting, Wisdom',
      'trained_skills' => 2,
    ],
    'monk' => [
      'id' => 'monk',
      'name' => 'Monk',
      'description' => 'The strength of your fist flows from your mind and spirit. You seek perfection through discipline.',
      'hp' => 10,
      'key_ability' => 'Strength or Dexterity',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Expert',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and unarmed attacks',
      'trained_skills' => 4,
    ],
    'sorcerer' => [
      'id' => 'sorcerer',
      'name' => 'Sorcerer',
      'description' => 'You didn\'t choose to become a spellcaster—you were born one. Magic is in your blood, whether from a draconic bloodline or strange magical essence.',
      'hp' => 6,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 2 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Bloodline spellcasting, Charisma',
      'trained_skills' => 2,
    ],
    'alchemist' => [
      'id' => 'alchemist',
      'name' => 'Alchemist',
      'description' => 'You enjoy tinkering with alchemical items and formulas to discover their secrets.',
      'hp' => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Expert',
        'reflex' => 'Expert',
        'will' => 'Trained',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons and alchemical bombs',
      'trained_skills' => 3,
    ],
    'investigator' => [
      'id' => 'investigator',
      'name' => 'Investigator',
      'description' => 'You seek to uncover the truth, doggedly pursuing leads to reveal the plots of devious villains.',
      'hp' => 8,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 4 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 4,
    ],
    'oracle' => [
      'id' => 'oracle',
      'name' => 'Oracle',
      'description' => 'You draw upon divine power through your mysterious connection to a curse that grants you abilities.',
      'hp' => 8,
      'key_ability' => 'Charisma',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Divine spellcasting, Charisma',
      'trained_skills' => 3,
    ],
    'swashbuckler' => [
      'id' => 'swashbuckler',
      'name' => 'Swashbuckler',
      'description' => 'You fight with flair and style, performing daring athletic feats in the heat of battle.',
      'hp' => 10,
      'key_ability' => 'Dexterity',
      'proficiencies' => [
        'perception' => 'Expert',
        'fortitude' => 'Trained',
        'reflex' => 'Expert',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 5 + Intelligence modifier',
      'weapons' => 'Trained in simple and martial weapons',
      'trained_skills' => 5,
    ],
    'witch' => [
      'id' => 'witch',
      'name' => 'Witch',
      'description' => 'You command powerful magic through your patron, who granted you a familiar to aid your spellcasting.',
      'hp' => 6,
      'key_ability' => 'Intelligence',
      'proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
      'skills' => 'Choose 3 + Intelligence modifier',
      'weapons' => 'Trained in simple weapons',
      'spellcasting' => 'Patron spellcasting, Intelligence',
      'trained_skills' => 3,
    ],
  ];

  /**
   * PF2e Class Feats (Level 1 feats available at character creation).
   * Organized by class with feat traits, prerequisites, and effects.
   */
  const CLASS_FEATS = [
    'fighter' => [
      ['id' => 'double-slice', 'name' => 'Double Slice', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'You lash out at your foe with both weapons. Make two Strikes, one with each of your two melee weapons, each using your current multiple attack penalty. Both Strikes must have the same target. If the second Strike hits, combine their damage for the purposes of resistances and weaknesses. Apply your multiple attack penalty to the Strikes normally.'],
      ['id' => 'exacting-strike', 'name' => 'Exacting Strike', 'level' => 1, 'traits' => ['Fighter', 'Press'], 'prerequisites' => '',
        'benefit' => 'You make a controlled attack, fully accounting for your momentum. Make a melee Strike. It counts as two attacks when calculating your multiple attack penalty. If this Strike fails, you don\'t increase your multiple attack penalty.'],
      ['id' => 'point-blank-shot', 'name' => 'Point-Blank Shot', 'level' => 1, 'traits' => ['Fighter', 'Open', 'Stance'], 'prerequisites' => '',
        'benefit' => 'You take aim to pick off nearby enemies quickly. When using a ranged volley weapon while in this stance, you don\'t take the penalty for attacking within the weapon\'s volley range. When using a ranged weapon that doesn\'t have the volley trait, you gain a +2 circumstance bonus to damage rolls on attacks against targets within the weapon\'s first range increment.'],
      ['id' => 'power-attack', 'name' => 'Power Attack', 'level' => 1, 'traits' => ['Fighter', 'Flourish'], 'prerequisites' => '',
        'benefit' => 'You unleash a particularly powerful attack that clobbers your foe but leaves you a bit unbalanced. Make a melee Strike. This counts as two attacks when calculating your multiple attack penalty. If this Strike hits, you deal an extra die of weapon damage.'],
      ['id' => 'reactive-shield', 'name' => 'Reactive Shield', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'Trigger: An enemy hits you with a melee Strike. You can snap your shield into place just as you would take a blow, avoiding the hit at the last second. You immediately use the Raise a Shield action and gain your shield\'s bonus to AC. The circumstance bonus applies to your AC when you\'re determining the outcome of the triggering attack.'],
      ['id' => 'snagging-strike', 'name' => 'Snagging Strike', 'level' => 1, 'traits' => ['Fighter'], 'prerequisites' => '',
        'benefit' => 'You combine an attack with quick grappling moves to throw an enemy off balance as long as it stays in your reach. Make a Strike while wielding a weapon with the two-hand trait, using only one hand. If this Strike hits and deals damage, the target is flat-footed until the start of your next turn.'],
    ],
    'rogue' => [
      ['id' => 'nimble-dodge', 'name' => 'Nimble Dodge', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'Trigger: A creature targets you with an attack and you can see the attacker. You deftly dodge out of the way, gaining a +2 circumstance bonus to AC against the triggering attack.'],
      ['id' => 'trap-finder', 'name' => 'Trap Finder', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'You have an intuitive sense that alerts you to the dangers and presence of traps. You gain a +1 circumstance bonus to Perception checks to find traps, to AC against attacks made by traps, and to saves against traps. You can find traps that require legendary proficiency in Perception. If you critically fail a check to Disable a Device on a trap, you don\'t trigger it.'],
      ['id' => 'twin-feint', 'name' => 'Twin Feint', 'level' => 1, 'traits' => ['Rogue'], 'prerequisites' => '',
        'benefit' => 'You make a dazzling series of attacks with both weapons, using the first attack to throw your foe off guard against a second attack. Make one Strike with each of your two melee weapons, both against the same target. The target is automatically flat-footed against the second attack.'],
      ['id' => 'you-re-next', 'name' => 'You\'re Next', 'level' => 1, 'traits' => ['Rogue', 'Emotion', 'Fear', 'Mental'], 'prerequisites' => '',
        'benefit' => 'Trigger: You reduce an enemy to 0 Hit Points. After downing a foe, you menace another to sow fear. Attempt an Intimidation check with a +2 circumstance bonus to Demoralize a single creature that you can see and that can see you. This creature doesn\'t need to be within 30 feet, but it must be able to perceive the creature you just killed.'],
    ],
    'wizard' => [
      ['id' => 'counterspell', 'name' => 'Counterspell', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'Trigger: A creature Casts a Spell that you have prepared. When a foe Casts a Spell and you can see its manifestations, you can use your own magic to counter it. You expend a prepared spell to counter the triggering creature\'s casting of that same spell. You lose your spell slot as if you had cast 

the triggering spell. You then attempt to counteract the triggering spell.'],
      ['id' => 'eschew-materials', 'name' => 'Eschew Materials', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You can use clever workarounds to replicate the arcane essence of certain materials. When Casting a Spell that requires material components, you can provide these material components without a spell component pouch by drawing intricate replacement sigils in the air. Unlike when providing somatic components, you still must have a hand completely free. This doesn\'t remove the need for any materials listed in the spell\'s cost entry.'],
      ['id' => 'familiar', 'name' => 'Familiar', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => '',
        'benefit' => 'You make a pact with a creature that serves you and assists your spellcasting. You gain a familiar.'],
      ['id' => 'hand-of-the-apprentice', 'name' => 'Hand of the Apprentice', 'level' => 1, 'traits' => ['Wizard'], 'prerequisites' => 'Universalist wizard',
        'benefit' => 'You can magically hurl your weapon at your foe. You gain the Hand of the Apprentice arcane school spell. If you don\'t already have one, you gain a focus pool of 1 Focus Point, which you can Refocus by studying your spellbook.'],
      ['id' => 'reach-spell', 'name' => 'Reach Spell', 'level' => 1, 'traits' => ['Concentrate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You extend your spell\'s range. If the next action you use is to Cast a Spell that has a range, increase that spell\'s range by 30 feet. As is standard for increasing spell ranges, if the spell normally has a range of touch, you extend its range to 30 feet.'],
      ['id' => 'widen-spell', 'name' => 'Widen Spell', 'level' => 1, 'traits' => ['Manipulate', 'Metamagic', 'Wizard'], 'prerequisites' => '',
        'benefit' => 'You manipulate the energy of your spell, causing it to affect a wider area. If the next action you use is to Cast a Spell that has an area of a burst, cone, or line and doesn\'t have a duration, increase the area of that spell. Add 5 feet to the radius of a burst that normally has a radius of at least 10 feet (a burst with a smaller radius is not affected). Add 5 feet to the length of a cone or line that is normally 15 feet long or smaller, and add 10 feet to the length of a larger cone or line.'],
    ],
    'ranger' => [
      ['id' => 'animal-companion', 'name' => 'Animal Companion', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'You gain the service of a young animal companion that travels with you and obeys your commands. The rules for animal companions appear on page 214.'],
      ['id' => 'crossbow-ace', 'name' => 'Crossbow Ace', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'Your extensive practice with the crossbow has helped you develop an eye for trajectory. When you use a crossbow, the Quick Draw action also reloads the crossbow. When your crossbow is loaded, you can reload without drawing weapon hand.'],
      ['id' => 'hunted-shot', 'name' => 'Hunted Shot', 'level' => 1, 'traits' => ['Flourish', 'Ranger'], 'prerequisites' => '',
        'benefit' => 'You carefully track a target and then launch two arrows in rapid succession. Make two Strikes against your prey with your ranged weapon, or one Strike if your weapon has the volley trait. If both hit, combine their damage for resistances and weaknesses. Apply your multiple attack penalty to both. This attack counts as two attacks for your multiple attack penalty.'],
      ['id' => 'monster-hunter', 'name' => 'Monster Hunter', 'level' => 1, 'traits' => ['Ranger'], 'prerequisites' => '',
        'benefit' => 'You swear to hunt down a specific type of creature. Choose one of the following monster types: aberration, animal, beast, construct, dragon, elemental, fey, fungus, giant, humanoid, ooze, or undead. You gain a +2 circumstance bonus to Recall Knowledge checks and Investigation checks against creatures with this trait.'],
      ['id' => 'twin-takedown', 'name' => 'Twin Takedown', 'level' => 1, 'traits' => ['Flourish', 'Ranger'], 'prerequisites' => '',
        'benefit' => 'You swiftly move from one opponent to the next. Make two Strikes, each against a different target and with a different weapon. The second Strike takes the normal multiple attack penalty, but the Double Slice ability applies.'],
    ],
  ];

  /**
   * PF2e Spells database (Cantrips and 1st level spells).
   * Organized by tradition (Arcane, Divine, Occult, Primal).
   */
  const SPELLS = [
    'arcane' => [
      // Cantrips (Level 0)
      'cantrips' => [
        ['id' => 'acid-splash', 'name' => 'Acid Splash', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Acid', 'Attack', 'Cantrip', 'Evocation'],
          'description' => 'You splash a glob of acid that deals 1d6 acid damage plus 1 splash damage. On a critical hit, the target takes 2 splash damage instead of 1.'],
        ['id' => 'chill-touch', 'name' => 'Chill Touch', 'level' => 0, 'school' => 'Necromancy', 'cast' => '2 actions', 'range' => 'touch', 'traits' => ['Cantrip', 'Necromancy', 'Negative'],
          'description' => 'Your touch does 1d4 negative damage and 1 persistent negative damage. The target\'s healing from positive energy is reduced by half until the persistent damage ends.'],
        ['id' => 'daze', 'name' => 'Daze', 'level' => 0, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '60 feet', 'traits' => ['Cantrip', 'Enchantment', 'Mental', 'Nonlethal'],
          'description' => 'You cloud the target\'s mind. The target must attempt a Will save. Success: 1d6 mental damage. Critical Failure: 4d6 mental damage and stunned 1.'],
        ['id' => 'detect-magic', 'name' => 'Detect Magic', 'level' => 0, 'school' => 'Divination', 'cast' => '2 actions', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Detection', 'Divination'],
          'description' => 'You send out a pulse that registers the presence of magic. Detects magic auras within 30 feet and reveals their school and strength.'],
        ['id' => 'electric-arc', 'name' => 'Electric Arc', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Cantrip', 'Electricity', 'Evocation'],
          'description' => 'An arc of lightning leaps from you to up to two targets. Each target takes 1d4 electricity damage (basic Reflex save).'],
        ['id' => 'ghost-sound', 'name' => 'Ghost Sound', 'level' => 0, 'school' => 'Illusion', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Auditory', 'Cantrip', 'Illusion'],
          'description' => 'You create an auditory illusion of simple sounds. The sound can be as loud as four normal humans talking.'],
        ['id' => 'light', 'name' => 'Light', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => 'touch', 'duration' => 'until your next daily preparations', 'traits' => ['Cantrip', 'Evocation', 'Light'],
          'description' => 'The object glows, shedding bright light in a 20-foot radius (and dim light for the next 20 feet).'],
        ['id' => 'mage-hand', 'name' => 'Mage Hand', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Evocation'],
          'description' => 'You create a floating, disembodied hand. It can manipulate objects (lift up to 1 Bulk, but can\'t attack).'],
        ['id' => 'prestidigitation', 'name' => 'Prestidigitation', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '10 feet', 'duration' => 'sustained', 'traits' => ['Cantrip', 'Evocation'],
          'description' => 'Simple magical effects: create harmless sensory effects, lift up to 1 Bulk, color/clean/soil objects, chill/warm/flavor food.'],
        ['id' => 'produce-flame', 'name' => 'Produce Flame', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Evocation', 'Fire'],
          'description' => 'A small ball of flame appears in your hand. You can throw it as a ranged attack that deals 1d4 fire damage plus 1 splash fire damage.'],
        ['id' => 'ray-of-frost', 'name' => 'Ray of Frost', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '120 feet', 'traits' => ['Attack', 'Cantrip', 'Cold', 'Evocation'],
          'description' => 'You blast an icy ray. The ray deals 1d4 cold damage. On a critical hit, the target is slowed 1 until the end of your next turn.'],
        ['id' => 'read-aura', 'name' => 'Read Aura', 'level' => 0, 'school' => 'Divination', 'cast' => '1 minute', 'traits' => ['Cantrip', 'Detection', 'Divination'],
          'description' => 'You study the aura of one object or creature to learn its magical, religious, or alignment qualities.'],
        ['id' => 'shield', 'name' => 'Shield', 'level' => 0, 'school' => 'Abjuration', 'cast' => '1 action', 'duration' => 'until the start of your next turn', 'traits' => ['Abjuration', 'Cantrip', 'Force'],
          'description' => 'You raise a magical shield. Gain a +1 circumstance bonus to AC. You can Shield Block with your shield spell (Hardness 5, 20 HP).'],
        ['id' => 'tanglefoot', 'name' => 'Tanglefoot', 'level' => 0, 'school' => 'Conjuration', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Conjuration'],
          'description' => 'A mass of sticky webbing clings to the target. The target takes a -10-foot status penalty to Speed for 1 round (critical hit: immobilized for 1 round then -10 Speed for 1 round).'],
        ['id' => 'telekinetic-projectile', 'name' => 'Telekinetic Projectile', 'level' => 0, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Attack', 'Cantrip', 'Evocation'],
          'description' => 'You hurl a loose object at the target. The object deals 1d6 bludgeoning, piercing, or slashing damage (your choice).'],
      ],
      // 1st Level Spells
      '1st' => [
        ['id' => 'burning-hands', 'name' => 'Burning Hands', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions', 'area' => '15-foot cone', 'traits' => ['Evocation', 'Fire'],
          'description' => 'Gouts of flame rush from your hands. Creatures in the area take 2d6 fire damage (basic Reflex save).'],
        ['id' => 'charm', 'name' => 'Charm', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 hour', 'traits' => ['Emotion', 'Enchantment', 'Incapacitation', 'Mental'],
          'description' => 'The target views you as a good friend. They don\'t necessarily agree with everything you say, but they respond positively to you. Critical Success: The target is unaffected and aware you tried to charm it. Success: Unaffected. Failure: Attitude improves by one step. Critical Failure: Improves by two steps.'],
        ['id' => 'color-spray', 'name' => 'Color Spray', 'level' => 1, 'school' => 'Illusion', 'cast' => '2 actions', 'area' => '15-foot cone', 'traits' => ['Illusion', 'Incapacitation', 'Visual'],
          'description' => 'Vivid colors overwhelm creatures in the area. Each creature must attempt a Will save. Critical Success: Unaffected. Success: Dazzled until the end of your next turn. Failure: Stunned 1, blinded and dazzled for 1 round. Critical Failure: Stunned for 1 round and blinded for 1 minute.'],
        ['id' => 'fear', 'name' => 'Fear', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'traits' => ['Emotion', 'Enchantment', 'Fear', 'Mental'],
          'description' => 'You plant fear in the target. It must attempt a Will save. Critical Success: Unaffected. Success: Frightened 1. Failure: Frightened 2. Critical Failure: Frightened 3 and fleeing for 1 round.'],
        ['id' => 'grease', 'name' => 'Grease', 'level' => 1, 'school' => 'Conjuration', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Conjuration'],
          'description' => 'You conjure grease in a 10-foot square. Creatures entering or standing in the grease must succeed at Acrobatics check (DC = spell DC) or fall prone. A creature can avoid this by Balancing through it.'],
        ['id' => 'mage-armor', 'name' => 'Mage Armor', 'level' => 1, 'school' => 'Abjuration', 'cast' => '2 actions', 'duration' => 'until your next daily preparations', 'traits' => ['Abjuration', 'Force'],
          'description' => 'You ward yourself with shimmering magical energy, gaining a +1 item bonus to AC and a +1 item bonus to saves against magic missiles. While wearing mage armor, you use your unarmored proficiency.'],
        ['id' => 'magic-missile', 'name' => 'Magic Missile', 'level' => 1, 'school' => 'Evocation', 'cast' => '1 to 3 actions', 'range' => '120 feet', 'traits' => ['Evocation', 'Force'],
          'description' => 'You send a dart of force streaking toward a creature. The dart automatically hits and deals 1d4+1 force damage. If you Cast this Spell using 2 actions, create two darts. If you Cast this Spell using 3 actions, create three darts.'],
        ['id' => 'ray-of-enfeeblement', 'name' => 'Ray of Enfeeblement', 'level' => 1, 'school' => 'Necromancy', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Attack', 'Necromancy'],
          'description' => 'A ray that saps the target\'s strength. The target takes a -2 status penalty to Strength-based attack rolls, damage rolls, Athletics checks, and Strength-based skill checks.'],
        ['id' => 'shocking-grasp', 'name' => 'Shocking Grasp', 'level' => 1, 'school' => 'Evocation', 'cast' => '2 actions', 'range' => 'touch', 'traits' => ['Attack', 'Electricity', 'Evocation'],
          'description' => 'You shroud your hands in a crackling field of lightning. Make a melee spell attack. On a hit, the target takes 2d12 electricity damage. If the target is wearing metal armor or is made of metal, you gain a +1 circumstance bonus to your attack roll with shocking grasp.'],
        ['id' => 'sleep', 'name' => 'Sleep', 'level' => 1, 'school' => 'Enchantment', 'cast' => '2 actions', 'range' => '30 feet', 'duration' => '1 minute', 'traits' => ['Enchantment', 'Incapacitation', 'Mental', 'Sleep'],
          'description' => 'Each creature in a 5-foot burst must attempt a Will save. Critical Success: Unaffected. Success: -1 status penalty to Perception checks for identifying creatures until the end of your next turn. Failure: Falls unconscious. Critical Failure: Falls unconscious for 1 minute.'],
        ['id' => 'true-strike', 'name' => 'True Strike', 'level' => 1, 'school' => 'Divination', 'cast' => '1 action', 'duration' => 'until the end of your turn', 'traits' => ['Divination', 'Fortune'],
          'description' => 'A glimpse into the future ensures your next blow strikes true. The next attack roll you make before the end of your turn gains a +10 circumstance bonus.'],
      ],
    ],
  ];

  /**
   * PF2E starting equipment by class.
   * Each class entry lists the standard starting gear at level 1.
   * Items reference IDs from EquipmentCatalogService::CATALOG.
   */
  const STARTING_EQUIPMENT = [
    'fighter' => [
      'weapons'  => ['longsword', 'dagger'],
      'armor'    => ['scale-mail'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Scale mail + longsword + dagger is the standard fighter kit.',
    ],
    'rogue' => [
      'weapons'  => ['shortsword', 'dagger', 'shortbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Light armor, quick weapons, climbing gear.',
    ],
    'wizard' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded', 'oil-pint'],
      'currency' => ['gp' => 15],
      'note'     => 'Wizards rely on spells; minimal mundane kit.',
    ],
    'cleric' => [
      'weapons'  => ['mace', 'dagger'],
      'armor'    => ['chain-shirt'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Chain shirt and mace, standard healer loadout.',
    ],
    'ranger' => [
      'weapons'  => ['shortsword', 'dagger', 'longbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Longbow + light melee; ranger wilderness kit.',
    ],
    'bard' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Rapier + leather for the performative combatant.',
    ],
    'barbarian' => [
      'weapons'  => ['greataxe', 'dagger'],
      'armor'    => ['hide-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Two-handed greataxe and hide armor.',
    ],
    'champion' => [
      'weapons'  => ['longsword', 'dagger'],
      'armor'    => ['breastplate'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Heavy warrior of faith; breastplate + longsword.',
    ],
    'druid' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => ['hide-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Nature magic; hide armor, simple weapons.',
    ],
    'monk' => [
      'weapons'  => ['dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Unarmed combatant; no armor needed.',
    ],
    'sorcerer' => [
      'weapons'  => ['dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded'],
      'currency' => ['gp' => 15],
      'note'     => 'Innate magic user; light travel kit.',
    ],
    'alchemist' => [
      'weapons'  => ['dagger', 'crossbow'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'waterskin', 'flint-steel'],
      'currency' => ['gp' => 15],
      'note'     => 'Crossbow + dagger + leather for the field alchemist.',
    ],
    'investigator' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['studded-leather'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'lantern-hooded'],
      'currency' => ['gp' => 15],
      'note'     => 'Finesse + studded leather for the analytical combatant.',
    ],
    'oracle' => [
      'weapons'  => ['mace', 'dagger'],
      'armor'    => ['chain-shirt'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'torch', 'waterskin'],
      'currency' => ['gp' => 15],
      'note'     => 'Divine conduit; chain shirt and mace.',
    ],
    'swashbuckler' => [
      'weapons'  => ['rapier', 'dagger'],
      'armor'    => ['leather-armor'],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'rope', 'chalk'],
      'currency' => ['gp' => 15],
      'note'     => 'Finesse fighter; rapier and light armor.',
    ],
    'witch' => [
      'weapons'  => ['staff', 'dagger'],
      'armor'    => [],
      'gear'     => ['backpack', 'bedroll', 'rations-week', 'chalk', 'oil-pint'],
      'currency' => ['gp' => 15],
      'note'     => 'Patron spellcaster; minimal kit.',
    ],
  ];

  public function __construct(Connection $database, AccountProxyInterface $current_user, UuidInterface $uuid) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->uuid = $uuid;
  }

  /**
   * Get all characters for the current user.
   */
  public function getUserCharacters(?int $uid = NULL): array {
    $uid = $uid ?? (int) $this->currentUser->id();
    return $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.uid', $uid)
      ->condition('c.campaign_id', 0)
      // Archived characters are hidden from the roster and selection flows.
      ->condition('c.status', 2, '<>')
      ->orderBy('c.changed', 'DESC')
      ->execute()
      ->fetchAll();
  }

  /**
   * Load a single character by ID.
   */
  public function loadCharacter(int $id): ?object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.id', $id)
      ->execute()
      ->fetchObject();

    return $record ?: NULL;
  }

  /**
   * Load a character by UUID.
   */
  public function loadByUuid(string $uuid): ?object {
    $record = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('c.uuid', $uuid)
      ->execute()
      ->fetchObject();

    return $record ?: NULL;
  }

  /**
   * Create a new character with full PF2e JSON.
   */
  public function createCharacter(string $name, string $ancestry, string $class, array $options = []): int {
    $character_data = $this->buildCharacterJson($name, $ancestry, $class, $options);
    $hot = $this->extractHotColumnValues($character_data);

    $now = \Drupal::time()->getRequestTime();
    $instanceId = $this->uuid->generate();
    $id = $this->database->insert('dc_campaign_characters')
      ->fields([
        'uuid' => $instanceId,
        'campaign_id' => 0,
        'character_id' => 0,
        'instance_id' => $instanceId,
        'uid' => (int) $this->currentUser->id(),
        'name' => $name,
        'level' => 1,
        'ancestry' => $ancestry,
        'class' => $class,
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => $hot['experience_points'],
        'position_q' => 0,
        'position_r' => 0,
        'last_room_id' => '',
        'character_data' => json_encode($character_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'status' => 1,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    return (int) $id;
  }

  /**
   * Update character data.
   */
  public function updateCharacter(int $id, array $fields): bool {
    $fields['changed'] = \Drupal::time()->getRequestTime();
    $updated = $this->database->update('dc_campaign_characters')
      ->fields($fields)
      ->condition('id', $id)
      ->execute();

    return (bool) $updated;
  }

  /**
   * Delete a character.
   */
  public function deleteCharacter(int $id): bool {
    $deleted = $this->database->delete('dc_campaign_characters')
      ->condition('id', $id)
      ->condition('uid', (int) $this->currentUser->id())
      ->condition('campaign_id', 0)
      ->execute();

    return (bool) $deleted;
  }

  /**
   * Build a full PF2e character JSON structure.
   */
  public function buildCharacterJson(string $name, string $ancestry_name, string $class_name, array $options = []): array {
    $ancestry = self::ANCESTRIES[$ancestry_name] ?? self::ANCESTRIES['Human'];
    $class = self::CLASSES[$class_name] ?? self::CLASSES['fighter'];

    // Default ability scores (10 base + ancestry boosts).
    $abilities = [
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
    ];

    // Apply manual ability boosts if provided.
    if (!empty($options['ability_boosts'])) {
      foreach ($options['ability_boosts'] as $ability => $boost) {
        $key = strtolower($ability);
        if (isset($abilities[$key])) {
          $abilities[$key] += (int) $boost;
        }
      }
    }
    else {
      // Apply ancestry boosts automatically.
      foreach ($ancestry['boosts'] as $boost) {
        if ($boost !== 'Free') {
          $key = strtolower($boost);
          if (isset($abilities[$key])) {
            $abilities[$key] += 2;
          }
        }
      }
      // Apply ancestry flaw.
      if (!empty($ancestry['flaw'])) {
        $key = strtolower($ancestry['flaw']);
        if (isset($abilities[$key])) {
          $abilities[$key] -= 2;
        }
      }
    }

    // Calculate modifiers.
    $ability_block = [];
    foreach ($abilities as $key => $score) {
      $ability_block[$key] = [
        'score' => $score,
        'modifier' => intdiv($score - 10, 2),
      ];
    }

    $con_mod = $ability_block['constitution']['modifier'];
    $dex_mod = $ability_block['dexterity']['modifier'];
    $wis_mod = $ability_block['wisdom']['modifier'];

    $hp = $ancestry['hp'] + $class['hp'] + $con_mod;

    $class_proficiencies = [
      'perception' => (string) ($class['proficiencies']['perception'] ?? $class['perception'] ?? 'Trained'),
      'fortitude' => (string) ($class['proficiencies']['fortitude'] ?? $class['fortitude'] ?? 'Trained'),
      'reflex' => (string) ($class['proficiencies']['reflex'] ?? $class['reflex'] ?? 'Trained'),
      'will' => (string) ($class['proficiencies']['will'] ?? $class['will'] ?? 'Trained'),
    ];

    // Proficiency bonus at level 1 = 2 + level for trained, 4 + level for expert.
    $trained = 3; // 2 + level(1)
    $expert = 5;  // 4 + level(1)

    $prof_to_bonus = function (?string $prof, int $ability_mod) use ($trained, $expert): int {
      return match((string) $prof) {
        'Expert' => $expert + $ability_mod,
        'Trained' => $trained + $ability_mod,
        default => $ability_mod,
      };
    };

    return [
      'pf2e_version' => '2.0',
      'character' => [
        'name' => $name,
        'player' => 'Player',
        'level' => 1,
        'experience_points' => 0,
        'hero_points' => 1,
        'ancestry' => [
          'name' => $ancestry_name,
          'heritage' => $options['heritage'] ?? '',
          'size' => $ancestry['size'],
          'speed' => $ancestry['speed'],
          'languages' => $ancestry['languages'],
          'traits' => $ancestry['traits'],
          'ancestry_features' => [
            'darkvision' => $ancestry['vision'] === 'darkvision',
            'low_light_vision' => $ancestry['vision'] === 'low-light vision',
            'hp' => $ancestry['hp'],
          ],
          'ancestry_feat' => [
            'name' => '',
            'description' => '',
          ],
        ],
        'background' => [
          'name' => $options['background'] ?? '',
          'description' => '',
          'ability_boosts' => [],
          'skill_training' => [],
          'feat' => ['name' => '', 'description' => ''],
        ],
        'class' => [
          'name' => $class_name,
          'subclass' => $options['subclass'] ?? '',
          'key_ability' => $class['key_ability'],
          'hp_per_level' => $class['hp'],
          'proficiencies' => [
            'perception' => $class_proficiencies['perception'],
            'fortitude' => $class_proficiencies['fortitude'],
            'reflex' => $class_proficiencies['reflex'],
            'will' => $class_proficiencies['will'],
          ],
          'class_features' => [],
          'class_feats' => [],
          'skill_feats' => [],
        ],
        'ability_scores' => $ability_block,
        'hit_points' => [
          'max' => $hp,
          'current' => $hp,
          'temporary' => 0,
        ],
        'armor_class' => 10 + $dex_mod,
        'saving_throws' => [
          'fortitude' => [
            'modifier' => $prof_to_bonus($class_proficiencies['fortitude'], $con_mod),
            'proficiency' => $class_proficiencies['fortitude'],
          ],
          'reflex' => [
            'modifier' => $prof_to_bonus($class_proficiencies['reflex'], $dex_mod),
            'proficiency' => $class_proficiencies['reflex'],
          ],
          'will' => [
            'modifier' => $prof_to_bonus($class_proficiencies['will'], $wis_mod),
            'proficiency' => $class_proficiencies['will'],
          ],
        ],
        'perception' => [
          'modifier' => $prof_to_bonus($class_proficiencies['perception'], $wis_mod),
          'proficiency' => $class_proficiencies['perception'],
          'senses' => $ancestry['vision'] !== 'normal' ? [ucwords($ancestry['vision'])] : [],
        ],
        'skills' => new \stdClass(),
        'attacks' => ['melee' => [], 'ranged' => []],
        'equipment' => [
          'worn' => ['armor' => NULL, 'other' => []],
          'held' => [],
          'stowed' => [],
          'currency' => ['gold' => 15, 'silver' => 0, 'copper' => 0],
          'bulk' => ['current' => 0, 'encumbered' => 5 + $ability_block['strength']['modifier'], 'max' => 10 + $ability_block['strength']['modifier']],
        ],
        'personality' => [
          'alignment' => $options['alignment'] ?? 'Neutral',
          'deity' => $options['deity'] ?? '',
          'traits' => [],
          'backstory' => $options['backstory'] ?? '',
        ],
      ],
    ];
  }

  /**
   * Get decoded character data from a record.
   */
  public function getCharacterData(object $record): array {
    return json_decode($record->character_data, TRUE) ?? [];
  }

  /**
   * Extract hot-column values from character payload.
   *
   * Maps JSON schema fields to hot relational columns for high-frequency gameplay:
   * - hit_points.max → hp_max
   * - hit_points.current → hp_current
   * - armor_class → armor_class
   * - experience_points → experience_points
   *
   * Hot columns enable fast reads/writes for gameplay mechanics without parsing JSON.
   * See character.schema.json for field definitions and hybrid storage documentation.
   *
   * @param array $characterData
   *   Character data array (may be nested under 'character' key).
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Normalized values for hot relational columns with safe defaults.
   */
  public function extractHotColumnsFromData(array $characterData): array {
    $character = is_array($characterData['character'] ?? NULL) ? $characterData['character'] : $characterData;
    $hitPoints = is_array($character['hit_points'] ?? NULL) ? $character['hit_points'] : [];

    $hpMax = (int) ($hitPoints['max'] ?? 0);
    $hpCurrent = (int) ($hitPoints['current'] ?? $hpMax);

    return [
      'hp_current' => $hpCurrent,
      'hp_max' => $hpMax,
      'armor_class' => (int) ($character['armor_class'] ?? 10),
      'experience_points' => (int) ($character['experience_points'] ?? 0),
    ];
  }

  /**
   * Resolve hot-column values using row columns first, then JSON payload fallback.
   *
   * Implements hybrid columnar storage pattern:
   * 1. Prefer values from dedicated hot columns (fast, indexed)
   * 2. Fall back to JSON schema fields if hot columns are null/unset
   * 3. Use safe defaults if neither source has data
   *
   * This ensures compatibility with characters created before hot columns were added
   * and provides resilience if data synchronization issues occur.
   *
   * @param object $record
   *   Database record from dc_campaign_characters table.
   * @param array $characterData
   *   Parsed character_data JSON payload.
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Row-preferred hot values with JSON fallback.
   */
  public function resolveHotColumnsForRecord(object $record, array $characterData): array {
    $fromJson = $this->extractHotColumnsFromData($characterData);

    return [
      'hp_current' => (int) ($record->hp_current ?? $fromJson['hp_current']),
      'hp_max' => (int) ($record->hp_max ?? $fromJson['hp_max']),
      'armor_class' => (int) ($record->armor_class ?? $fromJson['armor_class']),
      'experience_points' => (int) ($record->experience_points ?? $fromJson['experience_points']),
    ];
  }

  /**
   * Check if a character belongs to the current user.
   */
  public function isOwner(object $record): bool {
    return (int) $record->uid === (int) $this->currentUser->id();
  }

  /**
   * Returns class data by class id.
   */
  public function getClassData(string $classId): ?array {
    return self::CLASSES[strtolower($classId)] ?? NULL;
  }

  /**
   * Returns base HP for a class with safe fallback.
   */
  public function getClassHP(string $classId): int {
    $classData = $this->getClassData($classId);
    return (int) ($classData['hp'] ?? 8);
  }

  /**
   * Extract hot relational values from a character JSON payload.
   *
   * @return array{hp_current:int,hp_max:int,armor_class:int,experience_points:int}
   *   Normalized hot-column values.
   */
  private function extractHotColumnValues(array $characterData): array {
    return $this->extractHotColumnsFromData($characterData);
  }

}
