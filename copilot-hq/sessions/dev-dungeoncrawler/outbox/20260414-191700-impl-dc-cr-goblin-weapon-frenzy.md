- Status: in_progress
- Summary: Reviewing the feature brief and existing codebase to understand the implementation scope before writing any code.

Let me read the feature brief and explore the codebase.

```bash
cat /home/ubuntu/forseti.life/copilot-hq/features/dc-cr-goblin-weapon-frenzy/feature.md
```

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/features/dc-cr-goblin-weapon-frenzy/feature.md"}}
</tool_call>
<tool_response>
# Feature: dc-cr-goblin-weapon-frenzy

## Metadata
- Feature ID: dc-cr-goblin-weapon-frenzy
- Website: dungeoncrawler
- Release: 20260412-dungeoncrawler-release-m
- Status: in_progress
- Priority: medium
- PM owner: pm-dungeoncrawler

## Problem statement
Goblins in Pathfinder 2e gain access to goblin weapons (dogslicer, horsechopper, etc.) via Goblin Weapon Familiarity. The follow-on feat, Goblin Weapon Frenzy (level 5), adds critical specialization effects when using these weapons on a critical hit. Without this feat, critical hits with goblin weapons deal bonus damage or dice, but the specialization effect (e.g., flat-footed, prone, etc.) does not fire. This creates an incomplete combat experience for goblin players.

## Solution overview
Implement the `goblin_weapon_frenzy` feat hook in the combat resolution (cr) module. When a goblin character has this feat and scores a critical hit with a goblin-tagged weapon, trigger the appropriate critical specialization effect for that weapon type.

## Scope
- In scope: feat availability at level 5 (requires Goblin Weapon Familiarity), critical hit hook, weapon-type dispatch to existing critical specialization table, dogslicer + horsechopper as primary test cases
- Out of scope: adding new weapon types, new critical specialization effects, changes to goblin ancestry feat list (dc-cr-goblin-ancestry owns that)

## Acceptance criteria reference
- `sessions/dev-dungeoncrawler/inbox/20260414-191700-impl-dc-cr-goblin-weapon-frenzy/01-acceptance-criteria.md`
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "find /home/ubuntu/forseti.life/sites/dungeoncrawler -type f | grep -E '(goblin|weapon|frenzy|crit)' | sort | head -60"}}
</tool_call>
<tool_response>
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "find /home/ubuntu/forseti

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-191700-impl-dc-cr-goblin-weapon-frenzy
- Generated: 2026-04-16T16:55:28+00:00
