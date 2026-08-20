import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync(
    new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url),
    'utf8'
);
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1100, height: 760 } });
    await page.setContent(`<!doctype html><html><body>
        <main id="ai-scribe-root">
            <nav id="step-navigation">
                <button data-action="nav-step" data-step="1"></button>
                <button data-action="nav-step" data-step="2"></button>
            </nav>
            <section data-step-panel="1" class="active" data-state="ready">
                <h2>Title Generation</h2>
                <input data-testid="idea-input" value="old topic">
                <div class="results-section"><div id="titles-options">Old titles</div></div>
            </section>
            <section data-step-panel="2" data-state="ready" hidden>
                <h2>Keyword Research</h2>
                <div class="idle-state" hidden></div>
                <div class="results-section"><div id="keywords-options">Old keywords</div></div>
            </section>
            <section data-step-panel="express" data-state="ready" hidden>
                <input data-testid="express-topic" value="old express topic">
                <div class="results-section"><article id="express-output">Old Express article</article><button data-action="express-refine">Refine</button></div>
            </section>
            <textarea data-testid="prompt-editor">Old prompt</textarea>
        </main>
    </body></html>`);
    await page.addScriptTag({ content: `
        class StepViewRegistry {
            static isStreamingStep() { return false; }
        }
        window.StepViewRegistry = StepViewRegistry;
        window.ReviewStepView = { normaliseInPageLinks(value) { return value; } };
        window.ai_scribe = { checkArr: {}, i18n: { stepNames: { 1: 'Title Generation', 2: 'Keyword Research' } } };
    ` });
    await page.addScriptTag({ content: controllerSource });
    await page.addScriptTag({ content: `
        window.runStartAgainTransition = async function runStartAgainTransition() {
            const state = { currentStep: 1, conversationId: 91, stepData: {}, cost: {} };
            const appState = {
                getStateSlice(key) { return state[key]; },
                setStateSlice(key, value) { state[key] = value; },
                subscribe() {},
                reset() { Object.assign(state, { currentStep: 1, conversationId: null, stepData: {}, cost: {} }); }
            };
            function makeView(step, selector) {
                const panel = document.querySelector('[data-step-panel="' + step + '"]');
                return {
                    step,
                    panel,
                    resultsSection: panel.querySelector('.results-section'),
                    container: panel.querySelector(selector),
                    items: ['old'],
                    skipped: true,
                    state: 'ready',
                    setState(next) { this.state = next; this.panel.dataset.state = next; },
                    setNextEnabled() {},
                    showIdle() { this.setState('idle'); },
                    focusPanel() {}
                };
            }
            const title = makeView(1, '#titles-options');
            title.topicInput = document.querySelector('[data-testid="idea-input"]');
            title.getTopic = function () { return this.topicInput.value.trim(); };
            const keywords = makeView(2, '#keywords-options');
            const expressPanel = document.querySelector('[data-step-panel="express"]');
            const express = {
                panel: expressPanel,
                resultsSection: expressPanel.querySelector('.results-section'),
                topicInput: expressPanel.querySelector('[data-testid="express-topic"]'),
                output: expressPanel.querySelector('#express-output'),
                article: { title: 'Old Express article' },
                state: 'ready',
                setState(next) { this.state = next; this.panel.dataset.state = next; }
            };
            const registry = { get(step) { return step === 1 ? title : (step === 2 ? keywords : (step === 'express' ? express : null)); } };
            const api = { abortAllStreams() {} };
            const controller = new WizardFlowController(appState, api, registry);
            controller.renderCostMeter = function () {};
            controller.refreshCostEstimate = function () {};
            controller.updateReviewActions = function () {};
            const generated = [];
            controller.generate = function (step) { generated.push(step); };

            controller.startAgain();
            const afterReset = {
                keywordState: keywords.state,
                keywordHidden: keywords.resultsSection.classList.contains('hidden'),
                keywordText: keywords.container.textContent,
                keywordItems: keywords.items.slice(),
                keywordSkipped: keywords.skipped
                , expressState: express.state
                , expressTopic: express.topicInput.value
                , expressOutput: express.output.textContent
                , expressArticle: express.article
                , expressHidden: express.resultsSection.classList.contains('hidden')
                , expressRefineHidden: express.panel.querySelector('[data-action="express-refine"]').hidden
            };

            title.topicInput.value = 'web design tips for this year';
            title.state = 'ready';
            title.resultsSection.classList.remove('hidden');
            await controller.continueFromStep(1);
            return {
                afterReset,
                generated,
                currentStep: state.currentStep,
                keywordPanelActive: keywords.panel.classList.contains('active')
            };
        };
    ` });

    const result = await page.evaluate(() => window.runStartAgainTransition());
    assert.deepEqual(result.afterReset, {
        keywordState: 'idle',
        keywordHidden: true,
        keywordText: '',
        keywordItems: [],
        keywordSkipped: false
        , expressState: 'idle'
        , expressTopic: ''
        , expressOutput: ''
        , expressArticle: null
        , expressHidden: true
        , expressRefineHidden: true
    }, 'Start Again resets hidden results and the view state machine together');
    assert.deepEqual(result.generated, [2], 'Continue from the new title automatically starts Keyword Research');
    assert.equal(result.currentStep, 2);
    assert.equal(result.keywordPanelActive, true);

    console.log('Start Again transition: stale ready state is cleared and Keyword Research auto-generates');
} finally {
    await browser.close();
}
