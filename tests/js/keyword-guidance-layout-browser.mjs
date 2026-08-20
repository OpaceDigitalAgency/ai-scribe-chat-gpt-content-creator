import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = [
    fs.readFileSync('assets/css/main.css', 'utf8'),
    fs.readFileSync('assets/css/components.css', 'utf8'),
    fs.readFileSync('assets/css/admin-pages.css', 'utf8')
].join('\n');

const browser = await chromium.launch({ headless: true });
try {
    for (const viewport of [{ width: 1440, height: 900 }, { width: 768, height: 900 }, { width: 375, height: 812 }]) {
        const page = await browser.newPage({ viewport });
        await page.setContent(`<!doctype html><style>${css}</style><main class="ai-scribe-app">
          <div class="workspace-grid" style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:24px">
            <section class="step-panel active" data-step-panel="2">
              <div class="choice-guidance">
                <p>Choose one or more keyword suggestions. Demand bands are qualitative AI estimates, not measured search-volume data. Use Google Trends to check relative interest and seasonality.</p>
                <div class="choice-guidance-actions">
                  <span class="choice-selection-status">1 of 5 selected</span>
                  <button class="btn btn-link">Select all</button>
                  <button class="btn btn-link">Deselect all</button>
                  <a class="btn btn-link keyword-compare-link">Compare selected in Google Trends ↗</a>
                </div>
              </div>
            </section><aside></aside>
          </div>
        </main>`);
        if (viewport.width <= 768) {
            await page.locator('.workspace-grid').evaluate((node) => { node.style.gridTemplateColumns = 'minmax(0,1fr)'; });
        }
        const metrics = await page.evaluate(() => {
            const guidance = document.querySelector('.choice-guidance');
            const copy = guidance.querySelector(':scope > p');
            const actions = guidance.querySelector('.choice-guidance-actions');
            const gr = guidance.getBoundingClientRect();
            const cr = copy.getBoundingClientRect();
            const ar = actions.getBoundingClientRect();
            return {
                guidanceWidth: gr.width,
                copyWidth: cr.width,
                actionsWidth: ar.width,
                actionsBelow: ar.top >= cr.bottom - 1,
                noOverflow: document.documentElement.scrollWidth <= document.documentElement.clientWidth
            };
        });
        assert.ok(metrics.copyWidth >= metrics.guidanceWidth - 40, `${viewport.width}px helper copy keeps the available width`);
        assert.ok(metrics.actionsWidth >= metrics.guidanceWidth - 40, `${viewport.width}px actions use their own row`);
        assert.equal(metrics.actionsBelow, true, `${viewport.width}px actions sit below the guidance copy`);
        assert.equal(metrics.noOverflow, true, `${viewport.width}px has no horizontal overflow`);
        await page.close();
    }
    console.log('Keyword guidance layout: readable copy and wrapped actions passed at 375/768/1440');
} finally {
    await browser.close();
}
