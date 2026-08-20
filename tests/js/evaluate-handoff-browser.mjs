import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync(
    new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url),
    'utf8'
);
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1000, height: 700 } });
    await page.setContent(`<!doctype html><html><body>
        <main id="ai-scribe-root">
            <section data-step-panel="10" class="active">
                <div class="ql-editor"><h1>Visible review</h1><p><img src="https://example.test/visible.png" alt="Visible diagram"></p></div>
            </section>
            <section data-step-panel="11" hidden></section>
            <textarea data-testid="prompt-editor"></textarea>
        </main>
    </body></html>`);
    await page.addScriptTag({ content: `
        class StepViewRegistry { static isStreamingStep() { return false; } }
        class ReviewStepView { static normaliseInPageLinks(html) { return html; } }
        window.StepViewRegistry = StepViewRegistry;
        window.ReviewStepView = ReviewStepView;
        window.ai_scribe = { checkArr: {} };
    ` });
    await page.addScriptTag({ content: controllerSource });

    const result = await page.evaluate(async () => {
        const state = { currentStep: 10, conversationId: 44, stepData: {} };
        const appState = {
            getStateSlice(key) { return state[key]; },
            setStateSlice(key, value) { state[key] = value; },
            subscribe() { return function () {}; }
        };
        const review = {
            state: 'ready',
            getSelection() {
                return '<h1>Stale view root</h1><p><img src="https://example.test/stale-view.png" alt="Wrong image"></p>';
            }
        };
        const evaluate = { state: 'idle' };
        const registry = {
            get(step) {
                if (step === 10) return review;
                if (step === 11) return evaluate;
                return null;
            }
        };
        const controller = new WizardFlowController(appState, {}, registry);
        controller.unlockThrough = function () {};
        controller.navigateToStep = function (step) {
            // Simulate the exact failure mode: navigation hides/repaints Review
            // before Evaluate builds its request.
            state.currentStep = step;
            document.querySelector('[data-step-panel="10"] .ql-editor').innerHTML =
                '<h1>Hidden stale state</h1><p>No image remains here.</p>';
        };
        let sentExtras = null;
        controller.generate = function (step) {
            sentExtras = this.stepExtras({}, step);
        };

        const visibleBeforeContinue = document.querySelector(
            '[data-step-panel="10"].active .ql-editor'
        ).innerHTML;
        const staleViewSelection = review.getSelection();
        await controller.continueFromStep(10);
        return {
            visibleBeforeContinue,
            staleViewSelection,
            hiddenAfterNavigation: document.querySelector('[data-step-panel="10"] .ql-editor').innerHTML,
            pendingEvaluateHtml: controller.pendingEvaluateHtml,
            persistedReviewHtml: state.reviewEditedHtml,
            sentExtras
        };
    });

    const expected = '<h1>Visible review</h1><p><img src="https://example.test/visible.png" alt="Visible diagram"></p>';
    assert.equal(result.visibleBeforeContinue, expected);
    assert.match(result.staleViewSelection, /stale-view\.png/, 'fixture view root genuinely differs from active visible DOM');
    assert.equal(result.pendingEvaluateHtml, expected, 'Continue snapshots Review before navigation');
    assert.equal(result.persistedReviewHtml, expected, 'the same snapshot is retained in state');
    assert.notEqual(result.hiddenAfterNavigation, expected, 'fixture genuinely changes hidden Review DOM');
    assert.equal(result.sentExtras.content_html, expected, 'Evaluate sends the click-time snapshot, not later hidden DOM');
    assert.match(result.sentExtras.content_html, /<img\b[^>]*visible\.png/);
    assert.doesNotMatch(result.sentExtras.content_html, /Hidden stale state|No image remains here/);
    assert.doesNotMatch(result.sentExtras.content_html, /Stale view root|stale-view\.png|Wrong image/);

    console.log('Evaluate handoff browser: Review Continue snapshots visible image HTML before navigation and step 11 sends that snapshot');
} finally {
    await browser.close();
}
