# Suite Activation: forseti-jobhunter-offer-tracker

- Status: done
- Summary: Added 5 suite entries (TC-1 through TC-5) and 2 permission rules for `forseti-jobhunter-offer-tracker` into `qa-suites/products/forseti/suite.json` and `org-chart/sites/forseti.life/qa-permissions.json`. All entries tagged `feature_id=forseti-jobhunter-offer-tracker`. Suite validated OK (283 total suites). Committed as `a006329d0`.

## Next actions
- Monitor for Dev implementation of `forseti-jobhunter-offer-tracker`
- Unit-test inbox item `20260413-unit-test-20260413-004107-impl-forseti-jobhunter-offer-tracker` is queued and will be processed when dispatched

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 12
- Rationale: Suite activation before Dev implementation ensures failing tests correctly identify new work (not regressions) and unblocks Gate 4 regression readiness for the current release cycle.

---

## Entries added

### suite.json (5 entries)

| Entry ID | Type | TC | Description |
|---|---|---|---|
| forseti-jobhunter-offer-tracker-form-renders | functional | TC-1 | Offer details form renders on saved-job detail (status=offer received) |
| forseti-jobhunter-offer-tracker-save-creates-or-updates | functional | TC-2 | AJAX POST creates/updates jobhunter_offers row without duplicate |
| forseti-jobhunter-offer-tracker-comparison-page | functional | TC-3 | /jobhunter/offers returns HTTP 200 with comparison layout or empty state |
| forseti-jobhunter-offer-tracker-cross-user-isolation | security | TC-4 | User B sees no offer data from User A |
| forseti-jobhunter-offer-tracker-anon-csrf-blocked | security | TC-5 | Anon GET blocked/redirected; CSRF-missing POST returns 403 |

### qa-permissions.json (2 rules)

| Rule ID | Path | Anon | Auth |
|---|---|---|---|
| forseti-jobhunter-offer-tracker-offers-page | `^/jobhunter/offers$` | 403 | 200 |
| forseti-jobhunter-offer-tracker-offer-save | `^/jobhunter/offer/[0-9]+/save$` | 403 | ignore (CSRF POST-only) |

## Validation

```
python3 scripts/qa-suite-validate.py
OK: validated 7 suite manifest(s)
```

## Commit

- `a006329d0` — qa: suite activation — forseti-jobhunter-offer-tracker (5 TCs, 2 permission rules)
