import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync(
    new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url),
    'utf8'
);
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage();
    await page.setContent('<main id="ai-scribe-root"><section data-step-panel="10"><button id="save"></button></section></main>');
    await page.addScriptTag({ content: `
        class StepViewRegistry { static isStreamingStep() { return false; } }
        window.StepViewRegistry = StepViewRegistry;
        window.ai_scribe = { checkArr: {} };
    ` });
    await page.addScriptTag({ content: controllerSource });

    const result = await page.evaluate(async () => {
        const state = {
            conversationId: 51,
            currentStep: 10,
            featuredImageRemoved: false,
            galleryImages: [
                { url: 'featured-old.jpg', attachment_id: 71, alt_text: 'Old featured image' },
                { url: 'detail.jpg', attachment_id: 72, alt_text: 'Detail image' }
            ],
            persistence: { post: null, shortcode: null }
        };
        const previewStates = [];
        const savedFields = [];
        const review = {
            quill: null,
            renderFeaturedPreview() {
                previewStates.push({
                    removed: state.featuredImageRemoved,
                    url: state.featuredImageRemoved ? '' : (state.galleryImages[0] || {}).url
                });
            },
            compileArticleHtml: () => '<h1>Article</h1><p>Useful article body.</p>',
            getSelection: () => '<h1>Article</h1><p>Useful article body.</p>',
            announce() {},
            showError(error) { throw error; }
        };
        const registry = { get: step => step === 10 ? review : null };
        const appState = {
            getStateSlice: key => state[key],
            setStateSlice: (key, value) => { state[key] = value; },
            subscribe() {}
        };
        const api = {
            async savePost(id, fields) {
                savedFields.push({ id, fields });
                return { post_id: 91, edit_link: '/wp-admin/post.php?post=91&action=edit' };
            }
        };
        const controller = new WizardFlowController(appState, api, registry);
        controller.updateReviewActions = () => true;
        controller.renderPersistenceState = () => {};
        controller.notify = () => {};
        controller.announceImages = () => {};
        controller.updateGalleryStates = () => {};
        controller.persistEditorHtml = () => {};
        controller.hideImageToolbar = () => {};

        controller.replaceGalleryImage(
            state.galleryImages[0],
            { url: 'featured-new.jpg', attachment_id: 73, alt_text: 'New featured image' }
        );

        // Simulate deleting the featured copy from Review. The explicit
        // removal state hides the separate preview and suppresses save data.
        const wrapper = document.createElement('p');
        const img = document.createElement('img');
        img.src = 'featured-new.jpg';
        wrapper.appendChild(img);
        document.body.appendChild(wrapper);
        const originalIndex = WizardFlowController.embeddedImageIndex;
        WizardFlowController.embeddedImageIndex = () => 4;
        controller.deleteEmbeddedImage(10, { deleteText() {} }, img);
        WizardFlowController.embeddedImageIndex = originalIndex;
        await controller.savePost('draft', document.querySelector('#save'));

        // A genuinely new first image re-enables and refreshes the preview.
        state.galleryImages = [];
        state.featuredImageRemoved = true;
        controller.recordGalleryImage({
            url: 'fresh-featured.jpg', attachment_id: 74, alt_text: 'Fresh featured image'
        });

        const generatedState = { galleryImages: [], featuredImageRemoved: true };
        const generatedAnnouncements = [];
        let generatedInsertions = 0;
        let generatedPreviewRefreshes = 0;
        const generatedController = Object.create(WizardFlowController.prototype);
        generatedController.imageOperationPending = false;
        generatedController.appState = {
            getStateSlice: key => generatedState[key],
            setStateSlice: (key, value) => { generatedState[key] = value; }
        };
        generatedController.registry = { get: step => step === 10 ? {
            renderFeaturedPreview() { generatedPreviewRefreshes += 1; }
        } : null };
        generatedController.api = { async generateImage() {
            return { url: 'generated-on-review.jpg', attachment_id: 75, alt_text: 'Generated featured image' };
        } };
        generatedController.galleryEl = () => document.createElement('div');
        generatedController.setImageControlsBusy = () => {};
        generatedController.startImageProgress = () => {};
        generatedController.finishImageProgress = () => {};
        generatedController.renderImageProgressQueue = () => {};
        generatedController.updateImageProgressItem = () => {};
        generatedController.imagePrompt = () => 'safe image prompt';
        generatedController.effectiveImageOptions = () => ({});
        generatedController.imageRetryOptions = () => ({});
        generatedController.appendGalleryImage = (step, gallery, data) => generatedController.recordGalleryImage(data);
        generatedController.insertImageIntelligently = () => { generatedInsertions += 1; };
        generatedController.announceImages = (step, message) => generatedAnnouncements.push(message);
        await generatedController.addImage(10, null, '', 'Generating Review image');

        return {
            previewStates, savedFields, state,
            generatedState, generatedAnnouncements, generatedInsertions, generatedPreviewRefreshes
        };
    });

    assert.deepEqual(result.previewStates[0], { removed: false, url: 'featured-new.jpg' }, 'regenerating the first gallery image refreshes Review preview');
    assert.deepEqual(result.previewStates[1], { removed: true, url: '' }, 'deleting the featured copy immediately hides Review preview');
    assert.equal('featured_attachment_id' in result.savedFields[0].fields, false, 'explicitly removed featured image is absent from the save payload');
    assert.deepEqual(result.previewStates.at(-1), { removed: false, url: 'fresh-featured.jpg' }, 'a new featured image is shown immediately in Review');
    assert.equal(result.generatedInsertions, 0, 'a featured image generated on Review is never inserted into article HTML');
    assert.ok(result.generatedPreviewRefreshes > 0, 'a featured image generated on Review refreshes the separate preview');
    assert.equal(result.generatedState.featuredImageRemoved, false, 'a newly generated first image becomes the active featured preview');
    assert.match(result.generatedAnnouncements.at(-1), /separate preview.*not be inserted/i, 'Review announcement describes preview placement honestly');
    console.log('Featured Review state browser: replacement, deletion, save suppression and fresh preview passed');
} finally {
    await browser.close();
}
