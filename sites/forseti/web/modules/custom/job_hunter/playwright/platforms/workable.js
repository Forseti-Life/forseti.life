/**
 * Workable ATS — Playwright handler stub
 * Priority: 7 | Track: A (no login)
 */
'use strict';
async function apply(payload, buildResult) {
  return buildResult({ outcome: 'manual_required', reason: 'phase2_pending', apply_url: payload.apply_url,
    error: 'Workable automation not yet implemented.', instructions: 'Apply manually via the link below.',
    field_map: { name: (payload.personal_info||{}).full_name||'', email: (payload.personal_info||{}).email||'' } });
}
module.exports = { apply };
