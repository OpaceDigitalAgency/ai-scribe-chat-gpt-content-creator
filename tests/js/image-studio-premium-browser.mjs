import { chromium } from 'playwright';
import fs from 'node:fs';

const css = [fs.readFileSync('assets/css/main.css', 'utf8'), fs.readFileSync('assets/css/components.css', 'utf8')].join('\n');
const source = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const bodySource = fs.readFileSync('assets/js/views/steps/BodyStepView.js', 'utf8');
const baseSource = fs.readFileSync('assets/js/views/steps/BaseStepView.js', 'utf8');
const streamingSource = fs.readFileSync('assets/js/views/steps/StreamingStepView.js', 'utf8');
const template = fs.readFileSync('templates/create_template.php', 'utf8');
const expect = (condition, message) => {
  if (!condition) throw new Error(message);
  process.stdout.write(`PASS ${message}\n`);
};

expect(template.indexOf('data-testid="image-gallery"') < template.indexOf('data-testid="image-plan"'), 'generated results render before optional prompt plan');
expect(template.includes('<select class="form-control" data-image-option="style"'), 'article visual style uses a preset select');
expect(template.includes('data-testid="image-generation-status"'), 'studio exposes an accessible persistent progress panel');
expect(template.includes('data-testid="image-progress-queue"'), 'studio exposes truthful per-image queue progress');

const markup = `<div id="ai-scribe-root" class="ai-scribe-app"><section data-step-panel="6"><div class="editor-with-gallery">
<aside class="image-gallery image-studio"><div class="image-gallery-header"><div><h3 class="image-gallery-title">Article images</h3><p class="image-gallery-subtitle">Create, place and refine visuals.</p></div><button class="btn btn-outline image-insert-all">Place all unplaced (1)</button></div>
<div class="image-generation-status is-active" role="status" aria-live="polite"><span class="image-generation-status-icon">✦</span><span class="image-generation-status-copy"><strong>Creating section image set</strong><span>Completed images are kept if a later request fails.</span></span><time>37s</time><ol class="image-progress-queue"><li data-state="complete"><span class="image-progress-marker"></span><span class="image-progress-label">Introduction</span><span class="image-progress-state">Ready</span></li><li data-state="retrying"><span class="image-progress-marker"></span><span class="image-progress-label">A section heading that remains readable on narrow screens</span><span class="image-progress-state">Retry 1 of 2</span></li><li data-state="waiting"><span class="image-progress-marker"></span><span class="image-progress-label">Conclusion</span><span class="image-progress-state">Waiting</span></li></ol></div>
<div class="image-gallery-grid"><article class="gallery-item"><div class="gallery-media"><img class="gallery-image" alt="Web design workflow" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect width='300' height='200' fill='%23dbeafe'/%3E%3C/svg%3E"></div><div class="gallery-card-content"><div class="gallery-card-topline"><span class="gallery-badge">Featured image</span><span class="gallery-placement-state">In article</span></div><label class="gallery-caption-label">Caption<input class="form-control gallery-caption-editor" value="A practical web design workflow."></label><details class="gallery-prompt-details"><summary>View or edit prompt</summary><textarea class="form-control gallery-prompt-editor">Prompt evidence</textarea></details><button class="btn btn-outline gallery-place-btn">Place near section</button></div></article></div>
<div class="image-create-panel"><label>Create another image</label><p class="image-create-help">Describe the visual.</p><textarea class="form-control image-prompt-input"></textarea><div class="image-create-actions"><button class="btn btn-primary">Generate image</button><button class="btn btn-outline">Generate section set</button></div><details class="image-plan-details"><summary>8 section image prompts</summary></details></div>
<details class="image-override-panel" open><summary><span>Style & output</span><strong>Custom</strong></summary><label class="image-override-toggle"><input type="checkbox" checked>Use custom settings</label><div class="image-override-grid"><label>Visual style<select class="form-control"><option>Pencil sketch</option></select></label><label>Size<select class="form-control"><option>Square</option></select></label><label>Quality<select class="form-control"><option>Medium</option></select></label><label>Format<select class="form-control"><option>PNG</option></select></label><label>Background<select class="form-control"><option>Auto</option></select></label></div><button class="btn btn-link image-reset-overrides">Reset to global settings</button></details></aside>
<div class="quill-editor-container"><div class="quill-editor"><div class="ql-editor"><p>Article introduction.</p><p><img alt="Web design workflow"></p><p class="ai-scribe-image-caption">A practical web design workflow.</p><h2>Next section</h2><p>Body copy.</p></div></div></div></div></section></div>`;

