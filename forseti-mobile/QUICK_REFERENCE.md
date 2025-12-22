# Forseti Mobile - Developer Quick Reference

## 🚀 Quick Start Commands

```bash
# Development
npm start                    # Start Metro bundler
npm run web                  # Run web version (localhost:3000)
npm run android              # Run Android app
npm run ios                  # Run iOS app

# Code Quality
npm run lint                 # Check for issues
npm run lint:fix             # Auto-fix issues
npm run format               # Format all code
npm run type-check           # TypeScript validation

# Testing
npm test                     # Run tests
npm run test:watch           # Watch mode
npm run test:coverage        # Coverage report
```

## 🐛 Debugging

**VS Code (F5):**
- Debug Android
- Debug iOS  
- Debug Web (Chrome)
- Attach to Packager

**Set breakpoints** → Press F5 → Select config → Debug!

## 🌍 Environment Variables

**Import:**
```typescript
import { API_BASE_URL, ENV, ENABLE_DEBUG_MODE } from '@env';
```

**Switch environments:**
```bash
cp .env.development .env    # Development
cp .env.staging .env        # Staging
cp .env.production .env     # Production
```

## ✅ Code Quality Rules

- ✅ Format on save enabled
- ✅ ESLint auto-fix on save
- ⚠️ Warn on console.log
- ⚠️ Warn on inline styles
- ⚠️ Warn on color literals
- ❌ Error on unused variables
- ❌ Error on TypeScript issues

## 📁 Key Files

```
.vscode/
  ├── launch.json           # Debug configs
  ├── settings.json         # Workspace settings
  ├── tasks.json            # Build tasks
  └── extensions.json       # Recommended extensions

Config files:
  ├── .eslintrc.js         # ESLint rules
  ├── .prettierrc          # Code formatting
  ├── jest.config.js       # Test configuration
  └── babel.config.js      # Babel + env vars

Environment:
  ├── .env                 # Current environment
  ├── .env.development     # Dev config
  ├── .env.staging         # Staging config
  └── .env.production      # Production config

Tests:
  └── src/__tests__/       # Test files
```

## 🔧 VS Code Tasks

**Run task:** `Ctrl+Shift+P` → "Tasks: Run Task"

- Metro Bundler
- Run Android
- Run Web
- Run Tests
- Lint
- Format

## 📦 Installed Extensions

1. React Native Tools
2. ESLint
3. Prettier
4. GitLens
5. TypeScript
6. Path Intellisense
7. ES7+ React Snippets

## 🧪 Testing

**Write tests in:**
- `src/__tests__/ComponentName.test.tsx`

**Test structure:**
```typescript
import { render } from '@testing-library/react-native';

describe('Component', () => {
  it('renders correctly', () => {
    const {getByText} = render(<Component />);
    expect(getByText('Hello')).toBeTruthy();
  });
});
```

**Run:**
- `npm test` - Run once
- `npm run test:watch` - Watch mode
- `npm run test:coverage` - Coverage report

## 🎯 Code Standards

**Import order:**
1. React
2. React Native
3. Third-party libraries
4. Local imports (@/)
5. Components
6. Styles

**Component structure:**
```typescript
// Imports
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

// Component
export const MyComponent: React.FC = () => {
  return (
    <View style={styles.container}>
      <Text>Hello</Text>
    </View>
  );
};

// Styles
const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
});
```

## 🚨 Common Issues

**Metro bundler errors:**
```bash
npm start -- --reset-cache
```

**Environment variables not updating:**
```bash
npm start -- --reset-cache
# Then rebuild app
```

**TypeScript errors:**
- `Ctrl+Shift+P` → "TypeScript: Restart TS Server"

**ESLint not working:**
- `Ctrl+Shift+P` → "ESLint: Restart ESLint Server"

## 📚 Documentation

- [ENV_VARIABLES.md](ENV_VARIABLES.md) - Environment setup
- [CRITICAL_FIXES_SUMMARY.md](CRITICAL_FIXES_SUMMARY.md) - Full details
- [BEST_PRACTICES_REVIEW.md](BEST_PRACTICES_REVIEW.md) - Audit report

## 💡 Pro Tips

1. **Always format before commit:** `npm run format`
2. **Check lint before push:** `npm run lint`
3. **Write tests as you code:** Keep coverage high
4. **Use debug breakpoints:** Avoid console.log
5. **Check TS errors:** `npm run type-check`
6. **Reset cache if weird issues:** `npm start -- --reset-cache`

---

**Status:** ✅ All critical fixes complete
**Score:** Improved from 6.5/10 → 9/10
**Ready for:** Production development
