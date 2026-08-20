import { chromium } from 'playwright';
import fs from 'node:fs';

const css = [
  fs.readFileSync('assets/css/main.css', 'utf8'),
  fs.readFileSync('assets/css/components.css', 'utf8')
].join('\n');
const template = fs.readFileSync('templates/create_template.php', 'utf8');
const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const apiClientSource = fs.readFileSync('assets/js/services/ApiClient.js', 'utf8');
const appStateSource = fs.readFileSync('assets/js/core/AppState.js', 'utf8');

const expect = (condition, message) => {
  if (!condition) throw new Error(message);
  process.stdout.write(`PASS ${message}\n`);
};

const studio = `
<div id="ai-scribe-root" class="ai-scribe-app"><section data-step-panel="6">
<div class="editor-with-gallery"><aside class="image-gallery image-studio">
<div class="image-gallery-header"><div><h3 class="image-gallery-title">Article images</h3><p class="image-gallery-subtitle">Prompts, placement and article-only style controls in one place.</p></div><button class="btn btn-outline image-insert-all">Place all unplaced (2)</button></div>
<details class="image-override-panel" open><summary><span>Style & output for this article</span><strong>Custom for this article</strong></summary><label class="image-override-toggle"><input type="checkbox" checked> Use custom settings for this article</label><div class="image-override-grid"><label>Visual style<input class="form-control" value="Editorial line drawing"></label><label>Size<select class="form-control"><option>Landscape</option></select></label><label>Quality<select class="form-control"><option>High</option></select></label><label>Format<select class="form-control"><option>WebP</option></select></label></div></details>
<div class="image-create-panel"><label>Prompt for a new image</label><textarea class="form-control image-prompt-input">Editorial image for the article introduction. No text.</textarea><div class="image-create-actions"><button class="btn btn-primary">Generate image</button><button class="btn btn-outline">Generate section set</button></div></div>
<div class="image-gallery-grid"><article class="gallery-item is-placed"><div class="gallery-media"><img class="gallery-image" alt="Mechanical search engine illustration" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect width='200' height='200' fill='%23dbeafe'/%3E%3C/svg%3E"></div><div class="gallery-card-content"><div class="gallery-card-topline"><span class="gallery-badge">Featured image</span><span class="gallery-placement-state">In article</span></div><details class="gallery-prompt-details"><summary>View or edit prompt</summary><textarea class="form-control gallery-prompt-editor">Editorial image for the introduction. No text.</textarea><div class="gallery-prompt-actions"><button class="btn btn-link">Save prompt</button><button class="btn btn-outline">Regenerate image</button></div></details><button class="btn btn-outline gallery-place-btn" disabled>Already placed</button></div></article>
<article class="gallery-item"><div class="gallery-media"><img class="gallery-image" alt="Keyword research workflow" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect width='200' height='200' fill='%23dcfce7'/%3E%3C/svg%3E"></div><div class="gallery-card-content"><div class="gallery-card-topline"><span class="gallery-badge">Keyword research</span><span class="gallery-placement-state">Not placed</span></div><details class="gallery-prompt-details" open><summary>View or edit prompt</summary><textarea class="form-control gallery-prompt-editor">Show a practical keyword research workflow. Editorial line drawing. No text.</textarea><div class="gallery-prompt-actions"><button class="btn btn-link">Save prompt</button><button class="btn btn-outline">Regenerate image</button></div></details><button class="btn btn-outline gallery-place-btn">Place near section</button></div></article></div>
</aside><div class="quill-editor-container"><div class="quill-editor"><div class="ql-editor"><h2>Keyword research</h2><p>Article copy remains readable beside the image studio.</p></div></div></div></div></section></div>`;

