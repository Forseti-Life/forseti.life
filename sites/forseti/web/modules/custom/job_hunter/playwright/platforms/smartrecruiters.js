/**
 * SmartRecruiters ATS — Playwright handler stub
 * Priority: 5 | Track: A (no login)
 * Spec: docs/PHASE2_BROWSER_AUTOMATION_REQUIREMENTS.md §4.4
 */
'use strict';
async function apply(payload, buildResult) {
  return buildResult({
    outcome: 'manual_required', reason: 'phase2_pending', apply_url: payload.apply_url,
    error: 'SmartRecruiters automation not yet implemented (Phase 2, Priority 5).',
    instructions: 'Apply manually via the link below.',
    field_map: { firstName: (payload.personal_info||{}).first_name||'', lastName: (payload.personal_info||{}).last_name||'', email: (payload.personal_info||{}).email||'' },
  });
}
module.exports = { apply };
