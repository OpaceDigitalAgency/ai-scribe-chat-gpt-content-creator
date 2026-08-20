import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync(
    new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url),
    'utf8'
);
const mainCss = fs.readFileSync(new URL('../../assets/css/main.css', import.meta.url), 'utf8');
const componentsCss = fs.readFileSync(new URL('../../assets/css/components.css', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

const card = (context) => `<section data-save-status-card data-save-context="${context}" class="is-unsaved">
    <p data-save-status-message role="status"></p><span data-save-status-badge></span>
    <ul data-save-destinations hidden>
      <li data-save-destination="post" hidden><strong data-save-destination-label></strong><span data-save-destination-state></span></li>
      <li data-save-destination="shortcode" hidden><strong>Shortcode</strong><span data-save-destination-state></span></li>
    </ul>
    <div class="save-status-actions">
      <button data-action="save-draft"><span data-save-label>Save as Draft</span></button>
      <button data-action="save-post"><span data-save-label>Publish Post</span></button>
      <button data-action="save-shortcode"><span data-save-label>Save as Shortcode</span></button>
      <a data-saved-post-link hidden></a>
    </div><code data-saved-shortcode-note hidden></code>
  </section>`;

try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await page.setContent(`<!doctype html><html><body><main id="ai-scribe-root">
      <section data-step-panel="10">${card('review')}</section>
      <section data-step-panel="11">${card('evaluate')}
        <button data-testid="complete" data-action="start-again"><span data-complete-label></span></button>
      </section>
    </main></body></html>`);
    await page.addStyleTag({ content: mainCss });
    await page.addStyleTag({ content: componentsCss });
    await page.addScriptTag({ content: `
      class StepViewRegistry { static isStreamingStep() { return false; } }
      window.StepViewRegistry = StepViewRegistry;
      window.ai_scribe = { checkArr: {} };
    ` });
    await page.addScriptTag({ content: controllerSource });

    const result = await page.evaluate(async () => {
        let articleHtml = '<h1>Accurate article</h1><p>Version one.</p>';
        let resetCount = 0;
        const state = { currentStep: 11, conversationId: 72, persistence: { post: null, shortcode: null } };
        const observers = [];
        const appState = {
            getStateSlice(key) { return state[key]; },
            setStateSlice(key, value) {
                state[key] = value;
                observers.forEach((observer) => observer(state));
            },
            subscribe(observer) { observers.push(observer); },
            reset() { resetCount += 1; }
        };
        const review = {
            compileArticleHtml: () => articleHtml,
            getSelection: () => articleHtml,
            announce() {},
            showError(error) { throw error; }
        };
        const evaluate = { announce(message) { window.lastAnnouncement = message; } };
        const registry = { get(step) { return step === 10 ? review : (step === 11 ? evaluate : null); } };
        const api = {
            async savePost() {
                return { post_id: 91, edit_link: '/wp-admin/post.php?post=91&action=edit', updated: false };
            },
            async saveShortcode() { return { shortcode_id: 14 }; },
            abortAllStreams() {}
        };
        const controller = new WizardFlowController(appState, api, registry);
        controller.startAgain = () => { resetCount += 1; };
        controller.renderPersistenceState();
        const before = {
            badges: Array.from(document.querySelectorAll('[data-save-status-badge]'), (el) => el.textContent),
            finish: document.querySelector('[data-complete-label]').textContent
        };

        await controller.savePost('draft', document.querySelector('[data-save-context="evaluate"] [data-action="save-draft"]'));
        const afterDraft = {
            badges: Array.from(document.querySelectorAll('[data-save-status-badge]'), (el) => el.textContent),
            postState: document.querySelector('[data-save-context="evaluate"] [data-save-destination="post"] [data-save-destination-state]').textContent,
            postLink: document.querySelector('[data-save-context="evaluate"] [data-saved-post-link]').getAttribute('href'),
            finish: document.querySelector('[data-complete-label]').textContent
        };

        articleHtml = '<h1>Accurate article</h1><p>Version two, edited after save.</p>';
        controller.renderPersistenceState();
        const dirty = {
            badges: Array.from(document.querySelectorAll('[data-save-status-badge]'), (el) => el.textContent),
            postState: document.querySelector('[data-save-context="review"] [data-save-destination="post"] [data-save-destination-state]').textContent,
            finish: document.querySelector('[data-complete-label]').textContent
        };

        controller.confirmDiscardAndStartAgain(document.querySelector('[data-testid="complete"]'));
        const confirm = {
            label: document.querySelector('[data-complete-label]').textContent,
            resetCount,
            announcement: window.lastAnnouncement
        };
        controller.confirmDiscardAndStartAgain(document.querySelector('[data-testid="complete"]'));

        return { before, afterDraft, dirty, confirm, resetCount, persistence: state.persistence };
    });

    assert.deepEqual(result.before.badges, ['Not saved', 'Not saved']);
    assert.equal(result.before.finish, 'Finish Without Saving');
    assert.deepEqual(result.afterDraft.badges, ['Saved', 'Saved'], 'Review and Evaluate share confirmed state');
    assert.equal(result.afterDraft.postState, 'Current version');
    assert.match(result.afterDraft.postLink, /post=91/);
    assert.equal(result.afterDraft.finish, 'Finish & Start New');
    assert.deepEqual(result.dirty.badges, ['Unsaved changes', 'Unsaved changes']);
    assert.match(result.dirty.postState, /Older version/);
    assert.equal(result.dirty.finish, 'Finish Without Saving');
    assert.equal(result.confirm.resetCount, 0, 'first finish click does not discard');
    assert.equal(result.confirm.label, 'Discard & Start New');
    assert.match(result.confirm.announcement, /has not been saved/);
    assert.equal(result.resetCount, 1, 'explicit second click starts over');
    assert.equal(result.persistence.post.id, 91, 'post state comes from confirmed server id');
    assert.equal(
        await page.locator('[data-save-context="evaluate"] .save-status-actions').evaluate(
            (actions) => getComputedStyle(actions).flexWrap
        ),
        'wrap',
        'final save actions wrap responsively'
    );
    assert.equal(
        await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth),
        true,
        'mobile final actions do not cause horizontal overflow'
    );

    console.log('Final save-state browser: unsaved, confirmed draft, dirty-again and guarded discard states pass on both final steps');
} finally {
    await browser.close();
}
