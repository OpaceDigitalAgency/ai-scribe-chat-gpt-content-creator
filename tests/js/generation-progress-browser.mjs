import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const mainCss = fs.readFileSync(new URL('../../assets/css/main.css', import.meta.url), 'utf8');
const componentsCss = fs.readFileSync(new URL('../../assets/css/components.css', import.meta.url), 'utf8');
const baseSource = fs.readFileSync(new URL('../../assets/js/views/steps/BaseStepView.js', import.meta.url), 'utf8');
const evidenceDir = fileURLToPath(new URL('../../../.agent/docs/ai-scribe/evidence/loading-progress/', import.meta.url));
fs.mkdirSync(evidenceDir, { recursive: true });
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`<!doctype html><html><head><style>${mainCss}\n${componentsCss}</style></head><body>
        <main class="ai-scribe-app">
            <span id="active-model-details">Gemini 3.6 Flash · Google</span>
            <section data-step-panel="1" data-state="idle">
                <h2 class="step-heading">Title ideas</h2>
                <p class="visually-hidden" data-testid="step-status" aria-live="polite"></p>
                <div class="results-section hidden"><div id="titles-options"></div></div>
            </section>
            <section data-step-panel="6" data-state="ready">
                <h2 class="step-heading">Article body</h2>
                <p class="visually-hidden" data-testid="step-status" aria-live="polite"></p>
                <div class="results-section"><div class="ql-editor"><p>Existing draft stays here.</p></div></div>
            </section>
            <section data-step-panel="express" data-state="idle">
                <h2 class="step-heading">Express Article</h2>
                <p class="visually-hidden" data-testid="step-status" aria-live="polite"></p>
                <div data-testid="express-progress-slot"></div>
                <article class="stream-output"><div class="skeleton-loader"></div></article>
            </section>
        </main>
    </body></html>`);
    await page.addScriptTag({ content: baseSource });
    await page.evaluate(() => {
        window.ai_scribe = { contentSettings: { number_of_headings: 7 }, i18n: { stepNames: { 1: 'Title ideas', 3: 'Article outline', 6: 'Article body' } } };
        window.titleView = new BaseStepView(1, {});
        window.bodyView = new BaseStepView(6, {});
        window.expressView = new BaseStepView('express', {});
        window.titleView.showLoading();
    });

    const titlePanel = page.locator('[data-step-panel="1"]');
    const progress = titlePanel.locator('[data-testid="progress-ticker"]');
    assert.equal(await progress.getAttribute('data-progress-stage'), 'preparing');
    assert.match(await progress.locator('.step-progress-context').textContent(), /Gemini 3\.6 Flash · Google/);
    assert.equal(await titlePanel.locator('.skeleton-card').count(), 5, 'title loading mirrors five choices');
    assert.equal(await progress.locator('[role="progressbar"]').getAttribute('aria-valuenow'), null, 'indeterminate progress has no invented percentage');

    assert.deepEqual(await page.evaluate(() => {
        const outline = Object.create(BaseStepView.prototype);
        outline.step = 3;
        return outline.skeletonSpec();
    }), { shape: 'choice', count: 7 }, 'outline skeleton follows the configured heading count');

    await page.evaluate(() => window.titleView.updateProgressStage('waiting'));
    assert.equal(await progress.getAttribute('data-progress-stage'), 'waiting');
    assert.match(await titlePanel.locator('[data-testid="step-status"]').textContent(), /Waiting for and checking/);
    await page.evaluate(() => { window.titleView.progressStartedAt -= 19_000; });
    await progress.locator('.step-progress-note').waitFor({ state: 'visible', timeout: 2_000 });
    assert.equal(await progress.locator('.step-progress-note').isVisible(), true, 'long-wait guidance appears after about 18 seconds');
    await page.screenshot({ path: path.join(evidenceDir, 'progress-1440-light.png'), fullPage: true });

    await page.setViewportSize({ width: 768, height: 900 });
    await page.emulateMedia({ reducedMotion: 'reduce', colorScheme: 'dark' });
    await page.evaluate(() => document.documentElement.setAttribute('data-ai-scribe-theme', 'dark'));
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true);
    assert.equal(await progress.locator('.step-progress-fill').evaluate((el) => getComputedStyle(el).animationName), 'none');
    await page.screenshot({ path: path.join(evidenceDir, 'progress-768-dark-reduced-motion.png'), fullPage: true });

    await page.setViewportSize({ width: 375, height: 812 });
    await page.emulateMedia({ reducedMotion: 'no-preference', colorScheme: 'light' });
    await page.evaluate(() => document.documentElement.removeAttribute('data-ai-scribe-theme'));
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true);
    await page.screenshot({ path: path.join(evidenceDir, 'progress-375-light.png'), fullPage: true });

    await page.setViewportSize({ width: 1440, height: 900 });

    await page.evaluate(() => window.titleView.updateProgressStage('displaying'));
    assert.equal(await progress.getAttribute('data-progress-stage'), 'displaying');
    await page.evaluate(() => window.titleView.showReady());
    assert.equal(await progress.isHidden(), true, 'ready cleans up progress deterministically');
    assert.equal(await titlePanel.locator('.skeleton-loader').isHidden(), true, 'ready cleans up skeleton');
    assert.equal(await titlePanel.getAttribute('aria-busy'), 'false');

    await page.evaluate(() => window.bodyView.showLoading());
    const bodyPanel = page.locator('[data-step-panel="6"]');
    assert.equal(await bodyPanel.locator('.skeleton-loader').count(), 0, 'regeneration does not blank valid output');
    assert.equal(await bodyPanel.locator('.results-section').getAttribute('class'), 'results-section is-refreshing');
    assert.equal(await bodyPanel.locator('.ql-editor').textContent(), 'Existing draft stays here.');
    await page.evaluate(() => window.bodyView.showError(new Error('Provider returned an empty response.'), () => {}));
    assert.equal(await bodyPanel.locator('.ql-editor').textContent(), 'Existing draft stays here.', 'failure preserves prior output');
    assert.equal(await bodyPanel.locator('[data-testid="step-retry"]').isVisible(), true);
    assert.equal(await bodyPanel.locator('.results-section').getAttribute('class'), 'results-section');

    await page.evaluate(() => window.expressView.showLoading());
    const expressPanel = page.locator('[data-step-panel="express"]');
    assert.equal(await expressPanel.locator('[data-testid="progress-ticker"]').isVisible(), true, 'Express shows the shared progress card when its skeleton is nested');
    assert.equal(await expressPanel.locator('[data-testid="express-progress-slot"] > [data-testid="progress-ticker"]').count(), 1, 'Express progress stays directly below its controls');
    assert.match(await expressPanel.locator('.step-progress-context').textContent(), /Express Article.*Gemini 3\.6 Flash/s);
    await page.evaluate(() => window.expressView.updateProgressStage('waiting'));
    assert.match(await expressPanel.locator('.step-progress-label').textContent(), /Waiting for and checking/);

    assert.equal(componentsCss.includes('Math.exp'), false);
    console.log('Generation progress browser: stage order, truthful indeterminate state, shaped skeletons, preserved regeneration, retry, cleanup, reduced motion and 375px passed');
} finally {
    await browser.close();
}
