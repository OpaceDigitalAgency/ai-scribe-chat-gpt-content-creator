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
            <section data-step-panel="1" data-state="ready">
                <input data-testid="idea-input">
                <div id="titles-options"><article>stale result</article></div>
                <div class="results-section"></div>
            </section>
            <textarea data-testid="prompt-editor"></textarea>
        </main>
    </body></html>`);
    await page.addScriptTag({ content: `
        class StepViewRegistry {
            static isStreamingStep() { return false; }
        }
        window.StepViewRegistry = StepViewRegistry;
        window.ai_scribe = { checkArr: {}, i18n: { stepNames: { 1: 'Title Generation' } } };
    ` });
    await page.addScriptTag({ content: controllerSource });
    await page.addScriptTag({ content: `
        window.makeTopicHarness = function makeTopicHarness(config) {
            document.querySelector('[data-testid="idea-input"]').value = config.visibleTopic;
            document.querySelector('#titles-options').innerHTML = '<article>stale result</article>';
            document.querySelector('.results-section').classList.remove('hidden');
            const prompt = document.querySelector('[data-testid="prompt-editor"]');
            prompt.value = config.stalePrompt || '';
            prompt.defaultValue = config.stalePrompt || '';

            const state = {
                currentStep: 1,
                conversationId: config.conversationId,
                stepData: { 1: { selection: 'Old result' } },
                cost: {}
            };
            const appState = {
                getStateSlice(key) { return state[key]; },
                setStateSlice(key, value) { state[key] = value; },
                subscribe() { return function () {}; },
                reset() {
                    Object.keys(state).forEach((key) => delete state[key]);
                    Object.assign(state, { currentStep: 1, conversationId: null, stepData: {}, cost: {} });
                }
            };
            const rendered = [];
            const errors = [];
            const titleView = {
                state: 'ready',
                panel: document.querySelector('[data-step-panel="1"]'),
                topicInput: document.querySelector('[data-testid="idea-input"]'),
                getTopic() { return this.topicInput.value.trim(); },
                renderTyped(items) {
                    rendered.push((items.titles || []).slice());
                    document.querySelector('#titles-options').textContent = (items.titles || []).join('|');
                    this.state = 'ready';
                },
                showLoading() { this.state = 'loading'; },
                showError(error) { errors.push(error.message); this.state = 'error'; },
                updateProgressStage() {},
                focusPanel() {},
                setNextEnabled() {},
                hasSelection() { return true; },
                persistSelection() {},
                t(key) { return key; }
            };
            const registry = { get(step) { return step === 1 ? titleView : null; } };
            const calls = { getState: [], start: [], startSnapshots: [], run: [] };
            let getStateCount = 0;
            const api = {
                getState(id) {
                    calls.getState.push(id);
                    getStateCount += 1;
                    return config.getState(id, getStateCount);
                },
                startConversation(fields) {
                    calls.start.push(Object.assign({}, fields));
                    calls.startSnapshots.push({
                        resultText: document.querySelector('#titles-options').textContent,
                        promptText: document.querySelector('[data-testid="prompt-editor"]').value,
                        stepData: JSON.parse(JSON.stringify(state.stepData || {}))
                    });
                    return Promise.resolve({ conversation_id: config.newConversationId || 202 });
                },
                runStep(id, step, extras) {
                    calls.run.push({ id, step, extras: Object.assign({}, extras) });
                    return Promise.resolve({
                        conversation_id: id,
                        parsed: { titles: [config.newResult || 'New idea result'] },
                        prompt_used: config.newPrompt || ('Prompt for ' + config.visibleTopic)
                    });
                },
                abortAllStreams() {}
            };
            const controller = new WizardFlowController(appState, api, registry);
            controller.applyStepLocks = function () {};
            controller.renderProgress = function () {};
            controller.refreshCostEstimate = function () {};
            controller.updateReviewActions = function () { return false; };
            controller.restoreSelections = function () {};
            controller.conversationIdea = config.conversationIdea || '';
            if (config.stalePrompt) {
                controller.stepPrompts.set(1, config.stalePrompt);
            }
            return { controller, state, calls, rendered, errors, titleView };
        };
        window.waitForTopicTest = async function waitForTopicTest(predicate) {
            for (let i = 0; i < 100; i += 1) {
                if (predicate()) return;
                await new Promise((resolve) => setTimeout(resolve, 0));
            }
            throw new Error('Timed out waiting for topic regression condition');
        };
    ` });

    const unchanged = await page.evaluate(async () => {
        const recovered = {
            settings: { idea: 'seo tips for this year' },
            steps: { 1: { status: 'complete', parsed: { titles: ['Recovered result'] }, prompt_used: 'Recovered prompt' } },
            selections: {},
            cost: {}
        };
        const harness = window.makeTopicHarness({
            visibleTopic: ' SEO   TIPS for this year ',
            conversationId: 101,
            getState: () => Promise.resolve(recovered),
            newResult: 'Refreshed result',
            newPrompt: 'Refreshed same-topic prompt'
        });
        await harness.controller.hydrateFromServer();
        await harness.controller.generate(1, { append: false });
        await window.waitForTopicTest(() => harness.calls.run.length === 1 && !harness.controller.pendingSteps.has(1));
        return {
            conversationId: harness.state.conversationId,
            starts: harness.calls.start,
            startSnapshots: harness.calls.startSnapshots,
            runs: harness.calls.run,
            getState: harness.calls.getState,
            idea: harness.controller.conversationIdea
        };
    });
    assert.equal(unchanged.conversationId, 101, 'same recovered topic keeps its conversation');
    assert.equal(unchanged.starts.length, 0, 'same recovered topic starts no replacement conversation');
    assert.deepEqual(unchanged.runs.map((run) => run.id), [101]);
    assert.equal(unchanged.idea, 'seo tips for this year');

    const changed = await page.evaluate(async () => {
        const harness = window.makeTopicHarness({
            visibleTopic: 'seo tips for this year',
            conversationId: 101,
            conversationIdea: 'dgfdf',
            stalePrompt: 'Prompt for dgfdf',
            getState: () => Promise.resolve({ settings: { idea: 'dgfdf' }, steps: {}, selections: {}, cost: {} }),
            newConversationId: 202,
            newResult: 'Current SEO result',
            newPrompt: 'Prompt for seo tips for this year'
        });
        harness.controller.handleTopicInputChange();
        const promptAfterInput = document.querySelector('[data-testid="prompt-editor"]').value;
        await harness.controller.generate(1, { append: false });
        await window.waitForTopicTest(() => harness.calls.run.length === 1 && !harness.controller.pendingSteps.has(1));
        return {
            promptAfterInput,
            finalPrompt: document.querySelector('[data-testid="prompt-editor"]').value,
            resultText: document.querySelector('#titles-options').textContent,
            resultWasHiddenDuringReset: document.querySelector('.results-section').classList.contains('hidden'),
            stepData: harness.state.stepData,
            conversationId: harness.state.conversationId,
            starts: harness.calls.start,
            startSnapshots: harness.calls.startSnapshots,
            runs: harness.calls.run,
            rendered: harness.rendered
        };
    });
    assert.equal(changed.promptAfterInput, '', 'editing the topic removes the stale recovered prompt immediately');
    assert.deepEqual(changed.starts, [{ idea: 'seo tips for this year' }]);
    assert.deepEqual(changed.startSnapshots, [{ resultText: '', promptText: '', stepData: {} }],
        'old results, prompt and derived state are cleared before starting the new conversation');
    assert.equal(changed.conversationId, 202);
    assert.deepEqual(changed.runs.map((run) => ({ id: run.id, step: run.step })), [{ id: 202, step: 1 }]);
    assert.ok(!JSON.stringify(changed.runs).includes('dgfdf'), 'old idea never reaches runStep');
    assert.equal(changed.finalPrompt, 'Prompt for seo tips for this year');
    assert.equal(changed.resultText, 'Current SEO result');
    assert.deepEqual(changed.rendered, [['Current SEO result']]);

    const race = await page.evaluate(async () => {
        let releaseHydration;
        const lateOldState = new Promise((resolve) => { releaseHydration = resolve; });
        const oldState = {
            settings: { idea: 'dgfdf' },
            steps: { 1: { status: 'complete', parsed: { titles: ['STALE OLD RESULT'] }, prompt_used: 'STALE OLD PROMPT' } },
            selections: {},
            cost: {}
        };
        const harness = window.makeTopicHarness({
            visibleTopic: 'seo tips for this year',
            conversationId: 101,
            stalePrompt: 'Prompt for dgfdf',
            getState: (id, call) => call === 1 ? lateOldState : Promise.resolve(oldState),
            newConversationId: 303,
            newResult: 'RACE-SAFE NEW RESULT',
            newPrompt: 'RACE-SAFE NEW PROMPT'
        });
        const hydration = harness.controller.hydrateFromServer();
        const generation = harness.controller.generate(1, { append: false });
        await generation;
        await window.waitForTopicTest(() => harness.calls.run.length === 1 && !harness.controller.pendingSteps.has(1));
        releaseHydration(oldState);
        await hydration;
        return {
            conversationId: harness.state.conversationId,
            conversationIdea: harness.controller.conversationIdea,
            starts: harness.calls.start,
            runs: harness.calls.run,
            rendered: harness.rendered,
            resultText: document.querySelector('#titles-options').textContent,
            promptText: document.querySelector('[data-testid="prompt-editor"]').value
        };
    });
    assert.equal(race.conversationId, 303, 'late hydration cannot restore the superseded conversation id');
    assert.equal(race.conversationIdea, 'seo tips for this year');
    assert.deepEqual(race.starts, [{ idea: 'seo tips for this year' }]);
    assert.deepEqual(race.runs.map((run) => run.id), [303]);
    assert.ok(!JSON.stringify(race.runs).includes('dgfdf'), 'race sends no old idea to runStep');
    assert.deepEqual(race.rendered, [['RACE-SAFE NEW RESULT']], 'late old hydration never repaints stale results');
    assert.equal(race.resultText, 'RACE-SAFE NEW RESULT');
    assert.equal(race.promptText, 'RACE-SAFE NEW PROMPT');

    console.log('Topic conversation regression: unchanged reuse, changed-topic reset/new conversation, and early Generate hydration race passed');
} finally {
    await browser.close();
}
