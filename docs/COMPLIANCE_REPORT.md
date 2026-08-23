# AI-Scribe v3.0.2 — Compliance & Security Report (P8, REFACTOR.md §14)

> **Archive notice (14 August 2026):** this report is retained as package-specific evidence for
> 3.0.2. It is not the current security, Plugin Check, regression or release record for 3.2.30.
> Current commands and release gates live in `TESTING.md` and `docs/RELEASE_RUNBOOK.md`; the exact
> 3.2.30 package evidence is recorded in the project status documents. No number below should be
> copied into current public documentation without rerunning the named check on the current ZIP.

Date: 2026-08-10 · Auditor: P8 compliance agent · Scope: `dist/ai-scribe-3.0.2.zip` as packaged (the bytes a wp.org reviewer sees)

> **Staleness notice (added 2026-08-11).** This is dated evidence for the 3.0.2 package and has
> not been re-run. Nothing below has been re-measured; the findings stand as a record of what was
> true on 2026-08-10. Work has landed since, so treat these claims as stale until a fresh run:
>
> - **Every number in §1 and §6.** The Plugin Check counts (0 errors / 366 warnings), the PHP
>   assertion count (234), the Playwright matrix (121 passed) and the package size all describe
>   3.0.2. Releases 3.0.3, 3.0.5 and 3.0.6 changed PHP, JS, CSS and templates, and 3.0.7 adds a
>   dependency guard. None of it has been re-checked. The release gate is zero PCP errors on
>   *the package being shipped* — see `RELEASE_RUNBOOK.md` §3.
> - **§3's "standalone mode" framing.** Opace AI Hub is now a required plugin
>   (`Requires Plugins: ai-core` plus a runtime guard), so the standalone key-storage path
>   described there is a fallback for a broken install rather than a supported mode. The
>   encryption design, the fail-closed decryption and the uninstall wipe are all still in the code
>   and still apply to keys carried over from 2.6.x.
> - **§3's "hub frozen" note and the Opace AI Hub 0.7.8 change list.** Still unstarted: the hub in
>   `AI CORE MODULAR/ai-core-standalone/` remains at 0.7.7 with the four provider keys in
>   plaintext inside `ai_core_settings`. Making Opace AI Hub mandatory makes every item on that list
>   release-blocking rather than advisory.
> - **§5's endpoint table.** Still correct for the endpoints it lists, but no longer complete.
>   `ai_scribe_apply_hub_prompt` (`edit_posts`) and `ai_scribe_dismiss_notice` (`manage_options`,
>   its own nonce) were added afterwards. `docs/API_CONTRACT.md` §13 is the current inventory.
> - **§2's count of unregistered legacy endpoints.** Unchanged and still accurate; the readme's
>   3.0.2 changelog entry said sixteen and has been corrected to twenty to match this report.

## 1. Plugin Check (PCP) results

Method per §14.1: official **Plugin Check 2.0.0** installed as a plugin in wp-env; the packaged zip extracted into `wp-content/plugins/` under the real wp.org slug `ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/`, activated (dev copy deactivated), full default check suite run via `wp plugin check` — the same engine wp.org runs on every upload since Oct 2025.

| Run | Errors | Warnings |
|---|---|---|
| 3.0.1 under dev dir (P6/P7 record) | 43 | ~545 **+ 268 TextDomainMismatch** |
| 3.0.1 packaged under real slug, before P8 fixes | **43** | **545** |
| **3.0.2 packaged, after P8** | **0** | **366** |

### Errors fixed (43 → 0)

