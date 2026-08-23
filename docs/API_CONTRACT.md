# AI-Scribe v3 — AJAX API Contract (v1.6)

**Status:** BINDING — the UI layer builds against these shapes; the PHP layer implements them exactly.
**v1.1 (P5):** merged the UI's requested extensions as §9–§12 (model list, write-only API keys,
masked settings snapshot, UI prefs). The former `docs/API_CONTRACT_UI_PROPOSAL.md` is deleted.
**v1.2 (P5.6):** `tagline_position` accepted by `ai_scribe_save_selection`.
**v1.3 (this revision, checked against the registered surface):** §9 documents the fields the model
endpoint actually returns (`sources`, `providers`, per-model `category` and `parameters`) and its
`refresh` input; §10 documents the hub refusal; §13 is a new inventory of the endpoints registered
outside the two v3 controllers, which the contract previously did not mention at all.
**v1.4 (2026-08-13):** step 11 requires `content_html`, the exact final Review editor HTML.
The server sanitises and stores it as `selections.final_article`, derives objective structure facts,
and returns those facts beside the grounded editorial checks.
**v1.5 (2026-08-13):** adds article planning fields and quality results, the optional metadata
optimiser, and the featured-image/save-output contract. It also records that every conversation
endpoint is scoped to its creating WordPress user before provider, cost or post work begins.
**v1.6 (2026-08-14):** defines the canonical rendered-visible word count and the bounded,
user-triggered article length improvement endpoint.

**Transport:** WordPress `admin-ajax.php` POST unless stated. Every endpoint requires:

- `action` — the endpoint name below.
- `security` — nonce for action `ai_scribe_nonce` (localised as `ai_scribe.nonce`), except
  `ai_scribe_dismiss_notice`, which carries its own `ai_scribe_dismiss_notice` nonce.
- A logged-in user with `edit_posts` (generation and read paths) or `manage_options`
  (configuration and shortcode management), per endpoint. Failures return HTTP 200 with
  `success: false`.
- Every endpoint that accepts `conversation_id` is scoped to the user who created that
  conversation. Another logged-in author receives `conversation_not_found`, with no state,
  provider call, cost estimate or post mutation. Administrators do not bypass ownership by
  guessing an id; work is shared only through normal WordPress post permissions after save.

**Not in this contract:** the twenty legacy v2.6/v4 endpoints unregistered in P8. Their handler
methods still exist in `class-plugin-initializer.php` for unit coverage, but nothing registers
them, so they are unreachable over `admin-ajax.php`. Treat any of them found in old JS as dead:
`al_scribe_content_data`, `al_scribe_suggest_content`, `ai_scribe_generate_content`,
`al_scribe_generate_4o_image`, `al_scribe_send_post_page`, `ai_scribe_check_recent_images`,
`ai_scribe_update_image_metadata`, `al_scribe_evaluate_content`, `update_style_tone`,
`refresh_nonce`, `save_ai_scribe_settings`, `save_engine_settings`,
`al_scribe_engine_request_data`, `ai_scribe_save_prompt_template`,
`ai_scribe_get_prompt_template`, `ai_scribe_get_template_data`, `get_article`,
`ai_scribe_validate_api_key`, `ai_scribe_cleanup_phantom_data`, `ai_scribe_get_prompts`, plus
`ai_scribe_get_article` and `ai_scribe_get_qna_setting`.

There are no REST routes and no `wp_ajax_nopriv_` registrations.

All responses use `wp_send_json_success` / `wp_send_json_error`:

```json
{ "success": true,  "data": { ... } }
{ "success": false, "data": { "code": "...", "message": "...", "retryable": true|false } }
```

Error `code` values: `invalid_nonce`, `insufficient_permissions`, `forbidden`, `invalid_params`,
`invalid_step`, `conversation_not_found`, `provider_error` (retryable), `rate_limited` (retryable),
`schema_validation_failed` (retryable), `empty_response` (retryable), `not_configured`,
`save_failed`, `managed_by_hub`, `hub_inactive`, `prompt_not_found`.

## Step numbering (canonical)

