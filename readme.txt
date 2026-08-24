=== Opace AI Scribe: SEO Content Creator & Humanizer for OpenAI, Anthropic & Gemini ===
Contributors: opacewebdesign
Tags: AI Writer, Content Generator, AI Content Creator, AI, SEO
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

Version 3 keeps the whole article in one conversation, so later sections are written with the title, outline and existing copy in context. Use the guided 11-step Wizard or Express mode for a complete first draft.

= Required Opace AI Hub =

The free [Opace AI Hub](https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/) is required. It stores provider keys, discovers models, supplies pricing and shares prompts. AI Scribe owns the writing interface and sends requests directly from your site through the Hub; nothing is routed through Opace's servers. WordPress prevents activation without the Hub, and AI Scribe shuts down cleanly if it is later deactivated.

= Live providers and models =

Add OpenAI, Anthropic or Google Gemini keys in the Hub. Models are fetched from your account, cached for one hour and filtered for writing or still-image capability. A valid saved choice is preserved; Refresh models bypasses the cache.

**Snapshot: 23 August 2026, Hub refresh 13:03 BST and AI Scribe check 13:33 BST.** The Hub returned 192 identifiers; AI Scribe accepted 102: 68 OpenAI, 10 Anthropic and 24 Gemini. This included 58 multimodal writing, 31 text/reasoning and 13 image models. Highlights included GPT-5.6 Sol, Terra and Luna; Claude Opus 5, Sonnet 5, Fable 5 and Haiku 4.5; Gemini 3.7 Flash, 3.6 Flash and 3.1 Pro; GPT Image 2; and Gemini 3.1 Flash Image. Settings remains authoritative because access changes by account and provider rollout.

OpenAI and Gemini can supply text and images. Claude supplies text, so pair Anthropic with OpenAI or Gemini if you want Claude writing plus generated images. Audio, realtime, embedding, video, code, research and other specialist Hub models are deliberately excluded.

= Wizard, Express and prompts =

The Wizard covers titles, keywords, outline, introduction, tagline, body, conclusion, Q&A, SEO meta, Review and Evaluate. Results and prompts stay editable. Choose Auto, a preset or a custom word target; visible counts and safe improvement actions retain the usable draft if a request fails.

Express returns a structured whole-article draft in one starting request. The threaded workflow reduces resent context and repetition. Keyword demand bands are labelled unverified AI estimates and include Google Trends links rather than invented monthly volumes.

Use built-in prompts, your saved AI Scribe prompt, or a shared Hub prompt. A one-run amended prompt has highest priority. Custom Instructions apply brand voice, spelling and banned phrases last. Writing modes are Standard, Humanizer and Humanizer with Personality.

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

= Do I really need Opace AI Hub? =

Yes. The Hub stores keys, discovers models, records usage and supplies shared prompts. With Anthropic alone, writing works but image controls are hidden. Add OpenAI or Gemini for images; any mixture of the three providers works.

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

= Why did I see an "AI-Scribe model updated" notice after installing? =

An upgrade or reinstall retained either the untouched legacy `gpt-4o-mini` default or a retired model. AI Scribe selects a current valid Hub default and names both models. It never replaces a valid model you explicitly saved.

= What happens to my edited prompts when I upgrade? =

They are copied once into the Hub's "AI-Scribe" group and linked to their steps. Originals remain as a backup, interrupted migration resumes, and an existing same-name Hub prompt is never overwritten.

= Will my other 2.6 settings survive? =

Yes. API keys, content settings, custom languages and saved shortcodes migrate automatically. Existing values are not overwritten. Carried-over keys are encrypted at rest in the Hub.

= How do costs and word counts work? =

You pay the provider's API rates. AI Scribe shows costs when trustworthy pricing exists and says **Cost unavailable** otherwise. Choose Auto, a preset or a custom word target. Visible counts, ranges and optional improvement requests never discard the usable draft after failure.

= Are keyword search volumes measured? =

No. Demand bands are marked as unverified AI estimates. Use the Google Trends links for relative interest and seasonality; they are not monthly-volume data.

= What does Evaluate verify? =

It measures structural facts from the final Review HTML, including images, alt markup, headings, table-of-contents anchors and links. Originality, authority and writing quality remain editorial judgements requiring human confirmation.

= Do prompts, content or keys pass through Opace's servers? =

No. Keys are managed by Opace AI Hub; requests go directly from your WordPress site to your chosen provider. AI Scribe only shows provider status and never renders keys into its page.

= Where are Humanizer and spelling settings? =

Use Settings → Generation. Choose Standard, Humanizer or Humanizer with Personality, and British or American English. Custom Instructions are applied last, so your brand rules win.

= Does it work with SEO plugins and page builders? =

Yes. It integrates with Yoast SEO, Rank Math, AIOSEO and SEOPress. Standard post content works with WordPress editors, while saved shortcodes work in Divi, Elementor, widgets and ACF fields.

= What if generation is blank or times out? =

Keep the retained draft, check the provider-status chip, then retry. For repeated timeouts, use a faster model, reduce the requested length or ask your host about PHP/web-server time limits. Report persistent errors in the support forum with the step and provider, but never post an API key or private article content.

= Does uninstalling remove my data? =

Only if you opt in through the delete-on-uninstall setting. Otherwise settings, conversations and saved shortcodes are retained.

== AI-Scribe GPT ==

Prefer to work directly in ChatGPT? The separate [AI-Scribe GPT](https://chatgpt.com/g/g-ZTkBnCIbA-gpt-seo-article-creator-writer-ai-scribe) remains available. It does not install into WordPress or share Opace AI Hub settings, posts, prompts or usage records; copy any finished content into WordPress manually.

== Video demonstrations ==

Watch the current AI Scribe v3 walkthrough, covering Opace AI Hub setup, the guided writing workflow, Express mode, images, SEO, Review and Evaluate.

https://www.youtube.com/watch?v=NkXMf-rl6TE

== Community, support and contact ==

AI Scribe is free and community-supported. Report bugs or suggest improvements through the [WordPress.org support forum](https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/). If the plugin helps you, please [leave a WordPress.org review](https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/reviews/#new-post).

AI Scribe is designed and developed by [Opace Digital Agency](https://opace.agency/), a UK digital agency providing [WordPress development](https://opace.agency/services/web-design/wordpress-development/). You can [contact Opace](https://opace.agency/get-in-touch/), follow [Opace on Facebook](https://www.facebook.com/opacewebdesign) or follow [Opace on X](https://x.com/OpaceWeb).

== SEO and editorial guidance ==

AI-generated text needs human review. Before publishing:

1. Check every fact, quotation, source, product detail and claim. Never publish invented evidence.
2. Write for the reader first. Remove repetition, keyword stuffing and text that does not answer the search intent.
3. Verify keyword demand with a trusted data source. AI Scribe's demand bands are clearly labelled unverified estimates; Google Trends links show relative interest, not monthly volume.
4. Add first-hand knowledge, useful examples, internal links and properly reviewed external sources where they genuinely help.
5. Check image rights, alt text, captions, personal data and any disclosure required by your organisation or jurisdiction.
6. Review the final post in WordPress and in your SEO plugin before publishing. Humanizer changes writing style; it cannot guarantee rankings, originality or any AI-detector result.

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
