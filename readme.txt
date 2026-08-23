=== Opace AI Scribe: SEO Content Creator & Humanizer for OpenAI, Anthropic & Gemini ===
Contributors: opacewebdesign
Tags: AI Writer, Content Generator, AI, SEO
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: opace-ai-prompt-library-api-hub
Stable tag: 3.2.30
License: GPL-3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
Donate link: https://opace.agency/get-in-touch

AI SEO content writer and humaniser for OpenAI, Anthropic and Gemini. Eleven-step wizard, Express mode, Yoast and Rank Math ready.

== Description ==

**AI Scribe** is an SEO AI writer, content generator and humaniser for OpenAI, Anthropic and Google Gemini, with optional support for the WordPress 7 core AI client.

AI Scribe is independently developed by Opace Digital Agency and is not affiliated with, endorsed by or sponsored by OpenAI, Anthropic or Google.

Version 3 is a ground-up rebuild of the plugin around one idea: the model should always see the whole article. Every step of the 11-step wizard runs inside a single conversation thread, so the conclusion, Q&A and SEO meta are written with the full body in context rather than blind. The result is coherent long-form content without the repetition and drift older step-by-step tools suffer from.

= Modular architecture =

Version 3 splits what used to be one plugin into two. AI Scribe is the writing tool. **Opace AI Hub** is the hub that holds provider credentials, model lists, pricing and the shared prompt library. Add a key once in Opace AI Hub and every plugin built on the hub can use it.

AI Scribe sends writing and image requests through Opace AI Hub's public API. The Hub owns provider keys, live discovery, shared prompts, normalisation, usage and cost records.

What this buys you: one place to manage keys instead of one per plugin; model lists that come from your own provider account rather than a list baked into the plugin; usage and spend recorded across every add-on together; and a prompt library shared between them. It also means a provider fix lands once, in the hub, instead of separately in each plugin.

= Opace AI Hub is required =

AI Scribe is an add-on to the free **Opace AI Hub** hub plugin and does nothing without it. WordPress will not let you activate AI Scribe until Opace AI Hub is active. If Opace AI Hub is deactivated afterwards, AI Scribe shuts itself down cleanly — no menu, no wizard, no endpoints — and shows a notice with a button to switch Opace AI Hub back on.

AI Scribe's own Providers tab therefore has no key fields. It shows a status chip per provider — configured, not configured, or key rejected by the provider — and a link through to Opace AI Hub's settings.

= Providers and models =

Add a key for one provider or all three. The model list is fetched live from your own account and cached for an hour, with a Refresh button that bypasses the cache. AI Scribe filters the returned models by capability and preserves a valid saved choice. Opace AI Hub's maintained registry is used only when live discovery is unavailable, so the plugin does not promise model names which a provider may rename, retire or withhold from a particular account.

**Live catalogue snapshot: 23 August 2026 at 13:03 BST (UTC+1); AI Scribe compatibility checked at 13:33 BST.** That Hub refresh returned 132 OpenAI, 10 Anthropic Claude and 50 Google Gemini identifiers. The list below is every writing or still-image identifier AI Scribe can select from that snapshot. Your Settings screen remains the authority because access varies by account, region and provider rollout.

* **OpenAI** — text and image-capable models exposed by the configured account.
* **Anthropic** — text-capable models exposed by the configured account. Anthropic does not provide an image model.
* **Google Gemini** — text and image-capable models exposed by the configured account.

The complete dated list is in the FAQ below, grouped into multimodal writing, text/reasoning and still-image models. It includes GPT-5.6 Sol, Terra and Luna. Audio, speech, realtime, embeddings, video, code, research, search, moderation, computer-use and other specialist Hub identifiers are not selectable or invoked by AI Scribe.

= Which combination should I use? =

Any of them work. AI Scribe adapts to the keys you have rather than insisting on a particular provider:

* **OpenAI only** — text and images, both from OpenAI.
* **Gemini only** — text and images, both from Gemini. Google grants image models per account, so if yours has none the image controls hide and say so rather than failing.
* **Anthropic only** — text works normally. Claude cannot generate images, so the image controls are hidden rather than shown and then failed, and the wizard tells you which key to add if you want them back. Every other step is unaffected.
* **Anthropic plus OpenAI**, or **Anthropic plus Gemini** — Claude writes, the other provider draws. This is a common pairing and needs no configuration beyond adding both keys.
* **All three** — pick the model per step. Nothing forces one provider across the whole article.

Each model exposes its own parameter panel, built from that model's capability schema — reasoning or extended-thinking controls only where the selected model exposes them, and temperature or top-p only where the provider actually accepts them. Sampling values are passed through untouched: 2.6.2's hidden temperature multiplier and its stop sequences are gone.

Provider status is a live check, not a guess. AI Scribe proves the key against the provider before reporting it as working, so a stale or revoked key reads as rejected rather than "configured".

= Fresh-install defaults =

A new install starts with Auto article length, English, Business style, Professional tone, Humanizer, British spelling, five H2 sections, Q&A, table of contents, hyperlink suggestions and keyword emphasis enabled. Image generation and parallel image processing are enabled with the Photorealistic preset, but the controls remain unavailable until a configured provider exposes a compatible image model.

A valid saved model always wins. When it is missing or retired, Opace AI Hub ranks the configured account's live list inside a provider-specific family: newest Terra with medium reasoning for OpenAI writing; newest Claude Opus with medium effort for Anthropic writing; newest non-Lite Gemini Flash with medium thinking for Gemini writing; newest GPT Image for OpenAI images; and newest Gemini Flash Image / Nano Banana for Gemini images.

The maintained offline fallbacks are currently `gpt-5.6-terra`, `claude-opus-5`, `gemini-3.6-flash`, `gpt-image-2` and `gemini-3.1-flash-image` (Nano Banana 2). They do not promise that every account exposes the same list. Live discovery takes precedence, so a later model in the same preferred family becomes the default without a plugin update. Temperature and top-p begin at 0.5 only where the selected model accepts them; unsupported controls are omitted. Reactivation and retained-data reinstalls fill missing settings only and never replace a valid saved choice.

Three parameters from 2.6.2 — frequency penalty, presence penalty and Best Of — are still held in the options table for a downgrade, but v3 has no field for them and never sends them. Current models either reject them or ignore them.

= The 11-step wizard =

The guided stages cover titles, keywords, outline, introduction, tagline, article body, conclusion, Q&A, SEO meta, Review and Evaluate. Results stay editable and the model receives the accumulated article context.

Choose Auto, a preset or a custom word target for each article. Body and Review show the measured count, target, preferred range and shortfall. A retained under-target draft can be improved without replacing existing wording, and accepted additions must be balanced across existing sections.

Keyword cards show their role and a qualitative, unverified AI demand band. Google Trends links let you check relative interest and seasonality. AI Scribe does not present guessed monthly search volumes as measured data.

Every prompt is visible and editable, per step and globally, using the same placeholders as before: [Language], [Style], [Tone], [Title], [Selected Keywords], [The Tagline] with [above/below], [No. Headings], [Heading Tag] and [Keywords to Avoid]. Placeholders are resolved server-side. Editing a visible prompt does not silently rerun anything: choose **Run amended prompt**, and the current result stays in place if that request fails.

= Fewer tokens, better articles =

Threading the conversation is what makes the difference. Because the model already holds the article, later steps do not have to be re-fed the text that came before them, and they stop repeating what the introduction already said. Structured responses are requested with the provider's own schema mechanism — JSON schema on OpenAI and Gemini, a forced tool call on Claude — so a choice step returns parseable options first time instead of prose that has to be re-requested.

Three further savings: a failed or malformed response never advances the wizard and never bills you twice for the same step; pressing Generate repeatedly during a run no longer fires a request each time; and Express mode starts with one structured whole-article request instead of eleven step requests. Optional length improvements are separate requests.

= Express mode =

One click creates the first whole-article draft. Express mode returns the title, meta, tagline, outline, introduction, body, conclusion and Q&A together in one structured starting request. It shows the exact visible word count and preferred range; an under-target draft can be improved with a separate request without discarding the usable copy. Save the exact visible result as a draft, published post or shortcode, or refine any part in the wizard afterwards.