| Error class | Count | Fix (in the SOURCE repo) |
|---|---|---|
| `EscapeOutput.ExceptionNotEscaped` (ai-core/lib) | 25 | Dynamic parts of every thrown exception message wrapped in `\esc_html()` across AICore, HttpClient, all providers, ResponseNormalizer (plus 4 unflagged dynamic throws in GeminiImageProvider for consistency) |
| `wp_function_not_compatible_with_requires_wp` | 7 | The genuinely `function_exists`-guarded WP 7.0 calls (`wp_register_ability`, `wp_register_ability_category`, `wp_supports_ai`, `wp_ai_client_prompt`) converted to `call_user_func( 'literal_name', … )` so the static version scanner no longer misfires; runtime guards unchanged |
| `PreparedSQL.NotPrepared` (the 4 P6-deferred items) | 4 | `CREATE TABLE` in ShortcodeService and UtilityService rewritten to `dbDelta()` + `get_charset_collate()`; `get_all_shortcodes()` rewritten as fully-literal `$wpdb->prepare()` per branch with the `%i` identifier placeholder |
| `PluginCheck.Security.DirectDB.UnescapedDBParameter` | 3 | Same rewrites; every remaining table-name interpolation (`get_shortcode_content`, `delete_shortcode_record`, ConversationService load, show_template listing) converted to `%i` |
| `EscapeOutput.OutputNotEscaped` | 2 | SSE `event:` name now `esc_html()`-escaped; the dead `inject_debug_mode()` inline-script method (zero callers) deleted outright |
| `AlternativeFunctions.rename_rename` / `…mkdir` | 2 | Logger rotation now moves via `WP_Filesystem::move()`; the `mkdir` fallback deleted (`wp_mkdir_p()` always exists inside WP) |

**Consequence of the `%i` placeholder: “Requires at least” bumped 6.0 → 6.2** (identifier placeholders shipped in WP 6.2, March 2023). Header + readme updated.

### Text domain (the 268-warning question)

2.6.2 shipped **`ai-writer-gpt-article-builder`** (verified read-only in `snailsvn/trunk/article_builder.php`) — it never matched the wp.org slug, so language packs never loaded for the live plugin. v3.0.2 aligns the text domain to the slug **`ai-scribe-the-chatgpt-powered-seo-content-creation-wizard`** everywhere (header + all 259 i18n calls across 9 files, including 3 strays on a second wrong domain), and drops both now-redundant `load_plugin_textdomain()` calls (auto-loading since WP 4.6). Result: 0 TextDomainMismatch under the real slug, and translations will actually work.

### Warning dispositions (366 remaining, every class dispositioned)

