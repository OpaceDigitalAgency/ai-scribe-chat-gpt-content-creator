import { chromium } from 'playwright';
import fs from 'node:fs';

const css = [
  fs.readFileSync('assets/css/main.css', 'utf8'),
  fs.readFileSync('assets/css/components.css', 'utf8'),
].join('\n');

const expect = (condition, message) => {
  if (!condition) throw new Error(message);
  process.stdout.write(`PASS ${message}\n`);
};

const image = 'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%22640%22%20height=%22420%22%3E%3Crect%20width=%22640%22%20height=%22420%22%20fill=%22%23dbeafe%22/%3E%3C/svg%3E';
const markupForStep = (step) => `
<div id="ai-scribe-root" class="ai-scribe-app">
  <main class="app-main">
    <div class="two-column-layout">
      <section class="main-panel">
        <section class="step-content active" data-step-panel="${step}">
          <div class="editor-with-gallery">
            <aside class="image-gallery image-studio" aria-label="Article image studio">
              <div class="image-gallery-header">
                <div><h3 class="image-gallery-title">Article images</h3><p class="image-gallery-subtitle">Prompts, placement and article-only style controls in one place.</p></div>
                <button class="btn btn-outline image-insert-all">Place all unplaced (3)</button>
              </div>
              <div class="image-generation-status is-active">
                <span class="image-generation-status-icon">✦</span>
                <span class="image-generation-status-copy"><strong>Creating section image set</strong><span>Completed images are kept if a later request fails.</span></span>
                <time>37s</time>
                <ol class="image-progress-queue"><li data-state="retrying"><span class="image-progress-marker"></span><span class="image-progress-label">Long section heading remains readable while retrying</span><span class="image-progress-state">Retry 1 of 2</span></li></ol>
              </div>
              <div class="image-gallery-grid">
                <article class="gallery-item is-placed">
                  <div class="gallery-media"><img class="gallery-image" alt="Responsive workflow" src="${image}"></div>
                  <div class="gallery-card-content">
                    <div class="gallery-card-topline"><span class="gallery-badge">Featured image</span><span class="gallery-placement-state">In article</span></div>
                    <label class="gallery-caption-label">Caption<textarea class="form-control gallery-caption-editor">A complete automatic caption that remains readable without clipping on narrow screens.</textarea></label>
                    <details class="gallery-prompt-details" open><summary>View or edit prompt</summary><textarea class="form-control gallery-prompt-editor">Editorial image for a detailed responsive design section, without words in the visual.</textarea><div class="gallery-prompt-actions"><button class="btn btn-link">Save prompt</button><button class="btn btn-outline">Regenerate image</button></div></details>
                    <div class="gallery-item-status" data-state="complete">Image ready and placed near the selected section.</div>
                    <button class="btn btn-outline gallery-place-btn">Place near section</button>
                  </div>
                </article>
              </div>
              <div class="image-bulk-placement"><p>Finished reviewing the generated images?</p><button class="btn btn-outline image-insert-all">Place all unplaced (3)</button></div>
              <div class="image-create-panel">
                <label class="image-prompt-label">Create another image</label>
                <p class="image-create-help">Describe the visual. A caption is created automatically.</p>
                <textarea class="form-control image-prompt-input">Editorial image for the article introduction.</textarea>
                <div class="image-create-actions"><button class="btn btn-primary">Generate image</button><button class="btn btn-outline">Generate section set</button></div>
              </div>
              <details class="image-override-panel" open>
                <summary><span>Style &amp; output for this article</span><strong>Custom for this article</strong></summary>
                <label class="image-override-toggle"><input type="checkbox" checked>Use custom settings for this article</label>
                <div class="image-override-grid"><label>Visual style<select class="form-control"><option>Pencil sketch</option></select></label><label>Size<select class="form-control"><option>Landscape</option></select></label><label>Quality<select class="form-control"><option>High</option></select></label><label>Format<select class="form-control"><option>WebP</option></select></label></div>
              </details>
            </aside>
            <div class="quill-editor-container"><div class="quill-editor"><div class="ql-editor"><h2>Article body</h2><p>Article copy remains readable beside or below the image studio.</p><img src="${image}" alt="Placed responsive workflow"><p class="ai-scribe-image-caption">A complete caption beneath the placed image.</p></div></div></div>
          </div>
        </section>
      </section>
      <aside class="settings-panel"><div class="panel-header"><h3>Options &amp; prompt</h3></div><div class="panel-content"><textarea class="form-control">Current prompt remains usable.</textarea></div></aside>
    </div>
  </main>
</div>`;

