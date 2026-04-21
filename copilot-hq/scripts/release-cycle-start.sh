#!/usr/bin/env bash
# release-cycle-start.sh — Start a release cycle for a site/product team
#
# Usage:
#   ./scripts/release-cycle-start.sh <site> <current-release-id> <next-release-id>
#
# Both release IDs are required. The org always has two releases defined:
#   current: Dev executing, QA verifying (Stage 3–7)
#   next:    PM grooming, QA writing test plans (parallel, Stage 3 of current)
#
# Examples:
#   ./scripts/release-cycle-start.sh forseti.life 20260226-forseti-r1 20260226-forseti-r2
#   ./scripts/release-cycle-start.sh dungeoncrawler 20260226-dc-r1 20260226-dc-r2
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

site="${1:-}"
release_id="${2:-}"
next_release_id="${3:-}"

if [ -z "$site" ] || [ -z "$release_id" ] || [ -z "$next_release_id" ]; then
  echo "Usage: $0 <site> <current-release-id> <next-release-id>" >&2
  echo "" >&2
  echo "Both release IDs are required. The org always has two releases defined:" >&2
  echo "  current: Dev executing + QA verifying (Stage 3–7)" >&2
  echo "  next:    PM grooming + QA test plan design (parallel)" >&2
  echo "" >&2
  echo "Examples:" >&2
  echo "  $0 forseti.life 20260226-forseti-r1 20260226-forseti-r2" >&2
  exit 2
fi

if ! lookup_result="$(python3 - "$PRODUCT_TEAMS_JSON" "$site" <<'PY'
import json
import sys

cfg_path = sys.argv[1]
query = (sys.argv[2] or '').strip().lower()

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

teams = data.get('teams') or []
for team in teams:
    aliases = [str(a).strip().lower() for a in (team.get('aliases') or []) if str(a).strip()]
    team_id = str(team.get('id') or '').strip().lower()
    site = str(team.get('site') or '').strip().lower()
    if query not in aliases and query != team_id and query != site:
        continue

    if not team.get('active', False):
        print(f"ERROR: team is not active for query '{query}'", file=sys.stderr)
        raise SystemExit(3)

    if not team.get('release_preflight_enabled', False):
        print(f"ERROR: release preflight disabled for team '{team.get('id')}'", file=sys.stderr)
        raise SystemExit(3)

    qa_agent = str(team.get('qa_agent') or '').strip()
    pm_agent = str(team.get('pm_agent') or '').strip()
    normalized_site = str(team.get('site') or '').strip()
    team_id_out = str(team.get('id') or '').strip()
    if not qa_agent or not normalized_site:
        print(f"ERROR: team '{team_id_out}' missing qa_agent/site in registry", file=sys.stderr)
        raise SystemExit(4)

    print(f"{team_id_out}\t{normalized_site}\t{qa_agent}\t{pm_agent}")
    raise SystemExit(0)

print(f"ERROR: unknown site/team alias: {query}", file=sys.stderr)
print("Update org-chart/products/product-teams.json to onboard this team.", file=sys.stderr)
raise SystemExit(2)
PY
  2>&1)"; then
  echo "$lookup_result" >&2
  exit 2
fi

IFS=$'\t' read -r team_id site qa_agent pm_agent <<<"$lookup_result"

today="$(date +%Y%m%d)"
slug="$(echo "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"

item_id="${today}-release-preflight-test-suite-${slug}"
inbox_dir="sessions/${qa_agent}/inbox/${item_id}"
outbox_file="sessions/${qa_agent}/outbox/${item_id}.md"

if [ -d "$inbox_dir" ] || [ -f "$outbox_file" ]; then
  echo "OK: already queued/completed: ${qa_agent} ${item_id}"
  exit 0
fi

# GAP-QA-PREFLIGHT-DEDUP-01: suppress redundant preflight dispatches.
# If a preflight outbox was written for this qa_agent within the last 4 hours
# AND no QA-scoped commits (qa-suites/, qa-permissions.json, 03-test-plan.md)
# have landed since that outbox was written, skip this dispatch with a log message.
# This eliminates ~80% of preflight slot consumption when the release is advancing
# rapidly but QA configuration has not changed.
_recent_outbox=""
_four_hours_ago=$(date -d "4 hours ago" +%s 2>/dev/null || date -v-4H +%s 2>/dev/null || echo "0")
for _f in "sessions/${qa_agent}/outbox/"*-release-preflight-test-suite-*.md; do
  [ -f "$_f" ] || continue
  _fmtime=$(stat -c %Y "$_f" 2>/dev/null || stat -f %m "$_f" 2>/dev/null || echo "0")
  if [ "$_fmtime" -ge "$_four_hours_ago" ]; then
    # Pick the most-recent file; stat output is already epoch so we can compare
    if [ -z "$_recent_outbox" ]; then
      _recent_outbox="$_f"
      _recent_mtime="$_fmtime"
    elif [ "$_fmtime" -gt "$_recent_mtime" ]; then
      _recent_outbox="$_f"
      _recent_mtime="$_fmtime"
    fi
  fi
