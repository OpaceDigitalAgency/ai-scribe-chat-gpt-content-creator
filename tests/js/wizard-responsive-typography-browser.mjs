import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8'),
    fs.readFileSync('assets/css/admin-pages.css', 'utf8')
].join('\n');

const stepNames = [
    '',
    'Title Generation',
    'Keyword Research',
    'Article Outline',
    'Introduction',
    'Tagline',
    'Article Body',
    'Conclusion',
    'Questions & Answers',
    'SEO Meta',
    'Review & Edit',
    'Article Quality Analysis'
];

const stepBody = (step) => {
    const sharedActions = `<div class="results-actions">
      <button class="btn btn-outline">Back</button>
      <button class="btn btn-outline">Generate More</button>
      <button class="btn btn-primary next-step-btn">Continue to the next step</button>
    </div>`;

    if ([4, 6, 7, 10].includes(step)) {
        return `<article class="stream-output prose-output">
          <h1>A practical article heading that remains balanced on a smaller screen</h1>
          <h2>A useful section heading with a clear visual hierarchy</h2>
          <p>Generated article copy should retain a comfortable type size and line height without becoming clipped, oversized or compressed into an unreadable column.</p>
        </article>${sharedActions}`;
    }

    if (step === 8) {
        return `<div class="qa-options">
          <div class="qa-bulk-toolbar"><label class="qa-bulk-control"><input type="checkbox" checked> Deselect all</label><span class="qa-bulk-count">3 of 3 included</span></div>
          <article class="qa-item-card included"><label class="qa-item-include"><input type="checkbox" checked> Include in article</label><input class="form-control qa-item-question" value="How should this question adapt on a smaller screen?"><textarea class="form-control qa-item-answer">The question and answer fields should remain full width, legible and easy to edit.</textarea></article>
        </div>${sharedActions}`;
    }

    if (step === 9) {
        return `<article class="seo-combined-input selected"><div class="seo-field"><label>Meta title</label><input class="form-control" value="Responsive SEO Meta Title That Remains Easy to Edit"><span class="meta-count in-range">50/60</span></div><div class="seo-field"><label>Meta description</label><textarea class="form-control">A useful meta description should remain readable without any text or controls being clipped.</textarea><span class="meta-count in-range">96/160</span></div></article>${sharedActions}`;
    }

    if (step === 11) {
        return `<div class="evaluation-summary"><div class="evaluation-summary-heading"><h3>Article quality summary</h3><p>Measured checks and editorial assessments stay clear at every supported width.</p></div><div class="evaluation-summary-statuses"><div class="evaluation-summary-status eval-pass"><strong>8</strong><span>Pass</span></div><div class="evaluation-summary-status eval-warn"><strong>2</strong><span>Check</span></div></div></div>${sharedActions}`;
    }

    return `<div class="form-group"><label>Editable field for this Wizard step</label><input class="form-control" value="Generated content remains easy to read and edit"></div><div class="options-grid"><article class="option-card selected"><span class="checkbox">✓</span><strong>A complete option card sentence that must not collapse into a word-wide strip</strong></article><article class="option-card"><span class="checkbox"></span><strong>Another useful choice with enough copy to exercise responsive wrapping</strong></article></div>${sharedActions}`;
};

const markup = (step) => `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>html,body{margin:0}${css}</style></head><body>
  <main class="app-container ai-scribe-app"><div class="app-main"><div class="two-column-layout"><section class="main-panel"><div class="workflow-container"><section class="step-content active" data-step-panel="${step}">
    <h2 class="step-heading">${stepNames[step]}</h2>
    <p class="step-subtitle">Review the generated content, make any changes and continue when you are happy.</p>
    <div class="results-section">${stepBody(step)}</div>
  </section></div></section><aside class="settings-panel"><div class="panel-header"><h2>Options &amp; Prompt</h2></div><div class="panel-content"><div class="settings-section"><h3 class="settings-section-title">Current prompt</h3><textarea class="form-control prompt-editor">The current prompt remains readable and editable on a smaller screen.</textarea><div class="prompt-run-actions"><button class="btn btn-outline prompt-run-button">Run amended prompt</button></div></div></div></aside></div></div></main>
</body></html>`;

