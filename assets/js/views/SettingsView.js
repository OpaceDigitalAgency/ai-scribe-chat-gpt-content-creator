/**
 * SettingsView — DOM layer for the v3 settings screen
 * (templates/settings_template.php). Tab switching with ARIA state,
 * form collection, model-picker population, save feedback.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* exported SettingsView */

class SettingsView {

    /**
     * Human-readable provider name, matching WizardFlowController.providerLabel().
     *
     * @param {string} provider Provider slug.
     * @return {string} Display label.
     */
    static providerLabel(provider) {
        const labels = {
            openai: 'OpenAI',
            anthropic: 'Anthropic',
            gemini: 'Google Gemini',
            wordpress: 'WordPress AI',
        };
        const key = String(provider || '').toLowerCase();
        return labels[key] || (key.charAt(0).toUpperCase() + key.slice(1));
    }

    static i18n(key, fallback) {
        const strings = window.ai_scribe && window.ai_scribe.i18n;
        return strings && strings[key] ? strings[key] : fallback;
    }

    constructor(container, appState = null) {
        this.root = document.getElementById('ai-scribe-settings-root');
        this.appState = appState;
        this.controller = null;
    }

    isSettingsScreen() {
        return !!this.root;
    }

    bind(controller) {
        this.controller = controller;
        this.restoreTab();
        window.addEventListener('popstate', () => this.restoreTab());
        this.root.addEventListener('click', (e) => {
            const tab = e.target.closest('.tab-btn');
            if (tab) {
                this.switchTab(tab.dataset.tab);
                return;
            }
            const refresh = e.target.closest('[data-action="refresh-models"]');
            if (refresh) {
                // Visible acknowledgement: the button spins and locks while
                // the live fetch runs; the status line reports the outcome.
                refresh.disabled = true;
                refresh.classList.add('is-busy');
                refresh.setAttribute('aria-busy', 'true');
                Promise.resolve(controller.loadModels({ refresh: true })).finally(() => {
                    refresh.disabled = false;
                    refresh.classList.remove('is-busy');
                    refresh.setAttribute('aria-busy', 'false');
                });
                return;
            }
            const revert = e.target.closest('[data-action="revert-hub-prompt"]');
            if (revert) {
                this.applyHubPrompt(parseInt(revert.dataset.step, 10), 0);
                return;
            }
            const save = e.target.closest('[data-action="save-settings"]');
            if (save) {
                controller.save();
            }
        });
        const modelSelect = this.root.querySelector('#ai-scribe-model');
        if (modelSelect) {
            modelSelect.addEventListener('change', () => controller.onModelChange());
        }
        this.root.querySelectorAll('.hub-prompt-select').forEach((select) => {
            select.addEventListener('change', () => {
                this.applyHubPrompt(parseInt(select.dataset.step, 10), parseInt(select.value, 10) || 0);
            });
        });
        // Honest bounds enforcement at the input level (clamp, no scaling).
        this.root.addEventListener('change', (e) => {
            const input = e.target;
            if (input.id === 'ai-scribe-length-mode') {
                this.syncLengthControl();
            }
            if (input.id === 'ai-scribe-temperature' || input.id === 'ai-scribe-top-p') {
                const min = parseFloat(input.min);
                const max = parseFloat(input.max);
                const value = parseFloat(input.value);
                if (!Number.isNaN(value)) {
                    input.value = String(Math.min(max, Math.max(min, value)));
                }
            }
        });
        this.syncLengthControl();
    }

    /** Show the custom word field only when Custom is the chosen length mode. */
    syncLengthControl() {
        if (!this.root) {
            return;
        }
        const mode = this.root.querySelector('#ai-scribe-length-mode');
        const custom = this.root.querySelector('[data-settings-custom-word-count]');
        if (!mode || !custom) {
            return;
        }
        const visible = mode.value === 'custom';
        custom.hidden = !visible;
        const input = custom.querySelector('#ai-scribe-word-count');
        if (input) {
            input.disabled = !visible;
        }
    }

