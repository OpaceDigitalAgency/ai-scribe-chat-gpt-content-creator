import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8'),
    fs.readFileSync('assets/css/admin-pages.css', 'utf8')
].join('\n');

const option = (copy) => `<article class="option-card selected"><span class="checkbox">✓</span><strong>${copy}</strong></article>`;
const actions = (next = 'Continue') => `<div class="results-actions"><button class="btn btn-outline">Back</button><button class="btn btn-outline">Generate More</button><button class="btn btn-primary next-step-btn">${next}</button></div>`;
const prose = (heading) => `<article class="stream-output prose-output"><h2>${heading}</h2><p>A useful generated paragraph remains readable and keeps a comfortable measure at every supported viewport. Its words must never be squeezed into a narrow strip by the surrounding controls.</p><p>A second paragraph represents a longer response and confirms that ordinary article copy wraps naturally without creating page-level horizontal scrolling.</p></article>`;
const field = (label, tag = 'input') => `<label class="form-group">${label}${tag === 'textarea' ? '<textarea class="form-control">Editable response content remains fully visible.</textarea>' : '<input class="form-control" value="Editable generated content">'}</label>`;

const panelMarkup = {
    1: `<div class="input-section"><div class="form-group"><label>Article topic or idea</label><div class="input-with-button"><input class="form-control" value="technical SEO tips for this year"><button class="btn btn-primary">Generate</button></div><div class="form-row article-length-override"><div class="form-group"><label>Length for this article</label><select class="form-control"><option>Custom — choose a target</option></select></div><div class="form-group"><label>Custom target words</label><input class="form-control" value="2200"></div></div></div></div><div class="results-section"><div class="choice-guidance"><p>Choose one title to continue.</p><div class="choice-guidance-actions"><span class="choice-selection-status">1 of 5 selected</span></div></div><div class="options-grid">${option('Technical SEO Tips for 2027: A Practical Guide')}${option('Fix the Technical Problems Search Engines Notice')}</div>${actions('Continue to Keywords')}</div>`,
    2: `<div class="results-section"><div class="choice-guidance"><p>Choose one or more keyword suggestions. Demand bands are qualitative AI estimates, not measured search-volume data. Use Google Trends to check relative interest and seasonality.</p><div class="choice-guidance-actions"><span class="choice-selection-status">1 of 5 selected</span><button class="btn btn-link">Select all</button><button class="btn btn-link">Deselect all</button><a class="keyword-compare-link">Compare selected in Google Trends ↗</a></div></div><div class="keywords-grid"><article class="keyword-result selected"><span class="checkbox">✓</span><div class="keyword-content"><strong>technical SEO tips</strong><div class="keyword-stats"><span class="keyword-evidence-badge">Estimated search volume: High</span><a class="keyword-trends-link">Open Google Trends</a></div></div></article></div>${actions('Continue to Outline')}</div>`,
    3: `<div class="results-section"><div class="choice-guidance"><p>Keep the sections you want in the article. You can deselect any heading before continuing.</p><div class="choice-guidance-actions"><span class="choice-selection-status">4 of 5 selected</span><button class="btn btn-link">Select all</button><button class="btn btn-link">Deselect all</button></div></div><div class="options-grid">${option('Core technical SEO fixes that protect crawl efficiency')}${option('How to improve server response times without guesswork')}${option('Structured data checks that prevent avoidable errors')}</div>${actions('Continue to Introduction')}</div>`,
    4: `<div class="results-section">${prose('Introduction')}${actions('Continue to Tagline')}</div>`,
    5: `<div class="results-section"><div class="choice-guidance"><p>Choose one tagline and its position in the final article.</p><div class="choice-guidance-actions"><span class="choice-selection-status">1 of 5 selected</span></div></div><div class="options-grid">${option('Fix the technical foundations before chasing trends')}${option('Faster pages, clearer signals, better results')}</div><fieldset class="placement-options"><legend>Tagline placement</legend><label><input type="radio" checked> Below the introduction</label><label><input type="radio"> Above the introduction</label></fieldset>${actions('Continue to Article Body')}</div>`,
    6: `<div class="results-section"><div class="editor-with-gallery"><aside class="image-gallery image-studio"><div class="image-gallery-header"><div><h3 class="image-gallery-title">Article images</h3><p class="image-gallery-subtitle">Prompts, placement and article-only style controls in one place.</p></div><button class="btn btn-outline image-insert-all">Place all unplaced (3)</button></div><div class="image-create-panel"><label class="image-prompt-label">Create another image</label><p class="image-create-help">Describe the visual. A caption is created automatically.</p><textarea class="form-control image-prompt-input">Editorial image for this article section without text in the visual.</textarea><div class="image-create-actions"><button class="btn btn-primary">Generate image</button><button class="btn btn-outline">Generate section set</button></div></div></aside><div class="quill-editor-container"><div class="quill-editor"><div class="ql-editor">${prose('Article body')}</div></div></div></div>${actions('Continue to Conclusion')}</div>`,
    7: `<div class="results-section">${prose('Conclusion')}${actions('Continue to Q&A')}</div>`,
    8: `<div class="results-section"><p class="step-subtitle">Untick any Q&amp;A you do not want in the article. The wording can be edited before you continue.</p><div class="qa-options"><div class="qa-bulk-toolbar"><label class="qa-bulk-control"><input class="qa-bulk-checkbox" type="checkbox" checked> Deselect all</label><span class="qa-bulk-count">3 of 3 included</span></div><article class="qa-item-card included"><label class="qa-item-include"><input class="qa-item-checkbox" type="checkbox" checked> Include in article</label><input class="form-control qa-item-question" value="How often should technical SEO be audited?"><textarea class="form-control qa-item-answer">Review the main technical signals quarterly and after substantial platform changes.</textarea></article></div>${actions('Continue to SEO')}</div>`,
    9: `<div class="results-section"><article class="seo-combined-input selected"><span class="checkbox">✓</span><div class="seo-field"><label>Meta title</label><input class="form-control" value="Technical SEO Tips for 2027 | Practical Guide"><span class="meta-count in-range">49/60</span></div><div class="seo-field"><label>Meta description</label><textarea class="form-control">Fix crawl, speed and structured data problems with practical technical SEO guidance.</textarea><span class="meta-count in-range">93/160</span></div></article>${actions('Continue to Review')}</div>`,
    10: `<div class="review-content"><section class="featured-image-review"><div class="featured-image-review-copy"><h3>Featured image preview</h3><p>Used as the WordPress featured image; it is not duplicated inside the article.</p></div></section><div class="editor-with-gallery"><aside class="image-gallery image-studio"><div class="image-gallery-header"><div><h3 class="image-gallery-title">Article images</h3><p class="image-gallery-subtitle">Edit captions, prompts and placement.</p></div><button class="btn btn-outline image-insert-all">All images placed</button></div></aside><div class="quill-editor-container"><div class="quill-editor"><div class="ql-editor">${prose('Review article')}</div></div></div></div><section class="publishing-details"><div class="publishing-details-heading"><h3>Publishing details</h3><p>Suggested from this article and confirmed when the post is saved.</p></div><div class="publishing-details-grid">${field('Category')}${field('Tags')}</div></section><section class="save-status-card is-unsaved"><div class="save-status-summary"><div class="save-status-copy"><h3>Save status</h3><p>Not saved. This article currently exists only in AI-Scribe.</p></div><span class="save-status-badge">Not saved</span></div><div class="save-status-actions"><button class="btn btn-outline">Save as Draft</button><button class="btn btn-outline">Publish Post</button><button class="btn btn-outline">Save as Shortcode</button></div></section>${actions('Continue to Evaluate')}</div>`,
    11: `<div class="results-section"><p class="step-subtitle">A factual review of the final article. Measured facts come from the Review HTML.</p><div class="evaluation-table-container stream-output"><div class="evaluation-report-region"><table class="evaluation-report-table"><thead><tr><th>Status</th><th>Check</th><th>Evidence</th><th>What to do</th></tr></thead><tbody><tr class="eval-row eval-pass"><td class="eval-status-cell">Pass</td><td>Image accessibility markup</td><td>Nine image elements are present and all have alt attributes.</td><td>No action needed.</td></tr><tr class="eval-row eval-warn"><td class="eval-status-cell">Check</td><td>External contextual links</td><td>No contextual external links are present. Table-of-contents anchors are excluded.</td><td>Add a relevant source where it helps the reader.</td></tr></tbody></table></div></div><section class="save-status-card is-unsaved"><div class="save-status-summary"><div class="save-status-copy"><h3>Save status</h3><p>Not saved. This article currently exists only in AI-Scribe.</p></div><span class="save-status-badge">Not saved</span></div></section>${actions('Finish & Start New')}</div>`
};