const viewports = [375, 768, 1024, 1280, 1440];
const browser = await chromium.launch({ headless: true });

try {
    for (const width of viewports) {
        for (let step = 1; step <= 11; step += 1) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.setContent(markup(step));
            const label = `step ${step} at ${width}px`;
            const metrics = await page.evaluate(() => {
                const box = (node) => node?.getBoundingClientRect();
                const heading = document.querySelector('.step-heading');
                const subtitle = document.querySelector('.step-subtitle');
                const panel = document.querySelector('.main-panel');
                const buttons = [...document.querySelectorAll('.results-actions .btn, .prompt-run-button')];
                const optionCopy = [...document.querySelectorAll('.option-card strong')];
                const fields = [...document.querySelectorAll('.form-control')];
                const clippedText = [...document.querySelectorAll('.step-heading, .step-subtitle, .option-card strong, .qa-item-card label, .seo-field label, .evaluation-summary p')]
                    .filter((node) => node.scrollWidth > node.clientWidth + 1)
                    .map((node) => `${node.tagName}.${node.className}`);
                return {
                    documentWidth: document.documentElement.scrollWidth,
                    viewportWidth: document.documentElement.clientWidth,
                    panelWidth: box(panel).width,
                    headingSize: parseFloat(getComputedStyle(heading).fontSize),
                    headingLineHeight: parseFloat(getComputedStyle(heading).lineHeight),
                    subtitleSize: parseFloat(getComputedStyle(subtitle).fontSize),
                    subtitleLineHeight: parseFloat(getComputedStyle(subtitle).lineHeight),
                    buttonHeights: buttons.map((node) => box(node).height),
                    buttonsContained: buttons.every((node) => {
                        const owner = node.closest('.main-panel, .settings-panel');
                        return box(node).left >= box(owner).left - 1 && box(node).right <= box(owner).right + 1;
                    }),
                    optionWidths: optionCopy.map((node) => box(node).width),
                    fieldsContained: fields.every((node) => {
                        const owner = node.closest('.main-panel, .settings-panel');
                        const fieldBox = box(node);
                        const ownerBox = box(owner);
                        return fieldBox.left >= ownerBox.left - 1 && fieldBox.right <= ownerBox.right + 1;
                    }),
                    textareasWrap: fields
                        .filter((node) => node.tagName === 'TEXTAREA')
                        .every((node) => node.scrollWidth <= node.clientWidth + 1),
                    clippedText
                };
            });

            assert.ok(metrics.documentWidth <= metrics.viewportWidth + 1, `${label}: no page-level horizontal overflow`);
            assert.ok(metrics.panelWidth >= Math.min(300, width - 32), `${label}: main content remains readable`);
            assert.ok(metrics.headingLineHeight >= metrics.headingSize * 1.1, `${label}: heading keeps a readable line height`);
            assert.ok(metrics.subtitleSize >= 14, `${label}: supporting copy is not reduced below 14px`);
            assert.ok(metrics.subtitleLineHeight >= metrics.subtitleSize * 1.45, `${label}: supporting copy keeps a readable line height`);
            assert.ok(metrics.buttonHeights.every((height) => height >= 44), `${label}: every primary action retains a 44px touch target`);
            assert.equal(metrics.buttonsContained, true, `${label}: action labels and buttons stay inside their panel`);
            assert.ok(metrics.optionWidths.every((optionWidth) => optionWidth >= Math.min(180, metrics.panelWidth - 96)), `${label}: choice copy never becomes a word-wide column`);
            assert.equal(metrics.fieldsContained, true, `${label}: editable fields contain their text`);
            assert.equal(metrics.textareasWrap, true, `${label}: multiline fields wrap rather than clipping copy`);
            assert.deepEqual(metrics.clippedText, [], `${label}: important copy is not clipped`);

            if (width <= 480) {
                assert.ok(metrics.headingSize <= 28, `${label}: mobile heading scales down rather than dominating the screen`);
            }
            await page.close();
        }
    }
    console.log('Wizard typography: all 11 steps passed at 375/768/1024/1280/1440 (55 cases).');
} finally {
    await browser.close();
}
