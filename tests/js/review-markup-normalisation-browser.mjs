import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const baseSource = fs.readFileSync(new URL('../../assets/js/views/steps/BaseStepView.js', import.meta.url), 'utf8');
const streamingSource = fs.readFileSync(new URL('../../assets/js/views/steps/StreamingStepView.js', import.meta.url), 'utf8');
const bodySource = fs.readFileSync(new URL('../../assets/js/views/steps/BodyStepView.js', import.meta.url), 'utf8');
const reviewSource = fs.readFileSync(new URL('../../assets/js/views/steps/ReviewStepView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1100, height: 800 } });
    await page.setContent(`<!doctype html><html><body>
      <section data-step-panel="6"><div class="results-section"><div id="body-stream-output"></div><div id="body-quill-editor"></div><button data-testid="continue"></button></div><p data-testid="step-status"></p></section>
      <section data-step-panel="10"><div class="results-section"><div id="review-quill-editor"></div><button data-testid="continue"></button></div><p data-testid="step-status"></p><section data-testid="featured-image-preview" hidden><div data-featured-preview-media></div></section></section>
    </body></html>`);
    await page.addScriptTag({ content: `
      window.lucide = { createIcons() {} };
      window.ai_scribe = { checkArr: {} };
      class Quill {
        constructor(host) {
          this.root = document.createElement('div');
          this.root.className = 'ql-editor';
          host.append(this.root);
          this.clipboard = { convert: ({ html }) => ({ html }) };
        }
        on() {}
        setContents(delta) { this.root.innerHTML = delta.html || ''; }
        getSemanticHTML() { return this.root.innerHTML; }
      }
      window.Quill = Quill;
    ` });
    for (const source of [baseSource, streamingSource, bodySource, reviewSource]) {
        await page.addScriptTag({ content: source });
    }

    const result = await page.evaluate(() => {
        const malformedHeading = 'Ignoring structural technical debt will cost search visibility because crawlers become increasingly selective. This complete paragraph contains practical advice that belongs in normal article prose, not in the document outline or a huge bold heading.';
        const malformedBold = 'Run the audit with the engineering team, record each redirect chain, remove waste carefully, and verify every change before release. This second full paragraph must remain readable without turning every sentence into bold display copy.';
        const fragment = [
            '<h2>Actionable conclusion: fix your infrastructure today</h2>',
            `<h3>${malformedHeading}</h3>`,
            `<p><strong>${malformedBold}</strong></p>`,
            '<ul><li>Preserved list item with <a href="/audit">useful link</a></li></ul>',
            '<figure><img src="diagram.jpg" alt="Audit flow"><figcaption><strong>Concise caption</strong></figcaption></figure>'
        ].join('');
        const original = document.createElement('div');
        original.innerHTML = fragment;

        const state = {
            stepData: {
                1: { selection: 'Technical SEO guide' },
                3: { selection: [] },
                4: { contentHtml: '<p>Useful introduction.</p>' },
                6: { contentHtml: fragment },
                7: { contentHtml: `<h2>Conclusion</h2><p><strong>${malformedBold}</strong></p>` },
                8: { selection: [] }
            },
            galleryImages: []
        };
        const appState = {
            getStateSlice(key) { return state[key]; },
            setStateSlice(key, value) { state[key] = value; }
        };

        const body = new BodyStepView(appState);
        body.renderContent(fragment, {});
        const bodyHtml = body.getSelection();
        const bodyHost = document.createElement('div');
        bodyHost.innerHTML = bodyHtml;

        const review = new ReviewStepView(appState);
        const compiled = review.compileArticleHtml();
        review.quill = { root: { innerHTML: compiled } };
        const saved = review.getSelection();
        const savedHost = document.createElement('div');
        savedHost.innerHTML = saved;

        return {
            originalText: original.textContent.replace(/\s+/g, ' ').trim(),
            bodyText: bodyHost.textContent.replace(/\s+/g, ' ').trim(),
            savedText: savedHost.textContent.replace(/\s+/g, ' ').trim(),
            bodyLongHeadingCount: Array.from(bodyHost.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter((node) => node.textContent.includes('Ignoring structural')).length,
            bodyLongBoldCount: Array.from(bodyHost.querySelectorAll('strong,b')).filter((node) => node.textContent.includes('Run the audit')).length,
            legitimateHeading: bodyHost.querySelector('h2')?.textContent,
            savedLongHeadingCount: Array.from(savedHost.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter((node) => node.textContent.includes('Ignoring structural')).length,
            savedLongBoldCount: Array.from(savedHost.querySelectorAll('strong,b')).filter((node) => node.textContent.includes('Run the audit')).length,
            preservedLink: savedHost.querySelector('ul li a')?.getAttribute('href'),
            preservedImage: savedHost.querySelector('figure img')?.getAttribute('src'),
            preservedCaption: savedHost.querySelector('figcaption strong')?.textContent,
            savedHtml: saved
        };
    });

    assert.equal(result.bodyLongHeadingCount, 0, 'Body demotes paragraph-length provider headings');
    assert.equal(result.bodyLongBoldCount, 0, 'Body removes full-paragraph bold wrappers');
    assert.equal(result.legitimateHeading, 'Actionable conclusion: fix your infrastructure today', 'concise legitimate heading is preserved');
    assert.equal(result.savedLongHeadingCount, 0, 'Review/save output cannot restore the malformed heading');
    assert.equal(result.savedLongBoldCount, 0, 'Review/save output cannot restore full-paragraph bold');
    assert.ok(result.bodyText.includes(result.originalText), 'Body normalisation loses none of the provider words');
    assert.ok(result.savedText.includes(result.originalText), 'compiled Review/save output loses none of the fragment words');
    assert.equal(result.preservedLink, '/audit', 'links and lists survive normalisation');
    assert.equal(result.preservedImage, 'diagram.jpg', 'images survive normalisation');
    assert.equal(result.preservedCaption, 'Concise caption', 'legitimate caption emphasis survives normalisation');
    assert.doesNotMatch(result.savedHtml, /<h[1-6][^>]*>Ignoring structural/i);
    assert.doesNotMatch(result.savedHtml, /<strong>Run the audit/i);

    console.log('Review markup normalisation: Body, compile and save preserve content while repairing paragraph headings and full bold wrappers');
} finally {
    await browser.close();
}