done
if [ -n "$_recent_outbox" ]; then
  _outbox_iso=$(date -d "@${_recent_mtime}" -Iseconds 2>/dev/null || date -r "$_recent_mtime" -Iseconds 2>/dev/null || echo "")
  _qa_commits=""
  if [ -n "$_outbox_iso" ]; then
    _qa_commits=$(git -C "$ROOT_DIR" log --since="$_outbox_iso" --oneline \
      -- "qa-suites/" "org-chart/sites/*/qa-permissions.json" "features/**/03-test-plan.md" \
      2>/dev/null | head -1 || true)
  fi
  if [ -z "$_qa_commits" ]; then
    echo "PREFLIGHT-SUPPRESSED: recent outbox exists (${_recent_outbox}), no QA-scoped commits since ${_outbox_iso}; skipping dispatch."
    exit 0
  fi
fi

# Always write state files first — before any early exits.
# BUG-FIX: state must be persisted before conditional dispatches so the orchestrator
# release_cycle step sees the new release_id on the next tick regardless of whether
# a QA preflight item is dispatched (GAP-AGE-PREFLIGHT-01 exit was skipping this).
mkdir -p tmp/release-cycle-active 2>/dev/null || true
printf '%s\n' "$release_id"      > "tmp/release-cycle-active/${team_id}.release_id"
printf '%s\n' "$next_release_id" > "tmp/release-cycle-active/${team_id}.next_release_id"
printf '%s\n' "$(date -Iseconds)" > "tmp/release-cycle-active/${team_id}.started_at"

# GAP-AGE-PREFLIGHT-01: suppress preflight when no features are activated for this release.
# Count features with Status: in_progress AND Release: <release_id> — if zero, skip dispatch.
_pf_feat_count=$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
  | xargs grep -l "^- Release:.*${release_id}" 2>/dev/null | wc -l | tr -d '[:space:]' || echo 0)
if [ "${_pf_feat_count:-0}" -eq 0 ]; then
  echo "PREFLIGHT-SUPPRESSED: no features activated for release ${release_id}; skipping preflight dispatch."
else

mkdir -p "$inbox_dir" 2>/dev/null || true
printf '%s\n' "9" >"$inbox_dir/roi.txt" 2>/dev/null || true

cat >"$inbox_dir/command.md" <<MD
- command: |
    Release-cycle QA preflight (run once per release cycle).

    - Site: ${site}
    - Product team: ${team_id}
    - Release id: ${release_id}

    Goal:
    - As the FIRST QA task of this release cycle, review + refactor the QA test automation scripts and configs.
    - This is a process improvement step that happens once per release cycle.

    Required review/refactor targets (scripted; no GenAI required):
    - scripts/site-audit-run.sh
    - scripts/site-full-audit.py
    - scripts/site-validate-urls.py
    - scripts/drupal-custom-routes-audit.py
    - scripts/role-permissions-validate.py
    - org-chart/sites/${site}/qa-permissions.json (role matrix + cookie env vars)

    Expectations:
    - Confirm newly discovered URLs from scans are included in validation (union validation).
    - Confirm role coverage matches all relevant Drupal roles for this site (update qa-permissions.json roles list as needed).
    - Keep production audits gated behind ALLOW_PROD_QA=1.

    Deliverables:
    - If changes are needed: commit fixes to HQ (git add/commit) and reference commit hash in your outbox.
    - If no changes: outbox should explicitly state "preflight complete; no changes needed".

    Then proceed with normal QA verification work for release-bound items.
MD

fi  # end PREFLIGHT-SUPPRESSED gate

# Create PM grooming task for the next release (parallel to Dev execution this cycle)
if [ -n "$pm_agent" ]; then
  next_slug="$(echo "$next_release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"
  pm_item_id="${today}-groom-${next_slug}"
  pm_inbox_dir="sessions/${pm_agent}/inbox/${pm_item_id}"
  pm_outbox_file="sessions/${pm_agent}/outbox/${pm_item_id}.md"

  if [ -d "$pm_inbox_dir" ] || [ -f "$pm_outbox_file" ]; then
    echo "OK: PM grooming task already queued/completed: ${pm_agent} ${pm_item_id}"
  else
    mkdir -p "$pm_inbox_dir" 2>/dev/null || true
    # Keep next-release grooming warm enough to use spare slots without outranking
    # urgent current-release execution/signoff work.
    printf '%s\n' "25" >"$pm_inbox_dir/roi.txt" 2>/dev/null || true

    cat >"$pm_inbox_dir/command.md" <<MD
# Groom Next Release: ${next_release_id}

- Site: ${site}
- Current release (Dev executing): ${release_id}
- Next release (your target): ${next_release_id}

