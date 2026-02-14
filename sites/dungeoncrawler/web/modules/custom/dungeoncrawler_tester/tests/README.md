# Dungeon Crawler Tester - Tests

Comprehensive test suite targeting the Dungeon Crawler content module (tests live in the dungeoncrawler_tester module).

## 📚 Documentation

**Complete testing strategy and design:**
- [Testing Strategy Design Document](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md)
- [Testing Quick Start Guide](../../../../docs/dungeoncrawler/testing/README.md)
- [Documentation Index](../../../../docs/dungeoncrawler/issues/testing-strategy-index.md)

## 🏗️ Structure

```
tests/
├── src/
│   ├── Unit/                    # Unit tests (service calculators, traits)
│   └── Functional/              # Browser tests (routes + controllers)
├── fixtures/                    # Test data files
│   ├── characters/              # Character test data
│   ├── schemas/                 # Schema test data
│   └── pf2e_reference/          # PF2e reference data
├── phpunit.xml                  # PHPUnit configuration (in module root)
└── README.md                    # This file
```

## 🚀 Running Tests

### All Tests
```bash
cd sites/dungeoncrawler
./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
```

### Specific Test Suite
```bash
# Unit tests only (fast)
./vendor/bin/phpunit --testsuite=unit

# Functional tests only
./vendor/bin/phpunit --testsuite=functional
```

### Specific Group
```bash
# PF2e rules validation tests
./vendor/bin/phpunit --group=pf2e-rules

# Character creation tests
./vendor/bin/phpunit --group=character-creation
```

### Single Test File
```bash
./vendor/bin/phpunit web/modules/custom/dungeoncrawler_tester/tests/src/Unit/Service/CharacterCalculatorTest.php
```

### With Coverage Report
```bash
./vendor/bin/phpunit --coverage-html tests/coverage
# Open tests/coverage/index.html in browser
```

## 📊 Test Status

**Current State:** 🚧 Functional route/controller coverage is implemented; unit tests remain stubbed with `markTestIncomplete()`.

- Functional tests exercise route existence, access control, and basic content for public/admin/API endpoints.
- Unit tests outline calculators and PF2e rules but are not yet implemented.
- Fixtures for characters, schemas, and PF2e references are ready for unit test data providers.

**Next Steps:**
1. Implement unit tests for calculators and PF2e rules (use existing fixtures + data providers).
2. Add kernel/integration coverage where database interactions are needed.
3. Expand functional tests to assert rendered data, not only status codes.
4. Wire up CI for the tester module using this phpunit.xml.
5. Track coverage vs. targets (80-90%).

## 🎯 Coverage Targets

| Layer | Target | Priority |
|-------|--------|----------|
| Service Layer | 90% | Critical |
| Controllers | 70% | High |
| Overall | 80% | High |

See [Testing Strategy Design](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md) for complete coverage strategy.

## 📦 Test Fixtures

Test fixtures are located in `tests/fixtures/` and provide realistic test data:

### Characters
- `level_1_fighter.json` - Fighter with 18 STR, 16 CON (13 HP expected)
- `level_1_wizard.json` - Wizard with 18 INT, 12 CON (7 HP expected)
- `level_5_rogue.json` - Mid-level rogue with stealth focus

### Schemas
- `classes_test.json` - 6 core classes with HP and proficiencies
- `ancestries_test.json` - 6 ancestries with HP bonuses
- `backgrounds_test.json` - 6 backgrounds with ability boosts

### PF2e Reference
- `core_mechanics.json` - Official PF2e rules and calculations

All fixtures include expected results for validation.

## 🧪 Test Types

### Unit Tests (planned)
- Isolated calculators and rules; use fixtures + data providers.
- Currently stubbed with `markTestIncomplete()`.

### Functional Tests (implemented)
- BrowserTestBase for controllers and routes.
- Validate route availability, access control, and basic page content.

### Kernel/Integration Tests (planned)
- For database-backed flows and service wiring; not yet implemented.

## 🎮 PF2e Rules Testing

Special test group for validating Pathfinder 2e game rules:

```bash
./vendor/bin/phpunit --group=pf2e-rules
```

Tests in this group validate:
- Ability score modifiers (PF2e Core Rulebook pp. 20-21)
- HP calculations by class
- Multiple Attack Penalty (p. 446)
- Degrees of Success (p. 445)
- Proficiency progression
- Combat calculations

All PF2e tests reference official rulebook page numbers and test fixture data.

## 🛠️ Implementation Status

### ✅ Complete
- [x] Test directory structure and phpunit configuration
- [x] Fixtures for characters, schemas, PF2e reference
- [x] Functional route/controller coverage (access + basic content)
- [x] Trait scaffolding for fixtures

### 🚧 TODO
- [ ] Implement unit tests and data providers for calculators/rules
- [ ] Add kernel/integration coverage where persistence is involved
- [ ] Strengthen functional assertions with real content data
- [ ] Achieve 80-90% coverage and wire CI to this module
- [ ] Add performance and load-focused checks (per design doc)

## 📖 Resources

- **Design Document**: [issue-testing-strategy-design.md](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md)
- **Quick Start Guide**: [testing/README.md](../../../../docs/dungeoncrawler/testing/README.md)
- **Example Tests**: [testing/examples/](../../../../docs/dungeoncrawler/testing/examples/)
- **Drupal Testing Docs**: https://www.drupal.org/docs/testing
- **PHPUnit Docs**: https://phpunit.de/documentation.html
- **PF2e Rules**: [reference documentation/](../../../../docs/dungeoncrawler/reference%20documentation/)

## 💡 Tips

- Use `markTestIncomplete()` for tests that are defined but not yet implemented
- Reference fixture files in test docblocks for clarity
- Group related tests with `@group` annotations
- Use data providers for testing multiple scenarios
- Keep test methods focused on single assertions when possible
- Add `@covers` annotations to track coverage

---

**Status**: 🚧 Design complete, implementation pending  
**Created**: 2026-02-13  
**Design Reference**: [Testing Strategy & Bug Prevention](../../../../docs/dungeoncrawler/issues/issue-testing-strategy-design.md)
