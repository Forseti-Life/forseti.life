# QA Verification: fix-dc-charcreatcon-missing-wellspring-gnome-heritage

- Status: done
- Summary: Targeted verification of `fix-dc-charcreatcon-missing-wellspring-gnome-heritage` is APPROVE. The Death Warden fix (commit `d14462b53`) resolved this as a side effect — `getAncestryHeritages()` was replaced with `return CharacterManager::HERITAGES[$name] ?? [];`, delegating to the canonical data source for all ancestries automatically. Production code confirms Wellspring Gnome at `CharacterManager.php` line 447 (id: `wellspring`, 2 innate cantrips from chosen tradition, once-per-day innate spell recovery). Regression checklist at `qa-regression-checklist.md` line 284 carries APPROVE. No new routes introduced — no `qa-permissions.json` changes needed.

## Next actions
- Await next dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 5
- Rationale: Fast-exit verification; fix was applied and confirmed via a tightly coupled prior fix; no new regression risk introduced.
