/**
 * Step 8 — Q&A. Every generated question and answer renders as its own
 * editable box with an include checkbox, so the user picks exactly which
 * Q&As make the article (and can tidy the wording before they do).
 * Typed input: `items.qna: [{question, answer}]`. Skippable.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global ChoiceStepView */
/* exported QnaStepView */

class QnaStepView extends ChoiceStepView {
    constructor(appState) {
        super(8, appState, {
            contentType: 'qa',
            containerSelector: '#qa-options',
            multiSelect: true
        });
        this.qnaSet = [];
        this.skipped = false;
    }

    /**
     * Models frequently wrap these plain-text fields in <h2>/<p>. Unwrap to
     * text so the edit boxes hold prose, never literal tags.
     *
     * @param {string} text
     * @return {string}
     */
    static plainText(text) {
        const div = document.createElement('div');
        div.innerHTML = text == null ? '' : String(text);
        return (div.textContent || '').replace(/\s+/g, ' ').trim();
    }

    /** A small, deterministic score for reconciling edited stored items. */
    static similarity(left, right) {
        const words = (value) => new Set(QnaStepView.plainText(value).toLowerCase()
            .split(/[^\p{L}\p{N}]+/u).filter((word) => word.length > 2));
        const a = words(left);
        const b = words(right);
        if (a.size === 0 || b.size === 0) {
            return 0;
        }
        let shared = 0;
        a.forEach((word) => {
            if (b.has(word)) {
                shared += 1;
            }
        });
        return shared / Math.max(a.size, b.size);
    }

    /** @param {{qna: Array<{question:string,answer:string}>}} items */
    renderTyped(items, options = {}) {
        const qna = ((items && items.qna) || []).map((item) => ({
            question: QnaStepView.plainText(item && item.question),
            answer: QnaStepView.plainText(item && item.answer)
        }));
        this.qnaSet = options.append ? this.qnaSet.concat(qna) : qna.slice();
        this.skipped = false;
        if (!this.container) {
            return;
        }
        if (this.qnaSet.length === 0) {
            this.showEmpty();
            return;
        }

        this.container.textContent = '';
        this.container.setAttribute('role', 'group');

        const hint = document.createElement('p');
        hint.className = 'qa-list-hint';
        hint.textContent = this.t('qnaListHint') === 'qnaListHint'
            ? 'Untick any Q&A you do not want in the article. The wording can be edited before you continue.'
            : this.t('qnaListHint');
        this.container.appendChild(hint);

        this.container.appendChild(this.buildBulkToolbar());

        this.qnaSet.forEach((item, index) => {
            this.container.appendChild(this.buildItemCard(item, index));
        });

        this.items = [this.qnaSet];
        this.showReady();
        this.setNextEnabled(true); // Skippable: Continue is always available.
        this.updateBulkState();
        this.persistSelection();
    }

    /**
     * One native checkbox controls the whole set. Its checked, mixed and
     * visible-label states are recomputed from the item checkboxes, so it
     * cannot drift after an individual item changes or stored state hydrates.
     *
     * @return {HTMLElement}
     */
    buildBulkToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'qa-bulk-toolbar';

        const label = document.createElement('label');
        label.className = 'qa-bulk-control';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'qa-bulk-checkbox';
        checkbox.setAttribute('data-testid', 'qa-select-all');
        const text = document.createElement('span');
        text.className = 'qa-bulk-label';
        label.appendChild(checkbox);
        label.appendChild(text);

        const count = document.createElement('span');
        count.className = 'qa-bulk-count';
        count.setAttribute('data-testid', 'qa-selection-count');
        count.setAttribute('aria-live', 'polite');
        count.setAttribute('aria-atomic', 'true');

        checkbox.addEventListener('change', () => {
            const shouldInclude = checkbox.checked;
            this.container.querySelectorAll('.qa-item-checkbox').forEach((itemCheckbox) => {
                itemCheckbox.checked = shouldInclude;
                this.updateCardState(itemCheckbox);
            });
            if (shouldInclude) {
                this.skipped = false;
            }
            this.updateBulkState();
            this.persistSelection();
        });

