/**
 * Central selector map for AI-Scribe v3 E2E tests.
 *
 * P3 UPDATE: values below are the REAL selectors shipped by
 * templates/create_template.php and templates/settings_template.php.
 * Every interactive element carries a stable data-testid; step panels are
 * addressed by [data-step-panel="N"]. Page slugs remain TODO(P5) until the
 * engine's admin-menu registration is final.
 */

export const wp = {
	loginUser: '#user_login',
	loginPass: '#user_pass',
	loginSubmit: '#wp-submit',
	adminBar: '#wpadminbar',
	// data-plugin (dir/file) is stable for local plugins; data-slug only
	// exists for wp.org-known plugins.
	pluginsRow: ( slug: string ) => `tr[data-plugin^="${ slug }/"]`,
	activateLink: ( slug: string ) => `tr[data-plugin^="${ slug }/"] .activate a`,
	deactivateLink: ( slug: string ) => `tr[data-plugin^="${ slug }/"] .deactivate a`,
	noticeSuccess: '.notice-success, #message.updated',
};

/** Plugin slug/dir as mapped by wp-env ("." → directory name). */
export const PLUGIN_SLUG = 'ai-scribe-v3';

export const settings = {
	// P5: confirmed — PluginInitializer::register_admin_menu, slug ai_scribe_settings.
	pageUrl: '/wp-admin/admin.php?page=ai_scribe_settings',
	root: '#ai-scribe-settings-root',
	tab: ( id: 'providers' | 'generation' | 'images' | 'prompts' ) =>
		`[data-testid="settings-tab-${ id }"]`,
	openaiKey: '[data-testid="api-key-openai"]',
	anthropicKey: '[data-testid="api-key-anthropic"]',
	geminiKey: '[data-testid="api-key-gemini"]',
	keyStatus: ( provider: string ) => `[data-testid="key-status-${ provider }"]`,
	modelSelect: '[data-testid="model-select"]',
	modelListStatus: '[data-testid="model-list-status"]',
	temperature: '[data-testid="temperature"]',
	topP: '[data-testid="top-p"]',
	imagesEnabled: '[data-testid="images-enabled"]',
	imageModelSelect: '[data-testid="image-model-select"]',
	imageSize: '[data-testid="image-size"]',
	promptField: ( key: string ) => `[data-testid="prompt-${ key }"]`,
	saveButton: '[data-testid="settings-save"]',
	status: '[data-testid="settings-status"]',
};

export const wizard = {
	// P5: confirmed — top-level menu slug ai-scribe (toplevel_page_ai-scribe).
	pageUrl: '/wp-admin/admin.php?page=ai-scribe',

	root: '[data-testid="app-root"]',
	screen: '[data-testid="wizard-screen"]',
	modeWizard: '[data-testid="mode-wizard"]',
	modeExpress: '[data-testid="mode-express"]',

	// 11-chip step tablist.
	stepChip: ( n: number ) => `[data-testid="step-chip-${ n }"]`,
	activeStep: '#step-navigation [aria-current="step"]',
	progressText: '[data-testid="progress-text"]',

	// Panel-scoped controls: combine with `panel(n)` (testids repeat per panel).
	panel: ( n: number ) => `[data-step-panel="${ n }"]`,
	ideaInput: '[data-testid="idea-input"]',
	generateButton: '[data-testid="generate"]',
	continueButton: '[data-testid="continue"]',
	backButton: '[data-testid="back"]',
	skipButton: '[data-testid="skip"]',
	regenerateButton: '[data-testid="regenerate"]',
	promptEditor: '[data-testid="prompt-editor"]',
	errorBanner: '[data-testid="step-error"]',
	errorRetry: '[data-testid="step-retry"]',
	emptyState: '[data-testid="step-empty"]',
	loadingIndicator: '[data-testid="step-loading"]',
	statusRegion: '[data-testid="step-status"]',
	stateAttr: 'data-state', // idle | loading | streaming | ready | error | empty

	// Result cards (CardRenderer output, decorated by ChoiceStepView).
	resultCard: '[data-testid="result-card"]',
	resultCardSelected: '[data-testid="result-card"].selected',
	optionsGrid: '[data-testid="options-grid"]',

	// Step-panel shorthands.
	step: {
		titles: '[data-step-panel="1"]',
		keywords: '[data-step-panel="2"]',
		outline: '[data-step-panel="3"]',
		intro: '[data-step-panel="4"]',
		tagline: '[data-step-panel="5"]',
		body: '[data-step-panel="6"]',
		conclusion: '[data-step-panel="7"]',
		qna: '[data-step-panel="8"]',
		seoMeta: '[data-step-panel="9"]',
		review: '[data-step-panel="10"]',
		evaluate: '[data-step-panel="11"]',
	},

	// Long-form streaming targets.
	streamOutput: '[data-testid="stream-output"]',
	evaluationOutput: '[data-testid="evaluation-output"]',

	// Quill hosts (steps 6 and 10) + editable surface.
	contentEditor: '[data-testid="content-editor"]',
	editor: '.ql-editor',

	// SEO meta (step 9).
	metaTitle: '[data-testid="meta-title"]',
	metaDescription: '[data-testid="meta-description"]',
	metaTitleCount: '[data-testid="meta-title-count"]',
	metaDescriptionCount: '[data-testid="meta-description-count"]',

	// Review/publish controls (step 10).
	publishDraftButton: '[data-testid="publish-draft"]',
	publishPostButton: '[data-testid="publish-post"]',
	savedPostLink: '[data-testid="saved-post-link"]',

	// Header.
	costMeter: '[data-testid="cost-meter"]',
	costTotal: '[data-testid="cost-total"]',
	costCurrent: '[data-testid="cost-current"]',
	startAgain: '[data-testid="start-again"]',
	themeToggle: '[data-testid="theme-toggle"]',
};

export const express = {
	// Express lives on the same wizard page behind the mode switcher.
	screen: '[data-testid="express-screen"]',
	topicInput: '[data-testid="express-topic"]',
	generateButton: '[data-testid="express-generate"]',
	articleOutput: '[data-testid="express-article"]',
	refineButton: '[data-testid="express-refine"]',
};
