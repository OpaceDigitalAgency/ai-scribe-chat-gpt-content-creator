const assert = require('node:assert/strict');

class FakeElement {
    constructor(tag) {
        this.tag = tag;
        this.children = [];
        this._textContent = '';
        this.value = '';
        this.disabled = false;
        this.selected = false;
        this.label = '';
    }

    set textContent(value) {
        this._textContent = String(value);
        if (this.tag === 'select') {
            this.children = [];
        }
    }

    get textContent() {
        return this._textContent;
    }

    appendChild(child) {
        this.children.push(child);
    }
}

const root = { getAttribute: () => 'gpt-4o' };
global.document = {
    getElementById: (id) => id === 'ai-scribe-settings-root' ? root : null,
    createElement: (tag) => new FakeElement(tag),
};
global.window = {
    ai_scribe: {
        i18n: {
            savedKeyInvalidSuffix: 'saved; retained key did not pass validation',
        },
    },
};

const SettingsView = require('../../assets/js/views/SettingsView.js');
const models = [
    { id: 'gpt-4o', provider: 'openai', label: 'GPT-4o', configured: true },
    { id: 'gemini-2.5-flash', provider: 'gemini', label: 'Gemini 2.5 Flash', configured: true, pricing: { input_per_1m: 0.3 } },
];

const unavailableView = new SettingsView();
const unavailableSelect = new FakeElement('select');
const retained = unavailableView.fillSelect(
    unavailableSelect,
    models,
    'gpt-4o',
    { openai: { configured: true, validated: false }, gemini: { configured: true, validated: false } }
);
const savedOption = unavailableSelect.children.flatMap((group) => group.children).find((option) => option.value === 'gpt-4o');
assert.equal(retained, 'gpt-4o');
assert.deepEqual(unavailableView.savedUnavailable, {
    model: 'gpt-4o',
    provider: 'openai',
    providerLabel: 'OpenAI',
    state: 'invalid',
});
assert.equal(savedOption.selected, true);
assert.equal(savedOption.disabled, true);
assert.match(savedOption.textContent, /saved; retained key did not pass validation/);

const textSelect = new FakeElement('select');
const imageSelect = new FakeElement('select');
const pairedView = new SettingsView();
pairedView.root = {
    getAttribute: () => 'gpt-4o',
    querySelector: (selector) => selector === '#ai-scribe-model' ? textSelect : imageSelect,
};
pairedView.populateModelSelect(models, { openai: { configured: true, validated: false }, gemini: { configured: true, validated: false } });
pairedView.populateImageModelSelect([], { openai: { configured: true, validated: false }, gemini: { configured: true, validated: false } });
assert.deepEqual(pairedView.savedUnavailable, {
    model: 'gpt-4o',
    provider: 'openai',
    providerLabel: 'OpenAI',
    state: 'invalid',
});

const fallbackView = new SettingsView();
const fallbackSelect = new FakeElement('select');
const fallback = fallbackView.fillSelect(
    fallbackSelect,
    models,
    'gpt-4o',
    { openai: { configured: true, validated: false }, gemini: { configured: true, validated: true } }
);
assert.equal(fallback, 'gemini-2.5-flash');
assert.equal(fallbackView.lastFallbackFrom, 'gpt-4o');
assert.equal(fallbackView.savedUnavailable, null);

console.log('Saved unavailable model remains visible; a validated alternative still wins when one exists');
