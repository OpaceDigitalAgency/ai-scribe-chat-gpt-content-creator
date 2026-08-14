/**
 * BaseStepView — shared behaviour for every wizard step panel.
 *
 * Replaces the DisplayManager monolith. Each step gets one small view that
 * consumes TYPED data from the engine's structured outputs. Every view owns
 * exactly one panel `[data-step-panel="N"]` and exposes the four mandatory
 * states: loading (skeleton), streaming, error-with-retry, empty.
 *
 * Rules enforced here:
 * - Errors are always visible, never silent, and never auto-advance.
 * - No innerHTML of unsanitised strings — text goes through textContent;
 *   trusted HTML (server-sanitised article HTML) is the only exception and
 *   flows through renderTrustedHtml() so the exception is auditable.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global lucide */
/* exported BaseStepView */

class BaseStepView {

    /**
     * Repair provider markup that turns a prose paragraph into a heading or
     * makes an entire paragraph bold. The provider's words and inline content
     * remain intact; only the implausible block-level presentation is removed.
     *
     * Short headings and short emphasised callouts are deliberately left
     * alone. Images, captions, links, lists and tables are never re-parented.
     *
     * @param {string|null|undefined} html Server-sanitised article fragment.
     * @returns {string|null|undefined} Semantically safer article fragment.
     */
    static normaliseArticleMarkup(html) {
        if (typeof html !== 'string' || html === '') {
            return html;
        }
        const host = document.createElement('div');
        host.innerHTML = html;
        const words = (node) => (node.textContent || '')
            .replace(/\u00a0/g, ' ')
            .trim()
            .split(/\s+/)
            .filter(Boolean).length;
        const characters = (node) => (node.textContent || '').replace(/\s+/g, ' ').trim().length;

        host.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach((heading) => {
            if (words(heading) <= 20 && characters(heading) <= 160) {
                return;
            }
            const paragraph = document.createElement('p');
            paragraph.append(...Array.from(heading.childNodes));
            heading.replaceWith(paragraph);
        });

        host.querySelectorAll('strong, b').forEach((emphasis) => {
            if (words(emphasis) <= 24 && characters(emphasis) <= 180) {
                return;
            }
            const parent = emphasis.parentElement;
            if (!parent) return;
            const onlyMeaningfulChild = Array.from(parent.childNodes).every((node) => (
                node === emphasis || (node.nodeType === Node.TEXT_NODE && !(node.textContent || '').trim())
            ));
            if (!onlyMeaningfulChild) return;
            if (parent.matches('p')) {
                emphasis.replaceWith(...Array.from(emphasis.childNodes));
                return;
            }
            if (parent === host || parent.matches('div, section, article')) {
                const paragraph = document.createElement('p');
                paragraph.append(...Array.from(emphasis.childNodes));
                emphasis.replaceWith(paragraph);
            }
        });
        return host.innerHTML;
    }

    /**
     * What each step is for, shown on a panel nothing has been generated for
     * yet. Steps 2-9 and 11 keep every control inside the hidden results
     * section, so without this an unvisited panel is a heading above an empty
     * white void with no clue what it does or how to start it.
     */
    static IDLE_COPY = {
        2: { icon: 'search', what: 'Keyword research finds the search terms this article should target.' },
        3: { icon: 'list', what: 'The outline fixes the section headings the body gets written from.' },
        4: { icon: 'play-circle', what: 'The introduction opens the article, written from your title, keywords and outline.' },
        5: { icon: 'tag', what: 'The tagline is the one-line hook that sits with the introduction.' },
        6: { icon: 'file-text', what: 'The article body is the long-form draft, written section by section from your outline. You can edit it here and drop images into it.' },
        7: { icon: 'check-circle', what: 'The conclusion closes the article, written with the whole body in context.' },
        8: { icon: 'help-circle', what: 'A set of questions and answers to append to the article, useful for FAQ rich results.' },
        9: { icon: 'database', what: 'The meta title and description search engines show in their results.' },
        11: { icon: 'bar-chart-3', what: 'A quality and optimisation report on the finished article.' }
    };

