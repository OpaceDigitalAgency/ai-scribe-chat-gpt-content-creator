=== Opace AI Scribe: SEO Content Creator & Humanizer for OpenAI, Anthropic & Gemini ===
Contributors: opacewebdesign
Tags: AI Writer, Content Generator, Content Creator, AI, SEO
Requires at least: 6.5
Tested up to: 7.0.4
Requires PHP: 7.4
Requires Plugins: opace-ai-prompt-library-api-hub
Stable tag: 3.2.24
License: GPL-3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
Donate link: https://opace.agency/get-in-touch

AI SEO content writer and humaniser for OpenAI, Anthropic and Gemini. Eleven-step wizard, Express mode, Yoast and Rank Math ready.

== Description ==

**AI Scribe** is an SEO AI writer, content generator and humaniser for OpenAI, Anthropic and Google Gemini, with optional support for the WordPress 7 core AI client.

Version 3 is a ground-up rebuild of the plugin around one idea: the model should always see the whole article. Every step of the 11-step wizard runs inside a single conversation thread, so the conclusion, Q&A and SEO meta are written with the full body in context rather than blind. The result is coherent long-form content without the repetition and drift older step-by-step tools suffer from.

= Modular architecture =

Version 3 splits what used to be one plugin into two. AI Scribe is the writing tool. **Opace AI Hub** is the hub that holds provider credentials, model lists, pricing and the shared prompt library. Add a key once in Opace AI Hub and every plugin built on the hub can use it.

`
┌────────────────────────────────────────────────────────────────┐
│  AI Scribe  (this plugin — the writing tool)                   │
│                                                                │
│   Wizard UI (11 steps)  ·  Express mode  ·  Saved shortcodes   │
│   ────────────────────────────────────────────────────────     │
│   Browser controllers  →  AJAX endpoints (nonce + capability)  │
│   ────────────────────────────────────────────────────────     │
│   Services                                                     │
│     Generation · Conversation thread · Images · Cost meter     │
│     Prompt assembly · Response schemas · SEO + post output     │
│                             │                                  │
│                             ▼  Opace AI Hub adapter                 │
└─────────────────────────────┼──────────────────────────────────┘
                              │
┌─────────────────────────────▼──────────────────────────────────┐
│  Opace AI Hub  (required hub plugin — no content of its own)        │
│                                                                │
│   API keys, encrypted at rest   ·   Live model discovery       │
│   Shared prompt library         ·   Usage + cost statistics    │
│   Per-provider request builders ·   Response normaliser        │
└───────┬──────────────────────┬──────────────────────┬──────────┘
        │                      │                      │
        ▼                      ▼                      ▼
     OpenAI                Anthropic               Gemini
  text + images            text only            text + images
`

What this buys you: one place to manage keys instead of one per plugin; model lists that come from your own provider account rather than a list baked into the plugin; usage and spend recorded across every add-on together; and a prompt library shared between them. It also means a provider fix lands once, in the hub, instead of separately in each plugin.

= Opace AI Hub is required =

AI Scribe is an add-on to the free **Opace AI Hub** hub plugin and does nothing without it. WordPress will not let you activate AI Scribe until Opace AI Hub is active. If Opace AI Hub is deactivated afterwards, AI Scribe shuts itself down cleanly — no menu, no wizard, no endpoints — and shows a notice with a button to switch Opace AI Hub back on.

AI Scribe's own Providers tab therefore has no key fields. It shows a status chip per provider — configured, not configured, or key rejected by the provider — and a link through to Opace AI Hub's settings.

= Providers and models =

Add a key for one provider or all three. The model list is fetched live from your own account and cached for an hour, with a Refresh button that bypasses the cache. AI Scribe filters the returned models by capability and preserves a valid saved choice. Opace AI Hub's maintained registry is used only when live discovery is unavailable, so the plugin does not promise model names which a provider may rename, retire or withhold from a particular account.

* **OpenAI** — text and image-capable models exposed by the configured account.
* **Anthropic** — text-capable models exposed by the configured account. Anthropic does not provide an image model.
* **Google Gemini** — text and image-capable models exposed by the configured account.

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

1. **Titles** – generate title options from your idea
2. **Keywords** – SEO keyword suggestions, multi-select, skippable
3. **Outline** – section headings you can trim or extend
4. **Introduction** – long-form prose
5. **Tagline** – with above/below-introduction placement
6. **Article body** – full article in a rich editor with an image gallery
7. **Conclusion** – written with the complete body in context
8. **Q&A** – optional block, placed above or below the conclusion
9. **SEO meta** – meta title and description with SERP-length counters
10. **Review** – compile, edit, insert a table of contents, then save
11. **Evaluate & enhance** – quality checks against your chosen options

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

