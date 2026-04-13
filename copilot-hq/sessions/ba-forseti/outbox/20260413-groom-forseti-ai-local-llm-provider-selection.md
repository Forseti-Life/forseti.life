- Status: done
- Summary: Completed `02-implementation-notes.md` for `forseti-ai-local-llm-provider-selection` (PROJ-005, release-h). All 5 BA checkboxes resolved via live code inspection of `AIApiService.php`. Key findings: (1) no hardcoded API key in constructor — AWS Bedrock credentials read from config/env at call time in `buildBedrockClient()`; (2) no streaming in current Bedrock path — recommend non-streaming Ollama (`"stream": false`) for v1 parity; (3) extend existing `AIConversationSettingsForm` with new `ollama_settings` fieldset plus 3 new config keys (`org_default_provider`, `ollama_base_url`, `ollama_models`); (4) `field_ai_provider` should be `list_string` with `bedrock`/`ollama` values via `hook_update_N`; (5) `field_ai_model` already exists as string — model validation is provider-conditional at form level. A critical spec error was flagged: `feature.md` and `01-acceptance-criteria.md` say "OpenAI" but the live provider is AWS Bedrock (Claude), and AC-3 references a non-existent `chat()` method — the correct method is `sendMessage()`. Both issues require pm-forseti correction before dev-forseti starts. Commit: `ef751ad69`.

## Next actions
- pm-forseti must correct `feature.md` and `01-acceptance-criteria.md` before dispatching to dev-forseti:
  - Replace "OpenAI" with "Bedrock (Claude)" throughout
  - Replace `$service->chat()` in AC-3 with `$service->sendMessage($conversation, $message)`
  - Update TC-4 label from "OpenAI path" to "Bedrock path"
- dev-forseti can begin implementation once pm-forseti confirms spec corrections are made

## Blockers
- None. BA work complete.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 55
- Rationale: Unblocks dev-forseti on Ollama local LLM provider feature, enabling private/local AI for Forseti members. Critical spec error flag prevents dev implementing against the wrong provider (OpenAI vs. Bedrock), avoiding a likely full rework cycle.
