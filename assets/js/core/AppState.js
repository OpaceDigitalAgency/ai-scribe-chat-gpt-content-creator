/**
 * Application State Management
 * Centralized state container with observer pattern
 *
 * Persistence is per browser tab. Each tab keeps its own id in sessionStorage
 * (created once, kept across reloads of that tab) and writes its saved session
 * to `aiScribeState:<tabId>`. A reload therefore recovers exactly what that tab
 * was working on, while a second tab opened on the same wizard starts a new
 * article instead of silently joining — and overwriting — the first tab's
 * conversation.
 */
class AppState {

    /** Single-key format written before per-tab sessions; migrated once, then dropped. */
    static LEGACY_STORAGE_KEY = 'aiScribeState';

    /** localStorage key prefix for a tab's saved session. */
    static STORAGE_PREFIX = 'aiScribeState:';

    /** sessionStorage key holding this tab's id. */
    static TAB_ID_KEY = 'aiScribeTabId';

    /** Saved sessions older than this are pruned (24 hours). */
    static STORAGE_MAX_AGE_MS = 24 * 60 * 60 * 1000;

    /** Hard cap on stored sessions; the oldest are dropped first. */
    static STORAGE_MAX_SESSIONS = 8;

    constructor() {
        this.state = this.createInitialState();

        this.observers = [];
        this.history = [];
        this.maxHistorySize = 50;

        // Per-tab persistence identity. storageKey is where this tab saves.
        this.tabId = this.resolveTabId();
        this.storageKey = AppState.STORAGE_PREFIX + this.tabId;

        // Outcome of the last loadFromLocalStorage() call, for callers that
        // want to explain themselves to the user:
        //   'restored'  — this tab's saved session was recovered
        //   'empty'     — nothing saved anywhere, a genuinely fresh start
        //   'other-tab' — another tab holds an in-progress conversation, so
        //                 this tab starts a new article rather than joining it
        //   'error'     — storage unreadable; treated as a fresh start
        this.lastLoadStatus = 'empty';

        // Save initial state to history
        this.saveToHistory();
    }

    /**
     * Build a pristine state object.
     *
     * Used by the constructor and by reset(), so "Start Again" lands on
     * byte-for-byte the same state a freshly loaded page starts from.
     */
    createInitialState() {
        return {
            currentStep: 1,
            totalSteps: 11,
            // Null rather than absent: reset() has to be able to say
            // "no conversation" and have it survive a JSON round-trip.
            conversationId: null,
            stepData: new Map(),
            // Confirmed external persistence only. These values are written
            // after a successful server response, never when a save starts.
            persistence: {
                post: null,
                shortcode: null
            },
            settings: {
                apiKeys: {},
                contentSettings: {
                    tone: 'professional',
                    length: 'medium'
                },
                preferences: {
                    autoSave: true,
                    costAlerts: true
                }
            },
            ui: {
                isLoading: false,
                activeModal: null,
                errors: [],
                notifications: []
            },
            cost: {
                currentStepCost: 0,
                totalCost: 0,
                tokenEstimate: 0,
                selectedModel: 'gpt-4'
            }
        };
    }

    /**
     * Get current state (immutable copy)
     */
    getState() {
        return JSON.parse(JSON.stringify(this.state));
    }

    /**
     * Update state with partial updates
     *
     * @param {Object}  updates       Partial state, or the whole state when replacing.
     * @param {boolean} saveToHistory Push the result onto the undo history.
     * @param {boolean} replace       Swap the state object instead of merging.
     *                                A merge cannot remove a key, so anything
     *                                that must clear state (reset()) needs this.
     */
    setState(updates, saveToHistory = true, replace = false) {
        const prevState = this.getState();

        // Deep merge updates, or replace outright — see reset()
        this.state = replace ? { ...updates } : this.deepMerge(this.state, updates);

        // Save to history if requested
        if (saveToHistory) {
            this.saveToHistory();
        }

        // Notify observers
        this.notifyObservers(prevState, this.state);

        // Auto-save to localStorage if enabled
        if (this.state.settings.preferences.autoSave) {
            this.saveToLocalStorage();
        }

        return this.state;
    }