const appMarkup = (step) => `
<div id="ai-scribe-root" class="app-container ai-scribe-app">
  <header class="app-header"><div class="header-content">
    <div class="logo-section"><span class="logo-image"></span><div class="logo-text"><h1>AI-Scribe</h1><span class="version">v3.2.19</span></div></div>
    <div class="header-center"><div class="mode-switcher"><button class="btn btn-primary mode-btn">Wizard</button><button class="btn btn-outline mode-btn">Express</button></div><div class="progress-container"><div class="progress-info"><span>Step ${step} of 11 — Responsive test</span><span>${Math.round(step / 11 * 100)}%</span><button class="btn btn-outline btn-reset">Start Again</button></div><div class="progress-bar"><div class="progress-fill" style="width:${step / 11 * 100}%"></div></div></div></div>
    <div class="header-right"><div class="cost-display"><div class="cost-items"><div class="cost-item">Total: <strong>£0.0420</strong></div><div class="cost-item">Last step: <strong>£0.0042</strong></div></div></div><button class="theme-toggle">◐</button></div>
  </div></header>
  <main class="app-main"><nav class="step-navigation"><div class="steps-container">${Array.from({ length: 11 }, (_, index) => `<button class="step ${index + 1 === step ? 'active' : ''}"><span class="step-icon">${index + 1}</span><span class="step-label">Step ${index + 1}</span></button>`).join('')}</div></nav>
    <div class="two-column-layout"><div class="main-panel"><div class="workflow-container"><section class="step-content active" data-step-panel="${step}"><h2 class="step-heading">${['', 'Title Generation', 'Keyword Research', 'Article Outline', 'Introduction', 'Tagline', 'Article Body', 'Conclusion', 'Questions & Answers', 'SEO Meta', 'Review & Edit', 'Article Quality Analysis'][step]}</h2>${panelMarkup[step]}</section></div></div>
      <aside class="settings-panel"><div class="panel-header"><h2>Options &amp; Prompt</h2></div><div class="panel-content"><div class="settings-section"><h3 class="settings-section-title">Selected model</h3><div class="model-info">gemini-3.7-flash · Google Gemini</div></div><div class="settings-section"><h3 class="settings-section-title">Current prompt</h3><textarea class="form-control">A complete prompt for this step remains readable and editable at every supported viewport.</textarea><button class="btn btn-outline">Run amended prompt</button></div></div></aside>
    </div>
  </main>
</div>`;

