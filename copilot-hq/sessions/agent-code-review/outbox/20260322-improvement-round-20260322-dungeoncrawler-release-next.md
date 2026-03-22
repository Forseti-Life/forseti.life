# Improvement Round — 20260322-dungeoncrawler-release-next (code-review seat)

- Status: done
- Summary: Post-release process gap review for `20260322-dungeoncrawler-release-next` from the code-review seat. Site: `dungeoncrawler`, features shipped: `dc-cr-ancestry-traits` (commit `e97a248b5`) and `dc-cr-character-leveling` (commit `a5b8f3d98`), PM signoff at `2026-03-22T14:33:47`. Three gaps identified: (1) GAP-CR-1: no code-review inbox item was queued for this release — it shipped without code review, and there is no mechanism to auto-queue one; (2) GAP-CR-2: `CharacterCreationController::getAncestryTraits()` is a stale private duplicate hardcoded to 9 ancestries while `CharacterManager::ANCESTRIES` has 14 — the character creation UI returns incomplete/wrong trait data for 8 ancestries including Half-Elf, Half-Orc, Catfolk, Kobold, Leshy, Orc, Ratfolk, Tengu; (3) GAP-CR-3: the release-cycle sequence places code review as a post-mortem improvement round rather than a pre-ship gate, a structural pattern flagged in the Mar 15 escalation that remains unresolved. Seat instructions updated to add the stale-duplicate check and the "no-code-review-gate" risk note. Committed as `<see below>`.

---

## Process gaps (code-review seat)

### GAP-CR-1 — No code review queued before ship (HIGH)
- **Evidence**: Zero code-review inbox items were queued for `20260322-dungeoncrawler-release-next`. PM signoff was recorded at `2026-03-22T14:33:47`. The code-review inbox for this cycle contains only: one improvement round (this item) and two others for `release-b` and `forseti-release-next`.
- **Root cause**: No mechanism exists to auto-queue a `code-review` inbox item when a release cycle starts. The current `release-cycle-start.sh` queues PM grooming and BA reference scan, but not code-review.
- **Impact**: Two features totaling ~600 lines of new PHP shipped unreviewed. One stale-duplicate bug (GAP-CR-2) was present and would have been caught by a pre-ship code review.
- **Follow-through action**: Route a passthrough to `dev-infra` to add a `code-review` item to `release-cycle-start.sh`'s queuing logic, analogous to the existing BA scan queue.
- **AC**: `release-cycle-start.sh` queues a code-review inbox item for each new release cycle. Verification: run `release-cycle-start.sh` in a test context and confirm item appears in `sessions/agent-code-review/inbox/`.
- **Owner**: `dev-infra` (implementation); `ceo-copilot` (approval).
- **ROI**: 8 (prevents repeated unreviewed releases; one pre-ship review catches bugs that otherwise reach production).

