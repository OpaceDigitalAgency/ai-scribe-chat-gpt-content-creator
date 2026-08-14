/**
 * AI-Scribe v3.0 - Main Application Entry Point
 * Premium Article Builder with OOP/MVC Architecture
 *
 * WordPress Plugin Compliant:
 * - No external CDN dependencies
 * - Uses WordPress AJAX endpoints
 * - Follows WordPress coding standards
 * - Implements proper nonce security
 *
 * @package AI_Scribe
 * @version 3.0.0
 * @author AI-Scribe Team
 * @since 1.0.0
 */

// Global application instance - WordPress plugin namespace
let aiScribeApp = null;

/**
 * Application Configuration
 * WordPress-compliant configuration with proper fallbacks
 */
const APP_CONFIG = {
    ajaxUrl: window.ai_scribe?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
    nonce: window.ai_scribe?.nonce || '',

    // Application settings
    app: {
        version: window.ai_scribe?.version || '3.0.1',
        debug: window.ai_scribe?.debugMode || false,
        autoSave: true,
        saveInterval: 30000 // Auto-save every 30 seconds
    },

    // UI settings
    ui: {
        animationDuration: 300,
        toastDuration: 5000,
        loadingMinDuration: 1000 // Minimum loading time for better UX
    }
};

/**
 * Main Application Class
 * Orchestrates the entire application using MVC pattern
 */
