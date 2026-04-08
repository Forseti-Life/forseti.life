- Status: done
- Summary: Applied CSRF fix to `job_hunter.delete_resume` route. Added `methods: [POST]` and `_csrf_token: 'TRUE'` to the route in `job_hunter.routing.yml`. No template changes were needed — no Twig template or PHP currently generates a link to `job_hunter.delete_resume` (the profile form's delete buttons call the Drupal Form API submit handler `::deleteResumeFileSubmit` which already carries Drupal's built-in form token CSRF protection). The route-based `UserProfileController::deleteResume` was an orphaned GET endpoint with destructive side effects; it is now POST-only with CSRF. `drush cr` confirmed cache rebuilt. Commit `871cda11f`. KB lesson already exists in session memory for this CSRF split-route pattern.

## Next actions
- QA to re-verify: `GET /jobhunter/resume/delete/{id}` must return 405 Method Not Allowed (or equivalent); `anon: deny` per `forseti-jobhunter-resume-delete` ACL rule still holds (authenticated=deny on anon path)
- QA to confirm: form-based delete buttons on profile edit page still function (they use Drupal Form API CSRF — unaffected by this change)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: P1 security fix — MEDIUM severity CSRF on destructive delete endpoint. Quick surgical change with no regressions; directly unblocks QA Gate 2 re-verify for forseti-jobhunter-profile feature.

## Commits
- `871cda11f` — security(forseti-jobhunter-profile): CSRF fix on job_hunter.delete_resume route
