/**
 * ChoiceStepView — shared base for card-selection steps (titles, keywords,
 * outline, tagline, Q&A). Renders typed arrays through CardRenderer, then
 * decorates the cards for accessibility and enforces the selection policy
 * (single vs multi) with event delegation — no per-card inline handlers.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global BaseStepView, CardRenderer */
/* exported ChoiceStepView */

class ChoiceStepView extends BaseStepView {
    /**
     * @param {number} step
     * @param {Object} appState
     * @param {Object} config { contentType, containerId, multiSelect }
     */
    constructor(step, appState, config = {}) {
        super(step, appState);
        this.contentType = config.contentType || 'generic';
        this.multiSelect = config.multiSelect === true;
        this.container = this.panel
            ? this.panel.querySelector(config.containerSelector || '[data-testid="options-grid"]')
            : null;
        this.items = [];
        this.skipped = false;
        this.selectionStatus = this.panel
            ? this.panel.querySelector('[data-testid="choice-selection-status"]')
            : null;
    }

    /**
     * Skip this optional step (2.6.2 parity): clears any selection so the
     * server records an explicit empty choice and prompt assembly drops the
     * related clauses.
     */
    skip() {
        this.skipped = true;
        if (this.container) {
            this.container
                .querySelectorAll('.option-card.selected, .keyword-card.selected')
                .forEach((card) => card.classList.remove('selected'));
        }
        this.setNextEnabled(true);
    }

    bindPanelEvents() {
        // Delegated selection handling: click + keyboard (Enter/Space).
        this.panel.addEventListener('click', (e) => {
            const card = e.target.closest('.option-card, .keyword-card');
            if (card && this.panel.contains(card)) {
                // CardRenderer's own listener has already toggled `selected`;
                // we enforce policy and sync accessibility state afterwards.
                this.applySelectionPolicy(card);
            }
        });
        this.panel.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            const card = e.target.closest('.option-card, .keyword-card');
            if (card) {
                e.preventDefault();
                card.click();
            }
        });
        this.panel.addEventListener('click', (e) => {
            const bulk = e.target.closest('[data-choice-action]');
            if (!bulk || !this.container || !this.multiSelect) {
                return;
            }
            const selected = bulk.getAttribute('data-choice-action') === 'select-all';
            this.container.querySelectorAll('.option-card, .keyword-card').forEach((card) => {
                card.classList.toggle('selected', selected);
                card.setAttribute('aria-checked', selected ? 'true' : 'false');
                const check = card.querySelector('i[data-lucide="check"]');
                if (check) check.style.display = selected ? 'block' : 'none';
            });
            this.afterSelectionChange();
        });
    }

    /**
     * Render typed items. `items` is always an array of typed values
     * (strings or objects per the API contract) — never regex'd prose.
     */
    render(items, options = {}) {
        this.skipped = false;
        const existing = new Set((options.append ? this.items : []).map((item) =>
            JSON.stringify(item).toLocaleLowerCase()
        ));
        const freshItems = items.filter((item) => {
            const identity = JSON.stringify(item).toLocaleLowerCase();
            if (existing.has(identity)) return false;
            existing.add(identity);
            return true;
        });
        this.items = options.append ? this.items.concat(freshItems) : freshItems.slice();
        if (!this.container) {
            return;
        }
        if (!freshItems || freshItems.length === 0) {
            if (!options.append) {
                this.showEmpty();
            }
            return;
        }
        CardRenderer.createCards(freshItems, this.contentType, this.container, {
            appendMode: options.append === true,
            step: this.step
        });
        this.decorateCards();
        this.showReady();
        this.afterSelectionChange();
    }

    /** Make every card keyboard-selectable with proper semantics. */
    decorateCards() {
        const cards = this.container.querySelectorAll('.option-card, .keyword-card');
        cards.forEach((card, index) => {
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', this.multiSelect ? 'checkbox' : 'radio');
            card.setAttribute('aria-checked', card.classList.contains('selected') ? 'true' : 'false');
            card.setAttribute('data-testid', 'result-card');
            if (!card.hasAttribute('data-index')) {
                card.setAttribute('data-index', String(index));
            }
        });
        this.container.setAttribute('role', this.multiSelect ? 'group' : 'radiogroup');
        if (this.selectionStatus) {
            this.container.setAttribute('aria-describedby', 'choice-status-' + this.step);
            this.selectionStatus.id = 'choice-status-' + this.step;
        }
    }

    applySelectionPolicy(clickedCard) {
        if (!this.multiSelect && clickedCard.classList.contains('selected')) {
            // Deselect siblings for single-select steps.
            this.container
                .querySelectorAll('.option-card.selected, .keyword-card.selected')
                .forEach((card) => {
                    if (card !== clickedCard) {
                        card.classList.remove('selected');
                        const check = card.querySelector('i[data-lucide="check"]');
                        if (check) {
                            check.style.display = 'none';
                        }
                    }
                });
        }
        this.container
            .querySelectorAll('.option-card, .keyword-card')
            .forEach((card) => {
                card.setAttribute('aria-checked', card.classList.contains('selected') ? 'true' : 'false');
            });
        this.afterSelectionChange();
    }

    afterSelectionChange() {
        const selected = this.selectedIndexes().length;
        const total = this.container
            ? this.container.querySelectorAll('.option-card, .keyword-card').length
            : 0;
        this.setNextEnabled(selected > 0);
        this.persistSelection();
        if (this.selectionStatus) {
            this.selectionStatus.textContent = selected > 0
                ? (this.multiSelect ? `${selected} of ${total} selected` : '1 selected')
                : (this.multiSelect ? `Select at least one to continue · 0 of ${total} selected` : 'Select one to continue');
        }
    }

    hasSelection() {
        return !!this.container
            && this.container.querySelector('.option-card.selected, .keyword-card.selected') !== null;
    }

    selectedIndexes() {
        if (!this.container) {
            return [];
        }
        return Array.from(
            this.container.querySelectorAll('.option-card.selected, .keyword-card.selected')
        ).map((card) => parseInt(card.getAttribute('data-index'), 10))
            .filter((n) => !Number.isNaN(n));
    }

    getSelection() {
        if (this.skipped) {
            // Explicit empty choice: '' (single) persists a blank selection
            // so the server's skip handling sees the key; [] for multi.
            return this.multiSelect ? [] : '';
        }
        const indexes = this.selectedIndexes();
        const picked = indexes.map((i) => this.items[i]).filter((v) => v !== undefined);
        return this.multiSelect ? picked : (picked[0] || null);
    }

    persistSelection() {
        if (!this.appState) {
            return;
        }
        const stepData = this.appState.getStateSlice('stepData') || {};
        stepData[this.step] = stepData[this.step] || {};
        stepData[this.step].selection = this.getSelection();
        this.appState.setStateSlice('stepData', stepData);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChoiceStepView;
}
