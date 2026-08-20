# AI-Scribe release runbook

Nothing in this repository publishes automatically. Building a ZIP, committing Git and publishing to
WordPress.org are separate gates. Record the exact commit and package hash at each gate.

Last reconciled with AI-Scribe 3.2.29 on 20 August 2026.

## 1. Freeze scope and dependency

- Confirm the worktree, branch and current version in `article_builder.php`.
- Confirm the required Opace AI Hub release is available under the declared `ai-core` slug.
- Review unresolved status documents and user acceptance notes.
- Search the repository and history for keys, tokens, credentials, browser storage and customer data.
  Rotate any exposed secret outside Git before continuing; never document the value.
- Do not include Opace AI Hub source inside the AI-Scribe package.

## 2. Version and public copy

The following must agree:

- `article_builder.php` plugin header `Version:`;
- `AI_SCRIBE_VERSION` in `article_builder.php`;
- `readme.txt` `Stable tag:`;
- the newest `readme.txt` Changelog and Upgrade Notice entries;
- the visible version in `README.md`.

Reconcile `README.md`, `readme.txt`, `TESTING.md`, this runbook and the public GitHub sync plan against
the code and Git history. Do not list speculative model names: models are account-dependent and
discovered live. Do not describe deterministic mock evidence as a real-provider test.

## 3. Run the regression baseline

```bash
php tests/php/run-tests.php
php tests/php/article-planning-tests.php
php scripts/smoke.php

for test_file in tests/js/*.mjs tests/js/*.test.js; do
  node "$test_file"
done
```

Run PHP and JavaScript syntax checks and `git diff --check`. Use `TESTING.md` for wp-env, installed-
package and deliberate real-provider lanes. Record exact commands and totals in a release status
document.

## 4. Build and audit the exact ZIP

```bash
scripts/build-release.sh
```

The build creates `dist/ai-scribe-<version>.zip` with the WordPress.org slug as its only top-level
directory. It must contain runtime files only: `article_builder.php`, `uninstall.php`, `readme.txt`,
`includes/`, `templates/` and `assets/`.

For the exact ZIP being released:

- run `unzip -t` and calculate SHA-256;
- compare every eligible packaged file byte-for-byte with source;
- lint packaged PHP and JavaScript and parse packaged JSON;
- confirm no tests, development config, source maps, docs, logs, keys or mock fixtures leaked in;
- confirm every enqueued asset is cache-busted with `AI_SCRIBE_VERSION`;
- install the ZIP under the real slug in an isolated WordPress site with Opace AI Hub active;
- verify all admin screens, the eleven Wizard steps, Express and upgrade persistence;
- run WordPress Plugin Check against the packaged copy and record its results.

Historical compliance reports prove only their named package.

## 5. Refresh WordPress.org presentation assets

WordPress.org listing assets live in the SVN repository's sibling `/assets` directory, not inside the
plugin ZIP. Follow the official dimensions and naming rules:

- banner: `banner-772x250.png` plus retina `banner-1544x500.png`;
- icon: `icon-128x128.png` plus `icon-256x256.png` (an SVG may supplement, not replace, the PNGs);
- screenshots: `screenshot-1.png`, `screenshot-2.png`, and so on, with one matching caption per line
  in `readme.txt`.

Before every public release, compare the assets with the current installed interface. The 2.6-era
robot banner, ChatGPT-only icon and flat two-column screenshots are not suitable for the current
three-provider 3.2 product.

Current artwork brief:

- retain the recognisable blue/cyan AI-Scribe mark;
- use provider-neutral copy: OpenAI, Anthropic and Gemini;
- show the current responsive interface, with Wizard and Express as the two clear paths;
- give Image Studio, exact length status and evidence-led Evaluate visible representation;
- avoid invented model names, tiny unreadable UI and claims tied to one provider;
- capture at least a clean desktop hero, Express, Image Studio, Review/Evaluate, Settings and a narrow
  responsive view from the exact release package.

Do not overwrite or commit SVN assets until David approves the final artwork and screenshot order.

## 6. Stage and publish WordPress.org SVN

```bash
scripts/deploy-svn.sh            # dry run
scripts/deploy-svn.sh --apply    # stage trunk and tags/<version>
```

In the SVN working copy:

```bash
svn status
svn add --force trunk tags/<version>
svn status | awk '/^!/ {print $2}' | xargs -r svn rm
svn diff trunk/readme.txt
svn diff --summarize assets
```

Review every changed path before the explicit `svn commit`. Confirm the new tag, trunk, Stable tag,
screenshots and captions agree. Publication is an external irreversible action and requires explicit
approval.

## 7. Sync the public GitHub repository

The configured public remote is
`https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator.git`. Fetch immediately
before release, reconcile without force-pushing, scan the candidate for secrets, then push the
reviewed source commit and matching version tag. Attach the exact tested ZIP and its SHA-256 to the
GitHub release. GitHub publication and WordPress.org publication remain separate gates.

## 8. Post-release checks

1. Install from WordPress.org on a clean site and confirm the displayed version and assets.
2. Exercise an upgrade from a retained-data 2.6.x/v3 site with Opace AI Hub active.
3. Confirm settings, conversations, shortcodes, posts and uninstall preference behave as documented.
4. Run one bounded, deliberate real-provider check for each materially changed provider path.
5. Check the public GitHub tag and README match the released package.
6. Monitor the support forum and listing reviews during the first week.

## Known release boundary

The current “stream” endpoint buffers a complete provider response before emitting it. Do not claim
token-by-token streaming in public copy unless the implementation and provider acceptance prove it.
