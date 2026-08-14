/**
 * Step 9 — SEO Meta. Combined meta title + description form fed by typed
 * `items.meta: {title, description}`. Fields stay editable; character
 * counters warn against SERP truncation.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global BaseStepView */
/* exported SeoMetaStepView */

class SeoMetaStepView extends BaseStepView {
    static TITLE_LIMIT = 60;
    static TITLE_MIN = 50;
    static DESCRIPTION_LIMIT = 160;
    static DESCRIPTION_MIN = 120;

    constructor(appState) {
        super(9, appState);
        if (this.panel) {
            this.titleInput = this.panel.querySelector('#meta-title');
            this.descriptionInput = this.panel.querySelector('#meta-description');
            this.titleCount = this.panel.querySelector('[data-testid="meta-title-count"]');
            this.descriptionCount = this.panel.querySelector('[data-testid="meta-description-count"]');
            this.titleGuidance = this.panel.querySelector('[data-testid="meta-title-guidance"]');
            this.descriptionGuidance = this.panel.querySelector('[data-testid="meta-description-guidance"]');
            this.separatorGuidance = this.panel.querySelector('[data-testid="meta-separator-guidance"]');
            this.keywordGuidance = this.panel.querySelector('[data-testid="meta-keyword-guidance"]');
            this.liveRegion = this.panel.querySelector('[data-testid="seo-meta-live"]');
            this.optimisePanel = this.panel.querySelector('[data-testid="meta-optimise-panel"]');
            this.comparison = this.panel.querySelector('[data-testid="meta-optimise-comparison"]');
            this.originalTitle = this.panel.querySelector('[data-testid="meta-original-title"]');
            this.originalDescription = this.panel.querySelector('[data-testid="meta-original-description"]');
            this.suggestedTitle = this.panel.querySelector('[data-testid="meta-suggested-title"]');
            this.suggestedDescription = this.panel.querySelector('[data-testid="meta-suggested-description"]');
            this.optimiseStatus = this.panel.querySelector('[data-testid="meta-optimise-status"]');
        }
        this.originalMetadata = null;
        this.optimisedMetadata = null;
        this.optimisationApplied = false;
    }

    bindPanelEvents() {
        this.panel.addEventListener('input', (e) => {
            if (e.target && (e.target.id === 'meta-title' || e.target.id === 'meta-description')) {
                this.resetOptimisationState();
                this.updateCounters();
                this.updateGuidance();
                this.setNextEnabled(this.hasContent());
                this.persist();
            }
        });
    }

    /** Contract §3 selection key. */
    getSelectionKey() {
        return 'meta';
    }

    /** Skip SEO meta (2.6.2 parity): continue with empty meta fields. */
    skip() {
        if (this.titleInput) {
            this.titleInput.value = '';
        }
        if (this.descriptionInput) {
            this.descriptionInput.value = '';
        }
        this.updateCounters();
        this.updateGuidance();
        this.persist();
        this.setNextEnabled(true);
    }

    /** @param {{meta: {title:string, description:string}}} parsed */
    renderTyped(parsed) {
        this.resetOptimisationState();
        const meta = (parsed && parsed.meta) || {};
        if (this.titleInput) {
            this.titleInput.value = meta.title || '';
        }
        if (this.descriptionInput) {
            this.descriptionInput.value = meta.description || '';
        }
        this.updateCounters();
        this.updateGuidance();
        this.showReady();
        this.setNextEnabled(this.hasContent());
        this.persist();
    }

    updateCounters() {
        this.updateCounter(this.titleCount, this.titleInput, SeoMetaStepView.TITLE_MIN, SeoMetaStepView.TITLE_LIMIT);
        this.updateCounter(this.descriptionCount, this.descriptionInput, SeoMetaStepView.DESCRIPTION_MIN, SeoMetaStepView.DESCRIPTION_LIMIT);
    }

    /**
     * Live SERP-length guidance: green inside the recommended range
     * (50-60 title / 120-160 description), amber when short, red when the
     * value would be truncated in results.
     */
    updateCounter(counterEl, inputEl, min, max) {
        if (!counterEl || !inputEl) {
            return;
        }
        const length = inputEl.value.length;
        counterEl.textContent = `${length} character${length === 1 ? '' : 's'}`;
        counterEl.classList.toggle('over-limit', length > max);
        counterEl.classList.toggle('in-range', length >= min && length <= max);
        counterEl.classList.toggle('under-range', length > 0 && length < min);
    }

