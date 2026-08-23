/**
 * WizardFlowController — the v3 generation flow.
 *
 * Owns: step navigation (tablist semantics + focus management), generate /
 * generate-more / retry actions, streaming orchestration, Express mode,
 * cost meter updates, and publish/save-draft. All network access goes
 * through ApiClient; all rendering goes through the StepViewRegistry views.
 *
 * Event delegation only — the template ships zero inline onclick handlers.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global ApiClient, StepViewRegistry */
/* exported WizardFlowController */

class WizardFlowController {

    /**
     * Human-readable provider name. Providers brand themselves in ways a
     * naive ucfirst() gets wrong ("Openai"), so map the known ones.
     *
     * @param {string} provider Provider slug from the models endpoint.
     * @return {string} Display label.
     */
    static providerLabel(provider) {
        const labels = {
            openai: 'OpenAI',
            anthropic: 'Anthropic',
            gemini: 'Google Gemini',
            wordpress: 'WordPress AI',
        };
        const key = String(provider || '').toLowerCase();
        return labels[key] || (key.charAt(0).toUpperCase() + key.slice(1));
    }

    static STEP_TYPES = {
        1: 'titles', 2: 'keywords', 3: 'outline', 4: 'intro', 5: 'taglines',
        6: 'body', 7: 'conclusion', 8: 'qna', 9: 'meta', 10: 'review', 11: 'evaluation'
    };

    /**
     * Steps that never assemble a prompt (Review compiles from state). The
     * server may extend the list via window.ai_scribe.noPromptSteps; this is
     * the client-side floor so the prompt box never shows its placeholder as
     * if an automatic step had an editable prompt.
     */
    static NO_PROMPT_STEPS = [10];

    /**
     * @param {Object} appState  AppState instance.
     * @param {ApiClient} apiClient
     * @param {StepViewRegistry} registry
     */
    constructor(appState, apiClient, registry) {
        this.appState = appState;
        this.api = apiClient;
        this.registry = registry;
        this.activeStream = null;
        // Steps the user explicitly skipped (2 → skip_keywords, 5 → skip_tagline
        // flags on later generation calls, matching 2.6.2 prompt rewriting).
        this.skippedSteps = new Set();
        // Early/parallel image generation fires once per article (2.6 parity).
        this.imagesPrefetched = false;
        // Furthest step the user has actually reached. Forward navigation is
        // gated on it; everything at or below it stays freely reachable.
        this.maxUnlockedStep = 1;
        // Steps with a generation in flight — one billed run per press.
        this.pendingSteps = new Set();
        // The prompt each step actually ran with, and any unsent edit to it,
        // so the box never shows (or sends) another step's prompt.
        this.stepPrompts = new Map();
        this.stepPromptDrafts = new Map();
        this.promptRunStep = null;
        this.promptRunPhase = 'idle';
        // Exact Review HTML captured while the editor is still the active
        // panel. Evaluate starts after navigation, so reading the hidden
        // editor later can race Quill/state repainting and lose embeds.
        this.pendingEvaluateHtml = '';
        // Authoritative idea attached to the current server conversation.
        // The visible field may be edited before a new article is started.
        this.conversationIdea = '';
        // Guards the pre-flight estimate against out-of-order responses.
        this.estimateToken = 0;
        this.estimateRequest = null;
        // The gallery image currently armed for click-to-place, if any.
        this.armedImage = null;
        // The gallery image currently being dragged towards the editor.
        this.draggedImage = null;
        // Floating Delete/Replace toolbar for images embedded in the editor.
        this.imageToolbar = null;
        this.imageOperationPending = false;
        // A first click explains exactly what finishing an unsaved workflow
        // will discard. Only the explicit second click starts a new article.
        this.discardConfirmUntil = 0;
        // One visible elapsed timer follows the single in-flight manual
        // improvement request. It is cleared on every terminal state.
        this.improvementProgressTimer = null;
        this.improvementProgressStartedAt = 0;
        this.manualImprovementEpoch = 0;

        this.root = document.getElementById('ai-scribe-root');
        if (this.root) {
            this.bindEvents();
            this.observeState();
            this.applyQnaVisibility();
            this.applyStepLocks();
            this.refreshArticlePlanControls();
        }
    }

    /** check_Arr.addQNA === false hides the Q&A step entirely (2.6.2 parity). */
    qnaEnabled() {
        const arr = (window.ai_scribe && window.ai_scribe.checkArr) || {};
        return arr.addQNA !== false;
    }