    /**
     * Get specific state slice
     */
    getStateSlice(path) {
        return this.getNestedValue(this.state, path);
    }

    /**
     * Update specific state slice
     */
    setStateSlice(path, value, saveToHistory = true) {
        const updates = this.createNestedObject(path, value);
        return this.setState(updates, saveToHistory);
    }

    /**
     * Subscribe to state changes
     */
    subscribe(observer) {
        if (typeof observer !== 'function') {
            throw new Error('Observer must be a function');
        }

        this.observers.push(observer);

        // Return unsubscribe function
        return () => {
            const index = this.observers.indexOf(observer);
            if (index > -1) {
                this.observers.splice(index, 1);
            }
        };
    }

    /**
     * Notify all observers of state changes
     */
    notifyObservers(prevState, newState) {
        this.observers.forEach(observer => {
            try {
                observer(newState, prevState);
            } catch (error) {
                console.error('Error in state observer:', error);
            }
        });
    }

    /**
     * Deep merge objects
     */
    deepMerge(target, source) {
        const result = { ...target };

        for (const key in source) {
            if (source.hasOwnProperty(key)) {
                // Handle Map and Set instances directly without merging
                if (source[key] instanceof Map || source[key] instanceof Set) {
                    result[key] = source[key];
                } else if (this.isObject(source[key]) && this.isObject(result[key])) {
                    result[key] = this.deepMerge(result[key], source[key]);
                } else {
                    result[key] = source[key];
                }
            }
        }

        return result;
    }

    /**
     * Check if value is an object
     */
    isObject(item) {
        return item && typeof item === 'object' && !Array.isArray(item);
    }

    /**
     * Get nested value from object using dot notation
     */
    getNestedValue(obj, path) {
        return path.split('.').reduce((current, key) => {
            return current && current[key] !== undefined ? current[key] : undefined;
        }, obj);
    }

    /**
     * Create nested object from dot notation path
     */
    createNestedObject(path, value) {
        const keys = path.split('.');
        const result = {};
        let current = result;

        for (let i = 0; i < keys.length - 1; i++) {
            current[keys[i]] = {};
            current = current[keys[i]];
        }

        current[keys[keys.length - 1]] = value;
        return result;
    }

    /**
     * Save current state to history
     */
    saveToHistory() {
        this.history.push({
            state: this.getState(),
            timestamp: Date.now()
        });

        // Limit history size
        if (this.history.length > this.maxHistorySize) {
            this.history.shift();
        }
    }

    /**
     * Undo last state change
     */
    undo() {
        if (this.history.length > 1) {
            // Remove current state
            this.history.pop();

            // Get previous state
            const prevStateEntry = this.history[this.history.length - 1];
            this.state = { ...prevStateEntry.state };

            // Notify observers without saving to history
            this.notifyObservers({}, this.state);

            return true;
        }
        return false;
    }

    /**
     * Reset state to initial values
     *
     * "Start Again" has to leave nothing of the previous article behind, and
     * above all not the conversationId — with it still in place a completely
     * different topic carries on appending to the finished article. The reset
     * state is applied through the replace path because a deep merge cannot
     * remove a key: conversationId, the server cost keys (running_total_usd,
     * actual_usd, estimated_usd), stepData entries, selections and the prompt
     * override all survive a merge that simply omits them.
     *
     * The write-through to localStorage is left to setState's auto-save, so
     * this tab keeps its saved session — pointed at a clean state, not deleted.
     */
    reset() {
        this.setState(this.createInitialState(), true, true);
        this.history = [];
        this.saveToHistory();
    }