    /**
     * @param {number} step      Step number (1-11).
     * @param {Object} appState  AppState instance (observer pattern).
     */
    constructor(step, appState) {
        this.step = step;
        this.appState = appState;
        this.panel = document.querySelector(`[data-step-panel="${step}"]`);
        this.state = 'idle';
        this.lastRetryHandler = null;

        if (this.panel) {
            this.resultsSection = this.panel.querySelector('.results-section');
            this.statusRegion = this.panel.querySelector('[data-testid="step-status"]');
            this.bindPanelEvents();
        }
    }

    /** Override for delegated event wiring. No inline onclick anywhere. */
    bindPanelEvents() {}

    /** Override: render typed items into the panel. */
    render(/* items */) {}

    /** Override: return the user's selection for this step (or null). */
    getSelection() {
        return null;
    }

    isAvailable() {
        return !!this.panel;
    }

    /* ------------------------------------------------------------------ */
    /* State machine                                                      */
    /* ------------------------------------------------------------------ */

    setState(state) {
        this.state = state;
        if (state !== 'loading' && state !== 'streaming') {
            this.stopProgressTicker();
        }
        if (!this.panel) {
            return;
        }
        this.panel.setAttribute('data-state', state);
        this.panel.setAttribute('aria-busy', state === 'loading' || state === 'streaming' ? 'true' : 'false');
        if (this.resultsSection) {
            this.resultsSection.classList.toggle(
                'is-refreshing',
                (state === 'loading' || state === 'streaming') && this.hasExistingResult()
            );
        }
        if (state !== 'error') {
            this.clearError();
        }
        if (state !== 'idle') {
            this.hideIdle();
        }
        if (state === 'loading') {
            this.showSkeleton();
        } else {
            this.hideSkeleton();
        }
    }

    /**
     * The state a user meets on a panel that has not run yet: what the step
     * produces, and how it gets started. Called once per view at start-up,
     * and superseded by every other state.
     */
    showIdle() {
        const copy = this.idleCopy();
        if (!this.panel || !copy) {
            return;
        }
        this.setState('idle');
        const box = this.ensureIdleBox(copy.icon);
        box.querySelector('.state-box-lead').textContent = copy.what;
        box.querySelector('.state-box-next').textContent = this.idleNextText();
        box.hidden = false;
        this.refreshIcons();
    }

    /** Override to give a step its own idle description. */
    idleCopy() {
        return BaseStepView.IDLE_COPY[this.step] || null;
    }

