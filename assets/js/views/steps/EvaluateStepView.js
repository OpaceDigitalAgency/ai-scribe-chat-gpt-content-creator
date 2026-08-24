/**
 * Step 11 — Evaluate. The engine receives the exact final Review HTML and
 * returns typed checks plus deterministic structure facts measured server-side.
 *
 * The finished report is rendered as a tidy table: one row per check with a
 * coloured status icon (green pass, amber warn, red fail) and any
 * suggestions listed beneath the failing row. Two input shapes are handled:
 * a typed `parsed.checks` array when the engine provides one, otherwise the
 * streamed HTML is decorated in place by classifying each table row.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* global StreamingStepView */
/* exported EvaluateStepView */

class EvaluateStepView extends StreamingStepView {
    constructor(appState) {
        super(11, appState, { proseSelector: '#evaluation-output' });
    }

    /**
     * Normalise a status word to pass | warn | fail | unknown.
     *
     * @param {string} status
     * @return {string}
     */
    static normaliseStatus(status) {
        const value = String(status || '').toLowerCase();
        if (/^(pass|passed|ok|good|excellent|yes|done|met)/.test(value)) {
            return 'pass';
        }
        if (/^(warn|warning|partial|advisory|improve|consider)/.test(value)) {
            return 'warn';
        }
        if (/^(fail|failed|error|missing|poor|not met|no)/.test(value)) {
            return 'fail';
        }
        return 'unknown';
    }

    /** Classify free text from a report row when no typed status exists. */
    static classifyRowText(text) {
        const value = String(text || '').toLowerCase();
        if (/\b(fail|failed|missing|not (added|present|found|used)|poor|too short|too long)\b/.test(value)) {
            return 'fail';
        }
        if (/\b(warn|warning|partial|consider|could|improve|recommended?)\b/.test(value)) {
            return 'warn';
        }
        if (/\b(pass|passed|good|excellent|yes|present|added|ok)\b/.test(value)) {
            return 'pass';
        }
        return 'unknown';
    }

    renderContent(html, data) {
        const checks = data && data.parsed && Array.isArray(data.parsed.checks)
            ? data.parsed.checks
            : null;
        if (checks && checks.length) {
            this.renderChecksTable(checks, data.parsed.facts || null);
            return;
        }
        this.renderTrustedHtml(this.proseTarget, EvaluateStepView.decorateReport(html));
    }

    /**
     * Re-render a stored payload: typed checks when present, else the
     * stored HTML through the same decoration pass.
     */
    renderTyped(items) {
        if (items && Array.isArray(items.checks) && items.checks.length) {
            this.finalHtml = ''; // Typed shape carries the report.
            this.renderChecksTable(items.checks, items.facts || null);
            this.showReady();
            this.setNextEnabled(true);
            return;
        }
        this.finalHtml = (items && items.html) || '';
        if (!this.finalHtml) {
            return;
        }
        this.renderContent(this.finalHtml, null);
        this.showReady();
        this.setNextEnabled(true);
    }

    /**
     * The legacy seeded prompt told the model to wrap examples "in curly
     * brackets {like this}", and models that learnt the habit keep doing it.
     * Curly braces are prompt plumbing, not prose: rewrap the example in
     * double quotation marks.
     *
     * @param {string} text
     * @return {string}
     */
    static tidyExamples(text) {
        if (typeof text !== 'string' || text.indexOf('{') === -1) {
            return text;
        }
        return text.replace(/\{\s*([^{}]*?)\s*\}/g, '"$1"');
    }

