#!/usr/bin/env bash
# suggestion-intake.sh — Pull new community_suggestion nodes from Drupal into PM inbox
#
# Usage:
#   ./scripts/suggestion-intake.sh [site]      # site defaults to "forseti"
#
# What it does:
#   1. Queries Drupal for community_suggestion nodes with status = "new"
#   2. Marks each queried suggestion as "under_review" in Drupal
#   3. Writes a PM inbox batch item: sessions/pm-<site>/inbox/<date>-suggestion-intake/
#   4. Each suggestion gets its own sub-file for individual triage
#
# PM then reviews the inbox item and uses suggestion-triage.sh to accept/defer/decline each one.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-forseti}"
DRUPAL_ROOT="$(python3 -c "
import json, pathlib
p = pathlib.Path('org-chart/products/product-teams.json')
data = json.loads(p.read_text())
teams = data.get('teams', data) if isinstance(data, dict) else data
for t in teams:
    if t.get('site') == '${SITE}.life' or t.get('id') == '${SITE}':
        print(t.get('drupal_root',''))
        break
" 2>/dev/null)"

# Fallback: hardcoded known paths
if [ -z "$DRUPAL_ROOT" ]; then
  case "$SITE" in
    forseti) DRUPAL_ROOT="/home/keithaumiller/forseti.life/sites/forseti" ;;
    dungeoncrawler) DRUPAL_ROOT="/home/keithaumiller/forseti.life/sites/dungeoncrawler" ;;
    *) echo "ERROR: Unknown site '$SITE'. Pass drupal root as second arg." >&2; exit 1 ;;
  esac
fi

DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
PM_AGENT="pm-${SITE}"
INBOX_DIR="sessions/${PM_AGENT}/inbox"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"
BATCH_ITEM="${INBOX_DIR}/${DATE_TAG}-suggestion-intake"

if [ ! -f "$DRUSH" ]; then
  echo "ERROR: drush not found at $DRUSH" >&2
  exit 1
fi

echo "[suggestion-intake] Querying new suggestions for site: $SITE"
echo "[suggestion-intake] Drupal root: $DRUPAL_ROOT"

# Query new suggestions via drush php-eval
SUGGESTIONS_JSON="$(cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval '
$query = \Drupal::entityQuery("node")
  ->condition("type", "community_suggestion")
  ->condition("field_suggestion_status", "new")
  ->accessCheck(FALSE)
  ->sort("created", "ASC")
  ->execute();
$nodes = \Drupal\node\Entity\Node::loadMultiple($query);
$results = [];
foreach ($nodes as $node) {
  $results[] = [
    "nid"          => $node->id(),
    "title"        => $node->getTitle(),
    "created"      => date("Y-m-d H:i", $node->getCreatedTime()),
    "uid"          => $node->getOwnerId(),
    "summary"      => $node->get("field_suggestion_summary")->value ?? "",
    "original_msg" => $node->get("field_original_message")->value ?? "",
    "category"     => $node->get("field_suggestion_category")->value ?? "other",
    "conv_nid"     => $node->get("field_conversation_reference")->target_id ?? null,
  ];
}
echo json_encode($results);
' 2>/dev/null)"

COUNT="$(echo "$SUGGESTIONS_JSON" | python3 -c "import json,sys; print(len(json.loads(sys.stdin.read())))")"

if [ "$COUNT" -eq 0 ]; then
  echo "[suggestion-intake] No new suggestions found. Nothing to do."
  exit 0
fi

echo "[suggestion-intake] Found $COUNT new suggestion(s). Writing PM inbox item..."
mkdir -p "$BATCH_ITEM"

# Write the batch README
python3 - "$SUGGESTIONS_JSON" "$BATCH_ITEM" "$COUNT" "$SITE" "$DATE_TAG" <<'PY'
import json, sys, pathlib, textwrap

suggestions = json.loads(sys.argv[1])
batch_dir = pathlib.Path(sys.argv[2])
count = int(sys.argv[3])
site = sys.argv[4]
date_tag = sys.argv[5]

category_labels = {
    "safety_feature": "Safety Feature",
    "partnership": "Partnership Opportunity",
    "community_initiative": "Community Initiative",
    "technical_improvement": "Technical Improvement",
    "content_update": "Content Update",
    "general_feedback": "General Feedback",
    "other": "Other",
}