= Prompts, shared with Opace AI Hub =

The Prompt Library tab shows Opace AI Hub's prompt library grouped as you organised it there. Apply any of those prompts to a wizard step and that step uses it instead of AI Scribe's own. Your AI Scribe prompt stays behind it as the fallback, so deleting a prompt in Opace AI Hub, or deactivating the hub, never leaves a step with nothing to send.

Upgrading from 2.6.x copies your own edited prompts into Opace AI Hub, filed under a group called "AI-Scribe" and named per step, and points each step at its copy. Nothing is moved: the originals stay in the database as a backup, and the copy is skipped for any prompt you have already created in Opace AI Hub under the same name.

Precedence for a step, highest first: the prompt you typed into the wizard for this one run, then the Opace AI Hub prompt pinned to that step, then your saved AI Scribe prompt, then the built-in default.

= Custom Instructions =

Settings → Prompts has a Custom Instructions box for your brand voice, spelling rules and banned phrases. It is appended after the global instructions in the system message for every step, so it has the last word. In 2.6.2 this box saved your text and then never used it; that is fixed.

= Humanizer modes =

The Humanizer and Humanizer with Personality writing modes from 2.6 are back as a first-class setting. Humanizer rewrites the system instructions so output reads like natural human writing, with varied sentence lengths, direct address and natural imperfections. The Personality variant adds a witty, opinionated edge. Choose Standard, Humanizer or Humanizer with Personality under Settings → Generation.

= Cost transparency =

A cost meter shows the estimated spend before a step runs, the actual spend after it, and the running total for the article. Pricing comes from Opace AI Hub's live/cached pricing data and maintained fallback catalogue. When trustworthy pricing is unavailable, the interface says **Cost unavailable** rather than recording a misleading zero.

= Images =

Generate article images with any configured provider that exposes image generation. The Image Studio creates the featured image visibly at the Body step when images are enabled, and can generate or retry a section set. Inspect and amend each prompt, edit or remove automatic captions, use article-only style overrides, and place, replace or remove images without silently appending an unmatched image at the end. Size (including Auto), quality, output format, background and twenty style presets are configurable.

Review and Evaluate show whether the current article has been saved as a draft, published post or shortcode, and whether later edits have made the saved copy stale. Evaluate measures structural facts from the final Review HTML, reports table-of-contents, internal contextual and external links separately, and labels editorial judgements for human confirmation rather than presenting them as measured facts.

If none of your configured providers can generate images — an Anthropic-only site, for example — the image controls are hidden and the panel explains which key to add. A failed image never blocks or discards the article you have written.

= SEO integrations =

Meta title, description and focus keywords are written to whichever of Yoast SEO, All in One SEO, Rank Math or SEOPress is active — the first one detected, not all four — and are always stored with the post as well, so nothing is lost if you install or switch SEO plugin later.

= Shortcodes =

Save any finished article as a shortcode and embed it anywhere — pages, posts, Divi, Elementor or ACF fields — with [article_builder_generate_data template_id="N"]. Manage saved shortcodes from their own admin screen.

= Who can do what =

Running the wizard, Express mode and the Help screen need `edit_posts`, so authors and above can write. Settings and Saved Shortcodes need `manage_options`. Every generation endpoint enforces `edit_posts` and a nonce; there are no logged-out endpoints.

**Please note:** this plugin sends your prompts and article content to the external AI provider you configure (OpenAI, Anthropic or Google Gemini). Review each provider's terms and privacy policy. API usage is billed by your provider account.

== External services ==

This plugin connects only to the AI provider APIs you configure with your own API keys in Opace AI Hub. No other outbound requests are made, and nothing is sent to Opace's servers.

