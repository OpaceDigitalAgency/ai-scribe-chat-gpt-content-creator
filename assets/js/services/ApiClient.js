/**
 * ApiClient — the single network layer for the AI-Scribe v3 UI.
 *
 * Implements the BINDING docs/API_CONTRACT.md (P2 generation engine).
 * Extensions still awaiting engine confirmation are marked EXTENSION and
 * listed in docs/API_CONTRACT_UI_PROPOSAL.md. Nothing else in assets/js
 * may call fetch/XHR directly.
 *
 * - Nonce handling on every request.
 * - Typed errors (ApiError with code/retryable) — no silent failures.
 * - Streaming consumer for SSE-over-fetch (long-form steps + Express mode).
 * - Bounded retry affordance for retryable errors (caller opts in).
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

/* exported ApiClient, ApiError */

class ApiError extends Error {
    /**
     * @param {string}  message   Human-readable message (always displayable).
     * @param {Object}  detail    { code, retryable, status, provider }
     */
    constructor(message, detail = {}) {
        super(message || 'Unknown error');
        this.name = 'ApiError';
        this.code = detail.code || 'unknown';
        this.retryable = detail.retryable === true;
        this.status = detail.status || 0;
        this.provider = detail.provider || null;
    }

    static fromResponsePayload(payload, httpStatus) {
        if (payload && typeof payload === 'object') {
            // Legacy handlers (shortcode save/remove) use {msg} not {message}.
            return new ApiError(payload.message || payload.msg || 'Request failed', {
                code: payload.code,
                retryable: payload.retryable,
                status: payload.status || httpStatus,
                provider: payload.provider
            });
        }
        return new ApiError(typeof payload === 'string' ? payload : 'Request failed', {
            code: 'unknown',
            status: httpStatus
        });
    }
}

class ApiClient {
    /**
     * @param {Object} config { ajaxUrl, nonce, maxRetries }
     */
    constructor(config = {}) {
        const localized = window.ai_scribe || {};
        this.ajaxUrl = config.ajaxUrl || localized.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        this.nonce = config.nonce || localized.nonce || '';
        this.maxRetries = Number.isInteger(config.maxRetries) ? config.maxRetries : 2;
        this.activeStreams = new Map();
    }

    /* ------------------------------------------------------------------ */
    /* Core request plumbing                                              */
    /* ------------------------------------------------------------------ */

    buildFormData(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('security', this.nonce);
        Object.keys(data).forEach((key) => {
            const value = data[key];
            if (value === undefined || value === null) {
                return;
            }
            formData.append(key, typeof value === 'object' ? JSON.stringify(value) : String(value));
        });
        return formData;
    }

    /**
     * Non-streaming JSON request. Resolves with `response.data`.
     *
     * @param {string} action  admin-ajax action name.
     * @param {Object} data    Request fields.
     * @param {Object} options { retry: boolean, signal: AbortSignal, onRetry: Function }
     */
    async request(action, data = {}, options = {}) {
        const attempts = options.retry ? this.maxRetries + 1 : 1;
        let lastError = null;

        for (let attempt = 0; attempt < attempts; attempt++) {
            try {
                return await this.requestOnce(action, data, options.signal);
            } catch (error) {
                lastError = error;
                const isRetryable = error instanceof ApiError && error.retryable;
                if (!isRetryable || attempt === attempts - 1) {
                    throw error;
                }
                const delay = this.backoffDelay(attempt);
                if (typeof options.onRetry === 'function') {
                    options.onRetry({
                        attempt: attempt + 1,
                        maxRetries: attempts - 1,
                        delay,
                        error
                    });
                }
                await this.backoff(attempt, delay);
            }
        }
        throw lastError;
    }

    async requestOnce(action, data, signal) {
        let response;
        try {
            response = await fetch(this.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: this.buildFormData(action, data),
                signal
            });
        } catch (networkError) {
            if (networkError && networkError.name === 'AbortError') {
                throw networkError;
            }
            throw new ApiError('Network error — check your connection and try again.', {
                code: 'network',
                retryable: true
            });
        }

        let payload = null;
        try {
            payload = await response.json();
        } catch (parseError) {
            throw new ApiError('The server returned an unreadable response.', {
                code: 'bad_json',
                status: response.status,
                retryable: response.status >= 500
            });
        }

        if (!response.ok || !payload || payload.success !== true) {
            const detail = payload ? payload.data : null;
            const error = ApiError.fromResponsePayload(detail, response.status);
            if (response.status === 429 || response.status >= 500) {
                error.retryable = true;
            }
            throw error;
        }