The org always has two releases defined simultaneously:
- **Current release** — Dev is executing, QA is verifying. You monitor but do not add scope.
- **Next release** — You groom the backlog so Stage 0 of ${next_release_id} is instant scope selection.

This task does NOT touch the current release. All work here is for ${next_release_id} only.

## Steps

### 1. Pull community suggestions
\`\`\`bash
./scripts/suggestion-intake.sh ${team_id}
\`\`\`

### 2. Triage each suggestion
\`\`\`bash
./scripts/suggestion-triage.sh ${team_id} <nid> accept <feature-id>
./scripts/suggestion-triage.sh ${team_id} <nid> defer
./scripts/suggestion-triage.sh ${team_id} <nid> decline
./scripts/suggestion-triage.sh ${team_id} <nid> escalate
\`\`\`

Mandatory gate: if a suggestion clearly requests security abuse, release-integrity bypass, intentional crash/data-destruction behavior,
or a major architecture replatform/rewrite,
do NOT accept at PM level. Use \`escalate\` so it is reviewed at human board level first.
Otherwise continue normal PM triage so the majority of valid product requests can flow.

### 3. Write Acceptance Criteria for each accepted feature
  features/<feature-id>/01-acceptance-criteria.md  (from templates/01-acceptance-criteria.md)
  Must be complete before handing to QA.

### 4. Hand off to QA for test plan design
\`\`\`bash
./scripts/pm-qa-handoff.sh ${team_id} <feature-id>
\`\`\`
QA writes features/<id>/03-test-plan.md (spec only — NOT added to suite.json until Stage 0).
QA signals back via qa-pm-testgen-complete.sh when done.

### 5. When next Stage 0 starts: activate scoped features
For each feature selected into ${next_release_id}:
\`\`\`bash
./scripts/pm-scope-activate.sh ${team_id} <feature-id>
\`\`\`
This sends QA the activation task to add tests to suite.json for the live release.

## Groomed/ready gate (required for Stage 0 eligibility)
A feature is ready when ALL THREE exist:
- features/<id>/feature.md          (status: ready)
- features/<id>/01-acceptance-criteria.md
- features/<id>/03-test-plan.md

Security override: any feature requiring board-security review is ineligible until explicit board approval is documented.

Anything not groomed when Stage 0 of ${next_release_id} starts is automatically deferred.

## References
- runbooks/feature-intake.md
- runbooks/intake-to-qa-handoff.md
MD
    echo "QUEUED: ${pm_agent} ${pm_item_id} (next release: ${next_release_id})"
  fi
fi

# Queue BA reference scan task (Stage 3 parallel track — generates pre-triage feature stubs from reference docs)
if [ -f "scripts/ba-reference-scan.sh" ]; then
  bash scripts/ba-reference-scan.sh "${team_id}" "${next_release_id}" || true
fi

# Queue code-review inbox item for agent-code-review (GAP-CR-1 fix)
cr_item_id="${today}-code-review-${site}-${slug}"
cr_inbox_dir="sessions/agent-code-review/inbox/${cr_item_id}"
cr_outbox_file="sessions/agent-code-review/outbox/${cr_item_id}.md"

if [ -d "$cr_inbox_dir" ] || [ -f "$cr_outbox_file" ]; then
  echo "OK: code-review task already queued/completed: agent-code-review ${cr_item_id}"
else
  mkdir -p "$cr_inbox_dir" 2>/dev/null || true
  printf '%s\n' "10" >"$cr_inbox_dir/roi.txt" 2>/dev/null || true

  cat >"$cr_inbox_dir/command.md" <<MD
- Agent: agent-code-review
- Status: pending
- command: |
    Pre-ship code review for ${site} release ${release_id}.
    Review all commits in this release cycle against the code-review checklist in
    \`org-chart/agents/instructions/agent-code-review.instructions.md\`.
    Focus on: CSRF protection on new POST routes, authorization bypass risks,
    schema hook pairing (hook_schema + hook_update_N both present), stale
    private duplicates of canonical data, and hardcoded paths.
    Produce: one finding per issue, severity (CRITICAL/HIGH/MEDIUM/LOW),
    file path, and recommended fix pattern.
MD

  echo "QUEUED: agent-code-review ${cr_item_id} (current release: ${release_id})"
fi

# Trigger a fresh site audit immediately so the Gate 2 clean-audit backstop can
# evaluate the new release cycle as soon as it starts (rather than waiting up to
# 2 hours for the next CEO ops cycle or up to 1 hour for the audit cron).
# GAP-GATE2-AUDIT-TIMING: audit must be ≤1 cycle old relative to release activation.
if [ -n "${team_id:-}" ]; then
  echo "Triggering site audit for ${team_id} at release cycle start..."
  bash scripts/site-audit-run.sh "${team_id}" </dev/null 2>&1 | tail -5 || true
fi

echo "QUEUED: ${qa_agent} ${item_id} (current release: ${release_id})"
echo "RELEASE_CYCLE_ACTIVE: ${team_id} current=${release_id} next=${next_release_id}"
