import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8'),
    fs.readFileSync('assets/css/admin-pages.css', 'utf8')
].join('\n');

const stepNames = [
    'Title', 'Keywords', 'Outline', 'Intro', 'Tagline', 'Body',
    'Conclusion', 'Q&A', 'SEO', 'Review', 'Evaluate'
];

const nav = `<nav class="step-navigation" id="step-navigation" aria-label="Wizard steps">
  <div class="steps-container" role="tablist" aria-orientation="horizontal">
    ${stepNames.map((label, index) => `<button type="button" class="step ${index === 10 ? 'active' : ''}" role="tab" data-step="${index + 1}" tabindex="${index === 10 ? '0' : '-1'}">
      <span class="step-icon" aria-hidden="true">${index + 1}</span><span class="step-label">${label}</span><span class="step-number" aria-hidden="true">${index + 1}</span>
    </button>`).join('')}
  </div>
</nav>`;

const summary = `<section class="evaluation-summary" aria-label="Evaluation summary">
  <div class="evaluation-summary-heading"><h3>Evaluation summary</h3><p>Structural checks are measured from the final Review HTML. Editorial rows are clearly labelled AI review and should be confirmed by an editor.</p></div>
  <ul class="evaluation-summary-statuses">
    <li class="evaluation-summary-status eval-pass"><strong>9</strong><span>Passed</span></li>
    <li class="evaluation-summary-status eval-warn"><strong>4</strong><span>Needs a check</span></li>
    <li class="evaluation-summary-status eval-fail"><strong>0</strong><span>Failed</span></li>
    <li class="evaluation-summary-status eval-unknown"><strong>0</strong><span>Needs review</span></li>
  </ul>
</section>`;

const markup = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0}${css}</style></head><body>
  <div id="ai-scribe-root" class="app-container ai-scribe-app"><main class="app-main">${nav}
    <div class="two-column-layout"><section class="main-panel"><div class="workflow-container"><section class="step-content active"><h2 class="step-heading">Article Quality Analysis</h2>${summary}</section></div></section>
      <aside class="settings-panel"><div class="panel-header"><h2>Options &amp; Prompt</h2></div><div class="panel-content"><textarea class="form-control">Current evaluation prompt</textarea></div></aside>
    </div>
  </main></div>
  <script>window.ai_scribe={};window.module={exports:{}};</script>
</body></html>`;

const viewports = [375, 768, 1024, 1280, 1440, 1512];
const browser = await chromium.launch({ headless: true });

try {
    for (const width of viewports) {
        for (const dark of [false, true]) {
            const page = await browser.newPage({ viewport: { width, height: 900 }, colorScheme: dark ? 'dark' : 'light', reducedMotion: 'reduce' });
            await page.setContent(markup);
            if (dark) {
                await page.evaluate(() => document.documentElement.setAttribute('data-ai-scribe-theme', 'dark'));
            }
            await page.addScriptTag({ path: 'assets/js/main.js' });
            await page.evaluate(() => {
                const App = window.module.exports.AIScribeApp;
                App.prototype.updateStepNavigation.call({
                    state: {
                        getStateSlice(key) {
                            if (key === 'currentStep') return 11;
                            if (key === 'stepData') return new Map(Array.from({ length: 10 }, (_, index) => [index + 1, {}]));
                            return null;
                        }
                    }
                });
            });
            await page.waitForTimeout(50);

            const label = `${width}px ${dark ? 'dark' : 'light'}`;
            const metrics = await page.evaluate(() => {
                const rail = document.querySelector('.steps-container');
                const active = document.querySelector('.step.active');
                const railBox = rail.getBoundingClientRect();
                const activeBox = active.getBoundingClientRect();
                const statuses = [...document.querySelectorAll('.evaluation-summary-status')];
                const tops = [...new Set(statuses.map((item) => Math.round(item.getBoundingClientRect().top)))];
                return {
                    documentWidth: document.documentElement.scrollWidth,
                    viewportWidth: document.documentElement.clientWidth,
                    railOverflow: rail.scrollWidth > rail.clientWidth + 1,
                    railWidth: rail.clientWidth,
                    railContentWidth: rail.scrollWidth,
                    railScroll: rail.scrollLeft,
                    activeOffset: active.offsetLeft,
                    activeWidth: active.offsetWidth,
                    overflowX: getComputedStyle(rail).overflowX,
                    activeVisible: activeBox.left >= railBox.left - 1 && activeBox.right <= railBox.right + 1,
                    activeLabel: active.querySelector('.step-label').textContent.trim(),
                    activeTabIndex: active.tabIndex,
                    rows: tops.length,
                    cardContained: statuses.every((item) => item.scrollWidth <= item.clientWidth + 1),
                    cardWidths: statuses.map((item) => item.getBoundingClientRect().width),
                    colours: statuses.map((item) => getComputedStyle(item).color)
                };
            });

            assert.ok(metrics.documentWidth <= metrics.viewportWidth + 1, `${label}: no document overflow`);
            assert.equal(metrics.overflowX, 'auto', `${label}: overflow remains local to the step rail`);
            assert.equal(metrics.activeVisible, true, `${label}: Evaluate is fully visible ${JSON.stringify(metrics)}`);
            assert.equal(metrics.activeLabel, 'Evaluate', `${label}: the current label is not clipped or replaced`);
            assert.equal(metrics.activeTabIndex, 0, `${label}: current step remains keyboard focusable`);
            assert.equal(metrics.cardContained, true, `${label}: summary card labels are not clipped`);
            assert.ok(metrics.cardWidths.every((cardWidth) => cardWidth >= 120), `${label}: status cards retain a readable measure`);
            assert.ok(metrics.colours.every((colour) => colour !== 'rgba(0, 0, 0, 0)'), `${label}: summary copy remains visible`);

            if (width <= 420) {
                assert.equal(metrics.rows, 4, `${label}: phone summary uses one readable card per row`);
            } else if (width < 1024) {
                assert.equal(metrics.rows, 2, `${label}: constrained summary uses a balanced 2x2 grid`);
            } else {
                assert.ok([1, 2].includes(metrics.rows), `${label}: desktop summary uses either four-up or a balanced 2x2 grid`);
            }

            if (metrics.railOverflow) {
                assert.ok(metrics.railScroll > 0, `${label}: active-step update scrolls the local rail`);
            }

            await page.locator('.step.active').focus();
            assert.equal(await page.evaluate(() => document.activeElement?.textContent.includes('Evaluate')), true, `${label}: current step accepts keyboard focus`);
            await page.close();
        }
    }
    console.log('Navigation/Evaluate responsive: 6 widths in light and dark passed; local rail, active-step visibility and summary reflow verified.');
} finally {
    await browser.close();
}
