import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const viewSource = fs.readFileSync(
    new URL('../../assets/js/views/steps/ReviewStepView.js', import.meta.url),
    'utf8'
);
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1000, height: 700 } });
    await page.setContent(`<!doctype html><html><body>
        <section data-step-panel="10"><div id="review-quill-editor"></div></section>
    </body></html>`);
    await page.addScriptTag({ content: `
        class BaseStepView {
            constructor() { this.panel = document.querySelector('[data-step-panel="10"]'); }
        }
        window.BaseStepView = BaseStepView;
    ` });
    await page.addScriptTag({ content: viewSource });

    const result = await page.evaluate(() => {
        const visibleHtml = [
            '<h2>Table of Contents</h2>',
            '<ul><li><a href="#heading-0" target="_blank" rel="noopener noreferrer">Useful section</a></li></ul>',
            '<h2>Useful section</h2>',
            '<p><img src="https://example.test/generated-image.png" alt="Useful diagram" class="wp-image-42"></p>'
        ].join('');
        let semanticCalls = 0;
        const view = new ReviewStepView({ getStateSlice: () => ({}) });
        view.quill = {
            root: { innerHTML: visibleHtml },
            getSemanticHTML() {
                semanticCalls += 1;
                return '<h2>Table of Contents</h2><p>Semantic output omitted the image.</p>';
            }
        };
        const html = view.getSelection();
        const host = document.createElement('div');
        host.innerHTML = html;
        const link = host.querySelector('a[href="#heading-0"]');
        const image = host.querySelector('img');
        const section = Array.from(host.querySelectorAll('h2'))
            .find((heading) => heading.textContent === 'Useful section');
        return {
            html,
            semanticCalls,
            image: image && {
                src: image.getAttribute('src'),
                alt: image.getAttribute('alt'),
                className: image.className
            },
            link: link && {
                target: link.getAttribute('target'),
                rel: link.getAttribute('rel'),
                href: link.getAttribute('href')
            },
            sectionId: section && section.id
        };
    });

    assert.equal(result.semanticCalls, 0, 'getSelection must not use Quill semantic HTML when visible DOM exists');
    assert.deepEqual(result.image, {
        src: 'https://example.test/generated-image.png',
        alt: 'Useful diagram',
        className: 'wp-image-42'
    }, 'the visible image embed and its useful attributes survive selection');
    assert.ok(!result.html.includes('Semantic output omitted the image'));
    assert.deepEqual(result.link, { target: null, rel: null, href: '#heading-0' },
        'in-page links remain same-tab after selecting root HTML');
    assert.equal(result.sectionId, 'heading-0', 'normaliseInPageLinks restores the target heading id');

    console.log('Review selection browser: visible image preserved when semantic HTML omits it; in-page links normalised');
} finally {
    await browser.close();
}
