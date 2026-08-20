import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync(
    new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url),
    'utf8'
);
const templateSource = fs.readFileSync(
    new URL('../../templates/create_template.php', import.meta.url),
    'utf8'
);

assert.match(templateSource, /data-action="run-amended-prompt"/,
    'the prompt editor renders its own explicit run action');
assert.match(templateSource, /role="status" aria-live="polite"/,
    'the prompt action exposes visible and assistive status feedback');
assert.match(controllerSource, /runAmendedPrompt\(step\)[\s\S]*?this\.generate\(step, \{ append: false \}\)/,
    'Run amended prompt replaces the current result rather than silently appending another set');

const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 420, height: 780 } });
    await page.setContent(`<!doctype html><html><body>
        <main id="ai-scribe-root">
            <section data-step-panel="11" data-state="ready"></section>
            <textarea data-testid="prompt-editor" aria-describedby="prompt-run-status"></textarea>
            <div class="prompt-run-actions">
                <button type="button" data-action="run-amended-prompt" data-testid="run-amended-prompt" disabled>
                    <span data-prompt-run-label>Run amended prompt</span>
                </button>
                <p data-testid="prompt-run-status" id="prompt-run-status" role="status" aria-live="polite"></p>
            </div>
        </main>
    </body></html>`);
    await page.addScriptTag({ content: `
        class StepViewRegistry {
            static isStreamingStep() { return false; }
            static isChoiceStep() { return false; }
        }
        window.StepViewRegistry = StepViewRegistry;
        window.ai_scribe = { checkArr: {}, noPromptSteps: [10], contentSettings: {} };
    ` });
    await page.addScriptTag({ content: controllerSource });
    await page.addScriptTag({ content: `
        window.makePromptHarness = function makePromptHarness() {
            const state = { currentStep: 11, conversationId: 77, cost: {} };
            const appState = {
                getStateSlice(key) { return state[key]; },
                setStateSlice(key, value) { state[key] = value; },
                subscribe() { return function () {}; }
            };
            const calls = [];
            const errors = [];
            let outcome = 'success';
            const view = {
                panel: document.querySelector('[data-step-panel="11"]'),
                showLoading() {},
                updateProgressStage() {},
                renderTyped() {},
                showError(error) { errors.push(error.message); }
            };
            const api = {
                runStep(id, step, extras) {
                    calls.push({ id, step, extras: Object.assign({}, extras) });
                    if (outcome === 'error') {
                        return Promise.reject(new Error('Deliberate prompt test failure'));
                    }
                    return new Promise((resolve) => {
                        window.releasePromptRun = () => resolve({
                            conversation_id: id,
                            parsed: { checks: [] },
                            prompt_used: extras.prompt_override,
                            cost: {}
                        });
                    });
                }
            };
            const registry = { get(step) { return step === 11 ? view : null; } };
            const controller = new WizardFlowController(appState, api, registry);
            controller.refreshCostEstimate = function () {};
            controller.renderCostMeter = function () {};
            controller.renderPersistenceState = function () {};
            controller.stepPrompts.set(11, 'Original evaluation prompt');
            controller.loadPromptEditor(11);
            return {
                controller,
                calls,
                errors,
                failNext() { outcome = 'error'; }
            };
        };
        window.promptHarness = window.makePromptHarness();
    ` });

    const initial = await page.evaluate(() => ({
        value: document.querySelector('[data-testid="prompt-editor"]').value,
        disabled: document.querySelector('[data-testid="run-amended-prompt"]').disabled,
        status: document.querySelector('[data-testid="prompt-run-status"]').textContent
    }));
    assert.deepEqual(initial, {
        value: 'Original evaluation prompt',
        disabled: true,
        status: 'Edit the prompt to enable this button.'
    });

    await page.locator('[data-testid="prompt-editor"]').fill('Amended evaluation prompt');
    const ready = await page.evaluate(() => ({
        disabled: document.querySelector('[data-testid="run-amended-prompt"]').disabled,
        status: document.querySelector('[data-testid="prompt-run-status"]').textContent
    }));
    assert.deepEqual(ready, {
        disabled: false,
        status: 'Your amended prompt is ready to run.'
    });

    await page.locator('[data-testid="run-amended-prompt"]').focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => window.promptHarness.calls.length === 1);
    const running = await page.evaluate(() => ({
        label: document.querySelector('[data-prompt-run-label]').textContent,
        disabled: document.querySelector('[data-testid="run-amended-prompt"]').disabled,
        busy: document.querySelector('[data-testid="run-amended-prompt"]').getAttribute('aria-busy'),
        status: document.querySelector('[data-testid="prompt-run-status"]').textContent,
        call: window.promptHarness.calls[0]
    }));
    assert.equal(running.label, 'Running amended prompt…');
    assert.equal(running.disabled, true);
    assert.equal(running.busy, 'true');
    assert.equal(running.status, 'Running your amended prompt for this step…');
    assert.deepEqual(running.call, {
        id: 77,
        step: 11,
        extras: { prompt_override: 'Amended evaluation prompt', content_html: '' }
    });

    await page.evaluate(() => window.releasePromptRun());
    await page.waitForFunction(() => document.querySelector('[data-testid="prompt-run-status"]').textContent === 'Amended prompt used.');
    const used = await page.evaluate(() => ({
        label: document.querySelector('[data-prompt-run-label]').textContent,
        disabled: document.querySelector('[data-testid="run-amended-prompt"]').disabled,
        busy: document.querySelector('[data-testid="run-amended-prompt"]').getAttribute('aria-busy'),
        success: document.querySelector('[data-testid="prompt-run-status"]').classList.contains('is-success')
    }));
    assert.deepEqual(used, {
        label: 'Run amended prompt',
        disabled: true,
        busy: 'false',
        success: true
    });

    await page.locator('[data-testid="prompt-editor"]').fill('Another amended prompt');
    await page.evaluate(() => window.promptHarness.failNext());
    await page.locator('[data-testid="run-amended-prompt"]').click();
    await page.waitForFunction(() => document.querySelector('[data-testid="prompt-run-status"]').classList.contains('is-error'));
    const failed = await page.evaluate(() => ({
        disabled: document.querySelector('[data-testid="run-amended-prompt"]').disabled,
        status: document.querySelector('[data-testid="prompt-run-status"]').textContent,
        errors: window.promptHarness.errors.slice()
    }));
    assert.equal(failed.disabled, false, 'a failed amended prompt remains available to retry');
    assert.equal(failed.status, 'The amended prompt could not be run. Try again.');
    assert.deepEqual(failed.errors, ['Deliberate prompt test failure']);

    console.log('Prompt amendment action: idle, dirty, keyboard-run, success and retryable-error states passed');
} finally {
    await browser.close();
}
