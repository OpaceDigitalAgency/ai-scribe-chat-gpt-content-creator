/**
 * Express mode — topic in, full article out (one call, contract §5), then a
 * "Refine in wizard" handoff. `ai_scribe_run_express` is a plain JSON POST;
 * the view shows a skeleton while it runs and renders the typed article
 * object on completion.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global BaseStepView */
/* exported ExpressView */

class ExpressView extends BaseStepView {
    constructor(appState) {
        // Express lives on its own screen: panel [data-step-panel="express"].
        super('express', appState);
        if (this.panel) {
            this.topicInput = this.panel.querySelector('[data-testid="express-topic"]');
            this.output = this.panel.querySelector('#express-stream-output');
        }
        this.article = null;
    }

    getTopic() {
        return this.topicInput ? this.topicInput.value.trim() : '';
    }

    /**
     * Render the typed article object (contract §5 response shape).
     * Long-form parts arrive server-sanitised; scalar fields are escaped.
     */
    renderArticle(data, options = {}) {
        const received = (data && data.article) || null;
        const withoutFragmentTitle = (html) => String(html || '')
            .replace(/<h1\b[^>]*>[\s\S]*?<\/h1>/gi, '');
        const normaliseFragment = (html) => {
            const host = document.createElement('div');
            host.innerHTML = withoutFragmentTitle(html);

            /*
             * Structured providers occasionally return valid JSON containing
             * invalid prose markup, for example:
             *
             *   <span><h2>Conclusion</h2>Bare conclusion text</span>
             *
             * Browsers keep the outer inline wrapper and its bare text. That
             * text then bypasses the paragraph typography and reading measure,
             * which made a single fragment appear wider and less spaced than
             * the rest of the article. Unwrap only layout/invalid wrappers,
             * then turn direct inline/text runs into semantic paragraphs.
             * Tables, figures, blockquotes and inline emphasis remain intact.
             */
            host.querySelectorAll('article, main, section, div').forEach((wrapper) => {
                if (wrapper.closest('table, figure, blockquote')) return;
                wrapper.replaceWith(...Array.from(wrapper.childNodes));
            });

            const blockSelector = 'address, aside, blockquote, figure, figcaption, h1, h2, h3, h4, h5, h6, hr, ol, p, pre, table, ul';
            host.querySelectorAll('span, strong, em, b, i, small, mark').forEach((wrapper) => {
                if (wrapper.querySelector(blockSelector)) {
                    wrapper.replaceWith(...Array.from(wrapper.childNodes));
                }
            });

            const output = document.createDocumentFragment();
            let paragraph = null;
            const appendParagraph = () => {
                if (!paragraph) return;
                if ((paragraph.textContent || '').trim() || paragraph.children.length) {
                    output.append(paragraph);
                }
                paragraph = null;
            };
            Array.from(host.childNodes).forEach((node) => {
                const isElement = node.nodeType === 1;
                const isBlock = isElement && node.matches(blockSelector);
                if (isBlock) {
                    appendParagraph();
                    output.append(node);
                    return;
                }
                if (node.nodeType === 3 && !(node.textContent || '').trim() && !paragraph) {
                    return;
                }
                if (node.nodeType !== 1 && node.nodeType !== 3) {
                    return;
                }
                if (!paragraph) paragraph = document.createElement('p');
                paragraph.append(node);
            });
            appendParagraph();
            host.replaceChildren(output);
            return host.innerHTML.trim();
        };
        this.article = received ? Object.assign({}, received, {
            intro: normaliseFragment(received.intro),
            body_html: normaliseFragment(received.body_html),
            conclusion: normaliseFragment(received.conclusion)
        }) : null;
        if (!this.article) {
            this.showEmpty();
            return;
        }
        this.showQualityNotice(data && data.quality_plan);
        const article = this.article;
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
        const parts = [];
        // The typed title is authoritative and must always be the first article
        // element. Older stored responses can contain a provider-added H1 in
        // any fragment, sometimes using the SEO meta title instead; strip all
        // fragment H1s defensively so the preview and Refine handoff agree.
        if (article.title) {
            parts.push(`<h1>${esc(article.title)}</h1>`);
        }
        if (article.tagline) {
            parts.push(`<p><em>${esc(article.tagline)}</em></p>`);
        }
        parts.push(
            withoutFragmentTitle(article.intro),
            withoutFragmentTitle(article.body_html),
            withoutFragmentTitle(article.conclusion)
        );
        (article.qna || []).forEach((item) => {
            parts.push(`<h3>${esc(plain(item.question))}</h3><p>${esc(plain(item.answer))}</p>`);
        });
        this.renderTrustedHtml(this.output, parts.filter(Boolean).join('\n'));
        this.renderTargetStatus(data && data.quality_plan);
        this.seedWizardState(data, options);
        this.showReady();
    }

