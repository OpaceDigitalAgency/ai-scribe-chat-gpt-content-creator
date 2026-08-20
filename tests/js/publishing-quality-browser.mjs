import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const source = fs.readFileSync(new URL('../../assets/js/views/steps/ReviewStepView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 800 } });
    await page.setContent(`<!doctype html><html><body>
        <section data-step-panel="10">
            <section data-testid="featured-image-preview" hidden>
                <div data-featured-preview-media></div>
            </section>
            <div id="review-quill-editor"></div>
        </section>
    </body></html>`);
    await page.addScriptTag({ content: `class BaseStepView { constructor(step, appState) { this.panel = document.querySelector('[data-step-panel="10"]'); this.appState = appState; } } window.BaseStepView = BaseStepView;` });
    await page.addScriptTag({ content: source });

    const result = await page.evaluate(() => {
        const state = {
            galleryImages: [
                { url: 'featured.jpg', alt_text: 'A clear website layout', width: 1200, height: 800 },
                { url: 'detail.jpg', alt_text: 'Navigation controls', width: 900, height: 600 }
            ]
        };
        const view = new ReviewStepView({
            getStateSlice: (key) => state[key],
            setStateSlice: (key, value) => { state[key] = value; }
        });
        view.renderFeaturedPreview();
        view.quill = { root: { innerHTML: '<p>Intro</p><figure><img src="featured.jpg" alt="A clear website layout"><figcaption>Featured caption</figcaption></figure><p><img src="detail.jpg" alt="Navigation controls"></p><p>Keep this advice.</p>' } };
        const output = view.getSelection();
        const host = document.createElement('div');
        host.innerHTML = output;
        const preview = document.querySelector('[data-testid="featured-image-preview"]');
        const previewImage = preview.querySelector('img');
        const contentImage = host.querySelector('img');
        return {
            previewHidden: preview.hidden,
            preview: previewImage && {
                src: previewImage.getAttribute('src'),
                loading: previewImage.getAttribute('loading'),
                priority: previewImage.getAttribute('fetchpriority'),
                width: previewImage.getAttribute('width'),
                height: previewImage.getAttribute('height')
            },
            containsFeatured: output.includes('featured.jpg'),
            containsFeaturedCaption: output.includes('Featured caption'),
            preservesAdvice: output.includes('Keep this advice.'),
            content: contentImage && {
                src: contentImage.getAttribute('src'),
                loading: contentImage.getAttribute('loading'),
                decoding: contentImage.getAttribute('decoding'),
                width: contentImage.getAttribute('width'),
                height: contentImage.getAttribute('height')
            }
        };
    });

    assert.equal(result.previewHidden, false, 'featured preview is visible');
    assert.deepEqual(result.preview, { src: 'featured.jpg', loading: 'eager', priority: 'high', width: '1200', height: '800' });
    assert.equal(result.containsFeatured, false, 'featured image is absent from saved article HTML');
    assert.equal(result.containsFeaturedCaption, false, 'the featured figure caption is not orphaned in saved HTML');
    assert.equal(result.preservesAdvice, true, 'non-featured content beside the removed figure is preserved');
    assert.deepEqual(result.content, { src: 'detail.jpg', loading: 'lazy', decoding: 'async', width: '900', height: '600' });
    console.log('Publishing quality browser: separate featured preview, duplicate prevention and intrinsic image loading attributes passed');
} finally {
    await browser.close();
}
