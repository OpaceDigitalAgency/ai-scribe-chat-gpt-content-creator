/**
 * Step 4 — Introduction. Long-form streaming prose (full-context thread:
 * the engine sends title + keywords + OUTLINE, fixing the 2.6.2 blind spot).
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global StreamingStepView */
/* exported IntroStepView */

class IntroStepView extends StreamingStepView {
    constructor(appState) {
        super(4, appState, { proseSelector: '#intro-stream-output' });
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = IntroStepView;
}
