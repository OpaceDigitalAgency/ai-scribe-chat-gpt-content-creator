/**
 * StreamingStepView — shared base for long-form streaming steps
 * (4 intro, 7 conclusion; 6 body and 10 review extend this with Quill).
 *
 * Consumes ApiClient stream handlers: deltas render live into the prose
 * target with a skeleton fallback until the first delta arrives.
 *
 * The streamed HTML is server-sanitised per the API contract (wp_kses on
 * the PHP side) and enters the DOM only via renderTrustedHtml().
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global BaseStepView */
/* exported StreamingStepView */

class StreamingStepView extends BaseStepView {
    constructor(step, appState, config = {}) {
        super(step, appState);
        this.proseTarget = this.panel
            ? this.panel.querySelector(config.proseSelector || '[data-testid="stream-output"]')
            : null;
        this.buffer = '';
        this.finalHtml = '';
    }

    /**
     * Build ApiClient stream handlers bound to this view.
     *
     * @param {Object} callbacks { onDone(data), onRetry() }
     */
    streamHandlers(callbacks = {}) {
        this.buffer = '';
        this.finalHtml = '';
        this.showLoading();

        return {
            onDelta: (data) => {
                // Contract §8: delta payload is {"text": "chunk of html"}.
                if (this.state !== 'streaming') {
                    this.showStreaming();
                }
                this.buffer += (data && (data.text || data.html)) || '';
                this.renderTrustedHtml(this.proseTarget, this.buffer);
                if (this.proseTarget) {
                    this.proseTarget.scrollTop = this.proseTarget.scrollHeight;
                }
            },
            onDone: (data) => {
                // Contract §8: done payload = run_step success data,
                // long-form parsed shape {html}.
                this.finalHtml = (data && data.parsed && data.parsed.html) || this.buffer;
                this.renderContent(this.finalHtml, data);
                this.showReady();
                this.setNextEnabled(true);
                this.persist(data);
                if (typeof callbacks.onDone === 'function') {
                    callbacks.onDone(data);
                }
            },
            onError: (error) => {
                // Visible, retryable, never auto-advance (REFACTOR.md §5.1).
                this.showError(error, callbacks.onRetry);
            }
        };
    }

    /** Final render — override in Quill-backed subclasses. */
    renderContent(html /* , data */) {
        this.finalHtml = typeof BaseStepView.normaliseArticleMarkup === 'function'
            ? BaseStepView.normaliseArticleMarkup(html)
            : html;
        this.renderTrustedHtml(this.proseTarget, this.finalHtml);
        if ((this.step === 4 || this.step === 7) && this.proseTarget) {
            this.proseTarget.contentEditable = 'true';
            this.proseTarget.setAttribute('role', 'textbox');
            this.proseTarget.setAttribute('aria-multiline', 'true');
            this.proseTarget.setAttribute('aria-label', this.step === 4 ? 'Editable introduction' : 'Editable conclusion');
            this.proseTarget.dataset.editableProse = 'true';
            if (!this.proseTarget.dataset.editListener) {
                this.proseTarget.dataset.editListener = 'true';
                this.proseTarget.addEventListener('input', () => {
                    this.finalHtml = typeof BaseStepView.normaliseArticleMarkup === 'function'
                        ? BaseStepView.normaliseArticleMarkup(this.proseTarget.innerHTML)
                        : this.proseTarget.innerHTML;
                    this.persist(null);
                });
            }
        }
    }

    /**
     * Re-render a STORED longform payload (contract §4 recovery: reload
     * mid-conversation → get_state → re-render, never re-billed).
     *
     * @param {{html: string}} items Parsed payload from the state object.
     */
    renderTyped(items, options = {}) {
        this.finalHtml = (items && items.html) || '';
        if (!this.finalHtml) {
            return;
        }
        this.renderContent(this.finalHtml, options.qualityPlan ? { quality_plan: options.qualityPlan } : null);
        this.showReady();
        this.setNextEnabled(true);
    }

    persist(data) {
        if (!this.appState) {
            return;
        }
        const stepData = this.appState.getStateSlice('stepData') || {};
        stepData[this.step] = stepData[this.step] || {};
        stepData[this.step].contentHtml = this.finalHtml;
        if (data && data.cost) {
            stepData[this.step].cost = data.cost;
        }
        if (data && data.usage) {
            stepData[this.step].usage = data.usage;
        }
        if (data && data.quality_plan) {
            stepData[this.step].qualityPlan = data.quality_plan;
        }
        this.appState.setStateSlice('stepData', stepData);
    }

    getSelection() {
        if (this.proseTarget && this.proseTarget.dataset.editableProse === 'true') {
            return this.proseTarget.innerHTML || null;
        }
        return this.finalHtml || null;
    }

    /**
     * Skip this optional long-form step (2.6.2 parity): the review compiler
     * simply omits the empty segment.
     */
    skip() {
        this.finalHtml = '';
        this.persist(null);
        this.setNextEnabled(true);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = StreamingStepView;
}
