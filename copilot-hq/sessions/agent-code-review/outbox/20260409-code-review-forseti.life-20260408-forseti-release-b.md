- Status: done
- Summary: Completed code review for `20260408-forseti-release-b` (commit range `57e7d4696..c373f228d`, 5 commits, 11 files). Primary work: CSRF fixes across delete_resume route and 7 CSRF seed corrections in JobApplicationController + CompanyController; AIConversationStorageService extraction; schema update_9039 (age_18_or_older); UserProfileForm subform refactor (EducationHistorySubform, ResumeUploadSubform). All critical checks pass. Verdict: **APPROVE** with one LOW finding.

## Review details

### Scope
- Range: `57e7d4696..c373f228d` — 5 commits, 11 files, +1854/-1492 lines
- Not data-only: full checklist applied

### Checklist results

| Check | Result |
|---|---|
| CSRF: delete_resume route — POST + `_csrf_token: 'TRUE'` added | ✓ PASS |
| CSRF: toggle_job_applied route — already had `_csrf_token: 'TRUE'` | ✓ PASS |
| CSRF: job_apply route — already had `_csrf_token: 'TRUE'` | ✓ PASS |
| CSRF seed fix — 5 places in JobApplicationController, 2 in CompanyController corrected to route paths | ✓ PASS |
| Schema hook pairing — update_9039 adds `age_18_or_older`; hook_schema() at line 1051 declares it | ✓ PASS |
| Authz — ResumeUploadSubform scopes all queries via `currentUser()->id()` | ✓ PASS |
| AIConversationStorageService — service layer only; no user-controlled input path | ✓ PASS |
| Stale duplicates — UserProfileForm methods (addResumeSubmit, etc.) are thin delegates | ✓ PASS |
| Hardcoded paths — `private://` scheme references are standard Drupal | ✓ PASS |
| Twig XSS — my-jobs.html.twig: all variables auto-escaped, no `\|raw` | ✓ PASS |

### Finding: LOW — Unparameterized key name in JSON_EXTRACT queries

**Location:** `AIConversationStorageService::findCachedResponse()` and `deleteCachedResponses()` lines 83, 120

**Pattern:**
```php
$query->where("JSON_EXTRACT(context_data, '$.$key') = :value_$key", [":value_$key" => $value]);
```

`$key` is inserted directly into the SQL string (values are parameterized, but key names are not). All current callers pass hardcoded PHP array keys (`'uid'`, `'job_id'`, `'queue'`, `'item_key'`), so this is not currently exploitable. However, it is a latent injection surface — if any future caller passes user-derived key names, it becomes a SQL injection vector.

**Recommendation:** Add a key allowlist check or `preg_match('/^[a-z_]+$/', $key)` guard in `AIConversationStorageService` before the JSON_EXTRACT call.

## Verdict

**APPROVE** — no blocking issues. LOW finding is non-exploitable today but should be hardened before new callers are added.

## Next actions
- LOW finding (JSON_EXTRACT key sanitization) should be filed as a follow-on hardening item for dev-forseti. Not a gate blocker.

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 14
- Rationale: This review validated the CSRF fix chain (FR-RB-01 regression) is fully correct, confirmed schema hook pairing for update_9039, and surfaced a latent SQL injection pattern before any unsafe callers could be added. Low cost to review, high assurance value for a security-focused release.
