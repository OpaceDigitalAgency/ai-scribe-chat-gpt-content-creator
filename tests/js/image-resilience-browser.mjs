import { chromium } from 'playwright';
import fs from 'node:fs';

const apiSource = fs.readFileSync(new URL('../../assets/js/services/ApiClient.js', import.meta.url), 'utf8');
const controllerSource = fs.readFileSync(new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url), 'utf8');
const baseSource = fs.readFileSync(new URL('../../assets/js/views/steps/BaseStepView.js', import.meta.url), 'utf8');
const streamingSource = fs.readFileSync(new URL('../../assets/js/views/steps/StreamingStepView.js', import.meta.url), 'utf8');
const reviewSource = fs.readFileSync(new URL('../../assets/js/views/steps/ReviewStepView.js', import.meta.url), 'utf8');
const bodySource = fs.readFileSync(new URL('../../assets/js/views/steps/BodyStepView.js', import.meta.url), 'utf8');
const expect = (condition, message) => {
  if (!condition) throw new Error(message);
  process.stdout.write(`PASS ${message}\n`);
};

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.setContent('<section data-step-panel="6"><div data-testid="image-generation-status"><strong data-image-progress-title></strong><span data-image-progress-detail></span><time data-image-progress-time></time><ol data-testid="image-progress-queue"></ol></div><div data-testid="image-status"></div><div id="image-gallery"></div></section>');
await page.addScriptTag({ content: `${apiSource}\nwindow.ResilientApi = ApiClient; window.ResilientApiError = ApiError;${controllerSource}\nwindow.ResilientController = WizardFlowController;${baseSource}\n${streamingSource}\n${reviewSource}\nwindow.ResilientReview = ReviewStepView;${bodySource}\nwindow.ResilientBody = BodyStepView;` });