| Step | Key | Kind | Output |
|---|---|---|---|
| 1 | `title` | choice (structured) | `titles[]` |
| 2 | `keywords` | choice (structured) | `keywords[]` of `{keyword, role, demand_band, estimate_basis}` |
| 3 | `outline` | choice (structured) | `outline[]` (headings) |
| 4 | `introduction` | long-form | HTML string |
| 5 | `tagline` | choice (structured) | `taglines[]` |
| 6 | `article` | long-form | HTML string (body) |
| 7 | `conclusion` | long-form | HTML string |
| 8 | `qna` | choice (structured) | `qna[]` of `{question, answer}` |
| 9 | `meta` | choice (structured) | `meta {title, description}` |
| 10 | `review` | client-side assembly + optional revision call | HTML string |
| 11 | `evaluation` | long-form | HTML string (evaluation table) |

---

## 1. `ai_scribe_start_conversation`

Creates the server-side conversation (one per article).

Request fields (all optional overrides of saved settings):

| Field | Type | Notes |
|---|---|---|
| `idea` | string | The user's topic/idea (step 1 `[Idea]`) |
| `language`, `writing_style`, `writing_tone` | string | Default from `ab_gpt_content_settings` |
| `heading_tag` | string | `H2`..`H6`, default `H2` |
| `number_of_headings` | int | default 5 |
| `article_length_mode` | string | `auto` (default) \| `concise` \| `standard` \| `in_depth` \| `custom`; omitted per-article values inherit the global preference |
| `article_word_count` | int | 400–8000; used when `article_length_mode=custom`, default 1800 |
| `tagline_position` | string | `above` \| `below`, default `below` |
| `avoid_keywords` | string | comma-separated exclude list |
| `model` | string | default from `ab_gpt_ai_engine_settings.model` |
| `options` | JSON object | check_Arr style toggles (`addQNA`, `addinsertToc`, …) |

Response `data`:

```json
{
  "conversation_id": 123,
  "state": { <state object — see get_state> }
}
```

## 2. `ai_scribe_run_step`

Runs one wizard step inside the conversation thread. The server assembles the prompt
(all placeholder resolution is server-side), appends it to the running message history,
calls the provider, validates the response, **persists it before responding**, then returns it.

| Field | Type | Notes |
|---|---|---|
| `conversation_id` | int | required |
| `step` | int 1–11 | required |
| `prompt_override` | string | optional user-edited prompt for this run (sanitised server-side; placeholders still resolved) |
| `regenerate` | `"1"` | optional — request more options for a choice step (previous results kept) |
| `model` | string | optional per-run model override |
| `content_html` | string | required for step 11; exact current Review editor HTML, including user edits and inserted images |

Response `data` (choice steps 1,2,3,5,8,9):

```json
{
  "conversation_id": 123,
  "step": 1,
  "kind": "choice",
  "parsed": { "titles": ["...", "..."] },
  "raw": "<verbatim model output>",
  "prompt_used": "<final assembled prompt>",
  "usage": { "prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0 },
  "cost": { "estimated_usd": 0.0031, "actual_usd": 0.0028, "running_total_usd": 0.0113 },
  "state": { <state object> }
}
```

`parsed` payload per step: 1 `{titles: string[]}` · 2 `{keywords: [{keyword: string, role: "primary"|"supporting"|"long-tail", demand_band: "low"|"medium"|"high"|"unknown", estimate_basis: "ai_unverified"}]}` · 3 `{outline: string[]}`
· 5 `{taglines: string[]}` · 8 `{qna: [{question, answer}]}` · 9 `{meta: {title, description}}`
· 11 `{checks: [{label,status,detail,suggestion}], facts: {word_count,image_count,images_missing_alt_count,link_count,heading_count,bold_count}}`.

Step 2 demand bands are qualitative, unverified model estimates, not measured search volume. They never
contain a number; invalid or legacy metric-bearing output normalises to `unknown`. Existing string-only
keyword arrays remain readable and downstream `selections.keywords` remains an array of plain phrases.