    applyQnaVisibility() {
        if (this.qnaEnabled()) {
            return;
        }
        const chip = document.querySelector('#step-navigation [data-action="nav-step"][data-step="8"]');
        if (chip) {
            chip.hidden = true;
            chip.setAttribute('aria-hidden', 'true');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Wiring                                                             */
    /* ------------------------------------------------------------------ */

    bindEvents() {
        this.root.addEventListener('click', (e) => {
            const actionEl = e.target.closest('[data-action]');
            if (!actionEl || actionEl.disabled) {
                return;
            }
            const action = actionEl.getAttribute('data-action');
            const step = this.currentStep();
            switch (action) {
                case 'generate':
                    this.generate(step, { append: false });
                    break;
                case 'generate-more':
                    this.generate(step, { append: true });
                    break;
                case 'run-amended-prompt':
                    this.runAmendedPrompt(step);
                    break;
                case 'continue':
                    this.continueFromStep(step);
                    break;
                case 'back':
                    this.navigateToStep(this.adjacentStep(step, -1));
                    break;
                case 'skip-step':
                    this.skipStep(step);
                    break;
                case 'optimise-meta-length':
                    this.optimiseMetadata();
                    break;
                case 'apply-meta-optimisation':
                    this.registry.get(9).applyOptimiseSuggestion();
                    break;
                case 'keep-original-meta':
                    this.registry.get(9).keepOriginal();
                    break;
                case 'undo-meta-optimisation':
                    this.registry.get(9).undoOptimisation();
                    break;
                case 'nav-step':
                    this.navigateToStep(parseInt(actionEl.getAttribute('data-step'), 10));
                    break;
                case 'start-again':
                    if (step === 11 && !this.hasCurrentSavedDestination()) {
                        this.confirmDiscardAndStartAgain(actionEl);
                    } else {
                        this.startAgain();
                    }
                    break;
                case 'add-image':
                    this.addImage(step, actionEl);
                    break;
                case 'bulk-add-images':
                    this.bulkAddImages(step, actionEl);
                    break;
                case 'insert-all-images':
                    this.insertAllImages(step);
                    break;
                case 'toggle-image-overrides':
                    this.toggleImageOverrides(step, actionEl.checked);
                    break;
                case 'reset-image-overrides':
                    this.resetImageOverrides(step);
                    break;
                case 'save-draft':
                    this.savePost('draft', actionEl);
                    break;
                case 'save-post':
                    this.savePost('publish', actionEl);
                    break;
                case 'save-shortcode':
                    this.saveShortcode(actionEl);
                    break;
                case 'express-generate':
                    this.expressGenerate();
                    break;
                case 'express-improve-length':
                    this.improveExpressLength(actionEl);
                    break;
                case 'wizard-improve-length':
                    this.improveWizardLength(actionEl);
                    break;
                case 'express-refine':
                    this.enterWizardFromExpress();
                    break;
                case 'mode-wizard':
                case 'mode-express':
                    this.switchMode(action === 'mode-express' ? 'express' : 'wizard');
                    break;
                default:
                    break;
            }
        });

        this.root.addEventListener('change', (e) => {
            if (e.target && (e.target.matches('[data-article-length-mode]') || e.target.matches('[data-article-word-count]'))) {
                this.refreshArticlePlanControls();
            }
            const option = e.target && e.target.getAttribute('data-image-option');
            if (option) {
                this.saveImageOverridesFromPanel(this.currentStep());
            }
        });

        this.root.addEventListener('input', (e) => {
            if (e.target && e.target.matches('[data-testid="idea-input"]')) {
                this.handleTopicInputChange();
            }
            if (e.target && e.target.matches('[data-article-word-count]')) {
                this.refreshArticlePlanControls();
            }
            if (e.target && e.target.matches('[data-testid="prompt-editor"]')) {
                this.promptRunPhase = 'idle';
                this.updatePromptActionState();
            }
        });

        // Keyboard support for the step tablist (arrow keys per WAI-ARIA APG).
        const nav = document.getElementById('step-navigation');
        if (nav) {
            nav.addEventListener('keydown', (e) => {
                if (['ArrowRight', 'ArrowLeft', 'Home', 'End'].indexOf(e.key) === -1) {
                    return;
                }
                e.preventDefault();
                // Locked and hidden chips are not roving-focus stops.
                const chips = Array.from(
                    nav.querySelectorAll('[data-action="nav-step"]:not([hidden]):not([disabled])')
                );
                if (chips.length === 0) {
                    return;
                }
                const current = chips.indexOf(document.activeElement);
                let next = current;
                if (e.key === 'ArrowRight') {
                    next = (current + 1) % chips.length;
                } else if (e.key === 'ArrowLeft') {
                    next = (current - 1 + chips.length) % chips.length;
                } else if (e.key === 'Home') {
                    next = 0;
                } else {
                    next = chips.length - 1;
                }
                chips[next].focus();
            });
        }
    }

    observeState() {
        this.appState.subscribe((newState) => {
            this.renderCostMeter(newState);
            this.renderPersistenceState();
            if (this.currentStep() === 6 || this.currentStep() === 10) {
                this.renderWizardLengthStatus();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Navigation                                                         */
    /* ------------------------------------------------------------------ */

    currentStep() {
        return this.appState.getStateSlice('currentStep') || 1;
    }

    /**
     * Steps at or below the furthest one reached are selectable; the rest are
     * not. Clicking ahead used to land the user on a blank panel with no way
     * to tell that nothing had been generated for it yet.
     *
     * @param {number} step
     * @return {boolean} True when the step may be navigated to.
     */
    isStepUnlocked(step) {
        return step <= this.maxUnlockedStep;
    }

    /**
     * Mark everything up to `step` as reached and repaint the rail.
     *
     * @param {number} step
     */
    unlockThrough(step) {
        const target = Math.min(11, Math.max(1, parseInt(step, 10) || 1));
        if (target > this.maxUnlockedStep) {
            this.maxUnlockedStep = target;
        }
        this.applyStepLocks();
    }

    /** Reflect the gate on the step chips (`.step.disabled` is already styled). */
    applyStepLocks() {
        document.querySelectorAll('#step-navigation [data-action="nav-step"]').forEach((chip) => {
            const chipStep = parseInt(chip.getAttribute('data-step'), 10);
            const locked = !this.isStepUnlocked(chipStep);
            chip.classList.toggle('disabled', locked);
            chip.disabled = locked;
            chip.setAttribute('aria-disabled', locked ? 'true' : 'false');
            if (locked) {
                chip.setAttribute('tabindex', '-1');
            }
        });
    }

    navigateToStep(step) {
        if (!step || step < 1 || step > 11) {
            return;
        }
        if (!this.isStepUnlocked(step)) {
            return; // Not reached yet — the panel would be blank.
        }
        const previousStep = this.currentStep();
        if (previousStep !== step) {
            this.capturePromptDraft(previousStep);
        }
        this.appState.setStateSlice('currentStep', step);

        document.querySelectorAll('[data-step-panel]').forEach((panel) => {
            const isActive = panel.getAttribute('data-step-panel') === String(step);
            panel.classList.toggle('active', isActive);
            panel.hidden = !isActive;
        });

        document.querySelectorAll('#step-navigation [data-action="nav-step"]').forEach((chip) => {
            const chipStep = parseInt(chip.getAttribute('data-step'), 10);
            const isActive = chipStep === step;
            chip.classList.toggle('active', isActive);
            chip.setAttribute('aria-selected', isActive ? 'true' : 'false');
            if (isActive) {
                chip.setAttribute('aria-current', 'step');
                chip.setAttribute('tabindex', '0');
            } else {
                chip.removeAttribute('aria-current');
                chip.setAttribute('tabindex', '-1');
            }
        });

        this.renderProgress(step);
        this.applyStepLocks();

        // Review compiles from state on entry.
        const view = this.registry.get(step);
        if (step === 10 && view) {
            view.renderTyped();
            this.renderWizardLengthStatus();
            this.preparePublishingDetails();
            this.updateReviewActions();
            this.renderPersistenceState();
        }
        if (step === 11) {
            this.updateReviewActions();
            this.renderPersistenceState();
        }
        // Steps with a gallery show every image generated so far, so the
        // step-6 images (and the early background image) are still there on
        // Review and after a reload instead of the gallery restarting empty.
        if (step === 6 || step === 10) {
            this.syncImageStudio(step);
            this.restoreGallery(step);
            // Ensure the editor carries the drop-zone and image-toolbar
            // wiring before the user drags or hovers anything.
            this.editorForStep(step);
            if (step === 6) {
                this.maybeAutoGenerateFeaturedImage();
            }
            this.renderWizardLengthStatus();
        }
        this.loadPromptEditor(step);
        this.refreshCostEstimate(step);
        if (view) {
            view.focusPanel();
        }
    }

    renderProgress(step) {
        const total = 11;
        const pct = Math.round((step / total) * 100);
        const fill = document.getElementById('progress-fill');
        const text = document.getElementById('progress-text');
        const pctEl = document.getElementById('progress-percentage');
        const names = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.stepNames) || {};
        if (fill) {
            fill.style.width = `${pct}%`;
        }
        if (pctEl) {
            pctEl.textContent = `${pct}%`;
        }
        if (text) {
            const name = names[step] || '';
            text.textContent = `Step ${step} of ${total}${name ? ' — ' + name : ''}`;
        }
        const bar = document.querySelector('.progress-bar');
        if (bar) {
            bar.setAttribute('aria-valuenow', String(step));
        }
    }

    /** Next/previous step, hopping over the Q&A step when it is disabled. */
    adjacentStep(step, direction) {
        let target = step + direction;
        if (target === 8 && !this.qnaEnabled()) {
            target += direction;
        }
        return Math.min(11, Math.max(1, target));
    }

    async continueFromStep(step) {
        const view = this.registry.get(step);
        // Never advance without a rendered result/selection (REFACTOR.md §5.1).
        if (view && step !== 10 && view.state === 'error') {
            return;
        }
        if (view && typeof view.getSelectionKey === 'function') {
            try {
                await this.persistSelection(step);
            } catch (error) {
                return; // Error already shown by persistSelection.
            }
        }
        if (step === 5) {
            try {
                await this.persistTaglinePosition();
            } catch (error) {
                return; // Error already shown.
            }
        }
        if (step === 10 && view && typeof view.getSelection === 'function') {
            const visibleReviewEditor = document.querySelector(
                '[data-step-panel="10"].active .ql-editor'
            );
            this.pendingEvaluateHtml = visibleReviewEditor
                ? ReviewStepView.normaliseInPageLinks(visibleReviewEditor.innerHTML)
                : view.getSelection();
            if (this.appState) {
                this.appState.setStateSlice('reviewEditedHtml', this.pendingEvaluateHtml);
            }
        }
        // 2.6 parity: early/parallel image generation from the keyword step.
        this.maybePrefetchImages(step);
        if (step < 11) {
            const next = this.adjacentStep(step, 1);
            // Reaching a step is what unlocks it (the Q&A hop included).
            this.unlockThrough(next);
            this.navigateToStep(next);
            // Auto-kick generation on arrival for generation steps without content.
            const nextView = this.registry.get(next);
            // Evaluate must always rerun after Review: the user may have
            // changed text, links or images since the previous report.
            if (nextView && next !== 10 && (nextView.state === 'idle' || step === 10)) {
                this.generate(next, { append: false });
            }
        }
    }

    skipStep(step) {
        const view = this.registry.get(step);
        if (view && typeof view.skip === 'function') {
            view.skip();
        }
        this.skippedSteps.add(step);
        this.continueFromStep(step);
    }

    /** Contract §3 extension: tagline placement is a conversation setting. */
    async persistTaglinePosition() {
        const conversationId = this.appState.getStateSlice('conversationId');
        const radio = document.querySelector('input[name="above_below_tagline"]:checked');
        if (!conversationId || !radio) {
            return;
        }
        try {
            await this.api.saveSelection(conversationId, 'tagline_position', radio.value);
        } catch (error) {
            const view = this.registry.get(5);
            if (view) {
                view.showError(error, () => this.persistTaglinePosition());
            }
            throw error;
        }
    }

    /**
     * 2.6's flagship "early/parallel image generation": as soon as keywords
     * are chosen (step 2 → 3), fire the first article image asynchronously so
     * the step 6/10 gallery is already populated when the user gets there.
     * Respects the image-generation setting; runs once per article.
     */
    maybePrefetchImages(fromStep) {
        // Silent pre-generation contradicted the owner-approved rule that the
        // exact prompt must be visible and editable before a billable image
        // request. The Body image studio prepares those prompts instead.
        return fromStep;
    }

    /**
     * "Start Again" means a blank sheet. AppState.reset() clears the stored
     * state; everything the user can still SEE has to go with it, or the next
     * article inherits the last one's topic, prompt and compiled body.
     */
    startAgain() {
        const keepMode = this.root && this.root.classList.contains('mode-express-active')
            ? 'express'
            : 'wizard';
        if (this.activeStream) {
            this.activeStream.abort();
            this.activeStream = null;
        }
        this.api.abortAllStreams();
        this.cancelManualImprovement();
        this.disarmImage();
        this.appState.reset();
        this.skippedSteps.clear();
        this.imagesPrefetched = false;
        this.releaseAllGenerationLocks();
        this.stepPrompts.clear();
        this.stepPromptDrafts.clear();
        this.promptRunStep = null;
        this.promptRunPhase = 'idle';
        this.conversationIdea = '';
        this.discardConfirmUntil = 0;
        this.maxUnlockedStep = 1;
        document.querySelectorAll('[data-saved-shortcode-note]').forEach((note) => {
            note.hidden = true;
            note.textContent = '';
        });
        this.clearAuthoredContent();
        this.resetWorkflowViews();
        this.applyStepLocks();
        this.switchMode(keepMode);
        if (keepMode === 'wizard') {
            this.navigateToStep(1);
        }
        this.updateReviewActions();
    }

    /**
     * Return every wizard view to a genuinely untouched state.
     *
     * Hiding the result sections alone left each view's in-memory `state` as
     * `ready`. Continue only auto-generates the next untouched (`idle`) step,
     * so Start Again could navigate to a hidden Keyword result and then skip
     * the request, leaving a completely blank panel. Reset the state machine,
     * selection caches and visible result containers together.
     */
    resetWorkflowViews() {
        document.querySelectorAll('[id$="-options"]').forEach((container) => {
            container.textContent = '';
        });
        for (let step = 1; step <= 11; step += 1) {
            const view = this.registry.get(step);
            if (!view) {
                continue;
            }
            if (Array.isArray(view.items)) {
                view.items = [];
            }
            if (Object.prototype.hasOwnProperty.call(view, 'skipped')) {
                view.skipped = false;
            }
            if (Object.prototype.hasOwnProperty.call(view, 'finalHtml')) {
                view.finalHtml = '';
            }
            view.lastRetryHandler = null;
            if (view.container) {
                view.container.textContent = '';
            }
            if (view.resultsSection) {
                view.resultsSection.classList.add('hidden');
                view.resultsSection.classList.remove('is-refreshing');
            }
            if (typeof view.setState === 'function') {
                view.setState('idle');
            } else {
                view.state = 'idle';
            }
            if (typeof view.setNextEnabled === 'function') {
                view.setNextEnabled(false);
            }
            if (typeof view.showIdle === 'function') {
                view.showIdle();
            }
        }
        const express = this.registry.get('express');
        if (express) {
            express.article = null;
            express.lastRetryHandler = null;
            if (express.topicInput) {
                express.topicInput.value = '';
            }
            if (express.output) {
                express.output.textContent = '';
            }
            if (express.resultsSection) {
                express.resultsSection.classList.add('hidden');
                express.resultsSection.classList.remove('is-refreshing');
            }
            if (typeof express.setState === 'function') {
                express.setState('idle');
            } else {
                express.state = 'idle';
            }
            const refine = express.panel
                ? express.panel.querySelector('[data-action="express-refine"]')
                : null;
            if (refine) {
                refine.hidden = true;
            }
        }
    }

    /** Wipe the previous article out of the inputs and the two Quill editors. */
    clearAuthoredContent() {
        const titles = this.registry.get(1);
        if (titles && titles.topicInput) {
            titles.topicInput.value = '';
        }
        const editor = document.querySelector('[data-testid="prompt-editor"]');
        if (editor) {
            editor.value = '';
            editor.defaultValue = '';
        }
        [6, 10].forEach((step) => {
            const view = this.registry.get(step);
            if (view && view.quill) {
                view.quill.setContents([], 'silent');
            }
        });
        const meta = this.registry.get(9);
        if (meta) {
            if (meta.titleInput) {
                meta.titleInput.value = '';
            }
            if (meta.descriptionInput) {
                meta.descriptionInput.value = '';
            }
            if (typeof meta.updateCounters === 'function') {
                meta.updateCounters();
            }
        }
        document.querySelectorAll('#image-gallery, #review-image-gallery').forEach((gallery) => {
            gallery.textContent = '';
        });
    }

    switchMode(mode) {
        document.querySelectorAll('[data-mode-screen]').forEach((screen) => {
            const isActive = screen.getAttribute('data-mode-screen') === mode;
            screen.hidden = !isActive;
            screen.classList.toggle('active', isActive);
        });
        document.querySelectorAll('[data-action^="mode-"]').forEach((btn) => {
            const btnMode = btn.getAttribute('data-action') === 'mode-express' ? 'express' : 'wizard';
            btn.classList.toggle('active', btnMode === mode);
            btn.setAttribute('aria-pressed', btnMode === mode ? 'true' : 'false');
        });
        // §13.4: the step progress cluster is wizard-only — Express must not
        // show a stuck "Step 1 of 11" readout.
        const progress = document.querySelector('.progress-container');
        if (progress) {
            progress.hidden = mode === 'express';
        }
        if (this.root) {
            this.root.classList.toggle('mode-express-active', mode === 'express');
        }
        this.appState.setStateSlice('mode', mode);
    }

    /**
     * §13.1: hydrate the "Selected model" display in the options rail from
     * the live models endpoint (and the server-localised current model id).
     * No hardcoded fallback model ids anywhere — if the lookup fails the
     * raw configured id (or an honest "default model" label) is shown.
     */
    async hydrateModelDisplay() {
        const el = document.getElementById('active-model-details');
        if (!el) {
            return;
        }
        const configured = (window.ai_scribe && window.ai_scribe.model) || '';
        el.textContent = configured || '…';
        try {
            const data = await this.api.getAvailableModels();
            const models = (data && data.models) || [];
            const match = configured
                ? models.find((m) => m.id === configured)
                : null;
            if (match) {
                const provider = match.provider
                    ? WizardFlowController.providerLabel(match.provider)
                    : '';
                el.textContent = provider ? `${match.label || match.id} · ${provider}` : (match.label || match.id);
            } else if (!configured) {
                const i18n = (window.ai_scribe && window.ai_scribe.i18n) || {};
                el.textContent = i18n.noModelSelected || 'No model selected yet';
            }
        } catch (error) {
            // Endpoint unavailable: leave the configured id visible — never a
            // permanent "Loading…" and never a hardcoded fallback.
            if (!configured) {
                el.textContent = '—';
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Generation (binding contract: docs/API_CONTRACT.md)                */
    /* ------------------------------------------------------------------ */

    topicValue() {
        const titles = this.registry.get(1);
        return titles && typeof titles.getTopic === 'function' ? titles.getTopic() : '';
    }

    normaliseTopic(value) {
        return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
    }

    /** Stop presenting a recovered article's prompt as current after its topic changes. */
    handleTopicInputChange() {
        if (!this.appState.getStateSlice('conversationId') || !this.conversationIdea) {
            return;
        }
        if (this.normaliseTopic(this.topicValue()) === this.normaliseTopic(this.conversationIdea)) {
            return;
        }
        this.stepPrompts.delete(1);
        this.stepPromptDrafts.delete(1);
        if (this.currentStep() === 1) {
            this.loadPromptEditor(1);
        }
    }

    promptOverride() {
        const editor = document.querySelector('[data-testid="prompt-editor"]');
        return editor && editor.value !== editor.defaultValue ? editor.value : '';
    }

    promptActionButton() {
        return document.querySelector('[data-action="run-amended-prompt"]');
    }

    promptActionStatus() {
        return document.querySelector('[data-testid="prompt-run-status"]');
    }

    /**
     * Make the prompt editor's consequence explicit. The old UI placed its
     * rerun action beside the textarea; keeping the action in that location
     * avoids asking users to infer that a distant Regenerate button consumes
     * the edit.
     */
    updatePromptActionState() {
        const editor = this.promptEditor();
        const button = this.promptActionButton();
        const status = this.promptActionStatus();
        if (!editor || !button || !status) {
            return;
        }

        const label = button.querySelector('[data-prompt-run-label]');
        const automatic = editor.disabled || this.noPromptSteps().indexOf(this.currentStep()) !== -1;
        const dirty = !automatic && this.promptOverride().trim() !== '';
        const running = this.promptRunPhase === 'running' && this.promptRunStep === this.currentStep();
        const used = this.promptRunPhase === 'used' && this.promptRunStep === this.currentStep();
        const failed = this.promptRunPhase === 'error' && this.promptRunStep === this.currentStep();

        button.hidden = automatic;
        status.hidden = automatic;
        button.disabled = !dirty || running || this.pendingSteps.has(this.currentStep());
        if (label) {
            label.textContent = running ? 'Running amended prompt…' : 'Run amended prompt';
        }
        button.classList.toggle('is-busy', running);
        button.setAttribute('aria-busy', running ? 'true' : 'false');
        status.classList.toggle('is-success', used);
        status.classList.toggle('is-error', failed);

        if (running) {
            status.textContent = 'Running your amended prompt for this step…';
        } else if (used) {
            status.textContent = 'Amended prompt used.';
        } else if (failed) {
            status.textContent = 'The amended prompt could not be run. Try again.';
        } else if (dirty) {
            status.textContent = 'Your amended prompt is ready to run.';
        } else {
            status.textContent = 'Edit the prompt to enable this button.';
        }
    }

    runAmendedPrompt(step) {
        if (!this.promptOverride().trim() || this.pendingSteps.has(step)) {
            return;
        }
        this.promptRunStep = step;
        this.promptRunPhase = 'running';
        this.updatePromptActionState();
        // An amended prompt replaces the current result. "Generate more" is
        // the separate, explicit append action for choice steps.
        this.generate(step, { append: false });
    }

    markPromptRunFailed(step) {
        if (this.promptRunPhase !== 'running' || this.promptRunStep !== step) {
            return;
        }
        this.promptRunPhase = 'error';
        this.updatePromptActionState();
    }

    stepExtras(options = {}, step = this.currentStep()) {
        const extras = { prompt_override: this.promptOverride() };
        if (options.append) {
            extras.regenerate = '1'; // Contract §2: previous results kept.
        }
        // 2.6.2 skip-aware prompt rewriting (server-side, contract §2 flags).
        if (this.skippedSteps.has(2)) {
            extras.skip_keywords = '1';
        }
        if (this.skippedSteps.has(5)) {
            extras.skip_tagline = '1';
        }
        // Evaluate the exact final Review editor HTML, including the user's
        // last edits and every inserted/auto-featured image. The server
        // sanitises this again, stores it, and derives structural facts from
        // this value instead of asking the model to remember an older draft.
        if (step === 11) {
            const review = this.registry.get(10);
            // Read the exact visible Review DOM at click time. This avoids
            // any editor export/cache path dropping an image that the user
            // can plainly see in Review.
            const reviewEditor = document.querySelector('[data-step-panel="10"] .ql-editor');
            extras.content_html = this.pendingEvaluateHtml
                || (reviewEditor
                    ? ReviewStepView.normaliseInPageLinks(reviewEditor.innerHTML)
                    : (review && typeof review.getSelection === 'function' ? review.getSelection() : ''));
        }
        return extras;
    }

    /**
     * Contract §4 recovery: on page load with a saved conversation, re-fetch
     * server state and re-render every stored step payload — a reload
     * mid-conversation loses nothing and re-bills nothing. When the
     * conversation is gone (or the fetch fails), local state is cleaned and
     * the wizard starts fresh at step 1 — stale local state must never make
     * a Generate click bill the wrong step.
     *
     * Re-rendering the payloads is only half of recovery. AppState.stepData
     * is what the Review step compiles from, and the user's choices live in
     * the conversation's selections, so both are put back here: without them
     * every card came back unselected, Continue stayed disabled, and Review
     * assembled an empty article that Publish was still happy to post.
     *
     * @returns {Promise<void>}
     */
    async hydrateFromServer() {
        const conversationId = this.appState.getStateSlice('conversationId');
        const savedStep = this.currentStep();
        if (!conversationId) {
            this.maxUnlockedStep = 1;
            this.applyStepLocks();
            this.navigateToStep(1);
            return;
        }
        try {
            const state = await this.api.getState(conversationId);
            // Generate may have started a new article while recovery was in
            // flight. Never repaint the UI with a response for the superseded
            // conversation.
            if (this.appState.getStateSlice('conversationId') !== conversationId) {
                return;
            }
            const steps = (state && state.steps) || {};
            const settings = (state && state.settings) || {};
            let furthestStep = 1;

            this.conversationIdea = settings.idea
                ? String(settings.idea).trim()
                : '';
            // The conversation owns the length contract used to generate its
            // article. Restore that contract before Review is repainted so a
            // reload cannot silently recalculate the count card from a newer
            // or different global default.
            this.restoreArticleLengthSettings(settings);
            const titles = this.registry.get(1);
            if (titles && titles.topicInput && !titles.topicInput.value.trim() && this.conversationIdea) {
                titles.topicInput.value = this.conversationIdea;
            }

            Object.keys(steps).forEach((key) => {
                const stepNumber = parseInt(key, 10);
                const stepInfo = steps[key];
                const view = this.registry.get(stepNumber);
                if (!view || !stepInfo || stepInfo.status !== 'complete' || !stepInfo.parsed) {
                    return;
                }
                if (typeof view.renderTyped === 'function') {
                    view.renderTyped(stepInfo.parsed, { fromState: true, qualityPlan: stepInfo.quality || null });
                }
                if (stepInfo.prompt_used) {
                    this.stepPrompts.set(stepNumber, stepInfo.prompt_used);
                }
                // Long-form steps carry their HTML in stepData, which the
                // Review compiler reads and renderTyped alone never writes.
                if (typeof view.persist === 'function') {
                    view.persist(null);
                }
                furthestStep = Math.max(furthestStep, stepNumber);
            });

            this.restoreSelections(state);

            // Restore only a server-confirmed WordPress destination. This is
            // independent of the tab cache and survives a page reload. Legacy
            // conversations with only post_id deliberately remain unclaimed:
            // without the exact saved HTML/status we cannot say it is current.
            if (parseInt(settings.post_id, 10) > 0 && settings.post_html && settings.post_status) {
                const saved = this.persistenceState();
                this.appState.setStateSlice('persistence', Object.assign({}, saved, {
                    post: {
                        type: 'post',
                        id: parseInt(settings.post_id, 10),
                        status: settings.post_status,
                        editLink: settings.post_edit_link || '',
                        html: settings.post_html
                    }
                }));
            }

            if (state.cost && typeof state.cost.running_total_usd === 'number') {
                this.appState.setStateSlice('cost', { running_total_usd: state.cost.running_total_usd });
            }

            const landing = Math.min(11, Math.max(1, savedStep));
            this.unlockThrough(Math.max(furthestStep, landing));
            this.navigateToStep(landing);
        } catch (error) {
            if (this.appState.getStateSlice('conversationId') !== conversationId) {
                return;
            }
            // Conversation not found (or unreachable): reset local state so a
            // Generate click can never target a phantom step.
            this.appState.setStateSlice('conversationId', null);
            this.appState.setStateSlice('currentStep', 1);
            this.maxUnlockedStep = 1;
            this.applyStepLocks();
            this.navigateToStep(1);
        }
    }

    /**
     * Re-apply the server-owned per-conversation article-length contract to
     * both entry screens. The controls are the client planner's source of
     * truth, including while Review is restored after a page reload.
     *
     * @param {Object} settings Conversation settings returned by getState().
     */
    restoreArticleLengthSettings(settings) {
        const validModes = ['auto', 'concise', 'standard', 'in_depth', 'custom'];
        const mode = validModes.indexOf(settings && settings.article_length_mode) !== -1
            ? settings.article_length_mode
            : '';
        if (!mode) return;
        const target = Math.max(400, Math.min(8000,
            parseInt(settings.article_word_count, 10) || 1800));

        document.querySelectorAll('[data-step-panel="1"], [data-step-panel="express"]').forEach((panel) => {
            const modeField = panel.querySelector('[data-article-length-mode]');
            const countField = panel.querySelector('[data-article-word-count]');
            if (!modeField || !Array.from(modeField.options || []).some((option) => option.value === mode)) {
                return;
            }
            modeField.value = mode;
            if (countField) countField.value = String(target);
        });
        this.refreshArticlePlanControls();
    }

    /**
     * Re-apply every stored decision from the conversation: the selected
     * cards, the Q&A set, the edited SEO meta and the tagline placement.
     *
     * @param {Object} state Payload from ai_scribe_get_state.
     */
    restoreSelections(state) {
        const selections = (state && state.selections) || {};
        const settings = (state && state.settings) || {};

        this.applyStoredCardSelection(1, selections.title);
        this.applyStoredCardSelection(2, selections.keywords);
        this.applyStoredCardSelection(3, selections.outline);
        this.applyStoredCardSelection(5, selections.tagline);
        this.applyStoredQnaSelection(selections.qna);
        this.applyStoredMeta(selections.meta);
        this.applyStoredPlacement(settings);
    }

    /**
     * Re-select the cards matching a stored choice, then let the view
     * republish it to AppState through its own persistSelection().
     *
     * @param {number}       step
     * @param {string|Array} selection Stored value (single or multi).
     */
    applyStoredCardSelection(step, selection) {
        const view = this.registry.get(step);
        if (!view || !view.container || !Array.isArray(view.items)
            || selection === undefined || selection === null) {
            return;
        }
        const wanted = (Array.isArray(selection) ? selection : [selection])
            .map((value) => WizardFlowController.normaliseOption(value))
            .filter((value) => value !== '');
        if (wanted.length === 0) {
            return;
        }
        // A single-select step keeps one card checked even when the generated
        // options repeat a value — two lit radios would be a lie about state.
        const singleSelect = view.multiSelect !== true;
        let alreadyChosen = false;
        view.container.querySelectorAll('.option-card, .keyword-card').forEach((card) => {
            const index = parseInt(card.getAttribute('data-index'), 10);
            const item = WizardFlowController.normaliseOption(view.items[index]);
            let chosen = item !== '' && wanted.indexOf(item) !== -1;
            if (chosen && singleSelect) {
                chosen = !alreadyChosen;
                alreadyChosen = true;
            }
            card.classList.toggle('selected', chosen);
            card.setAttribute('aria-checked', chosen ? 'true' : 'false');
            const check = card.querySelector('i[data-lucide="check"]');
            if (check) {
                check.style.display = chosen ? 'block' : 'none';
            }
        });
        view.setNextEnabled(view.hasSelection());
        if (typeof view.persistSelection === 'function') {
            view.persistSelection();
        }
    }

    /**
     * Q&A items are individually includable, so the stored selection is the
     * list of kept items — the view re-ticks the matching ones. An absent
     * selection leaves the render defaults (everything included) alone.
     */
    applyStoredQnaSelection(selection) {
        const view = this.registry.get(8);
        if (!view || selection === undefined || selection === null) {
            return;
        }
        if (typeof view.applyStoredSelection === 'function') {
            view.applyStoredSelection(Array.isArray(selection) ? selection : []);
        }
        view.setNextEnabled(true); // Skippable: Continue is always available.
        if (typeof view.persistSelection === 'function') {
            view.persistSelection();
        }
    }

    /**
     * The stored meta is what the user EDITED, so it wins over the values
     * step 9's renderTyped() just put back from the generated payload.
     *
     * @param {{title: string, description: string}} meta
     */
    applyStoredMeta(meta) {
        const view = this.registry.get(9);
        if (!view || !meta || typeof meta !== 'object') {
            return;
        }
        if (view.titleInput && typeof meta.title === 'string') {
            view.titleInput.value = WizardFlowController.decodeEntities(meta.title);
        }
        if (view.descriptionInput && typeof meta.description === 'string') {
            view.descriptionInput.value = WizardFlowController.decodeEntities(meta.description);
        }
        if (typeof view.updateCounters === 'function') {
            view.updateCounters();
        }
        if (typeof view.updateGuidance === 'function') {
            view.updateGuidance();
        }
        if (typeof view.hasContent === 'function') {
            view.setNextEnabled(view.hasContent());
        }
        if (typeof view.persist === 'function') {
            view.persist();
        }
    }

    /** Tagline placement is a conversation setting, not a selection. */
    applyStoredPlacement(settings) {
        const position = settings && settings.tagline_position === 'above' ? 'above' : 'below';
        const radio = document.querySelector(`input[name="above_below_tagline"][value="${position}"]`);
        if (radio) {
            radio.checked = true;
        }
    }

    /**
     * Card text against a stored selection. Keyword cards carry the legacy
     * "keyword | volume | competition" display string while the saved value
     * is the bare keyword, and everything server-side has been through
     * wp_kses_post, so entities are decoded on both sides before comparing.
     *
     * @param {*} value
     * @return {string}
     */
    static normaliseOption(value) {
        if (value && typeof value === 'object' && typeof value.keyword === 'string') {
            value = value.keyword;
        }
        if (typeof value !== 'string') {
            return '';
        }
        return WizardFlowController.decodeEntities(value.split(' | ')[0]).trim();
    }

    /**
     * Decode the handful of entities wp_kses_post introduces. Deliberately a
     * string pass, not an innerHTML round-trip — no markup sink for values
     * that came back over the wire.
     *
     * @param {string} value
     * @return {string}
     */
    static decodeEntities(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#0?39;/g, '\'')
            .replace(/&#8217;/g, '’')
            .replace(/&#8216;/g, '‘')
            .replace(/&#8211;/g, '–')
            .replace(/&#8212;/g, '—')
            .replace(/&amp;/g, '&');
    }

    /** One server-side conversation per article; a changed topic starts a new article. */
    async ensureConversation(step = this.currentStep()) {
        let conversationId = this.appState.getStateSlice('conversationId');
        const topic = this.topicValue();
        if (conversationId && step === 1) {
            // Generate can be pressed before asynchronous recovery finishes.
            // Resolve the server idea here as a race-safe fallback.
            if (!this.conversationIdea) {
                const state = await this.api.getState(conversationId);
                this.conversationIdea = state && state.settings && state.settings.idea
                    ? String(state.settings.idea).trim()
                    : '';
            }
            if (this.normaliseTopic(topic) !== this.normaliseTopic(this.conversationIdea)) {
                this.resetForNewTopic(topic);
                conversationId = null;
            }
        }
        if (conversationId) {
            return conversationId;
        }
        const data = await this.api.startConversation(Object.assign({ idea: topic }, this.articleLengthFields('wizard')));
        conversationId = data.conversation_id;
        this.conversationIdea = topic;
        this.appState.setStateSlice('conversationId', conversationId);
        return conversationId;
    }

    /** Clear every derived value while preserving the topic the user just entered. */
    resetForNewTopic(topic) {
        this.cancelManualImprovement();
        this.appState.reset();
        this.skippedSteps.clear();
        this.imagesPrefetched = false;
        this.stepPrompts.clear();
        this.stepPromptDrafts.clear();
        this.conversationIdea = '';
        this.maxUnlockedStep = 1;
        this.clearAuthoredContent();
        this.resetWorkflowViews();
        const titles = this.registry.get(1);
        if (titles && titles.topicInput) {
            titles.topicInput.value = topic;
        }
        this.applyStepLocks();
        this.navigateToStep(1);
        this.updateReviewActions();
    }

    /**
     * Every control that would start a second billed run for a step: its
     * Generate, its Generate More / Regenerate, and the Retry in its error
     * box. Three presses during one five-second generation used to buy three
     * generations with nothing in the UI to say so (S10-05b).
     *
     * @param {number|string} step Step number, or 'express'.
     * @return {Array<HTMLElement>}
     */
    generationControls(step) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        if (!panel) {
            return [];
        }
        return Array.from(panel.querySelectorAll(
            '[data-action="generate"], [data-action="generate-more"],'
            + ' [data-action="express-generate"], [data-testid="step-retry"]'
        ));
    }

    /**
     * @param {number|string} step
     * @param {boolean}       busy
     */
    setGenerationBusy(step, busy) {
        if (busy) {
            this.pendingSteps.add(step);
        } else {
            this.pendingSteps.delete(step);
        }
        this.generationControls(step).forEach((control) => {
            WizardFlowController.setButtonBusy(control, busy);
        });
        if (step === this.currentStep()) {
            this.updatePromptActionState();
        }
    }

    /**
     * The one async-action pattern for every button: disabled with a spinner
     * while the request runs, so a press is always visibly acknowledged.
     *
     * @param {HTMLElement} button
     * @param {boolean}     busy
     */
    static setButtonBusy(button, busy) {
        if (!button) {
            return;
        }
        button.disabled = busy;
        button.classList.toggle('is-busy', busy);
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    /**
     * Visible toast for actions whose only feedback used to be a
     * visually-hidden live region — success must be seen, not just read out.
     *
     * @param {string} message
     * @param {string} type success | error | info | warning
     */
    notify(message, type = 'success') {
        if (window.aiScribeApp && typeof window.aiScribeApp.showNotification === 'function') {
            window.aiScribeApp.showNotification(message, type);
        }
    }

    /** Drop every in-flight lock (Start Again aborts the requests too). */
    releaseAllGenerationLocks() {
        Array.from(this.pendingSteps).forEach((step) => this.setGenerationBusy(step, false));
        this.pendingSteps.clear();
    }

    /** Invalidate one-call improvements so a late response cannot repaint a reset article. */
    cancelManualImprovement() {
        this.manualImprovementEpoch += 1;
        if (this.improvementProgressTimer) {
            window.clearInterval(this.improvementProgressTimer);
            this.improvementProgressTimer = null;
        }
        this.improvementProgressStartedAt = 0;
        this.pendingSteps.delete('wizard-improve-length');
        this.pendingSteps.delete('express-improve-length');
        document.querySelectorAll('[data-improve-length-progress]').forEach((progress) => {
            progress.hidden = true;
        });
        document.querySelectorAll('[data-action="wizard-improve-length"], [data-action="express-improve-length"]').forEach((button) => {
            WizardFlowController.setButtonBusy(button, false);
        });
    }

    async generate(step, options = {}) {
        const view = this.registry.get(step);
        if (!view) {
            this.markPromptRunFailed(step);
            return;
        }
        if (step === 1 && !this.topicValue()) {
            this.markPromptRunFailed(step);
            view.showError(new Error(view.t('topicRequired') === 'topicRequired'
                ? 'Enter a topic or idea first.'
                : view.t('topicRequired')), null);
            return;
        }
        if (this.pendingSteps.has(step)) {
            return; // Already generating this step — one press, one run.
        }

        // The nearby action is the clearest route, but an existing step-level
        // Regenerate button must report the same amended-prompt state if the
        // user chooses it after editing the textarea.
        if (this.promptOverride().trim() && this.promptRunPhase !== 'running') {
            this.promptRunStep = step;
            this.promptRunPhase = 'running';
            this.updatePromptActionState();
        }

        // Generating on a previously skipped step un-skips it.
        this.skippedSteps.delete(step);

        let conversationId;
        try {
            this.setGenerationBusy(step, true);
            view.showLoading();
            conversationId = await this.ensureConversation(step);
        } catch (error) {
            this.markPromptRunFailed(step);
            this.setGenerationBusy(step, false);
            view.showError(error, () => this.generate(step, options));
            return;
        }

        if (StepViewRegistry.isStreamingStep(step)) {
            this.generateStreaming(conversationId, step, view, options);
        } else {
            view.updateProgressStage('waiting');
            this.generateStructured(conversationId, step, view, options);
        }
    }

    async generateStructured(conversationId, step, view, options) {
        try {
            const data = await this.api.runStep(conversationId, step, this.stepExtras(options, step));
            view.updateProgressStage('displaying');
            this.recordResult(data);
            view.renderTyped(data.parsed || {}, { append: options.append === true });
            this.updatePromptEditor(data.prompt_used, step);
        } catch (error) {
            this.markPromptRunFailed(step);
            view.showError(error, () => this.generate(step, options));
        } finally {
            this.setGenerationBusy(step, false);
            this.refreshCostEstimate();
        }
    }

    async optimiseMetadata() {
        const view = this.registry.get(9);
        const conversationId = this.appState.getStateSlice('conversationId');
        if (!view || !conversationId) return;
        const current = view.getSelection();
        view.showOptimisePending(true);
        try {
            const data = await this.api.optimiseMeta(conversationId, current.title, current.description);
            this.recordResult(data);
            view.showOptimiseSuggestion(data.meta || {});
            this.notify('Metadata suggestion ready to review.', 'success');
        } catch (error) {
            view.showOptimiseError(error);
            this.notify(error.message || 'Metadata could not be optimised.', 'error');
        }
    }

    generateStreaming(conversationId, step, view, options) {
        if (this.activeStream) {
            this.activeStream.abort();
        }
        const handlers = view.streamHandlers({
            onRetry: () => this.generate(step, options),
            onDone: (data) => {
                this.recordResult(data);
                this.updatePromptEditor(data && data.prompt_used, step);
                this.activeStream = null;
                if (step === 6) {
                    this.maybeAutoGenerateFeaturedImage();
                }
            }
        });
        view.updateProgressStage('waiting');
        const stream = this.api.streamStep(conversationId, step, this.stepExtras(options, step), handlers);
        this.activeStream = stream;
        // Success, failure or abort — the lock is released exactly once.
        stream.finished.then(
            () => {
                this.setGenerationBusy(step, false);
                this.refreshCostEstimate();
            },
            () => {
                this.markPromptRunFailed(step);
                this.setGenerationBusy(step, false);
            }
        );
    }

    recordResult(data) {
        if (!data) {
            return;
        }
        if (data.conversation_id) {
            this.appState.setStateSlice('conversationId', data.conversation_id);
        }
        if (data.cost) {
            this.appState.setStateSlice('cost', data.cost);
        }
    }

    promptEditor() {
        return document.querySelector('[data-testid="prompt-editor"]');
    }

    /**
     * Record the prompt a step ran with and show it (§ Notes). The run has
     * consumed any override that was in the box, so the draft is dropped.
     *
     * @param {string} promptUsed Assembled prompt returned by the engine.
     * @param {number} step       Step the prompt belongs to.
     */
    updatePromptEditor(promptUsed, step) {
        const target = step || this.currentStep();
        if (typeof promptUsed !== 'string' || promptUsed === '') {
            return;
        }
        this.stepPrompts.set(target, promptUsed);
        this.stepPromptDrafts.delete(target);
        if (target === this.currentStep()) {
            this.loadPromptEditor(target);
        }
        if (this.promptRunPhase === 'running' && this.promptRunStep === target) {
            this.promptRunPhase = 'used';
            this.updatePromptActionState();
        }
    }

    /** Keep an unsent override so leaving a step and coming back keeps it. */
    capturePromptDraft(step) {
        const editor = this.promptEditor();
        if (!editor || !step) {
            return;
        }
        if (editor.value !== editor.defaultValue) {
            this.stepPromptDrafts.set(step, editor.value);
        } else {
            this.stepPromptDrafts.delete(step);
        }
    }

    /**
     * Steps whose prompt box must say "automatic" rather than invite an edit.
     * The server can extend the list (window.ai_scribe.noPromptSteps).
     *
     * @return {Array<number>}
     */
    noPromptSteps() {
        const fromServer = window.ai_scribe && window.ai_scribe.noPromptSteps;
        if (Array.isArray(fromServer) && fromServer.length) {
            return fromServer.map((n) => parseInt(n, 10)).filter((n) => !Number.isNaN(n));
        }
        return WizardFlowController.NO_PROMPT_STEPS;
    }

    /**
     * Bind the prompt box to ONE step. It used to keep the previous step's
     * prompt whenever the new step had not run yet, so an edit to that stale
     * text was sent as an override for the wrong step. Automatic steps get an
     * explicit "no prompt to edit" state instead of the editable placeholder.
     *
     * @param {number} step
     */
    loadPromptEditor(step) {
        const editor = this.promptEditor();
        if (!editor) {
            return;
        }
        if (this.defaultPromptPlaceholder === undefined) {
            this.defaultPromptPlaceholder = editor.placeholder || '';
        }
        const i18n = (window.ai_scribe && window.ai_scribe.i18n) || {};
        if (this.noPromptSteps().indexOf(step) !== -1) {
            editor.value = '';
            editor.defaultValue = '';
            editor.disabled = true;
            editor.placeholder = i18n.automaticStep
                || 'This step is automatic. There is no prompt to edit.';
            this.updatePromptActionState();
            return;
        }
        editor.disabled = false;
        editor.placeholder = this.defaultPromptPlaceholder;
        const assembled = this.stepPrompts.get(step) || '';
        editor.defaultValue = assembled;
        editor.value = this.stepPromptDrafts.has(step)
            ? this.stepPromptDrafts.get(step)
            : assembled;
        this.updatePromptActionState();
    }

    /**
     * Contract §6 pre-flight estimate: what this step is about to cost,
     * shown BEFORE the user commits to the run. Purely an aid — a failed or
     * superseded estimate hides the readout and never blocks generation.
     *
     * @param {number} step Defaults to the current step.
     * @returns {Promise<void>}
     */
    async refreshCostEstimate(step) {
        const value = document.getElementById('header-estimate-cost');
        if (!value) {
            return;
        }
        const target = step || this.currentStep();
        if (target === 10) {
            value.textContent = WizardFlowController.costPlaceholder(); // Review generates nothing.
            return;
        }
        const token = this.estimateToken + 1;
        this.estimateToken = token;
        if (this.estimateRequest) {
            this.estimateRequest.abort();
        }
        const controller = new AbortController();
        this.estimateRequest = controller;
        try {
            const data = await this.api.estimateCost(
                {
                    conversation_id: this.appState.getStateSlice('conversationId') || 0,
                    step: target
                },
                { signal: controller.signal }
            );
            if (token !== this.estimateToken) {
                return; // A later step won the race.
            }
            const usd = data && data.total && typeof data.total.usd === 'number'
                ? data.total.usd
                : null;
            value.textContent = usd === null ? WizardFlowController.costPlaceholder() : `$${usd.toFixed(4)}`;
        } catch (error) {
            // An estimate is an aid, never a blocker.
            if (token === this.estimateToken) {
                value.textContent = WizardFlowController.costPlaceholder();
            }
        }
    }

    /** Contract §3: persist the user's choice before advancing. */
    async persistSelection(step) {
        const view = this.registry.get(step);
        if (!view || typeof view.getSelectionKey !== 'function') {
            return;
        }
        const conversationId = this.appState.getStateSlice('conversationId');
        const selection = view.getSelection();
        if (!conversationId || selection === null || selection === undefined) {
            return;
        }
        try {
            await this.api.saveSelection(conversationId, view.getSelectionKey(), selection);
        } catch (error) {
            // Selection persistence failing must not lose local state; show it.
            view.showError(error, () => this.persistSelection(step));
            throw error;
        }
    }

    /** Honest empty state for the cost meters: words, never a bare dash. */
    static costPlaceholder() {
        const i18n = (window.ai_scribe && window.ai_scribe.i18n) || {};
        return i18n.noCostData || 'No data yet';
    }

    renderCostMeter(state) {
        const cost = (state && state.cost) || {};
        const totalEl = document.getElementById('header-total-cost');
        const currentEl = document.getElementById('header-current-cost');
        const format = (value) => (typeof value === 'number'
            ? `$${value.toFixed(4)}`
            : WizardFlowController.costPlaceholder());
        if (totalEl) {
            // Contract §2/§5 cost keys: running_total_usd is the article total.
            totalEl.textContent = format(cost.running_total_usd);
        }
        if (currentEl) {
            const step = typeof cost.actual_usd === 'number' ? cost.actual_usd : cost.estimated_usd;
            currentEl.textContent = format(step);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Express mode                                                       */
    /* ------------------------------------------------------------------ */

    async expressGenerate() {
        const view = this.registry.get('express');
        if (!view) {
            return;
        }
        const topic = view.getTopic();
        if (!topic) {
            view.showError(new Error('Enter a topic to generate the article.'), null);
            return;
        }
        if (this.pendingSteps.has('express')) {
            return; // A whole-article run is already in flight.
        }
        this.setGenerationBusy('express', true);
        view.showLoading();
        try {
            // Contract §5: plain JSON POST; article persisted server-side.
            view.updateProgressStage('waiting');
            const data = await this.api.runExpress(Object.assign({ idea: topic }, this.articleLengthFields('express')));
            view.updateProgressStage('displaying');
            this.recordResult(data);
            view.renderArticle(data);
            // A newly generated Express article is the current save source.
            // This explicit authority survives tab switches; `mode` alone is
            // not evidence that an older Express preview is still current.
            if (this.appState && typeof this.appState.setStateSlice === 'function') {
                this.appState.setStateSlice('articleSaveAuthority', 'express');
            }
            if (this.root) {
                this.updateReviewActions();
                this.renderPersistenceState();
            }
            const refineBtn = document.querySelector('[data-action="express-refine"]');
            if (refineBtn) {
                refineBtn.hidden = false;
            }
        } catch (error) {
            view.showError(error, () => this.expressGenerate());
        } finally {
            this.setGenerationBusy('express', false);
        }
    }

    /**
     * Manually extend a retained Express draft. This is deliberately one
     * request per press: the server works from its persisted article and does
     * not replace that article unless the improved response is valid.
     */
    async improveExpressLength(button) {
        const view = this.registry.get('express');
        const conversationId = this.appState.getStateSlice('conversationId');
        if (this.appState.getStateSlice('articleSaveAuthority') === 'review') {
            if (view) {
                view.setImprovementState(
                    'error',
                    'This article has been refined in the Wizard. Return to Review so the newer edited version is not replaced.'
                );
            }
            return;
        }
        if (!view || !conversationId || !view.getArticle()) {
            if (view) view.setImprovementState('error', 'Generate an Express article before improving its length.');
            return;
        }
        if (this.pendingSteps.has('express-improve-length')) return;

        this.pendingSteps.add('express-improve-length');
        const requestEpoch = ++this.manualImprovementEpoch;
        WizardFlowController.setButtonBusy(button, true);
        view.setImprovementState('loading');
        try {
            const data = await this.api.improveExpressLength(conversationId);
            if (requestEpoch !== this.manualImprovementEpoch) return;
            this.recordResult(data);
            view.renderArticle(data, { preservePersistence: true });
            view.setImprovementState('success', data && data.improvement && data.improvement.message
                ? String(data.improvement.message)
                : 'The draft was extended and the count has been checked again.');
            this.updateReviewActions();
            this.renderPersistenceState();
        } catch (error) {
            if (requestEpoch !== this.manualImprovementEpoch) return;
            const message = error && error.message
                ? `The improvement could not finish: ${error.message} Your existing draft is unchanged.`
                : 'The improvement could not finish. Your existing draft is unchanged.';
            view.setImprovementState('error', message);
            this.notify(message, 'error');
        } finally {
            if (requestEpoch === this.manualImprovementEpoch) {
                this.pendingSteps.delete('express-improve-length');
                WizardFlowController.setButtonBusy(button, false);
            }
        }
    }

    /** Count the same rendered whitespace tokens as the PHP article planner. */
    static visibleWordCount(html) {
        const host = document.createElement('div');
        host.innerHTML = typeof html === 'string' ? html : '';
        host.querySelectorAll('br, hr').forEach((node) => node.replaceWith(document.createTextNode(' ')));
        host.querySelectorAll('address, article, aside, blockquote, div, figcaption, figure, footer, h1, h2, h3, h4, h5, h6, header, li, main, nav, ol, p, pre, section, table, td, th, tr, ul')
            .forEach((node) => node.appendChild(document.createTextNode(' ')));
        const text = (host.textContent || '')
            .replace(/[\u00a0\u202f]/g, ' ').replace(/\s+/gu, ' ').trim();
        if (!text) return 0;
        return text.split(/\s+/u).filter((token) => /[\p{L}\p{N}]/u.test(token)).length;
    }

    wizardArticlePlan(bodyOnly = false) {
        const stepData = this.appState.getStateSlice('stepData') || {};
        if (bodyOnly && stepData[6] && stepData[6].qualityPlan) {
            const quality = stepData[6].qualityPlan;
            return {
                target_words: Number(quality.target_words),
                minimum_words: Number(quality.minimum_words),
                maximum_words: Number(quality.maximum_words),
                complete_target_words: Number(quality.complete_target_words),
                non_body_target_words: Number(quality.non_body_target_words)
            };
        }
        const globalSettings = (window.ai_scribe && window.ai_scribe.contentSettings) || {};
        const local = this.articleLengthFields('wizard');
        const mode = local.article_length_mode || globalSettings.article_length_mode || 'auto';
        const outline = stepData[3] && Array.isArray(stepData[3].selection) ? stepData[3].selection : [];
        const keywords = stepData[2] && Array.isArray(stepData[2].selection) ? stepData[2].selection : [];
        const fixed = { concise: 950, standard: 1800, in_depth: 2800 };
        let target = fixed[mode];
        if (mode === 'custom') {
            target = Math.max(400, Math.min(8000,
                Number(local.article_word_count || globalSettings.article_word_count) || 1800));
        }
        if (!target) {
            const idea = String(this.topicValue() || '').toLowerCase();
            const complex = /\b(guide|tutorial|strategy|comparison|versus|vs\.?|technical|complete|comprehensive|framework|implementation|audit|research|explained)\b/i.test(idea);
            target = 320 + Math.max(1, outline.length) * 245
                + (this.qnaEnabled() ? 260 : 0) + Math.max(0, keywords.length - 1) * 55
                + (complex ? 280 : 0);
            target = Math.max(1200, Math.min(4200, Math.round(target / 50) * 50));
        }
        const tolerance = mode === 'custom' ? 0.125 : 0.15;
        const completePlan = {
            target_words: target,
            minimum_words: Math.floor(target * (1 - tolerance)),
            maximum_words: Math.ceil(target * (1 + tolerance))
        };
        if (!bodyOnly) return completePlan;
        const reserved = Math.round(target * 0.08)
            + Math.round(target * 0.08)
            + (this.qnaEnabled() ? Math.round(target * 0.14) : 0)
            + Math.round(target * 0.02);
        const bodyTarget = Math.max(240, target - reserved);
        return {
            target_words: bodyTarget,
            minimum_words: Math.max(200, Math.floor(bodyTarget * (1 - tolerance))),
            maximum_words: Math.ceil(bodyTarget * 1.15),
            complete_target_words: target,
            non_body_target_words: reserved
        };
    }

    renderWizardLengthStatus(overridePlan = null) {
        const step = this.currentStep();
        if (step !== 6 && step !== 10) return;
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const status = panel && panel.querySelector('[data-wizard-target-status]');
        const view = this.registry.get(step);
        if (!status || !view || typeof view.getSelection !== 'function') return;
        const html = view.getSelection() || '';
        const words = WizardFlowController.visibleWordCount(html);
        const plan = overridePlan || this.wizardArticlePlan(step === 6);
        const target = Number(plan && plan.target_words);
        const min = Number(plan && plan.minimum_words);
        const max = Number(plan && plan.maximum_words);
        if (!words || !target || !min || !max) {
            status.hidden = true;
            return;
        }
        const heading = status.querySelector('[data-target-status-heading]');
        const detail = status.querySelector('[data-target-status-detail]');
        const bar = status.querySelector('[data-target-status-bar]');
        const action = status.querySelector('[data-target-status-action]');
        const inRange = words >= min && words <= max;
        const difference = Math.abs(target - words);
        const relation = words === target ? 'Selected target reached.'
            : `${difference.toLocaleString()} words ${words < target ? 'to' : 'above'} target.`;
        const stepData = this.appState.getStateSlice('stepData') || {};
        const hasOutline = Boolean(stepData[3] && Array.isArray(stepData[3].selection) && stepData[3].selection.length);
        if (heading) heading.textContent = `${words.toLocaleString()} words in ${step === 6 ? 'the article body' : 'the reviewed article'}`;
        if (detail) {
            const completeTarget = Number(plan && plan.complete_target_words);
            const reserved = Number(plan && plan.non_body_target_words);
            const allocation = step === 6 && completeTarget && reserved
                ? ` Body is ${target.toLocaleString()} of the ${completeTarget.toLocaleString()}-word complete target; approximately ${reserved.toLocaleString()} words are reserved for the introduction, conclusion${this.qnaEnabled() ? ', Q&A' : ''} and title/tagline. `
                : '';
            detail.textContent = `Target ${target.toLocaleString()} · preferred range ${min.toLocaleString()}–${max.toLocaleString()} · ${relation}`
                + allocation
                + (words < target && !hasOutline ? 'Select an outline before generating if you want AI-Scribe to improve sections safely.' : '');
        }
        if (bar) bar.style.width = `${Math.min(100, Math.max(4, (words / target) * 100))}%`;
        status.classList.toggle('is-in-range', inRange);
        status.classList.toggle('is-advisory', !inRange);
        if (action) action.hidden = words >= target || !hasOutline;
        status.hidden = false;
    }

    setWizardImprovementState(panel, state, message = '') {
        const button = panel && panel.querySelector('[data-action="wizard-improve-length"]');
        const label = button && button.querySelector('[data-improve-length-label]');
        const status = panel && panel.querySelector('[data-improve-length-status]');
        if (!button || !status) return;
        let progress = panel.querySelector('[data-improve-length-progress]');
        if (!progress) {
            progress = document.createElement('div');
            progress.className = 'article-improvement-progress';
            progress.dataset.improveLengthProgress = '';
            progress.setAttribute('role', 'status');
            progress.setAttribute('aria-live', 'polite');
            progress.setAttribute('aria-atomic', 'true');
            progress.hidden = true;
            progress.innerHTML = '<span class="article-improvement-spinner" aria-hidden="true"></span>'
                + '<span><strong data-improve-progress-stage></strong><small data-improve-progress-detail></small></span>'
                + '<span class="article-improvement-track" aria-hidden="true"><span></span></span>';
            button.closest('.article-target-action').appendChild(progress);
        }
        button.disabled = state === 'loading';
        button.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
        button.classList.toggle('is-busy', state === 'loading');
        if (label) label.textContent = state === 'loading' ? 'Improving length'
            : (state === 'error' ? 'Try improvement again' : 'Improve length');
        status.textContent = message || (state === 'loading'
            ? 'Adding useful detail now. The current draft will stay here if the request cannot finish.'
            : 'The draft was extended and the count has been checked again.');
        status.classList.toggle('is-error', state === 'error');
        if (this.improvementProgressTimer) {
            window.clearInterval(this.improvementProgressTimer);
            this.improvementProgressTimer = null;
        }
        progress.hidden = state !== 'loading';
        if (state === 'loading') {
            this.improvementProgressStartedAt = Date.now();
            const stage = progress.querySelector('[data-improve-progress-stage]');
            const detail = progress.querySelector('[data-improve-progress-detail]');
            if (stage) stage.textContent = 'Improving article length';
            const updateElapsed = () => {
                const elapsed = Math.max(0, Math.round((Date.now() - this.improvementProgressStartedAt) / 1000));
                if (detail) detail.textContent = `Distributing concise detail across existing sections · ${elapsed}s elapsed`;
            };
            updateElapsed();
            this.improvementProgressTimer = window.setInterval(updateElapsed, 1000);
        }
    }

    async improveWizardLength(button) {
        const step = this.currentStep();
        const bodyOnly = step === 6;
        const view = this.registry.get(step);
        const panel = button && button.closest('[data-step-panel]');
        const conversationId = this.appState.getStateSlice('conversationId');
        const currentHtml = view && typeof view.getSelection === 'function' ? view.getSelection() : '';
        if (!view || !conversationId || !currentHtml || (bodyOnly && !view.enforceOutlineCoverage())) return;
        if (this.pendingSteps.has('wizard-improve-length')) return;
        this.pendingSteps.add('wizard-improve-length');
        const requestEpoch = ++this.manualImprovementEpoch;
        this.setWizardImprovementState(panel, 'loading');
        try {
            const data = await this.api.improveWizardLength(conversationId, currentHtml, bodyOnly);
            if (requestEpoch !== this.manualImprovementEpoch) return;
            this.recordResult(data);
            if (bodyOnly) {
                view.finalHtml = data.improved_html;
                view.renderContent(data.improved_html, { quality_plan: data.quality_plan });
                const stepData = this.appState.getStateSlice('stepData') || {};
                stepData[6] = stepData[6] || {};
                stepData[6].qualityPlan = data.quality_plan;
                this.appState.setStateSlice('stepData', stepData);
                view.enforceOutlineCoverage();
            } else {
                view.renderImprovedHtml(data.improved_html);
            }
            this.renderWizardLengthStatus(data.quality_plan);
            this.setWizardImprovementState(panel, 'success', data.improvement && data.improvement.message);
        } catch (error) {
            if (requestEpoch !== this.manualImprovementEpoch) return;
            const message = `The improvement could not finish${error && error.message ? `: ${error.message}` : ''}. Your existing draft is unchanged.`;
            this.setWizardImprovementState(panel, 'error', message);
            this.notify(message, 'error');
        } finally {
            if (requestEpoch === this.manualImprovementEpoch) {
                this.pendingSteps.delete('wizard-improve-length');
            }
        }
    }

    /** Per-article length override; omitted fields inherit the global setting server-side. */
    articleLengthFields(context) {
        const panel = context === 'express'
            ? document.querySelector('[data-step-panel="express"]')
            : document.querySelector('[data-step-panel="1"]');
        const mode = panel && panel.querySelector('[data-article-length-mode]');
        const count = panel && panel.querySelector('[data-article-word-count]');
        if (!mode || mode.value === 'global') {
            return {};
        }
        const fields = { article_length_mode: mode.value };
        if (mode.value === 'custom' && count) {
            fields.article_word_count = Math.max(400, Math.min(8000, parseInt(count.value, 10) || 1800));
        }
        return fields;
    }

    /** Show the target and any obvious scope mismatch before generation. */
    refreshArticlePlanControls() {
        const globalSettings = (window.ai_scribe && window.ai_scribe.contentSettings) || {};
        document.querySelectorAll('.article-length-override').forEach((row) => {
            const modeField = row.querySelector('[data-article-length-mode]');
            const countField = row.querySelector('[data-article-word-count]');
            const customGroup = row.querySelector('[data-custom-word-count]');
            const panel = typeof row.closest === 'function' ? row.closest('[data-step-panel]') : null;
            const summary = (row.parentElement && row.parentElement.querySelector('[data-article-plan-summary]'))
                || (panel && panel.querySelector('[data-article-plan-summary]'));
            if (!modeField) return;
            const localMode = modeField.value;
            let mode = localMode;
            if (mode === 'global') mode = globalSettings.article_length_mode || 'auto';
            const isCustom = mode === 'custom';
            const isLocalCustom = localMode === 'custom';
            if (customGroup) {
                customGroup.hidden = !isLocalCustom;
                if (typeof customGroup.setAttribute === 'function') {
                    customGroup.setAttribute('aria-hidden', isLocalCustom ? 'false' : 'true');
                }
            }
            if (isLocalCustom && countField && document.activeElement === modeField) {
                window.setTimeout(() => countField.focus(), 0);
            }
            const headings = Math.max(1, parseInt(globalSettings.number_of_headings, 10) || 5);
            let target = { concise: 950, standard: 1800, in_depth: 2800 }[mode];
            if (isCustom) {
                const customTarget = isLocalCustom
                    ? parseInt(countField && countField.value, 10)
                    : parseInt(globalSettings.article_word_count, 10);
                target = Math.max(400, Math.min(8000, customTarget || 1800));
            }
            if (!target) target = Math.max(1200, Math.min(4200, Math.round((320 + headings * 245 + (this.qnaEnabled() ? 260 : 0)) / 50) * 50));
            const tolerance = isCustom ? 0.125 : 0.15;
            const min = Math.floor(target * (1 - tolerance));
            const max = Math.ceil(target * (1 + tolerance));
            const scopeMin = 300 + headings * 150 + (this.qnaEnabled() ? 180 : 0);
            const warning = max < scopeMin
                ? ' This may be too short for the configured headings and Q&A; increase the target or reduce the scope.'
                : ' The final target also reflects the selected outline and keywords.';
            if (summary) {
                summary.textContent = `Planned target: about ${target.toLocaleString()} words (${min.toLocaleString()}–${max.toLocaleString()}).${warning}`;
            }
        });
    }

    preparePublishingDetails() {
        const panel = document.querySelector('[data-step-panel="10"]');
        if (!panel) return;
        const categoryField = panel.querySelector('[data-publishing-category]');
        const tagsField = panel.querySelector('[data-publishing-tags]');
        if (!categoryField || !tagsField) return;
        const saved = this.appState.getStateSlice('publishingDetails') || {};
        const stepData = this.appState.getStateSlice('stepData') || {};
        const selected = stepData[2] && Array.isArray(stepData[2].selection) ? stepData[2].selection : [];
        const phrases = selected.map((item) => String(item && item.keyword ? item.keyword : item).trim()).filter(Boolean);
        const source = phrases[0] || String(this.topicValue() || (stepData[1] && stepData[1].selection) || 'Article');
        const category = source.replace(/\b(?:19|20)\d{2}\b/g, '')
            .replace(/\b(?:this year|tips?|guide|how to|best|top|for|the|a|an|and|of)\b/gi, '')
            .replace(/[^\p{L}\p{N}]+/gu, ' ').trim().split(/\s+/).slice(0, 4)
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ') || 'Articles';
        const fallbackPhrase = source.replace(/\bthis year\b/gi, String(new Date().getFullYear())).trim();
        const suggestedTags = Array.from(new Set((phrases.length ? phrases : [fallbackPhrase, category.toLowerCase()])
            .map((phrase) => String(phrase).trim()).filter(Boolean))).slice(0, 8);
        categoryField.value = saved.category || category;
        tagsField.value = saved.tags || suggestedTags.join(', ');
        const persist = () => this.appState.setStateSlice('publishingDetails', {
            category: categoryField.value.trim(), tags: tagsField.value.trim()
        });
        if (!categoryField.dataset.persistenceBound) {
            categoryField.dataset.persistenceBound = 'true';
            tagsField.dataset.persistenceBound = 'true';
            categoryField.addEventListener('input', persist);
            tagsField.addEventListener('input', persist);
        }
        persist();
    }

    publishingAssignmentMessage(requested, assigned) {
        const requestedCategory = String(requested && requested.category || '').trim();
        const requestedTags = String(requested && requested.tags || '').split(',').map((tag) => tag.trim()).filter(Boolean);
        const assignedCategory = String(assigned && assigned.category || '').trim();
        const assignedTags = Array.isArray(assigned && assigned.tags) ? assigned.tags.map((tag) => String(tag).trim()).filter(Boolean) : [];
        const missing = [];
        if (requestedCategory && assignedCategory.toLowerCase() !== requestedCategory.toLowerCase()) missing.push(`category “${requestedCategory}”`);
        const assignedKeys = new Set(assignedTags.map((tag) => tag.toLowerCase()));
        const missingTags = requestedTags.filter((tag) => !assignedKeys.has(tag.toLowerCase()));
        if (missingTags.length) missing.push(`${missingTags.length} requested ${missingTags.length === 1 ? 'tag' : 'tags'}`);
        if (missing.length) {
            return { type: 'warning', message: `Post saved, but WordPress did not assign ${missing.join(' or ')}. Check that the terms already exist or ask an administrator to create them.` };
        }
        const details = [];
        if (assignedCategory) details.push(`category “${assignedCategory}”`);
        if (assignedTags.length) details.push(`${assignedTags.length} ${assignedTags.length === 1 ? 'tag' : 'tags'}`);
        return { type: 'success', message: details.length ? `Publishing details saved: ${details.join(' and ')}.` : 'Post saved without a category or tags request.' };
    }

    enterWizardFromExpress() {
        // From this handoff onward the compiled/edited Review article is the
        // authoritative snapshot. Returning to the Express tab must never
        // make its older preview eligible to overwrite those Wizard edits.
        this.appState.setStateSlice('articleSaveAuthority', 'review');
        this.switchMode('wizard');
        this.unlockThrough(10); // Express has produced the whole article.
        this.navigateToStep(10); // Land on Review with the seeded article.
    }

    /* ------------------------------------------------------------------ */
    /* Image gallery (UAT §12.4 — steps 6 and 10)                          */
    /* ------------------------------------------------------------------ */

    galleryEl(step) {
        return document.getElementById(step === 10 ? 'review-image-gallery' : 'image-gallery');
    }

    imageStatusEl(step) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        return panel ? panel.querySelector('[data-testid="image-status"]') : null;
    }

    announceImages(step, text) {
        const status = this.imageStatusEl(step);
        if (status) {
            status.textContent = text;
        }
    }

    /**
     * The user's own image description from the panel's optional prompt
     * field, so what gets generated is steerable before it is billed.
     *
     * @param {number} step
     * @return {string}
     */
    imagePromptFieldValue(step) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const field = panel && panel.querySelector('[data-testid="image-prompt-input"]');
        return field ? field.value.trim() : '';
    }

    /**
     * The prompt an image is generated from: the user's own description when
     * one is given, otherwise title + current section hint.
     *
     * @param {string} extraContext Section heading for bulk generation.
     * @param {number} step         Panel the prompt field is read from.
     */
    imagePrompt(extraContext, step) {
        const state = this.appState.getStateSlice('stepData') || {};
        const titleData = state[1] && state[1].selection;
        const title = typeof titleData === 'string' && titleData
            ? titleData
            : this.topicValue();
        const suffix = extraContext ? ` (${extraContext})` : '';
        const cfg = this.effectiveImageOptions();
        const custom = this.imagePromptFieldValue(step);
        if (custom) {
            return custom;
        }
        if (cfg.style) {
            // 2.6.2 style-preset prompt formula (ab_image_style parity).
            return `${title}${suffix} - Create an image based on this title in the style of ${cfg.style}. `
                + 'You must not include any text, characters, symbols, or writing. '
                + 'Highly detailed and stylised to match the title.';
        }
        return `Editorial illustration for a blog article titled "${title}"${suffix}. No text in the image.`;
    }

    globalImageOptions() {
        const cfg = (window.ai_scribe && window.ai_scribe.images) || {};
        return ['model', 'style', 'size', 'quality', 'format', 'background'].reduce((result, key) => {
            if (typeof cfg[key] === 'string' && cfg[key]) {
                result[key] = cfg[key];
            }
            return result;
        }, {});
    }

    effectiveImageOptions() {
        const local = this.appState.getStateSlice('imageOverrides');
        return local && local.enabled
            ? Object.assign({}, this.globalImageOptions(), local.values || {})
            : this.globalImageOptions();
    }

    syncImageStudio(step) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        if (!panel) return;
        const global = this.globalImageOptions();
        const local = this.appState.getStateSlice('imageOverrides');
        const enabled = !!(local && local.enabled);
        const values = Object.assign({}, global, (local && local.values) || {});
        const toggle = panel.querySelector('[data-testid="image-override-toggle"]');
        if (toggle) toggle.checked = enabled;
        panel.querySelectorAll('[data-image-option]').forEach((field) => {
            field.disabled = !enabled;
            const value = values[field.getAttribute('data-image-option')] || '';
            if (field.tagName === 'SELECT' && value
                && !Array.from(field.options).some((option) => option.value === value)) {
                const legacy = document.createElement('option');
                legacy.value = value;
                legacy.textContent = `${value} (saved custom style)`;
                field.appendChild(legacy);
            }
            field.value = value;
        });
        const state = panel.querySelector('[data-testid="image-override-state"]');
        if (state) state.textContent = enabled ? 'Custom for this article' : 'Using global settings';
        const prompt = panel.querySelector('[data-testid="image-prompt-input"]');
        if (prompt) {
            const nextAuto = this.baseImagePrompt('article introduction');
            if (!prompt.value.trim() || prompt.value === prompt.dataset.autoPrompt) {
                prompt.value = nextAuto;
                prompt.dataset.autoPrompt = nextAuto;
            }
        }
        this.renderImagePlan(step);
        this.updateGalleryStates(step);
    }

    toggleImageOverrides(step, enabled) {
        const previous = this.appState.getStateSlice('imageOverrides');
        this.appState.setStateSlice('imageOverrides', {
            enabled: !!enabled,
            values: previous && previous.values ? previous.values : this.globalImageOptions()
        });
        this.syncImageStudio(step);
    }

    saveImageOverridesFromPanel(step) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        if (!panel) return;
        const values = {};
        panel.querySelectorAll('[data-image-option]').forEach((field) => {
            if (field.value) values[field.getAttribute('data-image-option')] = field.value;
        });
        this.appState.setStateSlice('imageOverrides', { enabled: true, values });
        [6, 10].forEach((target) => this.syncImageStudio(target));
    }

    resetImageOverrides(step) {
        this.appState.setStateSlice('imageOverrides', { enabled: false, values: {} });
        [6, 10].forEach((target) => this.syncImageStudio(target));
        this.announceImages(step, 'Article image settings reset to the global defaults.');
    }

    sectionPrompts(step) {
        const outline = ((this.appState.getStateSlice('stepData') || {})[3] || {}).selection;
        return (Array.isArray(outline) ? outline : []).filter(Boolean).map((section) => ({
            section: String(section),
            prompt: this.baseImagePrompt(String(section))
        }));
    }

    baseImagePrompt(section) {
        const data = this.appState.getStateSlice('stepData') || {};
        const title = (data[1] && data[1].selection) || this.topicValue();
        const style = this.effectiveImageOptions().style;
        return `Editorial image for the section "${section}" in the article "${title}". `
            + `Show the section's specific subject, not a generic article image.${style ? ` Visual style: ${style}.` : ''} `
            + 'Do not include words, letters, numbers, symbols or writing in the image.';
    }

    renderImagePlan(step) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const host = panel && panel.querySelector('[data-testid="image-plan"]');
        if (!host) return;
        const prompts = this.sectionPrompts(step);
        host.textContent = '';
        if (!prompts.length) return;
        const details = document.createElement('details');
        details.className = 'image-plan-details';
        const summary = document.createElement('summary');
        summary.textContent = `${prompts.length} section image prompts`;
        details.appendChild(summary);
        const help = document.createElement('p');
        help.className = 'image-plan-help';
        help.textContent = 'Optional: review or edit these prompts before generating the section set.';
        details.appendChild(help);
        prompts.forEach((entry, index) => {
            const label = document.createElement('label');
            label.textContent = entry.section;
            const textarea = document.createElement('textarea');
            textarea.className = 'form-control image-plan-prompt';
            textarea.rows = 3;
            textarea.value = entry.prompt;
            textarea.setAttribute('data-section-index', String(index));
            label.appendChild(textarea);
            details.appendChild(label);
        });
        host.appendChild(details);
    }

    /**
     * The first preview image is useful immediately, but generation is a
     * billable action. It therefore starts only after the Body exists, the
     * global image setting is on, and the studio can show live progress.
     * The persisted state flag makes entry, hydration and Back navigation
     * idempotent. A failure is retryable and does not poison future visits.
     */
    async maybeAutoGenerateFeaturedImage() {
        const flags = (window.ai_scribe && window.ai_scribe.images) || {};
        if (!flags.enabled || flags.available === false || this.galleryImages().length > 0
            || this.imageOperationPending || this.appState.getStateSlice('featuredImageAutoStarted')) {
            return;
        }
        const body = this.registry.get(6);
        if (!body || body.state !== 'ready' || !this.editorForStep(6)) {
            return;
        }
        const panel = document.querySelector('[data-step-panel="6"]');
        const button = panel && panel.querySelector('[data-action="add-image"]');
        if (!button) return;
        this.appState.setStateSlice('featuredImageAutoStarted', true);
        const result = await this.addImage(6, button, '', 'Creating and placing your featured image automatically. This uses your configured image provider and may incur a provider charge.');
        if (!result) {
            this.appState.setStateSlice('featuredImageAutoStarted', false);
        }
    }

    startImageProgress(step, title, detail) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const status = panel && panel.querySelector('[data-testid="image-generation-status"]');
        if (!status) return;
        window.clearInterval(this.imageProgressTimer);
        this.imageProgressStartedAt = Date.now();
        status.hidden = false;
        status.classList.add('is-active');
        status.classList.remove('is-complete', 'is-error');
        status.setAttribute('aria-busy', 'true');
        const titleEl = status.querySelector('[data-image-progress-title]');
        const detailEl = status.querySelector('[data-image-progress-detail]');
        if (titleEl) titleEl.textContent = title;
        if (detailEl) detailEl.textContent = detail || 'Your article remains editable while this runs.';
        const tick = () => {
            const elapsed = Math.max(0, Math.floor((Date.now() - this.imageProgressStartedAt) / 1000));
            const time = status.querySelector('[data-image-progress-time]');
            if (time) time.textContent = `${elapsed}s`;
            if (detailEl && elapsed >= 45) detailEl.textContent = 'Still working with your image provider. Larger images can take over a minute.';
        };
        tick();
        this.imageProgressTimer = window.setInterval(tick, 1000);
    }

    finishImageProgress(step, state, title, detail) {
        window.clearInterval(this.imageProgressTimer);
        this.imageProgressTimer = null;
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const status = panel && panel.querySelector('[data-testid="image-generation-status"]');
        if (!status) return;
        status.classList.remove('is-active');
        status.classList.toggle('is-complete', state === 'complete');
        status.classList.toggle('is-error', state === 'error');
        status.setAttribute('aria-busy', 'false');
        const titleEl = status.querySelector('[data-image-progress-title]');
        const detailEl = status.querySelector('[data-image-progress-detail]');
        if (titleEl) titleEl.textContent = title;
        if (detailEl) detailEl.textContent = detail || '';
    }

    renderImageProgressQueue(step, items) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const queue = panel && panel.querySelector('[data-testid="image-progress-queue"]');
        if (!queue) return;
        queue.textContent = '';
        (items || []).forEach((item, index) => {
            const row = document.createElement('li');
            row.dataset.imageQueueKey = String(item.key == null ? index : item.key);
            row.dataset.state = item.state || 'waiting';
            const marker = document.createElement('span');
            marker.className = 'image-progress-marker';
            marker.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.className = 'image-progress-label';
            label.textContent = item.label || `Image ${index + 1}`;
            const state = document.createElement('span');
            state.className = 'image-progress-state';
            state.textContent = item.message || (item.state === 'active' ? 'Generating' : 'Waiting');
            row.appendChild(marker);
            row.appendChild(label);
            row.appendChild(state);
            queue.appendChild(row);
        });
        queue.hidden = !items || items.length === 0;
    }

    updateImageProgressItem(step, key, state, message) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const queue = panel && panel.querySelector('[data-testid="image-progress-queue"]');
        const row = queue && Array.from(queue.children).find(
            (item) => item.dataset.imageQueueKey === String(key)
        );
        if (!row) return;
        row.dataset.state = state;
        const copy = row.querySelector('.image-progress-state');
        if (copy) copy.textContent = message;
    }

    imageRetryOptions(step, queueKey, label) {
        return {
            onRetry: ({ attempt, maxRetries, delay, error }) => {
                const wait = delay < 1000 ? 'under a second' : `${Math.ceil(delay / 1000)}s`;
                const message = `Provider temporarily unavailable. Retrying ${attempt} of ${maxRetries} in ${wait}.`;
                this.updateImageProgressItem(step, queueKey, 'retrying', `Retry ${attempt} of ${maxRetries}`);
                this.announceImages(step, `${label || 'Image'}: ${message}`);
                this.startImageProgress(step, 'Image provider is busy', message);
                if (error && error.message) {
                    const status = this.imageStatusEl(step);
                    if (status) status.dataset.lastProviderError = error.message;
                }
            }
        };
    }

    setGalleryCardProgress(url, state, message) {
        document.querySelectorAll('.gallery-item').forEach((card) => {
            if (card.dataset.imageUrl !== url) return;
            card.classList.toggle('is-generating', state === 'active' || state === 'retrying');
            const status = card.querySelector('[data-testid="gallery-item-status"]');
            if (!status) return;
            status.hidden = !message;
            status.dataset.state = state || '';
            status.textContent = message || '';
        });
    }

    async addImage(step, button, extraContext, statusLabel) {
        const gallery = this.galleryEl(step);
        if (!gallery || this.imageOperationPending) {
            return null;
        }
        this.imageOperationPending = true;
        this.setImageControlsBusy(step, true);
        WizardFlowController.setButtonBusy(button, true);
        const activeMessage = statusLabel || 'Generating image. This can take over a minute.';
        this.announceImages(step, activeMessage);
        this.startImageProgress(step, statusLabel && statusLabel.includes('automatically') ? 'Creating featured image' : 'Creating image', activeMessage);
        this.renderImageProgressQueue(step, [{
            key: 'single',
            label: statusLabel && statusLabel.includes('automatically') ? 'Featured image' : (extraContext || 'New article image'),
            state: 'active',
            message: 'Generating'
        }]);
        try {
            const prompt = this.imagePrompt(extraContext, step);
            const data = await this.api.generateImage(
                prompt,
                this.effectiveImageOptions(),
                this.imageRetryOptions(step, 'single', extraContext || 'Image')
            );
            data.prompt_used = data.prompt_used || prompt;
            data.source_section = extraContext || this.inferImageSourceSection(prompt, step);
            data.image_options = data.image_options || this.effectiveImageOptions();
            this.appendGalleryImage(step, gallery, data);
            const isFirstImage = this.galleryImages().length === 1;
            if (isFirstImage) {
                this.appState.setStateSlice('featuredImageRemoved', false);
                this.refreshFeaturedPreview();
                if (step !== 10) {
                    this.insertImageIntelligently(step, data, true);
                }
            }
            const firstImageDetail = step === 10
                ? 'Featured image generated and shown in the separate preview.'
                : 'Featured image generated and placed in the article.';
            this.updateImageProgressItem(step, 'single', 'complete', isFirstImage ? (step === 10 ? 'Ready in preview' : 'Ready and placed') : 'Ready to place');
            this.announceImages(step, isFirstImage && step === 10
                ? 'Featured image generated. It is shown in the separate preview and will not be inserted into the article.'
                : 'Image generated. Its prompt and placement status are shown below.');
            this.finishImageProgress(step, 'complete', 'Image ready', isFirstImage ? firstImageDetail : 'The new image is ready to review and place.');
            return data;
        } catch (error) {
            // An image failure is not a step failure. The article body has
            // already generated; putting the step into the error state
            // replaced it with an error box and left Continue with nothing to
            // continue from, stranding the user on step 6 with no way forward
            // but Start Again — which discards the article. Report it at the
            // gallery, where the user pressed the button, and leave the step
            // and its content alone.
            this.announceImages(step, `Image generation failed: ${error.message}. The article is unaffected. You can continue without an image, or try again.`);
            this.updateImageProgressItem(step, 'single', 'error', 'Failed — ready to retry');
            this.finishImageProgress(step, 'error', 'Image generation failed', 'Your article is safe. Check the message below, then try again.');
            return null;
        } finally {
            this.imageOperationPending = false;
            this.setImageControlsBusy(step, false);
            WizardFlowController.setButtonBusy(button, false);
        }
    }

    /**
     * One image per selected outline section, one request per section so the
     * queue reports true "Generating image N of M" progress (L-17/L-18). The
     * server builds a distinct prompt per heading; without an outline it
     * falls back to a single title-based image.
     */
    async bulkAddImages(step, button) {
        if (this.imageOperationPending) return;
        const outline = ((this.appState.getStateSlice('stepData') || {})[3] || {}).selection;
        const sections = (Array.isArray(outline) ? outline : [])
            .filter((section) => typeof section === 'string' && section.trim() !== '');
        this.imageOperationPending = true;
        this.setImageControlsBusy(step, true);
        WizardFlowController.setButtonBusy(button, true);
        const missingSections = sections.filter(
            (section) => !this.galleryImages().some((image) => image.source_section === section)
        );
        this.startImageProgress(step, 'Creating section image set', 'Generating only missing section images. Completed images are kept if a later request fails.');
        this.renderImageProgressQueue(step, missingSections.map((section) => ({
            key: section,
            label: section,
            state: 'waiting',
            message: 'Waiting'
        })));
        try {
            if (sections.length === 0) {
                this.imageOperationPending = false;
                await this.addImage(step, null, '', 'Generating image 1 of 1…');
                return;
            }
            const gallery = this.galleryEl(step);
            const titleData = ((this.appState.getStateSlice('stepData') || {})[1] || {}).selection;
            const articleTitle = typeof titleData === 'string' && titleData ? titleData : this.topicValue();
            const userPrompt = this.imagePromptFieldValue(step);
            const plan = document.querySelector(`[data-step-panel="${step}"] [data-testid="image-plan"]`);
            const planned = plan ? Array.from(plan.querySelectorAll('[data-section-index]')).map((field) => field.value.trim()) : [];
            const total = sections.length;
            const expected = sections.filter((section) => !this.galleryImages().some((image) => image.source_section === section)).length;
            let added = 0;
            for (let i = 0; i < total; i++) {
                if (this.galleryImages().some((image) => image.source_section === sections[i])) {
                    continue;
                }
                this.updateImageProgressItem(step, sections[i], 'active', `Generating ${added + 1} of ${expected}`);
                this.announceImages(step, `Generating image ${i + 1} of ${total}…`);
                this.startImageProgress(step, `Creating image ${i + 1} of ${total}`, `For “${sections[i]}”. Completed images are kept as the set progresses.`);
                try {
                    // Sequential on purpose: one billing unit per request.
                    // eslint-disable-next-line no-await-in-loop
                    const exactPrompt = planned[i] || this.baseImagePrompt(sections[i]);
                    const data = await this.api.generateSectionImage(
                        sections,
                        i,
                        articleTitle,
                        userPrompt,
                        exactPrompt,
                        this.effectiveImageOptions(),
                        this.imageRetryOptions(step, sections[i], sections[i])
                    );
                    data.prompt_used = data.prompt_used || exactPrompt;
                    data.source_section = sections[i];
                    data.image_options = data.image_options || this.effectiveImageOptions();
                    this.appendGalleryImage(step, gallery, data);
                    if (this.galleryImages().length === 1) {
                        this.appState.setStateSlice('featuredImageRemoved', false);
                        this.refreshFeaturedPreview();
                        if (step !== 10) this.insertImageIntelligently(step, data, true);
                    }
                    added++;
                    this.updateImageProgressItem(step, sections[i], 'complete', 'Ready');
                } catch (error) {
                    this.updateImageProgressItem(step, sections[i], 'error', 'Failed — retry missing');
                    this.announceImages(step, `Image ${i + 1} of ${total} failed: ${error.message}. ${added} completed image(s) were kept. Press Generate section set to retry only the missing sections.`);
                    this.finishImageProgress(step, 'error', `Image ${i + 1} failed`, `${added} completed image(s) were kept. Retry generates only missing images.`);
                    break; // Error already shown; do not keep billing.
                }
            }
            if (added === expected) {
                this.announceImages(step, expected
                    ? `${added === 1 ? 'Image' : `All ${added} missing images`} generated. Use Place all unplaced for automatic section placement.`
                    : 'Every section already has an image. No duplicate requests were sent.');
                this.finishImageProgress(step, 'complete', expected ? 'Section image set ready' : 'Nothing to generate', expected ? `${added} new image(s) are ready below.` : 'Every selected section already has an image.');
            }
        } finally {
            this.imageOperationPending = false;
            this.setImageControlsBusy(step, false);
            WizardFlowController.setButtonBusy(button, false);
        }
    }

    setImageControlsBusy(step, busy) {
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        if (!panel) return;
        panel.querySelectorAll('[data-action="add-image"], [data-action="bulk-add-images"], [data-action="insert-all-images"]').forEach((control) => {
            WizardFlowController.setButtonBusy(control, busy);
        });
    }

    /**
     * Images generated so far this article, persisted in AppState so they
     * survive step changes and reloads, and so the save path knows which
     * attachment to set as the featured image.
     *
     * @return {Array<{url:string,attachment_id:number,alt_text:string}>}
     */
    galleryImages() {
        const stored = this.appState.getStateSlice('galleryImages');
        return Array.isArray(stored) ? stored : [];
    }

    static automaticImageCaption(data) {
        const generic = new Set([
            'article introduction', 'introduction', 'article intro', 'featured image',
            'feature image', 'article image', 'article illustration', 'section image',
            'section illustration', 'blog image', 'ai generated image'
        ]);
        const clean = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const acceptable = (value) => {
            const text = clean(value);
            const identity = text.replace(/[^\p{L}\p{N}]+/gu, ' ').trim().toLowerCase();
            return text && !generic.has(identity)
                && !/\b(?:do not include|must not include|no text|visual style|highly detailed|image prompt)\b/i.test(text);
        };
        // Prompt text is evidence for regeneration, not publication-ready copy.
        // Server responses already carry their sanitised prompt-derived caption.
        for (const candidate of [data.caption, data.alt_text]) {
            if (acceptable(candidate)) return clean(candidate);
        }
        return '';
    }

    recordGalleryImage(data) {
        const images = this.galleryImages();
        const isFirstImage = images.length === 0;
        images.push({
            url: data.url,
            attachment_id: data.attachment_id ? parseInt(data.attachment_id, 10) : 0,
            alt_text: data.alt_text || '',
            // Never turn workflow labels or raw generation instructions into
            // visible copy. A blank field is safer and remains editable.
            caption: WizardFlowController.automaticImageCaption(data),
            width: data.width ? parseInt(data.width, 10) : 0,
            height: data.height ? parseInt(data.height, 10) : 0,
            prompt_used: data.prompt_used || '',
            prompt_draft: data.prompt_draft || '',
            source_section: data.source_section || data.section || '',
            image_options: data.image_options || {},
            status: data.status || 'ready'
        });
        this.appState.setStateSlice('galleryImages', images);
        if (isFirstImage) {
            this.appState.setStateSlice('featuredImageRemoved', false);
        }
        this.refreshFeaturedPreview();
    }

    /** Keep Review's theme-level featured preview in sync with gallery state. */
    refreshFeaturedPreview() {
        const review = this.registry && this.registry.get ? this.registry.get(10) : null;
        if (review && typeof review.renderFeaturedPreview === 'function') {
            review.renderFeaturedPreview();
        }
    }

    /** Repopulate a step's gallery from state when its DOM starts empty. */
    restoreGallery(step) {
        const gallery = this.galleryEl(step);
        if (!gallery || gallery.querySelector('.gallery-item')) {
            return;
        }
        const images = this.galleryImages();
        images.forEach((data) => {
            this.appendGalleryImage(step, gallery, data, { record: false });
        });
        if (images.length > 0) {
            this.announceImages(step, 'Previously generated images, prompts and placement state restored.');
        }
    }

    appendGalleryImage(step, gallery, data, options) {
        if (!data || !data.url) {
            return;
        }
        if (!options || options.record !== false) {
            this.recordGalleryImage(data);
        }
        const i18n = (window.ai_scribe && window.ai_scribe.i18n) || {};
        const stored = this.galleryImages().find((image) => image.url === data.url) || data;
        const item = document.createElement('article');
        item.className = 'gallery-item';
        item.dataset.imageUrl = stored.url;

        const media = document.createElement('div');
        media.className = 'gallery-media';

        const img = document.createElement('img');
        img.src = stored.url;
        img.alt = stored.alt_text || '';
        img.className = 'gallery-image';
        img.setAttribute('data-testid', 'gallery-image');
        img.setAttribute('tabindex', '0');
        img.setAttribute('role', 'button');
        img.title = i18n.selectToPlace || 'Drag into the article, or select and click a spot to place it';
        // Real drag-and-drop (owner spec §5): drop zones light up between
        // article blocks while dragging; releasing inserts exactly there.
        img.draggable = true;
        img.addEventListener('dragstart', (e) => {
            this.disarmImage();
            this.draggedImage = { step, data };
            item.classList.add('dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', stored.url);
            }
            // The drop targets are the editors, which are created lazily.
            this.prepareImageDropTargets(step);
        });
        img.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            this.draggedImage = null;
            this.clearDropIndicators();
        });
        img.addEventListener('click', () => this.armImage(step, stored, item));
        img.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                // Keyboard path: predictable placement without a second click.
                this.insertImageAtEnd(step, stored);
            }
        });
        media.appendChild(img);
        item.appendChild(media);

        const content = document.createElement('div');
        content.className = 'gallery-card-content';
        const top = document.createElement('div');
        top.className = 'gallery-card-topline';
        const index = this.galleryImages().findIndex((image) => image.url === stored.url);
        const badge = document.createElement('span');
        badge.className = 'gallery-badge';
        if (index === 0) badge.setAttribute('data-testid', 'featured-image-badge');
        badge.textContent = index === 0 ? (i18n.featuredImage || 'Featured image') : (stored.source_section || `Image ${index + 1}`);
        const placement = document.createElement('span');
        placement.className = 'gallery-placement-state';
        placement.setAttribute('data-testid', 'image-placement-state');
        top.appendChild(badge);
        top.appendChild(placement);
        content.appendChild(top);

        const captionLabel = document.createElement('label');
        captionLabel.className = 'gallery-caption-label';
        captionLabel.textContent = 'Caption';
        const caption = document.createElement('textarea');
        caption.rows = 2;
        caption.className = 'form-control gallery-caption-editor';
        caption.value = stored.caption || '';
        caption.placeholder = 'Edit caption, or clear it to remove';
        caption.setAttribute('data-testid', 'gallery-caption');
        caption.setAttribute('aria-label', `Caption for ${badge.textContent}`);
        const resizeCaption = () => {
            caption.style.height = 'auto';
            caption.style.height = `${Math.min(144, Math.max(54, caption.scrollHeight))}px`;
        };
        caption.addEventListener('input', resizeCaption);
        caption.addEventListener('change', () => this.saveGalleryCaption(stored.url, caption.value, step));
        captionLabel.appendChild(caption);
        window.setTimeout(resizeCaption, 0);
        const details = document.createElement('details');
        details.className = 'gallery-prompt-details';
        const summary = document.createElement('summary');
        summary.textContent = stored.prompt_used ? 'View or edit prompt' : 'Prompt unavailable';
        details.appendChild(summary);
        if (stored.prompt_used) {
            const used = document.createElement('p');
            used.className = 'gallery-prompt-used';
            used.textContent = `Generated with: ${stored.prompt_used}`;
            details.appendChild(used);
            const prompt = document.createElement('textarea');
            prompt.className = 'form-control gallery-prompt-editor';
            prompt.rows = 4;
            prompt.value = stored.prompt_draft || stored.prompt_used;
            prompt.setAttribute('data-testid', 'gallery-prompt');
            details.appendChild(prompt);
            const promptActions = document.createElement('div');
            promptActions.className = 'gallery-prompt-actions';
            const save = document.createElement('button');
            save.type = 'button';
            save.className = 'btn btn-link';
            save.textContent = 'Save prompt';
            save.addEventListener('click', () => this.saveGalleryPrompt(stored.url, prompt.value, step));
            const regenerate = document.createElement('button');
            regenerate.type = 'button';
            regenerate.className = 'btn btn-outline';
            regenerate.textContent = 'Regenerate image';
            regenerate.setAttribute('data-testid', 'regenerate-image');
            regenerate.addEventListener('click', () => this.regenerateGalleryImage(step, stored, prompt.value, regenerate));
            promptActions.appendChild(save);
            promptActions.appendChild(regenerate);
            details.appendChild(promptActions);
        }
        content.appendChild(details);
        content.appendChild(captionLabel);

        const itemStatus = document.createElement('div');
        itemStatus.className = 'gallery-item-status';
        itemStatus.setAttribute('data-testid', 'gallery-item-status');
        itemStatus.setAttribute('role', 'status');
        itemStatus.setAttribute('aria-live', 'polite');
        itemStatus.hidden = true;
        content.appendChild(itemStatus);

        const insertBtn = document.createElement('button');
        insertBtn.type = 'button';
        insertBtn.className = 'btn btn-outline gallery-place-btn';
        insertBtn.setAttribute('data-testid', 'insert-image-end');
        insertBtn.textContent = 'Place in article';
        insertBtn.addEventListener('click', () => this.insertImageIntelligently(step, stored, false));
        content.appendChild(insertBtn);
        item.appendChild(content);

        gallery.appendChild(item);
        this.updateGalleryStates(step);
    }

