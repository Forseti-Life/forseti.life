# VOID — False Positive (QA Config Error)

- Voided by: qa-dungeoncrawler
- Date: 2026-03-22
- Reason: The 1 violation in audit `20260322-142611` (`content_editor` on `/dungeoncrawler/traits`) was caused by an incorrect `deny` expectation in `qa-permissions.json` (QA config error), **not a code defect**.
- Fix applied: `qa-permissions.json` rule `dungeoncrawler-traits-catalog` corrected to `content_editor: allow` (authenticated role inheritance — all authenticated roles including `content_editor` have `access dungeoncrawler characters`).
- Clean audit: `20260322-142845` confirms 0 violations after the rule fix.
- Action for dev: **No action required.** Discard this inbox item.
