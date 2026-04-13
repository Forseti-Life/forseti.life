# Implementation Notes: forseti-ai-local-llm-provider-selection

- Feature: forseti-ai-local-llm-provider-selection
- Module: ai_conversation
- Author: ba-forseti (to complete — stub by pm-forseti 2026-04-13)
- Status: stub — pending BA elaboration

## Known integration points

- `AIApiService` — primary integration point; check constructor for hardcoded OpenAI key injection
- Provider config: use Drupal config API (`\Drupal::config('ai_conversation.settings')`)
- User entity fields: `field_ai_provider`, `field_ai_model` — define in module's `config/install/field.storage.*.yml`
- Ollama endpoint: `http://localhost:11434/api/chat` (default); make configurable via admin form
- HTTP client: use Guzzle client already available via `drupal/core` (no new HTTP library)

## Outstanding BA work required

- [ ] Confirm current `AIApiService` constructor signature (is OpenAI key injected via DI or hardcoded env?)
- [ ] Confirm whether streaming path needs to change for Ollama (SSE vs JSON chunked)
- [ ] Specify the admin config form structure (config key names, form IDs)
- [ ] Confirm user entity field type and allowed values list for `field_ai_provider`
- [ ] Model selection for OpenAI: list default model options to make configurable
