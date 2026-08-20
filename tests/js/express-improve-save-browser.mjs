import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const baseSource = fs.readFileSync('assets/js/views/steps/BaseStepView.js', 'utf8');
const expressSource = fs.readFileSync('assets/js/views/steps/ExpressView.js', 'utf8');
const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const mainCss = fs.readFileSync('assets/css/main.css', 'utf8');
const componentsCss = fs.readFileSync('assets/css/components.css', 'utf8');
const browser = await chromium.launch({ headless: true });

const saveCard = `<section class="save-status-card is-unsaved" data-save-status-card data-save-context="express">
  <div class="save-status-summary"><div class="save-status-copy"><h3>Save status</h3><p data-save-status-message role="status"></p></div><span data-save-status-badge></span></div>
  <ul data-save-destinations hidden>
    <li data-save-destination="post" hidden><strong data-save-destination-label></strong><span data-save-destination-state></span></li>
    <li data-save-destination="shortcode" hidden><strong>Shortcode</strong><span data-save-destination-state></span></li>
  </ul>
  <div class="save-status-actions">
    <button data-action="save-draft"><span data-save-label>Save as Draft</span></button>
    <button data-action="save-post"><span data-save-label>Publish Post</span></button>
    <button data-action="save-shortcode"><span data-save-label>Save as Shortcode</span></button>
    <a data-saved-post-link hidden></a>
  </div><code data-saved-shortcode-note hidden></code>
</section>`;

const article = (words, marker) => ({
    title: `Accurate article ${marker}`,
    tagline: 'A useful retained draft.',
    outline: ['Useful section'],
    intro: '<p>Opening paragraph.</p>',
    body_html: `<h2>Useful section</h2><p>${marker} body copy.</p>`,
    conclusion: '<h2>Conclusion</h2><p>Closing paragraph.</p>',
    qna: [],
    meta: { title: 'Accurate article', description: 'Description' },
    _words: words
});