    saveGalleryCaption(url, caption, step) {
        const images = this.galleryImages();
        const item = images.find((image) => image.url === url);
        if (!item) return;
        item.caption = String(caption || '').trim();
        this.appState.setStateSlice('galleryImages', images);
        [6, 10].forEach((target) => {
            const quill = this.editorForStep(target);
            if (!quill) return;
            quill.root.querySelectorAll('img').forEach((img) => {
                if (img.getAttribute('src') !== url) return;
                const paragraph = img.parentElement && img.parentElement.nextElementSibling;
                if (paragraph && paragraph.matches('[data-image-caption], .ai-scribe-image-caption')) {
                    if (item.caption) {
                        paragraph.textContent = item.caption;
                    } else {
                        paragraph.remove();
                    }
                } else if (item.caption && img.parentElement) {
                    const added = document.createElement('p');
                    added.className = 'ai-scribe-image-caption';
                    added.dataset.imageCaption = url;
                    added.textContent = item.caption;
                    img.parentElement.insertAdjacentElement('afterend', added);
                }
            });
            this.persistEditorHtml(target, quill);
        });
        this.announceImages(step, 'Caption updated in every placed copy.');
    }

    saveGalleryPrompt(url, prompt, step) {
        const images = this.galleryImages();
        const item = images.find((image) => image.url === url);
        if (item) item.prompt_draft = String(prompt || '').trim();
        this.appState.setStateSlice('galleryImages', images);
        this.announceImages(step, 'Regeneration prompt saved. The exact prompt used for the current image remains recorded separately.');
    }

