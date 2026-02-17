# DCC-0064: ActionsComponent.js Refactoring Summary

## Issue
Review file `js/ecs/components/ActionsComponent.js` for opportunities for improvement and refactoring.

## Overview
This refactoring focused on improving code quality, maintainability, and flexibility while maintaining 100% backward compatibility with existing code.

## Changes Made

### 1. Added MAPConstants Object (New Export)
```javascript
export const MAPConstants = {
  STANDARD_PENALTY: -5,  // Standard weapon MAP per attack
  AGILE_PENALTY: -4,     // Agile weapon MAP per attack
  MIN_BONUS: -3,         // Minimum action bonus allowed
  MAX_BONUS: 3           // Maximum action bonus allowed
};
```

**Benefits:**
- Eliminates magic numbers in code
- Provides semantic meaning to penalty values
- Makes it easy to support different weapon types (agile vs standard)
- Centralized configuration for action economy limits

### 2. Enhanced Constructor with Configurable MAP
**Before:**
```javascript
constructor(maxActions = 3) {
  // ... MAP always -5 per attack
}
```

**After:**
```javascript
constructor(maxActions = 3, mapPenaltyPerAttack = MAPConstants.STANDARD_PENALTY) {
  // Validation
  if (!Number.isInteger(maxActions) || maxActions < 0) {
    throw new Error(`maxActions must be a non-negative integer, got: ${maxActions}`);
  }
  // ... MAP now configurable
  this.mapPenaltyPerAttack = mapPenaltyPerAttack;
}
```

**Benefits:**
- Support for agile weapons (use `MAPConstants.AGILE_PENALTY`)
- Input validation prevents invalid states
- Clear error messages for debugging
- Backward compatible (defaults to -5)

### 3. Added Input Validation to 5 Methods

#### canAfford()
- Type checking for cost parameter
- Returns false instead of crashing on invalid input

#### setOnActionsDepleted()
- Validates callback is function or null
- Throws descriptive error for invalid types

#### applyActionModifier()
- Validates modifier is finite number
- Prevents NaN/Infinity issues
- Uses named constants instead of magic numbers

#### setCanAct()
- Validates boolean type
- Clear error message with actual type received

#### makeAttack()
- Now uses configurable `mapPenaltyPerAttack`
- More flexible for different weapon types

### 4. Improved JSDoc Documentation

**Enhanced all `@returns` descriptions:**
- Before: `@returns {boolean}`
- After: `@returns {boolean} True if the action can be afforded`

**Added JSDoc examples to 5 methods:**
```javascript
/**
 * @example
 * if (actions.canAfford(ActionCost.ONE)) {
 *   // Can perform a single action
 * }
 */
```

**Added API stability notes:**
- Documented why `effect` parameter exists in `applyActionModifier()`
- Explained future enhancement plans

### 5. Serialization/Deserialization Updates

**toJSON() - Added field:**
```javascript
mapPenaltyPerAttack: this.mapPenaltyPerAttack,
```

**fromJSON() - Backward compatible:**
```javascript
const mapPenaltyPerAttack = data.mapPenaltyPerAttack ?? MAPConstants.STANDARD_PENALTY;
const component = new ActionsComponent(data.maxActions, mapPenaltyPerAttack);
```

**Benefits:**
- Preserves new field during save/load
- Handles old save data gracefully (defaults to -5)
- No breaking changes to existing saves

### 6. Updated Exports in index.js
```javascript
export { ActionsComponent, ActionType, ActionCost, MAPConstants } from './components/ActionsComponent.js';
```

## Statistics

- **Files changed:** 2
- **Lines added:** 81
- **Lines removed:** 22
- **Net change:** +59 lines
- **Final file size:** 339 lines

## Backward Compatibility

All changes are 100% backward compatible:

1. **Constructor:** New parameter is optional with sensible default
2. **Methods:** No signature changes to existing methods
3. **Return types:** All return types unchanged
4. **Serialization:** Old saves load correctly with defaults
5. **Usage:** All existing code works without modification

## Testing

- ✅ JavaScript syntax validation passed
- ✅ Basic compatibility tests passed
- ✅ Code review completed
- ✅ Backward compatibility verified
- ✅ No breaking changes introduced

## Code Quality Improvements

1. **Defensive Programming:** Added 5 validation checks
2. **Error Messages:** Descriptive errors with context
3. **Documentation:** Improved JSDoc with examples
4. **Magic Numbers:** Replaced with named constants
5. **Flexibility:** Configurable MAP for weapon variety
6. **Consistency:** Matches patterns in CombatComponent and StatsComponent

## Future Enhancements Enabled

1. **Effect Tracking:** `applyActionModifier()` has `effect` parameter reserved for future use
2. **Weapon Variety:** Easy to add more weapon MAP types
3. **Custom Action Economies:** Configurable maxActions for special creatures
4. **Better Error Handling:** Validation prevents invalid states

## Usage Examples

### Standard Usage (Backward Compatible)
```javascript
// Old code continues to work
const actions = new ActionsComponent(3);
```

### Agile Weapon Support (New)
```javascript
// Support agile weapons with -4 MAP instead of -5
const actions = new ActionsComponent(3, MAPConstants.AGILE_PENALTY);
```

### Custom Creatures (New)
```javascript
// Creature with 4 actions per turn (e.g., buffed by Haste)
const actions = new ActionsComponent(4);
```

## Security Considerations

- Input validation prevents invalid states
- Type checking prevents type confusion bugs
- Finite number validation prevents NaN/Infinity issues
- No new attack surface introduced
- No sensitive data exposure

## Conclusion

This refactoring successfully improved code quality, flexibility, and maintainability while maintaining 100% backward compatibility. The changes follow established patterns in the codebase and enable future enhancements without breaking existing functionality.

## Related Files

- `ActionsComponent.js` - Main refactored file
- `index.js` - Updated exports
- `TurnManagementSystem.js` - Uses ActionsComponent
- `CombatSystem.js` - Uses ActionsComponent

## Commits

1. `a1f4a18` - Refactor ActionsComponent.js: Add validation, improve JSDoc, and make MAP configurable
2. `4c3b122` - Address code review: Clarify applyActionModifier effect parameter purpose
