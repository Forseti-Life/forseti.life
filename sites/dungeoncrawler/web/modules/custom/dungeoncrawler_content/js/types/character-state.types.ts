/**
 * @file
 * CharacterState TypeScript interfaces.
 * 
 * Based on the design document:
 * docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md
 * 
 * @see docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-object
 */

/**
 * Main character state interface.
 */
export interface CharacterState {
  // Identity
  characterId: string;
  userId: string;
  campaignId?: string;
  
  // Basic Info
  basicInfo: {
    name: string;
    level: number;
    experiencePoints: number;
    ancestry: string;
    heritage: string;
    background: string;
    class: string;
    alignment: string;
    deity?: string;
    age?: number;
    appearance?: string;
    personality?: string;
  };
  
  // Ability Scores
  abilities: {
    strength: number;
    dexterity: number;
    constitution: number;
    intelligence: number;
    wisdom: number;
    charisma: number;
  };
  
  // Hit Points and Resources
  resources: {
    hitPoints: {
      current: number;
      max: number;
      temporary: number;
    };
    stamina?: {
      current: number;
      max: number;
    };
    resolve?: {
      current: number;
      max: number;
    };
    heroPoints: {
      current: number;
      max: number;
    };
    focusPoints?: {
      current: number;
      max: number;
    };
    spellSlots?: {
      [level: string]: {
        current: number;
        max: number;
      };
    };
  };
  
  // Defenses
  defenses: {
    armorClass: {
      base: number;
      flatFooted: number;
      touch: number;
      modifiers: Modifier[];
    };
    savingThrows: {
      fortitude: {
        base: number;
        modifiers: Modifier[];
      };
      reflex: {
        base: number;
        modifiers: Modifier[];
      };
      will: {
        base: number;
        modifiers: Modifier[];
      };
    };
    perception: {
      base: number;
      modifiers: Modifier[];
    };
  };
  
  // Active Conditions
  conditions: Condition[];
  
  // Actions and Abilities
  actions: {
    threeActionEconomy: {
      actionsRemaining: number;
      reactionAvailable: boolean;
    };
    availableActions: Action[];
  };
  
  // Spells
  spells: {
    spellcastingTradition?: 'arcane' | 'divine' | 'occult' | 'primal';
    spellcastingType?: 'prepared' | 'spontaneous';
    spellAttackBonus: number;
    spellDC: number;
    knownSpells: Spell[];
    preparedSpells?: PreparedSpell[];
    focusSpells: Spell[];
    cantrips: Spell[];
  };
  
  // Skills
  skills: {
    [skillName: string]: {
      proficiencyRank: 'untrained' | 'trained' | 'expert' | 'master' | 'legendary';
      bonus: number;
      modifiers: Modifier[];
    };
  };
  
  // Inventory
  inventory: {
    worn: {
      armor?: Item;
      weapons: Item[];
      accessories: Item[];
    };
    carried: Item[];
    currency: {
      cp: number;
      sp: number;
      gp: number;
      pp: number;
    };
    totalBulk: number;
    encumbrance: 'unencumbered' | 'encumbered' | 'overloaded';
  };
  
  // Features and Feats
  features: {
    ancestryFeatures: Feature[];
    classFeatures: Feature[];
    feats: Feat[];
  };
  
  // Metadata
  metadata: {
    createdAt: string;
    updatedAt: string;
    lastSyncedAt: string;
    version: number;
  };
}

/**
 * Modifier interface.
 */
export interface Modifier {
  id: string;
  name: string;
  value: number;
  type: 'circumstance' | 'status' | 'item' | 'untyped';
  source: string;
  duration?: Duration;
}

/**
 * Condition interface.
 */
export interface Condition {
  id: string;
  name: string;
  description: string;
  severity: 'harmful' | 'beneficial' | 'neutral';
  value?: number;
  duration?: Duration;
  effects: Effect[];
  appliedAt: string;
}

/**
 * Duration interface.
 */
export interface Duration {
  type: 'rounds' | 'minutes' | 'hours' | 'days' | 'permanent' | 'encounter' | 'until_removed';
  value?: number;
  endsAt?: string;
}

/**
 * Effect interface.
 */
export interface Effect {
  type: 'modifier' | 'immunity' | 'resistance' | 'vulnerability' | 'special';
  target: string;
  value: number | string;
}

/**
 * Action interface.
 */
export interface Action {
  id: string;
  name: string;
  actionCost: number;
  isReaction: boolean;
  description: string;
  traits: string[];
  requirements?: string;
  effects: ActionEffect[];
}

/**
 * Action effect interface.
 */
export interface ActionEffect {
  type: 'damage' | 'heal' | 'condition' | 'movement' | 'custom';
  details: any;
}

/**
 * Spell interface.
 */
export interface Spell {
  id: string;
  name: string;
  level: number;
  tradition: 'arcane' | 'divine' | 'occult' | 'primal';
  school: string;
  traits: string[];
  castingTime: string;
  components: string[];
  range: string;
  area?: string;
  targets?: string;
  duration: string;
  savingThrow?: string;
  description: string;
  heightenedEffects?: { [level: string]: string };
}

/**
 * Prepared spell interface.
 */
export interface PreparedSpell {
  spellId: string;
  level: number;
  expended: boolean;
}

/**
 * Item interface.
 */
export interface Item {
  id: string;
  name: string;
  type: 'weapon' | 'armor' | 'consumable' | 'treasure' | 'tool' | 'other';
  quantity: number;
  bulk: number;
  equipped: boolean;
  description: string;
  properties?: any;
}

/**
 * Feature interface.
 */
export interface Feature {
  id: string;
  name: string;
  description: string;
  level: number;
  source: string;
}

/**
 * Feat interface.
 */
export interface Feat {
  id: string;
  name: string;
  type: 'ancestry' | 'class' | 'general' | 'skill';
  level: number;
  description: string;
  prerequisites?: string[];
  traits: string[];
}

/**
 * Update operation interface.
 */
export interface UpdateOperation {
  type: string;
  path: string;
  value: any;
  timestamp: number;
  version: number;
}