    async regenerateGalleryImage(step, oldImage, prompt, button) {
        const finalPrompt = String(prompt || '').trim();
        if (!finalPrompt || this.imageOperationPending) return;
        this.imageOperationPending = true;
        this.setImageControlsBusy(step, true);
        WizardFlowController.setButtonBusy(button, true);
        this.setGalleryCardProgress(oldImage.url, 'active', 'Generating replacement… Current image remains in place.');
        this.announceImages(step, 'Regenerating this image. The current image stays in place until the replacement is ready…');
        try {
            const replacement = await this.api.generateImage(
                finalPrompt,
                this.effectiveImageOptions(),
                {
                    onRetry: ({ attempt, maxRetries }) => {
                        this.setGalleryCardProgress(oldImage.url, 'retrying', `Provider busy — retry ${attempt} of ${maxRetries}.`);
                    }
                }
            );
            replacement.prompt_used = finalPrompt;
            replacement.source_section = oldImage.source_section || '';
            replacement.image_options = this.effectiveImageOptions();
            this.replaceGalleryImage(oldImage, replacement);
            [6, 10].forEach((target) => this.renderGallery(target));
            this.setGalleryCardProgress(replacement.url, 'complete', 'Replacement ready and placed copies updated.');
            this.announceImages(step, 'Image regenerated and every placed copy was safely updated.');
        } catch (error) {
            this.setGalleryCardProgress(oldImage.url, 'error', 'Regeneration failed. Current image kept.');
            this.announceImages(step, `Regeneration failed: ${error.message}. The current image is unchanged.`);
        } finally {
            this.imageOperationPending = false;
            this.setImageControlsBusy(step, false);
            WizardFlowController.setButtonBusy(button, false);
        }
    }

