#!/usr/bin/env bash
# ceo-system-health.sh — Systemic health checks beyond the release pipeline.
# Covers: error logs, executor failures, orchestrator health, feature velocity,
# scoreboard freshness, tailoring queue, KB lesson rate, dead-letter inboxes.
#
# Exit 0 = all checks pass
# Exit 1 = one or more FAIL or actionable WARN found
#
# Usage:
#   bash scripts/ceo-system-health.sh              # report only
#   bash scripts/ceo-system-health.sh --dispatch   # report + create agent inbox items for each finding
#   bash scripts/ceo-system-health.sh --json       # report + JSON summary line
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

JSON_MODE=0
DISPATCH_MODE=0
for arg in "$@"; do
  [[ "$arg" == "--json" ]]     && JSON_MODE=1
  [[ "$arg" == "--dispatch" ]] && DISPATCH_MODE=1
done

FAIL_COUNT=0
WARN_COUNT=0
RESULTS=()
# Dispatch queue: "agent|slug|severity|title|body" entries collected during checks
DISPATCH_ITEMS=()

now_ts=$(date +%s)
now_iso=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
date_prefix=$(date -u +"%Y%m%d")

SEP="────────────────────────────────────────────────────────"

pass()  { echo "✅ PASS $*"; RESULTS+=("PASS|$*"); }
fail()  { echo "❌ FAIL $*"; RESULTS+=("FAIL|$*"); FAIL_COUNT=$(( FAIL_COUNT + 1 )); }
warn()  { echo "⚠️  WARN $*"; RESULTS+=("WARN|$*"); WARN_COUNT=$(( WARN_COUNT + 1 )); }
info()  { echo "   ℹ️  $*"; }

# queue_dispatch <agent> <slug> <roi> <severity> <title> <body>
queue_dispatch() {
  local agent="$1" slug="$2" roi="$3" severity="$4" title="$5" body="$6"
  DISPATCH_ITEMS+=("${agent}|${slug}|${roi}|${severity}|${title}|${body}")
}

