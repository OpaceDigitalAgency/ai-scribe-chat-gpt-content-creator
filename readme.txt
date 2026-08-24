=== Opace AI Scribe: SEO Content Creator & Humanizer for OpenAI, Anthropic & Gemini ===
Contributors: opacewebdesign
Tags: AI Writer, Content Generator, Content Creator, AI, SEO
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

**AI Scribe** is a free, open-source SEO AI writer, content generator and humaniser for WordPress. It helps you turn an idea into an editable, search-focused article with OpenAI, Anthropic Claude or Google Gemini, with optional support for the WordPress 7 core AI client.

AI Scribe is independently developed by Opace Digital Agency and is not affiliated with, endorsed by or sponsored by OpenAI, Anthropic or Google.

Version 3 keeps the whole article in one conversation, so later sections are written with the title, outline and existing copy in context. Use the guided 11-step Wizard for control at every stage, or Express mode for a complete first draft. AI Scribe supports the process; you remain responsible for checking, editing and approving everything before publication.

= Required Opace AI Hub =

The free [Opace AI Hub](https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/) is required. It stores provider keys, discovers models, supplies pricing and shares prompts. AI Scribe owns the writing interface and sends requests directly from your site through the Hub; nothing is routed through Opace's servers. WordPress prevents activation without the Hub, and AI Scribe shuts down cleanly if it is later deactivated.

= Live providers and models =

Add OpenAI, Anthropic or Google Gemini keys in the Hub. Models are fetched from your account, cached for one hour and filtered for writing or still-image capability. A valid saved choice is preserved; Refresh models bypasses the cache.

**Snapshot: 23 August 2026, Hub refresh 13:03 BST and AI Scribe check 13:33 BST.** The Hub returned 192 identifiers; AI Scribe accepted 102: 68 OpenAI, 10 Anthropic and 24 Gemini. This included 58 multimodal writing, 31 text/reasoning and 13 image models. Highlights included GPT-5.6 Sol, Terra and Luna; Claude Opus 5, Sonnet 5, Fable 5 and Haiku 4.5; Gemini 3.7 Flash, 3.6 Flash and 3.1 Pro; GPT Image 2; and Gemini 3.1 Flash Image. Settings remains authoritative because access changes by account and provider rollout.

OpenAI and Gemini can supply text and images. Claude supplies text, so pair Anthropic with OpenAI or Gemini if you want Claude writing plus generated images. Audio, realtime, embedding, video, code, research and other specialist Hub models are deliberately excluded.

= Core features =

* **Guided or fast drafting:** use the 11-step Wizard for titles, keywords, outline, introduction, tagline, body, conclusion, Q&A, SEO meta, Review and Evaluate, or use Express for a structured whole-article draft.
* **One threaded article:** later steps retain the title, outline and existing copy, reducing repetition and disconnected sections.
* **Live provider choice:** select compatible writing and image models exposed by your own OpenAI, Anthropic or Gemini account.
* **Editable prompts and results:** amend a prompt for one run, save step prompts in AI Scribe or apply shared prompts from Opace AI Hub.
* **Brand and language controls:** set language, style, tone, British or American spelling, banned phrases and reusable Custom Instructions.
* **Three writing modes:** choose Standard, Humanizer or Humanizer with Personality for different levels of voice and informality.
* **Measured article length:** choose Auto, a preset or a custom word target; visible counts and safe improvement actions retain the usable draft if a request fails.
* **Honest keyword guidance:** qualitative demand bands are labelled unverified AI estimates and link to Google Trends rather than claiming invented monthly volumes.
* **Image Studio:** create, caption, place, replace and remove compatible OpenAI or Gemini featured and section images.
* **Visible costs:** see estimated, actual and running provider spend when trustworthy pricing is available.
* **SEO and editorial checks:** generate metadata and focus keywords, then separate measured structural checks from judgements requiring human review.
* **Flexible output:** save a draft or published post, or create a reusable shortcode for page builders, widgets and custom fields.
* **WordPress integrations:** supports the block and Classic Editors plus Yoast SEO, Rank Math, AIOSEO and SEOPress.
* **Responsive, permission-aware admin:** authors can write; administrators manage settings and saved shortcodes.

= Prompts, placeholders and writing modes =

Every Wizard step shows the prompt that will guide the selected model. Editing the visible prompt does not silently change a request: choose **Run amended prompt** to use it once. A one-run amendment takes priority, followed by an applied Hub prompt or your saved AI Scribe prompt; built-in wording remains the fallback. Custom Instructions are appended last so your brand rules take final priority.

