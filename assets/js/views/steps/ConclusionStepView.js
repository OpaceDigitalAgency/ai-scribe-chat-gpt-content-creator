/**
 * Step 7 — Conclusion. Long-form streaming prose. The engine thread carries
 * the FULL BODY context (2.6.2 wrote conclusions blind — fixed in v3).
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global StreamingStepView */
/* exported ConclusionStepView */

class ConclusionStepView extends StreamingStepView {
    constructor(appState) {
        super(7, appState, { proseSelector: '#conclusion-stream-output' });
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ConclusionStepView;
}
