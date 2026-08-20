import assert from 'node:assert/strict';
import fs from 'node:fs';
import { webkit } from 'playwright';

const apiSource = fs.readFileSync('assets/js/services/ApiClient.js', 'utf8');
const baseSource = fs.readFileSync('assets/js/views/steps/BaseStepView.js', 'utf8');
const expressSource = fs.readFileSync('assets/js/views/steps/ExpressView.js', 'utf8');
const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const browser = await webkit.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(`${error.name}: ${error.message}`));

    await page.setContent(`<!doctype html><html><body>
      <main id="ai-scribe-root" class="ai-scribe-app">
        <section data-step-panel="express" data-state="idle">
          <input data-testid="express-topic" value="web design tips for this year">
          <button data-action="express-refine" hidden>Refine in wizard</button>
          <p data-testid="step-status"></p>
          <div data-testid="express-progress-slot"></div>
          <div class="express-content-container">
            <div class="results-section hidden">
              <article id="express-stream-output"></article>
            </div>
          </div>
        </section>
      </main>
    </body></html>`);
    await page.addScriptTag({ content: 'window.lucide={createIcons(){}};window.ai_scribe={ajaxUrl:"/wp-admin/admin-ajax.php",nonce:"fixture",i18n:{}};' });
    await page.addScriptTag({ content: apiSource });
    await page.addScriptTag({ content: baseSource });
    await page.addScriptTag({ content: expressSource });
    await page.addScriptTag({ content: controllerSource });

    const result = await page.evaluate(async () => {
        const calls = [];
        const response = {
            success: true,
            data: {
                conversation_id: 246,
                article: {
                    title: 'A complete retained article',
                    meta: { title: 'A complete retained article', description: 'A useful description.' },
                    tagline: 'Useful work can still need a final edit.',
                    outline: ['First section'],
                    intro: '<p>Opening paragraph.</p>',
                    body_html: '<h2>First section</h2><p>Useful body copy.</p>',
                    conclusion: '<p>Closing paragraph.</p>',
                    qna: []
                },
                cost: { actual_usd: 0.0012, running_total_usd: 0.0012 },
                quality_plan: {
                    pass: false,
                    advisory: true,
                    message: 'The complete draft was kept 45 words below the preferred range.'
                }
            }
        };
        window.fetch = async (_url, options) => {
            calls.push({
                action: options.body.get('action'),
                idea: options.body.get('idea')
            });
            return new Response(JSON.stringify(response), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            });
        };

        const state = {};
        const appState = {
            getStateSlice(key) { return state[key]; },
            setStateSlice(key, value) { state[key] = value; }
        };
        const view = new ExpressView(appState);
        const controller = Object.create(WizardFlowController.prototype);
        controller.appState = appState;
        controller.api = new ApiClient({ ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'fixture' });
        controller.registry = { get(step) { return step === 'express' ? view : null; } };
        controller.pendingSteps = new Set();
        controller.articleLengthFields = () => ({});
        controller.setGenerationBusy = (step, busy) => {
            if (busy) controller.pendingSteps.add(step);
            else controller.pendingSteps.delete(step);
        };

        await controller.expressGenerate();

        const notice = document.querySelector('[data-testid="article-quality-notice"]');
        const results = document.querySelector('.results-section');
        return {
            calls,
            state: view.state,
            conversationId: state.conversationId,
            title: document.querySelector('#express-stream-output h1')?.textContent || '',
            advisory: notice?.textContent || '',
            noticeParent: notice?.parentElement?.className || '',
            noticeImmediatelyBeforeResults: notice?.nextElementSibling === results,
            resultsHidden: results.classList.contains('hidden'),
            refineHidden: document.querySelector('[data-action="express-refine"]').hidden,
            errorVisible: Boolean(document.querySelector('[data-testid="step-error"]:not([hidden])'))
        };
    });

    assert.deepEqual(result.calls, [
        { action: 'ai_scribe_run_express', idea: 'web design tips for this year' }
    ], 'the exact successful response completes after one Express request, without a provider repeat');
    assert.equal(result.state, 'ready');
    assert.equal(result.conversationId, 246);
    assert.equal(result.title, 'A complete retained article');
    assert.match(result.advisory, /45 words below the preferred range/);
    assert.equal(result.noticeParent, 'express-content-container');
    assert.equal(result.noticeImmediatelyBeforeResults, true);
    assert.equal(result.resultsHidden, false);
    assert.equal(result.refineHidden, false);
    assert.equal(result.errorVisible, false);
    assert.deepEqual(pageErrors, [], 'WebKit reports no NotFoundError or other uncaught runtime error');

    console.log('Express exact-response advisory browser (WebKit): one request, retained article and nested advisory passed');
} finally {
    await browser.close();
}
