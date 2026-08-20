import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const mainCss = fs.readFileSync(new URL('../../assets/css/main.css', import.meta.url), 'utf8');
const componentsCss = fs.readFileSync(new URL('../../assets/css/components.css', import.meta.url), 'utf8');
const viewSource = fs.readFileSync(new URL('../../assets/js/views/steps/SeoMetaStepView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await page.setContent(`<!doctype html><html><head><style>${mainCss}\n${componentsCss}</style></head><body>
        <main class="ai-scribe-app"><section data-step-panel="9">
            <div class="results-section" id="meta-results">
                <p class="seo-meta-intro" id="seo-meta-help">Generated suggestions, not locked fields.</p>
                <div class="seo-combined-input">
                    <div class="seo-field"><label for="meta-title">Meta Title</label>
                        <input id="meta-title" aria-describedby="seo-meta-help meta-title-guidance meta-separator-guidance">
                        <span class="meta-count" data-testid="meta-title-count"></span>
                        <span class="seo-meta-guidance" id="meta-title-guidance" data-testid="meta-title-guidance"></span>
                    </div>
                    <div class="seo-field"><label for="meta-description">Meta Description</label>
                        <textarea id="meta-description" aria-describedby="seo-meta-help meta-description-guidance"></textarea>
                        <span class="meta-count" data-testid="meta-description-count"></span>
                        <span class="seo-meta-guidance" id="meta-description-guidance" data-testid="meta-description-guidance"></span>
                    </div>
                </div>
                <div class="seo-meta-checks"><p class="seo-meta-check" data-testid="meta-separator-guidance"></p><div class="seo-meta-keyword-coverage" data-testid="meta-keyword-guidance"></div><span data-testid="seo-meta-live" role="status" aria-live="polite"></span></div>
                <section data-testid="meta-optimise-panel" hidden><button data-action="optimise-meta-length">Optimise metadata length</button><p data-testid="meta-optimise-status"></p><div data-testid="meta-optimise-comparison" hidden><span data-testid="meta-original-title"></span><span data-testid="meta-original-description"></span><span data-testid="meta-suggested-title"></span><span data-testid="meta-suggested-description"></span><button data-action="apply-meta-optimisation">Apply suggestion</button><button data-action="keep-original-meta">Keep original</button></div><button data-action="undo-meta-optimisation" hidden>Undo</button></section>
                <button class="next-step-btn" disabled>Continue</button>
            </div>
        </section></main></body></html>`);
    await page.addScriptTag({ content: `class BaseStepView {
        constructor(step, appState) { this.step = step; this.appState = appState; this.panel = document.querySelector('[data-step-panel="9"]'); this.bindPanelEvents(); }
        setNextEnabled(enabled) { document.querySelector('.next-step-btn').disabled = !enabled; }
        showReady() { this.state = 'ready'; }
    }` });
    await page.addScriptTag({ content: viewSource });
    await page.evaluate(() => {
        const state = { 2: { selection: ['2026 SEO Strategy', 'AI SEO trends', 'search intent optimisation'] } };
        const appState = {
            getStateSlice: () => state,
            setStateSlice: (key, value) => { window.persisted = { key, value }; }
        };
        window.metaView = new SeoMetaStepView(appState);
        window.metaView.renderTyped({ meta: { title: 'Generated title', description: 'Generated description' } });
    });

    const title = page.locator('#meta-title');
    await title.click();
    await title.fill('2026 SEO Strategy | AI Trends and Search Intent');
    await page.locator('#meta-description').fill('Update your 2026 SEO Strategy with AI SEO trends and search intent optimisation, linking useful content, buyer intent and measurable results naturally.');
    assert.equal(await title.inputValue(), '2026 SEO Strategy | AI Trends and Search Intent');
    assert.equal(await page.locator('.next-step-btn').isEnabled(), true);
    assert.match(await page.locator('[data-testid="meta-separator-guidance"]').textContent(), /required spaced pipe/i);
    const coverage = await page.locator('[data-testid="meta-keyword-guidance"] li').allTextContents();
    assert.equal(coverage.length, 3);
    assert.match(coverage[0], /title exact; description exact/i);
    assert.match(coverage[1], /title combined; description exact/i);
    assert.match(coverage[2], /title partial; description exact/i);
    assert.match(await page.locator('[data-testid="seo-meta-live"]').textContent(), /Title length:/);
    assert.equal(await title.evaluate((element) => getComputedStyle(element).pointerEvents), 'auto');
    assert.equal(await page.evaluate(() => window.persisted.value[9].selection.title), '2026 SEO Strategy | AI Trends and Search Intent');

    assert.equal(await page.locator('[data-testid="meta-optimise-panel"]').isHidden(), true);
    await title.fill('2026 SEO Strategy | AI Trends, Search Intent and Practical Advice for Better Results');
    assert.equal(await page.locator('[data-testid="meta-optimise-panel"]').isVisible(), true);
    await page.evaluate(() => window.metaView.showOptimiseSuggestion({title:'2026 SEO Strategy | AI and Search Intent Guide',description:'Use this 2026 SEO Strategy to understand AI SEO trends and search intent optimisation, with practical steps for clearer content and better results.'}));
    assert.match(await page.locator('[data-testid="meta-optimise-status"]').textContent(), /Suggestion ready/);
    assert.match(await page.locator('[data-testid="meta-original-title"]').textContent(), /Practical Advice/);
    assert.match(await page.locator('[data-testid="meta-suggested-title"]').textContent(), /2026 SEO Strategy/);
    await page.locator('[data-action="apply-meta-optimisation"]').click();
    await page.evaluate(() => window.metaView.applyOptimiseSuggestion());
    assert.equal(await title.inputValue(), '2026 SEO Strategy | AI and Search Intent Guide');
    assert.equal(await page.locator('[data-testid="meta-optimise-panel"]').isVisible(), true, 'valid applied values keep the Undo parent visible');
    assert.equal(await page.locator('[data-action="optimise-meta-length"]').isHidden(), true, 'applied state hides the no-longer-relevant optimise action');
    assert.equal(await page.locator('[data-action="undo-meta-optimisation"]').isVisible(), true, 'Undo remains operable after valid values hide overlength guidance');
    assert.match(await page.locator('[data-testid="meta-optimise-status"]').textContent(), /applied.*Undo remains available/i);
    await page.evaluate(() => window.metaView.undoOptimisation());
    assert.match(await title.inputValue(), /Practical Advice/);
    assert.equal(await page.locator('[data-action="undo-meta-optimisation"]').isHidden(), true);
    await page.evaluate(() => window.metaView.showOptimiseSuggestion({title:'2026 SEO Strategy | Fresh suggestion for testing',description:'Use this 2026 SEO Strategy with AI SEO trends and search intent optimisation to make practical content decisions and improve useful outcomes.'}));
    await title.fill('2026 SEO Strategy | Manual edit invalidates stale advice');
    assert.equal(await page.locator('[data-testid="meta-optimise-comparison"]').isHidden(), true);
    assert.equal(await page.locator('[data-testid="meta-optimise-status"]').textContent(), '');
    assert.equal(await page.locator('[data-action="undo-meta-optimisation"]').isHidden(), true);
    await page.evaluate(() => window.metaView.renderTyped({ meta: { title: '2026 SEO Strategy | Regenerated metadata example', description: 'A regenerated 2026 SEO Strategy description that is intentionally useful enough to verify stale optimiser state is cleared on a new generation.' } }));
    assert.equal(await page.locator('[data-testid="meta-optimise-comparison"]').isHidden(), true);
    assert.equal(await page.locator('[data-testid="meta-optimise-status"]').textContent(), '');

    await page.setViewportSize({ width: 375, height: 812 });
    assert.equal(await title.isVisible(), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true);

    await page.setViewportSize({ width: 768, height: 900 });
    await page.evaluate(() => document.documentElement.setAttribute('data-ai-scribe-theme', 'dark'));
    assert.equal(await title.isVisible(), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true);
    assert.notEqual(await title.evaluate((element) => getComputedStyle(element).color), 'rgba(0, 0, 0, 0)');

    console.log('SEO meta browser: keyword coverage, pipe separator, applied-state Undo, editing, responsive layout and AppState persistence passed');
} finally {
    await browser.close();
}
