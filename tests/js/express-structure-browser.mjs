import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const css = fs.readFileSync(new URL('../../assets/css/components.css', import.meta.url), 'utf8');
const baseSource = fs.readFileSync(new URL('../../assets/js/views/steps/BaseStepView.js', import.meta.url), 'utf8');
const expressSource = fs.readFileSync(new URL('../../assets/js/views/steps/ExpressView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.setContent(`<!doctype html><html><head><style>${css}</style></head><body>
      <main class="ai-scribe-app">
        <section data-step-panel="express" data-state="idle">
          <input data-testid="express-topic">
          <p data-testid="step-status"></p>
          <div class="results-section hidden">
            <article class="prose-output express-article" id="express-stream-output"></article>
          </div>
        </section>
      </main>
    </body></html>`);
    await page.addScriptTag({ content: 'window.lucide={createIcons(){}};window.ai_scribe={i18n:{}};' });
    await page.addScriptTag({ content: baseSource });
    await page.addScriptTag({ content: expressSource });
    await page.evaluate(() => {
        const state = {
            stepData: { 2: { selection: ['stale keyword'] } },
            galleryImages: [{ url: 'stale.jpg' }],
            reviewEditedHtml: '<p>Stale review</p>',
            persistence: { post: { id: 99 }, shortcode: null }
        };
        const appState = {
            getStateSlice(key) { return state[key]; },
            setStateSlice(key, value) { state[key] = value; }
        };
        window.expressState = state;
        window.expressView = new ExpressView(appState);
        window.expressView.renderArticle({
            conversation_id: 44,
            article: {
                title: 'Authoritative Article Title',
                meta: { title: 'SEO Meta Title | Site', description: 'Description' },
                tagline: 'A useful standfirst.',
                outline: ['First Section', 'Second Section'],
                intro: '<p>Opening paragraph.</p>',
                body_html: '<div><h1>SEO Meta Title | Site</h1><h2>First Section</h2><p>Nested paragraph.</p></div><h1>Authoritative Article Title</h1><h2>Second Section</h2><p>Direct paragraph.</p>',
                conclusion: '<h1>Wrong closing title</h1><span><h2>Conclusion</h2>Bare closing text with <strong>preserved emphasis</strong>.</span>',
                qna: [{ question: '<strong>Useful question?</strong>', answer: '<p>Useful answer.</p>' }]
            }
        });
    });

    const article = page.locator('#express-stream-output');
    assert.equal(await article.locator(':scope > :first-child').evaluate((el) => el.tagName), 'H1');
    assert.equal(await article.locator(':scope > h1').textContent(), 'Authoritative Article Title');
    assert.equal(await article.locator('h1').count(), 1, 'Express has exactly one H1');
    assert.equal((await article.innerText()).includes('SEO Meta Title | Site'), false, 'meta title is not injected mid-article');
    assert.equal((await article.innerText()).includes('Wrong closing title'), false, 'fragment H1 is removed');
    assert.equal(await article.locator(':scope > span').count(), 0,
        'an invalid inline wrapper around a heading and prose is removed');
    assert.equal(await article.locator(':scope > h2').filter({ hasText: 'Conclusion' }).count(), 1,
        'the semantic conclusion heading is preserved');
    assert.equal(await article.locator(':scope > p').filter({ hasText: 'Bare closing text' }).count(), 1,
        'bare provider text is converted to a semantic paragraph');
    assert.equal(await article.locator(':scope > p strong').filter({ hasText: 'preserved emphasis' }).count(), 1,
        'inline emphasis inside recovered prose is preserved');
    assert.equal(await page.evaluate(() => /<h1/i.test(window.expressState.stepData[6].contentHtml)), false,
        'Refine handoff receives the same H1-free body shown in Express');
    assert.equal(await page.evaluate(() => Object.prototype.hasOwnProperty.call(window.expressState.stepData, 2)), false,
        'old Wizard keywords do not leak into a new Express article');
    assert.deepEqual(await page.evaluate(() => window.expressState.galleryImages), [], 'old article images are cleared');
    assert.equal(await page.evaluate(() => window.expressState.reviewEditedHtml), '', 'old Review edits are cleared');
    assert.deepEqual(await page.evaluate(() => window.expressState.persistence), { post: null, shortcode: null }, 'old save destinations are cleared');

    const widths = await article.locator(':scope > :is(h1, h2, h3, p)').evaluateAll((nodes) => nodes.map((node) => Math.round(node.getBoundingClientRect().width)));
    assert.ok(widths.length >= 4);
    assert.ok(Math.max(...widths) - Math.min(...widths) <= 1,
        `headings, recovered prose, direct prose and Q&A share one measure (${widths.join(', ')})`);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true);

    for (const viewport of [
        { width: 375, height: 812, colour: 'light' },
        { width: 768, height: 1024, colour: 'light' },
        { width: 1440, height: 1000, colour: 'light' },
        { width: 1440, height: 1000, colour: 'dark' }
    ]) {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.emulateMedia({ colorScheme: viewport.colour });
        await page.evaluate((colour) => {
            document.documentElement.dataset.aiScribeTheme = colour;
        }, viewport.colour);
        const geometry = await article.locator(':scope > :is(h1, h2, h3, p)').evaluateAll((nodes) => nodes.map((node) => {
            const rect = node.getBoundingClientRect();
            return { left: Math.round(rect.left), right: Math.round(rect.right), width: Math.round(rect.width) };
        }));
        assert.ok(geometry.length > 0);
        assert.ok(Math.max(...geometry.map((item) => item.width)) - Math.min(...geometry.map((item) => item.width)) <= 1,
            `${viewport.width}px/${viewport.colour}: prose blocks share one readable measure`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), true,
            `${viewport.width}px/${viewport.colour}: no horizontal overflow`);
    }

    console.log('Express structure browser: semantic fragment recovery, authoritative title, clean Refine handoff and responsive reading measure passed');
} finally {
    await browser.close();
}
