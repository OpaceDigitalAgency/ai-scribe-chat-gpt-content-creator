import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8'),
    fs.readFileSync('assets/css/admin-pages.css', 'utf8')
].join('\n');
const baseSource = fs.readFileSync('assets/js/views/steps/BaseStepView.js', 'utf8');
const expressSource = fs.readFileSync('assets/js/views/steps/ExpressView.js', 'utf8');
const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    for (const viewport of [
        { width: 375, height: 812 },
        { width: 768, height: 900 },
        { width: 1440, height: 1000 }
    ]) {
        const page = await browser.newPage({ viewport });
        await page.setContent(`<!doctype html><html><head><style>${css}</style></head><body>
          <main class="ai-scribe-app">
            <section class="express-screen" data-step-panel="express">
              <h2 class="step-heading">Express Article</h2>
              <div class="input-section"><div class="form-group">
                <label for="topic">Article topic or idea</label>
                <div class="input-with-button"><input id="topic" class="form-control" value="web design tips for this year"><button class="btn btn-primary">Generate Full Article</button></div>
                <div class="form-row article-length-override">
                  <div class="form-group"><label for="length">Length for this article</label><select id="length" class="form-control"><option>Use global default</option></select></div>
                  <div class="form-group" data-custom-word-count hidden><label>Custom target words</label><input class="form-control"></div>
                </div>
                <p class="form-hint article-plan-summary">Planned target: about 1,800 words (1,530–2,070).</p>
              </div></div>
              <p data-testid="step-status"></p><div class="results-section hidden"><article id="express-stream-output"></article></div>
            </section>
          </main></body></html>`);

        const measurements = await page.evaluate(() => {
            const topic = document.getElementById('topic').getBoundingClientRect();
            const lengthLabel = document.querySelector('.article-length-override label').getBoundingClientRect();
            const select = document.getElementById('length').getBoundingClientRect();
            return {
                topicToLabel: Math.round(lengthLabel.top - topic.bottom),
                labelToSelect: Math.round(select.top - lengthLabel.bottom),
                overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth
            };
        });
        assert.ok(measurements.topicToLabel >= 20, `${viewport.width}px keeps the length label clear of the topic field (${measurements.topicToLabel}px)`);
        assert.ok(measurements.labelToSelect >= 6, `${viewport.width}px keeps the length field clear of its label (${measurements.labelToSelect}px)`);
        assert.equal(measurements.overflow, false, `${viewport.width}px has no horizontal overflow`);
        await page.close();
    }

    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.setContent('<main class="ai-scribe-app"><section data-step-panel="express"><input data-testid="express-topic"><p data-testid="step-status"></p><div class="results-section hidden"><article id="express-stream-output"></article></div></section></main>');
    await page.addScriptTag({ content: 'window.lucide={createIcons(){}};window.ai_scribe={i18n:{}};' });
    await page.addScriptTag({ content: baseSource });
    await page.addScriptTag({ content: expressSource });
    await page.evaluate(() => {
        const appState = { getStateSlice() { return null; }, setStateSlice() {} };
        const view = new ExpressView(appState);
        view.renderArticle({
            quality_plan: {
                pass: false,
                advisory: true,
                message: 'The generated complete article contains 1,485 words. Planned target: about 1,800 words (preferred range 1,530–2,070). It finished 45 words below the preferred range after 2 improvement attempts. The usable draft has been kept so you can review, edit or regenerate it.'
            },
            article: {
                title: 'A usable article', meta: {}, tagline: '', outline: ['Section'],
                intro: '<p>Intro.</p>', body_html: '<h2>Section</h2><p>Body.</p>', conclusion: '<p>End.</p>', qna: []
            }
        });
    });
    const notice = page.locator('[data-testid="article-quality-notice"]');
    assert.equal(await notice.isVisible(), true, 'near-target article renders a visible advisory');
    assert.match(await notice.innerText(), /1,485 words[\s\S]*about 1,800 words[\s\S]*1,530–2,070[\s\S]*45 words below[\s\S]*has been kept/);
    assert.equal(await page.locator('#express-stream-output h1').textContent(), 'A usable article', 'usable article remains rendered after the advisory');
    await page.close();

    // Express intentionally nests results inside its own content container.
    // A quality advisory arrives only after the successful provider response,
    // so this protects the exact DOM path that previously threw WebKit's
    // NotFoundError and incorrectly displayed an error after the article had
    // already been persisted.
    const nested = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await nested.setContent(`<main id="ai-scribe-root" class="ai-scribe-app">
      <section data-step-panel="express">
        <input data-testid="express-topic" value="web design tips for this year">
        <p data-testid="step-status"></p>
        <div class="express-content-container">
          <div class="results-section hidden"><article id="express-stream-output"></article></div>
        </div>
      </section>
    </main>`);
    await nested.addScriptTag({ content: 'window.lucide={createIcons(){}};window.ai_scribe={i18n:{}};' });
    await nested.addScriptTag({ content: baseSource });
    await nested.addScriptTag({ content: expressSource });
    await nested.addScriptTag({ content: controllerSource });
    const nestedResult = await nested.evaluate(async () => {
        const slices = { currentStep: 1 };
        const appState = {
            getStateSlice(key) { return slices[key]; },
            setStateSlice(key, value) { slices[key] = value; }
        };
        const view = new ExpressView(appState);
        // Express currently has no user-facing idle copy because its form is
        // already actionable. Supply copy here to exercise the shared initial
        // idle path against Express's nested results container.
        view.idleCopy = () => ({ icon: 'sparkles', what: 'An Express draft will appear here.' });
        view.showIdle();
        const idle = document.querySelector('[data-testid="step-idle"]');
        const results = document.querySelector('.results-section');
        const idleState = {
            idleParent: idle && idle.parentElement.className,
            idleImmediatelyBeforeResults: idle && idle.nextElementSibling === results,
            idleVisible: idle && !idle.hidden
        };
        let requests = 0;
        const controller = Object.create(WizardFlowController.prototype);
        controller.appState = appState;
        controller.registry = { get(step) { return step === 'express' ? view : null; } };
        controller.pendingSteps = new Set();
        controller.api = {
            async runExpress() {
                requests += 1;
                return {
                    conversation_id: 75,
                    quality_plan: {
                        pass: false,
                        advisory: true,
                        message: 'The usable draft was kept below the preferred range.'
                    },
                    article: {
                        title: 'Persisted article', meta: {}, tagline: '', outline: [],
                        intro: '<p>Intro.</p>', body_html: '<h2>Section</h2><p>Body.</p>', conclusion: '<p>End.</p>', qna: []
                    }
                };
            }
        };
        controller.articleLengthFields = () => ({});
        controller.setGenerationBusy = (step, busy) => {
            if (busy) controller.pendingSteps.add(step);
            else controller.pendingSteps.delete(step);
        };
        controller.recordResult = (data) => appState.setStateSlice('conversationId', data.conversation_id);
        await controller.expressGenerate();
        const notice = document.querySelector('[data-testid="article-quality-notice"]');
        return {
            requests,
            state: view.state,
            conversationId: slices.conversationId,
            ...idleState,
            noticeParent: notice && notice.parentElement.className,
            noticeImmediatelyBeforeResults: notice && notice.nextElementSibling === results,
            title: document.querySelector('#express-stream-output h1')?.textContent || ''
        };
    });
    assert.deepEqual(nestedResult, {
        requests: 1,
        state: 'ready',
        conversationId: 75,
        idleParent: 'express-content-container',
        idleImmediatelyBeforeResults: true,
        idleVisible: true,
        noticeParent: 'express-content-container',
        noticeImmediatelyBeforeResults: true,
        title: 'Persisted article'
    }, 'a successful advisory renders in the nested Express parent without a retry or second request');
    await nested.close();

    // Wizard step 6 keeps results directly inside its panel. Keep this
    // explicit alongside Express's nested case: shared advisories must not
    // regress either insertion shape.
    const wizardBody = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await wizardBody.setContent(`<main class="ai-scribe-app">
      <section data-step-panel="6">
        <p data-testid="step-status"></p>
        <div class="results-section hidden"><div id="body-stream-output"></div></div>
      </section>
    </main>`);
    await wizardBody.addScriptTag({ content: 'window.lucide={createIcons(){}};window.ai_scribe={i18n:{}};' });
    await wizardBody.addScriptTag({ content: baseSource });
    const wizardBodyResult = await wizardBody.evaluate(() => {
        const view = new BaseStepView(6, { getStateSlice() { return null; }, setStateSlice() {} });
        view.showQualityNotice({
            advisory: true,
            message: 'The complete draft was kept below the preferred range.'
        });
        const notice = document.querySelector('[data-testid="article-quality-notice"]');
        const results = document.querySelector('.results-section');
        return {
            noticeParentIsPanel: notice && notice.parentElement === view.panel,
            noticeImmediatelyBeforeResults: notice && notice.nextElementSibling === results,
            noticeVisible: notice && !notice.hidden
        };
    });
    assert.deepEqual(wizardBodyResult, {
        noticeParentIsPanel: true,
        noticeImmediatelyBeforeResults: true,
        noticeVisible: true
    }, 'Wizard Body advisory is inserted before its direct results child without throwing');
    await wizardBody.close();

    console.log('Article length advisory browser: spacing at 375/768/1440 and retained near-target Express result passed');
} finally {
    await browser.close();
}
