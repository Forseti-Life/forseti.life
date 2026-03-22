#!/usr/bin/env bash
# suggestion-triage.sh — Record PM triage decision for a community_suggestion node
#
# Usage:
#   ./scripts/suggestion-triage.sh <site> <nid> <accept|defer|decline|escalate> [feature-id]
#
# Examples:
#   ./scripts/suggestion-triage.sh forseti 42 accept forseti-safety-chat-history
#   ./scripts/suggestion-triage.sh forseti 43 defer
#   ./scripts/suggestion-triage.sh forseti 44 decline
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-}"
NID="${2:-}"
DECISION="${3:-}"
FEATURE_ID="${4:-}"

if [ -z "$SITE" ] || [ -z "$NID" ] || [ -z "$DECISION" ]; then
  echo "Usage: $0 <site> <nid> <accept|defer|decline|escalate> [feature-id]" >&2
  exit 1
fi

case "$DECISION" in
  accept|defer|decline|escalate) ;;
  *) echo "ERROR: decision must be accept, defer, decline, or escalate" >&2; exit 1 ;;
esac

if [ "$DECISION" = "accept" ] && [ -z "$FEATURE_ID" ]; then
  echo "ERROR: feature-id required when accepting a suggestion" >&2
  echo "  Example: $0 $SITE $NID accept forseti-my-feature-name" >&2
  exit 1
fi

if [ "$DECISION" = "escalate" ] && [ -n "$FEATURE_ID" ]; then
  echo "ERROR: do not pass feature-id when decision is escalate" >&2
  exit 1
fi

case "$SITE" in
  forseti) DRUPAL_ROOT="/home/keithaumiller/forseti.life/sites/forseti" ;;
  dungeoncrawler) DRUPAL_ROOT="/home/keithaumiller/forseti.life/sites/dungeoncrawler" ;;
  *) echo "ERROR: Unknown site '$SITE'" >&2; exit 1 ;;
esac

# Map decision → Drupal status
case "$DECISION" in
  accept)  DRUPAL_STATUS="in_progress" ;;
  defer)   DRUPAL_STATUS="deferred" ;;
  decline) DRUPAL_STATUS="declined" ;;
  escalate) DRUPAL_STATUS="under_review" ;;
esac

echo "[suggestion-triage] Site: $SITE | NID: $NID | Decision: $DECISION"

# Fetch suggestion data for feature brief
SUGGESTION_JSON="$(cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval "
\$node = \Drupal\node\Entity\Node::load($NID);
if (!\$node || \$node->bundle() !== 'community_suggestion') {
  echo json_encode(['error' => 'not found']);
} else {
  echo json_encode([
    'title'    => \$node->getTitle(),
    'summary'  => \$node->get('field_suggestion_summary')->value,
    'category' => \$node->get('field_suggestion_category')->value,
    'original' => \$node->get('field_original_message')->value,
  ]);
}
" 2>/dev/null)"

if echo "$SUGGESTION_JSON" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); exit(0 if 'error' not in d else 1)" 2>/dev/null; then
  :
else
  echo "ERROR: Suggestion NID $NID not found in Drupal" >&2
  exit 1
fi

RISK_REPORT_JSON="$(python3 - "$SUGGESTION_JSON" <<'PY'
import json
import re
import sys

s = json.loads(sys.argv[1])
txt = "\n".join([
  str(s.get("title") or ""),
  str(s.get("summary") or ""),
  str(s.get("original") or ""),
]).lower()

signals = []
rules = [
  ("auth-bypass", [
    r"\bauth(?:entication|orization)?\s+bypass\b",
    r"\bdisable\s+(?:auth|authentication|authorization|permissions?)\b",
    r"\bescalat(?:e|ing)\s+privileges?\b",
    r"\bimpersonat(?:e|ion)\b",
  ]),
  ("secrets-credentials", [
    r"\b(api\s*key|secret\s*key|access\s*token|passwords?|credentials?)\b",
    r"\bexfiltrat(?:e|ion)\b",
    r"\bdecrypt\b",
  ]),
  ("release-integrity-bypass", [
    r"\bskip\s+(?:qa|tests?|test\s*suite|review|approval)s?\b",
    r"\bbypass\s+(?:release|shipping|gate|approval)s?\b",
    r"\bauto\s*(?:push|deploy)\s*(?:to\s*)?(?:prod|production|main)?\b",
    r"\bforce\s+push\b",
    r"\bdisable\s+(?:logging|audit|guardrail|safety)\b",
  ]),
  ("destructive-stability", [
    r"\b(drop\s+table|truncate\s+table|delete\s+database|destroy\s+data)\b",
    r"\brm\s+-rf\b",
    r"\bfork\s+bomb\b",
    r"\bdenial\s+of\s+service\b|\bdos\b",
    r"\bcrash\b|\bkernel\s+panic\b|\bout\s+of\s+memory\b",
  ]),
  ("exploit-primitives", [
    r"\b(sql\s*injection|xss|cross\s*site\s*scripting|csrf|rce|remote\s+code\s+execution)\b",
    r"\b(command\s+injection|path\s+traversal|backdoor|malware)\b",
  ]),
]

for label, patterns in rules:
  for pattern in patterns:
    if re.search(pattern, txt):
      signals.append(label)
      break

signals = sorted(set(signals))
print(json.dumps({"risky": bool(signals), "signals": signals}, ensure_ascii=False))
PY
)"

RISKY="$(echo "$RISK_REPORT_JSON" | python3 -c "import json,sys; print('true' if json.loads(sys.stdin.read()).get('risky') else 'false')")"
RISK_SIGNALS="$(echo "$RISK_REPORT_JSON" | python3 -c "import json,sys; d=json.loads(sys.stdin.read()); print(','.join(d.get('signals', [])))")"

