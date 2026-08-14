/**
 * Step 10 — Review. Compiles the article from stored TYPED step data into
 * the review Quill editor (editor-with-gallery). No string-concat of DOM
 * reads (the 2.6.2 `titleVal + keywordVal + …` bug class is gone): the
 * source of truth is AppState stepData.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global BaseStepView, Quill */
/* exported ReviewStepView */

class ReviewStepView extends BaseStepView {
    constructor(appState) {
        super(10, appState);
        this.editorHost = this.panel ? this.panel.querySelector('#review-quill-editor') : null;
        this.quill = null;
    }

    ensureEditor() {
        if (this.quill || !this.editorHost || typeof Quill === 'undefined') {
            return this.quill;
        }
        this.quill = new Quill(this.editorHost, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'link'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'image', 'clean']
                ]
            }
        });
        this.quill.on('text-change', (delta, oldDelta, source) => {
            if (source !== 'silent' && this.appState) {
                this.appState.setStateSlice('reviewEditedHtml', this.quill.root.innerHTML);
            }
        });
        return this.quill;
    }

    renderImprovedHtml(html) {
        const quill = this.ensureEditor();
        if (!quill || typeof html !== 'string' || !html.trim()) return false;
        const delta = quill.clipboard.convert({ html });
        quill.setContents(delta, 'silent');
        if (this.appState) this.appState.setStateSlice('reviewEditedHtml', quill.root.innerHTML);
        return true;
    }

    /**
     * Build the full article HTML from typed state. All free-text segments
     * are escaped; long-form HTML segments arrive server-sanitised.
     */
    compileArticleHtml() {
        const stepData = this.appState ? (this.appState.getStateSlice('stepData') || {}) : {};
        const esc = (text) => {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        };
        /*
         * Q&A questions and answers are plain-text fields, but models
         * frequently return them already wrapped in <h2>/<p>. Escaping that
         * verbatim printed the tags on screen as literal text. Unwrap to the
         * text content first, then escape, so a tidy model and an untidy one
         * both render as prose. Only these two short fields are treated this
         * way — the article body stays untouched HTML.
         */
        const plain = (text) => {
            const div = document.createElement('div');
            div.innerHTML = text == null ? '' : String(text);
            return (div.textContent || '').replace(/\s+/g, ' ').trim();
        };
        const title = stepData[1] && stepData[1].selection;
        const tagline = stepData[5] && stepData[5].selection;
        const intro = ReviewStepView.removeStrayH1(ReviewStepView.normaliseArticleMarkup(stepData[4] && stepData[4].contentHtml));
        // editedHtml is what step 6's Quill editor actually holds — the typed
        // edits and the inserted images. BodyStepView writes it on every
        // change; contentHtml is only the generated draft it started from.
        // The model routinely opens the body with an H1 of its own invention,
        // which then outranked the user's chosen title in the compiled
        // article, the saved post and the shortcode. The selected title is
        // the only H1; anything the segments carry is dropped or demoted.
        let body = ReviewStepView.removeStrayH1(ReviewStepView.normaliseArticleMarkup(stepData[6] && (stepData[6].editedHtml || stepData[6].contentHtml)));
        const conclusion = ReviewStepView.removeStrayH1(ReviewStepView.normaliseArticleMarkup(stepData[7] && stepData[7].contentHtml));
        const qna = (stepData[8] && stepData[8].selection) || [];

        // Placement radios (2.6.2 above_below_tagline / above_below_conclusion).
        const radioValue = (name, fallback) => {
            const checked = document.querySelector(`input[name="${name}"]:checked`);
            return checked ? checked.value : fallback;
        };
        const taglinePosition = radioValue('above_below_tagline', 'below');
        const qnaPosition = radioValue('above_below_conclusion', 'below');

        // Insert TOC option (check_Arr.addinsertToc, 2.6.2 parity): anchor
        // every body heading and build a nested Table of Contents.
        let tocHtml = '';
        const checkArr = (window.ai_scribe && window.ai_scribe.checkArr) || {};
        if (body && checkArr.addinsertToc) {
            const built = ReviewStepView.buildToc(body);
            body = built.body;
            tocHtml = built.toc;
        }

        const taglineHtml = tagline ? `<p><strong><em>${esc(tagline)}</em></strong></p>` : '';
        const parts = [];
        if (title) {
            parts.push(`<h1>${esc(title)}</h1>`);
        }
        if (taglineHtml && taglinePosition === 'above') {
            parts.push(taglineHtml);
        }
        if (intro) {
            parts.push(intro);
        }
        if (taglineHtml && taglinePosition === 'below') {
            parts.push(taglineHtml);
        }
        // The first gallery image is previewed separately in Review and saved
        // as the WordPress featured image. It must not also live in post HTML,
        // where most themes would render it a second time.
        const featured = this.featuredImage();
        if (featured) {
            body = ReviewStepView.removeImageByUrl(body, featured.url);
        }
        if (tocHtml) {
            parts.push(tocHtml);
        }
        const qnaParts = [];
        if (Array.isArray(qna) && qna.length > 0) {
            qnaParts.push(`<h2>${esc(this.t('qnaHeading') === 'qnaHeading' ? 'Questions & Answers' : this.t('qnaHeading'))}</h2>`);
            qna.forEach((item) => {
                qnaParts.push(`<h3>${esc(plain(item.question))}</h3><p>${esc(plain(item.answer))}</p>`);
            });
        }
        if (body) {
            parts.push(body);
        }
        if (qnaPosition === 'above') {
            parts.push(...qnaParts);
        }
        if (conclusion) {
            parts.push(conclusion);
        }
        if (qnaPosition !== 'above') {
            parts.push(...qnaParts);
        }
        return parts.join('\n');
    }

    /**
     * The featured image's article block, or '' when it should not be added:
     * no image yet, the user deleted the auto-placed copy, or the image is
     * already somewhere in the article's segments.
     *
     * @param {Array<string|undefined>} segments Segment HTML to scan.
     * @returns {string}
     */
    featuredImage() {
        if (!this.appState) {
            return null;
        }
        const gallery = this.appState.getStateSlice('galleryImages');
        const featured = Array.isArray(gallery) ? gallery[0] : null;
        if (!featured || !featured.url) {
            return null;
        }
        if (this.appState.getStateSlice('featuredImageRemoved') === true) {
            return null;
        }
        return featured;
    }

    renderFeaturedPreview() {
        const preview = this.panel && this.panel.querySelector('[data-testid="featured-image-preview"]');
        if (!preview) return;
        const featured = this.featuredImage();
        preview.hidden = !featured;
        const media = preview.querySelector('[data-featured-preview-media]');
        if (!featured || !media) {
            if (media) media.replaceChildren();
            return;
        }
        const img = document.createElement('img');
        img.src = featured.url;
        img.alt = featured.alt_text || '';
        img.loading = 'eager';
        img.fetchPriority = 'high';
        if (featured.width) img.width = parseInt(featured.width, 10);
        if (featured.height) img.height = parseInt(featured.height, 10);
        media.replaceChildren(img);
    }

    static removeImageByUrl(html, url) {
        if (typeof html !== 'string' || !html || !url) return html;
        const host = document.createElement('div');
        host.innerHTML = html;
        host.querySelectorAll('img').forEach((img) => {
            if (img.getAttribute('src') !== url) return;
            const figure = img.closest('figure');
            if (figure && figure.querySelectorAll('img').length === 1) {
                figure.remove();
                return;
            }
            const container = img.closest('figure, p, div');
            const caption = container && container.nextElementSibling
                && container.nextElementSibling.matches('.ai-scribe-image-caption, [data-image-caption]')
                ? container.nextElementSibling
                : null;
            if (container && container.querySelectorAll('img').length === 1
                && !(container.textContent || '').trim()) {
                container.remove();
            } else {
                img.remove();
            }
            if (caption) caption.remove();
        });
        return host.innerHTML;
    }

    normaliseOutputImages(html) {
        if (typeof html !== 'string' || !html) return html;
        const host = document.createElement('div');
        host.innerHTML = html;
        const gallery = this.appState ? (this.appState.getStateSlice('galleryImages') || []) : [];
        const featured = this.featuredImage();
        if (featured) {
            host.innerHTML = ReviewStepView.removeImageByUrl(host.innerHTML, featured.url);
        }
        host.querySelectorAll('img').forEach((img) => {
            const source = gallery.find((item) => item && item.url === img.getAttribute('src'));
            img.loading = 'lazy';
            img.decoding = 'async';
            if (source && source.width) img.width = parseInt(source.width, 10);
            if (source && source.height) img.height = parseInt(source.height, 10);
        });
        return host.innerHTML;
    }

    /**
     * A segment's own H1 has no place in the article: the compiled article
     * gets exactly one H1, the user's selected title. A leading H1 is the
     * model titling its own output and is removed outright; any later H1 is
     * a section heading wearing the wrong tag and is demoted to H2.
     *
     * @param {string|undefined} html Segment HTML.
     * @returns {string|undefined} The segment without any H1.
     */
    static removeStrayH1(html) {
        if (typeof html !== 'string' || html.indexOf('<h1') === -1) {
            return html;
        }
        const host = document.createElement('div');
        host.innerHTML = html;
        const headings = Array.from(host.querySelectorAll('h1'));
        headings.forEach((heading, index) => {
            const isLeading = index === 0
                && heading === host.firstElementChild
                && (host.textContent || '').trim().indexOf((heading.textContent || '').trim()) === 0;
            if (isLeading) {
                heading.remove();
                return;
            }
            const demoted = document.createElement('h2');
            demoted.innerHTML = heading.innerHTML;
            heading.replaceWith(demoted);
        });
        return host.innerHTML;
    }

    /** Keep Review safe when older persisted fragments predate normalisation. */
    static normaliseArticleMarkup(html) {
        return typeof BaseStepView.normaliseArticleMarkup === 'function'
            ? BaseStepView.normaliseArticleMarkup(html)
            : html;
    }

    /**
     * Build a nested Table of Contents from the body's H2–H5 headings,
     * assigning `heading-N` anchor ids (port of 2.6.2 generateTOC, but
     * applied at compile time on trusted, server-sanitised HTML).
     *
     * @param {string} bodyHtml
     * @returns {{body: string, toc: string}}
     */
    static buildToc(bodyHtml) {
        const host = document.createElement('div');
        host.innerHTML = bodyHtml;
        const headings = host.querySelectorAll('h2, h3, h4, h5');
        if (headings.length === 0) {
            return { body: bodyHtml, toc: '' };
        }
        let tocHtml = '<ul class="toc">';
        headings.forEach((heading, index) => {
            heading.id = `heading-${index}`;
            const link = document.createElement('a');
            link.setAttribute('href', `#heading-${index}`);
            link.textContent = heading.textContent;
            tocHtml += `<li>${link.outerHTML}</li>`;
        });
        tocHtml += '</ul>';
        return {
            body: host.innerHTML,
            toc: `<h2>Table of Contents</h2>${tocHtml}`
        };
    }

    /**
     * Put the Table of Contents back together on the way out.
     *
     * buildToc() anchors the headings at compile time, but the compiled HTML
     * then goes through Quill, which drops `id` attributes and stamps every
     * link with `target="_blank" rel="noopener noreferrer"`. What reached the
     * post, the page and the shortcode was therefore a contents list of
     * `#heading-N` links pointing at nothing, each marked to open in a new
     * tab (S11-02). Both halves are repaired here, on the HTML that is
     * actually sent, so all three output hosts are covered by one pass.
     *
     * @param {string} html Article HTML from the editor (or the compiler).
     * @returns {string} The same HTML with resolvable in-page anchors.
     */
    static normaliseInPageLinks(html) {
        if (typeof html !== 'string' || html === '') {
            return html;
        }
        const host = document.createElement('div');
        host.innerHTML = html;
        const anchors = Array.from(host.querySelectorAll('a[href^="#"]'))
            .filter((anchor) => (anchor.getAttribute('href') || '').length > 1);
        if (anchors.length === 0) {
            return html;
        }

        // A jump to another part of the same document is not a new-tab link.
        anchors.forEach((anchor) => {
            anchor.removeAttribute('target');
            anchor.removeAttribute('rel');
        });

        // Only headings after the contents list are candidates, so the
        // "Table of Contents" heading itself can never be claimed.
        const last = anchors[anchors.length - 1];
        const available = Array.from(host.querySelectorAll('h1, h2, h3, h4, h5, h6'))
            .filter((heading) => (
                last.compareDocumentPosition(heading) & Node.DOCUMENT_POSITION_FOLLOWING
            ) !== 0);

        const claim = (heading, id) => {
            heading.id = id;
            available.splice(available.indexOf(heading), 1);
        };

        // Text first: each entry was built from the heading it points at, so
        // matching on text survives the user reordering sections in Review.
        const unmatched = [];
        anchors.forEach((anchor) => {
            const wanted = anchor.textContent.trim();
            const match = available.find((heading) => heading.textContent.trim() === wanted);
            if (match) {
                claim(match, anchor.getAttribute('href').slice(1));
            } else {
                unmatched.push(anchor);
            }
        });
        // Anything the user has since renamed falls back to document order,
        // so no entry in the contents list is left pointing at nothing.
        unmatched.forEach((anchor) => {
            if (available.length > 0) {
                claim(available[0], anchor.getAttribute('href').slice(1));
            }
        });

        return host.innerHTML;
    }

    /** Called on entering step 10. */
    renderTyped() {
        const restored = this.appState && this.appState.getStateSlice('reviewEditedHtml');
        const html = ReviewStepView.normaliseArticleMarkup(
            typeof restored === 'string' && restored.trim() ? restored : this.compileArticleHtml()
        );
        if (!html) {
            this.showEmpty();
            return;
        }
        const quill = this.ensureEditor();
        if (quill) {
            const delta = quill.clipboard.convert({ html });
            quill.setContents(delta, 'silent');
        }
        this.renderFeaturedPreview();
        this.showReady(this.t('articleReady'));
        this.setNextEnabled(true);
    }

    getSelection() {
        // Quill's getSemanticHTML() can omit image embeds in this editor.
        // The visible editor DOM is the exact reviewed article and is
        // sanitised again server-side, so preserve it byte-for-byte here.
        const html = this.quill ? this.quill.root.innerHTML : this.compileArticleHtml();
        const normalised = ReviewStepView.normaliseArticleMarkup(html);
        return ReviewStepView.normaliseInPageLinks(this.normaliseOutputImages(normalised));
    }

    /** Typed article payload for save-draft / publish. */
    getArticlePayload() {
        const stepData = this.appState ? (this.appState.getStateSlice('stepData') || {}) : {};
        return {
            title: (stepData[1] && stepData[1].selection) || '',
            content_html: this.getSelection(),
            meta: (stepData[9] && stepData[9].selection) || {},
            qna: (stepData[8] && stepData[8].selection) || []
        };
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReviewStepView;
}
