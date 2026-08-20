/**
 * SettingsController — finished implementation for the v3 settings screen
 * (templates/settings_template.php).
 *
 * Responsibilities: collect + save the four setting groups, feed the model
 * pickers from the live model-list AJAX, honest parameter bounds (values
 * pass through unmodified), prompt library persistence with the exact
 * `ab_prompts_content` keys (including capital-K `Keywords_prompts`).
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* exported SettingsController */

class SettingsController {
    /**
     * @param {Object} view       SettingsView.
     * @param {Object} apiClient  ApiClient.
     * @param {Object} appState   AppState (optional on the settings screen).
     */
    constructor(view, apiClient, appState = null) {
        this.view = view;
        this.api = apiClient;
        this.appState = appState;
    }

    async init() {
        if (!this.view.isSettingsScreen()) {
            return;
        }
        this.models = [];
        this.view.bind(this);
        /*
         * Fetch live on every page load rather than serving the hour-old
         * cache. Opening Settings and seeing a stale list — then having to
         * notice, and press Refresh — is not a reasonable thing to ask, and
         * it made a correct list indistinguishable from an outdated one. The
         * cache remains as the fallback when a provider cannot be reached.
         */
        await this.loadModels({ refresh: true });
        this.loadProviderStatus();
    }

    /**
     * §13.5: provider chips reflect a VALIDATED key (cheap live test call,
     * server-cached) — not mere key presence. Failure degrades silently to
     * the server-rendered configured/missing state.
     */
    async loadProviderStatus() {
        try {
            const data = await this.api.getSettings();
            if (data && data.providers) {
                this.view.updateProviderStatus(data.providers);
            }
        } catch (error) {
            // Server-rendered chips remain — no degradation of function.
        }
    }

    /**
     * Feed both model pickers from the LIVE model list (configured providers
     * fetch their provider /models endpoints server-side, transient-cached
     * ~1h). `refresh: true` bypasses the cache (the Refresh models button).
     */
    async loadModels(options = {}) {
        try {
            if (options.refresh) {
                this.view.setModelListStatus('loading', 0);
            }
            const data = await this.api.getAvailableModels(null, options.refresh === true);
            this.models = (data && data.models) || [];
            this.providers = (data && data.providers) || {};

            // §13 addendum: image-category models never appear in the TEXT
            // picker (and vice versa) — category is authoritative, not the
            // multimodal capabilities list.
            // Only text models can write, only image models can draw, and a
            // provider's live list also carries speech, embedding and tooling
            // models that can do neither. Those belong in neither picker: one
            // of them was being offered as the default article model.
            const textModels = this.models.filter((m) => ['text', 'reasoning'].includes(this.category(m)));
            const imageModels = this.models.filter((m) => this.category(m) === 'image');

            const selected = this.view.populateModelSelect(textModels, this.providers);
            this.view.populateImageModelSelect(imageModels, this.providers);
            this.view.setModelListStatus('loaded', this.models.length, null, data && data.sources);
			if (options.refresh) {
				this.view.notify({
					title: 'Model list refreshed',
					message: `${this.models.length} available models loaded from your configured providers.`,
					type: 'success',
					announce: false,
					key: 'models-refreshed'
				});
			}

            // Dead-selection fallback is VISIBLE, never silent.
            if (this.view.fallbackFrom) {
                const from = this.view.fallbackFrom;
                this.view.announce(
                    from
                        ? `The saved model "${from}" is unavailable (provider not configured or model retired) — switched to ${selected}. Save to keep it.`
                        : `Selected ${selected} from your validated providers.`
                );
                this.view.setModelListStatus(
                    'loaded',
                    this.models.length,
                    null,
                    data && data.sources,
                    from ? `Saved model "${from}" is unavailable — switched to ${selected}.` : ''
                );
            }
            this.onModelChange();
        } catch (error) {
            // Degrade visibly, not silently.
            this.view.setModelListStatus('error', 0, error);
			if (options.refresh) {
				this.view.notify({
					title: 'Model list was not refreshed',
					message: error && error.message ? error.message : 'Check your provider settings and try again.',
					type: 'error',
					announce: false,
					key: 'models-refresh-error'
				});
			}
        }
    }

    /** Model category with a modality backstop for live-registered ids. */
    category(model) {
        if (model.category) {
            return model.category;
        }
        const id = model.id || '';
        if (/(^|-)image(-|$)|dall-e|imagen|nano-banana/i.test(id)) {
            return 'image';
        }
        // Speech, audio, video, music, embedding and tooling models. They are
        // returned by the provider's model list but cannot generate an
        // article, so they belong in neither picker.
        if (/(^|-)(tts|audio|speech|embedding|embed|veo|lyria|computer-use|live|rerank|guard)(-|$)/i.test(id)) {
            return 'other';
        }
        return 'text';
    }

    /** Re-render the per-model parameter panel from the schema (UAT §12.2). */
    onModelChange() {
        const selected = this.view.selectedModelId();
        const model = this.models.find((m) => m.id === selected) || null;
        this.view.renderModelParams(model);
    }

    supports(model, capability) {
        return !model.capabilities || model.capabilities.indexOf(capability) !== -1;
    }

    async save() {
        const payload = this.view.collect();
        this.view.setSaving(true);
        try {
            await this.api.saveContentSettings(payload.settings);
            await this.api.savePromptSettings(payload.prompts);
            if (payload.hasKeys) {
                await this.api.saveApiKeys(payload.keys);
            }
            // Dynamic model controls are rebuilt from root data attributes.
            // Advance those attributes only after every settings write has
            // succeeded, otherwise a model-list refresh can repaint the old
            // thinking level while the database already contains the new one.
            this.view.commitSavedSettings(payload.settings);
            this.reflectCustomLanguage(payload.settings.custom_language);
            this.view.announceSaved(payload.settings);
        } catch (error) {
            this.view.showError(error, () => this.save());
        } finally {
            this.view.setSaving(false);
        }
    }

    /**
     * After saving a new custom language (2.6.2 Add New Language), append it
     * to the language select and clear the input so the UI reflects the save
     * without a reload.
     */
    reflectCustomLanguage(language) {
        if (!language) {
            return;
        }
        const select = document.getElementById('ai-scribe-language');
        const input = document.getElementById('ai-scribe-custom-language');
        if (select && !Array.from(select.options).some((o) => o.value === language)) {
            const option = document.createElement('option');
            option.value = language;
            option.textContent = language;
            select.appendChild(option);
        }
        if (select) {
            select.value = language;
        }
        if (input) {
            input.value = '';
        }
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SettingsController;
}
