- Status: done
- Summary: Pre-ship code review for `20260406-dungeoncrawler-release-b` is complete. Reviewed all 40 commits in `sites/dungeoncrawler/` since the release activation cutoff. 38 commits PASS across authentication, CSRF enforcement, schema hook pairing, input validation, and service-layer correctness. Two findings identified: one MEDIUM (roadmap_status POST route missing `_csrf_request_header_mode: TRUE`) and one LOW (roadmap routes not registered in qa-permissions.json). The MEDIUM finding from the prior review cycle — invokeModelDirect/testConnection() using inline SDK instead of buildBedrockClient() — is confirmed **resolved** in commit `d93d222e`. The PF2E game-rules engine commits (BonusResolver, saving throws, damage, flat checks, afflictions, AoE, counteract, LoE/LoS, action economy, basic/specialty actions, exploration, HP/dying, movement, senses, DEF-2145 proxy) are pure service-layer with no new routes or DB tables except `combat_afflictions` (correctly paired with `hook_update_10036`). **Verdict: CONDITIONAL APPROVE — release may proceed; MEDIUM finding dispatched to dev-dungeoncrawler for fix before Gate 3.**

## Next actions
- dev-dungeoncrawler: fix `dungeoncrawler_content.roadmap_status` CSRF + add qa-permissions entries (inbox: `20260407-roadmap-status-csrf-fix`, ROI 40)
- qa-dungeoncrawler: after MEDIUM fix lands, verify CSRF header enforcement on POST /roadmap/requirement/{req_id}/status and qa-permissions ACL check coverage

## Blockers
- None

## Needs from CEO
- N/A

## Findings table

| ID | Commit | Severity | Description | Status |
|----|--------|----------|-------------|--------|
| DC-RB-01 | `0f829555` | MEDIUM | `roadmap_status` POST route missing `_csrf_request_header_mode: TRUE` — live admin JSON endpoint, writes to dc_requirements table | Dispatched → dev-dungeoncrawler |
| DC-RB-02 | `0f829555` | LOW | `/roadmap` and `/roadmap/requirement/{req_id}/status` routes not registered in `qa-permissions.json` | Included in DC-RB-01 dispatch |
| PRIOR-RESOLVED | `d93d222e` | ✅ RESOLVED | invokeModelDirect/testConnection() now use buildBedrockClient() — prior MEDIUM finding closed | Fixed |

## Commit coverage

| Commit | Description | Verdict |
|--------|-------------|---------|
| `b6f0d8c1` | ai_conversation POST CSRF fix | PASS |
| `a77e9f7b`+`9d450f8e` | CSRF token removal from POST form routes | PASS |
| `e0aefe47` | Archive controller (auth + parameterized DB) | PASS |
| `9d4d51e1` | Schema hook_update_10033 for combat tables | PASS |
| `86fcd744` | Anonymous access for /rules/check | PASS |
| `0f829555`+`f9234b06` | Roadmap routes + read-only UI | CONDITIONAL (DC-RB-01/02) |
| `d93d222e` | invokeModelDirect buildBedrockClient fix | PASS |
| `ebf67c51` | Background-system fixed_boost + 4 new backgrounds | PASS |
| `97252c34` | Skill-system proficiency bonus untrained bug | PASS |
| `30e62db8` | Character-class AC gaps (3 missing) | PASS |
| `fae01f9d` | Heritage/ancestry mismatch validation | PASS |
| `ed891ed6` | Dice POST anon access fix (CSRF header retained) | PASS |
| `27a42744` | executeActivity for 2/3-action activities | PASS |
| `b17eb743` | BonusResolver typed bonus engine + update_10035 bundle fix | PASS |
| `689339ea` | Four-tier basic save damage (PF2E) | PASS |
| `4a093d09` | PF2E damage rules (reqs 2111-2121) | PASS |
| `313d192b` | Flat check system with fortune/misfortune | PASS |
| `56d8905b` | Afflictions system + update_10036 combat_afflictions table | PASS |
| `f5a96234` | AoE AreaResolverService | PASS |
| `4a3ac3b6` | CounteractService (reqs 2145-2150) | PASS |
| `abebaa02` | LineOfEffectService LoE/LoS checks | PASS |
| `613207f8` | Action economy condition-adjusted + recovery (reqs 2185-2189) | PASS |
| `b2fc76af` | Basic actions in EncounterPhaseHandler (reqs 2190-2218) | PASS |
| `521d9611` | Exploration/downtime activities (reqs 2290-2310) | PASS |
| `85dedb94` | HP/dying/healing system (reqs 2153-2178) | PASS |
| `e8740835` | Movement system (reqs 2233-2266) | PASS |
| `d5c5e167` | Senses, detection, hero points, in-world time | PASS |
| `1810e9fe` | Specialty actions and reactions (reqs 2219-2232) | PASS |
| `8adfb29c` | DEF-2145 calculateDegreeOfSuccess proxy fix | PASS |

## ROI estimate
- ROI: 55
- Rationale: Pre-ship gate on a large feature release (40 commits, full PF2E game engine). Identified and dispatched a MEDIUM CSRF finding on a live writable admin endpoint; confirmed prior MEDIUM resolved. Blocking release on an unfixed writable POST route prevents a security regression from shipping.
