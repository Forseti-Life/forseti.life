# LangGraph Workflow Future State & Migration Roadmap

**Module:** job_hunter  
**Date:** 2026-04-25  
**Status:** Planned (Roadmap Approved)

## 1) Future State Vision

JobHunter uses a hybrid workflow model:

1. **LangGraph = orchestration/state machine**
   - Controls step order, branching, retries, and human checkpoints.
   - Persists state so runs can resume after process restarts or worker failures.
2. **Playwright ATS handlers = deterministic executors**
   - Keep existing per-platform handlers (`greenhouse.js`, `workday.js`, etc.) for low-level browser actions.
3. **Field registry + answer policy layer = consistency engine**
   - Normalized field keys and selector strategies.
   - Deterministic answers for recurring required questions.
   - Pre-submit required-field validation before submit actions.

This separates strategy and reliability concerns from browser mechanics while preserving existing handler investments.

---

## 2) Scope and Non-Goals

### In Scope
- LangGraph orchestration for application submission workflows.
- Required-field detection and mapping for supported ATS platforms.
- Human-in-the-loop checkpoint support (CAPTCHA, ambiguous prompts, legal/disclosure review).
- Resume/cover-letter upload verification and pre-submit completeness gates.
- Resume-safe migration with feature flags and strict promotion gates.

### Out of Scope (Initial)
- Replacing all existing ATS handlers.
- Auto-answering legal disclosures without configurable policy controls.
- Cross-platform cutover in one release.

---

## 3) Target Workflow Graph

## Core Nodes
1. `initialize_run`
2. `resolve_apply_url_and_ats`
3. `load_candidate_profile`
4. `open_form_and_discover_schema`
5. `map_required_fields`
6. `resolve_answers`
7. `fill_core_fields`
8. `fill_required_custom_fields`
9. `upload_documents`
10. `pre_submit_validation`
11. `captcha_wait` (conditional)
12. `human_review_required` (conditional)
13. `submit_application`
14. `verify_confirmation`
15. `persist_outcome_and_evidence`
16. `close_run`

## Conditional Branches
- `required_field_missing` → block submit + return actionable reason.
- `selector_not_found` → fallback selector strategy + bounded retry.
- `captcha_detected` → pause and await manual completion.
- `submission_unconfirmed` → manual-review branch with evidence bundle.

---

## 4) Data Contract and Persistence

## New Operational Records
- `workflow_runs`
  - Run ID, application ID, ATS, graph version, current node, status.
- `workflow_run_nodes`
  - Node attempts, timings, errors, retry counts.
- `workflow_required_fields`
  - Detected required fields, labels, selectors, confidence.
- `workflow_answer_audit`
  - Selected answer values, source/policy provenance.
- `workflow_evidence`
  - Screenshots, confirmation snippets, URL snapshots, DOM hashes.

## Existing Tables (Retained)
- `jobhunter_applications` remains source-of-truth for submission status.
- Existing queue and browser services continue to run during migration.

---

## 5) Field Registry and Answer Policy

## Field Registry Responsibilities
- Canonical field keys (`country`, `location_city`, `work_auth`, `education_school_0`, etc.).
- ATS-specific selector sets (id/name/aria/label/role fallbacks).
- Type-aware fill strategies (text, combobox, radio, checkbox, file, grouped questions).
- Required/optional semantics with validation constraints.

## Answer Policy Responsibilities
- Configurable deterministic answers for recurring prompts.
- Optional demographic defaults (`Prefer not to say`) only where policy allows.
- Work authorization, language, and privacy acknowledgment response templates.
- Explicit handling for unresolved required fields (stop before submit).

---

## 6) Migration Strategy (Phased)

## Phase 0 — Baseline and Guardrails
- Freeze baseline metrics and reason-code taxonomy.
- Add feature flags:
  - `job_hunter.langgraph.enabled`
  - `job_hunter.langgraph.greenhouse.enabled`
  - `job_hunter.langgraph.shadow_mode`
- Define promotion SLO thresholds.

## Phase 1 — Contract + Registry Foundation
- Implement workflow run state contract and persistence schema.
- Build first Greenhouse field registry and answer policy rules.
- Keep current runtime path as primary.

## Phase 2 — Shadow Mode (No User Impact)
- Run LangGraph in parallel for Greenhouse.
- Compare required-field detection, answer coverage, and blocker prediction.
- No submit actions from graph yet.

## Phase 3 — Assisted Execution
- Graph drives planning and validation; existing Greenhouse handler performs actions.
- Enforce pre-submit completeness checks.
- Add human checkpoint controls for CAPTCHA and ambiguous required questions.

## Phase 4 — Controlled Greenhouse Cutover
- Gradual traffic ramp: 10% → 25% → 50% → 100%.
- Forward-only promotion: pause advancement if SLOs regress until fixes are verified.

## Phase 5 — Generalization
- Apply registry + graph pattern to Workday/Lever/other ATS flows.
- Add drift detection for selector/label changes.

---

## 7) Milestones and Deliverables

### M1: Architecture + Contract Finalized
- Graph node contract and persistence schema approved.
- Feature flags and reason-code alignment complete.

### M2: Greenhouse Registry + Validator Live
- Required fields mapped for current Greenhouse variants.
- Pre-submit validator blocks incomplete submissions.

### M3: Greenhouse Shadow Results Accepted
- Field detection and answer coverage meet acceptance thresholds.

### M4: Greenhouse Controlled Cutover Complete
- Submission success and manual-required rates non-regressed.

### M5: Multi-ATS Rollout Framework Ready
- Reusable ATS onboarding checklist, registry template, and runbook complete.

---

## 8) Acceptance Criteria

Greenhouse migration is considered complete when:

1. Required-field detection accuracy is consistently high for supported variants.
2. Required-field answer resolution is deterministic or explicitly routed to human checkpoint.
3. No submission occurs with unresolved required fields.
4. CAPTCHA pause/resume workflow is stable.
5. Confirmation/evidence capture is reliable in successful submits.

---

## 9) Risks and Mitigations

- **Risk:** ATS form drift breaks selectors.  
  **Mitigation:** Multi-strategy selectors + drift alerts + bounded retries.
- **Risk:** Policy-sensitive prompts answered incorrectly.  
  **Mitigation:** Central answer policy layer + required human review gates.
- **Risk:** Migration causes production regressions.  
  **Mitigation:** Shadow mode, phased rollout, and strict promotion gates before each traffic increase.

---

## 10) Forward-Only Promotion Policy

1. Use staged traffic ramps only after meeting defined SLO gates at each stage.
2. If regressions appear, freeze promotion at the current stage and remediate before continuing.
3. Keep `job_hunter.langgraph.shadow_mode` available for diagnostics during remediation.
4. Preserve workflow evidence for postmortem, tuning, and policy updates.

---

## 11) Immediate Next Steps

1. Implement Phase 1 schema and run-state services.
2. Build Greenhouse field registry from live schema inventory.
3. Add pre-submit required-field validator.
4. Launch shadow-mode comparison runs and tune gaps.