class AIScribeApp {
    constructor(config) {
        this.config = config;
        this.isInitialized = false;

        // Core components
        this.state = new AppState();
        this.serviceContainer = new ServiceContainer();
        this.eventManager = new EventManager();

        // MVC components
        this.models = new Map();
        this.views = new Map();
        this.controllers = new Map();

        // Services
        this.services = new Map();

        // Auto-save timer
        this.autoSaveTimer = null;
        // A saved tab is offered, never silently adopted. This in-memory copy
        // survives the clean initial render until the user chooses Resume.
        this.pendingResumeState = null;

        this.log('AI-Scribe application created');
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            this.log('Initializing AI-Scribe application...');

            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Merge template-provided i18n strings into the localized object
            this.loadTemplateI18n();

            // Theme (dark mode) before first paint of dynamic content
            this.initializeTheme();

            // Register services
            this.registerServices();

            // Initialize models
            this.initializeModels();

            // Initialize views
            this.initializeViews();

            // Initialize controllers
            this.initializeControllers();

            // Setup event handlers
            this.setupEventHandlers();

            // Check if this is a page refresh and reset if needed
            this.handlePageRefresh();

            // Start auto-save if enabled
            this.startAutoSave();

            // Initialize UI
            this.initializeUI();

            this.isInitialized = true;
            this.log('AI-Scribe application initialized successfully');

            // Expose reset function
            this.exposeResetFunction();

            // Welcome notification removed to prevent flash message

        } catch (error) {
            console.error('Failed to initialize AI-Scribe application:', error);
            this.showNotification('Failed to initialize application. Please refresh the page.', 'error');
        }
    }

    /**
     * Register application services
     */
    registerServices() {
        // v3 primary network layer — implements docs/API_CONTRACT.md.
        // ApiClient is the ONLY network path (REFACTOR.md §13.1 zombie purge).
        this.serviceContainer.register('apiClient', () => {
            return new ApiClient({
                ajaxUrl: this.config.ajaxUrl,
                nonce: this.config.nonce
            });
        });

        this.log('Services registered');
    }

    /**
     * Merge the template's JSON i18n block into window.ai_scribe.i18n
     * so views never hardcode user-facing strings.
     */
    loadTemplateI18n() {
        const block = document.getElementById('ai-scribe-i18n');
        if (!block) {
            return;
        }
        try {
            const strings = JSON.parse(block.textContent);
            window.ai_scribe = window.ai_scribe || {};
            window.ai_scribe.i18n = Object.assign({}, window.ai_scribe.i18n || {}, strings);
        } catch (error) {
            console.error('AI-Scribe: failed to parse i18n block', error);
        }
    }

    /**
     * Dark mode: honour saved preference, else prefers-color-scheme.
     * Persisted in localStorage immediately; user-meta persistence rides the
     * ai_scribe_save_ui_prefs endpoint when the engine provides it.
     */
    initializeTheme() {
        const stored = window.localStorage.getItem('ai-scribe-theme');
        const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = stored || (systemDark ? 'dark' : 'light');
        this.applyTheme(theme, false);

        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('[data-action="toggle-theme"]');
            if (toggle) {
                const current = document.documentElement.getAttribute('data-ai-scribe-theme') || 'light';
                this.applyTheme(current === 'dark' ? 'light' : 'dark', true);
            }
        });
    }

    applyTheme(theme, persist) {
        document.documentElement.setAttribute('data-ai-scribe-theme', theme);
        const toggle = document.querySelector('[data-action="toggle-theme"]');
        if (toggle) {
            toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        }
        if (persist) {
            window.localStorage.setItem('ai-scribe-theme', theme);
            try {
                this.serviceContainer.get('apiClient').saveUiPrefs({ theme }).catch(() => {
                    /* Endpoint is a contract extension — localStorage already holds it. */
                });
            } catch (e) {
                /* apiClient unavailable — localStorage fallback stands. */
            }
        }
    }

    /**
     * Initialize application models.
     *
     * The v4 model layer (WorkflowModel, CostModel, ValidationModel, …) is
     * deleted: state lives in AppState, rendering in the step views, and all
     * data comes from the contract endpoints via ApiClient.
     */
    initializeModels() {
        this.log('Models initialized (v3: AppState + step views only)');
    }

    /**
     * Initialize application views
     */
    initializeViews() {
        // v3 per-step views (canonical render path; DisplayManager pruned)
        if (typeof StepViewRegistry !== 'undefined') {
            this.stepRegistry = new StepViewRegistry(this.state);
            this.views.set('steps', this.stepRegistry);
        }

        // Settings view (active only on the settings screen)
        if (typeof SettingsView !== 'undefined') {
            this.views.set('settings', new SettingsView(document.body, this.state));
        }

        // Modal view
        if (typeof ModalView !== 'undefined') {
            this.views.set('modal', new ModalView(this.state));
        }

        this.log('Views initialized');
    }

    /**
     * Initialize application controllers
     */
    initializeControllers() {
        // v3 wizard flow controller — owns navigation, generation, streaming,
        // Express mode, cost meter and publishing via ApiClient.
        if (this.stepRegistry && typeof WizardFlowController !== 'undefined') {
            this.controllers.set('wizardFlow', new WizardFlowController(
                this.state,
                this.serviceContainer.get('apiClient'),
                this.stepRegistry
            ));
        }

        // Settings controller (settings screen only; no-ops elsewhere)
        if (typeof SettingsController !== 'undefined' && this.views.has('settings')) {
            const settingsController = new SettingsController(
                this.views.get('settings'),
                this.serviceContainer.get('apiClient'),
                this.state
            );
            this.controllers.set('settings', settingsController);
            settingsController.init();
        }

        // Modal controller
        if (typeof ModalController !== 'undefined' && this.views.has('modal')) {
            this.controllers.set('modal', new ModalController(
                this.views.get('modal'),
                this.state
            ));
        }

        this.log('Controllers initialized');
    }

    /**
     * Setup global event handlers
     */
    setupEventHandlers() {
        // Step navigation + header actions are owned by WizardFlowController
        // (event delegation on data-action) — no duplicate listeners here.

        // Modal handlers
        this.setupModalHandlers();

        // Form handlers
        this.setupFormHandlers();

        // Keyboard shortcuts
        this.setupKeyboardShortcuts();

        // Window events
        this.setupWindowEvents();

        document.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action="resume-draft"], [data-action="discard-resume"]');
            if (!action) {
                return;
            }
            if (action.getAttribute('data-action') === 'resume-draft') {
                this.resumeSavedDraft();
            } else {
                this.discardSavedDraft();
            }
        });

        this.log('Event handlers setup complete');
    }

    /**
     * Setup modal handlers
     */
    setupModalHandlers() {
        const closeModal = () => {
            const modal = this.controllers.get('modal');
            if (modal) {
                modal.closeModal();
            }
        };

        // Close modal when clicking overlay
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal();
            }
        });

        // Close modal buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        // Escape key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Setup form handlers
     */
    setupFormHandlers() {
        // Prevent form submissions
        document.addEventListener('submit', (e) => {
            e.preventDefault();
        });

        // Auto-resize textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', this.autoResizeTextarea);
        });

        this.setupEnterToSubmit();
    }

    /**
     * Enter in a single-line topic field runs that field's action button.
     *
     * The wizard suppresses native form submission (above), so pressing Enter
     * in the topic field did nothing at all — you had to reach for the mouse
     * to start a generation, which is the single most repeated action in the
     * app. Delegated so it also covers fields rendered later by step views.
     *
     * Textareas are deliberately excluded: Enter there inserts a newline,
     * which is what you want when editing a prompt.
     */
    setupEnterToSubmit() {
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) {
                return;
            }

            const field = e.target;
            if (!(field instanceof HTMLInputElement) || field.type !== 'text') {
                return;
            }

            // The button this field belongs to: an explicit pairing wins,
            // otherwise the action button sitting alongside it.
            const pairedId = field.getAttribute('data-submits');
            const group = field.closest('.input-with-button, .form-group, .input-section');
            const button = pairedId
                ? document.getElementById(pairedId)
                : group && group.querySelector('button[data-action]:not([hidden]):not(:disabled)');

            if (!button || button.disabled || button.hidden) {
                return;
            }

            e.preventDefault();
            button.click();
        });
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveCurrentState();
                this.showNotification('Progress saved!', 'success');
            }

            // Ctrl/Cmd + Z to undo
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                if (this.state.undo()) {
                    this.showNotification('Undone!', 'info');
                }
            }
        });
    }

    /**
     * Setup window event handlers
     */
    setupWindowEvents() {
        // Save state before page unload
        window.addEventListener('beforeunload', () => {
            this.saveCurrentState();
        });

        // Handle online/offline status
        window.addEventListener('online', () => {
            this.showNotification('Connection restored', 'success');
        });

        window.addEventListener('offline', () => {
            this.showNotification('Working offline', 'warning');
        });
    }

    /**
     * Load saved state from localStorage
     */
    loadSavedState() {
        try {
            if (this.config.app.autoSave) {
                const loaded = this.state.loadFromLocalStorage();
                if (loaded) {
                    this.log('Saved state loaded from localStorage');
                    // Notification removed to prevent flash message
                }
            }
        } catch (error) {
            console.error('Failed to load saved state:', error);
        }
    }

    /** Make a recoverable draft visible as a choice without mutating the new session. */
    offerSavedDraft() {
        const notice = document.querySelector('[data-testid="resume-draft-notice"]');
        if (notice) {
            notice.hidden = !this.pendingResumeState;
        }
    }

    /** User-authorised recovery: adopt the saved state, then hydrate its server conversation. */
    resumeSavedDraft() {
        if (!this.pendingResumeState || !this.state.restoreSavedState(this.pendingResumeState)) {
            return;
        }
        this.pendingResumeState = null;
        this.offerSavedDraft();
        const wizardFlow = this.controllers.get('wizardFlow');
        if (wizardFlow) {
            wizardFlow.resetWorkflowViews();
            wizardFlow.maxUnlockedStep = 1;
            wizardFlow.switchMode('wizard');
            // Return the hydration promise so browser tests and any future UI
            // caller can observe recovery completion instead of racing it.
            return wizardFlow.hydrateFromServer();
        }
        return Promise.resolve();
    }

    /** Keep the pristine session and discard this tab's recoverable draft. */
    discardSavedDraft() {
        this.pendingResumeState = null;
        this.state.clearLocalStorage();
        this.offerSavedDraft();
    }

    /**
     * Page load starts clean. If this exact tab has a recoverable server
     * conversation, offer Resume rather than silently repainting old work.
     */
    handlePageRefresh() {
        try {
            const saved = this.config.app.autoSave ? this.state.readOwnSession() : null;
            this.pendingResumeState = saved && saved.conversationId ? saved : null;
            this.state.lastLoadStatus = this.pendingResumeState ? 'resume-available' : 'empty';
            this.offerSavedDraft();
        } catch (error) {
            this.pendingResumeState = null;
            this.state.lastLoadStatus = 'error';
            console.error('Failed to inspect saved state:', error);
        }
    }

    /**
     * Save current state
     */
    saveCurrentState() {
        try {
            // Do not overwrite the recoverable draft with the pristine offer
            // screen while the user is deciding whether to resume it.
            if (this.pendingResumeState) {
                return;
            }
            if (this.config.app.autoSave) {
                this.state.saveToLocalStorage();
                this.log('State saved to localStorage');
            }
        } catch (error) {
            console.error('Failed to save state:', error);
        }
    }

    /**
     * Start auto-save timer
     */
    startAutoSave() {
        if (!this.config.app.autoSave) return;

        this.autoSaveTimer = setInterval(() => {
            this.saveCurrentState();
        }, this.config.app.saveInterval);

        this.log('Auto-save started');
    }

    /**
     * Stop auto-save timer
     */
    stopAutoSave() {
        if (this.autoSaveTimer) {
            clearInterval(this.autoSaveTimer);
            this.autoSaveTimer = null;
            this.log('Auto-save stopped');
        }
    }

    /**
     * Initialize UI components
     */
    initializeUI() {
        // Update progress display
        this.updateProgress();

        // Initialize step content
        this.initializeStepContent();

        // Setup responsive handlers
        this.setupResponsiveHandlers();

        // Initialize enhanced functionality
        this.initializeEnhancedFeatures();
    }

    /**
     * Initialize enhanced features: the header model display and the
     * no-keys-configured notice. Both read the contract endpoints /
     * server-localised flags — no legacy fallbacks (REFACTOR.md §13.1).
     */
    initializeEnhancedFeatures() {
        try {
            const wizardFlow = this.controllers.get('wizardFlow');
            if (wizardFlow && typeof wizardFlow.hydrateModelDisplay === 'function') {
                wizardFlow.hydrateModelDisplay();
            }

            this.renderNoKeysNotice();

            // Re-initialize Lucide icons after DOM updates
            if (typeof lucide !== 'undefined') {
                setTimeout(() => {
                    lucide.createIcons();
                    this.log('Icons refreshed');
                }, 100);
            }

        } catch (error) {
            console.error('Error initializing enhanced features:', error);
        }
    }

    /**
     * Visible, non-blocking notice when no provider key is configured
     * (server-side boolean flags only — keys never reach the page).
     */
    renderNoKeysNotice() {
        const data = window.ai_scribe || {};
        const root = document.getElementById('ai-scribe-root');
        if (!root || data.hasAnyApiKey !== false) {
            return;
        }
        const i18n = data.i18n || {};
        const notice = document.createElement('div');
        notice.className = 'app-notice app-notice-warning';
        notice.setAttribute('data-testid', 'no-keys-notice');
        notice.setAttribute('role', 'status');
        const text = document.createElement('span');
        text.textContent = i18n.noKeysNotice
            || 'No AI provider is configured yet. Add an API key to start generating.';
        notice.appendChild(text);
        if (data.settingsUrl) {
            const link = document.createElement('a');
            link.href = data.settingsUrl;
            link.textContent = i18n.openSettings || 'Open Settings';
            notice.appendChild(link);
        }
        root.insertBefore(notice, root.firstChild);
    }

    /**
     * Update progress indicators
     */
    updateProgress() {
        const currentStep = this.state.getStateSlice('currentStep') || 1;
        const totalSteps = this.state.getStateSlice('totalSteps') || 11;
        const percentage = Math.round((currentStep / totalSteps) * 100);

        // Update progress bar
        const progressFill = document.getElementById('progress-fill');
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }

        // Update progress text
        const progressText = document.getElementById('progress-text');
        if (progressText) {
            const stepNames = {
                1: "Title Generation",
                2: "Keyword Research",
                3: "Article Outline",
                4: "Introduction",
                5: "Tagline",
                6: "Article Body",
                7: "Conclusion",
                8: "Q&A Section",
                9: "Meta Data",
                10: "Review & Edit",
                11: "Publish Options"
            };

            progressText.textContent = `Step ${currentStep} of ${totalSteps} - ${stepNames[currentStep] || 'Unknown'}`;
        }

        // Update progress percentage
        const progressPercentage = document.getElementById('progress-percentage');
        if (progressPercentage) {
            progressPercentage.textContent = `${percentage}%`;
        }
    }

    /**
     * Initialize step content
     */
    initializeStepContent() {
        const wizardFlow = this.controllers.get('wizardFlow');
        if (wizardFlow) {
            // A page visit always presents the pristine first step. Server
            // hydration is deliberately reserved for resumeSavedDraft(),
            // after the user presses Resume; merely finding recoverable state
            // must never repaint an old step behind the notice.
            wizardFlow.maxUnlockedStep = 1;
            wizardFlow.switchMode('wizard');
            wizardFlow.navigateToStep(1);
            return;
        }

        // Fallback (no wizard on this screen)
        const currentStep = this.state.getStateSlice('currentStep') || 1;
        this.showStep(currentStep);
        this.updateStepNavigation();
    }

    /**
     * Show specific step
     */
    showStep(step) {
        if (step < 1 || step > 11) return;

        // v3: WizardFlowController owns navigation
        const wizardFlow = this.controllers.get('wizardFlow');
        if (wizardFlow) {
            return wizardFlow.navigateToStep(step);
        }

        // Fallback to basic navigation if controller not available
        this.log(`Showing step ${step}`);

        // Hide all step content
        document.querySelectorAll('.step-content').forEach(content => {
            content.classList.remove('active');
        });

        // Show target step
        const targetStep = document.querySelector(`.step-content[data-step="${step}"]`);
        if (targetStep && targetStep.classList.contains('step-content')) {
            targetStep.classList.add('active');
            this.log(`Step ${step} content shown`);
        } else {
            this.log(`Step ${step} content not found`);
        }

        // Update state
        this.state.setStateSlice('currentStep', step);

        // Update UI
        this.updateProgress();
        this.updateStepNavigation();
    }

    /**
     * Update step navigation visual state
     */
    updateStepNavigation() {
        const currentStep = this.state.getStateSlice('currentStep') || 1;
        const completedSteps = Array.from(this.state.getStateSlice('stepData')?.keys() || []);

        document.querySelectorAll('.step').forEach(stepEl => {
            const stepNum = parseInt(stepEl.dataset.step);

            // Remove all state classes
            stepEl.classList.remove('active', 'completed', 'disabled');

            if (stepNum === currentStep) {
                stepEl.classList.add('active');
            } else if (completedSteps.includes(stepNum)) {
                stepEl.classList.add('completed');
            } else if (stepNum > currentStep) {
                stepEl.classList.add('disabled');
            }
        });

        // The 11-step journey is a local horizontal scroller whenever the
        // wp-admin canvas cannot fit every readable label. Keep the current
        // step visible without moving the page itself; scrollIntoView can
        // unexpectedly shift the document vertically as well as the rail.
        const rail = document.querySelector('#step-navigation .steps-container');
        const activeStep = rail && rail.querySelector('.step.active');
        if (rail && activeStep) {
            window.requestAnimationFrame(() => {
                const railBox = rail.getBoundingClientRect();
                const stepBox = activeStep.getBoundingClientRect();
                if (stepBox.left < railBox.left + 1 || stepBox.right > railBox.right - 1) {
                    const target = activeStep.offsetLeft - ((rail.clientWidth - activeStep.offsetWidth) / 2);
                    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    rail.scrollTo({
                        left: Math.max(0, target),
                        behavior: reduceMotion ? 'instant' : 'smooth'
                    });
                }
            });
        }
    }

    /**
     * Setup responsive handlers
     */
    setupResponsiveHandlers() {
        // Handle mobile menu toggle
        const handleResize = () => {
            const isMobile = window.innerWidth < 768;
            document.body.classList.toggle('mobile', isMobile);
        };

        window.addEventListener('resize', handleResize);
        handleResize(); // Initial call

        this.setupStepRailAffordance();
    }

    /**
     * Below 768 the step rail becomes a horizontal scroller holding 11 steps,
     * only 3 of which fit at 360px. These classes drive the edge fades in
     * main.css so there is always a visible cue that the rail continues, and
     * the cue disappears once you reach that end.
     */
    setupStepRailAffordance() {
        const nav = document.querySelector('.step-navigation');
        const rail = nav && nav.querySelector('.steps-container');
        if (!nav || !rail) {
            return;
        }

        const update = () => {
            // 1px of slack absorbs sub-pixel rounding at fractional zooms.
            const maxScroll = rail.scrollWidth - rail.clientWidth;
            nav.classList.toggle('is-scrolled-start', rail.scrollLeft > 1);
            nav.classList.toggle('is-scrolled-end', rail.scrollLeft >= maxScroll - 1);
        };

        rail.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(update).observe(rail);
        }

        update();
    }

    /**
     * Auto-resize textarea
     */
    autoResizeTextarea(e) {
        const textarea = e.target;
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info', duration = null) {
        if (window.aiScribeNotifications) {
            window.aiScribeNotifications.show(message, type, duration);
        }
        this.log(`Notification: ${type} - ${message}`);
    }

    /**
     * Logging utility
     */
    log(message, ...args) {
        if (this.config.app.debug) {
            console.log(`[AI-Scribe] ${message}`, ...args);
        }
    }

    /**
     * Destroy application instance
     */
    destroy() {
        // Stop auto-save
        this.stopAutoSave();

        // Clear event listeners
        // (In a real app, we'd keep track of listeners to remove them)

        // Clear state
        this.state.reset();

        this.isInitialized = false;
        this.log('AI-Scribe application destroyed');
    }

    /**
     * Reset application to initial state
     */
    resetApplication() {
        // Reset state
        this.state.reset();

        // Reset UI
        this.showStep(1);
        this.updateProgress();
        this.updateStepNavigation();

        // Clear input
        const topicInput = document.getElementById('topic-input');
        if (topicInput) {
            topicInput.value = '';
        }

        // Hide all results
        document.querySelectorAll('.results-section').forEach(section => {
            section.classList.add('hidden');
        });

        // Clear all option containers
        document.querySelectorAll('[id$="-options"]').forEach(container => {
            container.innerHTML = '';
        });

        this.log('Application reset');
    }

    /**
     * Make reset function globally available
     */
    exposeResetFunction() {
        window.aiScribeApp.resetApplication = this.resetApplication.bind(this);
    }
}

