# AI Encounter Integration Blueprint

**Module**: dungeoncrawler_content  
**Version**: 1.0.0  
**Last Updated**: 2026-02-18  
**Status**: Phase 0 documented, Phase 1 started

## Overview

This blueprint defines how encounter orchestration in `dungeoncrawler_content` integrates with AI providers for tactical decision support, encounter narration, and non-player turn selection while preserving server-authoritative combat rules.

This document is the design artifact tracked by DCC-0224.

## Scope

### In scope

- AI-assisted NPC turn recommendation (never direct authoritative mutation)
- Encounter flavor text generation for room + turn context
- Provider abstraction for Gemini/Vertex-style model backends
- Campaign-safe context packaging (campaign ownership and encounter boundaries)
- Auditable request/response logging metadata (non-sensitive)

### Out of scope

- Replacing PF2e rules validation with model output
- Autonomous writes to encounter state without server-side validation
- Client-side direct model access

## Current Runtime Baseline

- Combat lifecycle APIs exist in `CombatEncounterApiController` (`/api/combat/start`, `/end-turn`, `/attack`, `/get`, `/set`, `/end`).
- Turn order and encounter state are persisted server-side.
- Character state APIs exist separately in `CharacterStateController`.
- AI image integration already uses provider-oriented service abstractions in this module.

## Target Integration Architecture

## 1) Encounter orchestration boundary

AI integration occurs at an orchestration layer between encounter state loading and action resolution:

1. Read encounter snapshot (campaign + participants + active turn).
2. Build constrained AI payload (allowed actions, tactical context, PF2e limits).
3. Request recommendation from provider service.
4. Validate recommendation against encounter rules.
5. Translate to canonical combat action payload.
6. Execute via existing server-authoritative combat pipeline.

## 2) Service contracts

### `EncounterAiIntegrationService` (planned)

- `buildEncounterContext(int $campaignId, int $encounterId): array`
- `requestNpcActionRecommendation(array $context): array`
- `requestEncounterNarration(array $context): array`
- `validateRecommendation(array $recommendation, array $context): array`

### `EncounterAiProviderInterface` (planned)

- `recommendNpcAction(array $context): array`
- `generateEncounterNarration(array $context): array`

Providers should map to existing AI provider patterns where practical.

## 3) Data contract: recommendation envelope

```json
{
  "version": "v1",
  "actor_instance_id": "npc-goblin-2",
  "recommended_action": {
    "type": "strike",
    "target_instance_id": "pc-kyra-1",
    "action_cost": 1,
    "parameters": {
      "weapon": "shortsword"
    }
  },
  "alternatives": [],
  "rationale": "Target is flanked and within melee reach.",
  "confidence": 0.72
}
```

Validation rules:

- `actor_instance_id` must match the active NPC turn.
- `action_cost` must fit remaining actions.
- `type` must map to server-supported action handlers.
- target and positioning must be valid in encounter state.

## Security and Governance

- Never include secrets, API keys, or user PII in AI payloads.
- Enforce campaign ownership checks before building context.
- Log provider, latency, token usage estimates, and validation outcome.
- Store prompt/response references in auditable metadata (redacted where needed).

## Phased Implementation Plan

### Phase 0 — Blueprint and route visibility (completed in this pass)

- Author this blueprint document.
- Add an architecture route/controller page summarizing integration boundaries.

### Phase 1 — Read-only orchestration scaffold (implemented)

- Create service interfaces and wire dependency injection.
- Add non-mutating endpoint for recommendation preview (admin or test permission).
- Return structured validation diagnostics without applying actions.

### Phase 2 — Controlled NPC auto-play integration

- Gate behind config flag.
- Execute only validated recommendations through existing combat action pipeline.
- Add failure fallback to deterministic rule-based action selection.

### Phase 3 — Encounter narration integration

- Add optional narration generation per round/turn.
- Persist narration events into encounter timeline metadata.

### Phase 4 — Hardening and observability

- Add functional coverage for campaign boundary validation.
- Add provider timeout/retry policy and circuit-breaker behavior.
- Add metrics dashboard panel (latency, rejection rate, fallback rate).

## Acceptance Checklist for DCC-0224

- [x] Blueprint page exists in `dungeoncrawler_content` docs.
- [x] Route-level visibility for integration architecture exists.
- [x] Service interfaces and orchestration scaffold merged.
- [x] Recommendation preview endpoint implemented.
- [ ] Validation and fallback behavior covered by tests.

## Related Files

- `dungeoncrawler_content.routing.yml`
- `src/Controller/CombatEncounterApiController.php`
- `src/Controller/ControllerArchitectureController.php`
- `src/Controller/EncounterAiPreviewController.php`
- `dungeoncrawler_content.services.yml`
- `README.md`