    replaceGalleryImage(oldImage, replacement) {
        const images = this.galleryImages();
        const index = images.findIndex((image) => image.url === oldImage.url);
        if (index === -1) return;
        images[index] = Object.assign({}, replacement, {
            caption: WizardFlowController.automaticImageCaption(Object.assign({}, replacement, {
                caption: replacement.caption || oldImage.caption || ''
            }))
        });
        this.appState.setStateSlice('galleryImages', images);
        [6, 10].forEach((step) => {
            const quill = this.editorForStep(step);
            if (!quill) return;
            quill.root.querySelectorAll('img').forEach((img) => {
                if (img.getAttribute('src') === oldImage.url) {
                    img.setAttribute('src', replacement.url);
                    img.setAttribute('alt', replacement.alt_text || '');
                }
            });
            this.persistEditorHtml(step, quill);
        });
        this.refreshFeaturedPreview();
    }

    renderGallery(step) {
        const gallery = this.galleryEl(step);
        if (!gallery) return;
        gallery.textContent = '';
        this.galleryImages().forEach((data) => this.appendGalleryImage(step, gallery, data, { record: false }));
    }

    /** The editor a step's images land in (Quill on steps 6 and 10). */
    editorForStep(step) {
        const view = this.registry.get(step);
        if (!view) {
            return null;
        }
        const quill = view.quill || (typeof view.ensureEditor === 'function' ? view.ensureEditor() : null);
        if (quill) {
            this.bindEditorImageUx(step, quill);
        }
        return quill;
    }