Step 11 status is `pass | warn | fail | unknown`. Word/image/link/heading/bold checks are generated
deterministically from `content_html`, not by the provider. Provider-authored structural duplicates
are discarded. Subjective checks must use only evidence found in the supplied article; unverifiable
claims use `unknown` and must not invent experts, links, domains, quotations or examples.

Long-form steps (4,6,7,11) return `"kind": "longform"` and `"parsed": { "html": "<p>…</p>" }`.

Article responses also return `quality_plan`. For the body and Express article this contains the
observed word and heading counts, thin-section reasons and pass state measured against the server's
deterministic target and range. Complete-article `word_count` includes the visible title and tagline
as well as introduction, body, conclusion and Q&A. Its canonical counter follows rendered whitespace
token boundaries, so slash-joined and hyphenated terms are not split by a second server-only rule.
The service may make one corrective expansion call when the body or whole article is short, thin or
does not match its selected outline exactly. A second failure returns `article_too_shallow`; Review
save separately returns `article_quality_incomplete` when final user edits fall below the same plan.
Concise and custom-short choices lower the target deliberately but do not disable proportional depth
or outline checks.

On failure NOTHING advances: the step is not marked complete, the error object carries
`retryable`, and (if the provider returned anything) `raw` is still persisted server-side
so a retry can re-render without a new billed call (`ai_scribe_get_state` returns it).

## 3. `ai_scribe_save_selection`

Stores the user's choice(s) for a completed choice step.

| Field | Type | Notes |
|---|---|---|
| `conversation_id` | int | required |
| `key` | string | `title` \| `keywords` \| `outline` \| `tagline` \| `introduction` \| `body` \| `conclusion` \| `qna` \| `meta` \| `tagline_position` |
| `value` | string or JSON | `keywords`/`outline`/`qna` accept JSON arrays; `meta` accepts JSON object; `tagline_position` accepts `above`/`below` and updates the conversation **setting** (not a selection) — v1.2, P5.6 |

Response `data`: `{ "conversation_id": 123, "state": { … } }`

### 3.1 `ai_scribe_optimise_meta` (optional)

This endpoint is exposed only as a user-triggered action when the current title exceeds 60 characters
or the description exceeds 160. It never overwrites `selections.meta`; the UI first displays the
suggestion beside the original and only an explicit Apply action saves it through §3.

| Field | Type | Notes |
|---|---|---|
| `conversation_id` | int | required; creator-owned |
| `title` | string | current edited meta title |
| `description` | string | current edited meta description |

Response `data` contains `meta {title, description}`, `secondary_coverage[]`, `usage` and `cost`.
Server validation requires a 50–60 character title, a 120–160 character description, the exact
primary keyword in both, and exactly one ` | ` as the sole title-component separator. Secondary
coverage reports `exact`, `combined`, `partial` or `absent` per field. Errors include
`primary_keyword_too_long`, `schema_validation_failed`, `optimisation_failed` and `provider_error`.

## 4. `ai_scribe_get_state`

| Field | Type |
|---|---|
| `conversation_id` | int |

Response `data` is the **state object** used everywhere:

```json
{
  "conversation_id": 123,
  "status": "active",            // active | complete | abandoned
  "mode": "wizard",              // wizard | express
  "settings": { "idea": "...", "language": "English", "writing_style": "Business",
                "writing_tone": "Professional", "heading_tag": "H2",
                "number_of_headings": 5, "tagline_position": "below",
                "article_length_mode": "auto", "article_word_count": 1800,
                "avoid_keywords": "", "model": "provider-model-id", "options": {} },
  "selections": { "title": "...", "keywords": ["..."], "outline": ["..."],
                  "tagline": "...", "introduction": "<p>…</p>", "body": "<h1>…",
                  "conclusion": "...", "qna": [], "meta": {"title": "", "description": ""},
                  "final_article": "<h1>Latest reviewed article…</h1>" },
  "steps": {
    "1": { "status": "complete", "kind": "choice", "parsed": {"titles": ["..."]},
            "raw": "...", "usage": {...}, "completed_at": "2026-08-10 12:00:00" },
    "4": { "status": "failed", "error": {"code": "rate_limited", "message": "...", "retryable": true} }
  },
  "cost": { "running_total_usd": 0.0113, "by_step": { "1": 0.0028 } }
}
```

