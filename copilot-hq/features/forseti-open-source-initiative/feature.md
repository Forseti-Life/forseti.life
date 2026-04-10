# Feature: forseti-open-source-initiative

status: in_progress
owner: pm-open-source
priority: high
target_release: tbd

## Summary
Publish the org's core software products as open source repositories on GitHub, enabling community contributions and advancing the mission to democratize and decentralize internet services.

## Success Criteria
1. At least two Tier 1 repos (forseti-job-hunter, copilot-agent-framework) are publicly accessible on GitHub
2. Each public repo has complete setup documentation allowing a fresh install on a clean machine
3. No secrets, private keys, or client data present in any public repo or its git history
4. GitHub Actions CI passes on each public repo
5. Community files present in each repo (LICENSE, README, CONTRIBUTING, CODE_OF_CONDUCT, SECURITY, issue templates)

## Work Breakdown

### Phase 0 — Governance & Org Setup
- [ ] CEO decision: GitHub org name (`forseti-community` vs personal account)
- [ ] Board review: confirm which products enter Tier 1 (requires no client involvement)
- [ ] Register GitHub org (if new org selected)

### Phase 1 — Pre-Publish Security Audit
Owner: dev-open-source, reviewed by pm-open-source

- [ ] Run BFG Repo Cleaner against monorepo to identify all secrets in 1,813-commit history
- [ ] Remove RSA private keys from `sites/forseti/keys/` AND from full git history
- [ ] Scrub `sessions/` from copilot-hq history (or mark for exclusion in new repo)
- [ ] Sanitize `.env.example` — replace all literal credentials with `YOUR_<VAR>` placeholders
- [ ] Audit 9 copilot scripts for hardcoded tokens, IPs, or server-specific values
- [ ] Audit `prod-config/` — confirm it will not be included in any public repo
- [ ] Confirm `database-exports/` not in any extractable history segment

### Phase 2 — Repo Extraction (per-product)

#### 2a — drupal-ai-conversation (standalone module — lowest risk, highest reuse value)
- [ ] Extract `shared/modules/ai_conversation/` to new git repo
- [ ] Write README (AWS Bedrock integration, supported Claude models, config)
- [ ] Add GitHub Actions: Drupal coding standards (phpcs) + basic install test
- [ ] Tag v1.0.0, publish

#### 2b — copilot-agent-framework (highest novelty, most interesting to AI/ML community)
- [ ] Extract `copilot-hq/` minus sessions/, minus prod secrets
- [ ] Write README (architecture overview, orchestrator setup, agent seat model)
- [ ] Add GitHub Actions: Python lint + orchestrator import test
- [ ] Write QUICKSTART.md (local Python venv setup, first agent run)
- [ ] Tag v1.0.0, publish

#### 2c — forseti-job-hunter (flagship product)
- [ ] Extract `sites/forseti/web/modules/custom/` + theme
- [ ] Write README (what it does, Drupal 10/11 requirements, install steps)
- [ ] Add GitHub Actions: Drupal coding standards + composer validate
- [ ] Write QUICKSTART.md (DDEV or Lando local setup)
- [ ] Document AWS Bedrock dependency (requires `.env` with keys)
- [ ] Tag v1.0.0, publish

#### 2d — dungeoncrawler (community TTRPG product)
- [ ] Extract `sites/dungeoncrawler/` modules + theme
- [ ] Write README (PF2E assistant, features, install)
- [ ] Add GitHub Actions: Drupal coding standards
- [ ] Tag v1.0.0, publish

### Phase 3 — Community Infrastructure
- [ ] Create GitHub issue templates per repo (bug report, feature request, module improvement)
- [ ] Write top-level CONTRIBUTING.md for each repo (code style, PR process, review policy)
- [ ] Create GitHub Discussions or Discord (TBD — pm-open-source to recommend)
- [ ] Add CODEOWNERS file to each repo
- [ ] Configure branch protection on `main` (require PR + CI before merge)

### Phase 4 — Launch
- [ ] Announce on relevant communities (Drupal.org, Hacker News, AI engineering forums, TTRPG communities)
- [ ] Drupal.org module listing for `drupal-ai-conversation`
- [ ] Post-launch KPI baseline: stars, forks, issues filed within 30 days

### Phase 5 — Tier 2 Evaluation (post-launch)
- [ ] `forseti-safety` / amisafe: privacy/data review before module extraction
- [ ] `forseti-mobile`: assess React Native app for standalone publishability
- [ ] `nfr` module: stakeholder review

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Secrets in git history | High | Critical | BFG scan before any push to public remote |
| Client data in extractable history | Medium | High | Audit before Phase 1 completes |
| No local dev path exists (no Docker) | High | Medium | Phase 2 includes QUICKSTART.md with DDEV/Lando |
| MySQL running --skip-grant-tables | Medium | Medium | Document as known issue; fix in separate infra ticket |
| Community issues overloading solo dev | Medium | Medium | Set clear issue triage policy in CONTRIBUTING.md |

## Dependencies
- GitHub org decision (Board)
- BFG Repo Cleaner available on server (`apt install bfg` or download jar)
- DDEV or Lando install documented for contributors (server doesn't have it — devs use locally)

## Notes
- All Tier 1 repos are Apache 2.0 — consistent with current root LICENSE
- forseti-mobile will need separate treatment (React Native build environment)
- `amisafe_database` (4.4 GB, 4 tables) is likely crime data — excluded from all open source repos; `amisafe` module code may be extractable separately after data review