const browser = await chromium.launch({ headless: true });
for (const [width, height, dark] of [[375, 812, false], [768, 900, true], [1440, 1000, false]]) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.setContent(`<style>${css}</style>${markup}`);
  if (dark) await page.evaluate(() => document.documentElement.setAttribute('data-ai-scribe-theme', 'dark'));
  expect(await page.locator('.image-generation-status').isVisible(), `${width}px progress is visible`);
  expect((await page.locator('.image-progress-queue li').count()) === 3, `${width}px item-by-item queue remains visible`);
  expect(await page.locator('.gallery-caption-editor').isEditable(), `${width}px caption is editable`);
  expect(await page.locator('.image-override-grid select').first().isEnabled(), `${width}px style preset is usable`);
  expect(await page.locator('body').evaluate((el) => el.scrollWidth <= innerWidth), `${width}px has no horizontal overflow`);
  const buttonBoxes = await page.locator('.image-create-actions .btn').evaluateAll((els) => els.map((el) => ({ scroll: el.scrollWidth, client: el.clientWidth })));
  expect(buttonBoxes.every((box) => box.scroll <= box.client), `${width}px button labels stay inside controls`);
  await page.close();
}

const behaviour = await (async () => {
  const page = await browser.newPage();
  await page.setContent('<section data-step-panel="6"><button data-action="add-image"></button><div data-testid="image-generation-status" hidden><strong data-image-progress-title></strong><span data-image-progress-detail></span><time data-image-progress-time></time></div><div class="ql-editor"><p>Body ready</p></div></section>');
  await page.addScriptTag({ content: `${source}\nwindow.ImageController = WizardFlowController;${baseSource}\n${streamingSource}\n${bodySource}\nwindow.ImageBody = BodyStepView;` });
  const result = await page.evaluate(async () => {
    window.ai_scribe = { images: { enabled: true, available: true } };
    const state = {};
    const controller = Object.create(window.ImageController.prototype);
    controller.imageOperationPending = false;
    controller.registry = { get: () => ({ state: 'ready' }) };
    controller.appState = { getStateSlice: (key) => state[key], setStateSlice: (key, value) => { state[key] = value; } };
    controller.galleryImages = () => state.galleryImages || [];
    controller.editorForStep = () => ({ root: document.querySelector('.ql-editor') });
    let calls = 0;
    controller.addImage = async () => { calls += 1; state.galleryImages = [{ url: 'ready.png' }]; return state.galleryImages[0]; };
    await controller.maybeAutoGenerateFeaturedImage();
    await controller.maybeAutoGenerateFeaturedImage();
    const compacted = window.ImageBody.compactEditorHtml('<p>Before</p><p><br></p><p>   </p><p><img src="x.png"></p><p>After</p>');
    return { calls, started: state.featuredImageAutoStarted, compacted };
  });
  await page.close();
  return result;
})();
expect(behaviour.calls === 1 && behaviour.started, 'featured image auto-generation is exactly-once across repeat entry');
expect(!behaviour.compacted.includes('<p><br></p>') && !behaviour.compacted.includes('<p>   </p>'), 'editor export removes empty Quill spacer blocks');
expect(behaviour.compacted.includes('<img src="x.png">'), 'gap compaction preserves real image content');

await browser.close();