try {
    for (const width of [375, 768, 1440]) {
        const page = await browser.newPage({ viewport: { width, height: 900 } });
        await page.setContent(`<!doctype html><html><body><main id="ai-scribe-root" class="ai-scribe-app mode-express-active">
          <section data-step-panel="10"><input data-publishing-category><input data-publishing-tags></section>
          <section data-step-panel="express" data-state="idle">
            <input data-testid="express-topic" value="useful topic"><p data-testid="step-status"></p>
            <div data-testid="express-progress-slot"></div>
            <div class="results-section hidden">
              <div class="article-target-status" data-article-target-status hidden>
                <div class="article-target-status-copy"><strong data-target-status-heading></strong><span data-target-status-detail></span></div>
                <div class="article-target-track"><span data-target-status-bar></span></div>
                <div class="article-target-action" data-target-status-action hidden><div><strong>Keep this draft and improve its length</strong><p data-improve-length-status role="status"></p></div><button data-action="express-improve-length"><span data-improve-length-label>Improve length</span></button></div>
              </div>
              <article class="express-article" id="express-stream-output"></article>
              <button data-action="express-refine" hidden>Refine</button>${saveCard}
            </div>
          </section>
        </main></body></html>`);
        await page.addStyleTag({ content: mainCss });
        await page.addStyleTag({ content: componentsCss });
        await page.addScriptTag({ content: 'window.ai_scribe={checkArr:{},contentSettings:{}};window.lucide={createIcons(){}};class StepViewRegistry{static isStreamingStep(){return false}}' });
        await page.addScriptTag({ content: baseSource });
        await page.addScriptTag({ content: expressSource });
        await page.addScriptTag({ content: controllerSource });

        const initial = await page.evaluate(({ initialArticle }) => {
            const state = { conversationId: 42, currentStep: 1, persistence: { post: null, shortcode: null } };
            const observers = [];
            const appState = {
                getStateSlice(key) { return state[key]; },
                setStateSlice(key, value) { state[key] = value; observers.forEach((observer) => observer(state)); },
                subscribe(observer) { observers.push(observer); }
            };
            const view = new ExpressView(appState);
            const review = { compileArticleHtml() { return ''; }, getSelection() { return ''; } };
            let improveResolve;
            const improvePromise = new Promise((resolve) => { improveResolve = resolve; });
            const api = {
                improveExpressLength() { return improvePromise; },
                async savePost(_id, fields) { window.savedPostHtml = fields.content_html; return { post_id: 90, edit_link: '/edit/90' }; },
                async saveShortcode(fields) { window.savedShortcodeHtml = fields.articleVal; return { shortcode_id: 11 }; }
            };
            const registry = { get(step) { return step === 'express' ? view : (step === 10 ? review : null); } };
            const controller = new WizardFlowController(appState, api, registry);
            controller.notify = () => {};
            view.renderArticle({ conversation_id: 42, article: initialArticle, quality_plan: { word_count: 1687, target_words: 2200, minimum_words: 1925, maximum_words: 2475, advisory: true, message: 'Short draft kept.' } });
            controller.updateReviewActions();
            controller.renderPersistenceState();
            window.fixture = { state, view, controller, improveResolve };
            const status = document.querySelector('[data-article-target-status]');
            return {
                heading: status.querySelector('[data-target-status-heading]').textContent,
                detail: status.querySelector('[data-target-status-detail]').textContent,
                improveHidden: status.querySelector('[data-target-status-action]').hidden,
                saveDisabled: document.querySelector('[data-action="save-draft"]').disabled,
                overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
            };
        }, { initialArticle: article(1687, 'original') });

        assert.equal(initial.heading, '1,687 words generated');
        assert.match(initial.detail, /Target 2,200.*1,925–2,475.*513 words to target/);
        assert.equal(initial.improveHidden, false);
        assert.equal(initial.saveDisabled, false, 'Express exposes the shared save actions after a real article exists');
        assert.equal(initial.overflow, false, `${width}px initial layout has no horizontal overflow`);

        if (width === 768) {
            await page.evaluate(async () => {
                await window.fixture.controller.savePost('draft', document.querySelector('[data-action="save-draft"]'));
            });
            assert.equal(await page.locator('[data-save-status-badge]').textContent(), 'Saved');

            const pending = page.evaluate(() => window.fixture.controller.improveExpressLength(document.querySelector('[data-action="express-improve-length"]')));
            await page.waitForFunction(() => document.querySelector('[data-action="express-improve-length"]').getAttribute('aria-busy') === 'true');
            assert.match(await page.locator('#express-stream-output').textContent(), /original body copy/, 'old draft remains visible while improvement runs');
            await page.evaluate(({ improvedArticle }) => window.fixture.improveResolve({
                conversation_id: 42,
                article: improvedArticle,
                quality_plan: { word_count: 1930, target_words: 2200, minimum_words: 1925, maximum_words: 2475, advisory: false },
                improvement: { message: 'Added 243 useful words; the draft is now in range.' }
            }), { improvedArticle: article(1930, 'improved') });
            await pending;

            assert.match(await page.locator('#express-stream-output').textContent(), /improved body copy/);
            assert.equal(await page.locator('[data-target-status-heading]').textContent(), '1,930 words generated');
            assert.equal(await page.locator('[data-target-status-action]').isHidden(), false, 'an in-range draft can still be improved towards the selected target');
            assert.equal(await page.locator('[data-save-status-badge]').textContent(), 'Unsaved changes', 'prior saved snapshot is retained and marked stale');

            await page.evaluate(async () => {
                await window.fixture.controller.saveShortcode(document.querySelector('[data-action="save-shortcode"]'));
            });
            const exact = await page.evaluate(() => ({
                rendered: window.fixture.view.getSaveHtml(),
                shortcode: window.savedShortcodeHtml,
                post: window.savedPostHtml
            }));
            assert.equal(exact.shortcode, exact.rendered, 'shortcode receives the exact improved Express snapshot');
            assert.notEqual(exact.post, exact.rendered, 'the earlier draft snapshot remains evidence of the older version');

            await page.evaluate(() => {
                const before = window.fixture.view.getSaveHtml();
                window.fixture.controller.api.improveExpressLength = async () => { throw new Error('Provider timed out.'); };
                document.querySelector('[data-target-status-action]').hidden = false;
                window.beforeFailedImprovement = before;
            });
            await page.evaluate(() => window.fixture.controller.improveExpressLength(document.querySelector('[data-action="express-improve-length"]')));
            const failed = await page.evaluate(() => ({
                unchanged: window.fixture.view.getSaveHtml() === window.beforeFailedImprovement,
                status: document.querySelector('[data-improve-length-status]').textContent,
                label: document.querySelector('[data-improve-length-label]').textContent
            }));
            assert.equal(failed.unchanged, true, 'failed enhancement cannot replace the draft');
            assert.match(failed.status, /existing draft is unchanged/i);
            assert.equal(failed.label, 'Try improvement again');
        }

        await page.close();
    }

    console.log('Express improve length + shared save UX browser: responsive, retained draft, exact snapshots and failure retry passed');
} finally {
    await browser.close();
}
