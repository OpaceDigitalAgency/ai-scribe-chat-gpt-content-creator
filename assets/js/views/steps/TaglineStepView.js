/**
 * Step 5 — Tagline. Single-select cards from typed `items.taglines: string[]`.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global ChoiceStepView */
/* exported TaglineStepView */

class TaglineStepView extends ChoiceStepView {
    constructor(appState) {
        super(5, appState, {
            contentType: 'taglines',
            containerSelector: '#tagline-options',
            multiSelect: false
        });
    }

    /** @param {{taglines: string[]}} parsed */
    renderTyped(parsed, options = {}) {
        // A tagline is one decision, not a five-card comparison. "Try
        // another" replaces the suggestion instead of growing duplicates.
        this.render(((parsed && parsed.taglines) || []).slice(0, 1), { append: false });
        if (this.container) {
            const only = this.container.querySelector('.option-card');
            if (only && !only.classList.contains('selected')) only.click();
        }
        this.updatePlacementState();
    }

    applySelectionPolicy(card) {
        super.applySelectionPolicy(card);
        this.updatePlacementState();
    }

    updatePlacementState() {
        const placement = this.panel && this.panel.querySelector('[data-testid="tagline-placement"]');
        if (placement) {
            placement.disabled = !this.hasSelection();
            placement.classList.toggle('is-disabled', !this.hasSelection());
        }
    }

    /** Contract §3 selection key. */
    getSelectionKey() {
        return 'tagline';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = TaglineStepView;
}
