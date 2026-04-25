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