    /**
     * One-off wiring per editor: the drag-and-drop drop zone and the
     * Delete/Replace toolbar on embedded images. Idempotent — the editors
     * are created lazily and this is called from every path that reaches one.
     *
     * @param {number} step
     * @param {Object} quill
     */
    bindEditorImageUx(step, quill) {
        if (quill.aiScribeImageUxBound) {
            return;
        }
        quill.aiScribeImageUxBound = true;
        this.bindImageDrop(step, quill);
        this.bindImageToolbar(step, quill);
    }

    /** Make sure the current step's editor is listening before a drag lands. */
    prepareImageDropTargets(step) {
        this.editorForStep(step);
    }

    /* ------------------------------------------------------------------ */
    /* Drag-and-drop insertion (owner spec: visible drop zones)            */
    /* ------------------------------------------------------------------ */

    /** The .quill-editor host element wrapping a Quill instance. */
    static editorHost(quill) {
        return quill.root.closest('.quill-editor');
    }

    bindImageDrop(step, quill) {
        const root = quill.root;
        const host = WizardFlowController.editorHost(quill);
        root.addEventListener('dragover', (e) => {
            if (!this.draggedImage) {
                return;
            }
            e.preventDefault();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'copy';
            }
            if (host) {
                host.classList.add('drop-active');
            }
            this.showDropIndicator(root, e.clientY);
        });
        root.addEventListener('dragleave', (e) => {
            if (!root.contains(e.relatedTarget)) {
                this.clearDropIndicators();
            }
        });
        root.addEventListener('drop', (e) => {
            if (!this.draggedImage) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const dropped = this.draggedImage;
            this.draggedImage = null;
            const target = WizardFlowController.dropTarget(root, e.clientY);
            this.clearDropIndicators();
            const index = WizardFlowController.dropIndex(quill, target);
            this.insertImageAt(step, quill, dropped.data, index);
        });
    }

    /**
     * The top-level block the pointer is nearest, and whether the insertion
     * point is before or after it.
     *
     * @param {HTMLElement} root    Quill's .ql-editor.
     * @param {number}      clientY Pointer position.
     * @return {{block: HTMLElement|null, before: boolean}}
     */
    static dropTarget(root, clientY) {
        const blocks = Array.from(root.children);
        for (const block of blocks) {
            const rect = block.getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                return { block, before: true };
            }
        }
        return { block: blocks[blocks.length - 1] || null, before: false };
    }

    /**
     * Quill document index for a drop target.
     *
     * @param {Object} quill
     * @param {{block: HTMLElement|null, before: boolean}} target
     * @return {number}
     */
    static dropIndex(quill, target) {
        if (!target.block || typeof Quill === 'undefined' || typeof Quill.find !== 'function') {
            return quill.getLength();
        }
        const blot = Quill.find(target.block);
        if (!blot) {
            return quill.getLength();
        }
        const index = quill.getIndex(blot);
        return target.before ? index : index + blot.length();
    }

    /** Highlight the insertion point between blocks while dragging over. */
    showDropIndicator(root, clientY) {
        const target = WizardFlowController.dropTarget(root, clientY);
        root.querySelectorAll('.drop-before, .drop-after').forEach((el) => {
            if (el !== target.block) {
                el.classList.remove('drop-before', 'drop-after');
            }
        });
        if (target.block) {
            target.block.classList.toggle('drop-before', target.before);
            target.block.classList.toggle('drop-after', !target.before);
        }
    }

    clearDropIndicators() {
        document.querySelectorAll('.ql-editor .drop-before, .ql-editor .drop-after').forEach((el) => {
            el.classList.remove('drop-before', 'drop-after');
        });
        document.querySelectorAll('.quill-editor.drop-active').forEach((el) => {
            el.classList.remove('drop-active');
        });
    }

    /* ------------------------------------------------------------------ */
    /* Delete / Replace affordances on embedded images                     */
    /* ------------------------------------------------------------------ */

    bindImageToolbar(step, quill) {
        const root = quill.root;
        root.querySelectorAll('img').forEach((img) => this.makeEmbeddedImageOperable(img));
        const show = (e) => {
            const img = e.target;
            if (img && img.tagName === 'IMG') {
                this.showImageToolbar(step, quill, img);
            }
        };
        root.addEventListener('mouseover', show);
        root.addEventListener('click', show);
        root.addEventListener('focusin', show);
        // Any edit can move or remove the image the toolbar points at.
        quill.on('text-change', () => this.hideImageToolbar());
        root.addEventListener('mouseleave', (e) => {
            const toolbar = this.imageToolbar && this.imageToolbar.el;
            if (!toolbar || !e.relatedTarget || !toolbar.contains(e.relatedTarget)) {
                this.scheduleImageToolbarHide();
            }
        });
    }

    scheduleImageToolbarHide() {
        window.clearTimeout(this.imageToolbarHideTimer);
        this.imageToolbarHideTimer = window.setTimeout(() => {
            const bar = this.imageToolbar;
            if (bar && !bar.el.matches(':hover') && !(bar.img.isConnected && bar.img.matches(':hover'))) {
                this.hideImageToolbar();
            }
        }, 350);
    }

    /**
     * Float the Delete/Replace controls over the top-right corner of an
     * embedded image (owner spec: inserted images carry visible affordances).
     */
    showImageToolbar(step, quill, img) {
        const container = quill.root.closest('.quill-editor-container');
        if (!container) {
            return;
        }
        if (this.imageToolbar && this.imageToolbar.img === img) {
            return;
        }
        this.hideImageToolbar();

        const i18n = (window.ai_scribe && window.ai_scribe.i18n) || {};
        const el = document.createElement('div');
        el.className = 'editor-image-toolbar';
        el.setAttribute('data-testid', 'editor-image-toolbar');

        const replaceBtn = document.createElement('button');
        replaceBtn.type = 'button';
        replaceBtn.className = 'btn btn-outline editor-image-btn';
        replaceBtn.setAttribute('data-testid', 'image-replace');
        replaceBtn.textContent = i18n.replaceImage || 'Replace';
        replaceBtn.addEventListener('click', () => this.toggleReplacePanel(step, quill, img, el));

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-outline editor-image-btn editor-image-btn-danger';
        deleteBtn.setAttribute('data-testid', 'image-delete');
        deleteBtn.textContent = i18n.deleteImage || 'Delete';
        deleteBtn.addEventListener('click', () => this.deleteEmbeddedImage(step, quill, img));

        el.appendChild(replaceBtn);
        el.appendChild(deleteBtn);
        el.addEventListener('mouseleave', () => this.scheduleImageToolbarHide());
        container.appendChild(el);

        // Position over the image's top-right corner, container-relative.
        const imgRect = img.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        el.style.top = `${Math.max(0, imgRect.top - containerRect.top + 8)}px`;
        el.style.left = `${Math.max(0, imgRect.right - containerRect.left - el.offsetWidth - 8)}px`;

        img.classList.add('has-image-toolbar');
        this.imageToolbar = { step, quill, img, el };
    }

    hideImageToolbar() {
        window.clearTimeout(this.imageToolbarHideTimer);
        const bar = this.imageToolbar;
        if (!bar) {
            return;
        }
        this.imageToolbar = null;
        bar.img.classList.remove('has-image-toolbar');
        bar.el.remove();
    }

    /** Index of an embedded image inside its Quill document, or -1. */
    static embeddedImageIndex(quill, img) {
        if (typeof Quill === 'undefined' || typeof Quill.find !== 'function') {
            return -1;
        }
        const blot = Quill.find(img);
        if (!blot) {
            return -1;
        }
        try {
            return quill.getIndex(blot);
        } catch (error) {
            return -1;
        }
    }

    deleteEmbeddedImage(step, quill, img) {
        const index = WizardFlowController.embeddedImageIndex(quill, img);
        const caption = img.parentElement && img.parentElement.nextElementSibling
            && img.parentElement.nextElementSibling.matches('.ai-scribe-image-caption, [data-image-caption]')
            ? img.parentElement.nextElementSibling
            : null;
        this.hideImageToolbar();
        if (index === -1) {
            return;
        }
        const url = img.getAttribute('src') || '';
        quill.deleteText(index, 1, 'user');
        if (caption && caption.isConnected) caption.remove();
        // Deleting the auto-placed featured image is a decision, not an
        // accident — the Review compiler must not quietly put it back.
        const first = this.galleryImages()[0];
        if (first && first.url === url) {
            this.appState.setStateSlice('featuredImageRemoved', true);
            this.refreshFeaturedPreview();
        }
        this.persistEditorHtml(step, quill);
        this.updateGalleryStates(step);
        this.announceImages(step, 'Image removed from the article. It is still in the gallery if you change your mind.');
    }

    /**
     * The Replace panel: pick any gallery image, or generate a fresh one
     * from an editable prompt, and it takes this image's exact place.
     */
    toggleReplacePanel(step, quill, img, toolbarEl) {
        const existing = toolbarEl.querySelector('.editor-image-replace-panel');
        if (existing) {
            existing.remove();
            return;
        }
        const i18n = (window.ai_scribe && window.ai_scribe.i18n) || {};
        const panel = document.createElement('div');
        panel.className = 'editor-image-replace-panel';
        panel.setAttribute('data-testid', 'image-replace-panel');

        const hint = document.createElement('p');
        hint.className = 'editor-image-replace-hint';
        hint.textContent = i18n.replaceHint || 'Choose a gallery image, or describe a new one to generate.';
        panel.appendChild(hint);

        const grid = document.createElement('div');
        grid.className = 'editor-image-replace-grid';
        const currentUrl = img.getAttribute('src') || '';
        this.galleryImages().forEach((data) => {
            if (!data.url || data.url === currentUrl) {
                return;
            }
            const choice = document.createElement('button');
            choice.type = 'button';
            choice.className = 'editor-image-replace-choice';
            choice.setAttribute('aria-label', `Replace with ${data.source_section || data.alt_text || 'gallery image'}`);
            const thumb = document.createElement('img');
            thumb.src = data.url;
            thumb.alt = data.alt_text || '';
            thumb.className = 'editor-image-replace-thumb';
            thumb.setAttribute('data-testid', 'replace-thumb');
            choice.addEventListener('click', () => this.replaceEmbeddedImage(step, quill, img, data));
            choice.appendChild(thumb);
            grid.appendChild(choice);
        });
        if (grid.children.length > 0) {
            panel.appendChild(grid);
        }

        const prompt = document.createElement('textarea');
        prompt.className = 'form-control editor-image-replace-prompt';
        prompt.rows = 2;
        prompt.placeholder = i18n.replacePromptPlaceholder || 'Describe the replacement image…';
        prompt.setAttribute('data-testid', 'replace-prompt');
        panel.appendChild(prompt);

        const generate = document.createElement('button');
        generate.type = 'button';
        generate.className = 'btn btn-primary editor-image-btn';
        generate.setAttribute('data-testid', 'replace-generate');
        generate.textContent = i18n.generateReplacement || 'Generate replacement';
        generate.addEventListener('click', async () => {
            WizardFlowController.setButtonBusy(generate, true);
            this.announceImages(step, 'Generating the replacement image, this can take up to a minute…');
            try {
                const custom = prompt.value.trim();
                const cfg = this.effectiveImageOptions();
                const styleSuffix = cfg.style ? ` In the style of ${cfg.style}.` : '';
                const finalPrompt = custom
                    ? `${custom}.${styleSuffix} You must not include any text, characters, symbols, or writing in the image.`
                    : this.imagePrompt('', step);
                const data = await this.api.generateImage(
                    finalPrompt,
                    cfg,
                    this.imageRetryOptions(step, 'replacement', 'Replacement image')
                );
                data.prompt_used = finalPrompt;
                data.image_options = cfg;
                this.appendGalleryImage(step, this.galleryEl(step), data);
                this.replaceEmbeddedImage(step, quill, img, data);
            } catch (error) {
                this.announceImages(step, `Image generation failed: ${error.message}. The article is unaffected.`);
                WizardFlowController.setButtonBusy(generate, false);
            }
        });
        panel.appendChild(generate);

        toolbarEl.appendChild(panel);
        prompt.focus();
    }

    replaceEmbeddedImage(step, quill, img, data) {
        const index = WizardFlowController.embeddedImageIndex(quill, img);
        const oldCaption = img.parentElement && img.parentElement.nextElementSibling
            && img.parentElement.nextElementSibling.matches('.ai-scribe-image-caption, [data-image-caption]')
            ? img.parentElement.nextElementSibling
            : null;
        this.hideImageToolbar();
        if (index === -1 || !data || !data.url) {
            return;
        }
        quill.deleteText(index, 1, 'user');
        if (oldCaption && oldCaption.isConnected) oldCaption.remove();
        quill.insertEmbed(index, 'image', data.url, 'user');
        this.decorateInsertedImage(quill, data);
        this.insertImageCaption(quill, data, index + 1);
        this.persistEditorHtml(step, quill);
        this.updateGalleryStates(step);
        this.announceImages(step, 'Image replaced.');
    }

    /**
     * Click-to-place, made visible: clicking a gallery image arms it (the
     * image highlights, the editor shows a placement cursor), and the next
     * click inside the article inserts it exactly there. Escape cancels.
     * "Drag anywhere" was never true — this is the mechanic the instructions
     * now describe.
     */
    armImage(step, data, item) {
        const wasArmed = this.armedImage && this.armedImage.item === item;
        this.disarmImage();
        if (wasArmed) {
            this.announceImages(step, 'Image deselected.');
            return;
        }
        const quill = this.editorForStep(step);
        if (!quill) {
            // No editor on this panel yet: honest fallback.
            this.insertImageAtEnd(step, data);
            return;
        }
        item.classList.add('is-armed');
        quill.root.classList.add('insert-target');
        this.announceImages(step, 'Image selected. Click a spot in the article to place it, use "Insert at end", or press Escape to cancel.');

        const clickHandler = () => {
            // Let Quill update its selection from the click first.
            window.setTimeout(() => {
                const range = quill.getSelection();
                const index = range ? range.index : quill.getLength();
                this.disarmImage();
                this.insertImageAt(step, quill, data, index);
            }, 0);
        };
        const keyHandler = (e) => {
            if (e.key === 'Escape') {
                this.disarmImage();
                this.announceImages(step, 'Placement cancelled.');
            }
        };
        quill.root.addEventListener('click', clickHandler, { once: true });
        document.addEventListener('keydown', keyHandler);
        this.armedImage = { step, data, item, quill, clickHandler, keyHandler };
    }

    disarmImage() {
        const armed = this.armedImage;
        if (!armed) {
            return;
        }
        this.armedImage = null;
        armed.item.classList.remove('is-armed');
        if (armed.quill) {
            armed.quill.root.classList.remove('insert-target');
            armed.quill.root.removeEventListener('click', armed.clickHandler);
        }
        document.removeEventListener('keydown', armed.keyHandler);
    }

    insertImageAt(step, quill, data, index) {
        if (this.isImagePlaced(quill, data.url)) {
            this.announceImages(step, 'That image is already in this article.');
            return false;
        }
        quill.insertEmbed(index, 'image', data.url, 'user');
        // Give the image its own paragraph-sized line. Inserting an embed at
        // the start of an H2 otherwise inherits the header format and creates
        // invalid `<h2><img>Heading</h2>` markup.
        if (typeof quill.insertText === 'function') {
            quill.insertText(index + 1, '\n', 'user');
        }
        if (typeof quill.formatLine === 'function') {
            quill.formatLine(index, 1, { header: false, blockquote: false, list: false }, 'silent');
        }
        this.decorateInsertedImage(quill, data);
        this.insertImageCaption(quill, data, index + 2);
        quill.setSelection(index + 2, 0, 'silent');
        if (this.galleryImages()[0] && this.galleryImages()[0].url === data.url) {
            this.appState.setStateSlice('featuredImageRemoved', false);
        }
        this.persistEditorHtml(step, quill);
        this.updateGalleryStates(step);
        this.announceImages(step, 'Image inserted into the article.');
        return true;
    }

    insertImageCaption(quill, data, index) {
        const caption = String(data.caption || '').trim();
        if (!caption || typeof quill.insertText !== 'function') return;
        quill.insertText(index, `${caption}\n`, 'user');
        if (typeof quill.formatLine === 'function') {
            quill.formatLine(index, Math.max(1, caption.length), { header: false, blockquote: false, list: false }, 'silent');
        }
        const decorateCaption = () => {
            const imgs = Array.from(quill.root.querySelectorAll('img'));
            const img = imgs.reverse().find((candidate) => candidate.getAttribute('src') === data.url);
            const captionBlock = img && img.parentElement ? img.parentElement.nextElementSibling : null;
            if (captionBlock) {
                captionBlock.classList.add('ai-scribe-image-caption');
                captionBlock.dataset.imageCaption = data.url;
            }
        };
        decorateCaption();
        window.setTimeout(decorateCaption, 0);
    }

    decorateInsertedImage(quill, data) {
        const candidates = Array.from(quill.root.querySelectorAll('img'));
        const img = candidates.reverse().find((candidate) => candidate.getAttribute('src') === data.url);
        if (!img) return;
        img.setAttribute('alt', data.alt_text || '');
        if (data.attachment_id) img.classList.add(`wp-image-${data.attachment_id}`);
        img.removeAttribute('title');
        this.makeEmbeddedImageOperable(img);
    }

    makeEmbeddedImageOperable(img) {
        img.setAttribute('tabindex', '0');
        img.setAttribute('role', 'button');
        img.setAttribute('aria-label', `${img.getAttribute('alt') || 'Article image'}. Focus to replace or remove.`);
    }

    persistEditorHtml(step, quill) {
        if (step === 6) {
            const view = this.registry.get(6);
            if (view && typeof view.persistEdit === 'function') view.persistEdit();
            return;
        }
        if (step === 10) {
            this.appState.setStateSlice('reviewEditedHtml', quill.root.innerHTML);
        }
    }

    isImagePlaced(quill, url) {
        return Array.from(quill.root.querySelectorAll('img')).some((img) => img.getAttribute('src') === url);
    }

    insertImageIntelligently(step, data, featured) {
        const quill = this.editorForStep(step);
        if (!quill) return false;
        let index = quill.getLength();
        const blocks = Array.from(quill.root.children);
        let target = null;
        if (data.source_section) {
            const identity = (value) => String(value || '').normalize('NFKD')
                .replace(/&amp;/gi, '&').replace(/[^\p{L}\p{N}]+/gu, ' ').trim().toLowerCase();
            const wanted = identity(data.source_section);
            target = blocks.find((block) => /^H[2-6]$/.test(block.tagName)
                && identity(block.textContent) === wanted);
        }
        if (!target && featured) {
            target = blocks.find((block) => block.tagName === 'P' && (block.textContent || '').trim());
        }
        if (!target && data.source_section && !featured) {
            this.announceImages(step, `Could not find the section “${data.source_section}”. The image was not moved to the end; choose a location in the article instead.`);
            return false;
        }
        if (target && typeof Quill !== 'undefined' && typeof Quill.find === 'function') {
            const blot = Quill.find(target);
            if (blot) index = quill.getIndex(blot) + blot.length();
        }
        return this.insertImageAt(step, quill, data, index);
    }

    inferImageSourceSection(prompt, step) {
        const text = String(prompt || '').replace(/\s+/g, ' ').trim();
        const planned = this.sectionPrompts(step);
        const exact = planned.find((entry) => String(entry.prompt || '').replace(/\s+/g, ' ').trim() === text);
        if (exact && exact.section) return exact.section;
        const quoted = text.match(/for the section\s+["“]([^"”]+)["”]/i);
        return quoted ? quoted[1].trim() : '';
    }

    insertAllImages(step) {
        const images = this.galleryImages();
        let placed = 0;
        let failed = 0;
        const quill = this.editorForStep(step);
        images.forEach((image, index) => {
            if (quill && this.isImagePlaced(quill, image.url)) return;
            if (this.insertImageIntelligently(step, image, index === 0)) {
                placed++;
            } else {
                failed++;
            }
        });
        let message = 'Every gallery image is already in the article.';
        if (failed > 0) {
            const kept = `${failed} ${failed === 1 ? 'image could' : 'images could'} not be matched to an article section and ${failed === 1 ? 'was' : 'were'} not moved to the end.`;
            message = placed > 0
                ? `${placed} ${placed === 1 ? 'image was' : 'images were'} placed. ${kept} Choose a location in the article for ${failed === 1 ? 'it' : 'them'}.`
                : `${kept} Choose a location in the article ${failed === 1 ? 'for it' : 'for them'}.`;
        } else if (placed > 0) {
            message = `${placed} unplaced ${placed === 1 ? 'image was' : 'images were'} added beside the most relevant sections.`;
        }
        this.announceImages(step, message);
        this.updateGalleryStates(step);
    }

    updateGalleryStates(step) {
        const quill = this.editorForStep(step);
        const gallery = this.galleryEl(step);
        if (!gallery || !quill) return;
        let unplaced = 0;
        gallery.querySelectorAll('.gallery-item').forEach((card) => {
            const placed = this.isImagePlaced(quill, card.dataset.imageUrl || '');
            const state = card.querySelector('[data-testid="image-placement-state"]');
            const button = card.querySelector('.gallery-place-btn');
            if (state) state.textContent = placed ? 'In article' : 'Not placed';
            card.classList.toggle('is-placed', placed);
            if (button) {
                button.disabled = placed;
                button.textContent = placed ? 'Already placed' : 'Place near section';
            }
            if (!placed) unplaced++;
        });
        const panel = document.querySelector(`[data-step-panel="${step}"]`);
        const all = panel ? panel.querySelectorAll('[data-action="insert-all-images"]') : [];
        all.forEach((button) => {
            button.disabled = unplaced === 0;
            button.textContent = unplaced ? `Place all unplaced (${unplaced})` : 'All images placed';
        });
    }

    /** The always-available fallback: append the image to the article. */
    insertImageAtEnd(step, data) {
        this.disarmImage();
        const quill = this.editorForStep(step);
        if (!quill) {
            this.announceImages(step, 'The article editor is not ready yet. Generate the article body first, then insert the image.');
            return;
        }
        this.insertImageAt(step, quill, data, quill.getLength());
    }

    /* ------------------------------------------------------------------ */
    /* Publishing                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Belt and braces for S8-10: Save as Draft, Publish and Save as Shortcode
     * are pressable only while there is a compiled article behind them. Both
     * halves have to hold — the compiled state AND the editor's contents — so
     * neither a hydration regression nor a stale editor can publish an empty
     * or abandoned post.
     *
     * @return {boolean} True when there is something to publish.
     */
    updateReviewActions() {
        const source = this.articleSaveSource();
        const hasArticle = !!source && WizardFlowController.hasVisibleText(source.html);
        if (!this.root) {
            return hasArticle;
        }
        this.root.querySelectorAll(
            '[data-action="save-draft"], [data-action="save-post"], [data-action="save-shortcode"]'
        ).forEach((button) => {
            button.disabled = !hasArticle;
        });
        return hasArticle;
    }

    /**
     * One canonical save source for Express, Review and Evaluate. Express
     * saves the exact preview snapshot; Wizard final steps keep using the
     * edited Review HTML. The publishing methods below remain shared.
     */
    articleSaveSource() {
        const authority = this.appState && this.appState.getStateSlice('articleSaveAuthority');
        const express = this.registry.get('express');
        if (authority !== 'review'
            && this.root && this.root.classList.contains('mode-express-active')
            && express && typeof express.getSaveHtml === 'function') {
            const html = express.getSaveHtml();
            if (WizardFlowController.hasVisibleText(html)) {
                return { view: express, html, context: 'express' };
            }
        }

        const review = this.registry.get(10);
        const compiled = review && typeof review.compileArticleHtml === 'function'
            ? review.compileArticleHtml()
            : '';
        const edited = review && typeof review.getSelection === 'function'
            ? review.getSelection()
            : '';
        if (WizardFlowController.hasVisibleText(compiled) && WizardFlowController.hasVisibleText(edited)) {
            return { view: review, html: edited, context: 'review' };
        }
        // Fail closed once Review owns the article. Falling back to an older
        // Express snapshot here would silently discard later Wizard edits.
        if (authority === 'review') {
            return null;
        }
        return null;
    }

    /**
     * Does this HTML carry anything a reader would actually see?
     *
     * @param {string} html
     * @return {boolean}
     */
    static hasVisibleText(html) {
        if (typeof html !== 'string' || html.trim() === '') {
            return false;
        }
        return html.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').trim().length > 0;
    }

    /** Confirmed output destinations for this article. */
    persistenceState() {
        const saved = this.appState && this.appState.getStateSlice('persistence');
        return saved && typeof saved === 'object'
            ? saved
            : { post: null, shortcode: null };
    }

    /** Exact current Review HTML used to decide whether a saved copy is stale. */
    currentReviewHtml() {
        const source = this.articleSaveSource();
        return source ? source.html : '';
    }

    /** At least one destination contains the exact current article. */
    hasCurrentSavedDestination() {
        const html = this.currentReviewHtml();
        const saved = this.persistenceState();
        return !!(html && [saved.post, saved.shortcode].some((item) => item && item.html === html));
    }

    /**
     * Paint the same evidence-led state on Review and Evaluate. A destination
     * appears only after its endpoint returned a valid identifier. Comparing
     * the HTML snapshot marks later Review edits as unsaved without guessing.
     */
    renderPersistenceState() {
        if (!this.root) {
            return;
        }
        const html = this.currentReviewHtml();
        const saved = this.persistenceState();
        const destinations = [saved.post, saved.shortcode].filter(Boolean);
        const current = destinations.filter((item) => html && item.html === html);
        const hasSaved = destinations.length > 0;
        const hasCurrent = current.length > 0;

        this.root.querySelectorAll('[data-save-status-card]').forEach((card) => {
            card.classList.toggle('is-unsaved', !hasSaved);
            card.classList.toggle('has-unsaved-changes', hasSaved && !hasCurrent);
            card.classList.toggle('is-saved', hasCurrent);
            const badge = card.querySelector('[data-save-status-badge]');
            const message = card.querySelector('[data-save-status-message]');
            if (!hasSaved) {
                badge.textContent = 'Not saved';
                message.textContent = 'Not saved. This article currently exists only in AI-Scribe.';
            } else if (!hasCurrent) {
                badge.textContent = 'Unsaved changes';
                message.textContent = 'Changes made since the last save exist only in AI-Scribe. Update a destination before finishing if you want to keep them.';
            } else {
                const labels = current.map((item) => item.type === 'post'
                    ? (item.status === 'publish' ? 'published post' : 'WordPress draft')
                    : 'shortcode');
                badge.textContent = 'Saved';
                message.textContent = `The current article is saved as ${labels.join(' and ')}.`;
            }

            const list = card.querySelector('[data-save-destinations]');
            list.hidden = !hasSaved;
            ['post', 'shortcode'].forEach((type) => {
                const row = card.querySelector(`[data-save-destination="${type}"]`);
                const item = saved[type];
                row.hidden = !item;
                if (!item) return;
                const label = row.querySelector('[data-save-destination-label]');
                if (label) label.textContent = item.status === 'publish' ? 'Published post' : 'WordPress draft';
                row.querySelector('[data-save-destination-state]').textContent = item.html === html
                    ? 'Current version'
                    : 'Older version — update needed';
            });

            const shortcodeButton = card.querySelector('[data-action="save-shortcode"]');
            if (shortcodeButton) {
                const label = shortcodeButton.querySelector('[data-save-label]');
                if (label) label.textContent = saved.shortcode ? 'Save as New Shortcode' : 'Save as Shortcode';
            }
        });

        const finish = this.root.querySelector('[data-testid="complete"]');
        const finishLabel = finish && finish.querySelector('[data-complete-label]');
        if (finish && finishLabel && Date.now() >= this.discardConfirmUntil) {
            finish.classList.remove('is-discard-confirm');
            finishLabel.textContent = hasCurrent ? 'Finish & Start New' : 'Finish Without Saving';
        }

        this.root.querySelectorAll('[data-saved-post-link]').forEach((link) => {
            link.hidden = !(saved.post && saved.post.editLink);
            if (saved.post && saved.post.editLink) link.href = saved.post.editLink;
        });
        this.root.querySelectorAll('[data-saved-shortcode-note]').forEach((note) => {
            note.hidden = !(saved.shortcode && saved.shortcode.code);
            note.textContent = saved.shortcode && saved.shortcode.code ? saved.shortcode.code : '';
        });
    }

    /** Require an explicit second action before abandoning an unsaved version. */
    confirmDiscardAndStartAgain(button) {
        if (Date.now() < this.discardConfirmUntil) {
            this.startAgain();
            return;
        }
        this.discardConfirmUntil = Date.now() + 8000;
        button.classList.add('is-discard-confirm');
        const label = button.querySelector('[data-complete-label]');
        if (label) label.textContent = 'Discard & Start New';
        const message = 'The current version has not been saved. Select “Discard & Start New” to confirm.';
        const evaluate = this.registry.get(11);
        if (evaluate) evaluate.announce(message);
        this.notify(message, 'warning');
        window.setTimeout(() => {
            if (Date.now() >= this.discardConfirmUntil) this.renderPersistenceState();
        }, 8100);
    }

    async savePost(kind, button) {
        const source = this.articleSaveSource();
        if (!source) {
            return;
        }
        if (!this.updateReviewActions()) {
            source.view.showError(
                new Error('There is nothing to publish yet — generate the article first.'),
                null
            );
            return;
        }
        const conversationId = this.appState.getStateSlice('conversationId') || '';
        WizardFlowController.setButtonBusy(button, true);
        try {
            // Contract §7: server assembles from selections; send final edits.
            const fields = {
                post_status: kind === 'draft' ? 'draft' : 'publish',
                content_html: source.html
            };
            this.preparePublishingDetails();
            const publishing = this.appState.getStateSlice('publishingDetails') || {};
            fields.category_name = publishing.category || '';
            fields.tag_names = publishing.tags || '';
            // Owner spec: the first generated image is the featured image.
            // Quill embeds carry no wp-image-N class, so the attachment id
            // has to travel explicitly or the server has nothing to set.
            const firstImage = this.galleryImages().find((image) => image.attachment_id > 0);
            if (firstImage && this.appState.getStateSlice('featuredImageRemoved') !== true) {
                fields.featured_attachment_id = firstImage.attachment_id;
            }
            const data = await this.api.savePost(conversationId, fields);
            const postId = data && parseInt(data.post_id, 10);
            if (!postId) {
                throw new Error('WordPress did not confirm a saved post. Nothing has been marked as saved.');
            }
            // Server contract: `updated` distinguishes updating the post this
            // conversation already created from creating a new one, and
            // `seo.message` says where the meta title/description went.
            const updated = !!(data && data.updated);
            let message;
            if (kind === 'draft') {
                message = updated
                    ? 'Existing draft updated. Use "View saved post" to open it in a new tab.'
                    : 'Draft saved. Use "View saved post" to open it in a new tab.';
            } else {
                message = updated
                    ? 'Saved draft published. Use "View saved post" to open it in a new tab.'
                    : 'Post published. Use "View saved post" to open it in a new tab.';
            }
            const seoMessage = data && data.seo && data.seo.message ? String(data.seo.message) : '';
            const saved = this.persistenceState();
            this.appState.setStateSlice('persistence', Object.assign({}, saved, {
                post: {
                    type: 'post',
                    id: postId,
                    status: kind,
                    editLink: data.edit_link || '',
                    html: fields.content_html
                }
            }));
            source.view.announce(seoMessage ? `${message} ${seoMessage}` : message);
            this.notify(message, 'success');
            if (seoMessage) {
                this.notify(seoMessage, 'info');
            }
            const assignment = this.publishingAssignmentMessage(publishing, data && data.publishing);
            const assignmentStatus = document.querySelector('[data-publishing-result]');
            if (assignmentStatus) {
                assignmentStatus.textContent = assignment.message;
                assignmentStatus.classList.toggle('is-warning', assignment.type === 'warning');
                assignmentStatus.classList.toggle('is-success', assignment.type === 'success');
            }
            this.notify(assignment.message, assignment.type);
            if (data && data.edit_link) {
                document.querySelectorAll('[data-saved-post-link]').forEach((link) => {
                    link.href = data.edit_link;
                    link.hidden = false;
                });
            }
            this.renderPersistenceState();
        } catch (error) {
            source.view.showError(error, () => this.savePost(kind, button));
        } finally {
            WizardFlowController.setButtonBusy(button, false);
            // Re-enable through the guard, never unconditionally.
            this.updateReviewActions();
        }
    }

    /**
     * Save the reviewed article into the {prefix}article_builder table so it
     * can be embedded anywhere with
     * [article_builder_generate_data template_id="N"] (2.6.2 headline
     * page-builder workflow; server side was already complete).
     */
    async saveShortcode(button) {
        const source = this.articleSaveSource();
        if (!source) {
            return;
        }
        if (!this.updateReviewActions()) {
            source.view.showError(
                new Error('There is nothing to save yet — generate the article first.'),
                null
            );
            return;
        }
        WizardFlowController.setButtonBusy(button, true);
        try {
            const data = await this.api.saveShortcode({
                articleVal: source.html
            });
            const shortcodeId = data && parseInt(data.shortcode_id, 10);
            if (!shortcodeId) {
                throw new Error('WordPress did not confirm a saved shortcode. Nothing has been marked as saved.');
            }
            const code = `[article_builder_generate_data template_id="${shortcodeId}"]`;
            const saved = this.persistenceState();
            this.appState.setStateSlice('persistence', Object.assign({}, saved, {
                shortcode: {
                    type: 'shortcode', id: shortcodeId, code, html: source.html
                }
            }));
            document.querySelectorAll('[data-saved-shortcode-note]').forEach((note) => {
                note.textContent = code;
                note.hidden = false;
            });
            const message = 'Shortcode saved. Paste it into any post or page, or manage it under Saved Shortcodes.';
            source.view.announce(message);
            this.notify(message, 'success');
            this.renderPersistenceState();
        } catch (error) {
            source.view.showError(error, () => this.saveShortcode(button));
        } finally {
            WizardFlowController.setButtonBusy(button, false);
            // Re-enable through the guard, never unconditionally.
            this.updateReviewActions();
        }
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = WizardFlowController;
}
