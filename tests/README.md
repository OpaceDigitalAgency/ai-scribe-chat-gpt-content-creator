# AI-Scribe test harness

The repository has three complementary test layers. Run all applicable layers; none replaces the
others.

## 1. PHP service regression

```bash
php tests/php/run-tests.php
php tests/php/article-planning-tests.php
```

`run-tests.php` covers services, persistence, permissions, output and migration contracts without a
WordPress bootstrap. `article-planning-tests.php` focuses on visible word counts, target allocation,
balanced improvement and draft-preservation rules.

## 2. Deterministic browser-contract fixtures

Files in `tests/js/` are standalone Node/Playwright fixtures. Run all of them with:

```bash
for test_file in tests/js/*.mjs tests/js/*.test.js; do
  node "$test_file"
done
```

They exercise current production classes and rendered components with controlled local fixtures.
Coverage includes the Wizard, Express, responsive layouts, keyword demand labels and Google Trends,
Image Studio, captions and placement, Review/Evaluate, metadata, save-state and failure paths. They
make no provider request.

## 3. WordPress end-to-end tests

`tests/e2e/` uses `@wordpress/env` and Playwright. The mock provider in `mu-plugins/` is inert unless
both `AI_SCRIBE_MOCK` and `AI_SCRIBE_AUTOMATED_TEST` are true.

```bash
npm ci
npx playwright install chromium
npm run env:start
npm run test:e2e
```

Focused commands:

```bash
npm run test:e2e:setup
npm run test:e2e:wizard
WP_BASE_URL=http://localhost:8889 npm run test:e2e
```

The configured environment URLs may change. Treat `.wp-env.json` and `WP_BASE_URL` as authoritative;
do not copy historical ports or credentials from old result files.

## Live and upgrade programmes

`test/live/` contains the wider authenticated scenario programme and installed-package upgrade
checks. These scripts can change WordPress state. Read the script, use an isolated site and confirm
whether provider traffic is mocked before running it. Real-provider mode is always a deliberate,
billed acceptance lane, never part of the default regression.

## Evidence rules

- Test the exact commit and, for a release, the exact ZIP.
- Store persistent release evidence under the project status-doc directory; keep transient browser
  screenshots under a temporary or ignored artefact directory.
- Never commit browser storage state, API keys, WordPress credentials or generated customer content.
- A green mock result proves the plugin contract, not the quality or availability of a live model.
