import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8'),
    fs.readFileSync('assets/css/admin-pages.css', 'utf8')
].join('\n');
const frontendCss = fs.readFileSync('assets/css/frontend-article.css', 'utf8');
const baseSource = fs.readFileSync('assets/js/views/steps/BaseStepView.js', 'utf8');
const expressSource = fs.readFileSync('assets/js/views/steps/ExpressView.js', 'utf8');
const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');

const browser = await chromium.launch({ headless: true });
try {
    for (const viewport of [{ width: 375, height: 812 }, { width: 1440, height: 1000 }]) {
        const page = await browser.newPage({ viewport });
        await page.setContent(`<!doctype html><html><head><style>${css}</style></head><body>
          <main class="ai-scribe-app">
            <section data-step-panel="express">
              <p data-testid="step-status"></p>
              <p class="visually-hidden" data-article-target-live role="status" aria-live="polite"></p>
              <div class="results-section hidden">
                <div class="article-target-status" data-article-target-status hidden><div class="article-target-status-copy"><strong data-target-status-heading></strong><span data-target-status-detail></span></div><div class="article-target-track"><span data-target-status-bar></span></div></div>
                <article class="prose-output express-article" id="express-stream-output"></article>
              </div>
            </section>
            <section class="featured-image-review"><div class="featured-image-review-copy"><h3>Featured image preview</h3><p>Used as the WordPress featured image; it is not duplicated inside the article.</p></div><div class="featured-image-review-media"><img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg'/>"></div></section>
            <article class="gallery-item"><div></div><div class="gallery-card-content"><label class="gallery-caption-label">Caption<textarea class="form-control gallery-caption-editor" rows="2">A complete caption which should wrap rather than being cut off at the edge of a single line field.</textarea></label></div></article>
          </main></body></html>`);
        await page.addScriptTag({ content: 'window.lucide={createIcons(){}};window.ai_scribe={i18n:{}};' });
        await page.addScriptTag({ content: baseSource });
        await page.addScriptTag({ content: expressSource });
        await page.evaluate(() => {
            const state = {};
            const view = new ExpressView({ getStateSlice(k) { return state[k]; }, setStateSlice(k, v) { state[k] = v; } });
            view.renderArticle({
                quality_plan: { word_count: 1726, target_words: 1800, minimum_words: 1575, maximum_words: 2025, pass: true },
                article: { title: 'Useful title', tagline: '', intro: '<div><p>Intro.</p></div>', body_html: '<section><h2>Section</h2><p>Body.</p></section>', conclusion: '<div><p>End.</p></div>', qna: [], meta: {}, outline: [] }
            });
        });
        assert.equal(await page.locator('[data-target-status-heading]').textContent(), '1,726 words generated');
        assert.match(
            await page.locator('[data-target-status-detail]').textContent(),
            /Target 1,800 · preferred range 1,575–2,025 · 74 words to target/,
            'status names the exact target, acceptable range and measured difference'
        );
        await page.waitForFunction(() => document.querySelector('[data-article-target-live]').textContent.includes('1,726 words generated'));
        assert.equal(await page.locator('#express-stream-output > div, #express-stream-output > section').count(), 0, 'provider wrappers are unwrapped');
        const featuredHeight = await page.locator('.featured-image-review').evaluate((node) => node.getBoundingClientRect().height);
        assert.ok(featuredHeight < 150, `${viewport.width}px featured preview stays compact (${featuredHeight}px)`);
        assert.equal(await page.locator('.gallery-caption-editor').evaluate((node) => node.scrollWidth <= node.clientWidth), true, 'caption editor wraps without clipping');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true, `${viewport.width}px has no horizontal overflow`);
        await page.close();
    }

    const page = await browser.newPage();
    await page.setContent(`<style>${css}</style><main class="ai-scribe-app">
      <section data-step-panel="1"><div class="input-section"><div class="form-group"><div class="article-length-override"><select data-article-length-mode><option value="global">Global</option><option value="custom">Custom</option></select><div data-custom-word-count hidden><input data-article-word-count value="1800"></div></div></div><p data-article-plan-summary></p></div></section>
      <section data-step-panel="6"></section><section data-step-panel="10"><input data-publishing-category><input data-publishing-tags></section>
    </main>`);
    await page.addScriptTag({ content: 'class StepViewRegistry{};window.StepViewRegistry=StepViewRegistry;window.ai_scribe={contentSettings:{}};' });
    await page.addScriptTag({ content: controllerSource });
    const section = await page.evaluate(() => {
        const controller = Object.create(WizardFlowController.prototype);
        controller.sectionPrompts = () => [{ section: 'Responsive Image Markup', prompt: 'Editorial image for the section "Responsive Image Markup" in this article.' }];
        return {
            exact: controller.inferImageSourceSection('Editorial image for the section "Responsive Image Markup" in this article.', 6),
            parsed: controller.inferImageSourceSection('Create a visual for the section “Accessible Navigation” with no text.', 6)
        };
    });
    assert.deepEqual(section, { exact: 'Responsive Image Markup', parsed: 'Accessible Navigation' });
    const placement = await page.evaluate(() => {
        const root = document.createElement('div');
        root.innerHTML = '<h2>First Section</h2><p>First copy.</p><h2>Responsive image markup!</h2><p>Target copy.</p>';
        document.body.appendChild(root);
        const quill = {
            root,
            getLength() { return 99; },
            getIndex(blot) { return Array.from(root.children).indexOf(blot.block) * 10; }
        };
        window.Quill = { find(block) { return { length() { return 2; }, block }; } };
        const controller = Object.create(WizardFlowController.prototype);
        const calls = [];
        const notices = [];
        controller.editorForStep = () => quill;
        controller.insertImageAt = (step, editor, data, index) => {
            calls.push({ section: data.source_section, index });
            return true;
        };
        controller.announceImages = (step, message) => notices.push(message);
        const matched = controller.insertImageIntelligently(6, { url: 'matched.png', source_section: 'Responsive Image Markup' }, false);
        const unmatched = controller.insertImageIntelligently(6, { url: 'missing.png', source_section: 'Missing Section' }, false);
        return { matched, unmatched, calls, notices };
    });
    assert.equal(placement.matched, true, 'normalised heading text accepts punctuation and case differences');
    assert.equal(placement.calls[0].index, 22, 'section image is inserted immediately after its matching H2');
    assert.equal(placement.unmatched, false, 'an unmatched section is never silently appended');
    assert.equal(placement.calls.length, 1, 'unmatched placement never reaches the end-insertion path');
    assert.match(placement.notices.at(-1), /was not moved to the end/);

    const bulkStatus = await page.evaluate(() => {
        const controller = Object.create(WizardFlowController.prototype);
        const quill = {};
        let notice = '';
        controller.galleryImages = () => [
            { url: 'existing.png', source_section: 'Existing' },
            { url: 'placed.png', source_section: 'Found' },
            { url: 'missing.png', source_section: 'Missing' }
        ];
        controller.editorForStep = () => quill;
        controller.isImagePlaced = (editor, url) => url === 'existing.png';
        controller.insertImageIntelligently = (step, image) => image.source_section === 'Found';
        controller.announceImages = (step, message) => { notice = message; };
        controller.updateGalleryStates = () => {};
        controller.insertAllImages(6);
        return notice;
    });
    assert.match(bulkStatus, /1 image was placed/);
    assert.match(bulkStatus, /1 image could not be matched/);
    assert.match(bulkStatus, /not moved to the end/);
    const customLength = await page.evaluate(async () => {
        const controller = Object.create(WizardFlowController.prototype);
        controller.qnaEnabled = () => true;
        const select = document.querySelector('[data-article-length-mode]');
        select.value = 'custom';
        select.focus();
        controller.refreshArticlePlanControls();
        await new Promise((resolve) => setTimeout(resolve, 5));
        const group = document.querySelector('[data-custom-word-count]');
        return { hidden: group.hidden, aria: group.getAttribute('aria-hidden'), focused: document.activeElement === group.querySelector('input') };
    });
    assert.deepEqual(customLength, { hidden: false, aria: 'false', focused: true }, 'Custom reveals and focuses its numeric target');
    const publishing = await page.evaluate(() => {
        const state = { stepData: { 1: { selection: 'Web Design Tips for 2026' }, 2: { selection: ['web design tips', 'responsive design'] } } };
        const controller = Object.create(WizardFlowController.prototype);
        controller.appState = { getStateSlice(key) { return state[key]; }, setStateSlice(key, value) { state[key] = value; } };
        controller.topicValue = () => 'web design tips for this year';
        controller.preparePublishingDetails();
        return {
            category: document.querySelector('[data-publishing-category]').value,
            tags: document.querySelector('[data-publishing-tags]').value,
            saved: state.publishingDetails
        };
    });
    assert.deepEqual(publishing, {
        category: 'Web Design', tags: 'web design tips, responsive design',
        saved: { category: 'Web Design', tags: 'web design tips, responsive design' }
    });
    const publishingAccuracy = await page.evaluate(() => {
        const controller = Object.create(WizardFlowController.prototype);
        const warning = controller.publishingAssignmentMessage(
            { category: 'Web Design', tags: 'responsive design, accessibility' },
            { category: '', tags: ['responsive design'] }
        );
        const success = controller.publishingAssignmentMessage(
            { category: 'Web Design', tags: 'responsive design' },
            { category: 'Web Design', tags: ['responsive design'] }
        );
        document.querySelector('[data-publishing-category]').value = '';
        document.querySelector('[data-publishing-tags]').value = '';
        const state = { stepData: { 1: { selection: 'A Very Long Provider Title That Must Not Become One Tag' } }, publishingDetails: {} };
        controller.appState = { getStateSlice(key) { return state[key]; }, setStateSlice(key, value) { state[key] = value; } };
        controller.topicValue = () => 'web design tips for this year';
        controller.preparePublishingDetails();
        return {
            warning, success,
            fallbackCategory: document.querySelector('[data-publishing-category]').value,
            fallbackTags: document.querySelector('[data-publishing-tags]').value
        };
    });
    assert.equal(publishingAccuracy.warning.type, 'warning');
    assert.match(publishingAccuracy.warning.message, /did not assign category “Web Design” or 1 requested tag/);
    assert.deepEqual(publishingAccuracy.success, { type: 'success', message: 'Publishing details saved: category “Web Design” and 1 tag.' });
    assert.equal(publishingAccuracy.fallbackCategory, 'Web Design');
    assert.equal(publishingAccuracy.fallbackTags, `web design tips for ${new Date().getFullYear()}, web design`);
    await page.close();

    for (const viewport of [{ width: 375, height: 812 }, { width: 1440, height: 1000 }]) {
        const saved = await browser.newPage({ viewport });
        await saved.setContent(`<style>html,body{margin:0}${frontendCss}</style><article class="ai-scribe-article-content"><div><p>Nested provider paragraph uses the same reading measure.</p><h2>Nested heading</h2></div><p>Direct paragraph uses the same reading measure.</p><figure class="wp-block-image size-large ai-scribe-article-figure"><img class="ai-scribe-article-image" width="1536" height="1024" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1536' height='1024'/%3E"><figcaption>A complete caption that remains beneath the responsive image.</figcaption></figure></article>`);
        const savedLayout = await saved.evaluate(() => {
            const nested = document.querySelector('div p').getBoundingClientRect();
            const direct = document.querySelector('.ai-scribe-article-content > p')?.getBoundingClientRect();
            const image = document.querySelector('img').getBoundingClientRect();
            return {
                nestedWidth: Math.round(nested.width), directWidth: direct ? Math.round(direct.width) : 0,
                imageWithinViewport: image.right <= document.documentElement.clientWidth && image.left >= 0,
                noOverflow: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
                topPadding: parseFloat(getComputedStyle(document.querySelector('.ai-scribe-article-content')).paddingTop),
                inlinePadding: nested.left
            };
        });
        assert.equal(savedLayout.nestedWidth, savedLayout.directWidth, `${viewport.width}px nested and direct paragraphs share one measure`);
        assert.equal(savedLayout.imageWithinViewport, true, `${viewport.width}px saved image stays within the article`);
        assert.equal(savedLayout.noOverflow, true, `${viewport.width}px saved article has no horizontal overflow`);
        assert.ok(savedLayout.topPadding >= 16, `${viewport.width}px saved article has deliberate top padding`);
        assert.ok(savedLayout.inlinePadding >= 16, `${viewport.width}px saved prose cannot touch the viewport edge`);
        await saved.close();
    }
    console.log('3.2.15 browser acceptance: exact word status, compact preview, wrapped captions, normalised prose and section-target recovery passed');
} finally {
    await browser.close();
}