    /**
     * This tab's persistence id.
     *
     * sessionStorage is per tab and survives a reload of that tab, which is
     * exactly the distinction the wizard needs: recovering a reloaded or
     * crashed session is a feature, inheriting another tab's conversation is
     * the bug. Where sessionStorage is unavailable (private mode, blocked
     * storage) a per-instance id is used: recovery after a reload is lost,
     * but two tabs still never share a conversation.
     *
     * @return {string} Tab id.
     */
    resolveTabId() {
        const generate = () => (
            window.crypto && typeof window.crypto.randomUUID === 'function'
                ? window.crypto.randomUUID()
                : `t${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`
        );

        try {
            let tabId = sessionStorage.getItem(AppState.TAB_ID_KEY);
            if (!tabId) {
                tabId = generate();
                sessionStorage.setItem(AppState.TAB_ID_KEY, tabId);
            }
            return tabId;
        } catch (error) {
            return generate();
        }
    }

    /**
     * Every saved session currently in localStorage.
     *
     * @return {Array<Object>} { key, savedAt, state } entries, unparseable ones included with a null state.
     */
    listStoredSessions() {
        const sessions = [];

        try {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (!key || key.indexOf(AppState.STORAGE_PREFIX) !== 0) {
                    continue;
                }

                let entry = null;
                try {
                    entry = JSON.parse(localStorage.getItem(key));
                } catch (error) {
                    entry = null;
                }

                sessions.push({
                    key,
                    savedAt: (entry && entry.savedAt) || 0,
                    state: (entry && entry.state) || null
                });
            }
        } catch (error) {
            console.error('Failed to read saved sessions:', error);
        }