# create_inbox_item — writes one inbox folder if it doesn't already exist
create_inbox_item() {
  local agent="$1" slug="$2" roi="$3" title="$4" body="$5"
  local dir="sessions/${agent}/inbox/${date_prefix}-syshealth-${slug}"
  if [ -d "$dir" ]; then
    echo "   ℹ️  [dispatch] Already exists, skipping: $dir"
    return
  fi
  mkdir -p "$dir"
  printf '%s\n' "$roi" > "$dir/roi.txt"
  # Use printf %b to interpret \n escape sequences in body
  body_rendered=$(printf '%b' "$body")
  cat > "$dir/README.md" <<ITEMEOF
# ${title}

- Agent: ${agent}
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: ${now_iso}
- Source: system health check

## Issue

${body_rendered}

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: \`bash scripts/ceo-system-health.sh\` — relevant check should show ✅ PASS
ITEMEOF
  echo "   📥 [dispatch] Created: $dir"
}

echo "═══════════════════════════════════════════════════════"
echo "  CEO System Health Check"
echo "  $now_iso"
echo "═══════════════════════════════════════════════════════"

# ─── 1. EXECUTOR FAILURES ───────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Executor Failures  (tmp/executor-failures/)"
echo "$SEP"

failure_dir="tmp/executor-failures"
if [ -d "$failure_dir" ]; then
  total_failures=$(ls "$failure_dir" | wc -l)
  recent_failures=$(find "$failure_dir" -newer <(date -d '24 hours ago' +%Y%m%d%H%M%S 2>/dev/null || date -v-24H +%Y%m%d%H%M%S 2>/dev/null || echo "19700101000000") -type f 2>/dev/null | wc -l || \
    find "$failure_dir" -mmin -1440 -type f 2>/dev/null | wc -l)

  if [ "$recent_failures" -gt 10 ]; then
    fail "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
    info "Recent: $(ls -t "$failure_dir" | head -3 | tr '\n' ' ')"
    info "Investigate: head tmp/executor-failures/\$(ls -t tmp/executor-failures/ | head -1)"
    queue_dispatch "dev-infra" "executor-failures-spike" "8" "FAIL" \
      "Executor failure spike: $recent_failures failures in 24h" \
      "The executor failure directory has $recent_failures new failures in the last 24 hours (total: $total_failures).\n\nRecent items:\n\`\`\`\n$(ls -t "$failure_dir" | head -5 | tr '\n' '\n')\`\`\`\n\nInvestigate root cause. Check agent command errors, tool timeouts, and permission issues. Prune resolved items."
  elif [ "$recent_failures" -gt 0 ]; then
    warn "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
    info "Recent: $(ls -t "$failure_dir" | head -3 | tr '\n' ' ')"
    queue_dispatch "dev-infra" "executor-failures-backlog" "5" "WARN" \
      "Executor failure backlog: $recent_failures new in 24h (total: $total_failures)" \
      "The executor failure directory has $recent_failures new entries in the last 24 hours and $total_failures total.\n\nReview recent failures and prune resolved items:\n\`\`\`\nbash: ls -t tmp/executor-failures/ | head -5\n\`\`\`"
  elif [ "$total_failures" -gt 100 ]; then
    warn "Executor failure backlog: $total_failures items — consider pruning after triage"
    info "Run: ls -t tmp/executor-failures/ | head -5"
    queue_dispatch "dev-infra" "executor-failures-prune" "3" "WARN" \
      "Executor failure backlog needs pruning: $total_failures items" \
      "The executor failure directory has $total_failures accumulated items. Review and prune resolved/stale entries to keep signal clear."
  else
    pass "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
  fi
else
  pass "Executor failures: directory not present"
fi

# ─── 2. ORCHESTRATOR HEALTH ─────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Orchestrator Health"
echo "$SEP"

pid_file=".orchestrator-loop.pid"
health_file="tmp/orchestrator-health-last-autoexec"

if [ -f "$pid_file" ]; then
  orc_pid=$(cat "$pid_file" 2>/dev/null || true)
  if [[ "$orc_pid" =~ ^[0-9]+$ ]] && ps -p "$orc_pid" >/dev/null 2>&1; then
    pass "Orchestrator: running (pid $orc_pid)"
  else
    fail "Orchestrator: pid file exists but process $orc_pid is not running"
    info "Restart: bash scripts/orchestrator-loop.sh start"
    queue_dispatch "dev-infra" "orchestrator-down" "9" "FAIL" \
      "Orchestrator process is down" \
      "The orchestrator pid file exists but process $orc_pid is not running.\n\nRestart:\n\`\`\`bash\nbash scripts/orchestrator-loop.sh start\n\`\`\`\nThen verify with: bash scripts/orchestrator-loop.sh status"
  fi
else
  warn "Orchestrator: no pid file found — may not be running"
  info "Start: bash scripts/orchestrator-loop.sh start"
  queue_dispatch "dev-infra" "orchestrator-no-pid" "7" "WARN" \
    "Orchestrator has no pid file — may not be running" \
    "No orchestrator pid file found at .orchestrator-loop.pid.\n\nVerify if it's running and restart if needed:\n\`\`\`bash\nbash scripts/orchestrator-loop.sh start\n\`\`\`"
fi

if [ -f "$health_file" ]; then
  last_ts=$(cat "$health_file" 2>/dev/null | tr -d '[:space:]' || echo "0")
  if [[ "$last_ts" =~ ^[0-9]+$ ]]; then
    age_h=$(( (now_ts - last_ts) / 3600 ))
    if [ "$age_h" -gt 2 ]; then
      warn "Orchestrator last autoexec: ${age_h}h ago (expected < 2h)"
    else
      pass "Orchestrator last autoexec: ${age_h}h ago"
    fi
  else
    warn "Orchestrator health file unreadable: $health_file"
  fi
else
  warn "Orchestrator: no last-autoexec health file"
fi

# ─── 3. APACHE ERROR LOG ANALYSIS ──────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Apache Error Logs (real errors, last 24h)"
echo "$SEP"

for site in forseti dungeoncrawler; do
  log="/var/log/apache2/${site}_error.log"
  if [ ! -f "$log" ]; then
    info "[$site] No error log at $log"
    continue
  fi

  # Count PHP fatal/warning errors (exclude AH01630 security scan noise)
  php_fatal=$(grep -cE "PHP Fatal|PHP Parse error|PHP Exception" "$log" 2>/dev/null || true); php_fatal=${php_fatal:-0}
  # Count real Apache errors (exclude AH01630 which is security probe noise)
  real_errors=$(grep -v "AH01630" "$log" 2>/dev/null | grep -cE "\[error\]" || true); real_errors=${real_errors:-0}

  if [ "${php_fatal}" -gt 0 ]; then
    fail "[$site] PHP Fatal/Parse/Exception errors: $php_fatal"
    info "$(grep -E "PHP Fatal|PHP Parse error|PHP Exception" "$log" | tail -2)"
    dev_agent="dev-${site}"
    [[ "$site" == "forseti" ]] && dev_agent="dev-forseti"
    queue_dispatch "$dev_agent" "php-fatal-${site}" "9" "FAIL" \
      "PHP Fatal errors in Apache log: $site ($php_fatal occurrences)" \
      "PHP fatal/parse/exception errors found in /var/log/apache2/${site}_error.log.\n\nRecent:\n\`\`\`\n$(grep -E "PHP Fatal|PHP Parse error|PHP Exception" "$log" | tail -3)\n\`\`\`\n\nInvestigate and fix. Verify site returns HTTP 200 after fix."
  elif [ "${real_errors}" -gt 50 ]; then
    warn "[$site] Non-scan Apache errors: $real_errors (last log)"
    info "$(grep -v "AH01630" "$log" | grep -E "\[error\]" | tail -2)"
    queue_dispatch "dev-infra" "apache-errors-${site}" "6" "WARN" \
      "High Apache error rate: $site ($real_errors non-scan errors)" \
      "Apache error log /var/log/apache2/${site}_error.log has $real_errors non-security-scan errors.\n\nInvestigate and resolve."
  else
    pass "[$site] No PHP fatals; non-scan errors: $real_errors"
  fi

  # Flag repeated security probe IPs (same IP probing .env/.git > 20 times)
  top_probe=$(grep "AH01630" "$log" 2>/dev/null | grep -oE 'client [0-9.]+' | awk '{print $2}' | sort | uniq -c | sort -rn | head -1 || true)
  if [ -n "$top_probe" ]; then
    probe_count=$(echo "$top_probe" | awk '{print $1}')
    probe_ip=$(echo "$top_probe" | awk '{print $2}')
    if [ "${probe_count:-0}" -gt 20 ] 2>/dev/null; then
      warn "[$site] High-volume security probe: $probe_ip ($probe_count hits) — consider rate-limiting or fail2ban"
      queue_dispatch "dev-infra" "security-probe-${site}" "5" "WARN" \
        "High-volume security probe on $site: $probe_ip ($probe_count hits)" \
        "IP $probe_ip has probed $site for .env/.git files $probe_count times.\n\nConsider adding to fail2ban or rate-limiting in Apache config."
    fi
  fi
done

# ─── 4. DRUPAL WATCHDOG ─────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Drupal Watchdog (forseti.life)"
echo "$SEP"

drupal_root="/home/ubuntu/forseti.life/sites/forseti"
if [ -f "$drupal_root/vendor/bin/drush" ]; then
  watchdog_out=$(cd "$drupal_root" && vendor/bin/drush watchdog:show --severity=error --count=5 --format=string 2>/dev/null || echo "DRUSH_UNAVAILABLE")
  if echo "$watchdog_out" | grep -q "DRUSH_UNAVAILABLE\|Error\|Cannot"; then
    warn "Drupal watchdog: drush unavailable or errored"
  elif [ -z "$(echo "$watchdog_out" | tr -d '[:space:]')" ]; then
    pass "Drupal watchdog: no recent errors"
  else
    error_count=$(echo "$watchdog_out" | grep -c "^" || echo 0)
    if [ "$error_count" -gt 0 ]; then
      fail "Drupal watchdog: $error_count recent error(s)"
      echo "$watchdog_out" | head -5 | sed 's/^/   /'
      info "Full log: cd $drupal_root && vendor/bin/drush watchdog:show --severity=error"
      queue_dispatch "dev-forseti" "drupal-watchdog-errors" "8" "FAIL" \
        "Drupal watchdog has $error_count recent error(s)" \
        "Drush watchdog:show reports $error_count errors.\n\nCheck:\n\`\`\`bash\ncd $drupal_root && vendor/bin/drush watchdog:show --severity=error\n\`\`\`\n\nInvestigate and resolve each error. Verify clean watchdog after fix."
    fi
  fi
else
  info "Drupal watchdog: drush not found at $drupal_root/vendor/bin/drush"
fi

# ─── 5. SCOREBOARD FRESHNESS ────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Scoreboard Freshness  (target: updated within 7 days)"
echo "$SEP"

scoreboard_dir="knowledgebase/scoreboards"
stale_boards=0
if [ -d "$scoreboard_dir" ]; then
  for board in "$scoreboard_dir"/*.md; do
    [ -f "$board" ] || continue
    board_name=$(basename "$board" .md)
    mtime=$(stat -c %Y "$board" 2>/dev/null || stat -f %m "$board" 2>/dev/null || echo 0)
    age_days=$(( (now_ts - mtime) / 86400 ))
    if [ "$age_days" -gt 7 ]; then
      warn "Scoreboard stale: $board_name (${age_days}d old)"
      stale_boards=$(( stale_boards + 1 ))
      # Map board name to PM agent
      pm_agent="pm-forseti"
      [[ "$board_name" == *"dungeoncrawler"* ]] && pm_agent="pm-dungeoncrawler"
      queue_dispatch "$pm_agent" "scoreboard-stale-${board_name}" "3" "WARN" \
        "Scoreboard stale: $board_name (${age_days}d old)" \
        "The weekly scoreboard at knowledgebase/scoreboards/${board_name}.md has not been updated in ${age_days} days (target: ≤7 days).\n\nUpdate with current KPI data: post-merge regressions, reopen rate, time-to-verify, escaped defects, audit freshness."
    else
      pass "Scoreboard fresh: $board_name (${age_days}d old)"
    fi
  done
  [ "$stale_boards" -eq 0 ] && info "All scoreboards within 7-day freshness target"
else
  warn "Scoreboard directory missing: $scoreboard_dir"
fi

# ─── 6. FEATURE VELOCITY ────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Feature Velocity  (shipped features per recent release)"
echo "$SEP"

for site in forseti dungeoncrawler; do
  feature_dir="features/$site"
  [ -d "$feature_dir" ] || continue

  total=$(grep -rl "Status: shipped" "$feature_dir"/*/feature.md 2>/dev/null | wc -l || echo 0)
  in_progress=$(grep -rl "Status: in_progress" "$feature_dir"/*/feature.md 2>/dev/null | wc -l || echo 0)
  ready=$(grep -rl "Status: ready" "$feature_dir"/*/feature.md 2>/dev/null | wc -l || echo 0)
  info "[$site] shipped=$total  in_progress=$in_progress  ready(backlog)=$ready"

  # Stale in_progress: mtime > 48h
  stale_ip=0
  while IFS= read -r ffile; do
    fmtime=$(stat -c %Y "$ffile" 2>/dev/null || stat -f %m "$ffile" 2>/dev/null || echo 0)
    age_h=$(( (now_ts - fmtime) / 3600 ))
    if [ "$age_h" -gt 48 ]; then
      warn "[$site] Stale in_progress feature (${age_h}h): $(dirname "$ffile" | xargs basename)"
      stale_ip=$(( stale_ip + 1 ))
      feature_id=$(dirname "$ffile" | xargs basename)
      dev_agent="dev-${site}"
      queue_dispatch "$dev_agent" "stale-feature-${feature_id}" "6" "WARN" \
        "Stale in_progress feature: $feature_id (${age_h}h without update)" \
        "Feature $feature_id has been in_progress for ${age_h}h without a file update.\n\nEither complete implementation and update status to 'done', or re-scope back to 'ready' if blocked. File outbox entry with current status."
    fi
  done < <(grep -rl "Status: in_progress" "$feature_dir"/*/feature.md 2>/dev/null || true)

  if [ "$stale_ip" -eq 0 ] && [ "$in_progress" -gt 0 ]; then
    pass "[$site] All $in_progress in_progress feature(s) recently active"
  elif [ "$in_progress" -eq 0 ]; then
    pass "[$site] No in_progress features (release between cycles or idle)"
  fi
done

# ─── 7. KB LESSON RATE ──────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  KB Lesson Rate  (lessons filed in last 7 days)"
echo "$SEP"

lesson_dir="knowledgebase/lessons"
if [ -d "$lesson_dir" ]; then
  recent_lessons=$(find "$lesson_dir" -name "*.md" -mtime -7 2>/dev/null | wc -l)
  total_lessons=$(find "$lesson_dir" -name "*.md" 2>/dev/null | wc -l)
  if [ "$recent_lessons" -eq 0 ]; then
    warn "No KB lessons filed in last 7 days (total: $total_lessons) — confirm friction is being captured"
    info "If recurring blockers occurred this week, file a lesson: knowledgebase/lessons/YYYYMMDD-<slug>.md"
  else
    pass "KB lessons filed in last 7 days: $recent_lessons (total: $total_lessons)"
  fi
else
  warn "KB lessons directory missing: $lesson_dir"
fi

# ─── 8. TAILORING QUEUE ─────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Drupal Queue Health  (tailoring queue)"
echo "$SEP"

queue_log="/var/log/drupal/tailoring_queue.log"
if [ -f "$queue_log" ]; then
  last_entry=$(tail -1 "$queue_log" 2>/dev/null || true)
  # Check for error patterns
  error_count=$(grep -c -i "error\|exception\|failed" "$queue_log" 2>/dev/null || echo 0)
  last_mtime=$(stat -c %Y "$queue_log" 2>/dev/null || echo 0)
  age_h=$(( (now_ts - last_mtime) / 3600 ))

  if [ "$error_count" -gt 0 ]; then
    fail "Tailoring queue log has $error_count error/exception lines"
    info "$(grep -i "error\|exception\|failed" "$queue_log" | tail -2)"
    queue_dispatch "dev-forseti" "tailoring-queue-errors" "8" "FAIL" \
      "Tailoring queue has $error_count error/exception lines in log" \
      "The Drupal tailoring queue log ($queue_log) contains $error_count error/exception/failed lines.\n\nRecent errors:\n\`\`\`\n$(grep -i "error\|exception\|failed" "$queue_log" | tail -5)\n\`\`\`\n\nInvestigate the AI resume service integration. Check JSON parsing, API connectivity, and cache state. Fix and verify the queue processes without errors."
  elif [ "$age_h" -gt 2 ]; then
    warn "Tailoring queue log last updated ${age_h}h ago — queue cron may be stopped"
    info "Check: crontab -l | grep tailoring"
    queue_dispatch "dev-infra" "tailoring-queue-cron-stopped" "7" "WARN" \
      "Tailoring queue cron appears stopped (log ${age_h}h stale)" \
      "The tailoring queue log has not been updated in ${age_h}h. The queue cron may be stopped.\n\nCheck and restart:\n\`\`\`bash\ncrontab -l | grep tailoring\n\`\`\`"
  else
    pass "Tailoring queue: processing normally (log updated ${age_h}h ago)"
    info "Last entry: $last_entry"
  fi
else
  info "Tailoring queue log not found: $queue_log"
fi

# ─── 9. QA AUDIT FRESHNESS ──────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  QA Audit Freshness  (auto-site-audit/latest)"
echo "$SEP"

for site_qa in qa-forseti qa-dungeoncrawler; do
  latest_link="sessions/${site_qa}/artifacts/auto-site-audit/latest"
  if [ -L "$latest_link" ] || [ -d "$latest_link" ]; then
    # Get mtime of the symlink target
    audit_mtime=$(stat -c %Y "$latest_link" 2>/dev/null || stat -f %m "$latest_link" 2>/dev/null || echo 0)
    age_h=$(( (now_ts - audit_mtime) / 3600 ))
    findings=""
    if [ -f "${latest_link}/findings-summary.md" ]; then
      findings=$(grep -c "^[|*-]" "${latest_link}/findings-summary.md" 2>/dev/null || echo "?")
    fi
    if [ "$age_h" -gt 24 ]; then
      warn "[$site_qa] Audit stale: ${age_h}h old (target ≤24h)"
      info "Rerun: bash scripts/site-audit-run.sh ${site_qa#qa-}"
      queue_dispatch "$site_qa" "audit-stale-${site_qa}" "6" "WARN" \
        "QA audit stale for ${site_qa}: ${age_h}h old" \
        "The auto-site-audit latest output is ${age_h}h old (target ≤24h).\n\nRerun:\n\`\`\`bash\nbash scripts/site-audit-run.sh ${site_qa#qa-}\n\`\`\`\nVerify findings-summary.md is updated."
    else
      pass "[$site_qa] Audit fresh: ${age_h}h old (findings lines: ${findings:-?})"
    fi
  else
    warn "[$site_qa] No auto-site-audit/latest found — audit may never have run"
    info "Run: bash scripts/site-audit-run.sh ${site_qa#qa-}"
    queue_dispatch "$site_qa" "audit-never-run-${site_qa}" "7" "WARN" \
      "No QA audit found for ${site_qa} — audit may never have run" \
      "No auto-site-audit/latest directory found for $site_qa.\n\nRun the initial audit:\n\`\`\`bash\nbash scripts/site-audit-run.sh ${site_qa#qa-}\n\`\`\`"
  fi
done

# ─── 10. DEAD-LETTER INBOX DETECTION ────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Dead-Letter Inbox Items  (non-archived items > 48h old)"
echo "$SEP"

dead_letter_count=0
# Walk all agent inboxes
while IFS= read -r inbox_item; do
  [[ "$(basename "$inbox_item")" == _archived* ]] && continue
  [[ -d "$inbox_item/_archived" ]] && continue
  item_mtime=$(stat -c %Y "$inbox_item" 2>/dev/null || echo 0)
  age_h=$(( (now_ts - item_mtime) / 3600 ))
  if [ "$age_h" -gt 48 ]; then
    agent=$(echo "$inbox_item" | sed 's|sessions/||;s|/inbox.*||')
    item_name=$(basename "$inbox_item")
    warn "Dead letter: $agent → $item_name (${age_h}h old)"
    dead_letter_count=$(( dead_letter_count + 1 ))
    queue_dispatch "ceo-copilot-2" "dead-letter-${agent}-${item_name}" "5" "WARN" \
      "Dead-letter inbox item: $agent → $item_name (${age_h}h)" \
      "Inbox item ${item_name} in sessions/${agent}/inbox/ has been sitting for ${age_h}h without resolution.\n\nCEO action required: investigate, resolve or archive.\n- If resolvable: create outbox item with Status: done\n- If stale/superseded: move to _archived subfolder"
    [ "$dead_letter_count" -ge 5 ] && { info "(truncated — more dead letters exist)"; break; }
  fi
done < <(find sessions/*/inbox -mindepth 1 -maxdepth 1 -not -name "_archived" 2>/dev/null | sort)

if [ "$dead_letter_count" -eq 0 ]; then
  pass "No dead-letter inbox items found (all items < 48h or archived)"
fi

# ─── DISPATCH ───────────────────────────────────────────────────────────────
if [ "$DISPATCH_MODE" -eq 1 ] && [ "${#DISPATCH_ITEMS[@]}" -gt 0 ]; then
  echo ""
  echo "$SEP"
  echo "  Dispatching inbox items for findings..."
  echo "$SEP"
  dispatched=0
  for entry in "${DISPATCH_ITEMS[@]}"; do
    IFS='|' read -r d_agent d_slug d_roi d_sev d_title d_body <<< "$entry"
    # Check if agent session inbox exists
    inbox_dir="sessions/${d_agent}/inbox"
    if [ ! -d "$inbox_dir" ]; then
      echo "   ⚠️  No inbox for $d_agent — skipping: $d_slug"
      continue
    fi
    create_inbox_item "$d_agent" "$d_slug" "$d_roi" "$d_title" "$d_body"
    dispatched=$(( dispatched + 1 ))
  done
  echo "   ℹ️  Dispatched $dispatched item(s)"
elif [ "$DISPATCH_MODE" -eq 1 ]; then
  echo ""
  echo "$SEP"
  echo "  No findings to dispatch — all checks clean"
  echo "$SEP"
fi

# ─── SUMMARY ────────────────────────────────────────────────────────────────
echo ""
echo "$SEP"
if [ "$FAIL_COUNT" -eq 0 ] && [ "$WARN_COUNT" -eq 0 ]; then
  echo "✅  All system health checks PASS"
elif [ "$FAIL_COUNT" -eq 0 ]; then
  echo "⚠️   $WARN_COUNT warning(s) — review items above"
else
  echo "❌  $FAIL_COUNT check(s) FAILED, $WARN_COUNT warning(s) — see items above"
fi
echo "$SEP"

if [ "$JSON_MODE" -eq 1 ]; then
  echo ""
  echo '{"fail":'$FAIL_COUNT',"warn":'$WARN_COUNT'}'
fi

[ "$FAIL_COUNT" -eq 0 ]