Prompt placeholders insert the current article choices when the request runs. Available placeholders include `[Idea]`, `[Title]`, `[Selected Keywords]`, `[Heading]`, `[Outline]`, `[Intro]`, `[The Tagline]`, `[above/below]`, `[Language]`, `[Style]`, `[Tone]`, `[No. Headings]`, `[Heading Tag]` and `[Keywords to Avoid]`. Use only placeholders relevant to that step and keep the square brackets intact.

* **Standard:** clear, direct writing guided by your selected style, tone, spelling and instructions.
* **Humanizer:** varies sentence structure and uses more natural phrasing while retaining the article brief and SEO controls.
* **Humanizer with Personality:** adds a more distinctive, informal voice where the subject and audience suit it.

These modes change writing guidance, not authorship. They cannot guarantee originality, rankings or a particular result from an AI-detection tool.

= Images, costs, SEO and output =

Image Studio supports compatible OpenAI and Gemini image models, editable prompts and captions, style presets, featured and section images, placement, replacement and removal. Image controls hide when no configured provider can generate images.

The cost meter shows estimated, actual and running spend when trustworthy pricing is available. Review and Evaluate distinguish measured structure from editorial checks requiring human confirmation.

Save as a draft, published post or reusable shortcode. Meta title, description and focus keywords are stored with the post and written to the first active Yoast SEO, Rank Math, AIOSEO or SEOPress integration. Standard post content works with the block and Classic Editors; shortcodes work in pages, posts, Divi, Elementor, widgets and ACF fields. Admin screens are responsive.

Authors and above can write; Settings and Saved Shortcodes require an administrator. Generation endpoints require permission and a nonce. Prompts and article content are sent only to the provider you select, and provider API charges apply.

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

= Is AI Scribe free, and are there any other costs? =

AI Scribe and the required Opace AI Hub are free, open-source WordPress plugins. The provider you choose charges separately for API usage under its own account, prices and terms. AI Scribe shows an estimate and running cost when the Hub has trustworthy pricing, but your provider account is the billing authority.

= Do I really need Opace AI Hub? =

Yes. The Hub stores keys, discovers models, records usage and supplies shared prompts. With Anthropic alone, writing works but image controls are hidden. Add OpenAI or Gemini for images; any mixture of the three providers works.

= Where can I get and enter an API key? =

