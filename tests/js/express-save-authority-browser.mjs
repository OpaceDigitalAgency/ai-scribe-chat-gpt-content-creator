import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    await page.setContent(`<!doctype html><html><body>
      <main id="ai-scribe-root" class="ai-scribe-app mode-express-active">
        <section data-mode-screen="wizard" hidden></section>
        <section data-mode-screen="express"></section>
      </main>
    </body></html>`);
    await page.addScriptTag({ content: 'window.ai_scribe={checkArr:{}};class StepViewRegistry{}' });
    await page.addScriptTag({ content: controllerSource });

    const result = await page.evaluate(async () => {
        const slices = { articleSaveAuthority: 'review', conversationId: 42 };
        const appState = {
            getStateSlice(key) { return slices[key]; },
            setStateSlice(key, value) { slices[key] = value; }
        };
        const express = {
            getSaveHtml() { return '<h1>Old Express draft</h1><p>Old copy.</p>'; },
            getArticle() { return { title: 'Old Express draft' }; },
            setImprovementState(state, message) { window.improvement = { state, message }; }
        };
        const review = {
            compileArticleHtml() { return '<h1>Refined article</h1><p>New edited copy.</p>'; },
            getSelection() { return '<h1>Refined article</h1><p>New edited copy.</p>'; }
        };
        const controller = Object.create(WizardFlowController.prototype);
        controller.root = document.getElementById('ai-scribe-root');
        controller.appState = appState;
        controller.registry = { get(step) { return step === 'express' ? express : (step === 10 ? review : null); } };
        controller.pendingSteps = new Set();
        let improvementRequests = 0;
        controller.api = { async improveExpressLength() { improvementRequests += 1; } };

        const refinedSource = controller.articleSaveSource();
        await controller.improveExpressLength(document.createElement('button'));

        appState.setStateSlice('articleSaveAuthority', 'express');
        const expressSource = controller.articleSaveSource();

        appState.setStateSlice('articleSaveAuthority', 'review');
        review.compileArticleHtml = () => '';
        review.getSelection = () => '';
        const unavailableSource = controller.articleSaveSource();

        return {
            refinedContext: refinedSource && refinedSource.context,
            refinedHtml: refinedSource && refinedSource.html,
            expressContext: expressSource && expressSource.context,
            expressHtml: expressSource && expressSource.html,
            unavailableSource,
            improvementRequests,
            improvementState: window.improvement
        };
    });

    assert.equal(result.refinedContext, 'review', 'Review remains authoritative after returning to the Express tab');
    assert.match(result.refinedHtml, /New edited copy/, 'the current edited Review snapshot is selected');
    assert.equal(result.expressContext, 'express', 'a newly generated Express draft is directly saveable');
    assert.match(result.expressHtml, /Old copy/, 'Express saves its exact rendered snapshot before refinement');
    assert.equal(result.unavailableSource, null, 'missing Review HTML fails closed instead of falling back to stale Express');
    assert.equal(result.improvementRequests, 0, 'the stale Express draft cannot be improved over newer Wizard edits');
    assert.equal(result.improvementState.state, 'error');
    assert.match(result.improvementState.message, /newer edited version is not replaced/i);

    console.log('Express save authority browser: Refine handoff, tab switch and Improve Length fail closed against stale Express HTML');
} finally {
    await browser.close();
}