const viewports = [
    { width: 375, height: 812 },
    { width: 768, height: 900 },
    { width: 1024, height: 900 },
    { width: 1280, height: 900 },
    { width: 1440, height: 1000 }
];

const browser = await chromium.launch({ headless: true });
try {
    for (const viewport of viewports) {
        for (let step = 1; step <= 11; step += 1) {
            const page = await browser.newPage({ viewport });
            await page.setContent(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>html,body{margin:0}${css}</style></head><body>${appMarkup(step)}</body></html>`);
            const label = `step ${step} at ${viewport.width}px`;

            const geometry = await page.evaluate(() => {
                const rect = (selector) => {
                    const node = document.querySelector(selector);
                    const box = node?.getBoundingClientRect();
                    return box ? { x: box.x, y: box.y, width: box.width, right: box.right, bottom: box.bottom } : null;
                };
                const overflowers = [...document.querySelectorAll('body *')]
                    .filter((node) => {
                        const box = node.getBoundingClientRect();
                        return box.right > document.documentElement.clientWidth + 1 || box.left < -1;
                    })
                    .slice(0, 8)
                    .map((node) => `${node.tagName.toLowerCase()}.${node.className}`);
                return {
                    viewport: document.documentElement.clientWidth,
                    documentWidth: document.documentElement.scrollWidth,
                    layoutDirection: getComputedStyle(document.querySelector('.two-column-layout')).flexDirection,
                    main: rect('.main-panel'),
                    settings: rect('.settings-panel'),
                    panel: rect('.step-content.active'),
                    prompt: rect('.settings-panel textarea'),
                    header: rect('.header-content'),
                    overflowers
                };
            });

            assert.ok(geometry.main && geometry.settings && geometry.panel && geometry.prompt && geometry.header, `${label}: frame renders`);
            assert.ok(geometry.documentWidth <= geometry.viewport + 1, `${label}: page has no horizontal overflow ${geometry.documentWidth}/${geometry.viewport} (${geometry.overflowers.join(', ')})`);
            for (const [name, box] of Object.entries({ main: geometry.main, settings: geometry.settings, panel: geometry.panel, prompt: geometry.prompt, header: geometry.header })) {
                assert.ok(box.x >= -1 && box.right <= geometry.viewport + 1, `${label}: ${name} stays inside viewport`);
            }
            assert.ok(geometry.panel.width >= Math.min(300, viewport.width - 48), `${label}: active panel keeps a readable width`);
            assert.ok(geometry.prompt.width >= Math.min(260, viewport.width - 64), `${label}: prompt editor keeps a readable width`);

            if (viewport.width <= 1400) {
                assert.equal(geometry.layoutDirection, 'column', `${label}: prompt panel stacks below main content`);
                assert.ok(geometry.settings.y >= geometry.main.bottom - 1, `${label}: stacked prompt panel does not squeeze main content`);
            } else {
                assert.equal(geometry.layoutDirection, 'row', `${label}: wide desktop uses the two-column workspace`);
                assert.ok(geometry.main.width >= 900, `${label}: wide desktop main column remains readable`);
                assert.ok(geometry.settings.width >= 380, `${label}: wide desktop prompt panel remains usable`);
            }

            assert.ok(await page.locator('.settings-panel textarea').evaluate((node) => node.scrollWidth <= node.clientWidth), `${label}: prompt text is contained`);
            assert.ok(await page.locator('.results-actions, .save-status-actions').evaluateAll((groups) => groups.every((group) => group.scrollWidth <= group.clientWidth)), `${label}: action rows stay contained`);

            if (step === 2) {
                const guidance = await page.locator('.choice-guidance').evaluate((node) => {
                    const copy = node.querySelector(':scope > p').getBoundingClientRect();
                    const actionsBox = node.querySelector('.choice-guidance-actions').getBoundingClientRect();
                    const box = node.getBoundingClientRect();
                    return { width: box.width, copyWidth: copy.width, actionsWidth: actionsBox.width, actionsBelow: actionsBox.top >= copy.bottom - 1 };
                });
                assert.ok(guidance.copyWidth >= guidance.width - 40, `${label}: keyword guidance copy uses the row width`);
                assert.ok(guidance.actionsWidth >= guidance.width - 40, `${label}: keyword actions use their own row`);
                assert.equal(guidance.actionsBelow, true, `${label}: keyword actions sit below the guidance copy`);
            }

            if (step === 6 || step === 10) {
                const nested = await page.evaluate(() => {
                    const studio = document.querySelector('.image-studio').getBoundingClientRect();
                    const editor = document.querySelector('.quill-editor-container').getBoundingClientRect();
                    return { studio, editor, direction: getComputedStyle(document.querySelector('.editor-with-gallery')).flexDirection };
                });
                assert.ok(nested.studio.width >= Math.min(280, geometry.panel.width - 32), `${label}: image studio is not crushed`);
                assert.ok(nested.editor.width >= Math.min(300, geometry.panel.width - 32), `${label}: article editor is not crushed`);
                if (viewport.width <= 1024) assert.equal(nested.direction, 'column', `${label}: article editor and image studio stack on smaller screens`);
            }

            if (step === 8 || step === 9) {
                assert.ok(await page.locator('.qa-item-card, .seo-combined-input').evaluateAll((cards) => cards.every((card) => card.scrollWidth <= card.clientWidth)), `${label}: editable content cards remain full-width and contained`);
            }

            if (step === 11) {
                const report = page.locator('.evaluation-report-region');
                const minimumReportWidth = Math.min(280, geometry.panel.width - 32);
                assert.ok(await report.evaluate((node, minimum) => node.getBoundingClientRect().width >= minimum, minimumReportWidth), `${label}: evaluation report uses the available panel width`);
            }

            await page.close();
        }
    }
    console.log('Responsive workspace: all 11 Wizard steps passed at 375/768/1024/1280/1440 (55 frame cases).');
} finally {
    await browser.close();
}