Create a key in the provider account you want to use: [OpenAI API keys](https://platform.openai.com/api-keys), [Anthropic Console keys](https://console.anthropic.com/settings/keys) or [Google AI Studio keys](https://aistudio.google.com/apikey). Enter it under Opace AI Hub → Settings, then test it there. Never post a key in a support request or paste it into an article prompt. Compatible WordPress 7 AI Connector credentials may also be used when that provider connector is installed and configured.

= Which models are supported? =

Whichever compatible writing and still-image models your configured OpenAI, Anthropic or Google Gemini account grants you. Models are fetched live and filtered by capability, so Settings is authoritative when providers change account access.

= How many models were in the dated live snapshot? =

The 23 August 2026 snapshot contained **102 AI Scribe-compatible identifiers**:

* **58 multimodal writing models:** 45 OpenAI, 7 Anthropic and 6 Gemini.
* **31 text and reasoning models:** 17 OpenAI, 3 Anthropic and 11 Gemini.
* **13 still-image models:** 6 OpenAI and 7 Gemini. Anthropic returned no image-generation model.

Showcased current families include:

* **OpenAI writing:** GPT-5.6 Sol, Terra and Luna, GPT-5.5/5.4 families, o4-mini and o3 families.
* **Anthropic writing:** Claude Opus 5, Sonnet 5, Fable 5, Haiku 4.5 and supported Claude 4 variants.
* **Gemini writing:** Gemini 3.7 Flash, 3.6 Flash, 3.1 Pro and supported Gemini 2.5 models.
* **Images:** GPT Image 2/1.5/1 and Gemini 3.1 Flash Image, Gemini 3 Pro Image and Nano Banana families.

Use Refresh models in Settings for the exact identifiers available to your account. The [complete Hub catalogue](https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/#description) also groups specialist identifiers that AI Scribe deliberately excludes.

= How are models kept current? =

The Hub requests the model list from each configured provider and caches it for one hour. AI Scribe filters that list to compatible writing and still-image capabilities. Use **Refresh models** to bypass the cache. A maintained Hub registry is only a fallback when live discovery is unavailable; your Settings screen is authoritative for your account.

= How do tokens and model limits work? =

Models process text as tokens, which may be whole words, parts of words, punctuation or spaces. In English, one token is often around four characters or three-quarters of a word, but this is only a rough estimate. A model's context limit must cover instructions, conversation history and output together. Limits vary by model and account, so shorten the requested article or choose a suitable model if a long request exceeds them.

= How do costs and word targets work? =

Provider charges normally depend on input/output tokens and, for images, the selected model and options. AI Scribe shows estimated, actual and running costs when trustworthy Hub pricing exists and says **Cost unavailable** otherwise. Choose Auto, a preset or a custom word target. Visible counts, preferred ranges and optional improvement requests never discard a usable draft after failure.

= How can I customise and save prompts? =

Edit the visible prompt at a Wizard step and choose **Run amended prompt** for a one-off request. Save a reusable AI Scribe step prompt in Settings, or apply a shared prompt from Opace AI Hub. A one-run amendment has highest priority; Custom Instructions are applied last. Your current result remains available if the amended request fails.

= What are prompt placeholders? =

Placeholders insert current article choices when a prompt runs. AI Scribe supports `[Idea]`, `[Title]`, `[Selected Keywords]`, `[Heading]`, `[Outline]`, `[Intro]`, `[The Tagline]`, `[above/below]`, `[Language]`, `[Style]`, `[Tone]`, `[No. Headings]`, `[Heading Tag]` and `[Keywords to Avoid]`. Keep the brackets and use placeholders that make sense for the step.

= What are Custom Instructions? =

Custom Instructions are reusable rules added to every writing request after the step prompt. Use them for brand terminology, preferred spelling, audience, formatting, phrases to avoid and non-negotiable editorial rules. They apply across OpenAI, Anthropic and Gemini rather than being limited to a particular ChatGPT model.

= How do I keep the content aligned with my brand voice and tone? =

Set the language, writing style, tone, spelling and writing mode under AI Scribe → Settings → Generation, then add precise brand rules to Custom Instructions. Give concrete examples of preferred wording and prohibited claims. Review the output because a model can still miss or misinterpret instructions.

= What is the difference between the three writing modes? =

**Standard** aims for clear, direct copy governed by your chosen style and tone. **Humanizer** encourages more varied sentence structure and natural phrasing. **Humanizer with Personality** permits a more distinctive, informal voice where appropriate. None can guarantee originality, search rankings or an AI-detector result.

= What if I do not like the generated content? =

Edit the result directly, select **Regenerate**, or change the visible prompt and choose **Run amended prompt**. You can also refine the saved prompt, style, tone or Custom Instructions for future runs. AI responses vary, so a second request may differ even without a prompt change; always keep and improve the best useful draft.

= What if nothing appears, the result is incomplete or a request times out? =

Check the provider-status chip and confirm that a valid live model is selected. Retry once; for repeated failures, refresh the model list, choose a faster model, request less content or ask your host about PHP and web-server time limits. AI Scribe retains an existing usable draft when a later improvement fails. Report persistent errors with the step and provider, but never include an API key or private article content.

= Why did I see an "AI-Scribe model updated" notice after installing? =

An upgrade or reinstall retained either the untouched legacy `gpt-4o-mini` default or a retired model. AI Scribe selects a current valid Hub default and names both models. It never replaces a valid model you explicitly saved.

= What happens to my edited prompts when I upgrade? =

They are copied once into the Hub's "AI-Scribe" group and linked to their steps. Originals remain as a backup, interrupted migration resumes, and an existing same-name Hub prompt is never overwritten.

= Will my other 2.6 settings survive? =

Yes. API keys, content settings, custom languages and saved shortcodes migrate automatically. Existing values are not overwritten. Carried-over keys are encrypted at rest in the Hub.

= Are keyword search volumes measured? =

No. Demand bands are marked as unverified AI estimates. Use the Google Trends links for relative interest and seasonality; they are not monthly-volume data.

= What does Evaluate verify? =

It measures structural facts from the final Review HTML, including images, alt markup, headings, table-of-contents anchors and links. Originality, authority and writing quality remain editorial judgements requiring human confirmation.

= Do prompts, content or keys pass through Opace's servers? =

No. Keys are managed by Opace AI Hub; requests go directly from your WordPress site to your chosen provider. AI Scribe only shows provider status and never renders keys into its page.

= Does it work with SEO plugins and page builders? =

Yes. It integrates with Yoast SEO, Rank Math, AIOSEO and SEOPress. Standard post content works with WordPress editors, while saved shortcodes work in Divi, Elementor, widgets and ACF fields.

= How should I check originality and duplicate content? =

Search distinctive passages and use an appropriate originality or plagiarism-checking service if your editorial process requires one. Add first-hand expertise, verify sources and rewrite generic sections. AI Scribe and its Humanizer modes cannot certify originality or promise how an AI detector will classify text.

= Does uninstalling remove my data? =

Only if you opt in through the delete-on-uninstall setting. Otherwise settings, conversations and saved shortcodes are retained.

= Who created AI Scribe, and where can I get help? =

AI Scribe was designed and developed by [Opace Digital Agency](https://opace.agency/), a UK digital agency. Use the [WordPress.org support forum](https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/) for bugs and suggestions. It is a free, community-supported project, so support is best-effort and prioritises critical defects, compatibility and useful improvements.

== AI-Scribe GPT ==

Prefer to work directly in ChatGPT? The separate [AI-Scribe GPT](https://chatgpt.com/g/g-ZTkBnCIbA-gpt-seo-article-creator-writer-ai-scribe) remains available. It does not install into WordPress or share Opace AI Hub settings, posts, prompts or usage records; copy any finished content into WordPress manually.

== Video demonstrations ==

Watch the current AI Scribe v3 walkthrough, covering Opace AI Hub setup, the guided writing workflow, Express mode, images, SEO, Review and Evaluate.

https://www.youtube.com/watch?v=NkXMf-rl6TE

== Community, support and contact ==

AI Scribe is free, open source and community-supported. It grew from Opace's aim to bring keyword-led, controllable SEO drafting into WordPress; feedback from users and the wider prompt-writing community has helped shape it. Support is best-effort and prioritises critical defects, compatibility and useful improvements. Report bugs or suggest improvements through the [WordPress.org support forum](https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/). If the plugin helps you, please [leave a WordPress.org review](https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/reviews/#new-post).

AI Scribe is designed and developed by [Opace Digital Agency](https://opace.agency/), a UK digital agency providing [WordPress development](https://opace.agency/services/web-design/wordpress-development/). You can [contact Opace](https://opace.agency/get-in-touch/), follow [Opace on Facebook](https://www.facebook.com/opacewebdesign) or follow [Opace on X](https://x.com/OpaceWeb).

== SEO and editorial guidance ==

AI-generated text needs human review. Before publishing:

1. Check every fact, quotation, source, product detail and claim. Never publish invented evidence.
2. Write for the reader first. Remove repetition, keyword stuffing and text that does not answer the search intent.
3. Verify keyword demand with a trusted data source. AI Scribe's demand bands are clearly labelled unverified estimates; Google Trends links show relative interest, not monthly volume.
4. Add first-hand knowledge, useful examples, internal links and properly reviewed external sources where they genuinely help.
5. Search distinctive passages and use an appropriate originality or plagiarism-checking service if your editorial process requires one.
6. Check image rights, alt text, captions, personal data and any disclosure required by your organisation or jurisdiction.
7. Review the final post in WordPress and in your SEO plugin before publishing. Humanizer changes writing style; it cannot guarantee rankings, originality or any AI-detector result.

== Potential future updates ==

These are ideas under consideration, not commitments or release dates:

* True token-by-token streaming where the selected provider and WordPress hosting environment support it.
* Optional verified keyword metrics through a user-configured keyword-data provider.
* A source-research and citation workflow with clear consent, provenance and user approval before sources are used.
* Batch article planning with per-item confirmation, provider rate limits and visible cost controls.
* Dedicated WooCommerce product and category content workflows.
* Deeper, provider-neutral SEO scoring previews for supported WordPress SEO plugins.

Please add roadmap suggestions through the support forum rather than treating this list as promised functionality.

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

* **3.2.30:** Aligned runtime providers and disclosures; restored complete public documentation; added dated model totals and current-family highlights.
* **3.2.29:** Added approved page/sidebar branding, normalised inline Markdown and resolved active-path Plugin Check findings.
* **3.2.28:** Filtered models by writing/image capability, improved legacy-default migration and removed sensitive debug payload logging.
* **3.2.27:** Made untouched legacy defaults follow the Hub without replacing explicit choices.
* **3.2.26:** Fixed renamed-Hub detection when the Hub loads after AI Scribe.
* **3.2.25:** Fixed Hub detection across load orders, multisite and network activation.
* **3.2.24:** Adopted the permanent `opace-ai-prompt-library-api-hub` dependency slug.
* **3.2.23:** Added the correct one-click Hub dependency route and public source links.
* **3.2.22:** Rebuilt directory documentation and provider-neutral dependency presentation.
* **3.2.21:** Improved narrow/high-resolution navigation, length additions and reset/default safety.
* **3.2.20:** Contained mobile form controls and included responsive, markup and caption fixes.
* **3.2.19:** Added responsive layouts, safe length improvement and reliable provider/caption markup.
* **3.2.18:** Restored the Wizard's Custom word-target field.
* **3.2.17:** Aligned counts and added draft-safe Express improvement and save actions.
* **3.2.16:** Fixed Safari Express rendering and status-card behaviour.
* **3.2.15:** Improved counts, image placement/captions, metadata and saved-article layouts.
* **3.2.14:** Retained useful near-target drafts and clarified length controls.
* **3.2.13:** Added editable captions, bulk placement, factual Evaluate results and amended-prompt action.
* **3.2.12:** Made metadata optimisation reversible.
* **3.2.11:** Added bounded final depth correction with a three-call ceiling.
* **3.2.10:** Made article-depth correction explicit and measurable.
* **3.2.9:** Added depth planning, completeness gates, metadata shortening and per-user conversations.
* **3.2.8:** Corrected Express, Gemini images, metadata casing and contextual-link evaluation.
* **3.2.7:** Enforced outline coverage and rebuilt Image Studio progress, placement and captions.
* **3.2.6:** Clarified Express progress and retained-data uninstall behaviour.
* **3.2.5:** Added qualified keyword demand bands and Google Trends links.
* **3.2.4:** Added multi-keyword metadata and spaced-pipe title guidance.
* **3.2.3:** Removed test fixtures from owner testing and fixed model/Q&A control state.
* **3.2.1:** Fixed blank Wizard transitions and Express progress.
* **3.2.0:** Improved current-year titles, keyword evidence, taglines and the editorial workflow.
* **3.1.9:** Refreshed Evaluate after continuing Review content.
* **3.1.8:** Included active Review content and images in Evaluate.
* **3.1.7:** Aligned Evaluate report columns.
* **3.1.6:** Sent Evaluate an exact pre-navigation article snapshot.
* **3.1.5:** Measured the Review article visible at click time.
* **3.1.4:** Preserved Review images in Evaluate and saved output.
* **3.1.3:** Preserved article structure around automatically placed images.
* **3.1.2:** Prevented changed topics using an older recovered article.
* **3.1.1:** Corrected Evaluate, images, SEO metadata, Q&A, progress and notifications.
* **3.1.0:** Fixed Gemini generation/usage recording and improved keywords, metadata, images and progress.
* **3.0.8:** Added Gemini images, honest key validation and provider request fixes.
* **3.0.0:** Rebuilt AI Scribe with threaded generation, Express, Gemini, live models, costs and migration.
* **2.6.2:** Updated the public description.
* **2.6:** Added then-current OpenAI/Claude models, GPT Image, dual keys, parallel images and cost estimates.
* **2.5.1:** Corrected API-key save feedback and Settings UX.
* **2.5:** Fixed shortcode deletion and hardened its data query.
* **2.4:** Added shortcode SQL protection, AJAX nonces/capabilities and stronger validation.
* **2.3:** Added generated titles to image alt/title attributes.
* **2.2:** Added DALL-E 3 article images.
* **2.1:** Expanded public instructions.
* **2.0:** Added GPT-4o-era models, refreshed prompts and introduced three writing modes.
* **1.2.5:** Added Vietnamese, Arabic, custom languages and the AI-Scribe GPT link.
* **1.2.4:** Improved token/timeout handling, displayed the version and added then-current Turbo models.
* **1.2.2:** Fixed a WordPress media-library menu conflict.
* **1.2.0:** Improved prompts, sticky save controls and custom instructions.
* **1.1.0:** Improved API errors, progress, keyboard submission, confirmations, models and SEO metadata.
* **1.0.0:** Initial release.

== Upgrade Notice ==

= 3.2.30 =
Aligns runtime providers with the published privacy disclosure, documents the complete current selectable model snapshot and updates live dependency links.