const result = await page.evaluate(async () => {
  const api = Object.create(window.ResilientApi.prototype);
  api.maxRetries = 2;
  let attempts = 0;
  const retryNotices = [];
  api.requestOnce = async () => {
    attempts += 1;
    if (attempts < 3) throw new window.ResilientApiError('Gemini is temporarily unavailable', { code: 'provider_unavailable', retryable: true, status: 503 });
    return { url: 'ready.png' };
  };
  api.backoff = async () => {};
  const generated = await api.generateImage('prompt', {}, { onRetry: detail => retryNotices.push(detail) });

  let permanentAttempts = 0;
  api.requestOnce = async () => {
    permanentAttempts += 1;
    throw new window.ResilientApiError('Invalid request', { code: 'invalid', retryable: false, status: 400 });
  };
  let permanentCode = '';
  try { await api.generateImage('bad prompt'); } catch (error) { permanentCode = error.code; }

  const state = {
    stepData: { 1: { selection: 'Article' }, 3: { selection: ['First', 'Existing', 'Last'] } },
    galleryImages: [{ url: 'existing.png', source_section: 'Existing' }]
  };
  const bulkCalls = [];
  let failLast = true;
  const controller = Object.create(window.ResilientController.prototype);
  controller.imageOperationPending = false;
  controller.appState = {
    getStateSlice: key => state[key],
    setStateSlice: (key, value) => { state[key] = value; }
  };
  controller.api = {
    generateSectionImage: async (sections, index) => {
      const section = sections[index];
      bulkCalls.push(section);
      if (section === 'Last' && failLast) throw new Error('503 exhausted');
      return { url: `${section}.png`, source_section: section, alt_text: section };
    }
  };
  controller.galleryEl = () => document.querySelector('#image-gallery');
  controller.galleryImages = () => state.galleryImages;
  controller.appendGalleryImage = (step, gallery, data) => state.galleryImages.push(data);
  controller.insertImageIntelligently = () => true;
  controller.setImageControlsBusy = () => {};
  controller.startImageProgress = () => {};
  controller.finishImageProgress = () => {};
  controller.announceImages = () => {};
  controller.imagePromptFieldValue = () => '';
  controller.baseImagePrompt = section => `Prompt ${section}`;
  controller.effectiveImageOptions = () => ({});
  controller.imageRetryOptions = () => ({});
  await controller.bulkAddImages(6, null);
  const afterFailure = state.galleryImages.map(image => image.source_section);
  failLast = false;
  await controller.bulkAddImages(6, null);
  const afterRetry = state.galleryImages.map(image => image.source_section);

  const captionState = { galleryImages: [] };
  const captionController = Object.create(window.ResilientController.prototype);
  captionController.appState = {
    getStateSlice: key => captionState[key],
    setStateSlice: (key, value) => { captionState[key] = value; }
  };
  captionController.recordGalleryImage({ url: 'plain.png', alt_text: 'Descriptive alt' });
  captionController.recordGalleryImage({ url: 'captioned.png', alt_text: 'Alt', caption: 'User caption' });
  captionController.recordGalleryImage({ url: 'generic.png', alt_text: 'Featured image', caption: 'article introduction', prompt_used: 'Editorial image for the article introduction. No text.' });
  captionController.recordGalleryImage({ url: 'fallback.png', alt_text: 'AI generated image', caption: '' });
  const insertedText = [];
  const fakeQuill = { insertText: (...args) => insertedText.push(args), formatLine: () => {}, root: document.createElement('div') };
  captionController.insertImageCaption(fakeQuill, captionState.galleryImages[0], 1);
  captionController.insertImageCaption(fakeQuill, captionState.galleryImages[1], 1);
  captionController.insertImageCaption(fakeQuill, captionState.galleryImages[2], 1);

  const editRoot = document.createElement('div');
  editRoot.innerHTML = '<p><img src="captioned.png"></p><p class="ai-scribe-image-caption" data-image-caption="captioned.png">User caption</p>';
  const editQuill = { root: editRoot };
  captionController.editorForStep = () => editQuill;
  captionController.registry = { get: () => ({ persistEdit: () => {} }) };
  captionController.announceImages = () => {};
  captionController.saveGalleryCaption('captioned.png', 'Edited visible caption', 6);
  const editedCaption = editRoot.querySelector('.ai-scribe-image-caption')?.textContent || '';
  captionController.saveGalleryCaption('captioned.png', '', 6);
  const captionRemoved = !editRoot.querySelector('.ai-scribe-image-caption');

  const review = Object.create(window.ResilientReview.prototype);
  review.appState = {
    getStateSlice: key => key === 'galleryImages' ? captionState.galleryImages : false
  };
  const noDuplicate = window.ResilientReview.removeImageByUrl('<p><img src="plain.png" alt="Descriptive alt"></p>', 'plain.png');
  const compiledFeatured = review.featuredImage();
  const compacted = window.ResilientBody.compactEditorHtml('<p>Intro</p><p><br></p><p><img src="plain.png"></p><p>Body</p>');

  return {
    generated,
    attempts,
    retryAttempts: retryNotices.map(item => item.attempt),
    retryDelays: retryNotices.map(item => item.delay),
    permanentAttempts,
    permanentCode,
    bulkCalls,
    afterFailure,
    afterRetry,
    captions: captionState.galleryImages.map(image => image.caption),
    insertedCaptionCalls: insertedText.length,
    insertedCaptionText: insertedText[0] && insertedText[0][1],
    editedCaption,
    captionRemoved,
    noDuplicate,
    compiledFeatured,
    compacted
  };
});

expect(result.generated.url === 'ready.png' && result.attempts === 3, '503 image request retries twice then succeeds');
expect(JSON.stringify(result.retryAttempts) === '[1,2]' && JSON.stringify(result.retryDelays) === '[500,1000]', 'retry callback reports bounded attempts and real backoff delays');
expect(result.permanentAttempts === 1 && result.permanentCode === 'invalid', 'non-retryable image error is not repeated');
expect(JSON.stringify(result.bulkCalls) === '["First","Last","Last"]', 'bulk retry requests only missing sections');
expect(JSON.stringify(result.afterFailure) === '["Existing","First"]', 'bulk failure preserves every completed image');
expect(JSON.stringify(result.afterRetry) === '["Existing","First","Last"]', 'bulk retry fills the remaining image without duplicates');
expect(result.captions[0] === 'Descriptive alt' && result.captions[1] === '' && result.captions[2] === '' && result.captions[3] === '', 'descriptive fallbacks persist while generic labels fail to a blank editable caption');
expect(result.insertedCaptionCalls === 2 && result.insertedCaptionText.includes('Descriptive alt'), 'automatic captions are inserted and remain editable or removable');
expect(result.editedCaption === 'Edited visible caption' && result.captionRemoved, 'caption edits persist to placed copies and clearing removes the visible caption');
expect(result.noDuplicate === '', 'review output excludes the featured image from article HTML');
expect(result.compiledFeatured.url === 'plain.png', 'featured image remains available for the separate Review preview');
expect(!result.compacted.includes('<p><br></p>') && result.compacted.includes('<img src="plain.png">'), 'saved body removes spacer gaps without removing images');

await page.close();
await browser.close();