        toolbar.appendChild(label);
        toolbar.appendChild(count);
        return toolbar;
    }

    /**
     * One editable, includable Q&A box.
     *
     * @param {{question:string, answer:string}} item
     * @param {number} index
     * @return {HTMLElement}
     */
    buildItemCard(item, index) {
        const card = document.createElement('div');
        card.className = 'qa-item-card included';
        card.setAttribute('data-index', String(index));
        // The e2e suite addresses every step's cards as result-card.
        card.setAttribute('data-testid', 'result-card');

        const includeLabel = document.createElement('label');
        includeLabel.className = 'qa-item-include';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        checkbox.className = 'qa-item-checkbox';
        checkbox.setAttribute('data-testid', 'qa-item-include');
        const includeText = document.createElement('span');
        includeText.textContent = this.t('qnaInclude') === 'qnaInclude'
            ? 'Include in article'
            : this.t('qnaInclude');
        includeLabel.appendChild(checkbox);
        includeLabel.appendChild(includeText);
        card.appendChild(includeLabel);

        const questionLabel = document.createElement('label');
        questionLabel.className = 'visually-hidden';
        questionLabel.setAttribute('for', `qa-question-${index}`);
        questionLabel.textContent = `Question ${index + 1}`;
        const question = document.createElement('input');
        question.type = 'text';
        question.id = `qa-question-${index}`;
        question.className = 'form-control qa-item-question';
        question.value = item.question || '';
        question.setAttribute('data-testid', 'qa-item-question');
        card.appendChild(questionLabel);
        card.appendChild(question);

        const answerLabel = document.createElement('label');
        answerLabel.className = 'visually-hidden';
        answerLabel.setAttribute('for', `qa-answer-${index}`);
        answerLabel.textContent = `Answer ${index + 1}`;
        const answer = document.createElement('textarea');
        answer.id = `qa-answer-${index}`;
        answer.className = 'form-control qa-item-answer';
        answer.rows = 3;
        answer.value = item.answer || '';
        answer.setAttribute('data-testid', 'qa-item-answer');
        card.appendChild(answerLabel);
        card.appendChild(answer);

        checkbox.addEventListener('change', () => {
            this.updateCardState(checkbox);
            // Choosing any set undoes an earlier Skip Q&A.
            if (checkbox.checked) {
                this.skipped = false;
            }
            this.updateBulkState();
            this.persistSelection();
        });
        const onEdit = () => {
            this.qnaSet[index] = {
                question: question.value,
                answer: answer.value
            };
            this.growToContent(answer);
            this.persistSelection();
        };
        question.addEventListener('input', onEdit);
        answer.addEventListener('input', onEdit);

        window.requestAnimationFrame(() => this.growToContent(answer));

        return card;
    }

    /** @param {HTMLInputElement} checkbox */
    updateCardState(checkbox) {
        const card = checkbox && checkbox.closest('.qa-item-card');
        if (!card) {
            return;
        }
        card.classList.toggle('included', checkbox.checked);
        card.classList.toggle('excluded', !checkbox.checked);
    }

    /** Keep generated answers readable while retaining vertical resize. */
    growToContent(textarea) {
        if (!textarea || textarea.scrollHeight <= textarea.clientHeight) {
            return;
        }
        textarea.style.height = `${textarea.scrollHeight + 2}px`;
    }

    /** Synchronise checked/mixed state, action label and included count. */
    updateBulkState() {
        if (!this.container) {
            return;
        }
        const items = Array.from(this.container.querySelectorAll('.qa-item-checkbox'));
        const selected = items.filter((checkbox) => checkbox.checked).length;
        const bulk = this.container.querySelector('.qa-bulk-checkbox');
        const label = this.container.querySelector('.qa-bulk-label');
        const count = this.container.querySelector('.qa-bulk-count');
        const allSelected = items.length > 0 && selected === items.length;
        const partlySelected = selected > 0 && !allSelected;
        if (bulk) {
            bulk.checked = allSelected;
            bulk.indeterminate = partlySelected;
            bulk.setAttribute('aria-checked', partlySelected ? 'mixed' : (allSelected ? 'true' : 'false'));
        }
        if (label) {
            label.textContent = allSelected ? 'Deselect all' : 'Select all';
        }
        if (count) {
            count.textContent = `${selected} of ${items.length} included`;
        }
    }

    /** Re-tick the boxes matching a stored selection (contract §4 recovery). */
    applyStoredSelection(selection) {
        if (!this.container) {
            return;
        }
        const wanted = (Array.isArray(selection) ? selection : []).map((item) => ({
            question: QnaStepView.plainText(item && item.question),
            answer: QnaStepView.plainText(item && item.answer)
        })).filter((item) => item.question !== '');
        const cards = Array.from(this.container.querySelectorAll('.qa-item-card'));
        const matched = new Map();
        const unused = new Set(cards.map((card, index) => index));

        wanted.forEach((stored) => {
            let bestIndex = -1;
            let bestScore = -1;
            unused.forEach((index) => {
                const generated = this.qnaSet[index] || {};
                const exactQuestion = QnaStepView.plainText(generated.question) === stored.question;
                const exactAnswer = QnaStepView.plainText(generated.answer) === stored.answer;
                const score = (exactQuestion ? 4 : 0) + (exactAnswer ? 2 : 0)
                    + QnaStepView.similarity(generated.question, stored.question)
                    + QnaStepView.similarity(generated.answer, stored.answer);
                if (score > bestScore) {
                    bestScore = score;
                    bestIndex = index;
                }
            });
            if (bestIndex >= 0) {
                matched.set(bestIndex, stored);
                unused.delete(bestIndex);
            }
        });

        cards.forEach((card, index) => {
            const question = card.querySelector('.qa-item-question');
            const checkbox = card.querySelector('.qa-item-checkbox');
            const answer = card.querySelector('.qa-item-answer');
            if (!question || !checkbox || !answer) {
                return;
            }
            const stored = matched.get(index);
            const keep = !!stored;
            checkbox.checked = keep;
            if (stored) {
                question.value = stored.question;
                answer.value = stored.answer;
                this.qnaSet[index] = { question: stored.question, answer: stored.answer };
                this.growToContent(answer);
            }
            this.updateCardState(checkbox);
        });
        this.skipped = wanted.length === 0;
        this.updateBulkState();
    }

    hasSelection() {
        return !!this.container
            && this.container.querySelector('.qa-item-checkbox:checked') !== null;
    }

    skip() {
        this.skipped = true;
        if (this.container) {
            this.container.querySelectorAll('.qa-item-checkbox').forEach((checkbox) => {
                checkbox.checked = false;
                const card = checkbox.closest('.qa-item-card');
                if (card) {
                    this.updateCardState(checkbox);
                }
            });
        }
        this.updateBulkState();
        this.setNextEnabled(true);
        this.persistSelection();
    }

    /** The included Q&As, with any edits the boxes now hold. */
    getSelection() {
        if (this.skipped || !this.container) {
            return [];
        }
        const kept = [];
        this.container.querySelectorAll('.qa-item-card').forEach((card) => {
            const checkbox = card.querySelector('.qa-item-checkbox');
            const question = card.querySelector('.qa-item-question');
            const answer = card.querySelector('.qa-item-answer');
            if (!checkbox || !checkbox.checked || !question || !answer) {
                return;
            }
            if (question.value.trim() === '') {
                return;
            }
            kept.push({
                question: question.value.trim(),
                answer: answer.value.trim()
            });
        });
        return kept;
    }

    /** Contract §3 selection key. */
    getSelectionKey() {
        return 'qna';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = QnaStepView;
}
