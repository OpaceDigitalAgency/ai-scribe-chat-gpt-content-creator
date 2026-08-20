import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const fields = new Map();
const field = (selector, value = '', extra = {}) => fields.set(selector, { value, ...extra });

field('#ai-scribe-model', 'gemini-test');
field('#ai-scribe-temperature', '0.6');
field('#ai-scribe-top-p', '0.85');
field('#ai-scribe-language', 'English');
field('#ai-scribe-custom-language', '');
field('#ai-scribe-style', 'Casual');
field('#ai-scribe-tone', 'Witty');
field('#ai-scribe-spelling', 'british');
field('#ai-scribe-heading-tag', 'H3');
field('#ai-scribe-number-of-headings', '9');
field('#ai-scribe-length-mode', 'in_depth');
field('#ai-scribe-word-count', '2800');
field('#ai-scribe-avoid-keywords', 'cheap, filler');
field('input[name="ai_scribe_mode"]:checked', 'humanize');
field('#ai-scribe-delete-data:checked', '', { checked: true });
field('#ai-scribe-images-enabled:checked', '', { checked: true });
field('#ai-scribe-image-model', 'imagen-test');
field('#ai-scribe-image-size', '1536x1024');
field('#ai-scribe-image-quality', 'high');
field('#ai-scribe-image-format', 'webp');
field('#ai-scribe-image-background', 'opaque');
field('#ai-scribe-image-style', 'Line art');

const param = {
    value: 'medium',
    type: 'select-one',
    getAttribute(name) { return name === 'data-param-key' ? 'thinking_level' : null; }
};
const checks = ['addQNA', 'addinsertToc'].map((key) => ({
    checked: true,
    getAttribute(name) { return name === 'data-check-key' ? key : null; }
}));
const prompt = {
    value: 'Saved title prompt',
    getAttribute(name) { return name === 'data-prompt-key' ? 'title_prompts' : null; }
};
const attrs = new Map([
    ['data-current-model', 'gemini-test'],
    ['data-model-params', JSON.stringify({ thinking_level: 'low' })]
]);
const root = {
    querySelector(selector) { return fields.get(selector) || null; },
    querySelectorAll(selector) {
        if (selector === '.model-param-field') return [param];
        if (selector === '.check-arr-field') return checks;
        if (selector === '.prompt-library-field') return [prompt];
        if (selector === '.provider-key-row input[type="password"]') return [];
        return [];
    },
    setAttribute(name, value) { attrs.set(name, String(value)); },
    getAttribute(name) { return attrs.get(name) || null; }
};

globalThis.document = { getElementById: (id) => id === 'ai-scribe-settings-root' ? root : null };
const require = createRequire(import.meta.url);
const SettingsView = require('../../assets/js/views/SettingsView.js');

const view = new SettingsView();
const collected = view.collect();
view.commitSavedSettings(collected.settings);

// A fresh view represents the model-parameter hydration path after reload or
// a model-list repaint: it reads the committed root attributes, not stale JS.
const reloaded = new SettingsView();
assert.deepEqual(reloaded.savedModelParams(), { thinking_level: 'medium' });
assert.equal(root.getAttribute('data-current-model'), 'gemini-test');

const settings = collected.settings;
assert.deepEqual(settings.model_params, { thinking_level: 'medium' });
assert.equal(settings.writing_style, 'Casual');
assert.equal(settings.writing_tone, 'Witty');
assert.equal(settings.language, 'English');
assert.equal(settings.spelling, 'british');
assert.equal(settings.heading_tag, 'H3');
assert.equal(settings.number_of_headings, 9);
assert.equal(settings.article_length_mode, 'in_depth');
assert.equal(settings.article_word_count, 2800);
assert.equal(settings.avoid_keywords, 'cheap, filler');
assert.equal(settings.mode, 'humanize');
assert.deepEqual(settings.check_arr, { addQNA: 'addQNA', addinsertToc: 'addinsertToc' });
assert.equal(settings.delete_data_on_uninstall, true);
assert.deepEqual(settings.images, {
    enabled: true,
    model: 'imagen-test',
    size: '1536x1024',
    quality: 'high',
    format: 'webp',
    background: 'opaque',
    style: 'Line art'
});
assert.equal(collected.prompts.title_prompts, 'Saved title prompt');

let visibleConfirmation = '';
view.announce = () => {};
view.showSaveFeedback = (text) => { visibleConfirmation = text; };
view.notify = () => {};
view.announceSaved(collected.settings);
assert.match(visibleConfirmation, /Casual/);
assert.match(visibleConfirmation, /medium thinking/);

console.log('Settings persistence: every visible settings group collects correctly and saved model parameters survive reload/repaint');
