/**
 * StepViewRegistry — constructs one small view per wizard step plus the
 * Express screen. main.js asks the registry, never the DOM, for a view.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global TitlesStepView, KeywordsStepView, OutlineStepView, IntroStepView,
   TaglineStepView, BodyStepView, ConclusionStepView, QnaStepView,
   SeoMetaStepView, ReviewStepView, EvaluateStepView, ExpressView */
/* exported StepViewRegistry */

class StepViewRegistry {
    constructor(appState) {
        this.appState = appState;
        this.views = new Map();
        const definitions = [
            [1, TitlesStepView],
            [2, KeywordsStepView],
            [3, OutlineStepView],
            [4, IntroStepView],
            [5, TaglineStepView],
            [6, BodyStepView],
            [7, ConclusionStepView],
            [8, QnaStepView],
            [9, SeoMetaStepView],
            [10, ReviewStepView],
            [11, EvaluateStepView],
            ['express', ExpressView]
        ];
        definitions.forEach(([key, ViewClass]) => {
            if (typeof ViewClass === 'function') {
                const view = new ViewClass(appState);
                if (view.isAvailable()) {
                    this.views.set(key, view);
                }
            }
        });
        this.paintIdleStates();
    }

    /**
     * Give every panel that has not run yet something to say. Hydration and
     * generation both move the panel out of `idle`, which clears the box, so
     * this only ever paints a genuinely untouched step.
     */
    paintIdleStates() {
        this.views.forEach((view) => {
            if (view.panel && view.panel.getAttribute('data-state') === 'idle'
                && typeof view.showIdle === 'function') {
                view.showIdle();
            }
        });
    }

    get(step) {
        return this.views.get(step) || null;
    }

    /** Long-form streaming steps (contract §8: only these may stream).
     *  Step 11 returns typed checks via run_step and no longer streams. */
    static isStreamingStep(step) {
        return [4, 6, 7].indexOf(step) !== -1;
    }

    /** Choice steps returning typed structured outputs (contract §2). */
    static isChoiceStep(step) {
        return [1, 2, 3, 5, 8, 9].indexOf(step) !== -1;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = StepViewRegistry;
}
