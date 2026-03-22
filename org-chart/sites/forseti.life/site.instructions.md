# Site Instructions: forseti.life

## Authority
- Primary owner: `pm-forseti`
- Methodology owner: `ceo-copilot`

## Mission Statement (non-negotiable)
> **Democratize and decentralize internet services by building community-managed versions of core systems for scientific, technology-focused, and tolerant people.**

This is the core product mission. All features, priorities, and trade-offs must be evaluated against it.

**What this means in practice:**
- Features that concentrate control, add tracking/surveillance, or restrict community access **contradict the mission** — escalate to Board before proceeding.
- Features that lower barriers, increase transparency, improve access, or add community ownership **align with the mission** — CEO can approve and execute without Board consult.
- When in doubt about mission alignment: ask "does this give power to the community or take it away?"

**Current product focus** (as of 2026): Job Hunter, Dungeon Crawler, AI Conversation (Forseti assistant), Community Safety module.

## Applies to
All seats with `website_scope: ["forseti.life"]`.

## Environments
- Local/dev `BASE_URL` (preferred for QA automation): `http://localhost` (or your local vhost URL).
- Production `BASE_URL` (reference only): `https://forseti.life`

Rule:
- QA seats must not run recursive crawls or route-probing against production unless explicitly authorized for a specific purpose.

## Code roots (on this host)
- Drupal site root: `/home/keithaumiller/forseti.life/sites/forseti`
- Drupal web root: `/home/keithaumiller/forseti.life/sites/forseti/web`

## Product-wide rules
- Keep work items logically separated from other websites.
- Module ownership is authoritative in `org-chart/ownership/module-ownership.yaml`.
- Cross-module work uses `runbooks/passthrough-request.md`.

## QA posture
- Anonymous crawling is expected to work on public pages.
- Auth-required surfaces may return 403/redirect; report unexpected changes.
- Continuous audit evidence (if configured): `sessions/qa-forseti/artifacts/auto-site-audit/latest/`.
- Role matrix + expected permissions source of truth: `org-chart/sites/forseti.life/qa-permissions.json`.

## Escalation
- Cross-module needs/conflicts: `runbooks/passthrough-request.md`, `runbooks/conflict-resolution.md`.
