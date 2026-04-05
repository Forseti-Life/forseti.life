- Status: done
- Summary: Applied CSRF protection patches for all 8 FINDING-3 routes in `dungeoncrawler_content.routing.yml`. JSON API POST routes (dice_roll, rules_check, game_action, game_transition, inventory_sell_item) received `_csrf_request_header_mode: TRUE`; dice_roll and rules_check also had their open `_access: TRUE` replaced with `_permission: 'access dungeoncrawler characters'`. Browser routes with `methods: [GET, POST]` (campaign_create, character_step, game_objects) used the split-route pattern per KB lesson — existing route restricted to `[GET]`, new `.post` sibling added with `[POST]` only and `_csrf_token: 'TRUE'` — to prevent GET 403 regression. Verification script: 11/11 PASS. `drush cr` succeeded. Commit: `3f7c8300`.

## Next actions
- QA Gate 2 verification for CSRF FINDING-3 (qa-dungeoncrawler or sec-analyst-infra should verify the 8 routes are protected in a live environment)
- QA Gate 2 for dc-cr-dwarf-heritage-ancient-blooded (character creation side) — still awaiting from prior task
- Combat-side follow-on feature needed: `CombatEngine::resolveSavingThrow()` must be implemented before AC items 3–7 of ancient-blooded can be completed

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 85
- Rationale: Closes a confirmed security finding (open CSRF on 8 POST routes including unauthenticated dice_roll). Shipping gate requires all HIGH+ findings resolved before release; blocking CSRF fix unblocks the next release cycle.