A UI crash mid-run loses nothing: re-fetch state and re-render `steps[n].parsed`.

## 5. `ai_scribe_run_express`

One-shot whole-article generation, persisted into the same conversation model
(so the wizard can refine afterwards).

Request: same fields as `ai_scribe_start_conversation` (creates a conversation with
`mode: "express"`), or pass an existing `conversation_id` to run express inside it.
`idea` is required.

Response `data`:

```json
{
  "conversation_id": 124,
  "article": {
    "title": "...",
    "meta": { "title": "...", "description": "..." },
    "tagline": "...",
    "outline": ["..."],
    "intro": "<p>…</p>",
    "body_html": "<h1>…</h1>…",
    "conclusion": "<p>…</p>",
    "qna": [ { "question": "...", "answer": "..." } ]
  },
  "quality_plan": { "pass": true, "word_count": 1825, "heading_count": 5, "reasons": [] },
  "usage": {...},
  "cost": { "actual_usd": 0.021, "running_total_usd": 0.021 },
  "state": { … }
}
```

All article parts are also written into `selections` and `steps` so the wizard renders them.

### 5.1 `ai_scribe_improve_article_length`

User-triggered, one-call improvement for a complete persisted Express or Wizard article. It measures
the current canonical visible count and asks the provider only for useful additions keyed to existing
body sections. Existing title, tagline, metadata, outline, introduction, body sentences, conclusion
and Q&A are read-only. The server inserts accepted additions and rejects empty, shrinking,
outline-changing or excessive output. Accepted output contains only semantic paragraph/list
blocks, each 45–120 words, with no more than two blocks or 220 words per section. Deficits of
140+ words must be spread over at least two available sections; deficits of 440+ words over at
least three. A provider or validation failure leaves the stored draft
unchanged. The user may retry manually; the endpoint never loops on its own.

Request:

| Field | Type | Notes |
|---|---|---|
| `conversation_id` | int | required; creator-owned |
| `current_html` | string | optional exact Wizard Body or Review editor snapshot; sanitised server-side and preserved byte-for-byte apart from accepted insertions |
| `body_only` | bool | `true` for Wizard Body planning; otherwise the complete reviewed-article plan is used |

Response `data`:

```json
{
  "conversation_id": 124,
  "article": { "title": "...", "body_html": "<h2>…</h2><p>…</p>" },
  "improved_html": "<h1>…</h1><h2>…</h2><p>…</p>",
  "quality_plan": { "word_count": 1940, "target_words": 2200, "advisory": true },
  "improvement": {
    "requested_words": 484,
    "added_words": 224,
    "remaining_words": 260,
    "message": "224 useful words were added. The complete draft was kept and remains 260 words below the selected target."
  },
  "usage": { "prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0 },
  "cost": { "actual_usd": 0.004, "running_total_usd": 0.025 },
  "state": { … }
}
```

Errors include `article_unavailable`, `provider_error` and retryable `improvement_invalid`.

## 6. `ai_scribe_estimate_cost`

| Field | Type | Notes |
|---|---|---|
| `conversation_id` | int | optional (uses stored settings/history sizes when given) |
| `step` | int | optional — estimate one step; omit for whole-article estimate |
| `model` | string | optional override |
| `mode` | string | `wizard` (default) \| `express` |

Response `data`:

```json
{
  "model": "provider-model-id",
  "pricing": { "input_per_mtok_usd": 2.5, "output_per_mtok_usd": 15, "cached_input_per_mtok_usd": 0.25 },
  "steps": { "1": { "input_tokens": 900, "output_tokens": 250, "usd": 0.006 }, "...": {} },
  "total": { "input_tokens": 14200, "output_tokens": 6800, "usd": 0.14,
             "usd_without_caching": 0.31, "cache_savings_usd": 0.17 }
}
```

When `step` is provided, `steps` contains only that step. Estimates only; actuals come back on `run_step`.

## 7. `ai_scribe_save_post` (extends existing PostService flow)

