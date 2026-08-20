const assert = require('node:assert/strict');
const SettingsView = require('../../assets/js/views/SettingsView.js');
const WizardFlowController = require('../../assets/js/controllers/WizardFlowController.js');

const values = {
    '#ai-scribe-length-mode': { value: 'in_depth' },
    '#ai-scribe-word-count': { value: '2800' },
    'input[name="ai_scribe_mode"]:checked': { value: 'standard' }
};
const root = {
    querySelector(selector) { return values[selector] || null; },
    querySelectorAll() { return []; }
};
global.document = { getElementById(id) { return id === 'ai-scribe-settings-root' ? root : null; } };
const settings = new SettingsView().collect().settings;
assert.equal(settings.article_length_mode, 'in_depth');
assert.equal(settings.article_word_count, 2800);

const customPanel = {
    querySelector(selector) {
        if (selector === '[data-article-length-mode]') return { value: 'custom' };
        if (selector === '[data-article-word-count]') return { value: '2375' };
        return null;
    }
};
global.document.querySelector = () => customPanel;
const controller = Object.create(WizardFlowController.prototype);
assert.deepEqual(controller.articleLengthFields('wizard'), { article_length_mode: 'custom', article_word_count: 2375 });

customPanel.querySelector = (selector) => selector === '[data-article-length-mode]' ? { value: 'global' } : { value: '1800' };
assert.deepEqual(controller.articleLengthFields('express'), {});

const modeField = { value: 'standard' };
const countField = { value: '1800' };
const customGroup = { hidden: false };
const summary = { textContent: '' };
const row = {
    parentElement: { querySelector: () => null },
    closest(selector) {
        return selector === '[data-step-panel]' ? { querySelector: () => summary } : null;
    },
    querySelector(selector) {
        if (selector === '[data-article-length-mode]') return modeField;
        if (selector === '[data-article-word-count]') return countField;
        if (selector === '[data-custom-word-count]') return customGroup;
        return null;
    }
};
global.window = { ai_scribe: { contentSettings: { number_of_headings: 5 }, checkArr: { addQNA: true } } };
global.document.querySelectorAll = () => [row];
controller.refreshArticlePlanControls();
assert.equal(customGroup.hidden, true, 'custom count remains hidden for non-Custom modes');
assert.match(summary.textContent, /1,530–2,070/, 'planned range is visible before generation');
modeField.value = 'custom';
countField.value = '600';
controller.refreshArticlePlanControls();
assert.equal(customGroup.hidden, false, 'custom count appears only for Custom mode');
assert.match(summary.textContent, /too short/i, 'scope mismatch warning is visible before generation');

modeField.value = 'global';
global.window.ai_scribe.contentSettings.article_length_mode = 'custom';
global.window.ai_scribe.contentSettings.article_word_count = 2200;
controller.refreshArticlePlanControls();
assert.equal(customGroup.hidden, true, 'using a global Custom default does not expose a conflicting per-article number field');
assert.match(summary.textContent, /2,200/, 'global Custom target remains visible in the plan summary');

modeField.value = 'custom';
countField.value = '2300';
global.document.querySelector = () => row;
controller.appState = { getStateSlice: () => ({ 2: { selection: [] }, 3: { selection: ['One', 'Two'] } }) };
const bodyPlan = controller.wizardArticlePlan(true);
assert.equal(bodyPlan.complete_target_words, 2300, 'body plan retains the visible complete-article target');
assert.equal(bodyPlan.target_words, 1564, 'body plan removes the exact introduction, conclusion, Q&A and title/tagline allocation');
assert.equal(bodyPlan.non_body_target_words, 736, 'body plan explains the exact non-body reserve');

const settingsMode = { value: 'auto' };
const settingsCount = { disabled: false };
const settingsCustom = {
    hidden: false,
    querySelector(selector) { return selector === '#ai-scribe-word-count' ? settingsCount : null; }
};
const settingsToggle = Object.create(SettingsView.prototype);
settingsToggle.root = {
    querySelector(selector) {
        if (selector === '#ai-scribe-length-mode') return settingsMode;
        if (selector === '[data-settings-custom-word-count]') return settingsCustom;
        return null;
    }
};
settingsToggle.syncLengthControl();
assert.equal(settingsCustom.hidden, true, 'settings custom word field stays hidden for Auto');
settingsMode.value = 'custom';
settingsToggle.syncLengthControl();
assert.equal(settingsCustom.hidden, false, 'settings custom word field appears only for Custom');

console.log('Article length controls: one selector, global preference, per-article override, visible range and scope warning passed');