# Write batch README
readme = f"""# Suggestion Intake Batch — {date_tag}

**Site:** {site}.life  
**New suggestions:** {count}  
**Status:** Pending PM triage  

## What to do

For each suggestion below:
1. Review summary + original message
2. Update triage decision in `triage/NID-triage.md`
3. Run: `./scripts/suggestion-triage.sh {site} <nid> <accept|defer|decline|escalate> [feature-id]`
   - `accept`  → creates `features/<feature-id>/feature.md`, marks Drupal node `in_progress`
   - `defer`   → marks Drupal node `deferred`, queued for next cycle
   - `decline` → marks Drupal node `declined`
  - `escalate`→ routes to board-security review queue, keeps node `under_review`

## Mandatory security gate

If a suggestion clearly asks for security abuse, release-gate/integrity bypass, intentionally destructive behavior,
or a major architecture replatform/rewrite,
do not accept it at PM level. Use `escalate` for human board review first.
Normal product improvements should continue through standard PM triage.

## Quick summary table

| # | NID | Category | Title |
|---|-----|----------|-------|
"""
for i, s in enumerate(suggestions, 1):
    cat = category_labels.get(s["category"], s["category"])
    title_short = s["title"][:60] + ("..." if len(s["title"]) > 60 else "")
    readme += f'| {i} | {s["nid"]} | {cat} | {title_short} |\n'

readme += "\n## Suggestions (detail)\n\n"
for s in suggestions:
    cat = category_labels.get(s["category"], s["category"])
    conv_link = f"Node {s['conv_nid']}" if s["conv_nid"] else "N/A"
    readme += f"""---
### NID {s['nid']}: {s['title']}

- **Created:** {s['created']}
- **Category:** {cat}
- **Conversation:** {conv_link}
- **Drupal URL:** /node/{s['nid']}/edit

**Summary:**
{textwrap.fill(s['summary'], 100)}

**Original user message:**
{textwrap.fill(s['original_msg'], 100)}

**Triage:** _(see triage/NID-{s['nid']}-triage.md)_

"""

(batch_dir / "README.md").write_text(readme, encoding="utf-8")

# Write individual triage stubs
triage_dir = batch_dir / "triage"
triage_dir.mkdir(exist_ok=True)

for s in suggestions:
    cat = category_labels.get(s["category"], s["category"])
    triage_file = triage_dir / f"NID-{s['nid']}-triage.md"
    triage_file.write_text(f"""# Triage: NID {s['nid']} — {s['title']}

- **Category:** {cat}
  - **Decision:** [ ] accept  [ ] defer  [ ] decline  [ ] escalate
- **Feature ID** (if accept): forseti-  
- **Priority** (if accept): P0 | P1 | P2
- **PM notes:**

## Rationale

_Why accept/defer/decline? Mission alignment? Scope fit? Effort estimate?_

## Mission alignment check

Does this align with: "Democratize and decentralize internet services by building
community-managed versions of core systems for scientific, technology-focused, and tolerant people."

- [ ] Directly advances mission
- [ ] Neutral / infrastructure
- [ ] Does not align (decline)

## Security / integrity gate (required)

- [ ] No security abuse pattern (auth bypass, secret exposure, exploit primitive)
- [ ] No release-integrity bypass (skip QA/tests/approval, disable logging/guardrails)
- [ ] No stability-destructive action (data destruction, crash/DoS pattern)
- [ ] If any box above is uncertain or false → **escalate** for board review

""", encoding="utf-8")

print(f"Written {len(suggestions)} triage stubs to {triage_dir}")
PY

# Mark suggestions as under_review in Drupal
echo "[suggestion-intake] Marking suggestions as under_review in Drupal..."
NIDS_JSON="$(echo "$SUGGESTIONS_JSON" | python3 -c "import json,sys; print(json.dumps([s['nid'] for s in json.loads(sys.stdin.read())]))")"
cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval "
\$nids = json_decode('${NIDS_JSON}', true);
foreach (\$nids as \$nid) {
  \$node = \Drupal\node\Entity\Node::load(\$nid);
  if (\$node) {
    \$node->set('field_suggestion_status', 'under_review');
    \$node->save();
  }
}
echo count(\$nids) . ' nodes updated to under_review';
" 2>/dev/null && echo "" || echo "[suggestion-intake] WARN: could not update Drupal status (offline?)"

cd "$ROOT_DIR"

# Write ROI estimate for the PM agent
echo "3" > "$BATCH_ITEM/roi.txt"

echo "[suggestion-intake] Done."
echo "[suggestion-intake] Inbox item: $BATCH_ITEM"
echo "[suggestion-intake] $COUNT suggestion(s) ready for PM triage."
