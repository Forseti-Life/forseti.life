# Feature: forseti-mobile-google-maps-key-hardening

- Status: ready
- Website: forseti.life
- Module: forseti-mobile
- Release: 
- Owner: pm-forseti
- Priority: P0

## Summary

Harden Google Maps API key handling for the mobile app after key exposure. Ensure production keys are restricted in GCP, key storage is local/secret-managed only, and build/runtime behavior uses injected configuration (never hardcoded keys in tracked source).

## Goal

Prevent billing abuse and service disruption from exposed or weakly restricted Google Maps API keys while preserving mobile map functionality.

## Non-goals

- Replacing Google Maps with another map provider.
- Full repository history rewrite.
- iOS key management changes in this iteration.

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| Remove hardcoded Android key from tracked source | `forseti-mobile/android/app/src/main/AndroidManifest.xml` | Full |
| Inject key through build-time config/env | `forseti-mobile/android/app/build.gradle` | Full |
| Enforce GCP key restrictions (Android app + API scope) | GCP Console credentials policy/process | None |
| Document secure key storage standard for team | `forseti-mobile/README.md` | Partial |
| Add release/operator checks for key rotation events | PM/QA runbooks and release checklist | None |

### Coverage determination

- **Feature type: enhancement** — code-level key injection exists now, but governance/restriction and operational enforcement must be completed.

### Test path guidance for QA

| Requirement | Test file | Test type |
|---|---|---|
| Android build succeeds with injected key | `forseti-mobile/android` build validation commands in QA evidence | Integration |
| Maps renders with restricted key | Mobile runtime smoke test (device/emulator evidence) | Functional |
| Missing key fails clearly (no silent insecure fallback) | Mobile build/runtime negative test evidence | Functional |
| Restriction policy verified in GCP | QA/PM screenshot + checklist artifact in sessions | Audit |

## Acceptance criteria

See `01-acceptance-criteria.md`.

## Security acceptance criteria

- Authentication/permission surface: only trusted operators with GCP IAM access may rotate/restrict keys.
- CSRF expectations: not applicable (GCP console + local build config process).
- Input validation: build must reject empty or malformed key injection values in release pipeline checks.
- PII/logging constraints: API keys must never appear in committed files, release notes, or logs; redact in all artifacts.

## Definition of done

- GCP key rotated and old leaked key disabled/deleted.
- New key restricted to Android application(s) and required Maps API only.
- Mobile build uses injected key from local/secret-managed source, not tracked files.
- QA evidence includes positive map render + negative missing-key behavior.
- PM release checklist includes key-hardening verification item for future incidents.

## Risks

- Incorrect Android package/fingerprint restrictions can break maps at runtime.
- Team may accidentally commit local key files without explicit ignore/check safeguards.

## Latest updates
- 2026-04-19: Feature created and groomed following exposed key incident.