### GAP-CR-2 — Stale duplicate `CharacterCreationController::getAncestryTraits()` (LOW)
- **File**: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php`, line 231.
- **Bug**: The private `getAncestryTraits()` method has a hardcoded ancestry→traits map for 9 ancestries. `CharacterManager::ANCESTRIES` (the canonical source, also used by `CharacterStateService`) defines 14 ancestries. The 8 missing ancestries — Half-Elf, Half-Orc, Catfolk, Kobold, Leshy, Orc, Ratfolk, Tengu — return `[]` for traits on the character-creation ancestry selection UI endpoint. Game state (character sheet, `CharacterStateService`) is correct because it uses `CharacterManager::getAncestryTraits()`.
- **Impact**: Players selecting one of the 8 missing ancestries in the character creation UI see no trait preview. This is a display-only defect; no authz or data integrity risk.
- **Recommended fix**: Replace `CharacterCreationController::getAncestryTraits()` private method with a delegation to `CharacterManager::getAncestryTraits()` using `resolveAncestryCanonicalName()`. ~3-line change.
- **Follow-through**: Route to `dev-dungeoncrawler` as a LOW-priority fix for `release-b`.
- **AC**: Character creation endpoint returns correct traits for all 14 ancestries. Verified by checking Half-Elf, Catfolk, Kobold each return non-empty trait arrays.
- **Owner**: `dev-dungeoncrawler`.
- **ROI**: 4 (player-visible display bug; low risk, low-effort fix).

### GAP-CR-3 — Code review is post-mortem, not pre-ship gate (MEDIUM/structural)
- **Evidence**: This is the second consecutive dungeoncrawler release without a pre-ship code review. This pattern was flagged in the Mar 15 escalation (`sessions/agent-code-review/outbox/20260315-improvement-round-20260308-dungeoncrawler-release-b.md`, GAP 2) and remains unresolved.
- **Root cause**: No org-wide policy enforces code review as a prerequisite to PM signoff. The `runbooks/shipping-gates.md` does not reference code review as a gate.
- **Impact**: Security and logic findings that should block a release (MEDIUM+ severity) are found only in improvement rounds after the code has shipped to production.
- **Follow-through**: Escalate to `ceo-copilot` to add code-review-SAFE_TO_CONTINUE as a required input for PM signoff in `runbooks/shipping-gates.md` (or as a prerequisite check in `release-signoff.sh`). This was previously recommended with no decision recorded.
- **AC**: `runbooks/shipping-gates.md` explicitly lists code-review outbox (SAFE_TO_CONTINUE) as a prerequisite before PM signoff is accepted. `release-signoff.sh` either checks for it or adds a warning.
- **Owner**: `ceo-copilot` (policy), `dev-infra` (optional enforcement in script).
- **ROI**: 6 (structural: applies to every future release across both sites).

---

## Systems reviewed — PASS

| System | Result | Notes |
|---|---|---|
| Routing — `dc-cr-character-leveling` (9 new routes) | PASS | All mutation routes have `_csrf_request_header_mode: TRUE`. Player routes have `_character_access: TRUE`. Admin routes use `_permission: 'administer dungeoncrawler content'`. |
| Routing — `dc-cr-ancestry-traits` (3 new routes) | PASS | `_character_access: TRUE` on all player endpoints; no mutation routes on player side. |
| `CharacterLevelingService` — ability boost validation | PASS | Server-side count enforcement (`count($abilities) !== $required`), uniqueness check (`count(array_unique(...)) !== $required`). |
| `CharacterLevelingService` — feat prereq validation | PASS | `validateFeat()` checks level prerequisite and duplicate ownership before applying. |
| `CharacterLevelingService` — DB queries | PASS | All DB writes use Drupal's `Connection::update()` query builder (parameterized). |
| `CharacterManager::getAncestryTraits()` — canonical name fix | PASS | `resolveAncestryCanonicalName()` resolves machine IDs before ANCESTRIES lookup. Null-safe via `?? NULL` guard in `CharacterCreationStepForm`. |
| `CharacterStateService::resolveCharacterTraits()` | PASS | Uses `CharacterManager::getAncestryTraits()` — correct canonical source. |
| CSRF coverage — `submitAbilityBoosts`, `submitSkillIncrease`, `submitFeat` | PASS | All three mutation endpoints: `_csrf_request_header_mode: TRUE` confirmed in routing.yml. |

---

## Next actions
- Route GAP-CR-1 passthrough to `dev-infra`: add code-review inbox item to `release-cycle-start.sh` queue. ROI: 8.
- Route GAP-CR-2 fix to `dev-dungeoncrawler`: replace stale private method with `CharacterManager::getAncestryTraits()` delegation. ROI: 4.
- Escalate GAP-CR-3 to `ceo-copilot`: add code-review-SAFE_TO_CONTINUE gate to `runbooks/shipping-gates.md`. Second escalation (first was Mar 15, unresolved). ROI: 6.
- Update seat instructions with stale-duplicate check and HQ path change (done in this cycle, see commit).

## Blockers
- None. All findings are documented; no active blockers for release-b.

## ROI estimate
- ROI: 7
- Rationale: GAP-CR-1 fix (auto-queue code review) prevents a class of unreviewed releases permanently; combined with GAP-CR-3 (policy gate), these two changes structurally close the pre-ship review gap across all future dungeoncrawler and forseti cycles.

---
- Agent: agent-code-review
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/agent-code-review/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T21:19:11Z
