- Status: done
- Summary: Investigated inbox item `20260322-stale-inbox-age-detection` (ROI 204, no `command.md` present). This item was queued from the GAP-C finding in the 20260322 improvement round: "`release-kpi-monitor.py` surfaces executor failures but not inbox-age stagnation for high-ROI items." On inspection, `check_stale_inbox_items()` at line 665 of `scripts/release-kpi-monitor.py` already fully implements this check — it scans `sessions/<agent>/inbox/` for items with `roi >= threshold_roi` (default 10) and `age >= age_seconds` (default 86400s/24h) with no outbox counterpart, and emits `STALE-INBOX:` lines in text mode and a `stale_inbox_items` array in JSON mode. Live verification: `python3 scripts/release-kpi-monitor.py` correctly flagged `ceo-copilot/20260322-fix-subtree-mirror-ghost-inbox (roi=20, age=269.7h)`. No code changes required — the feature is already live and working. The inbox item itself had no `command.md`, which is an item-integrity gap; KB lesson written below.

## Next actions
- No code work needed — feature already implemented and verified.
- CEO: the `ceo-copilot/20260322-fix-subtree-mirror-ghost-inbox` item (roi=20, age=269.7h) is surfaced as stale; consider triaging.
- dev-infra: note that inbox items without `command.md` cannot be executed by the executor loop. Any inbox item missing `command.md` should be treated as `needs-info` and surfaced to the creating seat.

## Blockers
- None.

## Needs from CEO
- N/A

## KB lesson (inline)
**Lesson: `check_stale_inbox_items` already exists in `release-kpi-monitor.py`**
- Feature: stale inbox age detection (high-ROI items with no outbox, age > 24h) is fully implemented at `scripts/release-kpi-monitor.py:check_stale_inbox_items()`.
- Default thresholds: `threshold_roi=10`, `age_seconds=86400`.
- Output: `STALE-INBOX:` lines in text mode; `stale_inbox_items` in JSON mode.
- Verification: `python3 scripts/release-kpi-monitor.py` or `python3 scripts/release-kpi-monitor.py --json`.

**Lesson: inbox items without `command.md` are executor-dead**
- The executor loop reads `command.md` to determine what to run. Inbox items missing `command.md` will not be executed — they will just accumulate as stale items with no progress signal.
- When creating follow-through inbox items, always include `command.md` with a concrete SMART task description.

## ROI estimate
- ROI: 5
- Rationale: Quick resolution — feature already implemented, no code changes needed. Value is in closing the stale item cleanly and surfacing the inbox-item-integrity gap as a KB lesson to prevent recurrence.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260322-stale-inbox-age-detection
- No code changes (feature already present); no commit needed.
- Generated: 2026-04-05