| Field | Type | Notes |
|---|---|---|
| `conversation_id` | int | required |
| `post_status` | string | `draft` (default) \| `publish` \| `pending` |
| `post_type` | string | `post` (default) \| `page` |
| `content_html` | string | optional final edited HTML (defaults to assembled selections) |
| `featured_attachment_id` | int | optional featured attachment; removed from article HTML before save and assigned as the WordPress thumbnail |

Writes SEO meta for the first active Yoast / AIOSEO / RankMath / SEOPress integration from
`selections.meta`; AI-Scribe does not duplicate social, author or structured-data output owned by
that plugin. PostService strips duplicate H1s, empty spacer blocks and artificial height styles,
adds intrinsic dimensions and lazy/async attributes to content images when known, creates a clean
excerpt and a concise slug for a new post, and preserves an existing post slug on update. A featured
attachment is shown separately in Review and deliberately excluded from saved article HTML.

Response `data`: `{ "post_id": 456, "updated": false, "edit_link": "...", "permalink": "...", "seo": {…}, "featured_image": {"set": true, "attachment_id": 789} }`

## 8. `ai_scribe_stream_step` (SSE)

GET or POST to `admin-ajax.php?action=ai_scribe_stream_step&conversation_id=…&step=…&security=…`
(+ optional `prompt_override`, `model`). Response is `Content-Type: text/event-stream`
(this endpoint is the one exception to the wp_send_json rule). Events:

```
event: delta      data: {"text": "chunk of html"}
event: done       data: {<same data object as ai_scribe_run_step's success payload>}
event: error      data: {"code": "provider_error", "message": "...", "retryable": true}
```

Only long-form steps (4, 6, 7, 11) may stream; choice steps reject with `invalid_params`.
The `done` payload is persisted server-side before the event is emitted.

**Current implementation:** `GenerationService::stream_step()` always takes the fallback path.
It runs the step non-streaming and emits the whole output as one `delta` followed by `done`.
Per-token passthrough needs a curl transport the WP HTTP API does not give us, and has not been
built for any provider. The event shape above is what the client codes against, so true streaming
can be dropped in later without a client change — but nothing streams today, and no user-facing
copy should say it does.

## 9. `ai_scribe_get_available_models` (v1.1)

Live model list — the UI never hardcodes model ids.

| Field | Type | Notes |
|---|---|---|
| `provider` | string | optional `openai\|anthropic\|gemini\|WordPress`; omit for all |
| `refresh` | truthy | optional — bypass the one-hour per-provider transient and re-fetch live |

Response `data`:

```json
{
  "models": [ {
    "id": "provider-model-id", "provider": "openai", "label": "Provider model label",
    "category": "text",
    "context_window": 1050000, "max_output_tokens": 65536,
    "pricing": { "input_per_1m": 2.5, "output_per_1m": 15 },
    "capabilities": ["text", "streaming", "tooluse"],
    "parameters": { "reasoning_effort": { "...": "..." } }
  } ],
  "sources":   { "openai": "live", "anthropic": "live-cached", "gemini": "registry" },
  "providers": { "openai": { "configured": true, "masked": "sk-••••••3a", "validated": true } }
}
```

Where the list comes from, per provider: a key present means a live call to that provider's
model-listing endpoint, cached in a transient for one hour (`sources` reports `live` or
`live-cached`); no key means the bundled `ModelRegistry` seed (`registry`), so a fresh install
still has a picker. Ids returned live that the registry has never seen are registered on the fly.

`category` distinguishes text from image models — the text picker must exclude `image`, and ids
matching the image families are classified by id when the registry has no metadata for them.
`parameters` is that model's parameter schema (an empty object when unknown), and is what the
per-model settings panel is built from. `context_window` / `max_output_tokens` / `pricing` are
`null` when the registry has no metadata.

`providers` is the same shape as §11, and the picker gates selectability on it: a provider whose
`validated` is not `true` is shown greyed out.

On WP 7.0+ with the core AI Client present, the list also contains the **"WordPress AI (core)"**
choice from `WpAiClientAdapter::provider_choice()` (`provider: "WordPress"`, plus a `configured`
boolean); it never appears on WP < 7.0. Requires `edit_posts`.

