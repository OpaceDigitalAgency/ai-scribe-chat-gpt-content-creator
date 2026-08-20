import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const mainCss = fs.readFileSync(new URL('../../assets/css/main.css', import.meta.url), 'utf8');
const componentsCss = fs.readFileSync(new URL('../../assets/css/components.css', import.meta.url), 'utf8');
const adminPagesCss = fs.readFileSync(new URL('../../assets/css/admin-pages.css', import.meta.url), 'utf8');
const viewSource = fs.readFileSync(new URL('../../assets/js/views/steps/QnaStepView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

const generated = [
    { question: 'How quickly will this work?', answer: 'Most established sites need four to eight weeks before a sustained change is visible.' },
    { question: 'Should I change every page?', answer: 'No. Start with the pages that already attract relevant visitors and measure one restrained change.' },
    { question: 'What should I measure?', answer: 'Measure qualified visits, enquiries and sales instead of treating rankings as the final outcome.' }
];

try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const geometryEvidence = [];
    await page.setContent(`<!doctype html><html><head><style>${mainCss}\n${componentsCss}\n${adminPagesCss}</style></head><body>
        <main class="ai-scribe-app app-main"><div class="two-column-layout">
            <div class="main-panel"><section data-step-panel="8">
                <div class="results-section"><div class="qa-options" id="qa-options"></div>
                <button data-testid="continue" disabled>Continue</button></div>
            </section></div>
            <aside class="settings-panel" data-testid="right-panel">Options &amp; Prompt</aside>
        </div></main></body></html>`);
    await page.addScriptTag({ content: `class ChoiceStepView {
        constructor(step, appState, config) {
            this.step = step;
            this.appState = appState;
            this.panel = document.querySelector('[data-step-panel="8"]');
            this.container = this.panel.querySelector(config.containerSelector);
        }
        t(key) { return key; }
        showReady() {}
        showEmpty() {}
        setNextEnabled(enabled) { document.querySelector('[data-testid="continue"]').disabled = !enabled; }
        persistSelection() {
            const stepData = this.appState.getStateSlice('stepData') || {};
            stepData[this.step] = stepData[this.step] || {};
            stepData[this.step].selection = this.getSelection();
            this.appState.setStateSlice('stepData', stepData);
        }
    }` });
    await page.addScriptTag({ content: viewSource });
    await page.evaluate((items) => {
        window.state = {};
        const appState = {
            getStateSlice: (key) => window.state[key],
            setStateSlice: (key, value) => { window.state[key] = value; }
        };
        window.qnaView = new QnaStepView(appState);
        window.qnaView.renderTyped({ qna: items });
    }, generated);

    for (const width of [375, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.evaluate((dark) => {
            document.documentElement.toggleAttribute('data-ai-scribe-theme', dark);
            if (dark) {
                document.documentElement.setAttribute('data-ai-scribe-theme', 'dark');
            }
        }, width === 768);
        const geometry = await page.locator('.qa-item-card').first().evaluate((card) => {
            const input = card.querySelector('.qa-item-question');
            const answer = card.querySelector('.qa-item-answer');
            return {
                inputWidth: input.getBoundingClientRect().width,
                answerWidth: answer.getBoundingClientRect().width,
                cardInnerWidth: card.clientWidth - parseFloat(getComputedStyle(card).paddingLeft) - parseFloat(getComputedStyle(card).paddingRight),
                inputMaxWidth: getComputedStyle(input).maxWidth,
                answerMaxWidth: getComputedStyle(answer).maxWidth,
                cardFitsMainPanel: card.getBoundingClientRect().right <= card.closest('.main-panel').getBoundingClientRect().right,
                rightPanelFits: document.querySelector('[data-testid="right-panel"]').getBoundingClientRect().right <= document.documentElement.clientWidth,
                pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth
            };
        });
        assert.ok(Math.abs(geometry.inputWidth - geometry.cardInnerWidth) < 2, `question fills card at ${width}px`);
        assert.ok(Math.abs(geometry.answerWidth - geometry.cardInnerWidth) < 2, `answer fills card at ${width}px`);
        assert.equal(geometry.inputMaxWidth, 'none', `question escapes the admin field cap at ${width}px`);
        assert.equal(geometry.answerMaxWidth, 'none', `answer escapes the admin textarea cap at ${width}px`);
        assert.equal(geometry.cardFitsMainPanel, true, `Q&A card stays inside the main panel at ${width}px`);
        assert.equal(geometry.rightPanelFits, true, `right panel stays within the viewport at ${width}px`);
        assert.equal(geometry.pageFits, true, `no horizontal page scroll at ${width}px`);
        geometryEvidence.push({
            viewport: width,
            question: Math.round(geometry.inputWidth),
            answer: Math.round(geometry.answerWidth),
            available: Math.round(geometry.cardInnerWidth)
        });
    }
    await page.evaluate(() => document.documentElement.removeAttribute('data-ai-scribe-theme'));

    const bulk = page.locator('[data-testid="qa-select-all"]');
    assert.equal(await bulk.isChecked(), true);
    assert.equal(await page.locator('.qa-bulk-label').textContent(), 'Deselect all');
    assert.equal(await page.locator('[data-testid="qa-selection-count"]').textContent(), '3 of 3 included');

    await page.locator('[data-testid="qa-item-include"]').nth(1).uncheck();
    assert.equal(await bulk.evaluate((checkbox) => checkbox.indeterminate), true);
    assert.equal(await bulk.getAttribute('aria-checked'), 'mixed');
    assert.equal(await page.locator('.qa-bulk-label').textContent(), 'Select all');
    assert.equal(await page.locator('[data-testid="qa-selection-count"]').textContent(), '2 of 3 included');

    await bulk.check();
    assert.equal(await page.locator('[data-testid="qa-item-include"]:checked').count(), 3);
    await bulk.uncheck();
    assert.equal(await page.locator('[data-testid="qa-item-include"]:checked').count(), 0);
    assert.equal(await page.locator('[data-testid="qa-selection-count"]').textContent(), '0 of 3 included');
    await bulk.focus();
    await bulk.press('Space');
    assert.equal(await page.locator('[data-testid="qa-item-include"]:checked').count(), 3, 'bulk checkbox works from the keyboard');
    await bulk.uncheck();

    await page.locator('[data-testid="qa-item-include"]').nth(0).check();
    await page.locator('[data-testid="qa-item-include"]').nth(2).check();
    await page.locator('[data-testid="qa-item-question"]').nth(0).fill('How long before results are trustworthy?');
    await page.locator('[data-testid="qa-item-answer"]').nth(2).fill('Measure qualified visits and valid enquiries first.');
    const payload = await page.evaluate(() => window.qnaView.getSelection());
    assert.deepEqual(payload, [
        { question: 'How long before results are trustworthy?', answer: generated[0].answer },
        { question: generated[2].question, answer: 'Measure qualified visits and valid enquiries first.' }
    ]);
    assert.deepEqual(await page.evaluate(() => window.state.stepData[8].selection), payload);

    await page.evaluate((items) => {
        const stored = window.qnaView.getSelection();
        window.qnaView.renderTyped({ qna: items }, { fromState: true });
        window.qnaView.applyStoredSelection(stored);
    }, generated);
    assert.equal(await page.locator('[data-testid="qa-item-include"]:checked').count(), 2);
    assert.equal(await page.locator('[data-testid="qa-item-question"]').nth(0).inputValue(), 'How long before results are trustworthy?');
    assert.equal(await page.locator('[data-testid="qa-item-answer"]').nth(2).inputValue(), 'Measure qualified visits and valid enquiries first.');
    assert.deepEqual(await page.evaluate(() => window.qnaView.getSelection()), payload);

    await page.evaluate(() => window.qnaView.renderTyped({ qna: [{ question: 'Replacement?', answer: 'Only after provider success.' }] }));
    assert.equal(await page.locator('.qa-item-card').count(), 1, 'successful regeneration replaces the prior set');
    assert.equal(await page.locator('[data-testid="qa-selection-count"]').textContent(), '1 of 1 included');

    console.log(`Q&A browser: responsive geometry ${JSON.stringify(geometryEvidence)}, bulk mixed state, edits, payload, hydration and successful replacement passed`);
} finally {
    await browser.close();
}
