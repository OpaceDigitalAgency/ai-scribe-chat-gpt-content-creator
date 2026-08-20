import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const source = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const browser = await chromium.launch({ headless: true });

const override = (scope) => `<section data-step-panel="${scope}">
  <div class="article-length-override">
    <select data-article-length-mode>
      <option value="global">Use global default</option><option value="auto">Auto</option>
      <option value="concise">Concise</option><option value="standard">Standard</option>
      <option value="in_depth">In-depth</option><option value="custom">Custom</option>
    </select>
    <div data-custom-word-count hidden><input data-article-word-count value="1800"></div>
  </div>
  <p data-article-plan-summary></p>
</section>`;

try {
    const page = await browser.newPage({ viewport: { width: 1000, height: 800 } });
    await page.setContent(`<!doctype html><html><body><main id="ai-scribe-root">
      ${override('1')}${override('express')}
      <section data-step-panel="10"><div data-wizard-target-status hidden>
        <strong data-target-status-heading></strong><span data-target-status-detail></span>
        <span data-target-status-bar></span><div data-target-status-action hidden></div>
      </div></section>
    </main></body></html>`);
    await page.addScriptTag({ content: `window.ai_scribe={contentSettings:{article_length_mode:'standard',article_word_count:1800,number_of_headings:5},checkArr:{addQNA:false},i18n:{}};` });
    await page.addScriptTag({ content: source });

    const result = await page.evaluate(async () => {
        const state = {
            currentStep: 10,
            conversationId: 77,
            stepData: { 3: { selection: ['First section'] } },
            cost: {}
        };
        const appState = {
            getStateSlice(key) { return state[key]; },
            setStateSlice(key, value) { state[key] = value; },
            subscribe() {}
        };
        const article = `<h1>Saved title</h1><h2>First section</h2><p>${Array.from({ length: 1700 }, () => 'word').join(' ')}</p>`;
        const review = { getSelection: () => article };
        const registry = { get(step) { return Number(step) === 10 ? review : null; } };
        const api = { getState: async () => ({
            settings: { idea: 'Saved article', article_length_mode: 'custom', article_word_count: 2200 },
            steps: {}, selections: {}, cost: {}
        }) };
        const controller = new WizardFlowController(appState, api, registry);
        controller.applyStepLocks = () => {};
        controller.unlockThrough = () => {};
        controller.navigateToStep = () => controller.renderWizardLengthStatus();
        await controller.hydrateFromServer();

        const snapshot = (scope) => {
            const panel = document.querySelector(`[data-step-panel="${scope}"]`);
            return {
                mode: panel.querySelector('[data-article-length-mode]').value,
                count: panel.querySelector('[data-article-word-count]').value,
                customHidden: panel.querySelector('[data-custom-word-count]').hidden,
                summary: panel.querySelector('[data-article-plan-summary]').textContent
            };
        };
        const card = document.querySelector('[data-wizard-target-status]');
        return {
            wizard: snapshot('1'), express: snapshot('express'),
            detail: card.querySelector('[data-target-status-detail]').textContent,
            improveHidden: card.querySelector('[data-target-status-action]').hidden
        };
    });

    for (const screen of [result.wizard, result.express]) {
        assert.equal(screen.mode, 'custom', 'saved mode replaces conflicting global default');
        assert.equal(screen.count, '2200', 'saved target replaces conflicting global target');
        assert.equal(screen.customHidden, false, 'saved custom target is visible after reload');
        assert.match(screen.summary, /2,200/, 'entry-screen plan uses the saved target');
    }
    assert.match(result.detail, /Target 2,200 · preferred range 1,925–2,475/,
        'restored Review length card uses the saved conversation target');
    assert.equal(result.improveHidden, false, 'restored under-target Review retains its Improve action');
} finally {
    await browser.close();
}

console.log('Article length hydration: Wizard, Express and Review restored custom 2,200 over global 1,800.');
