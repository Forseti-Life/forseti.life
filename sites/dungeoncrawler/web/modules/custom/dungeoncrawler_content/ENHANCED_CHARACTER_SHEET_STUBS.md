# Enhanced Character Sheet - Code Stubs

This directory contains code stubs for the Enhanced Character Sheet system as designed in:
`docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md`

## Overview

These files are **STUBS ONLY** - they contain function signatures, interfaces, and TODO comments referencing the design document, but do not contain full implementations.

## Files Created

### Backend (PHP)

#### 1. `src/Service/CharacterStateService.php`
Character state management service implementing the CharacterState operations:
- `getState()` - Retrieve character state
- `updateHitPoints()` - HP management with bounds checking
- `addCondition()` / `removeCondition()` - Condition tracking
- `castSpell()` - Spell slot/focus point consumption
- `useAction()` / `useReaction()` - Three-action economy tracking
- `startNewTurn()` - Turn start with action reset and condition duration updates
- `updateInventory()` - Item management with bulk calculation
- `gainExperience()` - XP tracking with level-up detection

**Reference:** [CharacterState Service Pseudocode](../../../../../../docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-service-pseudocode)

#### 2. `src/Controller/CharacterStateController.php`
API controller implementing 10 RESTful endpoints:
- `GET /api/character/{id}/state` - Full character state
- `POST /api/character/{id}/update` - Batch operations
- `GET /api/character/{id}/summary` - Lightweight summary
- `POST /api/character/{id}/cast-spell` - Cast spell
- `POST /api/character/{id}/hp` - Update HP
- `POST /api/character/{id}/conditions` - Add condition
- `DELETE /api/character/{id}/conditions/{condition_id}` - Remove condition
- `POST /api/character/{id}/inventory` - Update inventory
- `POST /api/character/{id}/experience` - Gain XP
- `POST /api/character/{id}/level-up` - Level up
- `POST /api/character/{id}/start-turn` - Start turn

**Reference:** [API Endpoints Design](../../../../../../docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#api-endpoints-design)

### Frontend (TypeScript/JavaScript)

#### 3. `js/types/character-state.types.ts`
TypeScript interfaces for character state:
- `CharacterState` - Main state interface with 10+ nested structures
- `Modifier`, `Condition`, `Duration`, `Effect` - Condition system
- `Action`, `ActionEffect` - Action system
- `Spell`, `PreparedSpell` - Spellcasting system
- `Item`, `Feature`, `Feat` - Inventory and character features
- `UpdateOperation` - Optimistic locking operations

**Reference:** [Data Structure for Character State](../../../../../../docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#data-structure-for-character-state)

#### 4. `js/character-state-service.ts`
Client-side character state service with:
- WebSocket connection management
- Optimistic updates with rollback
- State synchronization across clients
- Event emitter pattern for state changes
- Update queue with batch processing
- All character operation methods matching backend service

**Reference:** [CharacterState Service Pseudocode](../../../../../../docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md#characterstate-service-pseudocode)

### Configuration

#### 5. `dungeoncrawler_content.services.yml`
Added service definition:
```yaml
dungeoncrawler_content.character_state_service:
  class: Drupal\dungeoncrawler_content\Service\CharacterStateService
  arguments: ['@database', '@current_user']
```

#### 6. `dungeoncrawler_content.routing.yml`
Added 10 API routes for character state management (all routes listed in CharacterStateController section above).

## Implementation Status

🔴 **NOT IMPLEMENTED** - All files contain only stubs with TODO comments.

### To Implement

Each TODO comment references the specific section of the design document. To implement:

1. Read the referenced section in the design document
2. Implement the functionality as described
3. Add proper error handling
4. Write unit tests
5. Update TODO comments or remove them

## Design Document Reference

All stubs reference specific sections of:
`docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md`

This ensures:
- ✅ Clear implementation requirements
- ✅ Consistent structure with design
- ✅ Easy to find specifications
- ✅ Traceability from code to design

## Next Steps

1. **Review Design Document** - Understand the full system design
2. **Implement Backend Service** - Start with CharacterStateService methods
3. **Implement API Controller** - Add endpoints one at a time
4. **Implement Frontend Service** - Create client-side state management
5. **Add WebSocket Support** - Implement real-time sync (see design doc)
6. **Create UI Components** - Build React components for character sheet
7. **Testing** - Unit tests, integration tests, E2E tests
8. **Documentation** - API docs, component docs, user guide

## Additional Resources

- **Design Document:** `docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md`
- **Database Schema:** `docs/dungeoncrawler/database-schema-design.md`
- **Existing Character Manager:** `src/Service/CharacterManager.php`
- **Existing API Controller:** `src/Controller/CharacterApiController.php`

## Notes

- All backend code follows Drupal 11 coding standards
- Frontend uses TypeScript for type safety
- API follows RESTful conventions
- WebSocket protocol design included in design doc but not stubbed (requires separate infrastructure)
- Optimistic locking prevents race conditions via version field

---

**Created:** 2026-02-13  
**Status:** Stubs Only - Not Implemented  
**Design Version:** 1.0
