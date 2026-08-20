import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8')
].join('\n');
const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const baseSource = fs.readFileSync('assets/js/views/steps/BaseStepView.js', 'utf8');
const streamingSource = fs.readFileSync('assets/js/views/steps/StreamingStepView.js', 'utf8');
const bodySource = fs.readFileSync('assets/js/views/steps/BodyStepView.js', 'utf8');
const browser = await chromium.launch({ headless: process.env.CI === 'true' });

const statusCard = (scope) => `<div class="article-target-status wizard-target-status" data-wizard-target-status data-length-scope="${scope}" hidden>
  <div class="article-target-status-copy"><strong data-target-status-heading></strong><span data-target-status-detail></span></div>
  <div class="article-target-track"><span data-target-status-bar></span></div>
  <div class="article-target-action" data-target-status-action hidden><div><strong>Improve this draft</strong><p data-improve-length-status></p></div>
    <button class="btn btn-outline" data-action="wizard-improve-length"><span data-improve-length-label>Improve length</span></button></div>
</div>`;

try {
    for (const width of [375, 768, 1440]) {
        for (const step of [6, 10]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.setContent(`<!doctype html><html><head><style>${css}</style></head><body><main id="ai-scribe-root" class="ai-scribe-app">
              <section class="step-content active" data-step-panel="${step}">${statusCard(step === 6 ? 'body' : 'review')}<div class="quill-editor-container"><div class="ql-editor"></div></div></section>
            </main></body></html>`);
            await page.addScriptTag({ content: 'window.ai_scribe={contentSettings:{article_length_mode:"custom",article_word_count:2200,number_of_headings:1},checkArr:{addQNA:false},i18n:{}};' });
            await page.addScriptTag({ content: controllerSource });
            const result = await page.evaluate(async ({ step }) => {
                let html = step === 10
                    ? `<h1>Owner title</h1><p><em>Owner tagline</em></p><h2>First section</h2><p>${Array.from({ length: 1711 }, () => 'word').join(' ')}</p>`
                    : `<h2>First section</h2><p>${Array.from({ length: 1714 }, () => 'word').join(' ')}</p>`;
                const original = html;
                const subscribers = [];
                const slices = {
                    currentStep: step,
                    conversationId: 91,
                    stepData: { 3: { selection: ['First section'] }, 6: { qualityPlan: { target_words: 2200, minimum_words: 1925, maximum_words: 2475 } } }
                };
                const appState = {
                    getStateSlice(key) { return slices[key]; },
                    setStateSlice(key, value) { slices[key] = value; subscribers.forEach((fn) => fn(slices)); },
                    subscribe(fn) { subscribers.push(fn); }
                };
                let calls = 0;
                const view = {
                    getSelection() { return html; },
                    enforceOutlineCoverage() { return true; },
                    renderContent(value) { html = value; },
                    renderImprovedHtml(value) { html = value; return true; }
                };
                const api = {
                    async improveWizardLength(id, current, bodyOnly) {
                        calls += 1;
                        if (id !== 91 || current !== html || bodyOnly !== (step === 6)) throw new Error('wrong request');
                        return {
                            improved_html: current.replace('</p>', ` owner-edit-preserved ${Array.from({ length: 199 }, () => 'added').join(' ')}</p>`),
                            quality_plan: { word_count: 1916, target_words: 2200, minimum_words: 1925, maximum_words: 2475 },
                            improvement: { message: '200 useful words were added.' }
                        };
                    }
                };
                const registry = { get(key) { return Number(key) === step ? view : null; } };
                const controller = new WizardFlowController(appState, api, registry);
                controller.renderWizardLengthStatus();
                const before = document.querySelector('[data-wizard-target-status]').innerText;
                document.querySelector('[data-action="wizard-improve-length"]').click();
                await new Promise((resolve) => setTimeout(resolve, 0));
                const card = document.querySelector('[data-wizard-target-status]');
                return {
                    before,
                    after: card.innerText,
                    calls,
                    preserved: html.includes('owner-edit-preserved')
                        && html.replace(/ owner-edit-preserved(?: added){199}/, '') === original,
                    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
                    buttonWidth: card.querySelector('button').getBoundingClientRect().width,
                    cardWidth: card.getBoundingClientRect().width
                };
            }, { step });
            const expectedWords = step === 10 ? 1717 : 1716;
            const expectedDifference = 2200 - expectedWords;
            assert.match(result.before, new RegExp(`${expectedWords.toLocaleString()} words`));
            assert.match(result.before, new RegExp(`Target 2,200 · preferred range 1,925–2,475 · ${expectedDifference} words to target`));
            assert.equal(result.calls, 1, `step ${step} at ${width}px makes one request`);
            assert.equal(result.preserved, true, `step ${step} at ${width}px preserves visible draft text`);
            assert.match(result.after, new RegExp(`${(expectedWords + 200).toLocaleString()} words`));
            assert.equal(result.overflow, false, `step ${step} at ${width}px has no page overflow`);
            assert.ok(result.buttonWidth <= result.cardWidth, `step ${step} at ${width}px keeps action inside card`);
            await page.close();
        }
    }

    const loading = await browser.newPage({ viewport: { width: 375, height: 812 } });
    await loading.setContent(`<!doctype html><html><head><style>${css}</style></head><body><main id="ai-scribe-root" class="ai-scribe-app"><section data-step-panel="10">${statusCard('review')}</section></main></body></html>`);
    await loading.addScriptTag({ content: 'window.ai_scribe={contentSettings:{article_length_mode:"custom",article_word_count:2200},checkArr:{addQNA:false},i18n:{}};' });
    await loading.addScriptTag({ content: controllerSource });
    const loadingState = await loading.evaluate(async () => {
        let resolveRequest;
        const request = new Promise((resolve) => { resolveRequest = resolve; });
        const html = `<h2>First section</h2><p>${Array.from({ length: 1700 }, () => 'word').join(' ')}</p>`;
        const slices = { currentStep: 10, conversationId: 18, stepData: { 3: { selection: ['First section'] } } };
        const appState = { getStateSlice: (key) => slices[key], setStateSlice() {}, subscribe() {} };
        const view = { getSelection: () => html, renderImprovedHtml() {} };
        const controller = new WizardFlowController(appState, { improveWizardLength: () => request }, { get: () => view });
        controller.renderWizardLengthStatus();
        document.querySelector('[data-action="wizard-improve-length"]').click();
        await new Promise((resolve) => setTimeout(resolve, 0));
        const progress = document.querySelector('[data-improve-length-progress]');
        const result = {
            visible: progress && !progress.hidden,
            live: progress && progress.getAttribute('aria-live'),
            copy: progress && progress.innerText,
            busy: document.querySelector('[data-action="wizard-improve-length"]').getAttribute('aria-busy'),
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
        };
        resolveRequest({ improved_html: html, quality_plan: { target_words: 2200, minimum_words: 1925, maximum_words: 2475 }, improvement: { message: 'Draft retained.' } });
        await new Promise((resolve) => setTimeout(resolve, 0));
        return { ...result, hiddenAfter: progress.hidden, timerNullAfter: controller.improvementProgressTimer === null };
    });
    assert.equal(loadingState.visible, true, 'Wizard improvement exposes a visible progress region while its one request is pending');
    assert.equal(loadingState.live, 'polite', 'Wizard improvement progress is announced without interrupting the editor');
    assert.match(loadingState.copy, /Distributing concise detail across existing sections/);
    assert.equal(loadingState.busy, 'true');
    assert.equal(loadingState.overflow, false, 'Wizard improvement progress fits a 375px viewport');
    assert.equal(loadingState.hiddenAfter, true, 'Wizard improvement progress stops after the request finishes');
    assert.equal(loadingState.timerNullAfter, true, 'Wizard improvement clears its elapsed timer after success');
    await loading.emulateMedia({ reducedMotion: 'reduce' });
    const reducedMotion = await loading.evaluate(() => {
        const progress = document.querySelector('[data-improve-length-progress]');
        progress.hidden = false;
        return {
            spinner: getComputedStyle(progress.querySelector('.article-improvement-spinner')).animationName,
            track: getComputedStyle(progress.querySelector('.article-improvement-track span')).animationName
        };
    });
    assert.equal(reducedMotion.spinner, 'none', 'Reduced-motion preference disables spinner animation');
    assert.equal(reducedMotion.track, 'none', 'Reduced-motion preference disables indeterminate track animation');
    await loading.close();

    const canonicalBody = await browser.newPage({ viewport: { width: 768, height: 900 } });
    await canonicalBody.setContent('<main class="ai-scribe-app"><section data-step-panel="6"><div data-testid="article-quality-notice"><p data-quality-message></p></div><div class="results-section"><div id="body-stream-output"></div><div id="body-quill-editor"></div></div></section></main>');
    await canonicalBody.addScriptTag({ content: 'window.ai_scribe={i18n:{}};window.lucide={createIcons(){}};' });
    await canonicalBody.addScriptTag({ content: baseSource });
    await canonicalBody.addScriptTag({ content: streamingSource });
    await canonicalBody.addScriptTag({ content: bodySource });
    const canonicalBodyState = await canonicalBody.evaluate(() => {
        const view = new BodyStepView({ getStateSlice() { return null; }, setStateSlice() {} });
        view.renderContent('<h2>Section</h2><p>Current normalised editor copy.</p>', {
            quality_plan: { advisory: true, word_count: 1140, message: 'Stale server count.' }
        });
        return {
            staleHidden: document.querySelector('[data-testid="article-quality-notice"]').hidden,
            currentCopy: document.querySelector('#body-stream-output').textContent
        };
    });
    assert.equal(canonicalBodyState.staleHidden, true, 'Body suppresses the stale pre-normalisation advisory count in favour of its live target card');
    assert.match(canonicalBodyState.currentCopy, /Current normalised editor copy/);
    await canonicalBody.close();

    const failure = await browser.newPage({ viewport: { width: 375, height: 812 } });
    await failure.setContent(`<!doctype html><html><head><style>${css}</style></head><body><main id="ai-scribe-root" class="ai-scribe-app"><section data-step-panel="10">${statusCard('review')}</section></main></body></html>`);
    await failure.addScriptTag({ content: 'window.ai_scribe={contentSettings:{article_length_mode:"standard"},checkArr:{addQNA:false},i18n:{}};' });
    await failure.addScriptTag({ content: controllerSource });
    const failed = await failure.evaluate(async () => {
        let html = '<h2>First section</h2><p>Exact owner review edit.</p>';
        const before = html;
        const slices = { currentStep: 10, conversationId: 4, stepData: { 3: { selection: ['First section'] } } };
        const appState = { getStateSlice: (key) => slices[key], setStateSlice() {}, subscribe() {} };
        const view = { getSelection: () => html, renderImprovedHtml(value) { html = value; } };
        const controller = new WizardFlowController(appState, { improveWizardLength: async () => { throw new Error('provider unavailable'); } }, { get: () => view });
        controller.renderWizardLengthStatus();
        document.querySelector('[data-action="wizard-improve-length"]').click();
        await new Promise((resolve) => setTimeout(resolve, 0));
        return { unchanged: html === before, text: document.querySelector('[data-improve-length-status]').textContent, label: document.querySelector('[data-improve-length-label]').textContent, timerNull: controller.improvementProgressTimer === null };
    });
    assert.equal(failed.unchanged, true, 'failed Review improvement retains the visible draft');
    assert.match(failed.text, /existing draft is unchanged/);
    assert.equal(failed.label, 'Try improvement again');
    assert.equal(failed.timerNull, true, 'Wizard improvement clears its elapsed timer after failure');
    await failure.close();

    const reset = await browser.newPage({ viewport: { width: 375, height: 812 } });
    await reset.setContent(`<!doctype html><html><head><style>${css}</style></head><body><main id="ai-scribe-root" class="ai-scribe-app"><section data-step-panel="10">${statusCard('review')}</section><section data-step-panel="1"></section></main></body></html>`);
    await reset.addScriptTag({ content: 'window.ai_scribe={contentSettings:{article_length_mode:"standard"},checkArr:{addQNA:false},i18n:{}};' });
    await reset.addScriptTag({ content: controllerSource });
    const resetResult = await reset.evaluate(async () => {
        let resolveRequest;
        const request = new Promise((resolve) => { resolveRequest = resolve; });
        let html = '<h2>First section</h2><p>Exact owner draft.</p>';
        let repaints = 0;
        const slices = { currentStep: 10, conversationId: 9, stepData: { 3: { selection: ['First section'] } } };
        const appState = {
            getStateSlice: (key) => slices[key],
            setStateSlice(key, value) { slices[key] = value; },
            subscribe() {},
            reset() { slices.currentStep = 1; slices.conversationId = null; slices.stepData = {}; }
        };
        const view = { getSelection: () => html, renderImprovedHtml(value) { repaints += 1; html = value; } };
        const controller = new WizardFlowController(appState, { improveWizardLength: () => request }, { get: (step) => Number(step) === 10 ? view : null });
        controller.clearAuthoredContent = () => {};
        controller.resetWorkflowViews = () => {};
        controller.applyStepLocks = () => {};
        controller.navigateToStep = () => {};
        controller.updateReviewActions = () => {};
        controller.renderWizardLengthStatus();
        document.querySelector('[data-action="wizard-improve-length"]').click();
        await new Promise((resolve) => setTimeout(resolve, 0));
        controller.resetForNewTopic('New topic');
        const progress = document.querySelector('[data-improve-length-progress]');
        const resetState = {
            timerNull: controller.improvementProgressTimer === null,
            progressHidden: progress.hidden,
            lockCleared: !controller.pendingSteps.has('wizard-improve-length'),
            buttonBusy: document.querySelector('[data-action="wizard-improve-length"]').getAttribute('aria-busy')
        };
        resolveRequest({ improved_html: '<h2>Stale response</h2><p>Must never repaint.</p>', quality_plan: {}, improvement: {} });
        await new Promise((resolve) => setTimeout(resolve, 0));
        return { ...resetState, repaints, html };
    });
    assert.equal(resetResult.timerNull, true, 'Topic reset clears the improvement timer');
    assert.equal(resetResult.progressHidden, true, 'Topic reset hides improvement progress');
    assert.equal(resetResult.lockCleared, true, 'Topic reset clears the improvement request lock');
    assert.equal(resetResult.buttonBusy, 'false', 'Topic reset restores the improvement button');
    assert.equal(resetResult.repaints, 0, 'A stale improvement response cannot repaint after topic reset');
    assert.match(resetResult.html, /Exact owner draft/);
    await reset.close();

    const noOutline = await browser.newPage({ viewport: { width: 375, height: 812 } });
    await noOutline.setContent(`<!doctype html><html><head><style>${css}</style></head><body><main id="ai-scribe-root" class="ai-scribe-app"><section data-step-panel="10">${statusCard('review')}</section></main></body></html>`);
    await noOutline.addScriptTag({ content: 'window.ai_scribe={contentSettings:{article_length_mode:"standard"},checkArr:{addQNA:false},i18n:{}};' });
    await noOutline.addScriptTag({ content: controllerSource });
    const skippedOutline = await noOutline.evaluate(() => {
        const slices = { currentStep: 10, conversationId: 5, stepData: { 3: { selection: [] } } };
        const appState = { getStateSlice: (key) => slices[key], setStateSlice() {}, subscribe() {} };
        let calls = 0;
        const view = { getSelection: () => '<h2>Generated section</h2><p>Short usable draft.</p>' };
        const controller = new WizardFlowController(appState, { improveWizardLength: async () => { calls += 1; } }, { get: () => view });
        controller.renderWizardLengthStatus();
        const card = document.querySelector('[data-wizard-target-status]');
        return {
            actionHidden: card.querySelector('[data-target-status-action]').hidden,
            detail: card.querySelector('[data-target-status-detail]').textContent,
            calls
        };
    });
    assert.equal(skippedOutline.actionHidden, true, 'Improve action stays unavailable without a selected outline');
    assert.match(skippedOutline.detail, /Select an outline before generating/);
    assert.equal(skippedOutline.calls, 0, 'No provider request is offered without an insertion contract');
    await noOutline.close();
} finally {
    await browser.close();
}

console.log('Wizard Body and Review length cards passed at 375px, 768px and 1440px.');