const browser = await chromium.launch({ headless: true });

for (const [width, height, dark] of [
  [375, 812, false],
  [768, 900, true],
  [1024, 900, false],
  [1280, 900, true],
  [1440, 1000, false],
]) {
  for (const step of [6, 10]) {
    const page = await browser.newPage({ viewport: { width, height } });
    await page.setContent(`<style>html,body{margin:0}${css}</style>${markupForStep(step)}`);
    if (dark) await page.evaluate(() => document.documentElement.setAttribute('data-ai-scribe-theme', 'dark'));

    const label = `step ${step} at ${width}px`;
    const studio = page.locator('.image-studio');
    const studioBox = await studio.boundingBox();
    const editorBox = await page.locator('.quill-editor-container').boundingBox();
    expect(Boolean(studioBox && editorBox), `${label} studio and editor render`);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), `${label} page has no horizontal overflow`);
    expect(await studio.evaluate((el) => el.scrollWidth <= el.clientWidth), `${label} studio has no clipped horizontal content`);

    const header = page.locator('.image-gallery-header');
    const headerDirection = await header.evaluate((el) => getComputedStyle(el).flexDirection);
    if (studioBox.width <= 512) expect(headerDirection === 'column', `${label} narrow studio stacks its heading and place-all action`);

    if (width <= 1024) expect(editorBox.y < studioBox.y, `${label} presents the article editor before the image tools`);
    expect((await page.locator('.image-create-actions .btn').evaluateAll((buttons) => buttons.every((button) => button.scrollWidth <= button.clientWidth && button.getBoundingClientRect().height >= 44))), `${label} generation controls wrap inside 44px touch targets`);
    expect((await page.locator('.image-override-grid .form-control').evaluateAll((fields) => fields.every((field) => field.scrollWidth <= field.clientWidth))), `${label} article style controls remain contained`);
    expect((await page.locator('.gallery-caption-editor').evaluate((field) => field.scrollWidth <= field.clientWidth && field.clientHeight >= 54)), `${label} caption editor remains readable`);
    expect((await page.locator('.gallery-prompt-editor').evaluate((field) => field.scrollWidth <= field.clientWidth)), `${label} image prompt remains contained`);
    expect((await page.locator('.gallery-place-btn').evaluate((button) => button.getBoundingClientRect().height >= 40 && button.scrollWidth <= button.clientWidth)), `${label} placement action remains usable`);
    expect((await page.locator('.settings-panel textarea').evaluate((field) => field.scrollWidth <= field.clientWidth)), `${label} Options & Prompt remains usable`);

    if (dark) {
      const background = await studio.evaluate((el) => getComputedStyle(el).backgroundColor);
      const colour = await studio.evaluate((el) => getComputedStyle(el).color);
      expect(background !== 'rgba(0, 0, 0, 0)' && colour !== background, `${label} dark mode keeps a distinct readable studio surface`);
    }
    if (process.env.AI_SCRIBE_SCREENSHOTS && step === 6) {
      await page.screenshot({ path: `/tmp/ai-scribe-image-studio-${width}${dark ? '-dark' : ''}.png`, fullPage: true });
    }
    await page.close();
  }
}

await browser.close();