    /**
     * Pin an Opace AI Hub prompt to a wizard step, or (promptId 0) hand the step
     * back to the AI-Scribe prompt above it.
     *
     * The choice persists immediately rather than waiting for Save Settings:
     * it is not one of the settings groups the save payload carries, and a
     * half-applied prompt sitting unsaved in the DOM would misreport which
     * prompt a run is about to use. Routed through the ApiClient the
     * controller holds — assets/js never calls fetch directly.
     *
     * @param {number} step     Wizard step 1-11.
     * @param {number} promptId Opace AI Hub prompt id, 0 to revert.
     */
    applyHubPrompt(step, promptId) {
        const api = this.controller && this.controller.api;
        if (!api || !step) {
            return;
        }
        const row = this.root.querySelector(`.hub-prompt-apply[data-step="${step}"]`);
        const select = row && row.querySelector('.hub-prompt-select');
        const revert = row && row.querySelector('[data-action="revert-hub-prompt"]');
        const state = this.root.querySelector(`[data-hub-prompt-state="${step}"]`);

        api.request('ai_scribe_apply_hub_prompt', { step, prompt_id: promptId })
            .then((data) => {
                if (select) {
                    select.value = String(promptId);
                    select.setAttribute('data-applied', String(promptId));
                }
                if (revert) {
                    revert.disabled = promptId === 0;
                }
                if (state) {
                    state.textContent = (data && data.message) || '';
                }
				const message = (data && data.message) || 'Prompt updated.';
				this.announce(message);
				this.notify({ title: 'Prompt source updated', message, type: 'success', announce: false });
            })
            .catch((error) => {
                // Put the control back to what the server still believes,
                // so the UI never claims a prompt that was not applied.
                if (select) {
                    select.value = select.getAttribute('data-applied') || select.value;
                }
                this.showError(error, false);
            });
    }

    /** Tab ids this screen actually renders, in document order. */
    tabNames() {
        return Array.from(this.root.querySelectorAll('.tab-btn')).map((btn) => btn.dataset.tab);
    }

