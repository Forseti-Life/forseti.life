# Suite Activation: forseti-jobhunter-schema-fix

- Status: done
- Summary: Activated `forseti-jobhunter-schema-fix` test suite into `qa-suites/products/forseti/suite.json`. Added suite `forseti-jobhunter-schema-fix-functional` with 8 TCs, all tagged `feature_id: forseti-jobhunter-schema-fix`. TCs 01–05 are drush CLI functional checks (column exists, updb clean, boolean write, read round-trip, NULL row preservation). TC-06 and TC-07 are marked manual-only (row count preservation and rollback — production risk accepted). TC-08 (no ACL regressions) is covered by the existing `role-url-audit` suite — no new routes were added by this fix. No changes to `qa-permissions.json` (schema-only fix, no new ACL surfaces). Suite validated: `python3 scripts/qa-suite-validate.py` → OK, 5 manifests. Total forseti suites: 17. Committed at `6c37a55eb`.

## Entries added

| Suite ID | Type | TCs | feature_id |
|---|---|---|---|
| `forseti-jobhunter-schema-fix-functional` | functional | 8 | `forseti-jobhunter-schema-fix` |

TC breakdown:
- TC-01: `age_18_or_older` column exists in `job_hunter_profile` (PENDING — requires dev install hook)
- TC-02: `drush updb -y` exits 0, no PHP errors
- TC-03: boolean write to column succeeds without DB exception
- TC-04: read returns correct persisted value after write
- TC-05: existing NULL rows remain NULL after schema update
- TC-06: row count unchanged before/after updb (manual)
- TC-07: rollback clean (manual-only, production risk accepted)
- TC-08: no ACL regressions (covered by existing `role-url-audit`)

## Next actions
- All TCs in PENDING state — will move to PASS once Dev implements `job_hunter_install` hook adding `age_18_or_older` column.
- No new items for Dev (QA role policy); Dev consumes this suite as implementation target.
- PM may proceed with scheduling dev implementation.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Activating this suite gives Dev a concrete, runnable acceptance target for the schema fix, preventing silent DB failures on profile reads/writes. Covers data integrity for all Job Hunter users.
