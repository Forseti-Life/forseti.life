- Status: blocked
- Summary: Root cause of the 63 `MethodNotAllowedHttpException` watchdog errors is fully identified: `scripts/site-audit-run.sh` contains an inline Python helper (`read_urls_from`) that reads ALL entries from the custom-routes audit JSON — including POST-only routes that the route auditor correctly skipped — then passes those paths to `site-validate-urls.py`, which probes every path with GET. The 7-hits-per-route pattern matches exactly 7 role audit contexts (anon + 6 authenticated roles). The routing YAML, JS frontend, Twig templates, and Functional PHP tests are all correct and require no changes. The fix is a 2-line addition to `site-audit-run.sh` (filter entries where `note` starts with `"POST-only"`), but `scripts/` is owned by `dev-infra`. KB lesson written at `knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md` (commit `585b291cc`) with the exact patch diff.

## Next actions
- PM dispatches to dev-infra: apply 2-line patch to `site-audit-run.sh` `read_urls_from()` (see KB lesson for exact diff)
- After dev-infra patches: run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler` to confirm 0 new 405 entries
- QA confirms zero client errors on affected routes in next site audit (AC-5)

## Blockers
- Fix is in `scripts/site-audit-run.sh` — owned by `dev-infra`. Cannot apply directly.

## Needs from Supervisor
- Dispatch the 2-line patch to dev-infra with the KB lesson (`knowledgebase/lessons/20260409-site-audit-post-only-routes-405-in-watchdog.md`) as the spec.

## Decision needed
- Authorize dev-infra to apply the `read_urls_from` patch in `site-audit-run.sh` to skip POST-only routes from the validate-URLs step.

## Recommendation
- Apply the patch immediately. It is 2 lines, zero functional risk, and fully eliminates the 63-per-audit-run noise. No Drupal code changes are needed — the application is correct. The patch is documented with before/after diffs in the KB lesson.

## ROI estimate
- ROI: 15
- Rationale: Eliminates ongoing watchdog noise that masks real client errors and ensures QA audits produce clean signal. The fix is 2 lines with no functional risk, and without it every audit run regenerates 63 false 405 alarms.
