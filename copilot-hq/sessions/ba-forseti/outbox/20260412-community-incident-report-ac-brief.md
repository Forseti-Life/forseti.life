- Status: done
- Summary: Completed AC traceability brief for `forseti-community-incident-report` (PROJ-006, release-f). Read the feature stub, inspected `amisafe/js/crime-map.js` for the map layer initialization pattern, and verified module ownership. Produced full traceability at `sessions/ba-forseti/artifacts/proj006-community-incident-report-ac-brief/traceability.md` covering all 7 ACs with expected module/file, verify method, and dependencies. Identified one high-risk cross-module blocker for AC-4 (AmISafe JS passthrough) and one medium risk for AC-6 (VBO module dependency). Committed at `d6564452d`.

## Next actions
- **pm-forseti** must initiate passthrough to amisafe module owner before dev-forseti starts AC-4 — one-line patch to `amisafe/js/crime-map.js` to expose `crimeMap` instance on `$(container).data()`. Passthrough draft is included in the artifact.
- **dev-forseti** should verify `drush pm:list --status=enabled | grep views_bulk_operations` before starting AC-6; add `drupal/views_bulk_operations` to `composer.json` if absent.
- Recommended dev sequence: AC-1+AC-5 → AC-2 → AC-6 → AC-3 → AC-7 → AC-4 (last, after passthrough resolved).

## Blockers
- None for BA. AC-4 is a blocked dev task pending passthrough, not a BA blocker.

## Needs from CEO
- None

## Key findings for pm-forseti

**AC-4 passthrough (required):** `crime-map.js` stores the `AmISafeCrimeMap` instance as a local variable in the `attach()` closure — it's not on `window`, not in `$(container).data()`, not on `Drupal.amisafe`. No external behavior can add a Leaflet layer without a reference to the map instance. Fix is one line in `amisafe/js/crime-map.js`:
```js
$(this).data('amisafe-crime-map', crimeMap);
```
Without this, AC-4 cannot be implemented self-contained in `community_incident_report`. A draft passthrough request is in the artifact.

**AC-6 VBO:** Bulk operations admin view requires `drupal/views_bulk_operations` contributed module. If not already installed, dev must add it to `composer.json` and run `composer require drupal/views_bulk_operations`.

## ROI estimate
- ROI: 16
- Rationale: Grooming for release-f ahead of time; the AmISafe passthrough finding prevents a mid-sprint blocked-dev cycle — identifying it now means the passthrough can be dispatched before release-f opens.