    /**
     * Deterministic checks only. Character ranges are display guidance, not
     * fixed search-engine limits, and relevance/uniqueness remain human calls.
     */
    updateGuidance() {
        const title = this.titleInput ? this.titleInput.value.trim() : '';
        const description = this.descriptionInput ? this.descriptionInput.value.trim() : '';
        const titleResult = SeoMetaStepView.lengthGuidance(
            title,
            SeoMetaStepView.TITLE_MIN,
            SeoMetaStepView.TITLE_LIMIT,
            'title'
        );
        const descriptionResult = SeoMetaStepView.lengthGuidance(
            description,
            SeoMetaStepView.DESCRIPTION_MIN,
            SeoMetaStepView.DESCRIPTION_LIMIT,
            'description'
        );
        this.renderGuidance(this.titleGuidance, titleResult);
        this.renderGuidance(this.descriptionGuidance, descriptionResult);
        if (this.optimisePanel) {
            const overGuide = title.length > SeoMetaStepView.TITLE_LIMIT
                || description.length > SeoMetaStepView.DESCRIPTION_LIMIT;
            // Applying a valid suggestion must not hide the parent containing
            // Undo. Retain a compact applied state until Undo or a real field
            // edit/regeneration clears it.
            this.optimisePanel.hidden = !overGuide && !this.optimisationApplied;
            const optimiseButton = this.panel.querySelector('[data-action="optimise-meta-length"]');
            if (optimiseButton) optimiseButton.hidden = !overGuide;
        }

        const separatorResult = SeoMetaStepView.separatorGuidance(title);
        this.renderGuidance(this.separatorGuidance, separatorResult);

        let keywordResults = [];
        if (this.keywordGuidance) {
            keywordResults = this.selectedKeywords().map((keyword, index) => (
                SeoMetaStepView.keywordCoverage(title, description, keyword, index === 0)
            ));
            this.renderKeywordGuidance(keywordResults);
        }
        if (this.liveRegion) {
            this.liveRegion.textContent = [titleResult, descriptionResult, separatorResult, ...keywordResults]
                .filter(Boolean)
                .map((result) => `${result.label}: ${result.message}`)
                .join(' ');
        }
    }

    showOptimisePending(pending) {
        const button = this.panel && this.panel.querySelector('[data-action="optimise-meta-length"]');
        if (button) {
            button.disabled = pending;
            button.setAttribute('aria-busy', pending ? 'true' : 'false');
        }
        if (this.optimiseStatus && pending) {
            this.optimiseStatus.textContent = 'Asking the selected model to shorten the overlength metadata…';
            this.optimiseStatus.dataset.status = 'pending';
        }
    }

    /** Discard a comparison as soon as either source field changes/regenerates. */
    resetOptimisationState() {
        this.originalMetadata = null;
        this.optimisedMetadata = null;
        this.optimisationApplied = false;
        if (this.comparison) this.comparison.hidden = true;
        const undo = this.panel && this.panel.querySelector('[data-action="undo-meta-optimisation"]');
        if (undo) undo.hidden = true;
        if (this.optimiseStatus) {
            this.optimiseStatus.textContent = '';
            delete this.optimiseStatus.dataset.status;
        }
    }

    showOptimiseError(error) {
        this.showOptimisePending(false);
        if (this.optimiseStatus) {
            this.optimiseStatus.textContent = error && error.message
                ? `Metadata could not be optimised: ${error.message}`
                : 'Metadata could not be optimised. Please try again.';
            this.optimiseStatus.dataset.status = 'error';
        }
    }

    showOptimiseSuggestion(meta) {
        this.originalMetadata = this.getSelection();
        this.optimisedMetadata = {
            title: String(meta && meta.title || '').trim(),
            description: String(meta && meta.description || '').trim().replace(/\s*\.{3,}\s*$/, '')
        };
        if (this.originalTitle) this.originalTitle.textContent = this.originalMetadata.title;
        if (this.originalDescription) this.originalDescription.textContent = this.originalMetadata.description;
        if (this.suggestedTitle) this.suggestedTitle.textContent = this.optimisedMetadata.title;
        if (this.suggestedDescription) this.suggestedDescription.textContent = this.optimisedMetadata.description;
        if (this.comparison) this.comparison.hidden = false;
        if (this.optimiseStatus) {
            this.optimiseStatus.textContent = 'Suggestion ready. Compare it with your original before applying.';
            this.optimiseStatus.dataset.status = 'good';
        }
        this.showOptimisePending(false);
    }

