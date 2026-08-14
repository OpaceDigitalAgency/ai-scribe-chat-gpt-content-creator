/**
 * Step 6 — Article Body. Streaming long-form content rendered live into a
 * Quill editor with the image gallery alongside (editor-with-gallery layout).
 *
 * While streaming, deltas render into the prose overlay for zero-latency
 * feedback; on `done` the full sanitised HTML is loaded into Quill via the
 * clipboard API (never raw innerHTML on Quill internals).
 *
 * Salvaged from v4 DisplayManager.insertArticleBodyIntoQuill /
 * initializeBodyQuillEditor — reduced to the parts that worked.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global StreamingStepView, Quill */
/* exported BodyStepView */

class BodyStepView extends StreamingStepView {

    /**
     * Editor changes land in state this long after the last keystroke. Every
     * write goes through AppState, which clones the whole state and rewrites
     * localStorage, so persisting per character would make typing stutter.
     */
    static PERSIST_DELAY_MS = 300;

    constructor(appState) {
        super(6, appState, { proseSelector: '#body-stream-output' });
        this.editorHost = this.panel ? this.panel.querySelector('#body-quill-editor') : null;
        this.quill = null;
        this.persistTimer = null;
        this.coverageError = false;
    }

    ensureEditor() {
        if (this.quill || !this.editorHost || typeof Quill === 'undefined') {
            return this.quill;
        }
        this.quill = new Quill(this.editorHost, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'link'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'clean']
                ]
            }
        });
        // Everything the user does in here — typed edits and inserted images
        // alike — has to reach the Review compiler, which reads stepData from
        // AppState and never asks this view. Programmatic loads use the
        // 'silent' source and are mirrored by renderContent() instead.
        this.quill.on('text-change', (delta, oldDelta, source) => {
            if (source !== 'silent') {
                this.scheduleEditPersist();
            }
        });
        // Leaving the editor commits immediately: a pending debounce must not
        // be the reason an edit misses the article.
        this.quill.on('selection-change', (range) => {
            if (!range) {
                this.persistEdit();
            }
        });
        return this.quill;
    }

    renderContent(html, generationData = null) {
        const normalisedHtml = typeof BaseStepView.normaliseArticleMarkup === 'function'
            ? BaseStepView.normaliseArticleMarkup(html)
            : html;
        this.finalHtml = normalisedHtml;
        if (typeof this.showQualityNotice === 'function') {
            // The controller's live target card measures the exact HTML after
            // normalisation. Repeating the server's pre-render advisory here
            // can show an older count beside the canonical current count.
            this.showQualityNotice(null);
        }
        const quill = this.ensureEditor();
        if (quill) {
            // Hide the streaming overlay once the editor takes over.
            if (this.proseTarget) {
                this.proseTarget.hidden = true;
                this.renderTrustedHtml(this.proseTarget, '');
            }
            const delta = quill.clipboard.convert({ html: normalisedHtml });
            quill.setContents(delta, 'silent');
        } else {
            // Quill unavailable — keep the prose fallback visible.
            this.renderTrustedHtml(this.proseTarget, normalisedHtml);
        }
        // A regenerated body is the new starting point, so the stored edit
        // becomes what the editor now shows rather than the previous run's.
        this.persistEdit(false, generationData !== null);
    }

    /**
     * Require the generated body to represent the selected outline exactly.
     * Matching is deliberately strict after entity/case/space normalisation:
     * a different heading is different content, not a fuzzy match.
     */
    validateOutlineCoverage() {
        const stepData = this.appState ? (this.appState.getStateSlice('stepData') || {}) : {};
        const selected = (stepData[3] && Array.isArray(stepData[3].selection))
            ? stepData[3].selection : [];
        const uniqueSelected = [];
        const selectedById = new Map();
        selected.forEach((heading) => {
            const identity = BodyStepView.headingIdentity(heading);
            if (identity && !selectedById.has(identity)) {
                selectedById.set(identity, String(heading).replace(/\s+/g, ' ').trim());
                uniqueSelected.push(identity);
            }
        });
        if (uniqueSelected.length === 0) {
            return { valid: true, missing: [], unexpected: [] };
        }

        const host = document.createElement('div');
        host.innerHTML = this.getSelection() || '';
        const actualById = new Map();
        host.querySelectorAll('h2, h3, h4, h5, h6').forEach((heading) => {
            const identity = BodyStepView.headingIdentity(heading.textContent);
            if (identity && !actualById.has(identity)) {
                actualById.set(identity, heading.textContent.replace(/\s+/g, ' ').trim());
            }
        });
        const missing = uniqueSelected
            .filter((identity) => !actualById.has(identity))
            .map((identity) => selectedById.get(identity));
        const unexpected = Array.from(actualById.keys())
            .filter((identity) => !selectedById.has(identity))
            .map((identity) => actualById.get(identity));
        return { valid: missing.length === 0 && unexpected.length === 0, missing, unexpected };
    }

    enforceOutlineCoverage() {
        const coverage = this.validateOutlineCoverage();
        if (coverage.valid) {
            if (this.coverageError) {
                this.coverageError = false;
                this.showReady();
                this.setNextEnabled(true);
            }
            return true;
        }
        const details = [];
        if (coverage.missing.length) {
            details.push(`Missing: ${coverage.missing.join('; ')}`);
        }
        if (coverage.unexpected.length) {
            details.push(`Not selected: ${coverage.unexpected.join('; ')}`);
        }
        const regenerate = this.panel && this.panel.querySelector('[data-action="generate"]');
        this.coverageError = true;
        this.showError(
            new Error(`The generated body does not match your selected outline. ${details.join(' · ')}`),
            regenerate ? () => regenerate.click() : null
        );
        this.setNextEnabled(false);
        return false;
    }

    static headingIdentity(heading) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(heading == null ? '' : heading);
        return textarea.value.replace(/\s+/g, ' ').trim().toLocaleLowerCase();
    }

    /** Coalesce a burst of keystrokes into one state write. */
    scheduleEditPersist() {
        if (this.persistTimer) {
            clearTimeout(this.persistTimer);
        }
        this.persistTimer = setTimeout(() => {
            this.persistTimer = null;
            this.persistEdit(true);
        }, BodyStepView.PERSIST_DELAY_MS);
    }

    /**
     * Store the body the user can actually see. `stepData[6].contentHtml`
     * only ever held the generated draft, so every edit and every inserted
     * image was dropped on the way to Review (S8-06).
     */
    persistEdit(validateCoverage = true, invalidateReview = validateCoverage) {
        if (this.persistTimer) {
            clearTimeout(this.persistTimer);
            this.persistTimer = null;
        }
        if (!this.appState) {
            return;
        }
        const stepData = this.appState.getStateSlice('stepData') || {};
        stepData[this.step] = stepData[this.step] || {};
        stepData[this.step].editedHtml = this.getSelection() || '';
        this.appState.setStateSlice('stepData', stepData);
        // A newly generated or owner-edited body supersedes any old Review
        // editor snapshot. Recovery renders pass generationData=null above,
        // so a mere reload does not destroy a saved Review edit.
        if (invalidateReview) {
            this.appState.setStateSlice('reviewEditedHtml', null);
        }
        if (validateCoverage && (this.state === 'ready' || this.coverageError)) {
            this.enforceOutlineCoverage();
        }
    }

    streamHandlers(callbacks = {}) {
        if (this.proseTarget) {
            this.proseTarget.hidden = false;
        }
        const originalDone = callbacks.onDone;
        return super.streamHandlers({
            ...callbacks,
            onDone: (data) => {
                this.enforceOutlineCoverage();
                if (typeof originalDone === 'function') {
                    originalDone(data);
                }
            }
        });
    }

    renderTyped(items, options) {
        super.renderTyped(items, options);
        this.enforceOutlineCoverage();
    }

    /** Current edited HTML (Quill wins over the streamed original). */
    getSelection() {
        if (this.quill) {
            const html = this.quill.getSemanticHTML
                ? this.quill.getSemanticHTML()
                : this.quill.root.innerHTML;
            const compact = BodyStepView.compactEditorHtml(html);
            return typeof BaseStepView.normaliseArticleMarkup === 'function'
                ? BaseStepView.normaliseArticleMarkup(compact)
                : compact;
        }
        const fallback = this.finalHtml || null;
        return typeof BaseStepView.normaliseArticleMarkup === 'function'
            ? BaseStepView.normaliseArticleMarkup(fallback)
            : fallback;
    }

    /**
     * Quill uses <p><br></p> as its caret spacer. Repeated image insertion
     * can leave several of those blocks in the exported article, where they
     * become a large visible gap in Review and the saved post.
     */
    static compactEditorHtml(html) {
        if (typeof html !== 'string' || !html) return html;
        const host = document.createElement('div');
        host.innerHTML = html;
        host.querySelectorAll('p, div').forEach((block) => {
            const meaningful = (block.textContent || '').replace(/\u00a0/g, ' ').trim()
                || block.querySelector('img, video, iframe, table, ul, ol, blockquote');
            const onlyBreaks = Array.from(block.childNodes).every((node) => (
                (node.nodeType === Node.TEXT_NODE && !node.textContent.trim())
                || (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR')
            ));
            if (!meaningful && onlyBreaks) block.remove();
            block.style.removeProperty('height');
            block.style.removeProperty('min-height');
        });
        return host.innerHTML;
    }

    /**
     * Skipping the body drops the edited copy with it — editedHtml wins over
     * contentHtml in the Review compiler, so clearing only the latter would
     * leave the skipped draft in the article.
     */
    skip() {
        if (this.quill) {
            this.quill.setContents([], 'silent');
        }
        super.skip();
        this.persistEdit();
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = BodyStepView;
}