* **OpenAI API** (api.openai.com) — prompts, selected article content and generation settings are sent to generate text and images when an OpenAI model is selected, plus a model-list request used to populate the model picker and check that your key works. [Terms](https://openai.com/policies/terms-of-use) · [Privacy](https://openai.com/policies/privacy-policy)
* **Anthropic API** (api.anthropic.com) — the same data is sent when a Claude model is selected. [Terms](https://www.anthropic.com/legal/consumer-terms) · [Privacy](https://www.anthropic.com/legal/privacy)
* **Google Gemini API** (generativelanguage.googleapis.com) — the same data is sent when a Gemini model is selected. [Terms](https://ai.google.dev/gemini-api/terms) · [Privacy](https://policies.google.com/privacy)

Requests only happen when you run the wizard, Express mode, image generation, or open a screen that lists models or checks key status; no data is transmitted in the background.

== Installation ==

1. Install and activate the verified **Opace AI Hub** package. AI Scribe declares it as a required plugin, so WordPress refuses to activate AI Scribe until it is there. When Opace AI Hub is available from WordPress.org, the missing-dependency notice can install or activate it directly; until then, install the verified package supplied through Opace AI Hub's own release channel.
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

Whichever compatible models your configured provider account grants you. The list is fetched live and filtered by capability. Opace AI Hub's maintained registry is the fallback when live discovery is temporarily unavailable, so this readme does not make promises about volatile provider model names.

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

= 3.2.24 =
* Changed: the required hub plugin was renamed during WordPress.org review to Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini; the dependency slug, text-domain detection and all references now use `opace-ai-prompt-library-api-hub`.

= 3.2.23 =
* Fixed: the required Opace AI Hub slug, text-domain detection and one-click dependency route now match `opace-ai-prompt-library-api-hub`, the permanent WordPress.org submission slug.
* Improved: the missing-dependency notice enables its install button automatically as soon as WordPress.org resolves the approved Opace AI Hub listing.
* Documentation: linked the separately published Opace AI Hub source and verified GitHub release.
* Compatibility: tested up to WordPress 7.0.4.

= 3.2.22 =
* Improved: fresh provider defaults are now selected dynamically from each configured account: newest Terra, Claude Opus or non-Lite Gemini Flash for writing, with medium reasoning or thinking where supported.
* Improved: image defaults now prefer the newest GPT Image and Gemini Flash Image / Nano Banana families; the maintained offline fallbacks are GPT Image 2 and Nano Banana 2.
* Fixed: a valid model selected by the user remains authoritative, while a missing or retired choice is replaced by the best current model in the intended provider family.
* Fixed: the Opace AI Hub WordPress.org install button stays disabled until the dependency has a verified public listing, avoiding a dead install action before publication.
* Compatibility: tested up to WordPress 7.0.4.

= 3.2.21 =
* Improved: the eleven-step navigation stays locally scrollable and automatically reveals the active step; the Evaluate summary uses one, two or four columns according to its actual available width.
* Improved: manual Body and Review length improvement distributes concise additions across multiple existing sections, shows an accessible elapsed progress state and rejects oversized or concentrated output without changing the draft.
* Fixed: Start Again and topic changes cancel the visible improvement state and ignore late responses, preventing an old request from repainting a reset workflow.
* Fixed: Body and complete-article targets now use one exact allocation, the Body card explains reserved words, and untouched Settings display the effective 0.5 temperature rather than 0.7.

= 3.2.20 =
* Fixed: WordPress admin form controls now use border-box sizing and no inline margins, preventing full-width Wizard fields from overflowing the installed 375px admin canvas.
* Includes: the 3.2.19 responsive Wizard and admin layouts, safe Body and Review length improvement, provider-markup repair and meaningful generated-image caption handling.

= 3.2.19 =
* Fixed: all eleven Wizard steps, the Image Studio, Options & Prompt rail, Settings, Saved Shortcodes and Help now adapt to narrow admin canvases without crushed text or page-level horizontal overflow.
* Improved: Body and Review show the exact visible word count, preferred range and difference, with a guarded Improve length action that preserves the existing draft and owner edits.
* Fixed: implausibly long provider headings and whole-paragraph bold wrappers are normalised into readable paragraphs before editing, review and saving without changing the words or valid inline content.
* Improved: generated-image captions prefer a provider description or the specific visual subject; generic workflow labels and raw prompt instructions are never published as captions.

= 3.2.18 =
* Fixed: selecting a Custom per-article length in the real Wizard now reveals and focuses the target-word field and updates its planned range.

= 3.2.17 =
* Fixed: Express and Wizard now use the same rendered-visible word count, including the visible title and tagline; the reported owner fixture is exactly 1,716 words.
* Added: a retained under-target Express draft can be improved in one guarded request that adds useful detail without replacing existing copy; failed attempts keep the draft and can be retried.
* Added: Express now offers Save as Draft, Publish Post and Save as Shortcode using the exact visible article, while Review remains authoritative after refining in the Wizard.
* Fixed: invalid provider wrappers and bare text are normalised into semantic paragraphs so Express prose keeps one readable width and consistent spacing.

= 3.2.16 =
* Fixed: a successfully generated Express article that needs a word-count advisory now renders normally instead of Safari reporting an opaque object-not-found error.
* Fixed: shared idle and advisory cards use their results container's real parent, covering both nested Express results and the direct Wizard layout without repeating a provider request.

= 3.2.15 =
* Improved: Express reports the exact generated word count against the selected range, keeps provider fragments on one reading measure and retains a complete near-target draft as an advisory.
* Fixed: Custom article length reveals its target field; an amended prompt replaces the current result while Generate More remains the explicit append action.
* Improved: generated image captions wrap and remain editable, copied section prompts preserve placement, unmatched bulk images are never moved silently, and the featured preview is compact.
* Improved: saved articles receive scoped spacing and responsive figure markup; suggested category and tags are editable, capability-safe and saved with the current WordPress author.

= 3.2.14 =
* Fixed: a valid Wizard body or Express article that remains below its preferred word range after bounded improvement is retained instead of being discarded.
* Improved: the retained draft shows its actual word count, planned target, preferred range and shortfall as an advisory while structural outline loss remains a blocking error.
* Improved: article length is presented as one clear selector with ranges; Custom words appear only when Custom is selected, with consistent spacing in Settings, Wizard and Express.

= 3.2.13 =
* Added: generated images receive a concise automatic caption that can be edited or cleared, and bulk placement is available above and below the image results.
* Improved: Evaluate measures article length against the selected plan and separates TOC, internal and external links into actionable Pass or Check results; AI editorial judgements are labelled clearly instead of accumulating an ambiguous Could not verify total.
* Added: editing the current step prompt now exposes an explicit Run amended prompt action, with clear running, success and error feedback before generated content is replaced.

= 3.2.12 =
* Fixed: applying an in-range metadata suggestion keeps a compact success state and its Undo action visible instead of hiding Undo inside the resolved overlength panel.
* Fixed: manual edits, regeneration, keeping the original and undoing all clear stale metadata-optimiser state consistently.

= 3.2.11 =
* Fixed: when the first measured body correction still misses the unchanged depth gate, one final targeted correction starts from that latest draft and its recalculated word and section deficits.
* Safety: Wizard body and Express generation remain fail-closed with a hard maximum of three provider calls: the initial generation and no more than two bounded corrections.
* Accuracy: body and Express cost estimates now include both possible bounded corrective outputs.

= 3.2.10 =
* Fixed: the one permitted body correction now states the exact whole-body minimum, target, selected headings and measured per-section deficits, so providers cannot treat a vague expansion request as complete.
* Fixed: a failed correction reports the corrected draft's measured quality instead of stale first-draft evidence, while the unchanged helpfulness gate still fails closed after exactly two calls.
* Accuracy: pre-generation body and Express cost estimates include an allowance for their single bounded corrective rewrite.

= 3.2.9 =
* Added: Auto, Concise, Standard, In-depth and Custom article-length planning, with global defaults and per-article Wizard or Express overrides.
* Improved: helpfulness prompts set a sensible target from the topic, outline, keywords and Q&A, then require practical explanations, actions, examples, trade-offs and pitfalls where relevant.
* Safety: short, thin or structurally incomplete articles receive one bounded corrective expansion and then fail closed rather than advancing or saving; every selected outline heading must appear in order.
* Added: selecting more than three keywords shows a non-blocking quality warning, while every chosen phrase remains available to the article and metadata prompts.
* Added: overlength SEO metadata offers an optional model-assisted rewrite with a before-and-after review, Apply, Keep original and Undo; it preserves the exact primary keyword, natural secondary coverage and the required spaced pipe.
* Improved: Review previews the featured image separately while saved article HTML excludes it, preventing theme-level duplication. Saved posts also receive clean excerpts, useful slugs and intrinsic image dimensions where available.
* Fixed: empty editor spacer blocks and artificial height styles are removed before save; content images use lazy loading while the featured preview remains eager.
* Improved: Review warns about the default Uncategorized category and generic administrator attribution without inventing categories, biographies or credentials. Active SEO plugins retain ownership of author, social and structured metadata.
* Security: unfinished conversations and their generation, metadata, cost and save operations are isolated to the WordPress user who created them.

= 3.2.8 =
* Fixed: Express output always begins with the authoritative article title, removes provider-added title artefacts and uses one readable text measure throughout.
* Fixed: Wizard visits start clean and offer an explicit Resume action for recoverable work instead of silently reopening the last step.
* Fixed: real Gemini image requests retry transient 429 and 503 responses with visible wait, queue and failure states while keeping completed images.
* Fixed: SEO meta titles use sensible title case, preserve acronyms such as SEO and use the configured spaced pipe format without forced phrase stuffing.
* Accuracy: Evaluate now reports table-of-contents links, internal contextual links and external contextual links separately; anchor links cannot pass contextual-link checks.
* Accuracy: unverifiable editorial judgements are labelled Unknown, while images, headings and link facts come from the final Review HTML.
* Verified: full real-Gemini Wizard and Express runs, automatic featured-image creation, explicit recovery, draft persistence and factual Evaluate output were exercised in an isolated WordPress site.

= 3.2.7 =
* Fixed: every unique selected outline heading is now required in the generated body, while deselected headings are rejected before Review or saving.
* Improved: Review and Evaluate show truthful unsaved, draft, published and shortcode states, and warn when later edits make a saved copy out of date.
* Improved: finishing an unsaved project now requires an explicit discard confirmation instead of silently resetting the workflow.
* Improved: Image Studio presents generated results first, uses the Settings style presets, and shows clear per-image generation progress and retry states.
* Added: the first featured image generates and inserts once when the Article Body becomes ready, when image generation is configured and enabled.
* Added: generated images have editable captions that remain beneath figures through Review and WordPress saving.
* Fixed: redundant editor spacer blocks no longer create large blank gaps around images in Review or saved articles.

= 3.2.6 =
* Fixed: activating after a retained-data reinstall no longer resets writing style to Business or erases the selected model and thinking level on Opace AI Hub/Gemini installations.
* Improved: Express generation now keeps its named progress, elapsed time and provider context directly beneath the Generate controls where it remains visible.
* Improved: Settings now state the default data-retention behaviour plainly and warn before users opt into destructive uninstall cleanup.
* Improved: save confirmation names the selected writing style and model thinking level so users can verify the values that were stored.

= 3.2.5 =
* Added: keyword suggestions now show Primary, Supporting or Long-tail roles and clearly qualified Low, Medium, High or Unknown AI-estimated search-demand bands.
* Added: every keyword opens directly in Google Trends, with a five-year comparison action for up to five selected phrases.
* Accuracy: qualitative demand bands are always labelled as unverified AI estimates; numeric search volume is never invented and downstream article prompts receive only the selected keyword phrases.

= 3.2.4 =
* Improved: SEO metadata now checks every selected keyword in both fields, distinguishing exact, intelligently combined, partial and absent coverage.
* Improved: the primary keyword is required exactly in both fields, while secondary phrases can share overlapping terms naturally without repetition.
* Improved: metadata titles now use and visibly validate the required spaced pipe separator.

= 3.2.3 =
* Fixed: the Q&A question and answer editors now fill the available card width instead of inheriting generic 32rem and 48rem form caps.
* Fixed: saved model parameters no longer repaint an older value after refreshing the model list on the same Settings page.
* Safety: owner and development installs cannot serve automated mock fixtures; mock mode now requires two explicit test-only flags.

= 3.2.2 =
* Fixed: real Gemini image models no longer disappear behind a stale model list left by the development mock provider.
* Added: live Gemini image discovery and routing recognises Gemini Image, Imagen 4 and Nano Banana models exposed to the configured account.

= 3.2.1 =
* Fixed Start Again and changed-topic resets leaving later wizard steps visually blank instead of generating fresh results.
* Fixed Express generation failing before its progress card could render when its skeleton was nested inside the output article.
* Express now shows the shared named-stage progress, elapsed time, provider context, long-wait guidance and retryable error state.

= 3.2.0 =
* Fixed: familiar acronyms such as SEO retain their correct capitals, and time-sensitive title requests receive the verified current date and explicit current-year guidance.
* Fixed: keyword cards no longer display invented model estimates as search-volume data; their AI-only provenance is explicit and consistent.
* Changed: normal tagline generation returns and selects one article-specific tagline; Try another replaces it instead of accumulating duplicates.
* Improved: title, keyword and tagline choices are compact, clearly selectable and explain the selection required to continue, with keyword bulk controls.
* Improved: Introduction and Conclusion use the full result width and can be edited directly before continuing.
* Improved: the prompt rail stacks at constrained desktop widths, and an unavailable Image Studio becomes a compact full-width notice instead of squeezing the editor.
* Fixed: Body generation is limited to outline sections and no longer repeats the separately compiled H1, introduction or tagline.

= 3.1.9 =
* Fixed: returning to Review and continuing now always refreshes Evaluate from the latest text, links and images instead of showing an older report.

= 3.1.8 =
* Fixed: Evaluate snapshots the active visible Review editor, avoiding stale editor instances when measuring inserted images.

= 3.1.7 =
* Fixed: Evaluate column headings now align with their row text, including status labels and suggested actions.

= 3.1.6 =
* Fixed: Evaluate snapshots the exact Review article before leaving that step, preventing hidden-editor repainting from dropping inserted images during generation.

= 3.1.5 =
* Fixed: Evaluate captures the visible Review editor DOM at the moment it runs, ensuring inserted images are included even when an editor export or cached selection is stale.

= 3.1.4 =
* Fixed: Evaluate, save and publish now consume the exact visible Review editor HTML, preventing Quill's semantic exporter from dropping inserted image embeds.

= 3.1.3 =
* Fixed: intelligently placed images now occupy their own editor line instead of inheriting a section heading's format and becoming nested inside the heading.

= 3.1.2 =
* Fixed: changing the article topic after restoring an earlier session now starts a clean conversation, clears the old prompt and results, and prevents late recovery responses from repainting stale content.

= 3.1.1 =
* Fixed: Evaluate now checks the exact edited Review article, including inserted images, and presents measured facts, evidence and actions in a readable responsive report with unobscured sticky headings.
* Fixed: generated images no longer acquire automatic captions. Image Studio now shows the prompt used, supports article-only style overrides, individual regeneration, intelligent placement, removal and replacement.
* Fixed: SEO title and description fields are editable and persist into Review and save/publish flows, with factual character and keyword guidance.
* Improved: Q&A editors use the available width and include Select all/Deselect all with an accurate selected count.
* Improved: generation progress reports truthful stages and elapsed time without inventing a percentage, while save, publish and settings outcomes remain visible in a viewport-fixed notification centre.

= 3.1.0 =
* New: a spelling setting. Choose British or American English in Settings, and every step is told which to use. British is the default, and the instruction is applied in all three writing modes.
* New: fresh installs now start in Humanizer mode rather than Standard. An existing choice is never overwritten.
* New: one set of hard rules is applied after the writing mode and before your own Custom Instructions, in every mode: the spelling choice, no em dashes, no banned words, and keyword phrases woven into sentences in sentence case rather than dropped in mid-sentence in Title Case.
* Fixed: the system message once again opens with the current year, as it did in 2.6, so the model knows when it is writing. The year was missing from the wizard, the engine actions and the humanise ability.
* Fixed: the Humanizer instructions told the model to include grammatical errors, vary punctuation inconsistently and add stray spaces, which worked against your own writing rules. Natural rhythm, humour, tangents and personal anecdotes are kept; the licence to make mistakes is gone.
* Fixed: reactivating the plugin overwrote edited prompts and settings with the shipped defaults. Seeding now fills only what is missing.
* Fixed: the writing mode text existed in two places that had drifted apart, and the personality mode was stored under two different names.
* Fixed: live Gemini 3.x generations failed with an HTTP 400 — the thinking-level option is now sent in the format Google accepts.
* Fixed: every generation is routed through the Opace AI Hub, so usage, cost and error statistics are recorded correctly. On upgrade, keys held locally are handed to the hub (encrypted at rest).
* Fixed: selected keywords now persist and reach your SEO plugin as the focus keyword; AIOSEO v4 support rewritten; publishing after saving a draft updates the same post instead of creating a duplicate.
* Fixed: with no SEO plugin active, the meta title and description now render on the front end and the SEO step says where they were saved.
* New: image prompts are auto-written from each section and editable before generating; drag-and-drop insertion with visible drop zones; delete and replace controls on inserted images; the first image is set as the featured image and added to the article.
* New: step progress with elapsed time everywhere (including Express), visible success or error feedback on every save, publish and settings action.
* New: Q&A returns individually selectable question and answer boxes; meta title and description are editable with SERP-length counters; the evaluate report is a structured table with clear statuses.
* Improved: custom instructions are seeded on fresh installs, wizard state survives a page reload, settings screens work at mobile widths, and uninstall cleanup removes everything it should.

= 3.0.9 =
* Improved: the welcome notice's "Install Opace AI Hub" button now performs the one-click install directly, instead of sending you to a plugin search page.

= 3.0.8 =
* Tested against WordPress 7.0.4.
* New: when Opace AI Hub is missing, the notice now offers a one-click **Install Opace AI Hub now** button. WordPress does not install a dependency for you — it only blocks activation and prints a notice — so this saves going to find it manually.
* New: the model list is fetched live every time the settings screen opens, instead of serving an hour-old cache until you noticed and pressed Refresh. Providers you have configured are listed first.
* New: adding an API key now records a sensible default model for both text and images, chosen from that account's own model list. Change either afterwards; a model you pick is never overwritten.
* Fixed: Google Gemini could not generate anything. The response schema AI-Scribe builds was discarded before the request was sent, so every step came back as prose and failed with "Response was not valid JSON".
* Fixed: the default model came from the bundled registry rather than your account, so a site with only a Gemini or Anthropic key was pointed at a model it did not have — or at OpenAI, failing with "OpenAI API key not configured" on the very first step. The default is now chosen from your live model list.
* Fixed: model lists sorted release dates as version numbers, so "deep-research-pro-preview-12-2025" ranked as version 12 and sat above Gemini 3.6. Lists are now genuinely newest-first, and a provider's side families (Gemma, Imagen) no longer outrank its main line.
* Fixed: speech, audio, embedding and tooling models appeared in the article-model picker and could be selected to write with. Only text models are offered for text, and only image models for images.
* Fixed: models that answer in Markdown had their "###" headings stored verbatim, so a published article was one unbroken block of text with no headings at all. Markdown responses are converted to HTML; providers that already return HTML are untouched.
* Fixed: Q&A questions and answers returned inside HTML tags were escaped and printed the tags on screen as literal text.
* Fixed: saving settings gave no visible confirmation. The only feedback was a screen-reader-only live region, so clicking Save appeared to do nothing.
* New: a running "still working" indicator with elapsed seconds on every generating step and on Express, so a slow model can be told apart from a stuck one.
* Fixed: image generation kept requesting an OpenAI image model after the site moved to a different provider, failing every time with "OpenAI API key not configured for image generation". A saved image model is now only used while its provider still holds a key.
* Fixed: image controls were offered whenever a provider could generate images in principle. Google grants image models per account, so the controls are now gated on your account's own model list and hidden with an explanation when it holds none.
* Fixed: the wizard could display a different model from the one about to be billed — "GPT-5 · OpenAI" on a site holding only a Gemini key. Model resolution, ordering and display now come from one place.
* Changed: the Opace AI Hub library is no longer duplicated inside this plugin. The hub's copy always won, so the bundled copy was dead code that could silently receive a fix and do nothing — which is exactly how Gemini shipped unable to generate.
* Images no longer require OpenAI. Google Gemini's image models are used when a Gemini key is present, and the best available image model is chosen from whichever providers you have configured.
* On a site where no configured provider can generate images, the Add Image and Bulk Add controls are hidden and replaced with an explanation of which key to add. Previously they were offered and then failed.
* Fixed: a failed image generation reported its error over the whole step, discarding the article you had just written. Image failures are now reported at the gallery and leave the article intact.
* Fixed: provider key validation could not fail. A key was accepted as working whenever a model list could be produced, but the list falls back to a bundled registry when the provider call fails, so an invalid key reported as valid. Validation now requires a live answer from the provider, and only a genuine authentication failure marks a key as bad.
* Fixed: structured-output requests to OpenAI dropped the response format, so schema-enforced steps came back as free prose and then failed to parse.
* Fixed: Claude requests never forwarded the tool definition used to enforce a response schema, and the tool result was discarded when it did arrive.
* Fixed: image requests sent parameters that only some models accept, so image generation failed outright on newer models.
* Fixed: a hidden model override could send a step to a different model than the one shown in the picker.
* Fixed: a saved shortcode could repeat the article headline where the body already contained it.
* xAI / Grok has been removed from the interface. It was never tested end to end and is not claimed as supported.

= 3.0.7 =
* Fixed: generation failed outright on newer OpenAI models with "Unsupported parameter: 'max_tokens'". Each model's request is now built from its own documented contract — the right token parameter, and sampling parameters only where the model accepts them.
* Fixed: Claude 5, Gemini 3.x and the OpenAI reasoning models reject sampling parameters. Sending them returned an error on every request; they are no longer sent to models that refuse them.
* Fixed: the Temperature setting never reached the provider. It was saved under one name and read under another, so the value was silently ignored.
* Fixed: Top P was dropped for every model. The control is now declared per model and the value is actually sent.
* Fixed: reloading the page mid-article cleared the Review step while Save and Publish stayed enabled, so one click could publish an empty post. Reloading now restores the article and every selection you had made.
* Fixed: edits and images added in the body editor never reached the finished article.
* Fixed: "Start Again" left the previous article's conversation in place, so a new topic continued the old article. Opening the wizard in a second tab could overwrite the first tab's work.
* Fixed: steps could be opened before the previous one had run, showing a blank panel and the prior step's prompt.
* Fixed: pressing Generate repeatedly during a run fired a request each time and billed for all of them.
* Fixed: the quality evaluation table rendered its verdict and reasoning columns at zero width, so the assessment was unreadable.
* Fixed: the table of contents linked to headings that carried no matching ids, so its links went nowhere on posts, pages and shortcodes.
* Fixed: a saved shortcode printed the article title twice as a heading.
* Fixed: Custom Instructions are now applied to generation. In 2.6.2 the text was saved but never used.
* Changed: Opace AI Hub is now required. Provider keys, model lists, pricing and usage statistics live there, and AI-Scribe's own key fields have been removed so there is one place to configure a provider.
* Changed: your prompts are migrated into Opace AI Hub's Prompt Library on upgrade and can be managed alongside every other add-on's. Your existing prompts are copied, not moved, and remain untouched as a backup.
* Accessibility: the keyboard focus ring now meets the 3:1 minimum (it measured 1.63:1), hint text and status chips meet AA, and the completion of each generation is announced to screen readers.

= 3.0.6 =
* Fixed: tick and step icons sat a few pixels down and to the right of centre. Icon SVGs were rendering at their intrinsic 24px inside smaller containers and overflowing; all 65 icons on the wizard are now correctly centred and the selection tick is crisper.
* Design: rebuilt the dark theme on a neutral graphite palette. The old scheme washed every screen in a saturated navy gradient and drew cards darker than the panels behind them, so they read as holes rather than raised surfaces.
* Design: the blue palette is now a single coherent scale. Mid-tones were previously a different hue from the rest of the ramp, so gradients and hover states shifted colour part-way through.
* Design: primary buttons, the progress bar and panel headers are flat and solid instead of gradient-filled — steadier, and consistent between light and dark.
* Mobile: the header no longer stacks into a tall centred column; logo, mode switch, progress and cost meter now fit a compact three-row layout so the first field is visible without scrolling.
* Mobile and tablet: the step rail shows that it scrolls. The next step peeks past the edge with a fade, and the fade clears when you reach the end.
* Responsive: added layouts for the 769–1400px range and a maximum width on very wide screens; previously only 480 and 768 were handled.
* Settings: field widths now suit their content (a token count is no longer as wide as the page), the screen has a readable maximum width, and sections are visually grouped.
* Accessibility: fixed three text-contrast failures against WCAG AA (the version chip, the model settings link and in-app links, which were inheriting WordPress's default link blue).

= 3.0.5 =
* Fixed: tick and step icons sat a few pixels down and to the right of centre. Icon SVGs were rendering at their intrinsic 24px inside smaller containers and overflowing; all 65 icons on the wizard are now correctly centred and the selection tick is crisper.
* Design: rebuilt the dark theme on a neutral graphite palette. The old scheme washed every screen in a saturated navy gradient and drew cards darker than the panels behind them, so they read as holes rather than raised surfaces.
* Design: the blue palette is now a single coherent scale. Mid-tones were previously a different hue from the rest of the ramp, so gradients and hover states shifted colour part-way through.
* Design: primary buttons, the progress bar and panel headers are flat and solid instead of gradient-filled — steadier, and consistent between light and dark.
* Mobile: the header no longer stacks into a tall centred column; logo, mode switch, progress and cost meter now fit a compact three-row layout so the first field is visible without scrolling.
* Mobile and tablet: the step rail shows that it scrolls. The next step peeks past the edge with a fade, and the fade clears when you reach the end.
* Responsive: added layouts for the 769–1400px range and a maximum width on very wide screens; previously only 480 and 768 were handled.
* Settings: field widths now suit their content (a token count is no longer as wide as the page), the screen has a readable maximum width, and sections are visually grouped.
* Accessibility: fixed three text-contrast failures against WCAG AA (the version chip, the model settings link and in-app links, which were inheriting WordPress's default link blue).

= 3.0.3 =
* New: a one-time, dismissible welcome notice after updating explains what changed in 3.0 and links to the built-in guide; it never re-appears once dismissed.
* Upgrade: API keys carried over from 2.6.x are now encrypted at rest during the one-time migration (they previously remained in the older storage format until re-saved).
* Upgrade: if your previously selected model has been retired by its provider, the replacement is chosen automatically and a visible notice tells you what changed.
* Verified: the complete 2.6.2 → 3.0 update path is now exercised end-to-end in an automated test (settings, edited prompts, custom languages, saved shortcodes and API keys all survive; published shortcodes keep rendering).

= 3.0.2 =
* Security: provider API keys are now encrypted at rest for every provider (AES-256-CBC, key derived from your site's salts) with a versioned storage format and fail-closed decryption.
* Security: capability checks added to every AJAX endpoint alongside the existing nonce checks; twenty unused legacy endpoints removed entirely.
* Security: all database queries now run through $wpdb->prepare() with identifier placeholders; table creation uses dbDelta().
* Compliance: text domain aligned with the wordpress.org plugin slug so language packs load correctly; zero Plugin Check errors on the packaged build.
* Compliance: External Services section added to this readme; diagnostic logging is now fully debug-gated and silent in production.
* Fixed: plugin name typo ("Humaizer" → "Humanizer").
* Requires WordPress 6.2+ (identifier placeholders in prepared statements).

= 3.0.1 =
* Settings de-duplication: with the Opace AI Hub active, provider keys are managed centrally — AI-Scribe shows per-provider status chips and links to Opace AI Hub settings.
* Provider status now reflects a validated key (cheap live check, cached), not mere key presence.
* Model parameter panels: family-aware controls for current models — reasoning effort for o-series/GPT-5, extended thinking with budget for Claude 4.x — plus honest output-token caps and larger defaults for long-form steps.
* Cost meter and estimator source Opace AI Hub pricing and recorded actuals; the stale hardcoded pricing endpoint is removed.
* Removed the last legacy v4 script modules; the wizard model display hydrates from the live settings endpoints.
* Design polish: rebalanced wizard layout, stronger step chips and progress bar, working dark-mode theme toggle, full dark-mode audit of every admin screen.

= 3.0.0 =
* Complete rebuild: conversation-threaded generation — every step sees the full article so far, ending blind-written conclusions, Q&A and meta.
* New Express mode: whole article in one structured API call, refinable in the wizard afterwards.
* New provider: Google Gemini alongside OpenAI and Anthropic, plus optional WordPress 7 core AI client support.
* Live model lists fetched from each configured provider with per-model parameter panels (reasoning effort, thinking level) — no more stale hardcoded model lists.
* Cost transparency: pre-generation estimates and actual spend per step, with a running article total.
* Humanizer and Humanizer with Personality modes restored as a first-class writing-mode setting.
* Image workflow: background generation from the keyword step, bulk add per section, Auto size, quality/format/background options and twenty style presets.
* Structured outputs with validation for choice steps — a failed response never advances the wizard or wastes tokens.
* Server-side prompt assembly: placeholders resolved before sending, prompt editable per run.
* Honest generation parameters: temperature and top-p pass through unmodified (the old hidden multipliers and stop sequences are gone).
* SEO meta always stored with the post, and written to whichever of Yoast, AIOSEO, Rank Math or SEOPress is active.
* Save as Draft, Publish, and Save as Shortcode from the review step; rebuilt Saved Shortcodes and Help screens.
* WordPress 7 Abilities API registration for AI-driven site tooling.
* Opt-in uninstall cleanup; accessibility overhaul (keyboard navigation, live regions, dark mode).
* Security: API keys are write-only server-side and never sent to the browser.

= 2.6.2 =
* Fixes for image generation timeouts and model list updates.

= 2.6.1 =
* Minor fixes.

= 2.6 =
* GPT-4.5 support, early parallel image generation, live pricing.

(Full historical changelog available in earlier releases.)

== Upgrade Notice ==

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
Images work with Gemini as well as OpenAI, key validation is now honest, and several provider request faults are fixed. Tested on WordPress 7.0.3.

= 3.0.0 =
Major rebuild: threaded generation, Express mode, Gemini support, live model lists and cost tracking. Settings and saved shortcodes migrate automatically.
