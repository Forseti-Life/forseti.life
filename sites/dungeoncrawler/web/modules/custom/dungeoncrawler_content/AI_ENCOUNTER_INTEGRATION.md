# AI Encounter Integration Blueprint — DungeonCrawler.life

**Version**: 1.0.0  
**Last Updated**: 2026-02-18  
**Status**: Design Document (Pre-Implementation)  
**Tracker ID**: DCC-0224

## Table of Contents

1. [Overview](#overview)
2. [Architecture Goals](#architecture-goals)
3. [Integration Layer Design](#integration-layer-design)
4. [Context Building Pipeline](#context-building-pipeline)
5. [Request/Response Contracts](#requestresponse-contracts)
6. [AI Personality & Dialogue System](#ai-personality--dialogue-system)
7. [Turn Processing Flow](#turn-processing-flow)
8. [Service Architecture](#service-architecture)
9. [Data Flow Diagrams](#data-flow-diagrams)
10. [Implementation Roadmap](#implementation-roadmap)
11. [Related Documentation](#related-documentation)

## Overview

This document outlines the architecture for integrating AI-powered creature behavior into the DungeonCrawler encounter system. The integration layer connects the existing `dungeoncrawler_content` combat/encounter infrastructure with the `ai_conversation` module's API service to enable dynamic, personality-driven creature actions and dialogue during combat encounters.

**Current State:**
- `dungeoncrawler_content` has no `ai_conversation` dependency declared
- `ai_conversation` module exposes `ai_conversation.api_service` 
- Existing combat controllers: `CombatEncounterApiController`, `CombatActionController`
- Existing combat services: `CombatEngine`, `CombatEncounterStore`, `EncounterBalancer`
- Well-defined schemas: `creature.schema.json`, `encounter.schema.json`

**Target State:**
- New integration service layer that bridges combat and AI systems
- Per-turn context assembly from creature state, encounter state, and conversation history
- Structured request/response contracts for AI-generated actions and dialogue
- Non-blocking, async-ready architecture for AI API calls

## Architecture Goals

### Design Principles

1. **Separation of Concerns**: Keep AI integration logic separate from core combat mechanics
2. **Backward Compatibility**: Existing combat system continues to work without AI
3. **Performance**: AI calls should not block game flow; support async processing
4. **Extensibility**: Easy to add new AI behaviors, personality types, and action patterns
5. **Testability**: Clear contracts and mocked AI responses for unit/integration tests
6. **PF2e Compliance**: AI-generated actions must conform to Pathfinder 2E rules

### Non-Goals (Out of Scope)

- Real-time AI streaming during combat (batch processing per turn)
- Player character AI assistance (players control their own characters)
- Procedural generation of new creatures during combat
- Modification of core PF2e rules or stat calculations

## Integration Layer Design

### Component Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     Combat Encounter Layer                       │
│  (CombatEncounterApiController, CombatEngine, CombatCalculator) │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                   AI Integration Layer (NEW)                     │
│                                                                   │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────┐ │
│  │ AIEncounterTurn  │  │ CreatureContext  │  │  AIAction    │ │
│  │    Service       │  │    Builder       │  │  Validator   │ │
│  └──────────────────┘  └──────────────────┘  └──────────────┘ │
│                                                                   │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    AI Conversation Layer                         │
│             (ai_conversation.api_service)                        │
└─────────────────────────────────────────────────────────────────┘
```

### Key Services (To Be Implemented)

| Service | Responsibility | Dependencies |
|---------|---------------|--------------|
| **AIEncounterTurnService** | Orchestrates AI processing for creature turns | `ai_conversation.api_service`, `CombatEncounterStore` |
| **CreatureContextBuilder** | Assembles per-turn context payload for AI | `CombatEngine`, creature/encounter state |
| **AIActionValidator** | Validates AI-generated actions against PF2e rules | `CombatCalculator`, action definitions |
| **CreatureDialogueManager** | Manages creature conversation history and personality-driven responses | `ai_conversation.prompt_manager` |

## Context Building Pipeline

### Turn Context Assembly

For each creature's turn, the system must assemble a complete context payload that includes:

#### 1. Creature State
```json
{
  "creature_id": "uuid",
  "name": "Gribbles Rindsworth",
  "level": 3,
  "current_hp": 28,
  "max_hp": 45,
  "conditions": ["frightened_1", "wounded_1"],
  "position": {"q": 5, "r": 3},
  "actions_remaining": 3,
  "reactions_available": 1,
  "available_actions": [
    {
      "action_id": "strike_shortsword",
      "name": "Strike (Shortsword)",
      "action_cost": 1,
      "range": 1,
      "traits": ["attack"],
      "attack_bonus": 10,
      "damage": "1d6+2"
    },
    {
      "action_id": "stride",
      "name": "Stride",
      "action_cost": 1,
      "range": "speed",
      "traits": ["move"]
    }
  ],
  "personality": {
    "disposition": "hostile",
    "intelligence": "cunning",
    "combat_style": "opportunist",
    "goals": ["eliminate_intruders", "protect_treasure"],
    "fears": ["fire", "divine_magic"],
    "quirks": ["boasts_about_victories", "speaks_in_riddles"]
  }
}
```

#### 2. Encounter State
```json
{
  "encounter_id": "uuid",
  "round_number": 3,
  "active_participants": [
    {
      "entity_id": "uuid",
      "name": "Valeros",
      "type": "player",
      "position": {"q": 8, "r": 4},
      "hp_percent": 85,
      "visible_conditions": ["flanked"],
      "threat_level": "high"
    },
    {
      "entity_id": "uuid",
      "name": "Goblin Warrior",
      "type": "ally",
      "position": {"q": 6, "r": 2},
      "hp_percent": 40,
      "visible_conditions": ["wounded_2"],
      "threat_level": "moderate"
    }
  ],
  "terrain": [
    {"position": {"q": 7, "r": 3}, "type": "difficult", "feature": "rubble"}
  ],
  "visibility": {
    "light_level": "dim",
    "obscured_hexes": [{"q": 10, "r": 5}]
  }
}
```

#### 3. Conversation History
```json
{
  "conversation_id": "uuid",
  "history": [
    {
      "round": 1,
      "speaker": "Gribbles Rindsworth",
      "message": "You dare enter my domain? Prepare to face the wrath of the Magnificent!",
      "tone": "threatening"
    },
    {
      "round": 2,
      "speaker": "Valeros",
      "message": "Your treasure belongs to us now, goblin!",
      "tone": "aggressive"
    }
  ],
  "summary": "Hostile confrontation over treasure; Gribbles is protecting his hoard."
}
```

### Context Builder Implementation Strategy

The `CreatureContextBuilder` service will:

1. Query `CombatEncounterStore` for active encounter state
2. Fetch creature data from `creature.schema.json` validated entities
3. Calculate tactical analysis (threats, opportunities, range to targets)
4. Load creature conversation history from dialogue manager
5. Apply creature personality filters (what the creature knows/perceives)
6. Assemble final context payload with PF2e action options

**Key Method Signature:**
```php
public function buildTurnContext(
  string $encounter_id, 
  string $creature_instance_id
): array;
```

## Request/Response Contracts

### AI Request Format

The integration layer sends structured requests to `ai_conversation.api_service`:

```json
{
  "conversation_id": "encounter_123_creature_abc",
  "model": "claude-3-5-sonnet",
  "system_prompt": "You are controlling a PF2e creature in tactical combat. Generate valid actions and dialogue based on the creature's personality, goals, and current tactical situation. Respond with a valid JSON action plan.",
  "context": {
    "creature": { /* creature state */ },
    "encounter": { /* encounter state */ },
    "history": { /* conversation history */ }
  },
  "constraints": {
    "max_actions": 3,
    "available_action_types": ["Strike", "Stride", "Cast a Spell", "Demoralize"],
    "rules_reference": "Pathfinder 2E ORC/OGL"
  },
  "response_schema": {
    "type": "object",
    "required": ["actions", "dialogue"],
    "properties": {
      "actions": {
        "type": "array",
        "items": {
          "type": "object",
          "required": ["action_id", "targets"],
          "properties": {
            "action_id": {"type": "string"},
            "targets": {"type": "array"},
            "reasoning": {"type": "string"}
          }
        }
      },
      "dialogue": {
        "type": "object",
        "required": ["message", "tone"],
        "properties": {
          "message": {"type": "string", "maxLength": 500},
          "tone": {"type": "string", "enum": ["threatening", "taunting", "fearful", "commanding", "desperate"]}
        }
      }
    }
  }
}
```

### AI Response Format

Expected response structure from AI:

```json
{
  "success": true,
  "response": {
    "actions": [
      {
        "action_id": "stride",
        "targets": [{"type": "hex", "position": {"q": 7, "r": 4}}],
        "reasoning": "Move into flanking position to gain advantage"
      },
      {
        "action_id": "strike_shortsword",
        "targets": [{"type": "creature", "entity_id": "valeros_uuid"}],
        "reasoning": "Attack flanked target with +2 circumstance bonus"
      },
      {
        "action_id": "stride",
        "targets": [{"type": "hex", "position": {"q": 6, "r": 3}}],
        "reasoning": "Step back to avoid retaliatory attacks"
      }
    ],
    "dialogue": {
      "message": "The Magnificent sees your weakness, fool! Your flanks are as exposed as your ignorance!",
      "tone": "taunting"
    },
    "tactical_assessment": {
      "primary_threat": "valeros_uuid",
      "tactical_advantage": "flanking_available",
      "risk_level": "moderate"
    }
  },
  "metadata": {
    "tokens_used": 1523,
    "processing_time_ms": 1847,
    "model": "claude-3-5-sonnet-20241022"
  }
}
```

### Error Handling

AI service failures or invalid responses:

```json
{
  "success": false,
  "error": {
    "code": "AI_RESPONSE_INVALID",
    "message": "AI returned action not in available_actions list",
    "fallback": "use_basic_ai"
  }
}
```

**Fallback Strategy:**
1. Validate AI response against creature's available actions
2. If invalid, fall back to rule-based AI (existing simple AI logic)
3. Log error for later review/fine-tuning
4. Never block combat flow waiting for AI

## AI Personality & Dialogue System

### Personality-Driven Behavior

Creature personalities from `creature.schema.json` inform AI behavior:

| Personality Trait | Combat Behavior | Dialogue Style |
|------------------|-----------------|----------------|
| **Disposition: Hostile** | Aggressive, prioritizes damage | Threatening, taunting |
| **Intelligence: Cunning** | Uses tactics, exploits weaknesses | Clever, mocking |
| **Combat Style: Opportunist** | Seeks flanking, avoids fair fights | Cowardly when losing, boastful when winning |
| **Goals: Protect Treasure** | Guards specific hexes/items | Possessive language |
| **Fears: Fire** | Avoids fire sources, panics if burning | Fearful reactions to fire spells |

### Dialogue Context Management

**CreatureDialogueManager** responsibilities:

1. **Maintain Conversation History**: Store per-encounter dialogue threads
2. **Apply Personality Filters**: Ensure dialogue matches creature personality
3. **Track Emotional State**: Escalate/de-escalate based on combat flow
4. **Generate Context Summaries**: Compress long conversations for AI context limits

**Dialogue Triggers:**
- Turn start (creature declares action)
- Successful hit (battle cry or taunt)
- Taking damage (reaction dialogue)
- Low HP threshold (desperate or fearful)
- Ally defeated (morale change)
- Encounter end (victory or defeat speech)

### Sample Personality Profiles

#### Profile: "Cunning Opportunist"
```yaml
traits:
  - intelligent
  - cowardly
  - tactical
behavior:
  - seeks_flanking: true
  - avoids_fair_fights: true
  - targets_weakest: true
  - retreats_at_hp_percent: 40
dialogue_style:
  tone: mocking_when_winning, fearful_when_losing
  verbosity: medium
  uses_riddles: true
  boasts: true
```

#### Profile: "Brutal Berserker"
```yaml
traits:
  - aggressive
  - reckless
  - strong
behavior:
  - charges_strongest: true
  - ignores_tactics: true
  - fights_to_death: true
  - uses_power_attack: true
dialogue_style:
  tone: threatening
  verbosity: low
  simple_threats: true
  battle_cries: true
```

## Turn Processing Flow

### High-Level Turn Flow

```
1. Combat System: Creature turn starts
   ↓
2. AIEncounterTurnService.processTurn(encounter_id, creature_id)
   ↓
3. CreatureContextBuilder.buildTurnContext()
   - Gather creature state
   - Gather encounter state
   - Load conversation history
   ↓
4. Request AI decision via ai_conversation.api_service
   - Send context + constraints + response_schema
   - (Async/await or promise-based)
   ↓
5. Receive AI response
   ↓
6. AIActionValidator.validateActions(response.actions)
   - Check action_id in available_actions
   - Validate targets (range, line of sight)
   - Verify action economy (3 actions max)
   ↓
7. If valid: Execute actions via CombatEngine
   If invalid: Fall back to rule-based AI
   ↓
8. CreatureDialogueManager.recordDialogue(response.dialogue)
   ↓
9. Return turn result to combat system
```

### Detailed Sequence Diagram

```
Player               Combat            AI Integration         AI API          Combat
Turn End          Controller              Layer              Service         Engine
  |                   |                     |                   |               |
  |-- End Turn ------>|                     |                   |               |
  |                   |                     |                   |               |
  |                   |-- Next Turn ------->|                   |               |
  |                   |  (creature_id)      |                   |               |
  |                   |                     |                   |               |
  |                   |                     |-- Build Context ->|               |
  |                   |                     |                   |               |
  |                   |                     |-- AI Request ---->|               |
  |                   |                     |                   |               |
  |                   |                     |<-- AI Response ---|               |
  |                   |                     |                   |               |
  |                   |                     |-- Validate Actions->              |
  |                   |                     |                   |               |
  |                   |                     |-- Execute ----------------------->|
  |                   |                     |                   |               |
  |                   |<-- Turn Result -----|                   |               |
  |<-- Update UI ------|                     |                   |               |
```

### Error & Timeout Handling

**Timeout Strategy:**
- AI request timeout: 10 seconds (configurable)
- If timeout: Fall back to rule-based AI
- Log timeout for performance monitoring

**Validation Failures:**
- Invalid action: Remove from action list, continue with valid actions
- No valid actions: Use "Delay" action (pass turn)
- Invalid target: Select nearest valid target or skip action

**Network Failures:**
- Catch exceptions from ai_conversation.api_service
- Fall back to rule-based AI immediately
- Display user-facing message: "Creature thinking..." → "Creature acts instinctively"

## Service Architecture

### Service Definitions

#### AIEncounterTurnService

```php
namespace Drupal\dungeoncrawler_content\Service;

/**
 * Orchestrates AI-powered creature turns in combat encounters.
 */
class AIEncounterTurnService {

  /**
   * Process a creature's turn using AI.
   *
   * @param string $encounter_id
   *   The encounter UUID or ID.
   * @param string $creature_instance_id
   *   The creature instance UUID.
   *
   * @return array
   *   Turn result with actions, dialogue, and metadata.
   */
  public function processTurn(string $encounter_id, string $creature_instance_id): array;

  /**
   * Check if AI processing is enabled for a creature.
   *
   * @param array $creature_data
   *   Creature data from schema.
   *
   * @return bool
   *   TRUE if AI processing is enabled.
   */
  public function isAIEnabled(array $creature_data): bool;

  /**
   * Handle AI service failures with fallback logic.
   *
   * @param string $encounter_id
   * @param string $creature_instance_id
   * @param \Exception $exception
   *
   * @return array
   *   Fallback turn result using rule-based AI.
   */
  protected function handleAIFailure(
    string $encounter_id, 
    string $creature_instance_id, 
    \Exception $exception
  ): array;
}
```

#### CreatureContextBuilder

```php
namespace Drupal\dungeoncrawler_content\Service;

/**
 * Builds context payloads for AI-powered creature decisions.
 */
class CreatureContextBuilder {

  /**
   * Build complete turn context for AI processing.
   *
   * @param string $encounter_id
   * @param string $creature_instance_id
   *
   * @return array
   *   Context array with creature, encounter, and history data.
   */
  public function buildTurnContext(
    string $encounter_id, 
    string $creature_instance_id
  ): array;

  /**
   * Get creature state for current turn.
   */
  protected function getCreatureState(string $creature_instance_id): array;

  /**
   * Get encounter state visible to creature.
   */
  protected function getEncounterState(string $encounter_id, array $creature_data): array;

  /**
   * Get conversation history for creature.
   */
  protected function getConversationHistory(string $encounter_id, string $creature_instance_id): array;

  /**
   * Calculate tactical analysis (threats, opportunities).
   */
  protected function analyzeTacticalSituation(array $creature_state, array $encounter_state): array;
}
```

#### AIActionValidator

```php
namespace Drupal\dungeoncrawler_content\Service;

/**
 * Validates AI-generated actions against PF2e rules and creature capabilities.
 */
class AIActionValidator {

  /**
   * Validate a list of AI-generated actions.
   *
   * @param array $actions
   *   Array of action objects from AI response.
   * @param array $creature_state
   *   Current creature state with available actions.
   * @param array $encounter_state
   *   Current encounter state.
   *
   * @return array
   *   Result with 'valid' => true/false, 'actions' => filtered valid actions, 'errors' => validation errors.
   */
  public function validateActions(
    array $actions, 
    array $creature_state, 
    array $encounter_state
  ): array;

  /**
   * Validate a single action.
   */
  protected function validateAction(array $action, array $creature_state, array $encounter_state): array;

  /**
   * Validate action target (range, line of sight, validity).
   */
  protected function validateTarget(array $target, array $action_definition, array $creature_position): bool;

  /**
   * Check total action economy (must not exceed 3 actions per turn).
   */
  protected function validateActionEconomy(array $actions): bool;
}
```

#### CreatureDialogueManager

```php
namespace Drupal\dungeoncrawler_content\Service;

/**
 * Manages creature dialogue and conversation history during encounters.
 */
class CreatureDialogueManager {

  /**
   * Record dialogue from creature during turn.
   *
   * @param string $encounter_id
   * @param string $creature_instance_id
   * @param array $dialogue
   *   Dialogue object with 'message' and 'tone'.
   * @param int $round_number
   */
  public function recordDialogue(
    string $encounter_id, 
    string $creature_instance_id, 
    array $dialogue, 
    int $round_number
  ): void;

  /**
   * Get conversation history for encounter.
   */
  public function getConversationHistory(string $encounter_id, int $max_rounds = 10): array;

  /**
   * Generate context summary for long conversations.
   */
  public function generateSummary(string $encounter_id): string;

  /**
   * Apply personality filters to dialogue.
   */
  protected function applyPersonalityFilters(array $dialogue, array $personality): array;
}
```

### Service Registration

Add to `dungeoncrawler_content.services.yml`:

```yaml
services:
  # AI Integration Services (DCC-0224)
  dungeoncrawler_content.ai_encounter_turn:
    class: Drupal\dungeoncrawler_content\Service\AIEncounterTurnService
    arguments:
      - '@ai_conversation.api_service'
      - '@dungeoncrawler_content.combat_encounter_store'
      - '@dungeoncrawler_content.creature_context_builder'
      - '@dungeoncrawler_content.ai_action_validator'
      - '@dungeoncrawler_content.creature_dialogue_manager'
      - '@logger.channel.dungeoncrawler_content'

  dungeoncrawler_content.creature_context_builder:
    class: Drupal\dungeoncrawler_content\Service\CreatureContextBuilder
    arguments:
      - '@dungeoncrawler_content.combat_encounter_store'
      - '@dungeoncrawler_content.combat_engine'
      - '@entity_type.manager'

  dungeoncrawler_content.ai_action_validator:
    class: Drupal\dungeoncrawler_content\Service\AIActionValidator
    arguments:
      - '@dungeoncrawler_content.combat_calculator'
      - '@logger.channel.dungeoncrawler_content'

  dungeoncrawler_content.creature_dialogue_manager:
    class: Drupal\dungeoncrawler_content\Service\CreatureDialogueManager
    arguments:
      - '@database'
      - '@ai_conversation.prompt_manager'
      - '@logger.channel.dungeoncrawler_content'
```

### Module Dependency

Add to `dungeoncrawler_content.info.yml`:

```yaml
dependencies:
  # ... existing dependencies ...
  - ai_conversation
```

## Data Flow Diagrams

### Context Assembly Data Flow

```
┌──────────────────┐
│ Combat Encounter │
│     Store        │
└────────┬─────────┘
         │ encounter_data
         ▼
┌─────────────────────────────────────────┐
│    CreatureContextBuilder               │
│                                         │
│  1. Fetch creature state               │
│     - HP, conditions, position          │
│     - Available actions                 │
│     - Personality traits                │
│                                         │
│  2. Fetch encounter state               │
│     - Participants (visible)            │
│     - Terrain, lighting                 │
│     - Round number                      │
│                                         │
│  3. Fetch conversation history          │
│     - Previous dialogue                 │
│     - Combat events                     │
│                                         │
│  4. Calculate tactical analysis         │
│     - Threat assessment                 │
│     - Opportunity identification        │
│                                         │
│  5. Assemble final context              │
└────────┬────────────────────────────────┘
         │ context_payload
         ▼
┌──────────────────────────────────────────┐
│     ai_conversation.api_service          │
└──────────────────────────────────────────┘
```

### Action Validation Data Flow

```
┌──────────────────────────────────────────┐
│        AI Response (Raw JSON)            │
└────────┬─────────────────────────────────┘
         │ actions[]
         ▼
┌──────────────────────────────────────────┐
│       AIActionValidator                  │
│                                          │
│  For each action:                        │
│                                          │
│  1. ✓ action_id in available_actions    │
│  2. ✓ targets exist and valid           │
│  3. ✓ range check (creature → target)   │
│  4. ✓ line of sight check                │
│  5. ✓ action cost ≤ remaining actions   │
│                                          │
│  Aggregate:                              │
│  6. ✓ total action cost ≤ 3             │
│                                          │
└────────┬─────────────────────────────────┘
         │
         ├─ valid_actions[]
         │
         └─ validation_errors[]
                  │
                  ▼
         ┌────────────────────┐
         │  CombatEngine      │
         │  Execute Actions   │
         └────────────────────┘
```

## Implementation Roadmap

### Phase 1: Foundation (Week 1)
- [ ] Add `ai_conversation` dependency to `dungeoncrawler_content.info.yml`
- [ ] Create service definitions in `dungeoncrawler_content.services.yml`
- [ ] Implement `CreatureContextBuilder` service (basic context assembly)
- [ ] Unit tests for context builder

### Phase 2: AI Integration (Week 2)
- [ ] Implement `AIEncounterTurnService` (orchestration layer)
- [ ] Integrate with `ai_conversation.api_service`
- [ ] Implement request/response contracts
- [ ] Error handling and fallback logic
- [ ] Integration tests with mocked AI responses

### Phase 3: Validation & Safety (Week 3)
- [ ] Implement `AIActionValidator` service
- [ ] PF2e rule validation (action costs, range, line of sight)
- [ ] Action economy checks
- [ ] Validation unit tests with edge cases

### Phase 4: Dialogue System (Week 4)
- [ ] Implement `CreatureDialogueManager` service
- [ ] Database schema for dialogue history
- [ ] Personality filter logic
- [ ] Conversation summary generation
- [ ] Dialogue storage and retrieval tests

### Phase 5: Controller Integration (Week 5)
- [ ] Add AI turn processing to `CombatEncounterApiController`
- [ ] New API endpoint: `/api/combat/creature-turn/ai`
- [ ] Frontend integration (display creature dialogue)
- [ ] End-to-end integration tests

### Phase 6: Testing & Refinement (Week 6)
- [ ] Performance testing (AI latency, fallback timing)
- [ ] Personality profile testing (different creature types)
- [ ] Edge case testing (network failures, malformed AI responses)
- [ ] User acceptance testing
- [ ] Documentation updates

### Phase 7: Production Readiness (Week 7)
- [ ] Configuration UI for AI toggle per encounter
- [ ] Admin logging and monitoring
- [ ] Rate limiting and token usage tracking
- [ ] Production deployment checklist
- [ ] Final documentation and training materials

## Related Documentation

### Internal Documentation
- [HEXMAP_ARCHITECTURE.md](./HEXMAP_ARCHITECTURE.md) - Hex grid system and coordinate system
- [API_DOCUMENTATION.md](./API_DOCUMENTATION.md) - Combat API endpoint documentation
- `config/schemas/creature.schema.json` - Creature data structure and personality fields
- `config/schemas/encounter.schema.json` - Encounter state and combat tracking
- `src/Controller/CombatEncounterApiController.php` - Existing combat API controller
- `src/Service/CombatEngine.php` - Combat rules engine

### AI Conversation Module
- `ai_conversation/ARCHITECTURE.md` - AI conversation service architecture
- `ai_conversation/src/Service/AIApiService.php` - AI API integration service
- `ai_conversation/src/Service/PromptManager.php` - Prompt management utilities

### External References
- [Pathfinder 2E ORC License](https://paizo.com/community/blog/v5748dyo6sico) - Rules reference
- [AWS Bedrock Runtime API](https://docs.aws.amazon.com/bedrock/latest/APIReference/welcome.html) - AI service documentation
- [Claude 3.5 Sonnet Documentation](https://docs.anthropic.com/claude/docs) - AI model capabilities

---

## Appendix A: Example AI Prompts

### System Prompt Template

```text
You are controlling a creature in a Pathfinder 2nd Edition tactical combat encounter.

Creature Profile:
- Name: {creature_name}
- Type: {creature_type}
- Level: {level}
- Personality: {personality_summary}
- Goals: {goals_list}
- Fears: {fears_list}

Current Situation:
- HP: {current_hp}/{max_hp} ({hp_percent}%)
- Conditions: {conditions_list}
- Position: Hex {q},{r}
- Actions Available: {actions_remaining}/3

Tactical Analysis:
{tactical_summary}

Your task is to:
1. Choose up to {actions_remaining} actions from your available actions list
2. Target appropriate enemies or hexes
3. Generate in-character dialogue that reflects your personality and the situation
4. Provide reasoning for your tactical choices

Respond ONLY with valid JSON matching the provided schema. Do not include explanatory text outside the JSON structure.
```

### Example User Prompt

```text
Available Actions:
- Strike (Shortsword): 1 action, range 1 hex, +10 to hit, 1d6+2 damage
- Stride: 1 action, move up to 5 hexes
- Demoralize: 1 action, range 6 hexes, Intimidation check
- Take Cover: 1 action, gain +2 AC

Visible Enemies:
1. Valeros (Fighter, Level 3): Hex (8,4), ~85% HP, wielding longsword and shield, 3 hexes away
2. Kyra (Cleric, Level 3): Hex (9,6), ~100% HP, wielding mace, 5 hexes away

Allies:
1. Goblin Warrior: Hex (6,2), ~40% HP, wounded, 2 hexes away

Terrain:
- Hex (7,3): Difficult terrain (rubble)
- Hex (10,5): Obscured (fog)

What do you do this turn?
```

## Appendix B: Fallback AI Rules

When AI service is unavailable or returns invalid responses, fall back to rule-based AI:

### Simple Threat-Based AI

1. **Identify highest threat target**: Lowest HP percentage, closest, or highest damage dealer
2. **If in melee range**: Use best melee attack action
3. **If not in range**: Move toward target using Stride
4. **If low HP (<30%)**: Attempt to retreat or use defensive actions
5. **Use all 3 actions**: Fill remaining actions with Strikes, Strides, or Delay

### Personality Adjustments

- **Aggressive**: Always move toward enemies, prioritize damage
- **Defensive**: Prioritize Take Cover or defensive actions when HP < 50%
- **Cowardly**: Flee when HP < 50%, avoid strongest enemies
- **Tactical**: Use flanking, terrain, and support actions

---

**Document Status**: Design-only (DCC-0224)  
**Next Steps**: Proceed to Phase 1 implementation after stakeholder review  
**Author**: Forseti Development Team  
**Review Date**: 2026-02-18