        return payload.data;
    }

    backoffDelay(attempt) {
        return Math.min(4000, 500 * Math.pow(2, attempt));
    }

    backoff(attempt, requestedDelay) {
        const delay = Number.isFinite(requestedDelay) ? requestedDelay : this.backoffDelay(attempt);
        return new Promise((resolve) => setTimeout(resolve, delay));
    }

    /* ------------------------------------------------------------------ */
    /* Streaming (SSE over fetch POST)                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Open a streaming generation. Falls back to non-streaming when the
     * response is JSON (server without SSE support, or mock provider).
     *
     * @param {string}   action    e.g. 'ai_scribe_stream_step'.
     * @param {Object}   data      Request fields.
     * @param {Object}   handlers  { onStart, onDelta, onUsage, onDone, onError }
     * @returns {{ abort: Function, finished: Promise }}
     */
    stream(action, data = {}, handlers = {}) {
        const controller = new AbortController();
        const streamKey = `${action}:${Date.now()}`;
        this.activeStreams.set(streamKey, controller);

        const finished = this.consumeStream(action, data, handlers, controller.signal)
            .catch((error) => {
                if (error && error.name === 'AbortError') {
                    return; // Caller aborted deliberately — not an error state.
                }
                const apiError = error instanceof ApiError
                    ? error
                    : new ApiError(error && error.message ? error.message : 'Streaming failed', {
                        code: 'stream_failed',
                        retryable: true
                    });
                if (typeof handlers.onError === 'function') {
                    handlers.onError(apiError);
                } else {
                    throw apiError;
                }
            })
            .finally(() => {
                this.activeStreams.delete(streamKey);
            });

        return {
            abort: () => controller.abort(),
            finished
        };
    }

    async consumeStream(action, data, handlers, signal) {
        const response = await fetch(this.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'text/event-stream, application/json' },
            body: this.buildFormData(action, data),
            signal
        });

        const contentType = (response.headers.get('Content-Type') || '').toLowerCase();

        // Fallback path: plain JSON response (no streaming available).
        if (contentType.indexOf('text/event-stream') === -1) {
            let payload = null;
            try {
                payload = await response.json();
            } catch (e) {
                throw new ApiError('The server returned an unreadable response.', {
                    code: 'bad_json',
                    status: response.status,
                    retryable: response.status >= 500
                });
            }
            if (!response.ok || !payload || payload.success !== true) {
                throw ApiError.fromResponsePayload(payload ? payload.data : null, response.status);
            }
            if (typeof handlers.onDone === 'function') {
                handlers.onDone(payload.data);
            }
            return payload.data;
        }

        if (!response.ok || !response.body) {
            throw new ApiError('Streaming request failed.', {
                code: 'stream_http',
                status: response.status,
                retryable: response.status === 429 || response.status >= 500
            });
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let doneData = null;

        for (;;) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }
            buffer += decoder.decode(value, { stream: true });

            // SSE frames are separated by a blank line.
            let boundary = buffer.indexOf('\n\n');
            while (boundary !== -1) {
                const frame = buffer.slice(0, boundary);
                buffer = buffer.slice(boundary + 2);
                const event = this.parseSseFrame(frame);
                if (event) {
                    doneData = this.dispatchSseEvent(event, handlers) || doneData;
                }
                boundary = buffer.indexOf('\n\n');
            }
        }

        if (doneData === null) {
            throw new ApiError('The stream ended before the article was complete.', {
                code: 'stream_incomplete',
                retryable: true
            });
        }
        return doneData;
    }

    parseSseFrame(frame) {
        const lines = frame.split('\n');
        let eventName = 'message';
        const dataLines = [];
        lines.forEach((line) => {
            if (line.startsWith('event:')) {
                eventName = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice(5).trim());
            }
        });
        if (dataLines.length === 0) {
            return null;
        }
        let data = null;
        try {
            data = JSON.parse(dataLines.join('\n'));
        } catch (e) {
            data = { raw: dataLines.join('\n') };
        }
        return { event: eventName, data };
    }

    dispatchSseEvent(event, handlers) {
        switch (event.event) {
            case 'delta':
                if (typeof handlers.onDelta === 'function') {
                    handlers.onDelta(event.data);
                }
                return null;
            case 'done':
                if (typeof handlers.onDone === 'function') {
                    handlers.onDone(event.data);
                }
                return event.data;
            case 'error':
                throw ApiError.fromResponsePayload(event.data, 200);
            default:
                return null;
        }
    }

    abortAllStreams() {
        this.activeStreams.forEach((controller) => controller.abort());
        this.activeStreams.clear();
    }

    /* ------------------------------------------------------------------ */
    /* Contract endpoints                                                 */
    /* ------------------------------------------------------------------ */

    /** §1 — create the server-side conversation (one per article). */
    startConversation(fields = {}) {
        return this.request('ai_scribe_start_conversation', fields);
    }

    /**
     * §2 — run one wizard step in the conversation thread.
     *
     * @param {number} conversationId
     * @param {number} step 1-11
     * @param {Object} extra { prompt_override, regenerate: '1', model }
     */
    runStep(conversationId, step, extra = {}, options = {}) {
        return this.request(
            'ai_scribe_run_step',
            Object.assign({ conversation_id: conversationId, step }, extra),
            options
        );
    }

    optimiseMeta(conversationId, title, description) {
        return this.request('ai_scribe_optimise_meta', {
            conversation_id: conversationId,
            title,
            description
        }, { retry: true });
    }

    /** §3 — persist the user's choice for a completed step. */
    saveSelection(conversationId, key, value) {
        return this.request('ai_scribe_save_selection', {
            conversation_id: conversationId,
            key,
            value
        });
    }

    /** §4 — full conversation state (crash recovery / re-render). */
    getState(conversationId) {
        return this.request('ai_scribe_get_state', { conversation_id: conversationId });
    }

    /** §5 — Express one-shot whole-article generation. */
    runExpress(fields = {}, options = {}) {
        return this.request('ai_scribe_run_express', fields, options);
    }

    /** Extend the persisted Express draft without replacing it on failure. */
    improveExpressLength(conversationId, options = {}) {
        return this.request(
            'ai_scribe_improve_article_length',
            { conversation_id: conversationId },
            options
        );
    }

    /** Extend the exact Wizard HTML currently visible without replacing it on failure. */
    improveWizardLength(conversationId, currentHtml, bodyOnly, options = {}) {
        return this.request(
            'ai_scribe_improve_article_length',
            {
                conversation_id: conversationId,
                current_html: currentHtml,
                body_only: bodyOnly ? 1 : 0
            },
            options
        );
    }

    /**
     * §6 — pre-flight cost estimate.
     *
     * The UI re-estimates on every step change, so callers may pass a signal
     * to drop a superseded estimate rather than let it land out of order.
     *
     * @param {Object} fields  { conversation_id, step, mode, model }
     * @param {Object} options { signal: AbortSignal }
     */
    estimateCost(fields = {}, options = {}) {
        return this.request(
            'ai_scribe_estimate_cost',
            fields,
            Object.assign({ retry: true }, options)
        );
    }

    /** §7 — save/publish the article as a WP post. */
    savePost(conversationId, fields = {}) {
        return this.request(
            'ai_scribe_save_post',
            Object.assign({ conversation_id: conversationId }, fields)
        );
    }

    /** §8 — SSE streaming for long-form steps 4, 6, 7, 11. */
    streamStep(conversationId, step, extra, handlers) {
        return this.stream(
            'ai_scribe_stream_step',
            Object.assign({ conversation_id: conversationId, step }, extra || {}),
            handlers
        );
    }

    /**
     * Generate one image via ImageService (existing ai_scribe_generate_image
     * handler; UAT §12.4 wires the gallery buttons to it). Resolves the raw
     * success payload: { url, attachment_id, image_html, alt_text, … }.
     */
    async generateImage(prompt, imageOptions = {}, requestOptions = {}) {
        // ImageService responds with wp_send_json_success(payload) where the
        // payload itself carries url/attachment_id (no nested data field).
        return this.request('ai_scribe_generate_image', {
            prompt,
            image_options: JSON.stringify(imageOptions || {})
        }, Object.assign({}, requestOptions, { retry: true }));
    }

    /**
     * One image for ONE outline section (ai_scribe_generate_section_images
     * single-section mode): passing `index` makes the server generate just
     * that section's image, so the UI can report true "N of M" progress and
     * bill one unit per request. The server builds a distinct prompt per
     * heading; `userPrompt` is the optional guidance from the panel field.
     *
     * @param {Array<string>} sections     Every selected section heading.
     * @param {number}        index        Which section to generate now.
     * @param {string}        articleTitle Title context for the prompt.
     * @param {string}        userPrompt   Optional user guidance.
     */
    async generateSectionImage(sections, index, articleTitle, userPrompt, prompt, imageOptions = {}, requestOptions = {}) {
        return this.request('ai_scribe_generate_section_images', {
            sections: JSON.stringify(sections),
            index: String(index),
            article_title: articleTitle || '',
            user_prompt: userPrompt || '',
            prompt: prompt || '',
            image_options: JSON.stringify(imageOptions || {})
        }, Object.assign({}, requestOptions, { retry: true }));
    }

    /**
     * Save the reviewed article as a reusable shortcode row (legacy
     * al_scribe_send_shortcode_page handler; ShortcodeService). Resolves
     * { msg, shortcode_id }.
     */
    saveShortcode(fields = {}) {
        return this.request('al_scribe_send_shortcode_page', fields);
    }

    /* --- Existing v4 handlers kept for the settings screens --- */

    saveContentSettings(settings) {
        return this.request('ai_scribe_save_content_settings', { settings });
    }

    savePromptSettings(prompts) {
        return this.request('ai_scribe_save_prompt_settings', { prompts });
    }

    getPrompts() {
        return this.request('ai_scribe_get_prompts', {});
    }

    /* --- EXTENSIONS awaiting engine confirmation (see proposal doc) --- */

    getAvailableModels(provider, refresh) {
        const fields = {};
        if (provider) {
            fields.provider = provider;
        }
        if (refresh) {
            fields.refresh = '1';
        }
        return this.request('ai_scribe_get_available_models', fields, { retry: true });
    }

    getSettings() {
        return this.request('ai_scribe_get_settings', {});
    }

    saveApiKeys(keys) {
        return this.request('ai_scribe_save_api_keys', { keys });
    }

    saveUiPrefs(prefs) {
        return this.request('ai_scribe_save_ui_prefs', { prefs });
    }
}

// Export for module systems (tests) without breaking classic script loading.
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ApiClient, ApiError };
}