const browser = await chromium.launch({ headless: true });
for (const [width, height] of [[375, 812], [768, 900], [1440, 1000]]) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.setContent(`<style>${css}</style>${studio}`);
  if (width === 768) await page.evaluate(() => document.documentElement.setAttribute('data-ai-scribe-theme', 'dark'));
  expect(await page.locator('.image-studio').isVisible(), `${width}px studio visible`);
  expect((await page.locator('.gallery-item').count()) === 2, `${width}px cards readable`);
  expect(await page.locator('.gallery-prompt-editor').last().isEditable(), `${width}px prompt editable`);
  expect((await page.locator('body').evaluate(el => el.scrollWidth <= innerWidth)), `${width}px no horizontal overflow`);
  if (width === 1440) {
    await page.screenshot({ path: '../.agent/docs/ai-scribe/evidence/image-studio-1440-2026-08-13.png', fullPage: true });
  }
  await page.close();
}
expect(template.includes('Place all unplaced'), 'template exposes place-all control');
expect((template.match(/data-action="insert-all-images"/g) || []).length === 2, 'template exposes place-all above and below generated image cards');
expect(template.includes('data-testid="insert-all-images-bottom"'), 'bottom place-all control has a stable test hook');
expect(template.includes('Use custom settings for this article'), 'template exposes article-local override');
expect(!template.includes('data-testid="insert-image-end"'), 'template no longer renders repeated insert-at-end controls');

const behaviour = await (async () => {
  const page = await browser.newPage();
  await page.setContent('<div class="ql-editor"><p>Intro</p></div>');
  await page.addScriptTag({ content: `${controllerSource}\nwindow.ImageWorkflowController = WizardFlowController;` });
  const result = await page.evaluate(() => {
    const root = document.querySelector('.ql-editor');
    const quill = {
      root,
      getLength: () => root.children.length + 1,
      setSelection: () => {},
      insertEmbed: (index, type, url) => {
        const paragraph = document.createElement('p');
        const img = document.createElement('img');
        img.src = url;
        paragraph.appendChild(img);
        root.appendChild(paragraph);
      },
      insertText: (index, text) => {
        if (!text.trim()) return;
        const paragraph = document.createElement('p');
        paragraph.textContent = text.trim();
        root.appendChild(paragraph);
      },
      formatLine: () => {}
    };
    const controller = Object.create(window.ImageWorkflowController.prototype);
    const state = { galleryImages: [
      { url: 'https://example.test/featured.png', alt_text: 'Featured', caption: 'Featured illustration', attachment_id: 1 },
      { url: 'https://example.test/section.png', alt_text: 'Section', caption: 'Section illustration', attachment_id: 2 }
    ] };
    controller.appState = {
      getStateSlice: (key) => state[key],
      setStateSlice: (key, value) => { state[key] = value; }
    };
    controller.registry = { get: () => ({ persistEdit: () => {} }) };
    controller.editorForStep = () => quill;
    controller.updateGalleryStates = () => {};
    controller.announceImages = () => {};
    controller.insertAllImages(6);
    const firstCount = root.querySelectorAll('img').length;
    controller.insertAllImages(6);
    return { firstCount, secondCount: root.querySelectorAll('img').length, html: root.innerHTML };
  });
  await page.close();
  return result;
})();
expect(behaviour.firstCount === 2, 'place-all inserts each unplaced image once');
expect(behaviour.secondCount === 2, 'place-all never duplicates already placed images');
expect((behaviour.html.match(/ai-scribe-image-caption/g) || []).length === 2, 'place-all inserts each generated image with its automatic caption');