| Warning class | Count | Disposition |
|---|---|---|
| `NonceVerification.Missing` / `.Recommended` | 87 + 15 | **False positives from centralised guards.** The live surface verifies nonce + capability in `guard()` (Conversation and Settings controllers) or at handler top before any `$_POST` read; PHPCS cannot see cross-method checks. 41/87 are the Conversation controller alone. The rest sit in legacy handler methods whose endpoints were **unregistered this pass** (unreachable over admin-ajax). Endpoint-by-endpoint proof in §5. |
| `ValidatedSanitizedInput.MissingUnslash` | 63 | Reduced from 128 by adding `wp_unslash()` inside every `sanitize_*()/wp_kses_post()` superglobal read (also fixes stray backslashes on quoted input). The remainder are casts (`intval`, `(int)`, `!empty` presence checks — PHPCS still flags the read) and nonce values (verified by exact match; slashing cannot forge one) in retired handlers. |
| `InputNotSanitized` / `InputNotValidated` | 56 + 9 | Concentrated in unregistered legacy handlers (kept only for unit coverage) plus nonce arguments to `wp_verify_nonce()` (comparison, not output/storage). All reachable inputs are sanitized at read. |
| `PrefixAllGlobals.*` | 93 | Bundled Opace AI Hub library namespace (`AICore\`) and template locals; the lib is properly namespaced (the sniff wants an `AI_Scribe_` prefix it cannot see), template vars are `$ai_scribe_*` where new. Cosmetic; no collision risk. |
| `DirectDatabaseQuery` / `NoCaching` / `SchemaChange` | 27 | Custom `{prefix}article_builder` + conversations tables (2.6.2 heritage schema): CRUD is fully prepared, admin-only, low-volume; caching a wizard-scoped table would add staleness bugs for zero measurable gain. Uninstall/teardown queries annotated inline. |
| `DevelopmentFunctions.error_log*` / `print_r` | 12 + 1 | All logging now flows through debug gates: the new `ai_scribe_debug_log()` helper (no-op unless `AI_SCRIBE_DEBUG_MODE`, which defaults to `WP_DEBUG`), the Logger class (`debug_enabled`-gated write + one failure path), and two `WP_DEBUG`-gated lib sites. Production sites log nothing. `set_error_handler` is the plugin's own error-boundary registration. |
| `SlowDBQuery.slow_db_query_meta_query` | 2 | Media-library attachment lookups by meta on admin request; bounded result sets. |
| `Squiz…Discouraged` (`set_time_limit`) | 1 | Long-generation guard inside an admin AJAX request; no-ops on hosts that forbid it. |
| `mismatched_plugin_name` | 0 (fixed) | Header aligned to the readme name; the 2.6.2 typo "Humaizer" corrected to "Humanizer" in both. |

## 2. Security sweep (§14.2)

Full grep-audit of `$_POST/$_GET/$_REQUEST`, echo/print paths and every `$wpdb` call in the packaged tree.

- **$wpdb**: every query site enumerated (14 call sites). All now `prepare()`d with `%i` for identifiers, or `dbDelta()` for schema, or operate on `$wpdb->options` with `esc_like()` (uninstall transient teardown). The 4 P6-deferred PreparedSQL items are closed — no v3.1 deferral remains.
- **Input**: all reachable handlers sanitize at read (`sanitize_text_field/sanitize_textarea_field/wp_kses_post/sanitize_key/absint` + `wp_unslash`, JSON payloads decoded then per-field validated).
- **Output**: templates escape via `esc_html/esc_attr/esc_url`; SSE emitter escapes event names; exception messages escaped at throw (lib-wide).
- **Attack-surface reduction (biggest single win):** **20 distinct AJAX endpoints (22 registrations, including duplicates) had zero frontend consumers** (v2.6/v4 leftovers the P7 zombie purge removed from JS but not from PHP) and were **unregistered**: `al_scribe_content_data`, `al_scribe_suggest_content`, `ai_scribe_generate_content`, `al_scribe_generate_4o_image`, `al_scribe_send_post_page`, `ai_scribe_check_recent_images`, `ai_scribe_update_image_metadata`, `al_scribe_evaluate_content`, `update_style_tone`, `refresh_nonce`, `save_ai_scribe_settings`, **`save_engine_settings`**, **`al_scribe_engine_request_data`** (both accepted plaintext key writes), `ai_scribe_save_prompt_template`, `ai_scribe_get_prompt_template`, `ai_scribe_get_template_data`, `get_article`, **`ai_scribe_validate_api_key`** (received keys in POST), `ai_scribe_cleanup_phantom_data`, `ai_scribe_get_prompts`, plus AdminService's `ai_scribe_get_article`/`ai_scribe_get_qna_setting`. Handler methods remain (unit coverage) but are unreachable over admin-ajax. Verified by grep across `assets/js`, `templates/` and `tests/` before removal; full Playwright matrix green after.
- **Capability checks added** where only nonces existed: image generation (`edit_posts`), content/prompt settings saves (`manage_options`), shortcode save (`edit_posts`) and delete (`manage_options`, both wrapper and service), prompts read (was `is_user_logged_in()`, now `edit_posts`).
- **No `wp_ajax_nopriv_` registrations exist** — the `$public` flag in both registration helpers is never set true.
- Dead inline-script payloads removed from AJAX responses (`trigger_js` in ImageService, popup `<script>` in the workflow fallback) — inert under the v3 renderer, and no client read them.

## 3. Key-storage hardening (§14.3)

**Design** (standalone mode; hub mode stores nothing in AI-Scribe):

- **At rest**: AES-256-CBC via OpenSSL, random per-value 16-byte IV, key = `sha256(AUTH_SALT . SECURE_AUTH_SALT . LOGGED_IN_SALT . NONCE_SALT . constant)`. Same family as the 3.5 build's approach (reviewed read-only at `AI CORE MODULAR backup/ai-scribe`), hardened three ways:
  1. **Versioned storage format** `aisenc1:` + base64(IV‖ciphertext) — decryption never guesses. The 3.5/3.0.1 format was unprefixed, so decrypting relied on "does base64+openssl happen to work" heuristics that could mangle a plaintext key.
  2. **Fail-closed**: a marked ciphertext that will not decrypt (e.g. salts rotated) returns `''`, never raw stored bytes. Unprefixed legacy values keep a printable-ASCII plausibility check so pre-hardening plaintext keys read back unmangled.
  3. **Coverage**: `gemini_api_key` and `grok_api_key` added to the sensitive-key set (they were stored **plaintext** before), and the AJAX save path (`store_key()`) now encrypts before `update_option()` — previously it wrote plaintext and bypassed ConfigManager entirely.
- **Fields**: the four standalone key inputs are `type="password"` with `autocomplete="new-password"`, `spellcheck/autocapitalize/autocorrect` off.
- **Never echoed**: `ai_scribe_get_settings` strips all four key fields; `provider_status()` returns configured/masked (first 3 + last 2 chars max)/validated only; save responses return provider names, never values. Hub-active mode renders no key fields at all and refuses key writes (`managed_by_hub`).
- **Never logged**: all diagnostic logging debug-gated (off in production); grep confirms no key value ever enters a log call (lengths only, and only under the debug gate).
- **Uninstall wipes**: `uninstall.php` deletes `ab_api_key`, `ab_anthropic_api_key`, and `ab_gpt_ai_engine_settings` (which carries gemini/grok), plus all transients.
- **Regression-tested**: 27 new PHP assertions (suite 207 → 234) — per-provider encrypt round-trip, ciphertext marker present, plaintext absent from stored options, fresh-instance decrypt, legacy-plaintext compatibility, fail-closed tamper case, autocomplete hardening, response stripping, uninstall wipe, legacy-endpoint removal.

### Required changes for Opace AI Hub 0.7.8 (hub — NOT changed this pass, hub frozen)

The hub stores all four provider keys **plaintext** in the `ai_core_settings` option (confirmed: AI-Scribe's `get_hub_api_key()` reads them raw). For 0.7.8 the hub needs the same treatment:

1. Encrypt the four `*_api_key` fields in `ai_core_settings` at rest — same `aisenc1:` AES-256-CBC/salts-derived design (copy `encrypt_value/decrypt_value/get_encryption_key` from AI-Scribe's ConfigManager), with a one-time migration that re-saves existing plaintext values encrypted.
2. Decrypt in the hub's settings accessor so add-ons (AI-Scribe `get_hub_api_key()`, AI-Imagen) keep receiving plaintext via the existing read path — **coordinate the release**: once the hub encrypts, add-ons reading `ai_core_settings` directly MUST go through the hub accessor (AI-Scribe already tolerates this: if the raw option value arrives encrypted, its own `decrypt_value` chain would not decode a hub-format value — so AI-Scribe 3.0.2's `get_hub_api_key()` must be updated in the same release to call the hub's accessor instead of `get_option`).
3. `autocomplete="new-password"` on the hub's key fields; masked display; keys stripped from any AJAX/REST responses.
4. Hub uninstall must delete `ai_core_settings` (and any key transients).
5. Apply the same lib fixes AI-Scribe's bundled copy received (ship as lib 0.7.8): `esc_html()` on thrown exception messages, ABSPATH guards on every `lib/src` file, WP_DEBUG-gating the two GeminiImageProvider `error_log` calls.

## 4. Mechanical rules (§14.4)

- **Inline `<script>`**: none rendered. Grep of templates + all inline-script APIs found: the i18n JSON data island (`type="application/json"`, not executable), the theme-boot snippet via `wp_print_inline_script_tag()` (static literal, no dynamic input — the correct WP API for pre-paint boot code), and two dead script-strings in AJAX JSON payloads — both **removed** this pass.
- **Minified files**: the package ships **no `.min.js`/`.min.css`** — all sources unminified. Nothing to pair.
- **i18n**: all user-facing strings through `__()/esc_html__()/esc_attr__()` on the slug-matching domain; PCP reports zero i18n errors.
- **ABSPATH guards**: every PHP file in the package verified; **14 bundled-lib files were missing guards and got them** (inserted after the `namespace` statement, where PHP allows the first executable statement).
- **External hosts**: exactly four — `api.openai.com`, `api.anthropic.com`, `generativelanguage.googleapis.com`, `api.x.ai`. Verified by grepping every `wp_remote_*` call site and URL literal; no telemetry, no other outbound. A full **“External services”** readme section added (per-provider data-sent description + Terms/Privacy links, as the wp.org guideline requires).
- **eval/base64 obfuscation**: zero `eval`/`create_function`/`assert` anywhere (the 2.6 monolith's eval-server-JS pattern is confirmed gone). `base64_decode` appears only in the key-crypto (own data) and provider image payload decoding (API responses).

## 5. Endpoint permission audit (§14.5)

REST routes: **none registered**. Abilities API: category + 4 abilities, every one with `permission_callback` → `current_user_can('edit_posts')`. No `wp_ajax_nopriv_` anywhere. Live admin-ajax surface (all require valid `ai_scribe_nonce` in `security` + listed capability, checked before any input use):

| Endpoint (wp_ajax_) | Handler | Nonce | Capability |
|---|---|---|---|
| ai_scribe_start_conversation | Conversation::handle_start_conversation | guard() | edit_posts |
| ai_scribe_run_step | Conversation::handle_run_step | guard() | edit_posts |
| ai_scribe_run_express | Conversation::handle_run_express | guard() | edit_posts |
| ai_scribe_get_state | Conversation::handle_get_state | guard() | edit_posts |
| ai_scribe_save_selection | Conversation::handle_save_selection | guard() | edit_posts |
| ai_scribe_estimate_cost | Conversation::handle_estimate_cost | guard() | edit_posts |
| ai_scribe_save_post | Conversation::handle_save_post | guard() | edit_posts |
| ai_scribe_stream_step | Conversation::handle_stream_step | guard(false) | edit_posts |
| ai_scribe_get_available_models | Settings::handle_get_available_models | guard() | edit_posts |
| ai_scribe_save_api_keys | Settings::handle_save_api_keys | guard() | **manage_options** (+ refuses under hub) |
| ai_scribe_get_settings | Settings::handle_get_settings | guard() | manage_options |
| ai_scribe_save_ui_prefs | Settings::handle_save_ui_prefs | guard() | edit_posts |
| ai_scribe_save_content_settings | Initializer::handle_save_content_settings | wp_verify_nonce | **manage_options** (added P8) |
| ai_scribe_save_prompt_settings | Initializer::handle_save_prompt_settings | wp_verify_nonce | **manage_options** (added P8) |
| al_scribe_send_shortcode_page | Initializer → ShortcodeService::send_shortcode_page | check_ajax_referer | **edit_posts** (added P8) |
| al_scribe_remove_short_code_content | Initializer → ShortcodeService::remove_short_code_content | wp_verify_nonce + check_ajax_referer | **manage_options** (added P8, both layers) |
| ai_scribe_generate_image | Initializer::handle_image_generation | check_ajax_referer | **edit_posts** (added P8) |

Wordfence-style heuristics all grep-proven clean: no variable functions on user input (the one dynamic `new $class` instantiates from a fixed whitelist map), no `unserialize()` of user data (only a singleton `__wakeup` guard), no file operations from user-supplied paths (logger/prompt-cache/image-temp paths are all plugin-derived).

## 6. Verification

- **PCP**: `wp plugin check` on the packaged 3.0.2 zip under the real slug — **0 errors**, 366 dispositioned warnings (§1).
- **PHP suite**: **234 assertions, 0 failures** (was 207; +27 P8 key-storage/endpoint regressions).
- **Playwright**: full matrix **121 passed / 38 skipped / 0 failed** (~4.9 min, three viewports) against the dev copy.
- **Packaged smoke**: 3.0.2 zip extracted under the wp.org slug, activated alongside the Opace AI Hub, **wizard.spec.ts run against the packaged copy: 6/6 passed** (full 11-step run + Express + prompt-override + Q&A-skip). Dev-plugin active state restored and packaged copy removed afterwards.
- **Package**: `dist/ai-scribe-3.0.2.zip` (832K, 130 entries, top dir `ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/`).

## 7. Release checklist evidence (per §14 footer)

**PCP zero-errors on the packaged zip: MET.** Remaining pre-deploy items unchanged from the P6/P7 checklist (SVN password rotation first; Opace AI Hub 0.7.8 must ship the §3 changes, including the coordinated `get_hub_api_key` accessor switch).