    /**
     * Typed checks → an evidence-led summary and one semantic row per check.
     * All fields are untrusted text and enter the DOM via textContent.
     *
     * @param {Array<{label:string,status:string,detail:string,suggestion:string}>} checks
     */
    renderChecksTable(checks, facts = null) {
        if (!this.proseTarget) {
            return;
        }
        this.proseTarget.textContent = '';

        const counts = { pass: 0, warn: 0, fail: 0, unknown: 0 };
        checks.forEach((check) => {
            counts[EvaluateStepView.normaliseStatus(check && check.status)] += 1;
        });

        const summary = document.createElement('section');
        summary.className = 'evaluation-summary';
        summary.setAttribute('aria-label', 'Evaluation summary');
        const heading = document.createElement('div');
        heading.className = 'evaluation-summary-heading';
        const title = document.createElement('h3');
        title.textContent = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.evaluationSummary) || 'Evaluation summary';
        const note = document.createElement('p');
        note.textContent = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.evaluationSummaryNote) || 'Structural checks are measured from the final Review HTML. Editorial rows are clearly labelled AI review and should be confirmed by an editor.';
        heading.appendChild(title);
        heading.appendChild(note);
        summary.appendChild(heading);

        const statusList = document.createElement('ul');
        statusList.className = 'evaluation-summary-statuses';
        [
            ['pass', 'Passed'], ['warn', 'Needs a check'],
            ['fail', 'Failed'], ['unknown', 'Needs review']
        ].forEach(([status, label]) => {
            const item = document.createElement('li');
            item.className = `evaluation-summary-status eval-${status}`;
            const value = document.createElement('strong');
            value.textContent = String(counts[status]);
            const text = document.createElement('span');
            text.textContent = label;
            item.appendChild(value);
            item.appendChild(text);
            statusList.appendChild(item);
        });
        summary.appendChild(statusList);

        if (facts && typeof facts === 'object') {
            const factList = document.createElement('dl');
            factList.className = 'evaluation-facts';
            [
                ['Words', facts.word_count],
                ['Images', facts.image_count],
                ['Anchor links', facts.valid_anchor_link_count],
                ['Internal links', facts.internal_contextual_link_count],
                ['External links', facts.external_contextual_link_count],
                ['Headings', facts.heading_count]
            ].forEach(([label, value]) => {
                if (typeof value !== 'number') {
                    return;
                }
                const fact = document.createElement('div');
                const term = document.createElement('dt');
                term.textContent = label;
                const description = document.createElement('dd');
                description.textContent = String(value);
                fact.appendChild(term);
                fact.appendChild(description);
                factList.appendChild(fact);
            });
            summary.appendChild(factList);
        }
        this.proseTarget.appendChild(summary);

        const tableRegion = document.createElement('div');
        tableRegion.className = 'evaluation-report-region';
        const table = document.createElement('table');
        table.className = 'evaluation-report-table';
        table.setAttribute('data-testid', 'evaluation-report');

        const caption = document.createElement('caption');
        caption.textContent = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.evaluationTableCaption) || 'Article evaluation checks with evidence and suggested next actions';
        table.appendChild(caption);

        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        ['Status', 'Check', 'Evidence', 'What to do'].forEach((label) => {
            const th = document.createElement('th');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        checks.forEach((check) => {
            const status = EvaluateStepView.normaliseStatus(check && check.status);
            const row = document.createElement('tr');
            row.className = `eval-row eval-${status}`;

            const statusCell = document.createElement('td');
            statusCell.className = 'eval-status-cell';
            const icon = document.createElement('span');
            icon.className = `eval-status-icon eval-status-${status}`;
            icon.setAttribute('aria-hidden', 'true');
            const statusText = document.createElement('span');
            statusText.className = 'eval-status-text';
            statusText.textContent = { pass: 'Pass', warn: 'Check', fail: 'Fail', unknown: 'Review' }[status];
            statusCell.setAttribute('data-label', 'Status');
            statusCell.appendChild(icon);
            statusCell.appendChild(statusText);
            row.appendChild(statusCell);

            const labelCell = document.createElement('td');
            labelCell.className = 'eval-label-cell';
            labelCell.textContent = (check && check.label) || '';
            labelCell.setAttribute('data-label', 'Check');
            row.appendChild(labelCell);

            const detailsCell = document.createElement('td');
            detailsCell.className = 'eval-details-cell';
            // The schema emits singular `detail`; older payloads used `details`.
            detailsCell.textContent = EvaluateStepView.tidyExamples((check && (check.detail || check.details)) || '');
            detailsCell.setAttribute('data-label', 'Evidence');
            row.appendChild(detailsCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'eval-action-cell';
            actionCell.setAttribute('data-label', 'What to do');
            let suggestions = (check && check.suggestions) || [];
            if (!Array.isArray(suggestions)) {
                suggestions = [suggestions];
            }
            if (!suggestions.length && check && check.suggestion) {
                suggestions = [check.suggestion];
            }
            suggestions = suggestions.filter((s) => typeof s === 'string' && s.trim() !== '');
            if (suggestions.length && status !== 'pass') {
                const list = document.createElement('ul');
                suggestions.forEach((suggestion) => {
                    const li = document.createElement('li');
                    li.textContent = EvaluateStepView.tidyExamples(String(suggestion));
                    list.appendChild(li);
                });
                actionCell.appendChild(list);
            } else {
                actionCell.textContent = status === 'pass' ? 'No action needed.' : 'Review this check against the article.';
            }
            row.appendChild(actionCell);
            tbody.appendChild(row);
        });
        table.appendChild(tbody);
        tableRegion.appendChild(table);
        this.proseTarget.appendChild(tableRegion);
    }

    /**
     * Fallback: decorate the streamed (already server-sanitised) HTML by
     * classifying each table row so the status colouring still applies.
     *
     * @param {string} html
     * @return {string}
     */
    static decorateReport(html) {
        if (typeof html !== 'string' || html === '') {
            return html;
        }
        const host = document.createElement('div');
        host.innerHTML = html;
        const rows = host.querySelectorAll('table tr');
        if (rows.length === 0) {
            return html;
        }
        rows.forEach((row) => {
            if (row.querySelector('th')) {
                return; // Header rows carry no status.
            }
            row.querySelectorAll('td').forEach((cell) => {
                if (cell.children.length === 0 && cell.textContent.indexOf('{') !== -1) {
                    cell.textContent = EvaluateStepView.tidyExamples(cell.textContent);
                }
            });
            const status = EvaluateStepView.classifyRowText(row.textContent);
            row.classList.add('eval-row', `eval-${status}`);
            const first = row.querySelector('td');
            if (first && !first.querySelector('.eval-status-icon')) {
                const icon = document.createElement('span');
                icon.className = `eval-status-icon eval-status-${status}`;
                icon.setAttribute('aria-hidden', 'true');
                first.insertBefore(icon, first.firstChild);
            }
        });
        host.querySelectorAll('table').forEach((table) => {
            table.classList.add('evaluation-report-table');
        });
        return host.innerHTML;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = EvaluateStepView;
}
