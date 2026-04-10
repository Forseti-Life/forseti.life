# Site Instructions: open-source

## Authority
- Primary owner: `pm-open-source`
- Methodology owner: `ceo-copilot-2`

## Mission
Make the org's core software products publicly available under open source licenses, enabling community contributions, external adoption, and transparency aligned with the org-wide mission to **democratize and decentralize internet services**.

## Applies to
All seats with `website_scope: ["open-source"]`.

## Scope — What Gets Open Sourced

### Tier 1 — Primary public repos (new GitHub repos, separate from monorepo)

| Repo Name | Source Path | Contents | Audience |
|---|---|---|---|
| `forseti-job-hunter` | `sites/forseti/web/modules/custom/job_hunter` + `ai_conversation` + `copilot_agent_tracker` + theme | AI-powered job application platform | Job seekers, Drupal devs |
| `dungeoncrawler` | `sites/dungeoncrawler/` | Full PF2E assistant Drupal site | TTRPG community, Drupal devs |
| `copilot-agent-framework` | `copilot-hq/` (sanitized) | LangGraph AI agent orchestration system | AI/ML engineers, platform builders |
| `drupal-ai-conversation` | `shared/modules/ai_conversation/` | Standalone AWS Bedrock/Claude Drupal module | Drupal module ecosystem |

### Tier 2 — Evaluate after Tier 1
- `forseti-safety` (`amisafe` module + H3 geolocation) — after data/privacy review
- `forseti-mobile` (React Native app) — after API surface is stable
- `nfr` (National Firefighter Registry) — after stakeholder review

### Not Open Sourced
- Client sites: `stlouisintegration`, `theoryofconspiracies`, `thetruthperspective` — client-owned content
- `prod-config/` — production server config with sensitive values
- `database-exports/` — contains real data
- `sessions/` in copilot-hq — internal org communications

## Repo Structure Decision: Separate Repos (not monorepo split)

**Rationale:**
- Each product has a distinct audience and contribution community
- Prevents cross-contamination of client site history
- Cleaner public surface per product
- Standard open source practice for distinct products
- Allows different release cadences and governance per repo

## Pre-Publish Blocklist (must be resolved before any repo goes public)

| Item | Severity | Action |
|---|---|---|
| RSA private keys in `sites/forseti/keys/` | 🔴 CRITICAL | Remove from files + scrub git history (BFG) |
| `.env.example` with literal sample passwords | 🔴 HIGH | Replace with generic placeholders (`YOUR_DB_PASSWORD`) |
| 1,813-commit git history may contain secrets | 🟡 HIGH | BFG Repo Cleaner scan before publishing |
| Copilot-hq `sessions/` directory | 🟡 HIGH | Exclude entirely from public repo (gitignore + history scrub) |
| Hardcoded server-specific values in scripts | 🟡 MEDIUM | Parameterize via env vars |
| `prod-config/` directory | 🟡 MEDIUM | Exclude from public repo |
| `database-exports/` | 🔴 HIGH | Exclude from public repo; check git history |

## Repo Prerequisites (each public repo needs before launch)

- [ ] LICENSE file (Apache 2.0 — already present in monorepo)
- [ ] README.md (installation, usage, architecture overview)
- [ ] CONTRIBUTING.md (how to contribute, code standards)
- [ ] CODE_OF_CONDUCT.md (already present — reuse)
- [ ] SECURITY.md (vulnerability disclosure process — already present)
- [ ] `.env.example` with NO real credentials
- [ ] GitHub Actions CI (tests pass on PR)
- [ ] Issue templates (bug report, feature request)
- [ ] Local development setup (Docker Compose or documented DDEV/Lando setup)

## Environments
- No dedicated "open-source" web surface — this project produces GitHub repositories, not a running site.
- Validation environment: clean Ubuntu VM or Docker to verify setup docs work end-to-end.

## Code roots (source of truth for extraction)
- Monorepo: `/home/ubuntu/forseti.life/`
- Public GitHub org target: `github.com/keithaumiller/` (or new org TBD — see decision below)

## Open Decision: GitHub Org
- **Option A:** Publish under `keithaumiller/` personal account (current monorepo location)
- **Option B:** Create a new GitHub org (e.g., `forseti-community/`) for community identity
- **Recommendation:** Create `forseti-community` org for Tier 1 repos to signal community ownership — aligns with mission. Keep monorepo under personal account as private source-of-truth.
- **Board decision required** before repo creation.

## QA posture
- Each public repo must pass its own CI before going public.
- `drupal-ai-conversation` module: test on clean Drupal 10 and 11 install.
- `copilot-agent-framework`: test orchestrator on clean Python 3.12 venv.
