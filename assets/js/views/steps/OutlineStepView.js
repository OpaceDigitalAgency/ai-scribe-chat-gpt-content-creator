/**
 * Step 3 — Outline. The engine returns ONE outline as typed headings
 * (`parsed.outline: string[]`, contract §2). Headings render as
 * multi-select cards, all selected by default, so the user can drop the
 * ones they don't want; "Generate More" appends alternatives.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global ChoiceStepView */
/* exported OutlineStepView */

class OutlineStepView extends ChoiceStepView {
    constructor(appState) {
        super(3, appState, {
            contentType: 'outline',
            containerSelector: '#outline-options',
            multiSelect: true
        });
    }

    /** @param {{outline: string[]}} parsed */
    renderTyped(parsed, options = {}) {
        const headings = (parsed && parsed.outline) || [];
        const existing = new Set((options.append ? this.items : []).map(OutlineStepView.headingIdentity));
        const freshHeadings = headings.filter((heading) => {
            const identity = OutlineStepView.headingIdentity(heading);
            if (!identity || existing.has(identity)) {
                return false;
            }
            existing.add(identity);
            return true;
        });
        this.items = options.append ? this.items.concat(freshHeadings) : freshHeadings.slice();
        if (!this.container) {
            return;
        }
        if (freshHeadings.length === 0 && !options.append) {
            this.showEmpty();
            return;
        }
        if (!options.append) {
            this.container.textContent = '';
        }
        const offset = options.append ? this.items.length - freshHeadings.length : 0;
        freshHeadings.forEach((heading, i) => {
            // New headings arrive pre-selected; user deselects unwanted ones.
            this.container.appendChild(this.buildHeadingCard(heading, offset + i, true));
        });
        this.decorateCards();
        this.showReady();
        this.afterSelectionChange();
    }

    buildHeadingCard(heading, index, selected) {
        const card = document.createElement('div');
        card.className = 'option-card outline-card' + (selected ? ' selected' : '');
        card.setAttribute('data-index', String(index));

        const checkbox = document.createElement('div');
        checkbox.className = 'checkbox';
        const check = document.createElement('i');
        check.setAttribute('data-lucide', 'check');
        check.style.display = selected ? 'block' : 'none';
        checkbox.appendChild(check);

        const content = document.createElement('div');
        content.className = 'option-content';
        const text = document.createElement('div');
        text.className = 'option-text';
        text.textContent = heading;
        content.appendChild(text);

        card.appendChild(checkbox);
        card.appendChild(content);
        card.addEventListener('click', () => {
            card.classList.toggle('selected');
            check.style.display = card.classList.contains('selected') ? 'block' : 'none';
        });
        return card;
    }

    /** Selected headings in display order. */
    getSelection() {
        if (this.skipped) {
            return [];
        }
        return this.selectedIndexes()
            .sort((a, b) => a - b)
            .map((i) => this.items[i])
            .filter(Boolean);
    }

    getSelectionKey() {
        return 'outline';
    }

    /** Entity/case/space-safe identity used only to suppress exact duplicates. */
    static headingIdentity(heading) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(heading == null ? '' : heading);
        return textarea.value.replace(/\s+/g, ' ').trim().toLocaleLowerCase();
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OutlineStepView;
}
