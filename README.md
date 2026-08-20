# AI Scribe

<p align="center">
  <img src=".github/social-preview.png" width="960" alt="AI Scribe for WordPress — guided AI article creation with OpenAI, Anthropic and Google Gemini">
</p>

<p align="center"><strong>SEO content creator and humaniser for WordPress, powered by OpenAI, Anthropic and Google Gemini.</strong></p>

<p align="center">
  <img src="https://img.shields.io/badge/version-3.2.23-087abd" alt="AI Scribe version 3.2.23">
  <img src="https://img.shields.io/badge/WordPress-6.5%2B-21759b" alt="Requires WordPress 6.5 or newer">
  <img src="https://img.shields.io/badge/tested%20up%20to-WordPress%207.0.4-21759b" alt="Tested up to WordPress 7.0.4">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4" alt="Requires PHP 7.4 or newer">
  <img src="https://img.shields.io/badge/licence-GPL--3.0-2ea44f" alt="GPL 3.0 licence">
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/">WordPress.org</a>
  · <a href="https://github.com/OpaceDigitalAgency/ai-core-integration-hub-prompt-engine-wordpress-plugin">Required Opace AI Hub</a>
  · <a href="https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator/issues">Issues</a>
  · <a href="SECURITY.md">Security</a>
  · <a href="https://github.com/OpaceDigitalAgency">More from Opace</a>
</p>

