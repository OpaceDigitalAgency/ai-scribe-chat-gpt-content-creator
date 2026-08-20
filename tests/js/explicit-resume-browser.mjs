import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const appStateSource = fs.readFileSync(new URL('../../assets/js/core/AppState.js', import.meta.url), 'utf8');
const mainSource = fs.readFileSync(new URL('../../assets/js/main.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage();
    const html = `<!doctype html><html><body>
      <main id="ai-scribe-root">
        <section data-testid="resume-draft-notice" hidden>
          <button data-action="resume-draft">Resume draft</button>
          <button data-action="discard-resume">Start clean</button>
        </section>
      </main>
    </body></html>`;
    await page.route('http://ai-scribe.test/', (route) => route.fulfill({
        status: 200,
        contentType: 'text/html',
        body: html
    }));
    await page.goto('http://ai-scribe.test/');
    await page.addScriptTag({ content: 'window.ai_scribe={};' });
    await page.addScriptTag({ content: appStateSource });
    await page.addScriptTag({ content: mainSource });

    const offered = await page.evaluate(() => {
        window.resumeApp = new AIScribeApp(APP_CONFIG);
        const saved = window.resumeApp.state.createInitialState();
        saved.conversationId = 72;
        saved.currentStep = 6;
        saved.stepData = [[1, { selection: 'Saved title' }]];
        localStorage.setItem(window.resumeApp.state.storageKey, JSON.stringify({
            tabId: window.resumeApp.state.tabId,
            savedAt: Date.now(),
            state: saved
        }));
        window.resumeCalls = [];
        window.resumeApp.controllers.set('wizardFlow', {
            resetWorkflowViews() { window.resumeCalls.push('reset'); },
            switchMode(mode) { window.resumeCalls.push('mode:' + mode); },
            navigateToStep(step) { window.resumeCalls.push('navigate:' + step); },
            hydrateFromServer() { window.resumeCalls.push('hydrate'); },
            maxUnlockedStep: 9
        });
        window.resumeApp.handlePageRefresh();
        window.resumeApp.initializeStepContent();
        return {
            conversationId: window.resumeApp.state.getStateSlice('conversationId'),
            pending: window.resumeApp.pendingResumeState.conversationId,
            noticeHidden: document.querySelector('[data-testid="resume-draft-notice"]').hidden,
            calls: window.resumeCalls.slice()
        };
    });
    assert.deepEqual(offered, {
        conversationId: null,
        pending: 72,
        noticeHidden: false,
        calls: ['mode:wizard', 'navigate:1']
    }, 'same-tab reload shows clean step 1 and offers Resume without hydrating old work');
    await page.evaluate(() => window.resumeApp.saveCurrentState());
    assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem(window.resumeApp.state.storageKey)).state.conversationId), 72,
        'auto-save cannot overwrite a recoverable draft while Resume is offered');

    const resumed = await page.evaluate(() => {
        window.resumeApp.resumeSavedDraft();
        return {
            conversationId: window.resumeApp.state.getStateSlice('conversationId'),
            currentStep: window.resumeApp.state.getStateSlice('currentStep'),
            stepDataIsMap: window.resumeApp.state.getStateSlice('stepData') instanceof Map,
            calls: window.resumeCalls,
            noticeHidden: document.querySelector('[data-testid="resume-draft-notice"]').hidden
        };
    });
    assert.deepEqual(resumed, {
        conversationId: 72,
        currentStep: 6,
        stepDataIsMap: true,
        calls: ['mode:wizard', 'navigate:1', 'reset', 'mode:wizard', 'hydrate'],
        noticeHidden: true
    }, 'Resume is the only action that adopts and hydrates the saved conversation');

    await page.evaluate(() => {
        const saved = window.resumeApp.state.createInitialState();
        saved.conversationId = 88;
        window.resumeApp.pendingResumeState = saved;
        window.resumeApp.offerSavedDraft();
        window.resumeApp.discardSavedDraft();
    });
    assert.equal(await page.evaluate(() => localStorage.getItem(window.resumeApp.state.storageKey)), null);
    assert.equal(await page.locator('[data-testid="resume-draft-notice"]').isHidden(), true);

    console.log('Explicit resume browser: new load stays clean, Resume alone hydrates, and Start clean discards passed');
} finally {
    await browser.close();
}
