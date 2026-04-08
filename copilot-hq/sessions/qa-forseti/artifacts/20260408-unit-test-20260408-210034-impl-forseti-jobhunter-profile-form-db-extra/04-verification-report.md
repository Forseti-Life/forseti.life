# Verification Report: forseti-jobhunter-profile-form-db-extraction

- Feature: forseti-jobhunter-profile-form-db-extraction
- Release: 20260408-forseti-release-j
- QA seat: qa-forseti
- Dev commit: c664d0b47
- Date: 2026-04-08

## Verdict: BLOCK

AC-3 fails as written. 10 `\Drupal::database()` static calls remain in `UserProfileForm.php`
at lines 1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175.

The dev extracted the 2 `$this->database` (arrow-notation) call sites to `UserProfileRepository`,
which is complete and correct. However, the pre-existing static `\Drupal::database()` calls were
not part of the extraction scope. AC-3 text ("no `\Drupal::database()` in UserProfileForm") is
broader than the actual extraction scope — this is an AC scope mismatch requiring PM decision.

**See: Scope mismatch note below.**

## AC Results

| AC | Description | Result | Evidence |
|----|-------------|--------|----------|
| AC-1 | 0 `->database(` calls in UserProfileForm | PASS | grep count = 0 |
| AC-2 | UserProfileRepository exists with extracted methods | PASS | 3 public methods confirmed |
| AC-3 | No `\Drupal::database()` in UserProfileForm | **FAIL** | 10 static calls at lines 1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175 |
| AC-4 | PHP lint clean on modified files | PASS | `php -l` → no errors on both files |
| AC-5 | Site audit 0 failures, 0 violations | PASS | audit 20260408-220624: 0 failures, 0 violations |

## Suite Results

| Suite entry | Result |
|-------------|--------|
| forseti-jobhunter-profile-form-db-extraction-static | PASS |
| forseti-jobhunter-profile-form-db-extraction-functional | PASS (`/jobhunter/profile` → 403) |
| forseti-jobhunter-profile-form-db-extraction-regression | PASS (audit 20260408-220624 clean) |

**Note:** The static suite checks `->database(` (arrow notation only) — it does not catch
`\Drupal::database()` static calls. The suite passes but AC-3 fails at code-review level.

## AC-3 Scope Mismatch — PM Decision Required

Dev outbox states the extraction scope was "constructor injection, update at ~1701, select at ~3074"
(2 `$this->database` call sites). These were correctly extracted to `UserProfileRepository`.

The 10 remaining `\Drupal::database()` static calls appear to be pre-existing, long-standing calls
NOT included in the dev's extraction scope. AC-3 as written ("no `\Drupal::database()` in UserProfileForm")
covers ALL static calls, which is broader than what was scoped.

**PM options:**
1. Expand dev scope: require Dev to extract all 10 remaining `\Drupal::database()` static calls to repository layer.
2. Narrow AC-3: accept current extraction as complete (2 `$this->database` calls extracted), close AC-3 as met (scope was `$this->database` calls only), and create a follow-on task for remaining static calls.

## Suite Coverage Gap

The `forseti-jobhunter-profile-form-db-extraction-static` suite entry uses:
```
grep -c "->database("
```
This only catches arrow-notation calls (`$this->database->`). It will not catch `\Drupal::database()`.
If AC-3 is to be enforced at suite level, the static entry needs an additional check for `\Drupal::database`.

## KB Reference
- No prior lessons found for this pattern. Adding to checklist.
