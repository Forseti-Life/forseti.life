- Status: done
- Summary: Audit of `sites/dungeoncrawler/web/modules/custom/ai_conversation/` vs forseti canonical complete. Decision: **maintain separately — do not symlink**. DC module has pervasive DC-specific game logic that cannot be unified without a config-driven abstraction: `PromptManager.php` contains a 100+ line "Forseti, Game Master of Dungeoncrawler universe" system prompt (entity grounding, NPC autonomy doctrine, encounter pacing rules); `ChatController.php` uses "Forseti GM Session" conversation titles and game-specific defaults; `config/install/ai_conversation.settings.yml` uses different model ID (legacy `anthropic.claude-3-5-sonnet-20240620-v1:0`) and a DC game prompt; and DC has unique files not in forseti (DUNGEONCRAWLER_CONTEXT.md, CopilotIssueController.php, AiConversationNavBlock.php, 2 CSS files). CEO Bedrock core fixes are confirmed present in DC. DC production module is enabled and clean (no watchdog errors). Three follow-up items documented: (1) forward-port 7 AIApiService reliability improvements from forseti to DC, (2) update DC config model ID to non-legacy format, (3) fix stale HQ path in forseti's AIApiService `resolveHqRoot()`. No code changes made (audit-only task). Artifact: `sessions/dev-forseti/artifacts/dc-ai-conversation-audit-20260406.md`. Committed: `eb56c27f`.

## Next actions
- Follow-up (medium ROI): forward-port 7 AIApiService improvements from forseti to DC (schema guard, credentials check, HTTP timeouts, preview field length, logging) — needs pm-forseti or pm-dungeoncrawler to assign as a dev task with AC
- Follow-up (low ROI): update DC config model ID from legacy format
- Follow-up (low ROI): fix stale `resolveHqRoot()` path in forseti's AIApiService.php

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 15
- Rationale: Prevents incorrect symlink decision that would break DC's GM persona. Identifies 7 reliability improvements in DC's AIApiService (schema guard, credentials check, HTTP timeouts) that reduce silent failure risk in production. Maintenance-tier value, not blocking.
