# Feature Brief: Economy, Services, and Currency

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: in_progress
- Release: none
- Dependencies: dc-cr-equipment-system, dc-cr-character-creation

## Goal

Implement the currency and bulk encumbrance systems: GP/SP/CP with standard exchange rates, Bulk weight tracking (L/1-n/—), carrying limits (5 + STR mod Bulk), and item pricing from the equipment catalog.

## Source reference

> "Coins are divided into four denominations: platinum (pp), gold (gp), silver (sp), and copper (cp)." (Chapter 6: Equipment, PF2E Core Rulebook)

## Implementation hint

Character entity gains `currency{pp,gp,sp,cp}` fields. All economic transactions go through `EconomyService::transferCurrency(character, delta{pp,gp,sp,cp})` which validates non-negative balance. Bulk tracking: each carried item has `bulk` field (L=0.1, integer, or 0 for negligible); `BulkCalculator::total(character)` sums all; encumbered when > 5 + STR mod, over-encumbered when > 10 + STR mod (speed reduced, penalties applied). Currency auto-exchanges (10cp = 1sp, 10sp = 1gp, 10gp = 1pp). Starting gold distributed at character creation by class.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; currency mutations require character ownership (`_character_access: TRUE`); shop transactions require session context
- CSRF expectations: all POST/PATCH economy/currency routes require `_csrf_request_header_mode: TRUE`
- Input validation: currency delta validated as non-negative result after transaction; bulk calculation is server-side computed; no client-supplied bulk totals accepted
- PII/logging constraints: no PII logged; character id + currency delta + transaction type + source id only

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