    /** How this step gets started, named after the step before it. */
    idleNextText() {
        const names = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.stepNames) || {};
        const previous = names[this.step - 1];
        return previous
            ? ` Nothing has been generated yet — it runs as soon as you continue from ${previous}.`
            : ' Nothing has been generated yet.';
    }

    /**
     * Lazily create the idle box, in the place the results it stands in for
     * will appear.
     *
     * @param {string} icon Lucide icon name.
     * @returns {HTMLElement}
     */
    ensureIdleBox(icon) {
        let box = this.panel.querySelector('.idle-state');
        if (box) {
            return box;
        }
        box = document.createElement('div');
        box.className = 'state-box idle-state';
        box.setAttribute('data-testid', 'step-idle');
        box.hidden = true;

        const iconEl = document.createElement('i');
        iconEl.setAttribute('data-lucide', icon || 'sparkles');
        iconEl.setAttribute('aria-hidden', 'true');

        const message = document.createElement('p');
        message.className = 'state-box-message';
        const lead = document.createElement('strong');
        lead.className = 'state-box-lead';
        const next = document.createElement('span');
        next.className = 'state-box-next';
        message.appendChild(lead);
        message.appendChild(next);

        box.appendChild(iconEl);
        box.appendChild(message);
        // Results normally belong directly to the panel, but Express nests
        // them in its content container. The reference must belong to the
        // parent receiving insertBefore(), otherwise WebKit throws.
        const parent = this.resultsSection && this.resultsSection.parentNode;
        if (parent) {
            parent.insertBefore(box, this.resultsSection);
        } else {
            this.panel.appendChild(box);
        }
        return box;
    }

    hideIdle() {
        if (!this.panel) {
            return;
        }
        const box = this.panel.querySelector('.idle-state');
        if (box) {
            box.hidden = true;
        }
    }

    showLoading(message) {
        this.showQualityNotice(null);
        this.setState('loading');
        this.startProgressTicker(message || this.t('preparingRequest'), 'preparing');
    }

    /**
     * Keep a valid draft visible when it misses a preferred editorial range.
     * This is deliberately separate from the error component: the generation
     * completed and the next action is review, edit or regenerate.
     */
    showQualityNotice(quality) {
        if (!this.panel) {
            return;
        }
        let notice = this.panel.querySelector('[data-testid="article-quality-notice"]');
        if (!quality || !quality.advisory || !quality.message) {
            if (notice) notice.hidden = true;
            return;
        }
        if (!notice) {
            notice = document.createElement('div');
            notice.className = 'article-quality-notice';
            notice.setAttribute('data-testid', 'article-quality-notice');
            notice.setAttribute('role', 'status');
            const title = document.createElement('strong');
            title.textContent = 'Article generated — target not fully reached';
            const message = document.createElement('p');
            message.setAttribute('data-quality-message', '');
            notice.appendChild(title);
            notice.appendChild(message);
            // Most step results live directly in their panel, but Express
            // nests its results inside its content container. insertBefore()
            // requires the reference node to be a child of the receiving
            // parent; using the panel for that nested reference throws a
            // NotFoundError in WebKit after a successful response.
            const parent = this.resultsSection && this.resultsSection.parentNode;
            if (parent) {
                parent.insertBefore(notice, this.resultsSection);
            } else {
                this.panel.appendChild(notice);
            }
        }
        const message = notice.querySelector('[data-quality-message]');
        if (message) message.textContent = quality.message;
        notice.hidden = false;
    }

    /**
     * The ONE progress component every generation shows: what is running,
     * how long it has been running, and an explicitly indeterminate bar.
     *
     * A skeleton alone gives no way to tell a slow model from a hung request,
     * so a long step looked identical to a crash. This names the running
     * step, counts elapsed seconds, shows activity without inventing a total,
     * and once a run passes the point where people start to assume it has
     * died, says explicitly that it is still going. Errors replace it with
     * the explicit error state via showError().
     *
     * @param {string} label Message to show alongside the timer.
     * @param {string} stage Stable state-machine key.
     */
    startProgressTicker(label, stage = 'preparing') {
        this.stopProgressTicker();
        if (!this.panel) {
            return;
        }
        let box = this.panel.querySelector('[data-testid="progress-ticker"]');
        if (!box) {
            box = document.createElement('div');
            box.className = 'step-progress';
            box.setAttribute('role', 'group');
            box.setAttribute('aria-label', 'Generation progress');
            box.setAttribute('data-testid', 'progress-ticker');

            const row = document.createElement('div');
            row.className = 'step-progress-row';
            const labelEl = document.createElement('span');
            labelEl.className = 'step-progress-label';
            const elapsedEl = document.createElement('span');
            elapsedEl.className = 'step-progress-elapsed';
            row.appendChild(labelEl);
            row.appendChild(elapsedEl);

            const bar = document.createElement('div');
            bar.className = 'step-progress-bar';
            bar.setAttribute('role', 'progressbar');
            bar.setAttribute('aria-label', 'Generation in progress');
            const fill = document.createElement('div');
            fill.className = 'step-progress-fill';
            bar.appendChild(fill);

            const note = document.createElement('p');
            note.className = 'step-progress-note';
            note.hidden = true;

            const context = document.createElement('p');
            context.className = 'step-progress-context';

            box.appendChild(row);
            box.appendChild(bar);
            box.appendChild(note);
            box.appendChild(context);
            const expressSlot = this.step === 'express'
                ? this.panel.querySelector('[data-testid="express-progress-slot"]')
                : null;
            if (expressSlot) {
                expressSlot.appendChild(box);
            }
            const skeleton = this.panel.querySelector('.skeleton-loader');
            // Express keeps its skeleton inside the output article rather
            // than as a direct panel child. insertBefore() only accepts a
            // reference node owned by the parent, so climb to the direct
            // child that contains the preferred insertion point.
            let anchor = skeleton || this.resultsSection || null;
            while (!expressSlot && anchor && anchor.parentNode !== this.panel) {
                anchor = anchor.parentNode;
            }
            if (!expressSlot) {
                this.panel.insertBefore(box, anchor || null);
            }
        }
        box.hidden = false;
        this.progressStartedAt = Date.now();
        this.updateProgressStage(stage, label);

        const elapsedEl = box.querySelector('.step-progress-elapsed');
        const note = box.querySelector('.step-progress-note');
        const paint = () => {
            const elapsed = (Date.now() - this.progressStartedAt) / 1000;
            const secs = Math.round(elapsed);
            if (elapsedEl) {
                elapsedEl.textContent = `${secs}s`;
            }
            if (note) {
                note.hidden = secs < 18;
                note.textContent = this.t('stillRunning') === 'stillRunning'
                    ? 'Still working; longer responses can take up to a minute.'
                    : this.t('stillRunning');
            }
        };
        paint();
        this.progressTicker = window.setInterval(paint, 1000);
    }

    /**
     * Move between boundaries the browser genuinely observes. Server-side
     * provider waiting, validation and persistence happen inside one request,
     * so they deliberately share one honest label.
     */
    updateProgressStage(stage, label) {
        const box = this.panel && this.panel.querySelector('[data-testid="progress-ticker"]');
        if (!box || box.hidden) {
            return;
        }
        const labels = {
            preparing: this.t('preparingRequest'),
            waiting: this.t('waitingForResponse'),
            displaying: this.t('displayingResult')
        };
        const text = label || labels[stage] || labels.waiting;
        const labelEl = box.querySelector('.step-progress-label');
        const context = box.querySelector('.step-progress-context');
        box.setAttribute('data-progress-stage', stage);
        if (labelEl) {
            labelEl.textContent = text;
        }
        if (context) {
            const model = document.getElementById('active-model-details');
            const modelText = model ? model.textContent.trim() : '';
            context.textContent = [this.stepDisplayName(), modelText].filter(Boolean).join(' · ');
        }
        this.announce(text);
    }

    /** The human name of what is running, for the progress label. */
    stepDisplayName() {
        const names = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.stepNames) || {};
        if (names[this.step]) {
            return names[this.step];
        }
        const heading = this.panel && this.panel.querySelector('.step-heading');
        return heading ? heading.textContent.trim() : '';
    }

    stopProgressTicker() {
        if (this.progressTicker) {
            window.clearInterval(this.progressTicker);
            this.progressTicker = null;
        }
        const el = this.panel && this.panel.querySelector('[data-testid="progress-ticker"]');
        if (el) {
            el.hidden = true;
        }
        this.progressStartedAt = null;
    }

    showStreaming() {
        this.setState('streaming');
        this.updateProgressStage('displaying');
    }

    /**
     * Success. Completion was the one outcome that stayed silent: loading,
     * streaming, empty and error all announce themselves, so a screen-reader
     * user was told a step had started and never that it had finished.
     *
     * @param {string} [message] Step-specific wording; defaults to t('ready').
     */
    showReady(message) {
        this.setState('ready');
        if (this.resultsSection) {
            this.resultsSection.classList.remove('hidden');
        }
        this.refreshIcons();
        this.announce(message || this.t('ready'));
    }

    showEmpty(message) {
        this.setState('empty');
        const empty = this.ensureStateBox('empty-state', 'inbox');
        empty.querySelector('.state-box-message').textContent =
            message || this.t('empty');
        empty.hidden = false;
        this.announce(message || this.t('empty'));
    }

    /**
     * Visible error with retry. Never silent, never auto-advance.
     *
     * @param {Error}    error    ApiError or plain Error.
     * @param {Function} onRetry  Re-runs the failed request (idempotent server-side).
     */
    showError(error, onRetry) {
        this.setState('error');
        this.lastRetryHandler = typeof onRetry === 'function' ? onRetry : null;

        const box = this.ensureStateBox('error-state', 'alert-triangle');
        box.querySelector('.state-box-message').textContent =
            (error && error.message) ? error.message : this.t('genericError');

        const retryBtn = box.querySelector('[data-testid="step-retry"]');
        retryBtn.hidden = !this.lastRetryHandler;
        const retryable = !error || error.retryable !== false;
        retryBtn.textContent = retryable ? this.t('retry') : this.t('tryAgain');

        box.hidden = false;
        this.announce(box.querySelector('.state-box-message').textContent);
		if (window.aiScribeNotifications) {
			window.aiScribeNotifications.show({
				title: 'This step could not finish',
				message: box.querySelector('.state-box-message').textContent,
				type: 'error',
				announce: false,
				key: `step-${this.step}-error:${box.querySelector('.state-box-message').textContent}`
			});
		}
        box.querySelector('.state-box-message').setAttribute('tabindex', '-1');
        box.querySelector('.state-box-message').focus({ preventScroll: false });
        this.refreshIcons();
    }

    clearError() {
        if (!this.panel) {
            return;
        }
        const box = this.panel.querySelector('.error-state');
        if (box) {
            box.hidden = true;
        }
        const empty = this.panel.querySelector('.empty-state');
        if (empty) {
            empty.hidden = true;
        }
    }

    /**
     * Lazily create a shared state box (error/empty) inside the panel.
     */
    ensureStateBox(className, icon) {
        let box = this.panel.querySelector(`.${className}`);
        if (box) {
            return box;
        }
        box = document.createElement('div');
        box.className = `state-box ${className}`;
        box.setAttribute('role', className === 'error-state' ? 'alert' : 'status');
        box.setAttribute('data-testid', className === 'error-state' ? 'step-error' : 'step-empty');
        box.hidden = true;

        const iconEl = document.createElement('i');
        iconEl.setAttribute('data-lucide', icon);
        iconEl.setAttribute('aria-hidden', 'true');

        const message = document.createElement('p');
        message.className = 'state-box-message';

        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'btn btn-outline state-box-retry';
        retry.setAttribute('data-testid', 'step-retry');
        retry.textContent = this.t('retry');
        retry.hidden = className !== 'error-state';
        retry.addEventListener('click', () => {
            if (this.lastRetryHandler) {
                this.clearError();
                this.lastRetryHandler();
            }
        });

        box.appendChild(iconEl);
        box.appendChild(message);
        box.appendChild(retry);
        this.panel.insertBefore(box, this.panel.firstChild);
        return box;
    }

    /* ------------------------------------------------------------------ */
    /* Skeleton loaders                                                   */
    /* ------------------------------------------------------------------ */

    showSkeleton() {
        if (!this.panel) {
            return;
        }
        if (this.hasExistingResult()) {
            this.hideSkeleton();
            return;
        }
        const spec = this.skeletonSpec();
        let skeleton = this.panel.querySelector('.skeleton-loader');
        if (!skeleton || skeleton.getAttribute('data-skeleton-shape') !== spec.shape
            || parseInt(skeleton.getAttribute('data-skeleton-count'), 10) !== spec.count) {
            if (skeleton) {
                skeleton.remove();
            }
            skeleton = document.createElement('div');
            skeleton.className = `skeleton-loader skeleton-${spec.shape}`;
            skeleton.setAttribute('data-testid', 'step-loading');
            skeleton.setAttribute('aria-hidden', 'true');
            skeleton.setAttribute('data-skeleton-shape', spec.shape);
            skeleton.setAttribute('data-skeleton-count', String(spec.count));
            for (let i = 0; i < spec.count; i++) {
                const card = document.createElement('div');
                card.className = `skeleton-card skeleton-card-${spec.shape}`;
                const lines = spec.shape === 'choice' ? 1 : (spec.shape === 'meta' ? 2 : 3);
                for (let line = 0; line < lines; line++) {
                    const span = document.createElement('span');
                    span.className = 'skeleton-line' + (line === 0 ? ' skeleton-line-title' : '')
                        + (line === lines - 1 && lines > 1 ? ' skeleton-line-short' : '');
                    card.appendChild(span);
                }
                skeleton.appendChild(card);
            }
            const anchor = this.resultsSection || this.panel;
            anchor.parentNode.insertBefore(skeleton, this.resultsSection || null);
        }
        skeleton.hidden = false;
    }

    skeletonSpec() {
        if (this.step === 5) {
            return { shape: 'choice', count: 1 };
        }
        if (this.step === 1 || this.step === 2) {
            return { shape: 'choice', count: 5 };
        }
        if (this.step === 3) {
            const settings = (window.ai_scribe && window.ai_scribe.contentSettings) || {};
            const configured = parseInt(
                settings.number_of_headings || settings.number_of_heading || 5,
                10
            );
            return { shape: 'choice', count: Math.max(1, Math.min(20, configured || 5)) };
        }
        if (this.step === 8) {
            return { shape: 'choice', count: 5 };
        }
        if (this.step === 9) {
            return { shape: 'meta', count: 1 };
        }
        if (this.step === 11) {
            return { shape: 'report', count: 4 };
        }
        if (this.step === 'express') {
            return { shape: 'article', count: 4 };
        }
        return { shape: 'prose', count: 2 };
    }

    /** Existing authored output remains visible during regeneration. */
    hasExistingResult() {
        if (!this.resultsSection || this.resultsSection.classList.contains('hidden')) {
            return false;
        }
        const results = Array.from(this.resultsSection.querySelectorAll(
            '[data-testid="result-card"], .option-card, .keyword-card, .ql-editor, '
            + '.prose-output, [data-testid="evaluation-output"], input, textarea'
        ));
        return results.some((result) => {
            if (result.matches('input, textarea')) {
                return result.value.trim() !== '';
            }
            return result.textContent.trim() !== '' || result.querySelector('img') !== null;
        });
    }

    hideSkeleton() {
        if (!this.panel) {
            return;
        }
        const skeleton = this.panel.querySelector('.skeleton-loader');
        if (skeleton) {
            skeleton.hidden = true;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /** Screen-reader announcement via the panel's live region. */
    announce(text) {
        if (this.statusRegion) {
            this.statusRegion.textContent = text;
        }
    }

    /** Focus management on step change (WCAG 2.4.3). */
    focusPanel() {
        if (!this.panel) {
            return;
        }
        const heading = this.panel.querySelector('h2, h3, [data-step-heading]');
        const target = heading || this.panel;
        if (!target.hasAttribute('tabindex')) {
            target.setAttribute('tabindex', '-1');
        }
        target.focus({ preventScroll: false });
    }

    /**
     * The ONLY sanctioned innerHTML sink: server-sanitised article HTML
     * (wp_kses'd on the PHP side per the contract).
     */
    renderTrustedHtml(element, html) {
        if (element) {
            element.innerHTML = html || '';
        }
    }

    setNextEnabled(enabled) {
        if (!this.panel) {
            return;
        }
        const next = this.panel.querySelector('[data-testid="continue"]');
        if (next) {
            next.disabled = !enabled;
        }
    }

    refreshIcons() {
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    }

    /** Localised UI strings injected by templates/create_template.php. */
    t(key) {
        const strings = (window.ai_scribe && window.ai_scribe.i18n) || {};
        const fallback = {
            generating: 'Generating…',
            preparingRequest: 'Preparing request…',
            waitingForResponse: 'Waiting for and checking the provider response…',
            displayingResult: 'Response received. Displaying the result…',
            stillRunning: 'Still working; longer responses can take up to a minute.',
            ready: 'Generation complete. The results are below.',
            articleReady: 'Article compiled. Review and edit it below.',
            empty: 'Nothing generated yet. Use Generate to create options.',
            qnaSetHint: 'Include this Q&A set in the article. Click anywhere on this card to select or deselect it.',
            genericError: 'Something went wrong. Your tokens were not wasted — retry re-renders the stored response.',
            retry: 'Retry',
            tryAgain: 'Try again'
        };
        return strings[key] || fallback[key] || key;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = BaseStepView;
}
