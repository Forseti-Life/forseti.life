# Suite Activation: forseti-mobile-google-maps-key-hardening

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-25T12:01:59-04:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-mobile-google-maps-key-hardening"`**  
   This links the test to the living requirements doc at `features/forseti-mobile-google-maps-key-hardening/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-mobile-google-maps-key-hardening-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-mobile-google-maps-key-hardening",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-mobile-google-maps-key-hardening"`**  
   Example:
   ```json
   {
     "id": "forseti-mobile-google-maps-key-hardening-<route-slug>",
     "feature_id": "forseti-mobile-google-maps-key-hardening",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-mobile-google-maps-key-hardening",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-mobile-google-maps-key-hardening

- Feature: forseti-mobile-google-maps-key-hardening
- Module: forseti-mobile
- Date: 2026-04-19
- QA owner: qa-forseti

## Scope

- In scope:
  - Verify Android build uses injected key path only.
  - Verify key absent from tracked source after remediation.
  - Verify runtime map loads with restricted replacement key.
  - Verify missing-key path fails clearly.
  - Verify GCP restrictions configured (application + API restrictions).
- Out of scope:
  - iOS key management.
  - Full history rewrite of previously leaked commits.

## Test Matrix

- Browsers/devices (if UI): Android emulator + at least one physical Android device.
- Roles/permissions: PM/QA for evidence capture; GCP project owner for credential settings.
- Environments: local dev mobile build, production GCP credential console.

## Central automated test-case suite (SoT)

- Overlay manifest path: `qa-suites/products/forseti/features/forseti-mobile-google-maps-key-hardening.json`
- Live release manifest path: `qa-suites/products/forseti/suite.json`
- How to run (commands):
  - `cd forseti-mobile/android && ./gradlew clean assembleDebug`
  - `grep -R "AIza" forseti-mobile/android/app/src/main/AndroidManifest.xml forseti-mobile/android/app/build.gradle`
- Reporting (where PASS/FAIL is recorded): QA outbox + release evidence artifacts.

## Feature suite overlay requirements

- Overlay file: `qa-suites/products/forseti/features/forseti-mobile-google-maps-key-hardening.json`
- Each suite entry must declare:
  - `owner_seat`
  - `source_path`
  - `env_requirements`
  - `release_checkpoint`

## Standard source locations

- Unit tests: N/A (config/process hardening feature)
- Functional tests: mobile runtime smoke evidence in QA artifacts
- E2E tests: map screen load + interaction check
- Audit/static checks: repository key scan + GCP restriction checklist evidence

## Manual Tests (non-SoT)

- Validate Google Cloud Console key settings screenshots for:
  - application restriction = Android apps
  - package + SHA fingerprint match
  - API restrictions limited to required Maps API

## Automated Tests

- Existing suites to run:
  - Relevant mobile build sanity checks already in Forseti suite
- New tests expected (if any):
  - Add a static key-pattern check in CI/release preflight (future dev follow-up)

## Pass/Fail Criteria

- PASS if AC-1..AC-7 all satisfied with evidence.
- FAIL if any tracked source contains live key, key is unrestricted, or map runtime fails under intended restricted key configuration.

## Knowledgebase references

- Related lesson(s) or proposal(s): none found specific to Google Maps key hardening; this feature establishes baseline policy.

## What I learned (QA)

- Ensure key-restriction verification is treated as release-gate evidence, not optional checklist.

## What I'd change next time (QA)

- Add automated secret scanning for Google API key patterns in release preflight.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-mobile-google-maps-key-hardening

- Feature: forseti-mobile-google-maps-key-hardening
- Module: forseti-mobile
- Author: pm-forseti
- Date: 2026-04-19

## Summary

Lock down Google Maps API key management for Android mobile builds and operations so exposed keys cannot be abused.

## Acceptance criteria

### AC-1: [EXTEND] No hardcoded key in tracked source

**Given** the mobile repository on `main`,
**When** key scanning is run for Google API key patterns,
**Then** no production Google Maps API key is present in tracked source files.

---

### AC-2: [EXTEND] Build-time key injection only

**Given** Android build configuration,
**When** the app is built,
**Then** `com.google.android.geo.API_KEY` is sourced from build-time placeholder/env/property, not literal key text in manifest.

---

### AC-3: [NEW] GCP key rotation completed

**Given** the exposed key incident,
**When** operator remediation is performed,
**Then** compromised key is rotated and old key is disabled/deleted.

---

### AC-4: [NEW] Application restrictions enforced

**Given** the replacement Maps key in GCP,
**When** restrictions are configured,
**Then** key is restricted to Android app identifiers (package + SHA fingerprints) for Forseti mobile only.

---

### AC-5: [NEW] API restrictions enforced

**Given** the replacement Maps key,
**When** API restrictions are configured,
**Then** key is limited to required Maps API surface (minimum required, no broad/unrestricted access).

---

### AC-6: [EXTEND] Secure storage standard documented and used

**Given** dev/prod mobile build processes,
**When** operators configure key values,
**Then** keys are stored only in local untracked config or secret manager-backed env vars, never committed to git.

---

### AC-7: [NEW] Negative-path guard

**Given** missing or blank key configuration,
**When** build/release validation runs,
**Then** process fails with explicit error and does not proceed with silent fallback.

## Happy Path
- [ ] AC-1 through AC-7 verified with evidence.

## Edge Cases
- [ ] Wrong package/SHA restriction detected and corrected before release.
- [ ] Multiple build variants (debug/release) validated with correct key source behavior.

## Failure Modes
- [ ] `[NEW]` Restriction misconfiguration produces clear diagnostics and rollback steps.
- [ ] `[NEW]` Unauthorized key use attempts are prevented by GCP restriction policy.

## Permissions / Access Control
- [ ] Anonymous user behavior: not applicable.
- [ ] Authenticated user behavior: not applicable.
- [ ] Admin behavior: only GCP IAM-authorized operators can rotate/restrict keys.

## Data Integrity
- [ ] No secret material added to tracked files during remediation.
- [ ] Rollback path identified: temporary key rollback via restricted replacement key (not restoring leaked key).

## Knowledgebase check
- Related lessons/playbooks: `knowledgebase/lessons/20260405-hotfix-code-review-gate-gap.md` (process gate relevance), plus this feature's incident trail in PM/QA artifacts.
