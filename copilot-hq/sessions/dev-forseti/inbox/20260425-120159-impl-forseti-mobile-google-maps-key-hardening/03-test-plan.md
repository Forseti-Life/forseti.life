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
