- Status: done
- Summary: Release preflight for `20260406-dungeoncrawler-release-c` complete. New release ID (not previously preflighted) with one QA-scoped commit since last preflight (`da4aa017` — heritage system cross-ancestry form validation). Full checklist run: all 5 scripts present and correct, no changes needed; `qa-permissions.json` at 6 roles / 32 rules remains adequate (heritage change is form-validation only, no new routes, existing `auth-required-create` rule `^/(?:campaigns|characters)/(?:create|step)(?:/|$)` covers all character step paths); regression checklist updated to reflect `dc-cr-heritage-system` dependency met for `dc-cr-dwarf-heritage-ancient-blooded` (still blocked on `dc-cr-dwarf-ancestry`). Commit: `4f53c466`.

## Preflight checklist results

| Item | Status | Notes |
|---|---|---|
| `scripts/site-audit-run.sh` | ✅ no change | Present, ALLOW_PROD_QA gate intact |
| `scripts/site-full-audit.py` | ✅ no change | Present |
| `scripts/site-validate-urls.py` | ✅ no change | Present |
| `scripts/drupal-custom-routes-audit.py` | ✅ no change | Present |
| `scripts/role-permissions-validate.py` | ✅ no change | Present |
| `qa-permissions.json` | ✅ no change | 6 roles, 32 rules; heritage commit `da4aa017` adds no new routes |
| Heritage commit `da4aa017` | ✅ covered | `CharacterCreationStepForm.php` form validation only; `character_step` POST route already exists; `auth-required-create` rule covers it |
| Regression checklist | ✅ updated | `dc-cr-dwarf-heritage-ancient-blooded`: dc-cr-heritage-system dep now MET; dc-cr-dwarf-ancestry still deferred |
| Suite manifest | ✅ no change | `dc-cr-heritage-system-phpunit` suite already registered |

## Heritage commit assessment (da4aa017)

- **Change**: Adds `!array_key_exists($submitted_heritage, $heritage_opts)` guard in `CharacterCreationStepForm::validateForm()`. Returns Form API error `'Invalid heritage for selected ancestry.'` on mismatch.
- **Route impact**: None — no new routes, no routing.yml changes.
- **Permission impact**: None — form validation runs inside existing authenticated POST flow.
- **Existing coverage**: `auth-required-create` rule `^/(?:campaigns|characters)/(?:create|step)(?:/|$)` covers all character creation and step routes. `dc-cr-heritage-system-phpunit` suite exists in suite manifest (`required_for_release: true`).
- **QA action needed**: Suite execution for `dc-cr-heritage-system-phpunit` when suite-activate items arrive.

## Regression checklist delta

- `dc-cr-dwarf-heritage-ancient-blooded`: First dependency (`dc-cr-heritage-system`) now MET. Second dependency (`dc-cr-dwarf-ancestry`) still deferred — item remains blocked until dwarf ancestry ships.

## Next actions
- Proceed with suite-activate items for `20260406-dungeoncrawler-release-c` in normal queue order
- `dc-cr-heritage-system-phpunit` targeted retest when suite-activate item arrives for heritage system

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 35
- Rationale: Clean preflight enables full release-c QA queue to proceed without re-checking configs. Heritage system dep tracking ensures `dc-cr-dwarf-heritage-ancient-blooded` can ship the moment dwarf ancestry lands.