    applyOptimiseSuggestion() {
        if (!this.optimisedMetadata) return;
        this.titleInput.value = this.optimisedMetadata.title;
        this.descriptionInput.value = this.optimisedMetadata.description;
        this.updateCounters();
        this.optimisationApplied = true;
        this.updateGuidance();
        this.persist();
        const undo = this.panel.querySelector('[data-action="undo-meta-optimisation"]');
        if (undo) undo.hidden = false;
        if (this.comparison) this.comparison.hidden = true;
        if (this.optimiseStatus) {
            this.optimiseStatus.textContent = 'Optimised metadata applied. Undo remains available until you edit or regenerate either field.';
            this.optimiseStatus.dataset.status = 'good';
        }
    }

    keepOriginal() {
        this.originalMetadata = null;
        this.optimisedMetadata = null;
        this.optimisationApplied = false;
        if (this.comparison) this.comparison.hidden = true;
        if (this.optimiseStatus) this.optimiseStatus.textContent = 'Original metadata kept.';
        this.updateGuidance();
    }

    undoOptimisation() {
        if (!this.originalMetadata) return;
        this.titleInput.value = this.originalMetadata.title;
        this.descriptionInput.value = this.originalMetadata.description;
        this.originalMetadata = null;
        this.optimisedMetadata = null;
        this.optimisationApplied = false;
        this.updateCounters();
        this.updateGuidance();
        this.persist();
        const undo = this.panel.querySelector('[data-action="undo-meta-optimisation"]');
        if (undo) undo.hidden = true;
        if (this.comparison) this.comparison.hidden = true;
        if (this.optimiseStatus) this.optimiseStatus.textContent = 'Original metadata restored.';
    }

    renderGuidance(element, result) {
        if (!element || !result) {
            return;
        }
        element.textContent = `${result.label}: ${result.message}`;
        element.setAttribute('data-status', result.status);
    }

    renderKeywordGuidance(results) {
        if (!this.keywordGuidance) {
            return;
        }
        this.keywordGuidance.replaceChildren();

        const heading = document.createElement('strong');
        heading.className = 'seo-meta-keyword-heading';
        heading.textContent = 'Selected keyword coverage';
        this.keywordGuidance.appendChild(heading);

        if (!results.length) {
            const empty = document.createElement('p');
            empty.className = 'seo-meta-keyword-empty';
            empty.dataset.status = 'warning';
            empty.textContent = 'No selected keyword is available to check. Confirm relevance manually.';
            this.keywordGuidance.appendChild(empty);
            this.keywordGuidance.dataset.status = 'warning';
            return;
        }

        const list = document.createElement('ul');
        list.className = 'seo-meta-keyword-list';
        results.forEach((result) => {
            const item = document.createElement('li');
            item.dataset.status = result.status;
            item.textContent = `${result.label}: ${result.message}`;
            list.appendChild(item);
        });
        this.keywordGuidance.appendChild(list);
        this.keywordGuidance.dataset.status = results.every((result) => result.status === 'good') ? 'good' : 'warning';
    }

    selectedKeywords() {
        if (!this.appState) {
            return [];
        }
        const stepData = this.appState.getStateSlice('stepData') || {};
        const keywords = stepData[2] && Array.isArray(stepData[2].selection)
            ? stepData[2].selection
            : [];
        return keywords.map((keyword) => String(keyword).trim()).filter(Boolean);
    }

    static lengthGuidance(value, min, max, type) {
        const length = String(value || '').length;
        const label = type === 'title' ? 'Title length' : 'Description length';
        if (length === 0) {
            return { status: type === 'title' ? 'error' : 'warning', label, message: 'Empty.' };
        }
        if (length > max) {
            return { status: 'warning', label, message: `Above the ${min}–${max} character display guide; it may be shortened in search results.` };
        }
        if (length < min) {
            return { status: 'warning', label, message: `Below the ${min}–${max} character display guide. Use extra space only when it adds useful detail.` };
        }
        return { status: 'good', label, message: `Within the ${min}–${max} character display guide.` };
    }

