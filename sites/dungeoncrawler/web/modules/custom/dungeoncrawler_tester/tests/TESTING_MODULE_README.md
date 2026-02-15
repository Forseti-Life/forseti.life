# Dungeon Crawler Testing Module (tester package)

## Overview

This testing module exercises the Dungeon Crawler module with route/controller functional tests and a `/testing` stub page. Unit/kernel coverage is planned; current focus is access, routing, and basic content assertions.

**For complete run instructions, test suites, groups, and examples, see [README.md](README.md) - the canonical testing guide.**

## Testing Page

The testing page is a simple stub page that can be used for manual testing and validation.

**URL:** `/testing`
**Access:** Public (no authentication required)
**Controller:** `TestingPageController`

## Test Structure

### Route Tests (`tests/src/Functional/Routes/`)

Route tests validate that all defined routes work correctly with proper access control:

1. **PublicRoutesTest** - Tests public-facing routes (home, world, about, credits, how-to-play, testing)
2. **AdminRoutesTest** - Tests admin routes (settings, dashboard)
3. **CharacterRoutesTest** - Tests character management routes (list, create, view, edit, delete)
4. **CampaignRoutesTest** - Tests campaign routes (list, create, tavern entrance, select character)
5. **ApiRoutesTest** - Tests API endpoints (character and combat APIs)
6. **DemoRoutesTest** - Tests demo routes (hexmap demo)

### Controller Tests (`tests/src/Functional/Controller/`)

Controller tests validate the behavior of individual controllers:

1. **HomeControllerTest** - Tests homepage functionality
2. **AboutControllerTest** - Tests about page
3. **WorldControllerTest** - Tests world page
4. **CreditsControllerTest** - Tests credits page
5. **HowToPlayControllerTest** - Tests how-to-play page
6. **DashboardControllerTest** - Tests admin dashboard
7. **CampaignControllerTest** - Tests campaign management
8. **CharacterListControllerTest** - Tests character list
9. **CharacterCreationStepControllerTest** - Tests character creation wizard
10. **CharacterViewControllerTest** - Tests character viewing
11. **CharacterApiControllerTest** - Tests character API
12. **CharacterStateControllerTest** - Tests character state management API
13. **CombatControllerTest** - Tests combat functionality
14. **CombatActionControllerTest** - Tests combat actions
15. **CombatEncounterApiControllerTest** - Tests combat encounter API
16. **DungeonControllerTest** - Tests dungeon functionality
17. **HexMapControllerTest** - Tests hex map demo
18. **TestingPageControllerTest** - Tests the testing page itself

## Test Cases

Each test file includes:

### Positive Test Cases
- Valid user with proper permissions can access routes
- Routes return expected status codes (200, etc.)
- Routes display expected content
- API endpoints accept valid requests

### Negative Test Cases
- Users without permissions receive 403 Forbidden
- Invalid route parameters return 404 Not Found
- Wrong HTTP methods return 405 Method Not Allowed
- Anonymous users are blocked from protected routes
- Invalid data returns appropriate error codes

## Running Tests

See [README.md](README.md) for complete run instructions including:
- All tests / specific test suites
- Test groups (routes, controllers, API, PF2e rules)
- Single test files
- Coverage reports

## Test Coverage

See [README.md](README.md) for detailed coverage targets, test structure, and status.

## Notes

- Tests use Drupal's `BrowserTestBase` for functional testing
- Each test extends the base test case with proper module dependencies
- Tests create users with specific permissions as needed
- Some tests validate route existence even without actual entities (character, campaign, etc.)
- Negative tests ensure proper access control and error handling

## Future Enhancements

- Add kernel tests for service layer testing
- Add unit tests for specific business logic
- Add integration tests for complex workflows
- Add tests with actual entity creation for more complete coverage
- Add performance tests for API endpoints