/**
 * Service Container Implementation
 */
class ServiceContainer {
    constructor() {
        this.services = new Map();
        this.instances = new Map();
    }

    register(name, factory) {
        this.services.set(name, factory);
    }

    get(name) {
        if (this.instances.has(name)) {
            return this.instances.get(name);
        }

        const factory = this.services.get(name);
        if (!factory) {
            throw new Error(`Service ${name} not found`);
        }

        const instance = factory();
        this.instances.set(name, instance);
        return instance;
    }

    has(name) {
        return this.services.has(name);
    }
}

/**
 * Event Manager Implementation
 */
class EventManager {
    constructor() {
        this.listeners = new Map();
    }

    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);

        // Return unsubscribe function
        return () => this.off(event, callback);
    }

    emit(event, data) {
        const callbacks = this.listeners.get(event) || [];
        callbacks.forEach(callback => {
            try {
                callback(data);
            } catch (error) {
                console.error(`Error in event listener for ${event}:`, error);
            }
        });
    }

    off(event, callback) {
        const callbacks = this.listeners.get(event) || [];
        const index = callbacks.indexOf(callback);
        if (index > -1) {
            callbacks.splice(index, 1);
        }
    }
}

/**
 * Application Bootstrap
 * Initialize the application when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Create and initialize the application
        aiScribeApp = new AIScribeApp(APP_CONFIG);

        // Make app globally available before initialization
        window.aiScribeApp = aiScribeApp;

        aiScribeApp.init();

    } catch (error) {
        console.error('Failed to bootstrap AI-Scribe application:', error);

        // Show fallback error message
        const errorDiv = document.createElement('div');
        errorDiv.innerHTML = `
            <div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 8px; color: #c33;">
                <h3>Application Error</h3>
                <p>Failed to load AI-Scribe application. Please refresh the page and try again.</p>
                <details>
                    <summary>Error Details</summary>
                    <pre>${error.message}</pre>
                </details>
            </div>
        `;

        const root = document.getElementById('ai-scribe-root');
        if (root) {
            root.innerHTML = '';
            root.appendChild(errorDiv);
        }
    }
});

/**
 * Handle page visibility changes
 */
document.addEventListener('visibilitychange', function() {
    if (aiScribeApp && aiScribeApp.isInitialized) {
        if (document.hidden) {
            // Page is hidden, save state
            aiScribeApp.saveCurrentState();
        } else {
            // Page is visible, could reload latest state
            // (useful for multi-tab scenarios)
        }
    }
});

/**
 * Export for potential external access
 */
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AIScribeApp, APP_CONFIG };
}
