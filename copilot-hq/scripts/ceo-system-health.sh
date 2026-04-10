#!/usr/bin/env bash
# ceo-system-health.sh — Systemic health checks beyond the release pipeline.
# Covers: error logs, executor failures, orchestrator health, feature velocity,
# scoreboard freshness, tailoring queue, KB lesson rate, dead-letter inboxes.
#
# Exit 0 = all checks pass
# Exit 1 = one or more FAIL or actionable WARN found
#
# Usage: bash scripts/ceo-system-health.sh [--json]
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

JSON_MODE=0
[[ "${1:-}" == "--json" ]] && JSON_MODE=1

FAIL_COUNT=0
WARN_COUNT=0
RESULTS=()

now_ts=$(date +%s)
now_iso=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

SEP="────────────────────────────────────────────────────────"

pass()  { echo "✅ PASS $*"; RESULTS+=("PASS|$*"); }
fail()  { echo "❌ FAIL $*"; RESULTS+=("FAIL|$*"); FAIL_COUNT=$(( FAIL_COUNT + 1 )); }
warn()  { echo "⚠️  WARN $*"; RESULTS+=("WARN|$*"); WARN_COUNT=$(( WARN_COUNT + 1 )); }
info()  { echo "   ℹ️  $*"; }

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
  elif [ "$recent_failures" -gt 0 ]; then
    warn "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
    info "Recent: $(ls -t "$failure_dir" | head -3 | tr '\n' ' ')"
  elif [ "$total_failures" -gt 100 ]; then
    warn "Executor failure backlog: $total_failures items — consider pruning after triage"
    info "Run: ls -t tmp/executor-failures/ | head -5"
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

pid_file="tmp/orchestrator.pid"
health_file="tmp/orchestrator-health-last-autoexec"

if [ -f "$pid_file" ]; then
  orc_pid=$(cat "$pid_file" 2>/dev/null || true)
  if [[ "$orc_pid" =~ ^[0-9]+$ ]] && ps -p "$orc_pid" >/dev/null 2>&1; then
    pass "Orchestrator: running (pid $orc_pid)"
  else
    fail "Orchestrator: pid file exists but process $orc_pid is not running"
    info "Restart: source orchestrator/.venv/bin/activate && python3 orchestrator/run.py"
  fi
else
  warn "Orchestrator: no pid file found — may not be running"
  info "Start: source orchestrator/.venv/bin/activate && python3 orchestrator/run.py"
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
  elif [ "${real_errors}" -gt 50 ]; then
    warn "[$site] Non-scan Apache errors: $real_errors (last log)"
    info "$(grep -v "AH01630" "$log" | grep -E "\[error\]" | tail -2)"
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
  elif [ "$age_h" -gt 2 ]; then
    warn "Tailoring queue log last updated ${age_h}h ago — queue cron may be stopped"
    info "Check: crontab -l | grep tailoring"
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
    else
      pass "[$site_qa] Audit fresh: ${age_h}h old (findings lines: ${findings:-?})"
    fi
  else
    warn "[$site_qa] No auto-site-audit/latest found — audit may never have run"
    info "Run: bash scripts/site-audit-run.sh ${site_qa#qa-}"
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
    [ "$dead_letter_count" -ge 5 ] && { info "(truncated — more dead letters exist)"; break; }
  fi
done < <(find sessions/*/inbox -mindepth 1 -maxdepth 1 -not -name "_archived" 2>/dev/null | sort)

if [ "$dead_letter_count" -eq 0 ]; then
  pass "No dead-letter inbox items found (all items < 48h or archived)"
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
