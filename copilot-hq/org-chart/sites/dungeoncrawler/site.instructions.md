# Site Instructions: dungeoncrawler

## Authority
- Primary owner: `pm-dungeoncrawler`
- Methodology owner: `ceo-copilot`

## Applies to
All seats with `website_scope: ["dungeoncrawler"]`.

## Environments
- Local/dev `BASE_URL` (preferred for QA automation): `http://localhost:8080` (dungeoncrawler runs on port 8080; port 80 is forseti.life).
- Production `BASE_URL` (reference only): `https://dungeoncrawler.forseti.life`

Rule:
- QA seats must not run recursive crawls or route-probing against production unless explicitly authorized for a specific purpose.

## Code roots (on this host)
- Drupal site root: `/home/keithaumiller/forseti.life/sites/dungeoncrawler`
- Drupal web root: `/home/keithaumiller/forseti.life/sites/dungeoncrawler/web`

## Product-wide rules
- Keep work items logically separated from other websites.
- Module ownership is authoritative in `org-chart/ownership/module-ownership.yaml`.
- Cross-module work uses `runbooks/passthrough-request.md`.

## QA posture
- Anonymous crawling is expected to work on public pages.
- Auth-required surfaces may return 403/redirect; report unexpected changes.
- Continuous audit evidence (if configured): `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/`.
- Role matrix + expected permissions source of truth: `org-chart/sites/dungeoncrawler/qa-permissions.json`.

## Escalation
- Cross-module needs/conflicts: `runbooks/passthrough-request.md`, `runbooks/conflict-resolution.md`.