    renderTargetStatus(plan) {
        const status = this.panel && this.panel.querySelector('[data-article-target-status]');
        const live = this.panel && this.panel.querySelector('[data-article-target-live]');
        if (!status || !plan || !Number.isFinite(Number(plan.word_count))) {
            if (status) status.hidden = true;
            if (live) live.textContent = '';
            return;
        }
        const words = Number(plan.word_count);
        const target = Math.max(1, Number(plan.target_words) || words);
        const min = Number(plan.minimum_words) || Math.floor(target * 0.85);
        const max = Number(plan.maximum_words) || Math.ceil(target * 1.15);
        const inRange = words >= min && words <= max;
        const heading = status.querySelector('[data-target-status-heading]');
        const detail = status.querySelector('[data-target-status-detail]');
        const bar = status.querySelector('[data-target-status-bar]');
        const action = status.querySelector('[data-target-status-action]');
        if (heading) heading.textContent = `${words.toLocaleString()} words generated`;
        if (detail) {
            const difference = Math.abs(target - words);
            const progress = difference === 0
                ? 'Selected target reached.'
                : `${difference.toLocaleString()} words ${words < target ? 'to' : 'above'} target.`;
            detail.textContent = `Target ${target.toLocaleString()} · preferred range ${min.toLocaleString()}–${max.toLocaleString()} · ${progress}`
                + (!inRange ? ' The complete draft was kept.' : '');
        }
        if (bar) bar.style.width = `${Math.min(100, Math.max(4, (words / target) * 100))}%`;
        status.classList.toggle('is-in-range', inRange);
        status.classList.toggle('is-advisory', !inRange);
        if (action) action.hidden = words >= target;
        status.hidden = false;
        this.setImprovementState('idle');
        if (live) {
            live.textContent = '';
            window.setTimeout(() => {
                live.textContent = `${heading ? heading.textContent : `${words.toLocaleString()} words generated`}. ${detail ? detail.textContent : ''}`;
            }, 0);
        }
    }

    /** Keep the current draft visible while a manual length improvement runs. */
    setImprovementState(state, message = '') {
        const button = this.panel && this.panel.querySelector('[data-action="express-improve-length"]');
        const label = button && button.querySelector('[data-improve-length-label]');
        const status = this.panel && this.panel.querySelector('[data-improve-length-status]');
        if (!button || !status) return;

        button.disabled = state === 'loading';
        button.classList.toggle('is-busy', state === 'loading');
        button.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
        const translated = (key, fallback) => {
            const value = this.t(key);
            return value && value !== key ? value : fallback;
        };
        if (label) {
            label.textContent = state === 'loading'
                ? translated('improvingLength', 'Improving length')
                : (state === 'error'
                    ? translated('retryImprovement', 'Try improvement again')
                    : translated('improveLength', 'Improve length'));
        }
        const defaults = {
            idle: translated('improveLengthIdle', 'AI-Scribe will extend thin sections without replacing the draft you can see.'),
            loading: translated('improveLengthLoading', 'Extending the current draft now. The existing version will stay here if the request cannot finish.'),
            error: translated('improveLengthError', 'The improvement could not finish. Your existing draft is unchanged.'),
            success: translated('improveLengthSuccess', 'The draft was extended and the count has been checked again.')
        };
        status.textContent = message || defaults[state] || defaults.idle;
        status.classList.toggle('is-error', state === 'error');
    }

    /**
     * Contract §5: all article parts are already written into the server
     * conversation's selections/steps. Mirror them into local stepData so
     * the wizard renders instantly without a get_state round-trip.
     */
    seedWizardState(data, options = {}) {
        if (!this.appState || !this.article) {
            return;
        }
        const article = this.article;
        // Express is a new article, not another step in whatever Wizard draft
        // happened to be open. Build a fresh handoff object so old keywords,
        // edits, images and save destinations cannot leak into this result.
        const stepData = {};
        stepData[1] = { selection: article.title || '' };
        stepData[3] = { selection: article.outline || [] };
        stepData[4] = { contentHtml: article.intro || '' };
        stepData[5] = { selection: article.tagline || '' };
        stepData[6] = { contentHtml: article.body_html || '', qualityPlan: data.quality_plan || null };
        stepData[7] = { contentHtml: article.conclusion || '' };
        stepData[8] = { selection: article.qna || [] };
        stepData[9] = { selection: article.meta || {} };
        this.appState.setStateSlice('stepData', stepData);
        this.appState.setStateSlice('reviewEditedHtml', '');
        if (!options.preservePersistence) {
            this.appState.setStateSlice('galleryImages', []);
            this.appState.setStateSlice('featuredImageAutoStarted', false);
            this.appState.setStateSlice('featuredImageRemoved', false);
            this.appState.setStateSlice('persistence', { post: null, shortcode: null });
            this.appState.setStateSlice('publishingDetails', {});
        }
        if (data.conversation_id) {
            this.appState.setStateSlice('conversationId', data.conversation_id);
        }
    }

    getArticle() {
        return this.article;
    }

    /** Exact visible Express snapshot used by the shared save handlers. */
    getSaveHtml() {
        return this.output ? this.output.innerHTML.trim() : '';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ExpressView;
}