    /**
     * The tab the URL asks for: ?tab=generation, or a #tab-generation /
     * #generation fragment. Falls back to the server-rendered active tab so a
     * bare settings URL behaves exactly as before.
     */
    requestedTab() {
        const names = this.tabNames();
        const valid = (name) => (name && names.indexOf(name) !== -1 ? name : '');
        let requested = '';
        try {
            requested = valid(new URL(window.location.href).searchParams.get('tab'));
        } catch (e) {
            requested = '';
        }
        if (!requested && window.location.hash) {
            requested = valid(window.location.hash.replace(/^#(tab-|tab=)?/, ''));
        }
        if (requested) {
            return requested;
        }
        const active = this.root.querySelector('.tab-btn.active');
        return (active && active.dataset.tab) || names[0] || '';
    }

    /** Open whichever tab the URL names, without rewriting the URL. */
    restoreTab() {
        const tabName = this.requestedTab();
        if (tabName) {
            this.switchTab(tabName, false);
        }
    }

    /**
     * D-14: the open tab is state, so it lives in the URL — it survives a
     * reload after saving, and a tab is linkable and shareable.
     *
     * @param {string}  tabName   Tab id.
     * @param {boolean} updateUrl Mirror the change into the address bar.
     */
    switchTab(tabName, updateUrl = true) {
        this.root.querySelectorAll('.tab-btn').forEach((btn) => {
            const isActive = btn.dataset.tab === tabName;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        this.root.querySelectorAll('.tab-content').forEach((panel) => {
            const isActive = panel.dataset.tab === tabName;
            panel.classList.toggle('active', isActive);
            panel.hidden = !isActive;
        });
        if (updateUrl) {
            this.rememberTab(tabName);
        }
    }

    /** Write the open tab into the address bar without adding history noise. */
    rememberTab(tabName) {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.get('tab') === tabName) {
                return;
            }
            url.searchParams.set('tab', tabName);
            window.history.replaceState(window.history.state, '', url.toString());
        } catch (e) {
            // Address bar is a convenience here — never break tab switching.
        }
    }

    /**
     * §13 addendum: the text-model picker is GROUPED by provider; only
     * models from validated providers are selectable; unconfigured/invalid
     * providers render greyed out with a "configure in Opace AI Hub" affordance.
     * If no provider currently validates, the saved model remains visibly
     * selected (but disabled) so an upgrade never looks as though it erased
     * the user's choice.
     * Returns the value actually selected (after any dead-selection
     * fallback) so the controller can announce it.
     */
    populateModelSelect(models, providers = {}) {
        const selected = this.fillSelect(
            this.root.querySelector('#ai-scribe-model'),
            models,
            this.root.getAttribute('data-current-model') || '',
            providers
        );
        // Snapshot the TEXT picker's fallback origin — the image picker fill
        // below must not clobber it. The same applies to a retained but
        // unusable text model: fillSelect() resets both pieces of state for
        // every select it builds.
        this.fallbackFrom = this.lastFallbackFrom;
        this.textSavedUnavailable = this.savedUnavailable;
        return selected;
    }

    populateImageModelSelect(models, providers = {}) {
        const select = this.root.querySelector('#ai-scribe-image-model');
        const current = select && select.value;
        const selected = this.fillSelect(select, models, current || '', providers);
        this.savedUnavailable = this.textSavedUnavailable || null;
        return selected;
    }

    providerSelectable(providers, providerId) {
        const info = providers && providers[providerId];
        if (!info) {
            // Unknown provider row (e.g. the WordPress core AI client):
            // selectable only when the model itself reports configured.
            return null;
        }
        return info.validated === true;
    }

    fillSelect(select, models, currentValue, providers) {
        if (!select) {
            return '';
        }
        select.textContent = '';

        const providerLabels = {
            openai: 'OpenAI',
            anthropic: 'Anthropic',
            gemini: 'Google Gemini',
            wordpress: 'WordPress'
        };
        const order = [];
        const grouped = {};
        models.forEach((model) => {
            const key = model.provider || 'other';
            if (!grouped[key]) {
                grouped[key] = [];
                order.push(key);
            }
            grouped[key].push(model);
        });

        let selectedValue = '';
        let unavailableCurrent = null;
        order.forEach((providerId) => {
            const selectable = this.providerSelectable(providers, providerId);
            const groupEnabled = selectable !== false
                && (selectable === true || grouped[providerId].some((m) => m.configured !== false));
            const group = document.createElement('optgroup');
            const baseLabel = providerLabels[providerId] || providerId;
            group.label = groupEnabled
                ? baseLabel
                : `${baseLabel} — not configured (add a key in Opace AI Hub)`;
            grouped[providerId].forEach((model) => {
                const option = document.createElement('option');
                option.value = model.id;
                const pricing = model.pricing || {};
                const input = pricing.input_per_1m ?? pricing.input_per_mtok_usd;
                const output = pricing.output_per_1m ?? pricing.output_per_mtok_usd;
                const price = (input !== undefined && input !== null && output !== undefined && output !== null)
                    ? ` — $${input}/$${output} per 1M`
                    : '';
                option.textContent = `${model.label || model.id}${price}`;
                option.disabled = !groupEnabled;
                if (model.id === currentValue) {
                    if (option.disabled) {
                        unavailableCurrent = option;
                    } else {
                        option.selected = true;
                        selectedValue = model.id;
                    }
                }
                group.appendChild(option);
            });
            select.appendChild(group);
        });

        // Nothing selected. Two different situations, two different answers:
        //
        // - Nothing was ever chosen (fresh site, key just added): pick the BEST
        //   model the validated providers offer. The list arrives already
        //   ranked best-first from the server, off the registry's own priority
        //   data, so "best" is simply the first selectable entry.
        // - A saved selection went dead (provider unconfigured, model retired):
        //   fall back to the CHEAPEST validated model. Substituting the most
        //   expensive model for one the user is already paying for is not a
        //   decision to make on their behalf.
        this.lastFallbackFrom = null;
        this.savedUnavailable = null;
        if (!selectedValue) {
            const hadSelection = !!currentValue;
            const fallback = hadSelection
                ? this.cheapestSelectable(models, providers)
                : this.bestSelectable(models, providers);
            if (fallback) {
                select.value = fallback.id;
                selectedValue = fallback.id;
                this.lastFallbackFrom = currentValue || '';
            } else if (unavailableCurrent) {
                const providerId = models.find((model) => model.id === currentValue)?.provider || '';
                const providerInfo = providers && providers[providerId] ? providers[providerId] : {};
                const state = !providerInfo.configured
                    ? 'missing'
                    : (providerInfo.validated === false ? 'invalid' : 'unchecked');
                const suffixKey = state === 'invalid'
                    ? 'savedKeyInvalidSuffix'
                    : (state === 'missing' ? 'savedKeyMissingSuffix' : 'savedKeyUncheckedSuffix');
                const suffixFallback = state === 'invalid'
                    ? 'saved; retained key did not pass validation'
                    : (state === 'missing' ? 'saved; provider key is missing' : 'saved; retained key could not be checked');
                unavailableCurrent.selected = true;
                unavailableCurrent.textContent += ` — ${SettingsView.i18n(suffixKey, suffixFallback)}`;
                selectedValue = currentValue;
                this.savedUnavailable = {
                    model: currentValue,
                    provider: providerId,
                    providerLabel: SettingsView.providerLabel(providerId),
                    state,
                };
            }
        }
        return selectedValue;
    }

    /**
     * Best selectable model: the first entry from a validated provider, the
     * list already being ranked most-capable-first server-side.
     */
    bestSelectable(models, providers) {
        return models.find((m) => this.providerSelectable(providers, m.provider) === true) || null;
    }

    /** Cheapest selectable model: lowest input price, then cheap-tier name. */
    cheapestSelectable(models, providers) {
        const usable = models.filter((m) => this.providerSelectable(providers, m.provider) === true);
        if (usable.length === 0) {
            return null;
        }
        const priced = usable
            .filter((m) => m.pricing && m.pricing.input_per_1m !== null && m.pricing.input_per_1m !== undefined)
            .sort((a, b) => a.pricing.input_per_1m - b.pricing.input_per_1m);
        if (priced.length) {
            return priced[0];
        }
        // No pricing metadata: prefer the cheapest tier by naming convention.
        const tiers = [/nano/i, /haiku/i, /flash/i, /mini/i];
        for (const tier of tiers) {
            const match = usable.find((m) => tier.test(m.id));
            if (match) {
                return match;
            }
        }
        return usable[0];
    }

    setModelListStatus(state, count, error = null, sources = null, extra = '') {
        const status = this.root.querySelector('[data-testid="model-list-status"]');
        if (!status) {
            return;
        }
        if (state === 'loading') {
            status.textContent = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.refreshingModels) || 'Refreshing the model list from your providers…';
            status.classList.remove('form-error');
            return;
        }
        if (state === 'loaded') {
            let suffix = '';
            if (sources && typeof sources === 'object') {
                const live = Object.keys(sources).filter((p) => String(sources[p]).indexOf('live') === 0);
                // A configured provider whose fetch failed must say so. It
                // used to be reported as "no providers configured", so a
                // stale or truncated list looked like a normal one.
                const failed = Object.keys(sources).filter((p) => sources[p] === 'registry-fallback');
                if (live.length) {
                    suffix = ` Live from: ${live.map((p) => SettingsView.providerLabel(p)).join(', ')}.`;
                } else if (!failed.length) {
                    suffix = ' No providers configured yet — showing the built-in registry.';
                }
                if (failed.length) {
                    suffix += ` Could not reach ${failed.map((p) => SettingsView.providerLabel(p)).join(', ')} —`
                        + ' showing the built-in list for now. Press Refresh models to try again.';
                }
            }
            status.textContent = `${count} models loaded.${suffix}${extra ? ` ${extra}` : ''}`;
            status.classList.toggle('form-error', !!extra);
        } else {
            status.textContent = (error && error.message)
                ? `Model list unavailable: ${error.message}`
                : 'Model list unavailable — check your API keys.';
            status.classList.add('form-error');
        }
    }

    selectedModelId() {
        const select = this.root.querySelector('#ai-scribe-model');
        return select ? select.value : '';
    }

    /**
     * Per-model parameter panel generated from the ModelRegistry parameter
     * schema (UAT §12.2): number and select controls (reasoning effort,
     * thinking level, …). Temperature/top_p keep their dedicated inputs on
     * the Generation tab and are hidden when the model doesn't support them.
     */
    renderModelParams(model) {
        const panel = this.root.querySelector('#ai-scribe-model-params');
        if (!panel) {
            return;
        }
        panel.textContent = '';
        const schema = (model && model.parameters) || {};
        const saved = this.savedModelParams();

        // Show/hide the dedicated temperature / top_p inputs by support.
        //
        // D-15: no registry schema declares top_p, yet the value is real —
        // GenerationService.build_request_options() puts the saved top_p on
        // the wire for every model. The two sampling parameters are only ever
        // dropped together (Model_Schema_Inference::infer() unsets
        // temperature and top_p in one statement for the OpenAI reasoning
        // family; the Opace AI Hub adapter does the same for the o-series), so
        // temperature support is the honest signal for both. Without this the
        // Top P control could never render for any model.
        const hasSchema = Object.keys(schema).length > 0;
        const supports = {
            temperature: !hasSchema || !!schema.temperature,
            top_p: !hasSchema || !!schema.top_p || !!schema.temperature,
        };
        [ ['temperature', '#ai-scribe-temperature'], ['top_p', '#ai-scribe-top-p'] ].forEach(([key, selector]) => {
            const input = this.root.querySelector(selector);
            const group = input && input.closest('.form-group');
            if (group) {
                // Unknown schema (no metadata) keeps the inputs visible.
                group.hidden = !supports[key];
            }
        });

        const keys = Object.keys(schema).filter((k) => k !== 'temperature' && k !== 'top_p');
        if (keys.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'form-hint';
            // §13 addendum: this copy must only appear for GENUINELY
            // parameterless models and must name the model it applies to.
            empty.textContent = model
                ? `${model.label || model.id} exposes no adjustable parameters beyond the defaults — output length is managed per step automatically.`
                : 'Select a model to see its parameters.';
            panel.appendChild(empty);
            return;
        }

        const heading = document.createElement('h3');
        heading.className = 'settings-section-title';
        heading.textContent = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.modelParameters) || 'Model parameters';
        panel.appendChild(heading);

        keys.forEach((key) => {
            const def = schema[key] || {};
            const group = document.createElement('div');
            group.className = 'form-group';
            const id = `ai-scribe-param-${key}`;

            const label = document.createElement('label');
            label.setAttribute('for', id);
            label.textContent = def.label || key;
            group.appendChild(label);

            const savedValue = saved[key] !== undefined ? saved[key] : def.default;
            let input;
            if (def.type === 'select' && def.options && typeof def.options === 'object') {
                input = document.createElement('select');
                // Options arrive either as a value=>label map (inferred
                // schemas) or an array of {value, label} objects (curated
                // registry seed entries) — normalise both.
                const pairs = Array.isArray(def.options)
                    ? def.options.map((o) => (o && typeof o === 'object'
                        ? [String(o.value), String(o.label !== undefined ? o.label : o.value)]
                        : [String(o), String(o)]))
                    : Object.keys(def.options).map((value) => [value, String(def.options[value])]);
                pairs.forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    option.selected = String(savedValue) === String(value);
                    input.appendChild(option);
                });
            } else {
                input = document.createElement('input');
                input.type = def.type === 'number' ? 'number' : 'text';
                if (def.min !== undefined) { input.min = String(def.min); }
                if (def.max !== undefined) { input.max = String(def.max); }
                if (def.step !== undefined) { input.step = String(def.step); }
                if (savedValue !== undefined && savedValue !== null) {
                    input.value = String(savedValue);
                }
            }
            input.id = id;
            input.className = 'form-control model-param-field';
            input.setAttribute('data-param-key', key);
            input.setAttribute('data-testid', `model-param-${key}`);
            group.appendChild(input);

            if (def.help) {
                const help = document.createElement('p');
                help.className = 'form-hint';
                help.textContent = String(def.help);
                group.appendChild(help);
            }
            panel.appendChild(group);
        });
    }

    savedModelParams() {
        try {
            const raw = this.root.getAttribute('data-model-params');
            const parsed = raw ? JSON.parse(raw) : {};
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    /**
     * Make the just-saved dynamic model state the new hydration source.
     *
     * Model parameter controls are rebuilt whenever the model list refreshes
     * or the selected model changes. Those rebuilds read the server-rendered
     * data attributes, which previously remained frozen at page-load values.
     * A successful save could therefore be followed by a refresh that visibly
     * restored an older thinking level even though WordPress had stored the
     * new value correctly.
     *
     * @param {Object} settings Settings payload accepted by the server.
     */
    commitSavedSettings(settings = {}) {
        if (!this.root) {
            return;
        }
        if (settings.model) {
            this.root.setAttribute('data-current-model', String(settings.model));
        }
        const params = settings.model_params && typeof settings.model_params === 'object'
            ? settings.model_params
            : {};
        this.root.setAttribute('data-model-params', JSON.stringify(params));
    }

    /** Collect the payload groups from the form. */
    collect() {
        const value = (selector) => {
            const el = this.root.querySelector(selector);
            return el ? el.value : '';
        };
        const keys = {};
        let hasKeys = false;
        this.root.querySelectorAll('.provider-key-row input[type="password"]').forEach((input) => {
            const provider = input.closest('.provider-key-row').getAttribute('data-provider');
            if (input.value.trim() !== '') {
                keys[provider] = input.value.trim();
                hasKeys = true;
            }
        });
        const prompts = {};
        this.root.querySelectorAll('.prompt-library-field').forEach((field) => {
            // Exact ab_prompts_content keys, incl. capital-K Keywords_prompts.
            prompts[field.getAttribute('data-prompt-key')] = field.value;
        });
        const modelParams = {};
        this.root.querySelectorAll('.model-param-field').forEach((field) => {
            const key = field.getAttribute('data-param-key');
            if (key && field.value !== '') {
                modelParams[key] = field.type === 'number' ? parseFloat(field.value) : field.value;
            }
        });
        // check_Arr enhancement toggles — legacy stored shape key => key.
        const checkArr = {};
        this.root.querySelectorAll('.check-arr-field').forEach((box) => {
            if (box.checked) {
                checkArr[box.getAttribute('data-check-key')] = box.getAttribute('data-check-key');
            }
        });
        const modeRadio = this.root.querySelector('input[name="ai_scribe_mode"]:checked');
        const customLanguage = value('#ai-scribe-custom-language').trim();
        return {
            settings: {
                model: value('#ai-scribe-model'),
                model_params: modelParams,
                temperature: parseFloat(value('#ai-scribe-temperature')),
                top_p: parseFloat(value('#ai-scribe-top-p')),
                language: customLanguage || value('#ai-scribe-language'),
                custom_language: customLanguage,
                writing_style: value('#ai-scribe-style'),
                writing_tone: value('#ai-scribe-tone'),
                spelling: value('#ai-scribe-spelling') || 'british',
                heading_tag: value('#ai-scribe-heading-tag'),
                number_of_headings: parseInt(value('#ai-scribe-number-of-headings'), 10) || 5,
                article_length_mode: value('#ai-scribe-length-mode') || 'auto',
                article_word_count: parseInt(value('#ai-scribe-word-count'), 10) || 1800,
                avoid_keywords: value('#ai-scribe-avoid-keywords'),
                mode: modeRadio ? modeRadio.value : 'standard',
                check_arr: checkArr,
                delete_data_on_uninstall: !!this.root.querySelector('#ai-scribe-delete-data:checked'),
                images: {
                    enabled: !!this.root.querySelector('#ai-scribe-images-enabled:checked'),
                    model: value('#ai-scribe-image-model'),
                    size: value('#ai-scribe-image-size'),
                    quality: value('#ai-scribe-image-quality'),
                    format: value('#ai-scribe-image-format'),
                    background: value('#ai-scribe-image-background'),
                    style: value('#ai-scribe-image-style')
                }
            },
            prompts,
            keys,
            hasKeys
        };
    }

    /**
     * §13.5: reflect VALIDATED provider status on the chips (hub mode) and
     * key-status badges (standalone mode). validated: true = live test call
     * succeeded, false = provider rejected the key, null = not checkable.
     */
    updateProviderStatus(providers) {
        Object.keys(providers || {}).forEach((provider) => {
            const info = providers[provider] || {};
            const label = info.validated === true
                ? 'Validated'
                : (info.validated === false
                    ? 'Key invalid'
                    : (info.configured ? 'Configured' : 'Not configured'));

            const chip = this.root.querySelector(`[data-testid="provider-chip-${provider}"]`);
            if (chip) {
                chip.classList.toggle('provider-chip-validated', info.validated === true);
                chip.classList.toggle('provider-chip-invalid', info.validated === false);
                chip.classList.toggle('provider-chip-configured', info.validated !== false && !!info.configured);
                chip.classList.toggle('provider-chip-missing', !info.configured);
                const state = chip.querySelector('[data-chip-state]');
                if (state) {
                    state.textContent = label;
                }
            }

            const badge = this.root.querySelector(`[data-testid="key-status-${provider}"]`);
            if (badge) {
                badge.textContent = info.configured ? label : 'Missing';
                badge.classList.toggle('key-status-set', !!info.configured && info.validated !== false);
                badge.classList.toggle('key-status-invalid', info.validated === false);
                badge.classList.toggle('key-status-missing', !info.configured);
            }
        });
    }

    setSaving(saving) {
        const button = this.root.querySelector('[data-testid="settings-save"]');
        if (button) {
            button.disabled = saving;
            button.classList.toggle('is-busy', saving);
            button.setAttribute('aria-busy', saving ? 'true' : 'false');
        }
    }

    announceSaved(settings = {}) {
        const params = settings.model_params && typeof settings.model_params === 'object'
            ? settings.model_params
            : {};
        const thinking = params.thinking_level || params.reasoning_effort || '';
        const details = [settings.writing_style, thinking ? `${thinking} thinking` : '']
            .filter(Boolean)
            .join(' · ');
        const confirmation = details ? `Settings saved: ${details}.` : 'Settings saved.';
        this.announce(confirmation);
        this.showSaveFeedback(confirmation, false);
		this.notify({
			title: 'Settings saved',
			message: `${details ? `${details}. ` : ''}New articles will use these settings. An article already in progress keeps the settings it started with.`,
			type: 'success',
			announce: false,
			key: 'settings-saved'
		});
        // Blank out key fields after save so they are never re-displayed.
        this.root.querySelectorAll('.provider-key-row input[type="password"]').forEach((input) => {
            if (input.value.trim() !== '') {
                input.value = '';
                const status = input.closest('.provider-key-row').querySelector('.key-status');
                if (status) {
                    status.textContent = (window.ai_scribe && window.ai_scribe.i18n && window.ai_scribe.i18n.configured) || 'Configured';
                    status.classList.remove('key-status-missing');
                    status.classList.add('key-status-set');
                }
            }
        });
    }

    showError(error, onRetry) {
        const message = error && error.message ? error.message : 'Saving failed.';
        this.announce(onRetry ? `${message} Use Save Settings to try again.` : message);
        this.showSaveFeedback(onRetry ? `${message} Use Save Settings to try again.` : message, true);
		this.notify({
			title: 'Settings were not saved',
			message: onRetry ? `${message} Use Save Settings to try again.` : message,
			type: 'error',
			announce: false,
			key: 'settings-save-error'
		});
    }

	notify(options) {
		if (window.aiScribeNotifications) {
			window.aiScribeNotifications.show(options);
		}
	}

    announce(text) {
        const status = this.root.querySelector('[data-testid="settings-status"]');
        if (status) {
            status.textContent = text;
        }
    }

    /**
     * Visible confirmation beside the Save button.
     *
     * announce() writes to a visually-hidden live region, which a sighted
     * user never sees — clicking Save appeared to do nothing at all. This
     * shows the same message on screen and clears it after a few seconds so
     * it does not linger as a stale claim about unsaved edits.
     *
     * @param {string} text    Message to show.
     * @param {boolean} isError Style as a failure rather than a success.
     */
    showSaveFeedback(text, isError) {
        const el = this.root.querySelector('[data-testid="settings-save-feedback"]');
        if (!el) {
            return;
        }
        window.clearTimeout(this.saveFeedbackTimer);
        el.textContent = text;
        el.classList.toggle('is-error', !!isError);
        el.hidden = false;
        this.saveFeedbackTimer = window.setTimeout(() => {
            el.hidden = true;
            el.textContent = '';
        }, isError ? 10000 : 4000);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SettingsView;
}
