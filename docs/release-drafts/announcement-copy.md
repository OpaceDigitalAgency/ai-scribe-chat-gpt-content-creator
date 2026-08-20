# Draft announcement copy for AI-Scribe 3.2.22

> Publication draft only. Use after the exact 3.2.22 package has been uploaded
> to the intended channel and that public version has been verified. Do not
> describe local or packaged evidence as a public release.

## Short version

AI-Scribe 3.2.22 gives WordPress publishers two clear routes from idea to
article: an editable 11-step Wizard and a faster Express draft. It works with
usable OpenAI, Anthropic and Google Gemini text models exposed through Opace AI Hub,
so the model list follows the providers configured on the site rather than a
hard-coded dropdown.

This release adds per-article length targets, exact visible word counts and a
balanced Improve length action that keeps the existing draft if improvement
cannot finish safely. Keyword suggestions now show honest qualitative demand
bands with Google Trends links instead of invented search-volume numbers.

## Longer version

AI-Scribe 3.2.22 is a substantial update to the WordPress article workflow.
The Wizard keeps every selected title, keyword, outline section, introduction,
tagline, body section, conclusion, Q&A item and SEO field available for review.
Express can produce a complete draft first, then hand it into the same editing
and saving workflow.

Writers can choose an automatic, preset or custom article-length target. The
Body and Review screens measure the visible article with one canonical counter,
show the preferred range and retain useful drafts that finish short. A manual
Improve length action asks the configured provider for balanced additions
across several sections; it does not silently replace the existing article.

The current Image Studio automatically creates an editable caption, records
the prompt used, supports article-level style overrides, regenerates individual
images and places generated images beside their intended sections. Draft,
publish and shortcode actions report what was actually saved, while Evaluate
separates measured HTML facts from editorial judgements that still need a
person's confirmation.

AI-Scribe uses Opace AI Hub for configured providers, live model discovery, request
accounting and pricing evidence. If a model's price cannot be verified, the UI
reports that cost is unavailable instead of displaying a false zero.

## Claims to verify before publication

- The public package and displayed version are exactly 3.2.22.
- The public screenshots show the current Wizard, Express, Image Studio,
  Review and Evaluate interfaces.
- Upgrade and fresh-install paths have been checked against the published ZIP.
- Any provider-quality claim is backed by a deliberate real-provider test;
  deterministic fixtures alone are not described as provider acceptance.
- No fixed model name is promised: available models depend on the configured
  provider account and its current capabilities.