## 10. `ai_scribe_save_api_keys` (v1.1)

Write-only key storage for installs where AI-Scribe still owns keys. Requires `manage_options`.

**With Opace AI Hub active this endpoint refuses every write** and returns
`{ code: "managed_by_hub", retryable: false }` — the hub owns provider configuration, and the
settings screen renders no key fields at all. Since Opace AI Hub is a required plugin, that is the
normal case; the branch below only runs where the hub is missing or deactivated.

| Field | Type | Notes |
|---|---|---|
| `keys` | JSON | `{openai, anthropic, gemini}` — empty string/absent = leave unchanged; `"-"` = clear |

Response `data`: `{ "updated": ["openai"], "cleared": [], "providers": { <see §11> } }`.
Values are encrypted before storage (`aisenc1:` AES-256-CBC, key derived from the site salts) and
are NEVER returned; the page never receives key material.

## 11. `ai_scribe_get_settings` (v1.1)

Full settings snapshot for the settings screen. Requires `manage_options`.

Response `data`:

```json
{
  "providers": { "openai": { "configured": true, "masked": "sk-••••••3a", "validated": true }, "...": {} },
  "engine":  { "model": "provider-model-id", "temp": 0.5, "top_p": 0.5 },
  "content": { "language": "English", "article_length_mode": "auto", "article_word_count": 1800, "...": "..." },
  "images":  { "enabled": false, "model": "", "size": "1024x1024" },
  "prompts": { "title_prompts": "...", "Keywords_prompts": "..." },
  "ui_prefs": { "theme": "dark" }
}
```

`engine` is `ab_gpt_ai_engine_settings` with all four `*_api_key` fields removed, so it carries the
2.6.2 key names (`model`, `temp`, `top_p`) verbatim. It also still carries `freq_pent`,
`Presence_penalty` and `n` — stored for a downgrade, exposed by no UI, and never sent to a
provider. `content` is `ab_gpt_content_settings`, `images` is `ab_gpt_image_settings`, `prompts` is
`ab_prompts_content`, `ui_prefs` is the caller's own user meta.

`providers.*.masked` is at most first-3 + last-2 characters. `providers.*.validated` is the result
of a cached live call to the provider: `true` accepted, `false` rejected, `null` not configured or
not checkable — key presence alone never reads as working.

Related: `ai_scribe_save_content_settings` accepts a JSON `settings` object and
`ai_scribe_save_prompt_settings` accepts a JSON `prompts` object keyed exactly as
`ab_prompts_content` (capital-K `Keywords_prompts` preserved). Both require `manage_options` —
see §13.

## 12. `ai_scribe_save_ui_prefs` (v1.1)

| Field | Type | Notes |
|---|---|---|
| `prefs` | JSON | `{theme: "light"\|"dark"\|"auto"}` persisted in user meta |

Response `data`: `{ "prefs": { "theme": "dark" } }`. Requires `edit_posts`. The UI also
keeps a localStorage copy so a failed call degrades gracefully.

## 13. Endpoints outside the two v3 controllers (v1.3)

These are registered elsewhere and were missing from earlier revisions of this contract. They are
part of the live surface and a UI change must account for them.

| Action | Registered in | Capability | Purpose |
|---|---|---|---|
| `ai_scribe_save_content_settings` | `Plugin_Initializer::register_plugin_hooks` | `manage_options` | Settings save: content settings, engine model + sampling + `model_params`, image options, writing mode, `check_Arr` toggles, custom language, uninstall opt-in |
| `ai_scribe_save_prompt_settings` | `Plugin_Initializer::register_plugin_hooks` | `manage_options` | Writes `ab_prompts_content` (including `user_instructions`) |
| `ai_scribe_generate_image` | `Plugin_Initializer::register_plugin_hooks` | `edit_posts` | Wizard image generation |
| `al_scribe_send_shortcode_page` | `Plugin_Initializer` → `ShortcodeService` | `edit_posts` | Save the finished article as a shortcode row |
| `al_scribe_remove_short_code_content` | `Plugin_Initializer` → `ShortcodeService` | `manage_options` | Delete a shortcode row (checked in both the wrapper and the service) |
| `ai_scribe_apply_hub_prompt` | `Hub_Prompt_Reader::register` | `edit_posts` | §13.1 below |
| `ai_scribe_dismiss_notice` | `Onboarding_Notice::register` | `manage_options` | Dismiss the post-update notice. Uses its own `ai_scribe_dismiss_notice` nonce, not `ai_scribe_nonce` |

