# Public GitHub sync plan

Target: `OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator`.

The public repository still presents the 2.x monolith and a 2.6 README. The local AI-Scribe v3
repository has no configured remote. Updating only the public README would create a misleading mix of
current product claims and obsolete source.

## History plan

1. Freeze and test the exact AI-Scribe release candidate.
2. Preserve the former public default branch as `legacy-2.x`.
3. Build a clean public snapshot from an explicit allow-list instead of publishing the private
   development tree or its internal history.
4. Scan that snapshot for keys, tokens, browser state, local paths and customer data.
5. Replace public `main` with the reviewed snapshot and tag the exact public version.
6. Update the repository description, topics and social preview separately in GitHub settings.

The public commit should be reproducible from the reviewed local candidate, while private release
notes, research, compliance working papers and local QA artefacts stay outside the public history.

## Public repository contents

Publish only:

- the WordPress runtime (`article_builder.php`, `uninstall.php`, `includes/`, `templates/`, `assets/`);
- `README.md`, `readme.txt` and the public API/brand documentation;
- the minimal package and architecture-smoke tooling needed to verify the public source;
- GitHub security policy and current GitHub/WordPress.org marketing assets.

Exclude private compliance and release documents, research/design references, release drafts, the
private test harness, local wp-env configuration, mock-provider fixtures, live browser programmes and
session state, generated ZIPs/results, provider keys, customer content, release credentials and
`.DS_Store` files.

The public README must state plainly:

- eleven-step Wizard and one-request Express mode;
- OpenAI, Anthropic and Google Gemini through the required Opace AI Hub;
- live, account-dependent model discovery rather than a fixed list of future model names;
- custom article targets, exact count/range and retained-draft improvement;
- qualitative keyword demand plus Google Trends, not invented monthly volumes;
- Image Studio prompts, automatic editable captions, placement and article-only overrides;
- Draft, Publish and Shortcode destinations in Review/Evaluate and Express;
- structural Evaluate evidence separated from editorial judgement;
- deterministic test evidence separated from deliberate real-provider acceptance;
- WordPress.org as the supported install/update channel.

## Publication order

1. Push the reviewed public snapshot and matching GitHub tag when David authorises GitHub publication.
2. Confirm the public default branch, README, source and tag all point at the same version.
3. Publish the WordPress.org package and listing assets only under separate WordPress.org authority.
4. Verify the live WordPress.org version, readme and screenshots after that release.
5. Triage existing issues against v3, close obsolete 2.x reports with context, and add current bug
   report templates asking for WordPress, PHP, Opace AI Hub, provider, model and reproduction details.

No push or public repository mutation should happen without David's explicit approval.
