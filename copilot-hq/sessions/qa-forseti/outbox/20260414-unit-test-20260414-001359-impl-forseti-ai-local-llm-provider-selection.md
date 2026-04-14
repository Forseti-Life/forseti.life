- Status: done
- Summary: Unit-test verify complete for `forseti-ai-local-llm-provider-selection`. All code-verifiable ACs pass: AC-1 admin config form at `/admin/config/forseti/ai-provider` (anon→403 confirmed); AC-2 user.data service stores provider preference via `hook_form_user_form_alter` + submit handler; AC-3 `OllamaApiService.php` present with `isConfigured()` guard; AC-4 `resolveProvider()` cascades user.data → org default → Bedrock fallback; AC-5 `sendMessage()` catches `RuntimeException`, fires `addWarning()` messenger, falls back to Bedrock — no unhandled exception path. Site audit `20260414-005254` shows 0 admin-200 violations. AC-deviation noted and accepted: live stack uses Bedrock (not OpenAI) as primary, per dev outbox explanation. TC-5/TC-6 (Ollama edge cases) are manual-only until `OLLAMA_BASE_URL` is configured. Regression checklist line 178 updated to `[x]`. Verdict: **APPROVE**.

## Next actions
- Release-i queue: await PM/CEO dispatch for next inbox item
- TC-5/TC-6 Ollama manual tests: schedule when `OLLAMA_BASE_URL` is provisioned in the environment

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 70
- Rationale: AI provider selection is a core user-facing feature enabling Ollama/Bedrock switching. APPROVE unblocks PM for release-i gate progression. TC-5/TC-6 gap is low risk since Ollama is not yet configured in prod.

## Verification evidence
- Admin route: `ai_conversation.ai_provider_settings` → `/admin/config/forseti/ai-provider` — `_permission: administer site configuration` — anon→403 ✓
- `OllamaApiService.php` — `isConfigured()` line ~40, `chat()` line ~104 — RuntimeException on failure ✓
- `AIApiService.php` — `resolveProvider()` line ~137, `sendMessage()` fallback path line ~196 ✓
- `ai_conversation.module` — `hook_form_user_form_alter` user.data save confirmed lines 127,142-145,198-207 ✓
- Site audit `20260414-005254` — 0 admin-200 violations ✓
- Dev commits verified: `b4a08887a` (feat), `290eed3f6` (docs)
- Checklist commit: `114728e90`

## Manual test gaps (TC-5/TC-6)
- TC-5: Ollama fallback when `isConfigured()` returns false — requires OLLAMA_BASE_URL set in admin config
- TC-6: Ollama server unreachable → messenger warning + Bedrock fallback — requires live Ollama endpoint
- Both marked `manual-only` in suite.json; no automated coverage until env is provisioned