        return sessions;
    }

    /**
     * Is another tab in the middle of an article?
     *
     * @return {boolean} True when a session other than this tab's holds a conversation.
     */
    hasForeignConversation() {
        return this.listStoredSessions().some(
            (session) => session.key !== this.storageKey && session.state && session.state.conversationId
        );
    }

    /**
     * Drop stale and surplus sessions so abandoned tabs do not accumulate.
     */
    pruneStoredSessions() {
        const now = Date.now();
        const survivors = [];

        this.listStoredSessions().forEach((session) => {
            if (session.key !== this.storageKey && now - session.savedAt > AppState.STORAGE_MAX_AGE_MS) {
                localStorage.removeItem(session.key);
                return;
            }
            survivors.push(session);
        });

        survivors
            .sort((a, b) => b.savedAt - a.savedAt)
            .slice(AppState.STORAGE_MAX_SESSIONS)
            .forEach((session) => {
                if (session.key !== this.storageKey) {
                    localStorage.removeItem(session.key);
                }
            });

        // The single-key format is dead once any tab owns a session.
        localStorage.removeItem(AppState.LEGACY_STORAGE_KEY);
    }

    /**
     * Save state to localStorage, under this tab's own key.
     */
    saveToLocalStorage() {
        try {
            const stateToSave = { ...this.state };

            // Handle stepData Map conversion properly
            if (this.state.stepData instanceof Map) {
                stateToSave.stepData = Array.from(this.state.stepData.entries());
            } else if (typeof this.state.stepData === 'object' && this.state.stepData !== null) {
                // If it's already an object, convert to Map entries format
                stateToSave.stepData = Object.entries(this.state.stepData);
            } else {
                stateToSave.stepData = [];
            }

            localStorage.setItem(this.storageKey, JSON.stringify({
                tabId: this.tabId,
                savedAt: Date.now(),
                state: stateToSave
            }));

            this.pruneStoredSessions();
        } catch (error) {
            console.error('Failed to save state to localStorage:', error);
        }
    }

    /**
     * Load state from localStorage.
     *
     * Only this tab's own session is ever adopted. A second tab finds nothing
     * of its own, reports 'other-tab' when a sibling has an article in
     * progress, and begins a new one.
     *
     * @return {boolean} True when a saved session was restored.
     */
    loadFromLocalStorage() {
        try {
            const parsedState = this.readOwnSession() || this.claimLegacySession();
            if (parsedState) {
                // Convert Array back to Map properly
                if (parsedState.stepData) {
                    if (Array.isArray(parsedState.stepData)) {
                        parsedState.stepData = new Map(parsedState.stepData);
                    } else if (typeof parsedState.stepData === 'object') {
                        // Handle case where stepData is a plain object
                        parsedState.stepData = new Map(Object.entries(parsedState.stepData));
                    }
                } else {
                    parsedState.stepData = new Map(parsedState.stepData);
                }

                this.setState(parsedState, false); // Don't save to history
                this.lastLoadStatus = 'restored';
                return true;
            }

            this.lastLoadStatus = this.hasForeignConversation() ? 'other-tab' : 'empty';
            return false;
        } catch (error) {
            console.error('Failed to load state from localStorage:', error);
            this.lastLoadStatus = 'error';
        }
        return false;
    }

    /**
     * Explicitly adopt a state the user chose to resume.
     *
     * Page load no longer calls loadFromLocalStorage() automatically: a saved
     * draft is offered as a choice, while the working state stays pristine.
     * This method performs the old array-to-Map repair only after Resume.
     *
     * @param {Object} savedState State returned by readOwnSession().
     * @return {boolean} Whether a valid state was restored.
     */
    restoreSavedState(savedState) {
        if (!savedState || typeof savedState !== 'object') {
            return false;
        }
        const restored = { ...savedState };
        if (Array.isArray(restored.stepData)) {
            restored.stepData = new Map(restored.stepData);
        } else if (restored.stepData && typeof restored.stepData === 'object') {
            restored.stepData = new Map(Object.entries(restored.stepData));
        } else {
            restored.stepData = new Map();
        }
        this.setState(restored, false, true);
        this.lastLoadStatus = 'restored';
        return true;
    }

    /**
     * This tab's saved session, if it has one.
     *
     * @return {Object|null} Saved state.
     */
    readOwnSession() {
        const raw = localStorage.getItem(this.storageKey);
        if (!raw) {
            return null;
        }
        const entry = JSON.parse(raw);
        return entry && entry.state ? entry.state : null;
    }

    /**
     * One-off migration from the single-key format.
     *
     * The old blob is adopted only while no per-tab session exists at all, so
     * an upgrade mid-article keeps its conversation and a tab opened after
     * that cannot inherit it. Claimed or not, the key is removed.
     *
     * @return {Object|null} Saved state.
     */
    claimLegacySession() {
        const raw = localStorage.getItem(AppState.LEGACY_STORAGE_KEY);
        if (!raw || this.listStoredSessions().length > 0) {
            return null;
        }

        localStorage.removeItem(AppState.LEGACY_STORAGE_KEY);

        const parsedState = JSON.parse(raw);
        return parsedState && typeof parsedState === 'object' ? parsedState : null;
    }

    /**
     * Clear localStorage
     */
    clearLocalStorage() {
        try {
            localStorage.removeItem(this.storageKey);
            localStorage.removeItem(AppState.LEGACY_STORAGE_KEY);
        } catch (error) {
            console.error('Failed to clear localStorage:', error);
        }
    }

    /**
     * Validate state integrity
     */
    validateState() {
        const issues = [];

        // Check current step is within bounds
        if (this.state.currentStep < 1 || this.state.currentStep > this.state.totalSteps) {
            issues.push('Current step is out of bounds');
        }

        // Check required properties exist
        const requiredPaths = [
            'settings.apiKeys',
            'settings.contentSettings',
            'ui.isLoading',
            'cost.selectedModel'
        ];

        requiredPaths.forEach(path => {
            if (this.getNestedValue(this.state, path) === undefined) {
                issues.push(`Missing required property: ${path}`);
            }
        });

        return {
            isValid: issues.length === 0,
            issues
        };
    }

    /**
     * Get state statistics
     */
    getStats() {
        return {
            currentStep: this.state.currentStep,
            totalSteps: this.state.totalSteps,
            completedSteps: Array.from(this.state.stepData.keys()).length,
            progressPercentage: Math.round((this.state.currentStep / this.state.totalSteps) * 100),
            totalCost: this.state.cost.totalCost,
            historySize: this.history.length,
            observerCount: this.observers.length
        };
    }
}
