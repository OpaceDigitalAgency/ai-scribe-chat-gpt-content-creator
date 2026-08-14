/**
 * Step 2 — Keywords. Multi-select cards from typed keyword evidence objects.
 * Legacy strings remain readable, but never regain their old unverified pipe
 * metrics. The saved selection is always an array of bare keyword phrases.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global ChoiceStepView */
/* exported KeywordsStepView */

class KeywordsStepView extends ChoiceStepView {
    constructor(appState) {
        super(2, appState, {
            contentType: 'keywords',
            containerSelector: '#keywords-options',
            multiSelect: true
        });
        this.compareLink = this.panel
            ? this.panel.querySelector('[data-keyword-action="compare-trends"]')
            : null;
        this.loadWarning = this.panel
            ? this.panel.querySelector('[data-testid="keyword-load-warning"]')
            : null;
    }

    /** @param {{keywords: Array<string|{keyword:string,role:string,demand_band:string,estimate_basis:string}>}} parsed */
    renderTyped(parsed, options = {}) {
        this.render((parsed && parsed.keywords) || [], options);
        if (!this.hasSelection() && this.container) {
            const first = this.container.querySelector('.keyword-card');
            if (first) first.click();
        }
    }

    getSelection() {
        if (this.skipped) {
            return [];
        }
        const indexes = this.selectedIndexes();
        return indexes
            .map((i) => {
                const item = this.items[i];
                if (typeof item === 'string') {
                    return item.split(' | ')[0].trim();
                }
                return item && typeof item.keyword === 'string' ? item.keyword.trim() : null;
            })
            .filter(Boolean);
    }

    afterSelectionChange() {
        super.afterSelectionChange();
        const selected = this.getSelection();
        if (this.loadWarning) {
            const overloaded = selected.length > 3;
            this.loadWarning.hidden = !overloaded;
            this.loadWarning.textContent = overloaded
                ? `${selected.length} keywords selected. You can continue, but focus and natural phrasing may decline. For best quality, use 1 primary keyword and 1–2 secondary keywords.`
                : '';
        }
        if (!this.compareLink) {
            return;
        }
        const withinLimit = selected.length <= 5;
        const enabled = selected.length > 0 && withinLimit;
        this.compareLink.href = enabled ? CardRenderer.googleTrendsUrl(selected) : '#';
        this.compareLink.setAttribute('aria-disabled', enabled ? 'false' : 'true');
        this.compareLink.classList.toggle('is-disabled', !enabled);
        this.compareLink.tabIndex = enabled ? 0 : -1;
        this.compareLink.firstChild.textContent = withinLimit
            ? 'Compare selected in Google Trends '
            : 'Select up to 5 to compare in Google Trends ';
    }

    /** Contract §3 selection key. */
    getSelectionKey() {
        return 'keywords';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = KeywordsStepView;
}