if [ "$DECISION" = "accept" ] && [ "$RISKY" = "true" ]; then
  echo "[suggestion-triage] SECURITY GATE: NID $NID cannot be accepted by PM due to risk signals: ${RISK_SIGNALS}" >&2
  DECISION="escalate"
  DRUPAL_STATUS="under_review"
  FEATURE_ID=""
fi

# Update Drupal status
cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval "
\$node = \Drupal\node\Entity\Node::load($NID);
\$node->set('field_suggestion_status', '$DRUPAL_STATUS');
\$node->save();
echo 'Updated NID $NID to $DRUPAL_STATUS';
" 2>/dev/null
cd "$ROOT_DIR"

echo "[suggestion-triage] Drupal node $NID → $DRUPAL_STATUS"

if [ "$DECISION" = "escalate" ]; then
  ESC_AGENT="${SUGGESTION_SECURITY_REVIEW_AGENT:-ceo-copilot}"
  slug="$(printf '%s' "$NID" | tr -cd '0-9')"
  item_id="$(date +%Y%m%d)-needs-board-security-review-nid-${slug}"
  esc_dir="sessions/${ESC_AGENT}/inbox/${item_id}"
  mkdir -p "$esc_dir"
  printf '%s\n' "21" > "$esc_dir/roi.txt"
  python3 - "$SUGGESTION_JSON" "$esc_dir/command.md" "$SITE" "$NID" "$RISK_SIGNALS" <<'PY'
import json
import pathlib
import sys

s = json.loads(sys.argv[1])
out = pathlib.Path(sys.argv[2])
site = sys.argv[3]
nid = sys.argv[4]
signals = [x for x in (sys.argv[5] or "").split(",") if x]
signal_text = ", ".join(signals) if signals else "manual-escalation"

out.write_text(
f"""# Board Security Review Required: community suggestion NID {nid}

- Site: {site}
- Suggestion NID: {nid}
- Suggested by PM action: escalate
- Risk signals: {signal_text}

This suggestion must not be accepted/released by PM without human board review.

## Suggested path
1. Human reviewer board decides: `approve-with-constraints` or `reject`.
2. If approved, include explicit constraints and required safeguards.
3. PM then records a follow-up decision via:
   - `./scripts/suggestion-triage.sh {site} {nid} accept <feature-id>` (only after board approval), or
   - `./scripts/suggestion-triage.sh {site} {nid} decline`

## Source snapshot
- Title: {s.get('title','')}
- Category: {s.get('category','')}

Summary:
{s.get('summary','')}

Original user message:
{s.get('original','')}
""",
encoding="utf-8",
)
PY
  echo "[suggestion-triage] Escalation queued for human board review: $esc_dir"
  echo "[suggestion-triage] Done. Decision recorded: escalate for NID $NID"
  exit 0
fi

# If accepted: create feature brief in features/
if [ "$DECISION" = "accept" ]; then
  FEATURE_DIR="features/${FEATURE_ID}"
  if [ -d "$FEATURE_DIR" ]; then
    echo "[suggestion-triage] Feature dir already exists: $FEATURE_DIR — skipping creation"
  else
    mkdir -p "$FEATURE_DIR"
    python3 - "$SUGGESTION_JSON" "$FEATURE_ID" "$SITE" "$NID" "$FEATURE_DIR" <<'PY'
import json, sys, pathlib, datetime

s = json.loads(sys.argv[1])
feature_id = sys.argv[2]
site = sys.argv[3]
nid = sys.argv[4]
feature_dir = pathlib.Path(sys.argv[5])
today = datetime.date.today().isoformat()

category_labels = {
    "safety_feature": "Safety Feature",
    "partnership": "Partnership Opportunity",
    "community_initiative": "Community Initiative",
    "technical_improvement": "Technical Improvement",
    "content_update": "Content Update",
    "general_feedback": "General Feedback",
    "other": "Other",
}
cat = category_labels.get(s.get("category","other"), s.get("category","other"))

feature_md = f"""# Feature Brief: {s['title']}

- Work item id: {feature_id}
- Website: {site}.life
- Module: _TBD (PM to assign)_
- Status: planned
- Priority: P1
- PM owner: pm-{site}
- Dev owner: dev-{site}
- QA owner: qa-{site}
- Source: community_suggestion NID {nid} (Talk to Forseti intake)
- Category: {cat}
- Created: {today}

## Goal

{s['summary']}

## Non-goals

_PM to define during acceptance criteria refinement._

## Acceptance Criteria

_PM to write. See `templates/01-acceptance-criteria.md`._

## Mission Alignment

This feature was submitted by a user via the "Talk to Forseti" channel. It aligns with the mission:
> "Democratize and decentralize internet services by building community-managed versions of core systems
> for scientific, technology-focused, and tolerant people."

_PM to confirm: how specifically does this feature advance that mission?_

## Original User Message

> {s.get('original', '(not captured)')}

## Risks

_PM to assess during triage._

## Security & Release Integrity Gate

- Board security review required: no
- Board approval artifact: n/a
- Intake risk signals: ${RISK_SIGNALS:-none}

## Latest updates

- {today}: Created from community_suggestion NID {nid} via suggestion-triage.sh
"""
(feature_dir / "feature.md").write_text(feature_md, encoding="utf-8")
print(f"Created {feature_dir / 'feature.md'}")
PY
    echo "[suggestion-triage] Feature brief created: $FEATURE_DIR/feature.md"
    echo "[suggestion-triage] Next: fill in Acceptance Criteria and assign module ownership"
  fi
fi

echo "[suggestion-triage] Done. Decision recorded: $DECISION for NID $NID"
