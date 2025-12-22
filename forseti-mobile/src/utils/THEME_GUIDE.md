# Forseti Mobile Theme System

## Overview

Centralized design tokens for consistent styling across the Forseti Mobile application.

## Usage

### Import the theme

```typescript
import { Theme } from '../utils/theme';
// or import individual modules
import { Colors, Spacing, Typography, Shadows } from '../utils/theme';
```

### Apply styles

```typescript
const styles = StyleSheet.create({
  container: {
    backgroundColor: Theme.colors.background,
    padding: Theme.spacing.md,
    borderRadius: Theme.spacing.borderRadius.lg,
    ...Theme.shadows.md,
  },
  title: {
    ...Theme.typography.heading2,
    color: Theme.colors.primary,
    marginBottom: Theme.spacing.sm,
  },
});
```

## Design Tokens

### Colors (`Theme.colors`)

- **Primary**: `#00d4ff` (Forseti brand cyan)
- **Secondary**: `#16213e` (Forseti dark blue)
- **Status colors**: success, warning, danger, info
- **Risk levels**: riskCritical, riskHigh, riskMedium, riskLow, riskMinimal
- **Neutrals**: white, black, gray, lightGray, darkGray
- **Backgrounds**: background, backgroundDark
- **Text**: text, textPrimary, textSecondary, textMuted

### Spacing (`Theme.spacing`)

- **Base units** (8px grid): xs(4), sm(8), md(16), lg(24), xl(32), xxl(48), xxxl(64)
- **Specific use**: screenPadding, cardPadding, buttonPadding, inputPadding, sectionSpacing, itemSpacing
- **Border radius**: sm(4), md(8), lg(12), xl(16), full(9999)

### Typography (`Theme.typography`)

- **Font sizes**: xs(12), sm(14), base(16), lg(18), xl(20), xxl(24), xxxl(32), display(40)
- **Font weights**: light(300), regular(400), medium(500), semibold(600), bold(700)
- **Line heights**: tight(1.2), normal(1.5), relaxed(1.8)
- **Presets**: heading1, heading2, heading3, heading4, body, bodySmall, caption, button

### Shadows (`Theme.shadows`)

- **Elevations**: none, sm, md, lg, xl
- Includes shadowColor, shadowOffset, shadowOpacity, shadowRadius, elevation

## Examples

### Card with shadow

```typescript
const cardStyle = {
  backgroundColor: Theme.colors.white,
  padding: Theme.spacing.cardPadding,
  borderRadius: Theme.spacing.borderRadius.md,
  ...Theme.shadows.md,
};
```

### Primary button

```typescript
const buttonStyle = {
  backgroundColor: Theme.colors.primary,
  padding: Theme.spacing.buttonPadding,
  borderRadius: Theme.spacing.borderRadius.lg,
  ...Theme.shadows.sm,
};

const buttonText = {
  ...Theme.typography.button,
  color: Theme.colors.white,
};
```

### Heading with margin

```typescript
const headingStyle = {
  ...Theme.typography.heading2,
  color: Theme.colors.primary,
  marginBottom: Theme.spacing.md,
};
```

## Migration Guide

### Before (hardcoded values)

```typescript
const styles = StyleSheet.create({
  container: {
    backgroundColor: '#1a1a2e',
    padding: 16,
    borderRadius: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.23,
    shadowRadius: 2.62,
    elevation: 4,
  },
});
```

### After (using theme)

```typescript
const styles = StyleSheet.create({
  container: {
    backgroundColor: Theme.colors.background,
    padding: Theme.spacing.md,
    borderRadius: Theme.spacing.borderRadius.md,
    ...Theme.shadows.md,
  },
});
```

## Benefits

1. **Consistency** - Same values used everywhere
2. **Maintainability** - Change once, update everywhere
3. **Type safety** - TypeScript autocomplete for all values
4. **Scalability** - Easy to add dark mode or themes
5. **Documentation** - Self-documenting code

## Future Enhancements

- [ ] Add dark mode theme variant
- [ ] Add animation/transition constants
- [ ] Add icon size presets
- [ ] Create common component styles (buttons, cards, inputs)