    static separatorGuidance(title) {
        const value = String(title || '').trim();
        const parts = value.split(' | ');
        const rawPipes = (value.match(/\|/g) || []).length;
        const valid = parts.length >= 2
            && rawPipes === parts.length - 1
            && parts.every((part) => part.trim().length > 0);
        if (valid) {
            return {
                status: 'good',
                label: 'Title separator',
                message: 'Uses the required spaced pipe ( | ).'
            };
        }
        return {
            status: 'warning',
            label: 'Title separator',
            message: 'Separate title components with a spaced pipe ( | ), not a colon, hyphen, dash or slash.'
        };
    }

    static normaliseForMatch(value) {
        return String(value || '')
            .toLocaleLowerCase()
            .normalize('NFKC')
            .replace(/[^\p{L}\p{N}]+/gu, ' ')
            .trim()
            .replace(/\s+/g, ' ');
    }

    static meaningfulTokens(value) {
        const stopWords = new Set([
            'a', 'an', 'and', 'as', 'at', 'by', 'for', 'from', 'in', 'of', 'on', 'or', 'the', 'to', 'with',
            'best', 'guide', 'how', 'improve', 'tips'
        ]);
        const tokens = SeoMetaStepView.normaliseForMatch(value).split(' ').filter(Boolean);
        const meaningful = tokens.filter((token) => !stopWords.has(token));
        return meaningful.length ? meaningful : tokens;
    }

    static fieldCoverage(value, keyword) {
        const field = SeoMetaStepView.normaliseForMatch(value);
        const phrase = SeoMetaStepView.normaliseForMatch(keyword);
        if (!phrase || !field) {
            return 'absent';
        }
        if (` ${field} `.includes(` ${phrase} `)) {
            return 'exact';
        }

        const fieldTokens = field.split(' ');
        const keywordTokens = SeoMetaStepView.meaningfulTokens(keyword);
        if (keywordTokens.every((token) => fieldTokens.includes(token))) {
            return 'combined';
        }

        let cursor = -1;
        let orderedMatches = 0;
        keywordTokens.forEach((token) => {
            const found = fieldTokens.indexOf(token, cursor + 1);
            if (found !== -1) {
                orderedMatches += 1;
                cursor = found;
            }
        });
        if (orderedMatches === keywordTokens.length) {
            return 'combined';
        }
        const presentTokens = keywordTokens.filter((token) => fieldTokens.includes(token)).length;
        if (presentTokens >= Math.max(1, Math.ceil(keywordTokens.length * 0.5))) {
            return 'partial';
        }
        return 'absent';
    }

    static keywordCoverage(title, description, keyword, isPrimary = false) {
        if (!keyword) {
            return {
                status: 'warning',
                label: isPrimary ? 'Primary keyword' : 'Secondary keyword',
                message: 'No selected keyword is available to check. Confirm relevance manually.'
            };
        }
        const titleCoverage = SeoMetaStepView.fieldCoverage(title, keyword);
        const descriptionCoverage = SeoMetaStepView.fieldCoverage(description, keyword);
        const primaryPass = titleCoverage === 'exact' && descriptionCoverage === 'exact';
        const secondaryPass = !['partial', 'absent'].includes(titleCoverage)
            && !['partial', 'absent'].includes(descriptionCoverage);
        const status = (isPrimary ? primaryPass : secondaryPass) ? 'good' : 'warning';
        const role = isPrimary ? 'Primary' : 'Secondary';
        const instruction = isPrimary
            ? (primaryPass ? 'Exact wording is present in both.' : 'The primary should be exact in both fields.')
            : (secondaryPass ? 'Strong coverage in both.' : 'Improve exact or intelligently combined coverage where it remains natural.');
        return {
            status,
            label: `${role} “${keyword}”`,
            message: `title ${titleCoverage}; description ${descriptionCoverage}. ${instruction}`,
            titleCoverage,
            descriptionCoverage,
            keyword,
            primary: isPrimary
        };
    }

    /** Back-compatible single-keyword helper retained for existing integrations. */
    static keywordGuidance(title, description, keyword) {
        return SeoMetaStepView.keywordCoverage(title, description, keyword, true);
    }

    hasContent() {
        return !!(this.titleInput && this.titleInput.value.trim());
    }

    getSelection() {
        return {
            title: this.titleInput ? this.titleInput.value.trim() : '',
            description: this.descriptionInput ? this.descriptionInput.value.trim() : ''
        };
    }

    persist() {
        if (!this.appState) {
            return;
        }
        const stepData = this.appState.getStateSlice('stepData') || {};
        stepData[9] = stepData[9] || {};
        stepData[9].selection = this.getSelection();
        this.appState.setStateSlice('stepData', stepData);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SeoMetaStepView;
}
