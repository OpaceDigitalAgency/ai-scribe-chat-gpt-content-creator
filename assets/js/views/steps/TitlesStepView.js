/**
 * Step 1 — Titles. Single-select cards from typed `items.titles: string[]`.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global ChoiceStepView */
/* exported TitlesStepView */

class TitlesStepView extends ChoiceStepView {
    constructor(appState) {
        super(1, appState, {
            contentType: 'titles',
            containerSelector: '#titles-options',
            multiSelect: false
        });
        this.topicInput = this.panel ? this.panel.querySelector('[data-testid="idea-input"]') : null;
    }

    /** @param {{titles: string[]}} items */
    renderTyped(items, options = {}) {
        this.render((items && items.titles) || [], options);
    }

    getTopic() {
        return this.topicInput ? this.topicInput.value.trim() : '';
    }

    /** Contract §3 selection key. */
    getSelectionKey() {
        return 'title';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = TitlesStepView;
}