> **AI Scribe + [Opace Opace AI Hub](https://github.com/OpaceDigitalAgency/ai-core-integration-hub-prompt-engine-wordpress-plugin) are one companion WordPress AI stack, installed as two separate plugins.**
> AI Scribe provides the writing and publishing workflow; Opace AI Hub provides encrypted provider keys,
> live model discovery, shared prompts, usage and cost reporting. Install
> [Opace AI Hub](https://github.com/OpaceDigitalAgency/ai-core-integration-hub-prompt-engine-wordpress-plugin/releases/latest)
> first, then AI Scribe. They remain separate packages so Opace AI Hub can also support other compatible plugins.

**Compatibility:** requires WordPress 6.5 or newer and has been tested up to **WordPress 7.0.4**.

AI Scribe writes long-form, search-optimised articles inside WordPress. An eleven-step wizard takes an
idea through titles, keywords, outline, body, conclusion, Q&A and SEO meta; Express mode starts with one
structured whole-article generation. Optional length improvements are separate requests. Output goes to a draft, a published post, or a saved shortcode you can drop into
any page builder.

---

## What changed in version 3

Version 2.6.2 sent each step to the model as an isolated request. The conclusion had never seen the
body; the Q&A had never seen the conclusion. Version 3 is a rebuild around a single idea: **the model
should always see the whole article.** Every step runs inside one conversation thread.

The other structural change is modularity. What used to be one plugin is now two.

| | 2.6.2 | 3.0 |
|---|---|---|
| Provider config | Per-plugin API key fields | Central, in Opace AI Hub |
| Model list | Hardcoded, went stale | Fetched live from your account |
| Step context | Each step blind to the others | One threaded conversation |
| Whole-article mode | — | Express mode, one structured starting request |
| Structured responses | Free text, parsed hopefully | Provider-native schemas, validated |
| Cost | Unknown until the invoice | Estimated before, actual after, running total |
| Custom Instructions | Saved, never sent | Applied to every step |
| Temperature / top-p | Hidden multiplier applied | Passed through untouched |
| Keys at rest | Plain text | AES-256-CBC, fail-closed |
| Providers | OpenAI, Anthropic | OpenAI, Anthropic, Google Gemini |
| Images | OpenAI only | OpenAI or Gemini, per availability |

---

## Architecture

```
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
```

AI Scribe declares `Requires Plugins: opace-ai-prompt-library-api-hub`, so WordPress refuses activation without the hub. If
Opace AI Hub is deactivated later, AI Scribe unhooks itself — no menu, no endpoints — and shows a notice
with a reactivation button.

### Why split it

- One set of keys serves every plugin on the hub, rather than one set per plugin.
- Model lists come from your own provider account, so a new model is usable the day you get it.
- A provider bug is fixed once, in the hub, not once per plugin.
- Usage and spend are recorded in one place across every add-on.

### Source layout

The Opace AI Hub library is **not** duplicated here. The hub's plugin directory sorts first, so its copy
always won and a bundled copy would be dead code that could silently receive a fix and do nothing.
There is one copy, in the hub.

```
article_builder.php          bootstrap, constants, Requires Plugins header
includes/
  core/                      config manager, migration service
  adapters/                  Opace AI Hub adapter, WP 7 core AI client adapter
  ajax/                      endpoints — every one nonce + capability checked
  services/                  generation, conversation, images, cost, schemas,
                             prompts, post output, shortcodes, admin,
                             model resolver (single source for which model runs)
  prompts/                   built-in per-step prompt defaults
  abilities/                 WordPress 7 Abilities API registration
assets/js/
  controllers/               wizard flow, settings, express
  views/steps/               one view per step
templates/                   admin screens
scripts/                     release builder and public architecture smoke check
```

---

## Providers and models

Model lists are fetched from each configured provider account and cached for an hour, with a Refresh
that bypasses the cache. AI Scribe filters those lists by capability, preserves a valid saved choice,
and uses Opace AI Hub's maintained registry only when live discovery is unavailable. This avoids promising
model names which a provider may rename, retire or withhold from a particular account.

| Provider | Text | Images |
|---|---|---|
| **OpenAI** | Models exposed by the configured account and recognised as text-capable | Image-capable models exposed by the account |
| **Anthropic** | Models exposed by the configured account and recognised as text-capable | none — Anthropic does not provide an image model |
| **Google Gemini** | Models exposed by the configured account and recognised as text-capable | Image-capable models exposed by the account |

### Any combination works

| Keys configured | Text | Images |
|---|---|---|
| OpenAI only | OpenAI | OpenAI |
| Gemini only | Gemini | Gemini, when the account has an image model |
| Anthropic only | Anthropic | **not available** — controls hidden, with an explanation of which key to add |
| Anthropic + OpenAI | either | OpenAI |
| Anthropic + Gemini | either | Gemini |
| All three | per step | best available |

Nothing forces one provider across an article. Each model gets a parameter panel built from its own
capability schema — reasoning or extended-thinking controls only where the selected model exposes
them, and temperature or top-p only where the provider accepts them.

Key status is a live check. AI Scribe proves the key against the provider before reporting it as
working, and only a genuine authentication failure marks a key as bad.

## Current writing workflow

- **Article length that remains honest.** Choose Auto, a preset or a custom target. Body and Review
  show the measured count, target, preferred range and shortfall. A retained under-target draft can
  be improved without replacing existing wording; additions must be balanced across sections.
- **Useful keyword signals without invented numbers.** Keyword cards label their role and qualitative,
  unverified AI demand band. Per-keyword and selected-keyword Google Trends links let you check relative
  interest and seasonality. AI Scribe never presents guessed monthly volumes as measured data.
- **Image Studio.** The featured image is created visibly when image generation is enabled. Generate
  or retry section images, inspect and amend each prompt, edit or remove automatic captions, use
  article-only style overrides, and place or replace images without silently appending unmatched ones.
- **Explicit prompt changes.** Editing the visible prompt does nothing until you choose **Run amended
  prompt**. The existing result stays in place if that request fails.
- **Save confidence.** Review and Evaluate show whether the current article has been saved as a draft,
  published post or shortcode, and whether later edits have made that saved copy stale.
- **Evidence-led evaluation.** Structural checks are measured from the final Review HTML. Table-of-
  contents links, internal contextual links and external links are reported separately; editorial
  judgements are labelled for human confirmation rather than presented as measured facts.

## Product tour

<table>
  <tr>
    <td width="50%"><img src=".wordpress-org/screenshot-1.png" alt="AI Scribe title-generation step with live model, prompt and cost information"><br><strong>1. Start with an idea.</strong><br>Generate and select a title in the guided eleven-step workflow.</td>
    <td width="50%"><img src=".wordpress-org/screenshot-2.png" alt="Keyword Research cards with qualitative demand bands and Google Trends links"><br><strong>2. Research without invented metrics.</strong><br>Review keyword roles and AI-estimated demand, then verify relative interest in Google Trends.</td>
  </tr>
  <tr>
    <td width="50%"><img src=".wordpress-org/screenshot-3.png" alt="Article Outline step with selectable proposed headings"><br><strong>3. Control the structure.</strong><br>Select, deselect and expand the headings that the article must cover.</td>
    <td width="50%"><img src=".wordpress-org/screenshot-4.png" alt="Article Body editor with measured word target and Image Studio"><br><strong>4. Write and illustrate.</strong><br>Edit the body, measure it against the selected target and manage generated images beside the article.</td>
  </tr>
  <tr>
    <td width="50%"><img src=".wordpress-org/screenshot-5.png" alt="Questions and Answers step with editable full-width fields and bulk selection"><br><strong>5. Refine Q&amp;A.</strong><br>Edit every answer and include only the questions that help the reader.</td>
    <td width="50%"><img src=".wordpress-org/screenshot-6.png" alt="Editable SEO meta title and description with measured guidance"><br><strong>6. Check SEO metadata.</strong><br>Edit the title and description with measured length and keyword guidance.</td>
  </tr>
  <tr>
    <td width="50%"><img src=".wordpress-org/screenshot-7.png" alt="Express mode completed article with exact word-count status and save actions"><br><strong>7. Use Express when speed matters.</strong><br>Generate a complete first draft in one structured request, then optionally improve its length and save it directly.</td>
    <td width="50%"><img src=".wordpress-org/screenshot-8.png" alt="Evaluate screen with summary cards and an evidence-led quality table"><br><strong>8. Evaluate the final HTML.</strong><br>Separate measurable structural facts from editorial checks that need human judgement.</td>
  </tr>
</table>

### Fresh-install defaults

AI Scribe starts with Auto article length, English, Business style, Professional tone, Humanizer,
British spelling, five H2 sections, Q&A, table of contents, hyperlink suggestions and keyword emphasis
enabled. Image generation and parallel image processing are enabled with the Photorealistic preset;
the controls remain unavailable until a configured provider exposes a compatible image model.

AI Scribe does not seed one provider's model into every installation. A valid saved choice always
wins. When a choice is missing or has been retired, Opace AI Hub ranks the configured account's live list
inside the intended provider family:

| Provider | Writing default | Image default |
|---|---|---|
| **OpenAI** | newest mainstream **Terra** model, medium reasoning (current registry fallback: `gpt-5.6-terra`) | newest **GPT Image** model (current fallback: `gpt-image-2`) |
| **Anthropic** | newest **Claude Opus** model, medium effort (current fallback: `claude-opus-5`) | none |
| **Google Gemini** | newest non-Lite **Gemini Flash** model, medium thinking (current stable fallback: `gemini-3.6-flash`) | newest **Gemini Flash Image / Nano Banana** model (current fallback: `gemini-3.1-flash-image`, Nano Banana 2) |

Those identifiers describe the maintained offline fallback, not a promise that every account exposes
the same list. Live discovery takes precedence, so a later model in the same preferred family becomes
the default without a plugin release. Temperature and top-p begin at 0.5 only where the selected
model accepts them; model-specific capability rules suppress unsupported controls. Reactivation and
retained-data reinstalls fill missing settings only and never overwrite a valid saved choice.

---

## Token and cost behaviour

- **Threaded context.** Later steps are not re-fed the text they already hold, and stop repeating what
  the introduction said.
- **Provider-native schemas.** JSON schema on OpenAI and Gemini, a forced tool call on Claude. A choice
  step returns parseable options first time rather than prose that has to be re-requested.
- **No double billing.** A malformed response never advances the wizard; repeated Generate presses
  during a run no longer fire a request each time.
- **Express mode.** One structured starting request instead of eleven step requests; optional improvements are separate.
- **A visible meter.** Estimated spend before a step, actual after, running total for the article.
  Pricing is supplied by Opace AI Hub. When trustworthy pricing is unavailable, the interface says
  **Cost unavailable** rather than recording a misleading zero.

---

## Quality and security

- Every generation endpoint requires the `edit_posts` capability and a valid WordPress nonce.
- Provider credentials live in Opace AI Hub and are never rendered into an AI Scribe page.
- Unknown model pricing is shown as **Cost unavailable**, never as a false zero.
- `php scripts/smoke.php` checks the public architecture and dependency contract without contacting a provider.
- Security reports can be submitted privately through the repository's [Security policy](SECURITY.md).

---

## Installation

1. Install and activate the verified [**Opace Opace AI Hub** package](https://github.com/OpaceDigitalAgency/ai-core-integration-hub-prompt-engine-wordpress-plugin/releases/latest).
2. In Opace AI Hub → Settings, add a key for at least one provider and press Test.
3. Install and activate [**AI Scribe**](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator), following the current repository instructions.
4. AI Scribe → Settings → Providers & Model: confirm the status chip, pick a model, then open
   Generate Article.

Upgrading from 2.6.x is a normal update, but Opace AI Hub must be active first. Prompts, settings, custom
languages, saved shortcodes and keys migrate on the first admin page load; keys are encrypted at rest
during that migration; edited prompts are **copied** into Opace AI Hub's library, never moved.

---

## Companion plugin

- [**Opace Opace AI Hub**](https://github.com/OpaceDigitalAgency/ai-core-integration-hub-prompt-engine-wordpress-plugin) — the required provider, model, prompt, usage and pricing hub.
- [**AI Scribe**](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator) — the article creation, SEO, image, review and WordPress publishing workflow.

The projects are developed and released together as a companion stack, but installed from separate
ZIP packages. This avoids duplicating credentials and provider code inside AI Scribe and lets other
WordPress plugins share the same Opace AI Hub configuration.

---

## Capabilities

| Screen | Capability |
|---|---|
| Wizard, Express, Help | `edit_posts` |
| Settings, Saved Shortcodes | `manage_options` |

Every generation endpoint enforces `edit_posts` and a nonce. There are no logged-out endpoints.

---

## Privacy

Prompts and article content go directly from your site to the provider you configured. Nothing is
routed through Opace. Provider terms and billing are between you and them.

---

## Licence

GPL-3.0. © [Opace Digital Agency](https://opace.agency/services/web-design/wordpress-development/).