const mutations = await (async () => {
  const page = await browser.newPage();
  await page.setContent('<div class="ql-editor"><p>Intro</p><img src="https://example.test/old.png" alt="Old"></div>');
  await page.addScriptTag({ content: `${controllerSource}\nwindow.ImageWorkflowController = WizardFlowController;` });
  const result = await page.evaluate(() => {
    const root = document.querySelector('.ql-editor');
    window.Quill = { find: (node) => ({ node, length: () => 1 }) };
    const quill = {
      root,
      getIndex: (blot) => Array.from(root.querySelectorAll('img')).indexOf(blot.node),
      deleteText: (index) => root.querySelectorAll('img')[index]?.remove(),
      insertEmbed: (index, type, url) => {
        const img = document.createElement('img'); img.src = url; root.appendChild(img);
      },
      setSelection: () => {}
    };
    const state = { galleryImages: [
      { url: 'https://example.test/old.png', alt_text: 'Old', attachment_id: 1 },
      { url: 'https://example.test/new.png', alt_text: 'Replacement', attachment_id: 2 }
    ] };
    const controller = Object.create(window.ImageWorkflowController.prototype);
    controller.imageToolbar = null;
    controller.appState = { getStateSlice: key => state[key], setStateSlice: (key, value) => { state[key] = value; } };
    controller.registry = { get: () => ({ persistEdit: () => {} }) };
    controller.updateGalleryStates = () => {};
    controller.announceImages = () => {};
    const old = root.querySelector('img');
    controller.replaceEmbeddedImage(6, quill, old, state.galleryImages[1]);
    const replacement = root.querySelector('img');
    const replaced = replacement?.src.endsWith('/new.png') && replacement.alt === 'Replacement';
    controller.deleteEmbeddedImage(6, quill, replacement);
    return { replaced, remaining: root.querySelectorAll('img').length };
  });
  await page.close();
  return result;
})();
expect(mutations.replaced, 'replace swaps the selected editor image and preserves replacement alt text');
expect(mutations.remaining === 0, 'remove deletes only the inserted editor copy');

const requestScope = await (async () => {
  const page = await browser.newPage();
  await page.addScriptTag({ content: `${apiClientSource}\nwindow.ImageApiClient = ApiClient;` });
  const result = await page.evaluate(async () => {
    const api = Object.create(window.ImageApiClient.prototype);
    api.request = async (action, fields) => ({ action, fields });
    const options = { style: 'Ink wash', size: '1536x1024', quality: 'high', format: 'webp', background: 'opaque' };
    return {
      single: await api.generateImage('Exact visible prompt', options),
      section: await api.generateSectionImage(['Section'], 0, 'Article', '', 'Exact section prompt', options)
    };
  });
  await page.close();
  return result;
})();
expect(requestScope.single.fields.prompt === 'Exact visible prompt', 'single image sends the exact visible prompt');
expect(JSON.parse(requestScope.single.fields.image_options).style === 'Ink wash', 'single image sends request-scoped article options');
expect(requestScope.section.fields.prompt === 'Exact section prompt', 'section image sends the exact edited prompt');
expect(JSON.parse(requestScope.section.fields.image_options).format === 'webp', 'section image sends request-scoped output options');

const restored = await (async () => {
  const page = await browser.newPage();
  await page.route('http://state.test/**', route => route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>State test</title>' }));
  await page.goto('http://state.test/');
  await page.addScriptTag({ content: `${appStateSource}\nwindow.ImageAppState = AppState;` });
  const result = await page.evaluate(() => {
    localStorage.clear(); sessionStorage.clear();
    const first = new window.ImageAppState();
    first.setStateSlice('galleryImages', [{ url: 'saved.png', prompt_used: 'Actual prompt', prompt_draft: 'Edited prompt' }]);
    first.setStateSlice('imageOverrides', { enabled: true, values: { style: 'Ink wash' } });
    const second = new window.ImageAppState();
    const loaded = second.loadFromLocalStorage();
    return { loaded, gallery: second.getStateSlice('galleryImages'), overrides: second.getStateSlice('imageOverrides') };
  });
  await page.close();
  return result;
})();
expect(restored.loaded && restored.gallery[0].prompt_used === 'Actual prompt', 'reload restores gallery prompt evidence');
expect(restored.gallery[0].prompt_draft === 'Edited prompt', 'reload restores the separate regeneration draft');
expect(restored.overrides.enabled && restored.overrides.values.style === 'Ink wash', 'reload restores article-local overrides');
await browser.close();
