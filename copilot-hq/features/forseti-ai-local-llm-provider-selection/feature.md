# Feature: forseti-ai-local-llm-provider-selection

- Status: ready
- Website: forseti.life
- Module: ai_conversation
- Release: 20260412-forseti-release-h
- Owner: pm-forseti
- Project: PROJ-005

## Summary

The AI chat experience at `/forseti/chat` is hard-wired to a single external LLM provider (OpenAI API). This is a lock-in risk and is contrary to the org mission of decentralized services. This feature adds a provider selection layer to `AIApiService`: users can choose their preferred LLM backend (OpenAI or Ollama/local) in account settings, and the AI service resolves the correct backend at request time. Ollama is added as the first self-hosted option, enabling fully local inference with no external API dependency.

## Goal

Allow authenticated users to select their LLM provider preference (OpenAI or Ollama) so the platform is not locked to a single external API, and so users can run fully local inference if desired.

## Acceptance criteria

- AC-1: Admin form at `/admin/config/forseti/ai-provider` allows setting org-default provider (`openai` or `ollama`) and `OLLAMA_BASE_URL` (e.g., `http://localhost:11434`) and available Ollama model list.
- AC-2: Authenticated users can set a personal provider preference (OpenAI or Ollama) and model on their account page (`/user/{uid}/edit`); stored as `field_ai_provider` and `field_ai_model` on the user entity.
- AC-3: `AIApiService::chat()` (or the injected service) resolves the effective provider at request time: user preference → org default → OpenAI fallback.
- AC-4: A new `OllamaApiService` (or equivalent) makes HTTP POST requests to `{OLLAMA_BASE_URL}/api/chat` with the correct Ollama payload format; streaming response is handled consistently with the existing OpenAI path.
- AC-5: If the user's selected provider is unreachable (connection refused / timeout), the chat UI shows a provider-error banner and falls back to the org default without crashing.
- AC-6: Existing chat routes (`/forseti/chat`, `/forseti/conversations`) and all existing conversation API endpoints work correctly regardless of the effective provider.
- AC-7: If `OLLAMA_BASE_URL` is unset and a user or admin attempts to select Ollama, the settings form displays an inline error; Ollama option is disabled in user account settings until configured.

## Definition of done

- All 7 AC pass QA verification on the dev environment.
- `AIApiService` is not tightly coupled to OpenAI after this feature (provider is injected or resolved via config).
- No regressions on existing `/forseti/chat` flow (covered by `qa-suites/products/forseti/suite.json` suite `ai_conversation_user_chat`).

## Notes

- `AIApiService` is the integration point; check current constructor for hardcoded OpenAI key injection.
- Ollama by default listens on `http://localhost:11434`; this is the expected dev setup.
- Do not introduce a PDF library or any heavy dependency. HTTP calls to Ollama use Drupal's `\Drupal\Component\Utility\Http` or the Guzzle client already available via `drupal/core`.
- Model selection for OpenAI (e.g., `gpt-4o`, `gpt-4o-mini`) should also be made configurable as part of this work to maintain parity.
