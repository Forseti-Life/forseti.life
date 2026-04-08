- Status: done
- Summary: Pre-ship code review for `20260408-forseti-release-c`. Two new commits reviewed since release-b cutoff (`871cda11f`): `7f9e10c0f` (FR-RB-01 CSRF seed fix) and `0c0ebecc2` (profile form subform refactor). Result: **APPROVE**. FR-RB-01 is confirmed fully resolved — all five `csrfToken()->get('job_apply_{id}')` calls in `JobApplicationController` updated to use correct path seeds (`jobhunter/my-jobs/{id}/applied`), and both the generator and manual validator in `CompanyController` updated to `jobhunter/jobs/{id}/apply`; zero `job_apply_` seed occurrences remain in controllers. The refactor commit is clean: `EducationHistorySubform` and `ResumeUploadSubform` are extracted correctly, `$uid` is sourced from `$this->currentUser->id()` in `UserProfileForm` (not from user input), no new routes or ACL surfaces introduced, no hardcoded filesystem paths, and `job_hunter.routing.yml` is unmodified. FR-RB-02 (LOW, `age_18_or_older` absent from `hook_schema`) remains deferred — no new fix landed in this release, acceptable carry-forward.

## Next actions
- No dispatch required; FR-RB-01 resolved, FR-RB-02 deferred as LOW.
- FR-RB-02 recommendation: add `age_18_or_older` to `_job_hunter_create_job_seeker_table()` in a future release update hook batch.

## Blockers
- None

## Needs from CEO
- N/A

## Findings table

| ID | Commit | Severity | Description | Status |
|----|--------|----------|-------------|--------|
| FR-RB-01 | 7f9e10c0f | MEDIUM | CSRF token seed mismatch — all 5 JAppCtrl + 2 CompanyCtrl generators corrected to route-path seeds | **RESOLVED** |
| FR-RB-02 | — | LOW | `age_18_or_older` absent from `hook_schema` fresh-install path | Deferred (carry-forward) |
| ✓ | 0c0ebecc2 | PASS | Subform refactor: clean extraction, `$uid` from currentUser, no new routes/ACL surfaces, routing.yml unchanged | — |

## ROI estimate
- ROI: 35
- Rationale: FR-RB-01 (live functional breakage) now confirmed resolved; APPROVE unblocks the forseti-release-c ship path.