* **OpenAI API** ([api.openai.com](https://api.openai.com/)) — prompts, selected article content and generation settings are sent to generate text and images when an OpenAI model is selected, plus a model-list request used to populate the model picker and check that your key works. [Terms](https://openai.com/policies/terms-of-use) · [Privacy](https://openai.com/policies/privacy-policy)
* **Anthropic API** ([api.anthropic.com](https://api.anthropic.com/)) — the same data is sent when a Claude model is selected. [Terms](https://www.anthropic.com/legal/consumer-terms) · [Privacy](https://www.anthropic.com/legal/privacy)
* **Google Gemini API** ([generativelanguage.googleapis.com](https://generativelanguage.googleapis.com/)) — the same data is sent when a Gemini model is selected. [Terms](https://ai.google.dev/gemini-api/terms) · [Privacy](https://policies.google.com/privacy)

Requests only happen when you run the wizard, Express mode, image generation, or open a screen that lists models or checks key status; no data is transmitted in the background.

== Installation ==

1. Install and activate [Opace AI Hub from WordPress.org](https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/). AI Scribe declares the approved dependency slug, so WordPress can install or activate the Hub from the missing-dependency notice and refuses to activate AI Scribe until it is present.
2. In Opace AI Hub → Settings, add an API key for at least one provider: OpenAI, Anthropic or Google Gemini.
3. Upload AI Scribe to `/wp-content/plugins/` or install it from the Plugins screen, then activate it.
4. Open AI Scribe → Settings → Providers & Model. Check that your provider shows as configured, pick a model (Refresh models re-fetches the live list), then open Generate Article and start writing.

Upgrading from 2.6.x is a normal one-click update, but Opace AI Hub must be installed and active or the updated plugin will not run. WordPress does not deactivate a plugin whose dependency is missing, so AI Scribe checks for itself: without Opace AI Hub it hides its screens and endpoints, leaves your data untouched, and shows a notice to install or activate the hub. Nothing is lost — it starts working again the moment Opace AI Hub is active. Saved prompts, content settings, custom languages, saved shortcodes and API keys are migrated the first time an admin page loads, keys carried over from 2.6.x are encrypted at rest during that migration, and your edited prompts are copied into Opace AI Hub's library.

== Frequently Asked Questions ==

= Do I really need Opace AI Hub? =

Yes. Opace AI Hub stores the API keys, supplies the model lists and pricing, and holds the shared prompt library. Without it AI Scribe has nowhere to configure a model, so it will not activate, and it shuts down if Opace AI Hub is deactivated later.

= Can I use Claude for writing and something else for images? =

Yes, and it is a sensible pairing. Anthropic does not offer image generation at all, so add an OpenAI or Google Gemini key alongside your Anthropic one and images come from whichever of those you have. No further configuration is needed.

= What happens if I only have an Anthropic key? =

Everything except images works normally. The image controls are hidden rather than offered and then failed, and the panel tells you which key to add to turn them on.

= Which models are supported? =

Whichever compatible writing and still-image models your configured OpenAI, Anthropic or Google Gemini account grants you. The dated snapshot above names every currently selectable identifier, including GPT-5.6 Sol, Terra and Luna. The list is still fetched live and filtered by capability, so Settings is authoritative if a provider changes access after that snapshot.

= Which models were in the dated live snapshot? =

**Multimodal writing models**

* **OpenAI (45):** `gpt-5.6-sol`, `gpt-5.6-terra`, `gpt-5.6-luna`, `gpt-5.5-pro`, `gpt-5.5-pro-2026-04-23`, `gpt-5.5`, `gpt-5.5-2026-04-23`, `gpt-5.4-pro`, `gpt-5.4-pro-2026-03-05`, `gpt-5.4`, `gpt-5.4-2026-03-05`, `gpt-5.4-mini`, `gpt-5.4-mini-2026-03-17`, `gpt-5.4-nano`, `gpt-5.4-nano-2026-03-17`, `gpt-5.2-pro`, `gpt-5.2-pro-2025-12-11`, `gpt-5.2`, `gpt-5.2-2025-12-11`, `gpt-5.1`, `gpt-5.1-2025-11-13`, `gpt-5-pro`, `gpt-5-pro-2025-10-06`, `gpt-5`, `gpt-5-2025-08-07`, `gpt-5-mini-2025-08-07`, `gpt-5-mini`, `gpt-5-nano-2025-08-07`, `gpt-4.1-2025-04-14`, `gpt-4.1`, `gpt-4.1-mini-2025-04-14`, `gpt-4.1-mini`, `gpt-4.1-nano`, `gpt-4.1-nano-2025-04-14`, `gpt-4o-2024-05-13`, `gpt-4o-2024-08-06`, `gpt-4o-2024-11-20`, `gpt-4-0613`, `gpt-4o`, `gpt-4-turbo`, `gpt-4-turbo-2024-04-09`, `gpt-4o-mini-2024-07-18`, `gpt-4o-mini`, `o3`, `o3-mini`
* **Anthropic Claude (7):** `claude-opus-5`, `claude-opus-4-8`, `claude-opus-4-7`, `claude-opus-4-6`, `claude-sonnet-4-6`, `claude-opus-4-5-20251101`, `claude-sonnet-4-5-20250929`
* **Google Gemini (6):** `gemini-3.6-flash`, `gemini-3.1-pro-preview`, `gemini-3.1-pro-preview-customtools`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-pro-latest`

**Text and reasoning models**

* **OpenAI (17):** `gpt-5-nano`, `gpt-4`, `gpt-3.5-turbo-0125`, `gpt-3.5-turbo-1106`, `gpt-3.5-turbo-16k`, `gpt-3.5-turbo`, `o4-mini-2025-04-16`, `o4-mini`, `o3-pro`, `o3-pro-2025-06-10`, `o3-2025-04-16`, `o3-mini-2025-01-31`, `o1-pro`, `o1-pro-2025-03-19`, `o1`, `o1-2024-12-17`, `chat-latest`
* **Anthropic Claude (3):** `claude-sonnet-5`, `claude-fable-5`, `claude-haiku-4-5-20251001`
* **Google Gemini (11):** `gemini-3.7-flash`, `gemini-3.5-flash`, `gemini-3.5-flash-lite`, `gemini-3.1-flash-lite-preview`, `gemini-3.1-flash-lite`, `gemini-3-flash-preview`, `gemini-2.5-flash-lite`, `gemini-flash-latest`, `gemini-flash-lite-latest`, `gemma-4-26b-a4b-it`, `gemma-4-31b-it`

**Still-image generation models**

* **OpenAI (6):** `gpt-image-2`, `gpt-image-2-2026-04-21`, `gpt-image-1.5`, `gpt-image-1`, `gpt-image-1-mini`, `chatgpt-image-latest`
* **Google Gemini (7):** `gemini-3.1-flash-image`, `gemini-3.1-flash-image-preview`, `gemini-3.1-flash-lite-image`, `gemini-3-pro-image`, `gemini-3-pro-image-preview`, `gemini-2.5-flash-image`, `nano-banana-pro-preview`
* **Anthropic Claude:** no still-image generation model was returned.

The [complete live Hub catalogue](https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/#description) also groups the specialist identifiers which AI Scribe deliberately excludes.

= Why did I see an "AI-Scribe model updated" notice after installing? =

That notice is for retained data, not a genuinely clean install. It appears when an upgrade or reinstall still holds the untouched legacy `gpt-4o-mini` default, or when a previously selected model has been retired. AI Scribe then follows Opace AI Hub's current valid default and names both models in the notice. A valid model you explicitly saved is never overwritten, and a fresh install starts provider-neutral until the Hub selects from your live account.

= What happens to my edited prompts when I upgrade? =

They are copied into Opace AI Hub's prompt library, into a group named "AI-Scribe", with one prompt per wizard step, and each step is pointed at its copy. Your originals are left in place untouched as a backup. The copy runs once, resumes if it is interrupted, and never overwrites a prompt you already have in Opace AI Hub with the same name. If Opace AI Hub is not active yet, it simply waits and runs the next time an admin page loads.

= Is this cheaper than using ChatGPT directly? =

You pay your provider's API rates. Express mode starts the complete article in one structured call; optional length improvements are separate calls. The cost meter shows the estimate and recorded cost when trustworthy pricing is available. Unknown pricing is shown as Cost unavailable, never as a false zero.

= Can I choose a word count? =

Yes. Use Auto, a preset or a custom target globally or for one article. Body, Review and Express show the exact visible count, target and preferred range. If a complete draft is short, Improve length can add balanced detail while preserving the existing wording and outline; a failed improvement leaves the draft untouched.

= Are keyword search volumes measured? =

No. Without a paid keyword-data provider, AI Scribe labels qualitative demand as an unverified AI estimate and never invents monthly volume numbers. Use the per-keyword or selected-keyword Google Trends links to compare relative interest and seasonality.

= How do image captions and placement work? =

When image generation is available, Image Studio creates a specific editable caption from the generated visual or section subject. Generic workflow labels and raw prompts are not published as captions. You can edit or remove the caption, regenerate an image, place it by its matching section, replace it or delete it. An image whose section cannot be matched is not silently appended at the end.

= What does Evaluate verify? =

Structural facts are measured from the exact final Review HTML: usable images and alt markup, headings, table-of-contents anchors, internal contextual links and external links. Editorial assessments such as originality, authority and writing quality are clearly labelled for human confirmation; they are not presented as independently measured facts.

= Do my prompts or content pass through Opace's servers? =

No. Requests go directly from your WordPress site to the AI provider you configured. Nothing is routed through us.

= Where did the API key fields go? =

Into Opace AI Hub. AI Scribe's Providers tab shows a status chip per provider and a link to Opace AI Hub's settings. Keys are never rendered into the page, and AI Scribe refuses key writes while Opace AI Hub is active.

= What happened to the Humanize option? =

It's in Settings → Generation → Writing mode, as Humanizer and Humanizer with Personality, the same instruction sets as 2.6 and now applied to every step's system prompt. Humanizer is the default on a new install. It writes naturally: varied rhythm, direct address, humour and the occasional anecdote or tangent. Humanizer with Personality adds a witty, opinionated edge on top, with a blunt, sceptical voice that debunks trends and uses cultural references. Standard leaves the persona off entirely. Whichever you choose, your spelling choice, the banned-word list and the no-em-dash rule still apply, and your own Custom Instructions are applied last so they always win.

= Which English does it write in? =

Settings → Generation → Spelling, with British English as the default and American English as the alternative. The choice is stated explicitly in every request, in every writing mode. If you need something more specific than spelling, add it to Custom Instructions, which are applied after everything else.

= Does it work with Yoast / Rank Math / AIOSEO / SEOPress? =

Yes. Generated meta titles, descriptions and focus keywords are written to whichever of those is active, and stored with the post regardless.

= Will my 2.6 settings survive the upgrade? =

Yes. Saved prompts, API keys, content settings, languages and saved shortcodes are migrated automatically, and existing values are never overwritten — the migration only fills in keys that are missing.

= My model no longer exists. What happens? =

If the model you had selected has been retired by its provider, the upgrade picks a current replacement and shows a dismissible notice naming the old and new model. Nothing is swapped silently.

= Does uninstalling remove my data? =

Only if you opt in. Choose the delete-on-uninstall setting before deleting the plugin; otherwise AI Scribe settings, conversations and saved shortcodes are retained. The setting and confirmation copy describe the data classes affected so the choice is explicit.

== Screenshots ==

1. Title Generation in the responsive 11-step wizard, with the selected live model, editable prompt and article cost meter.
2. Keyword Research with qualitative AI demand bands and direct Google Trends checks instead of invented monthly volumes.
3. A selectable Article Outline that keeps the user in control of every section the body must cover.
4. The Article Body editor with measured word-target status and Image Studio beside the editable draft.
5. Full-width, editable Q&A fields with Select all and Deselect all controls.
6. Editable SEO meta title and description with measured length and keyword guidance.
7. Express mode with exact word-count status, retained-draft improvement and direct save actions.
8. Evaluate with responsive summary cards, measured structural evidence and clearly labelled editorial checks.

== Changelog ==

= 3.2.30 =
* Fixed: removed the dormant Grok/xAI provider route so runtime behaviour now matches the documented OpenAI, Anthropic and Google Gemini service disclosure.
* Added: a dated, complete snapshot of every writing and still-image model AI Scribe can select, including GPT-5.6 Sol, Terra and Luna, with specialist Hub-only categories clearly separated.
* Changed: linked the live Opace AI Hub dependency and provider hosts directly; clarified the retained-data model-update notice and current one-click dependency installation.
* Compliance: shortened the plugin header description, removed the unsupported main-header `Tested up to` field and retained WordPress 7.1 compatibility in this directory readme.

= 3.2.29 =
* Changed: plugin page headings now use the complete approved AI-Scribe logo, while the WordPress sidebar uses a centred white 20px symbol on transparency.
* Fixed: inline-only Markdown such as `**bold**` is converted before generated text reaches the editor instead of being displayed as markup characters.
* Compliance: added an explicit non-affiliation statement for OpenAI, Anthropic and Google, removed unreachable legacy provider/AJAX code and resolved Plugin Check findings in active request paths.
* Compatibility: tested up to WordPress 7.1.

= 3.2.28 =
* Changed: installed admin screens now use the approved AI-Scribe logo and a crisp symbol-only WordPress menu icon.
* Added: packaged 16px, 32px and 48px favicon variants plus a 32px ICO for compatible add-ons and integrations; AI-Scribe does not alter the site's favicon.
* Fixed: text-model discovery now excludes provider products that require audio, realtime, image, video, embedding, moderation, research, Codex, search or other specialist request paths.
* Fixed: OpenAI reasoning models remain available for article writing, while supported OpenAI and Gemini image models are routed to Image Studio instead of being removed with non-text models.
* Fixed: a saved specialist model can no longer be treated as usable for article generation.
* Improved: the legacy default migration notice now distinguishes an untouched default update from a model retired by its provider.
* Privacy: debug logs record request field names and response summaries instead of serialising request bodies, headers or generated image data.

= 3.2.27 =
* Fixed: an untouched legacy GPT-4o Mini seed now follows Opace AI Hub's current provider default; explicitly saved model choices remain unchanged.
* Improved: fresh installs no longer persist a hard-coded text-model seed before the Hub has selected from the account's live models.

= 3.2.26 =
* Fixed: generation now detects Opace AI Hub at request time, so the renamed `opace-ai-prompt-library-api-hub` folder can load after AI Scribe without producing a false "plugin is not active" error.
* Tests: added a regression check for an adapter created before the Hub API becomes available.

= 3.2.25 =
* Fixed: AI Scribe now recognises an active Opace AI Hub regardless of plugin load order, including network activation on multisite.
* Compatibility: Tested with WordPress 7.1 and PHP 8.3.

= 3.2.24 =
* Changed: the required hub plugin was renamed during WordPress.org review to Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini; the dependency slug, text-domain detection and all references now use `opace-ai-prompt-library-api-hub`.
* Compatibility: tested up to WordPress 7.1.

= 3.2.23 =
* Fixed: the required Opace AI Hub slug, text-domain detection and one-click dependency route now match `opace-ai-prompt-library-api-hub`, the permanent WordPress.org submission slug.
* Improved: the missing-dependency notice enables its install button automatically as soon as WordPress.org resolves the approved Opace AI Hub listing.
* Documentation: linked the separately published Opace AI Hub source and verified GitHub release.
* Compatibility: tested up to WordPress 7.1.

(Full historical changelog is available in the GitHub release history and earlier releases.)

== Upgrade Notice ==

= 3.2.30 =
Aligns runtime providers with the published privacy disclosure, documents the complete current selectable model snapshot and updates live dependency links.

= 3.2.29 =
Uses the complete approved page logo, a white WordPress sidebar symbol and correct formatting for inline-only Markdown.

= 3.2.28 =
Keeps AI-Scribe focused on supported writing and still-image models, updates legacy defaults safely and installs the approved branding assets.

= 3.2.22 =
Provider-aware dynamic defaults keep valid saved choices and select the newest preferred writing or image family only when a choice is missing or retired.

= 3.2.21 =
Improves high-resolution and narrow-screen navigation, balances manual length additions, hardens reset safety and aligns article-length and temperature defaults.

= 3.2.20 =
Contains installed WordPress form controls at phone widths and includes all responsive, length, markup and caption corrections from 3.2.19.

= 3.2.19 =
Corrects responsive Wizard and admin layouts, adds safe Body and Review length improvement, repairs malformed provider markup and prevents generic image captions.

= 3.2.18 =
Restores the Custom target-word field in the Wizard's actual page structure.

= 3.2.17 =
Aligns visible word counts, adds draft-safe Express length improvement and save actions, and fixes inconsistent Express paragraph structure.

= 3.2.16 =
Fixes Safari rendering after successful Express generation and hardens the same shared status-card path in the Wizard.

= 3.2.15 =
Improves word-count feedback, image placement and captions, publishing metadata and responsive saved-article layout.

= 3.2.14 =
Keeps usable near-target articles, reports word-count shortfalls honestly and simplifies the article-length controls.

= 3.2.13 =
Adds automatic editable captions, two-way bulk image placement, clearer factual Evaluate results and an explicit amended-prompt action.

= 3.2.12 =
Keeps metadata optimisation reversible after a valid suggestion is applied.

= 3.2.11 =
Adds one final measured correction for provider variance while retaining strict depth thresholds and a three-call ceiling.

= 3.2.10 =
Makes bounded article-depth correction explicit and measurable after live Gemini stopped below the unchanged helpfulness minimum.

= 3.2.9 =
Adds intelligent article-depth planning, optional metadata shortening, stricter completeness gates, cleaner featured-image publishing and per-user conversation isolation.

= 3.2.8 =
Corrects Express structure, explicit recovery, Gemini image resilience, metadata casing and factual contextual-link evaluation.

= 3.2.7 =
Prevents incomplete outline coverage, makes final save state explicit, and rebuilds Image Studio around visible progress, automatic featured images and editable captions.

= 3.2.6 =
Makes Express progress unmistakable and clarifies that settings are retained unless destructive uninstall cleanup is explicitly enabled.

= 3.2.5 =
Adds free, clearly qualified keyword demand signals and Google Trends comparison without claiming live search-volume measurements.

= 3.2.4 =
Adds multi-keyword metadata coverage guidance and the required spaced-pipe title convention.

= 3.2.3 =
Prevents test fixtures appearing in owner testing, keeps saved model controls visually in sync, and fixes Q&A editor width.

= 3.2.1 =
Fixes blank Wizard transitions and restores visible Express generation progress.

= 3.2.0 =
More accurate current-year titles, truthful keyword evidence, one-tagline generation and a clearer full-width editorial workflow.

= 3.1.9 =
Evaluate now refreshes whenever the current Review article is continued.

= 3.1.8 =
Evaluate now measures the active Review editor users can see, including inserted images.

= 3.1.7 =
Evaluate report headings and row text now share consistent column alignment.

= 3.1.6 =
Evaluate now receives the exact reviewed article, including images, from a pre-navigation snapshot.

= 3.1.5 =
Evaluate now measures the exact article visible in Review at click time.

= 3.1.4 =
Inserted images visible in Review now remain present in Evaluate and saved article output.

= 3.1.3 =
Automatically placed images now retain valid article structure beside section headings.

= 3.1.2 =
Changing the title-step topic can no longer regenerate against an older recovered article.

= 3.1.1 =
The Evaluate, image, SEO metadata, Q&A, progress and notification workflows have been corrected and made easier to verify. Requires Opace AI Hub 0.7.8 or later.

= 3.1.0 =
Live Gemini generations failed outright before this release, and usage was never recorded in Opace AI Hub. Both are fixed, along with keyword and SEO meta handling, the image workflow and progress feedback throughout. Requires Opace AI Hub 0.7.8 or later.

= 3.0.8 =
Images work with Gemini as well as OpenAI, key validation is now honest, and several provider request faults are fixed. Tested on WordPress 7.1.

= 3.0.0 =
Major rebuild: threaded generation, Express mode, Gemini support, live model lists and cost tracking. Settings and saved shortcodes migrate automatically.