### 13.3 Image generation (article-local options)

`ai_scribe_generate_image` accepts `prompt` plus optional `image_options` JSON. The section endpoint
also accepts the exact editable `prompt` for its requested `index`. Supported request-scoped keys
are `model`, `style`, `size`, `quality`, `format` and `background`; invalid values are discarded.
They override the effective request only and never write `ab_gpt_image_settings` or legacy global
image options.

Successful image payloads include `url`, `attachment_id`, `alt_text`, `prompt_used` and
`image_options`. Section payloads also include `section` and `index`. Newly generated attachments
receive descriptive alt text but an empty caption; no `<figcaption>` is generated unless an
explicit caption is supplied by a separate authoring path. Existing attachment captions are not
modified globally.

### 13.1 `ai_scribe_apply_hub_prompt`

Pins an Opace AI Hub prompt to a wizard step, or clears it. Changing what a step sends is an authoring
action, not a site setting, so the bar is `edit_posts` — the same as running the wizard.

| Field | Type | Notes |
|---|---|---|
| `step` | int 1–11 | required; anything else returns `invalid_step` |
| `prompt_id` | int | Opace AI Hub prompt id, or `0` to revert the step to AI-Scribe's own prompt |

Response `data`: `{ "step": 6, "prompt_id": 12, "title": "House body prompt", "message": "..." }`.
Errors: `hub_inactive` when Opace AI Hub is not running, `prompt_not_found` when the id is gone.

The map lives in the `ai_scribe_hub_prompt_map` option. Reads are defensive: a step whose pinned
prompt has been deleted in Opace AI Hub, or whose content is empty, silently falls back to AI-Scribe's
own prompt.

The same map is written once by the upgrade. `Migration_Service::maybe_migrate_prompts_to_hub()`
copies the site's `ab_prompts_content` step prompts into an "AI-Scribe" group in the hub and pins
each step to its copy, guarded by `ai_scribe_hub_prompts_migrated` plus a per-prompt title check so
a half-finished run resumes rather than duplicating. It never writes `ab_prompts_content`, never
overwrites a hub prompt of the same title, and does nothing at all without a writable hub library.
`user_instructions` is excluded: it is Custom Instructions, not a step prompt.

### 13.2 Step-prompt precedence

`PromptManager::resolve_step_prompt()` is the only place this is decided. Highest wins:

1. `prompt_override` sent with this one `run_step` / `stream_step` call. Never persisted.
2. The Opace AI Hub prompt pinned to the step (§13.1).
3. The site's saved `ab_prompts_content` value.
4. The built-in default.

The system message is a separate axis and is not part of that chain. `get_system_prompt()` always
sends the Humanizer / Personality blocks (per writing mode), then `instructions_prompts`, then the
user's `user_instructions` last — so Custom Instructions apply to every run whichever level
supplied the step prompt. In 2.6.2 `user_instructions` was written and never read; v3 sends it.

## Notes for the UI agent

- Cost meter: call `ai_scribe_estimate_cost` on settings/model change; update actuals from
  each `run_step`/`run_express` response `cost.running_total_usd`.
- The editable prompt box: pre-fill from `run_step` response `prompt_used`, or fetch a
  template preview by calling `ai_scribe_run_step` — assembled prompts are also available
  pre-flight via `ai_scribe_estimate_cost` with a `step` (field `prompt_preview`).
- Never advance the wizard on `success: false`. Show the error, offer Retry when `retryable`.
- Q&A skip: simply never run step 8; `save_selection` with `key=qna`, `value=[]` clears it.
