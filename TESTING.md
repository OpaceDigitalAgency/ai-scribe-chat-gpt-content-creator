# AI-Scribe testing guide

This guide separates deterministic regression, an installed-package browser check and deliberate
real-provider acceptance. A green mock suite does not prove provider behaviour, and a source-tree
test does not prove the ZIP that will be released.

## Prerequisites

- PHP 7.4 or newer for the minimum-version syntax lane.
- Node 20 or newer.
- Docker Desktop for the WordPress environments.
- Dependencies installed with `npm ci`, then `npx playwright install chromium`.

Do not put provider keys or WordPress credentials in commands, screenshots, Git or result files.

## Deterministic baseline

Run from the repository root:

```bash
php tests/php/run-tests.php
php tests/php/article-planning-tests.php
php scripts/smoke.php

for test_file in tests/js/*.mjs tests/js/*.test.js; do
  node "$test_file"
done
```

For the local 3.2.29 candidate, the recorded baseline is 561/561 PHP assertions, 77/77
article-planning assertions, 11/11 smoke checks and 36 passing JavaScript suites. Re-run the
commands on the exact
commit being packaged; the historical totals are context, not a substitute for a fresh result.

The JavaScript fixtures cover individual contracts and responsive layouts without provider calls.
They include all eleven Wizard steps, Express, Image Studio, Review/Evaluate, save-state, article
length, captions, metadata and failure preservation.

## WordPress browser suite

The wp-env configuration mounts AI-Scribe and the test-only mock provider into isolated WordPress
containers:

```bash
npm run env:start
npm run test:e2e
```

Use `WP_BASE_URL` to target a particular isolated environment:

```bash
WP_BASE_URL=http://localhost:8889 npm run test:e2e
```

Mock responses are enabled only when both `AI_SCRIBE_MOCK=true` and
`AI_SCRIBE_AUTOMATED_TEST=true`. A normal or owner-test site must not define that pair. When the
pair is absent, tests must not assume that generation is free or intercepted.

Useful focused commands:

```bash
npm run test:e2e:setup
npm run test:e2e:wizard
npx playwright test --project=desktop-1280
```

HTML reports are written under `tests/e2e/report/`. Keep generated screenshots and browser storage
state out of release packages.

## Exact-package acceptance

Build first, then install the resulting ZIP into an isolated WordPress site under the real
WordPress.org slug. Do not rely on a source-mounted development copy.

```bash
scripts/build-release.sh
```

Acceptance must verify:

- plugin header, Stable tag and enqueued asset versions match the ZIP;
- Opace AI Hub is active and the dependency guard behaves honestly;
- all eleven Wizard steps, Express, Settings, Saved Shortcodes and Help render at the agreed widths;
- active navigation stays visible without page-level horizontal overflow;
- Body and Review use the same word-count plan, retain drafts on failure and cancel late improvement
  responses after Start Again;
- Draft, Publish and Shortcode save the exact visible article;
- no console errors, page errors, failed application requests or PHP warnings are recorded;
- existing AI-Scribe options, conversations and shortcodes survive a force-install upgrade.

The 3.2.21 release record contains an isolated installed-ZIP check across 66 Wizard layouts and 18
admin layouts. That evidence belongs to that exact package only.

The 3.2.29 candidate was force-installed with Opace AI Hub 1.0.7 on the dedicated WordPress 7.1,
PHP 8.3 release site from the exact distribution ZIPs. Browser inspection covered the Wizard,
Settings, Saved Shortcodes, Help and two Hub pages. Approved full page logos rendered at 56px or
72px; both 20px transparent menu images had zero measured centre offset; Hub notices occupied their
own row; all six pages had zero horizontal overflow, console errors and page errors. Plugin Check
2.1.0 returned no errors and one unavoidable dependency-directory warning: the required Hub slug is
not yet live in the WordPress.org directory. This is local candidate evidence, not GitHub release,
WordPress.org publication or real-provider acceptance.

## Deliberate real-provider acceptance

Real-provider checks are separate, billed and opt-in. Use a fresh isolated browser context and the
smallest bounded scenario needed to judge the provider contract. Confirm mock flags are off before
the call. Record the provider, model, prompt boundary, response shape and cost evidence without
recording the key.

At minimum, a release that changes generation should sample the affected path against each relevant
configured provider. A deterministic pass must never be described as real-provider proof.

## Dropbox mount fallback

If Docker refuses the Dropbox path, sync a disposable run copy and execute wp-env there:

```bash
npm run sync:run
cd "$HOME/ai-scribe-v3-run"
npm ci
npm run env:start
```

Edit only the Dropbox working tree. Re-sync after source changes.

## Failure handling

- Never weaken a capability check, validator or fail-closed path to make a test pass.
- Preserve the current draft when generation, improvement, image creation or saving fails.
- Stop on a data-loss or authentication boundary failure.
- Report the exact command, expected result, actual result and persistent evidence path.
