<?php
/**
 * AI-Scribe v3 — settings screen.
 *
 * Rendered by the admin service (includes/) from its settings page callback.
 * Tabs: Providers & Model · Generation · Images · Prompt Library.
 *
 * - Provider status for the supported providers (openai, anthropic, gemini),
 *   read from Opace AI Hub — the hub is a hard dependency and owns the keys.
 * - Model picker fed by the live model-list AJAX (never hardcoded model ids).
 * - Honest temperature/top_p bounds — values pass through unmodified.
 * - Prompt library editor preserving the exact `ab_prompts_content` keys
 *   (including the legacy capital-K `Keywords_prompts`).
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ai_scribe_engine  = get_option( 'ab_gpt_ai_engine_settings', array() );
$ai_scribe_content = get_option( 'ab_gpt_content_settings', array() );
$ai_scribe_images  = get_option( 'ab_gpt_image_settings', array() );
$ai_scribe_prompts = get_option( 'ab_prompts_content', array() );

// §13.12 (AI-Imagen pattern): Opace AI Hub is a hard dependency — the bootstrap
// refuses to load AI-Scribe without it — and the hub OWNS provider
// configuration. This screen therefore shows no key fields at all, only a
// "Managed by Opace AI Hub" panel with per-provider status chips and a link to the
// hub settings. There is one place a key can be entered, and it is not here.
$ai_scribe_hub_settings = get_option( 'ai_core_settings', array() );
$ai_scribe_hub_settings = is_array( $ai_scribe_hub_settings ) ? $ai_scribe_hub_settings : array();

$ai_scribe_providers = array(
	'openai'    => array(
		! empty( $ai_scribe_hub_settings['openai_api_key'] ),
		__( 'OpenAI', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	),
	'anthropic' => array(
		! empty( $ai_scribe_hub_settings['anthropic_api_key'] ),
		__( 'Anthropic', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	),
	'gemini'    => array(
		! empty( $ai_scribe_hub_settings['gemini_api_key'] ),
		__( 'Google Gemini', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	),
);

// Whether this site can generate images at all, and why not when it cannot.
// Anthropic publishes no image generation API, so an Anthropic-only site is
// told plainly on this screen rather than at the moment it clicks Add image.
$ai_scribe_images_available = class_exists( 'AI_Scribe_Image_Service' )
	? AI_Scribe_Image_Service::images_available()
	: ( ! empty( $ai_scribe_hub_settings['openai_api_key'] ) || ! empty( $ai_scribe_hub_settings['gemini_api_key'] ) );

$ai_scribe_prompt_fields = array(
	'title_prompts'        => __( 'Step 1 — Titles', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'Keywords_prompts'     => __( 'Step 2 — Keywords', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'outline_prompts'      => __( 'Step 3 — Outline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'intro_prompts'        => __( 'Step 4 — Introduction', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'tagline_prompts'      => __( 'Step 5 — Tagline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'article_prompts'      => __( 'Step 6 — Article Body', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'conclusion_prompts'   => __( 'Step 7 — Conclusion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'qa_prompts'           => __( 'Step 8 — Q&A', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'meta_prompts'         => __( 'Step 9 — SEO Meta', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'review_prompts'       => __( 'Step 10 — Review', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'evaluate_prompts'     => __( 'Step 11 — Evaluate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'instructions_prompts' => __( 'Global instructions', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'user_instructions'    => __( 'Custom instructions', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
);

// Help text for the prompt fields that need it. 2.6.2 wrote the Custom
// Instructions box to `user_instructions` but never read the key back, so
// upgraded sites arrive with the text present and unused — v3 honours it,
// and this control is where it is edited.
$ai_scribe_prompt_help = array(
	'user_instructions' => __( 'Your own brand voice, spelling rules and "never say this" list. Appended to the end of the instructions sent with every step, so it has the final word.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
);

// The wizard step each prompt key drives, mirroring
// AI_Scribe_Prompt_Manager::$step_option_keys. Only these keys can have an
// Opace AI Hub prompt applied — the global instructions are AI-Scribe's own.
$ai_scribe_prompt_steps = array(
	'title_prompts'      => 1,
	'Keywords_prompts'   => 2,
	'outline_prompts'    => 3,
	'intro_prompts'      => 4,
	'tagline_prompts'    => 5,
	'article_prompts'    => 6,
	'conclusion_prompts' => 7,
	'qa_prompts'         => 8,
	'meta_prompts'       => 9,
	'review_prompts'     => 10,
	'evaluate_prompts'   => 11,
);

// The hub's prompt library, read through its own public accessor. All three
// reads degrade to empty arrays when Opace AI Hub is inactive — the tab then
// renders AI-Scribe's own prompts with an explanatory empty state.
$ai_scribe_hub_library    = class_exists( 'AI_Scribe_Hub_Prompt_Reader' ) && AI_Scribe_Hub_Prompt_Reader::library_available();
$ai_scribe_hub_prompts    = $ai_scribe_hub_library ? AI_Scribe_Hub_Prompt_Reader::get_prompts_by_group() : array();
$ai_scribe_hub_applied    = $ai_scribe_hub_library ? AI_Scribe_Hub_Prompt_Reader::get_applied_map() : array();
$ai_scribe_hub_prompt_url = admin_url( 'admin.php?page=ai-core-prompt-library' );

$ai_scribe_temperature = isset( $ai_scribe_engine['temperature'] )
	? (float) $ai_scribe_engine['temperature']
	: ( isset( $ai_scribe_engine['temp'] ) ? (float) $ai_scribe_engine['temp'] : 0.5 );
$ai_scribe_top_p       = isset( $ai_scribe_engine['top_p'] ) ? (float) $ai_scribe_engine['top_p'] : 1.0;
$ai_scribe_saved_model = isset( $ai_scribe_engine['model'] ) ? (string) $ai_scribe_engine['model'] : '';
$ai_scribe_model       = class_exists( 'AI_Scribe_Model_Resolver' )
	? AI_Scribe_Model_Resolver::resolve( $ai_scribe_saved_model )
	: $ai_scribe_saved_model;

// Languages list — seeded exactly as 2.6.2 did (common/settings.php:200-240),
// user-extendable via "Add New Language" (ai_scribe_languages, changelog 1.2.5).
$ai_scribe_languages = get_option( 'ai_scribe_languages' );
if ( ! is_array( $ai_scribe_languages ) || empty( $ai_scribe_languages ) ) {
	$ai_scribe_languages = array(
		'English',
		'Bulgarian',
		'Czech',
		'Danish',
		'German',
		'Greek',
		'British',
		'Spanish',
		'Estonian',
		'Finnish',
		'French',
		'Hungarian',
		'Indonesian',
		'Italian',
		'Japanese',
		'Korean',
		'Lithuanian',
		'Latvian',
		'Norwegian (Bokmål)',
		'Dutch',
		'Polish',
		'Portuguese',
		'Portuguese (Brazilian)',
		'Romanian',
		'Russian',
		'Slovak',
		'Slovenian',
		'Swedish',
		'Turkish',
		'Ukrainian',
		'Chinese',
		'Vietnamese',
		'Arabic',
	);
	update_option( 'ai_scribe_languages', $ai_scribe_languages );
}
$ai_scribe_language_saved = isset( $ai_scribe_content['language'] ) ? (string) $ai_scribe_content['language'] : 'English';
if ( '' !== $ai_scribe_language_saved && ! in_array( $ai_scribe_language_saved, $ai_scribe_languages, true ) ) {
	$ai_scribe_languages[] = $ai_scribe_language_saved;
}

// 2.6.2 writing style / tone value lists (common/settings.php:240-263).
$ai_scribe_styles      = array( 'Business', 'Academic', 'Casual', 'Creative', 'Formal', 'Journalistic', 'Technical' );
$ai_scribe_tones       = array( 'Cheerful', 'Convincing', 'Excited', 'Professional', 'Witty', 'Sarcastic', 'Feminine', 'Masculine', 'Bold', 'Dramatic', 'Grumpy', 'Secretive' );
$ai_scribe_style_saved = isset( $ai_scribe_content['writing_style'] ) ? (string) $ai_scribe_content['writing_style'] : 'Business';
if ( '' !== $ai_scribe_style_saved && ! in_array( $ai_scribe_style_saved, $ai_scribe_styles, true ) ) {
	$ai_scribe_styles[] = $ai_scribe_style_saved;
}
$ai_scribe_tone_saved = isset( $ai_scribe_content['writing_tone'] ) ? (string) $ai_scribe_content['writing_tone'] : 'Professional';
if ( '' !== $ai_scribe_tone_saved && ! in_array( $ai_scribe_tone_saved, $ai_scribe_tones, true ) ) {
	$ai_scribe_tones[] = $ai_scribe_tone_saved;
}

// Humanizer writing mode (2.6.2 ab_gpt_content_settings['mode']).
$ai_scribe_mode = isset( $ai_scribe_content['mode'] ) && in_array( $ai_scribe_content['mode'], array( 'humanize', 'personality' ), true )
	? $ai_scribe_content['mode'] : 'standard';

// Spelling variant. British is the default whenever the key is absent.
$ai_scribe_spelling = ( isset( $ai_scribe_content['spelling'] ) && 'american' === strtolower( (string) $ai_scribe_content['spelling'] ) )
	? 'american' : 'british';

// check_Arr enhancement toggles (legacy format: key => key when enabled).
$ai_scribe_check_arr_options = array(
	'addQNA'           => __( 'Add Q&As', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'addkeywordBold'   => __( 'Suggest keywords to make bold', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'addinsertHyper'   => __( 'Suggest keywords to add hyperlinks', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'addinsertToc'     => __( 'Insert TOC', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'addfurtheReading' => __( 'Suggest related topics of interest', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'addsubMatter'     => __( 'Suggest authorities on the subject matter for further reading', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'addimgCont'       => __( 'Suggest ideas for suitable images and video content', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
);
$ai_scribe_check_arr_saved   = isset( $ai_scribe_content['check_Arr'] ) && is_array( $ai_scribe_content['check_Arr'] )
	? $ai_scribe_content['check_Arr'] : array( 'addQNA' => 'addQNA' );

$ai_scribe_heading_tag_saved = isset( $ai_scribe_content['heading_tag'] ) ? (string) $ai_scribe_content['heading_tag']
	: ( isset( $ai_scribe_content['Heading_tag'] ) ? (string) $ai_scribe_content['Heading_tag'] : 'H2' );
$ai_scribe_no_headings       = isset( $ai_scribe_content['number_of_headings'] ) ? (int) $ai_scribe_content['number_of_headings']
	: ( isset( $ai_scribe_content['number_of_heading'] ) ? (int) $ai_scribe_content['number_of_heading'] : 5 );
$ai_scribe_length_mode       = isset( $ai_scribe_content['article_length_mode'] ) && in_array( $ai_scribe_content['article_length_mode'], array( 'auto', 'concise', 'standard', 'in_depth', 'custom' ), true )
	? $ai_scribe_content['article_length_mode'] : 'auto';
$ai_scribe_word_count        = isset( $ai_scribe_content['article_word_count'] ) ? max( 400, min( 8000, (int) $ai_scribe_content['article_word_count'] ) ) : 1800;
$ai_scribe_avoid_saved       = isset( $ai_scribe_content['avoid_keywords'] ) ? (string) $ai_scribe_content['avoid_keywords']
	: ( isset( $ai_scribe_content['cs_list'] ) ? (string) $ai_scribe_content['cs_list'] : '' );
// U-01: 2.6.2 saved `cs_list` from the magic-slashed $_POST exactly as it did
// the prompt library, so an upgraded value arrives carrying backslashes. Same
// peeling, same single implementation — the stored option is left untouched.
if ( class_exists( 'AI_Scribe_Prompt_Manager' ) ) {
	$ai_scribe_avoid_saved = AI_Scribe_Prompt_Manager::normalise_stored_text( $ai_scribe_avoid_saved );
}

// 2.6.2 image style presets (common/settings.php:670-689).
$ai_scribe_image_styles           = array(
	'Photorealistic',
	'Cinematic lighting',
	'Watercolour painting',
	'Oil painting',
	'Pencil sketch',
	'Charcoal drawing',
	'Line art',
	'Vector illustration',
	'Cartoon Illustration',
	'Handdrawn Sketch',
	'Pop art',
	'Retro 80s',
	'Cyberpunk',
	'Fantasy art',
	'Surrealist',
	'Minimalist',
	'3D render',
	'Monochrome',
	'Impressionist',
	'Low-poly',
);
$ai_scribe_image_style_saved      = isset( $ai_scribe_images['style'] ) ? (string) $ai_scribe_images['style']
	: (string) get_option( 'ab_image_style', 'Photorealistic' );
$ai_scribe_image_quality_saved    = isset( $ai_scribe_images['quality'] ) ? (string) $ai_scribe_images['quality'] : 'medium';
$ai_scribe_image_format_saved     = isset( $ai_scribe_images['format'] ) ? (string) $ai_scribe_images['format']
	: (string) get_option( 'ab_image_format', 'png' );
$ai_scribe_image_background_saved = isset( $ai_scribe_images['background'] ) ? (string) $ai_scribe_images['background']
	: (string) get_option( 'ab_image_background', 'auto' );

$ai_scribe_tabs = array(
	'providers'  => __( 'Providers & Model', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'generation' => __( 'Generation', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'images'     => __( 'Images', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
	'prompts'    => __( 'Prompt Library', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
);

// The open tab is addressable, so it survives a reload and can be linked to
// (?tab=generation). The JS mirrors any tab change back into the URL — see
// SettingsView.switchTab(). Read-only navigation state: no nonce applies.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$ai_scribe_requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
$ai_scribe_active_tab    = isset( $ai_scribe_tabs[ $ai_scribe_requested_tab ] ) ? $ai_scribe_requested_tab : 'providers';
?>

<?php $ai_scribe_model_params = isset( $ai_scribe_engine['model_params'] ) && is_array( $ai_scribe_engine['model_params'] ) ? $ai_scribe_engine['model_params'] : array(); ?>
<div class="wrap ai-scribe-app" id="ai-scribe-settings-root" data-testid="settings-root"
	data-current-model="<?php echo esc_attr( $ai_scribe_model ); ?>"
	data-model-params="<?php echo esc_attr( wp_json_encode( $ai_scribe_model_params ) ); ?>">
	<div class="page-brand">
		<img class="logo-image logo-image-large" src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/images/ai-scribe-logo.png' ); ?>"
			alt="" aria-hidden="true" width="72" height="72" data-testid="brand-logo">
		<h1><?php esc_html_e( 'AI-Scribe Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h1>
	</div>

	<nav class="settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
		<?php
		foreach ( $ai_scribe_tabs as $ai_scribe_tab_id => $ai_scribe_tab_label ) :
			$ai_scribe_tab_on = ( $ai_scribe_tab_id === $ai_scribe_active_tab );
			?>
			<button type="button" class="tab-btn<?php echo $ai_scribe_tab_on ? ' active' : ''; ?>"
				role="tab" id="settings-tab-<?php echo esc_attr( $ai_scribe_tab_id ); ?>"
				aria-controls="settings-panel-<?php echo esc_attr( $ai_scribe_tab_id ); ?>"
				aria-selected="<?php echo $ai_scribe_tab_on ? 'true' : 'false'; ?>"
				data-tab="<?php echo esc_attr( $ai_scribe_tab_id ); ?>"
				data-testid="settings-tab-<?php echo esc_attr( $ai_scribe_tab_id ); ?>">
				<?php echo esc_html( $ai_scribe_tab_label ); ?>
			</button>
			<?php
		endforeach;
		?>
	</nav>

	<p class="visually-hidden" role="status" aria-live="polite" data-testid="settings-status"></p>

	<?php /* ---------------- Providers & Model ---------------- */ ?>
	<section class="tab-content<?php echo 'providers' === $ai_scribe_active_tab ? ' active' : ''; ?>" role="tabpanel" id="settings-panel-providers" aria-labelledby="settings-tab-providers" data-tab="providers" <?php echo 'providers' === $ai_scribe_active_tab ? '' : 'hidden'; ?>>
		<?php /* §13.12: hub owns provider config — no key fields here. */ ?>
		<div class="managed-by-hub" data-testid="managed-by-hub">
			<div class="managed-by-hub-header">
				<h2><?php esc_html_e( 'Providers managed by Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
				<p class="form-hint"><?php esc_html_e( 'AI-Scribe requires the Opace AI Hub plugin, so API keys, provider selection and usage statistics are configured centrally and shared by every Opace AI Hub add-on.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
			</div>
			<div class="provider-chip-row" data-testid="provider-chips">
				<?php foreach ( $ai_scribe_providers as $ai_scribe_provider => $ai_scribe_meta ) : ?>
					<span class="provider-chip <?php echo $ai_scribe_meta[0] ? 'provider-chip-configured' : 'provider-chip-missing'; ?>"
						data-provider="<?php echo esc_attr( $ai_scribe_provider ); ?>"
						data-testid="provider-chip-<?php echo esc_attr( $ai_scribe_provider ); ?>">
						<span class="provider-chip-dot" aria-hidden="true"></span>
						<span class="provider-chip-name"><?php echo esc_html( $ai_scribe_meta[1] ); ?></span>
						<span class="provider-chip-state" data-chip-state><?php echo $ai_scribe_meta[0] ? esc_html__( 'Configured', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) : esc_html__( 'Not configured', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
					</span>
				<?php endforeach; ?>
			</div>
			<p>
				<a class="btn btn-primary" data-testid="open-ai-core-settings"
					href="<?php echo esc_url( admin_url( 'admin.php?page=ai-core-settings' ) ); ?>">
					<?php esc_html_e( 'Open Opace AI Hub Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</a>
			</p>
		</div>

		<h2><?php esc_html_e( 'Text model', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<div class="form-group">
			<label for="ai-scribe-model"><?php esc_html_e( 'Model', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
			<div class="input-with-button">
				<select id="ai-scribe-model" class="form-control" data-testid="model-select">
					<?php if ( $ai_scribe_model ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_model ); ?>" selected><?php echo esc_html( $ai_scribe_model ); ?></option>
					<?php endif; ?>
				</select>
				<button type="button" class="btn btn-outline" data-action="refresh-models" data-testid="models-refresh">
					<?php esc_html_e( 'Refresh models', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</button>
			</div>
			<p class="form-hint" data-testid="model-list-status"><?php esc_html_e( 'Model list loads live from your configured providers — nothing is hardcoded.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		</div>

		<div class="form-group model-params" id="ai-scribe-model-params" data-testid="model-params">
			<?php /* Per-model parameter panel rendered from the ModelRegistry parameter schema. */ ?>
		</div>
	</section>

	<?php /* ---------------- Generation ---------------- */ ?>
	<section class="tab-content<?php echo 'generation' === $ai_scribe_active_tab ? ' active' : ''; ?>" role="tabpanel" id="settings-panel-generation" aria-labelledby="settings-tab-generation" data-tab="generation" <?php echo 'generation' === $ai_scribe_active_tab ? '' : 'hidden'; ?>>
		<h2><?php esc_html_e( 'Generation parameters', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p class="form-hint"><?php esc_html_e( 'Values are sent to the provider exactly as entered — no hidden multipliers.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>

		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-temperature"><?php esc_html_e( 'Temperature (0–2)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<input type="number" id="ai-scribe-temperature" class="form-control" data-testid="temperature"
					min="0" max="2" step="0.1" value="<?php echo esc_attr( (string) $ai_scribe_temperature ); ?>">
			</div>
			<div class="form-group">
				<label for="ai-scribe-top-p"><?php esc_html_e( 'Top P (0–1)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<input type="number" id="ai-scribe-top-p" class="form-control" data-testid="top-p"
					min="0" max="1" step="0.05" value="<?php echo esc_attr( (string) $ai_scribe_top_p ); ?>">
			</div>
		</div>

		<h2><?php esc_html_e( 'Preferred article length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p class="form-hint"><?php esc_html_e( 'Auto plans a useful length from the topic, headings, keywords and Q&A. Fixed choices are targets with natural tolerance, never padding quotas; each article can override this default.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		<div class="form-row article-length-settings">
			<div class="form-group">
				<label for="ai-scribe-length-mode"><?php esc_html_e( 'Default length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-length-mode" class="form-control" data-testid="article-length-mode">
					<option value="auto" <?php selected( $ai_scribe_length_mode, 'auto' ); ?>><?php esc_html_e( 'Auto — planned from article scope', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
					<option value="concise" <?php selected( $ai_scribe_length_mode, 'concise' ); ?>><?php esc_html_e( 'Concise — 800–1,100 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
					<option value="standard" <?php selected( $ai_scribe_length_mode, 'standard' ); ?>><?php esc_html_e( 'Standard — 1,500–2,100 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
					<option value="in_depth" <?php selected( $ai_scribe_length_mode, 'in_depth' ); ?>><?php esc_html_e( 'In-depth — 2,400–3,200 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
					<option value="custom" <?php selected( $ai_scribe_length_mode, 'custom' ); ?>><?php esc_html_e( 'Custom — choose a target', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
				</select>
			</div>
			<div class="form-group" data-settings-custom-word-count <?php echo 'custom' === $ai_scribe_length_mode ? '' : 'hidden'; ?>>
				<label for="ai-scribe-word-count"><?php esc_html_e( 'Custom target words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<input type="number" id="ai-scribe-word-count" class="form-control" data-testid="article-word-count" min="400" max="8000" step="50" value="<?php echo esc_attr( (string) $ai_scribe_word_count ); ?>">
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-language"><?php esc_html_e( 'Language', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-language" class="form-control" data-testid="language">
					<?php foreach ( $ai_scribe_languages as $ai_scribe_lang ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_lang ); ?>" <?php selected( $ai_scribe_language_saved, $ai_scribe_lang ); ?>><?php echo esc_html( $ai_scribe_lang ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group">
				<label for="ai-scribe-custom-language"><?php esc_html_e( 'Add new language', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<input type="text" id="ai-scribe-custom-language" class="form-control" data-testid="custom-language"
					placeholder="<?php esc_attr_e( 'Enter a new language and save', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-spelling"><?php esc_html_e( 'Spelling', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-spelling" class="form-control" data-testid="spelling">
					<option value="british" <?php selected( $ai_scribe_spelling, 'british' ); ?>><?php esc_html_e( 'British English', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
					<option value="american" <?php selected( $ai_scribe_spelling, 'american' ); ?>><?php esc_html_e( 'American English', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
				</select>
				<p class="form-hint"><?php esc_html_e( 'Applies to every writing mode. The instruction is sent with each generation, so organise stays organise.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-style"><?php esc_html_e( 'Writing style', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-style" class="form-control" data-testid="writing-style">
					<?php foreach ( $ai_scribe_styles as $ai_scribe_style_option ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_style_option ); ?>" <?php selected( $ai_scribe_style_saved, $ai_scribe_style_option ); ?>><?php echo esc_html( $ai_scribe_style_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group">
				<label for="ai-scribe-tone"><?php esc_html_e( 'Writing tone', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-tone" class="form-control" data-testid="writing-tone">
					<?php foreach ( $ai_scribe_tones as $ai_scribe_tone_option ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_tone_option ); ?>" <?php selected( $ai_scribe_tone_saved, $ai_scribe_tone_option ); ?>><?php echo esc_html( $ai_scribe_tone_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-number-of-headings"><?php esc_html_e( 'Number of headings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<input type="number" id="ai-scribe-number-of-headings" class="form-control" data-testid="number-of-headings"
					min="1" max="20" step="1" value="<?php echo esc_attr( (string) $ai_scribe_no_headings ); ?>">
			</div>
			<div class="form-group">
				<label for="ai-scribe-heading-tag"><?php esc_html_e( 'Heading tag', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-heading-tag" class="form-control" data-testid="heading-tag">
					<?php foreach ( array( 'H2', 'H3', 'H4', 'H5' ) as $ai_scribe_htag ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_htag ); ?>" <?php selected( strtoupper( $ai_scribe_heading_tag_saved ), $ai_scribe_htag ); ?>><?php echo esc_html( $ai_scribe_htag ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label for="ai-scribe-avoid-keywords"><?php esc_html_e( 'Keywords to avoid (comma separated)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
			<input type="text" id="ai-scribe-avoid-keywords" class="form-control" data-testid="avoid-keywords"
				value="<?php echo esc_attr( $ai_scribe_avoid_saved ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. cheap, budget', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
		</div>

		<h2><?php esc_html_e( 'Writing mode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p class="form-hint"><?php esc_html_e( 'Humanizer modes rewrite the system instructions so the output reads like natural human writing; "with Personality" adds a witty, opinionated edge.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		<fieldset class="form-group" data-testid="writing-mode">
			<legend class="visually-hidden"><?php esc_html_e( 'Writing mode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></legend>
			<label class="radio-label">
				<input type="radio" name="ai_scribe_mode" value="standard" data-testid="mode-standard" <?php checked( $ai_scribe_mode, 'standard' ); ?>>
				<?php esc_html_e( 'Standard', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</label>
			<label class="radio-label">
				<input type="radio" name="ai_scribe_mode" value="humanize" data-testid="mode-humanize" <?php checked( $ai_scribe_mode, 'humanize' ); ?>>
				<?php esc_html_e( 'Humanizer', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</label>
			<label class="radio-label">
				<input type="radio" name="ai_scribe_mode" value="personality" data-testid="mode-personality" <?php checked( $ai_scribe_mode, 'personality' ); ?>>
				<?php esc_html_e( 'Humanizer with Personality', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</label>
		</fieldset>

		<h2><?php esc_html_e( 'Content enhancements', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p class="form-hint"><?php esc_html_e( 'Add Q&As controls the wizard\'s Q&A step; Insert TOC adds an anchored Table of Contents at review; the remaining options add checks to the Evaluate step.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		<div class="form-group check-arr-options" data-testid="check-arr-options">
			<?php foreach ( $ai_scribe_check_arr_options as $ai_scribe_check_key => $ai_scribe_check_label ) : ?>
				<label class="checkbox-label">
					<input type="checkbox" class="check-arr-field" value="<?php echo esc_attr( $ai_scribe_check_key ); ?>"
						data-check-key="<?php echo esc_attr( $ai_scribe_check_key ); ?>"
						data-testid="check-<?php echo esc_attr( $ai_scribe_check_key ); ?>"
						<?php checked( isset( $ai_scribe_check_arr_saved[ $ai_scribe_check_key ] ) && $ai_scribe_check_arr_saved[ $ai_scribe_check_key ] === $ai_scribe_check_key ); ?>>
					<?php echo esc_html( $ai_scribe_check_label ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e( 'Data retention', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<div class="form-group">
			<p class="form-hint" data-testid="retention-summary">
				<?php esc_html_e( 'Your AI-Scribe settings, prompts, saved shortcodes and conversations are kept when the plugin is updated, reinstalled or deleted. This is the recommended default.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</p>
			<label class="checkbox-label">
				<input type="checkbox" id="ai-scribe-delete-data" data-testid="delete-data-on-uninstall"
					<?php checked( 'yes' === get_option( 'ai_scribe_delete_data_on_uninstall' ) ); ?>>
				<?php esc_html_e( 'Permanently delete all AI-Scribe data when the plugin is deleted', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</label>
			<p class="form-hint form-error"><?php esc_html_e( 'Only enable this if you deliberately want deletion to remove settings, prompts, saved shortcodes and conversation history. Opace AI Hub API keys are managed separately.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		</div>
	</section>

	<?php /* ---------------- Images ---------------- */ ?>
	<section class="tab-content<?php echo 'images' === $ai_scribe_active_tab ? ' active' : ''; ?>" role="tabpanel" id="settings-panel-images" aria-labelledby="settings-tab-images" data-tab="images" <?php echo 'images' === $ai_scribe_active_tab ? '' : 'hidden'; ?>>
		<h2><?php esc_html_e( 'Image generation', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<?php if ( ! $ai_scribe_images_available ) : ?>
			<p class="form-hint form-error" data-testid="images-unavailable">
				<?php
				echo esc_html(
					class_exists( 'AI_Scribe_Image_Service' )
						? AI_Scribe_Image_Service::image_unavailable_message()
						: __( 'Image generation is unavailable because none of your configured providers can generate images. Add an OpenAI or Google Gemini API key on the Opace AI Hub settings page.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' )
				);
				?>
			</p>
		<?php endif; ?>
		<div class="form-group">
			<label class="checkbox-label">
				<input type="checkbox" id="ai-scribe-images-enabled" data-testid="images-enabled"
					<?php checked( ! empty( $ai_scribe_images['enabled'] ) && $ai_scribe_images_available ); ?>
					<?php disabled( ! $ai_scribe_images_available ); ?>>
				<?php esc_html_e( 'Generate images for articles', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</label>
		</div>
		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-image-model"><?php esc_html_e( 'Image model', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-image-model" class="form-control" data-testid="image-model-select">
					<?php if ( ! empty( $ai_scribe_images['model'] ) ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_images['model'] ); ?>" selected><?php echo esc_html( $ai_scribe_images['model'] ); ?></option>
					<?php endif; ?>
				</select>
			</div>
			<div class="form-group">
				<label for="ai-scribe-image-size"><?php esc_html_e( 'Image size', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-image-size" class="form-control" data-testid="image-size">
					<?php
					$ai_scribe_size_saved = isset( $ai_scribe_images['size'] ) ? $ai_scribe_images['size'] : '1024x1024';
					$ai_scribe_sizes      = array(
						'auto'      => __( 'Auto', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'1024x1024' => __( 'Square (1024×1024)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'1536x1024' => __( 'Landscape (1536×1024)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'1024x1536' => __( 'Portrait (1024×1536)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
					);
					foreach ( $ai_scribe_sizes as $ai_scribe_size => $ai_scribe_size_label ) :
						?>
						<option value="<?php echo esc_attr( $ai_scribe_size ); ?>" <?php selected( $ai_scribe_size_saved, $ai_scribe_size ); ?>><?php echo esc_html( $ai_scribe_size_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="form-row">
			<div class="form-group">
				<label for="ai-scribe-image-quality"><?php esc_html_e( 'Image quality', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-image-quality" class="form-control" data-testid="image-quality">
					<?php foreach ( array( 'high', 'medium', 'low' ) as $ai_scribe_quality ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_quality ); ?>" <?php selected( $ai_scribe_image_quality_saved, $ai_scribe_quality ); ?>><?php echo esc_html( ucfirst( $ai_scribe_quality ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group">
				<label for="ai-scribe-image-format"><?php esc_html_e( 'Output format', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-image-format" class="form-control" data-testid="image-format">
					<?php foreach ( array( 'png', 'webp', 'jpeg' ) as $ai_scribe_format ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_format ); ?>" <?php selected( $ai_scribe_image_format_saved, $ai_scribe_format ); ?>><?php echo esc_html( strtoupper( $ai_scribe_format ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-group">
				<label for="ai-scribe-image-background"><?php esc_html_e( 'Background', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<select id="ai-scribe-image-background" class="form-control" data-testid="image-background">
					<?php foreach ( array( 'auto', 'transparent', 'opaque' ) as $ai_scribe_background ) : ?>
						<option value="<?php echo esc_attr( $ai_scribe_background ); ?>" <?php selected( $ai_scribe_image_background_saved, $ai_scribe_background ); ?>><?php echo esc_html( ucfirst( $ai_scribe_background ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label for="ai-scribe-image-style"><?php esc_html_e( 'Image style', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
			<select id="ai-scribe-image-style" class="form-control" data-testid="image-style">
				<?php
				if ( '' !== $ai_scribe_image_style_saved && ! in_array( $ai_scribe_image_style_saved, $ai_scribe_image_styles, true ) ) {
					$ai_scribe_image_styles[] = $ai_scribe_image_style_saved;
				}
				foreach ( $ai_scribe_image_styles as $ai_scribe_img_style ) :
					?>
					<option value="<?php echo esc_attr( $ai_scribe_img_style ); ?>" <?php selected( $ai_scribe_image_style_saved, $ai_scribe_img_style ); ?>><?php echo esc_html( $ai_scribe_img_style ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="form-hint"><?php esc_html_e( 'The style preset is woven into every image prompt, exactly as in 2.6.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		</div>
	</section>

	<?php /* ---------------- Prompt Library ---------------- */ ?>
	<section class="tab-content<?php echo 'prompts' === $ai_scribe_active_tab ? ' active' : ''; ?>" role="tabpanel" id="settings-panel-prompts" aria-labelledby="settings-tab-prompts" data-tab="prompts" <?php echo 'prompts' === $ai_scribe_active_tab ? '' : 'hidden'; ?>>
		<h2><?php esc_html_e( 'Prompt library', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p class="form-hint"><?php esc_html_e( 'These prompts drive every wizard step. Placeholders are resolved server-side at generation time — what you see after generating is exactly what the model received.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		<details class="prompt-placeholder-help" data-testid="prompt-placeholders">
			<summary><?php esc_html_e( 'Available prompt placeholders', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></summary>
			<ul>
				<li><code>[Idea]</code> — <?php esc_html_e( 'your article topic or idea', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[Title]</code> — <?php esc_html_e( 'the selected title', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[Selected Keywords]</code> — <?php esc_html_e( 'the keywords chosen at step 2', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[Heading]</code> / <code>[Outline]</code> — <?php esc_html_e( 'the selected outline headings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[Intro]</code> — <?php esc_html_e( 'the generated introduction', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[The Tagline]</code> <?php esc_html_e( 'with', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?> <code>[above/below]</code> — <?php esc_html_e( 'the tagline and its placement', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[Language]</code>, <code>[Style]</code>, <code>[Tone]</code> — <?php esc_html_e( 'from the Generation tab', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[No. Headings]</code>, <code>[Heading Tag]</code> — <?php esc_html_e( 'heading controls from the Generation tab', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
				<li><code>[Keywords to Avoid]</code> — <?php esc_html_e( 'appended automatically from "Keywords to avoid"', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
			</ul>
		</details>

		<?php /* The Opace AI Hub view: this tab reflects the hub's library and lets a
			prompt from it drive a wizard step. AI-Scribe's own prompts below
			stay put and remain the fallback — nothing is migrated or deleted. */ ?>
		<?php if ( $ai_scribe_hub_library ) : ?>
			<div class="managed-by-hub" data-testid="hub-prompt-library">
				<div class="managed-by-hub-header">
					<h2><?php esc_html_e( 'Prompts from Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
					<p class="form-hint"><?php esc_html_e( 'The Opace AI Hub plugin is active, so its prompt library is shown here. Apply any of these prompts to a wizard step and that step will use it instead of the AI-Scribe prompt below. Your AI-Scribe prompts are kept as the fallback.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
				</div>

				<?php if ( empty( $ai_scribe_hub_prompts ) ) : ?>
					<p class="form-hint" data-testid="hub-prompt-library-empty">
						<?php esc_html_e( 'Opace AI Hub has no prompts yet. Add some in Opace AI Hub and they will appear here.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $ai_scribe_hub_prompts as $ai_scribe_group_name => $ai_scribe_group_prompts ) : ?>
						<h3 class="settings-section-title" data-testid="hub-prompt-group"><?php echo esc_html( $ai_scribe_group_name ); ?></h3>
						<div class="provider-chip-row" data-testid="hub-prompt-group-items">
							<?php foreach ( $ai_scribe_group_prompts as $ai_scribe_hub_prompt ) : ?>
								<span class="provider-chip provider-chip-configured"
									data-hub-prompt-id="<?php echo esc_attr( (string) $ai_scribe_hub_prompt['id'] ); ?>"
									data-testid="hub-prompt-<?php echo esc_attr( (string) $ai_scribe_hub_prompt['id'] ); ?>"
									title="<?php echo esc_attr( wp_html_excerpt( $ai_scribe_hub_prompt['content'], 300, '…' ) ); ?>">
									<span class="provider-chip-dot" aria-hidden="true"></span>
									<span class="provider-chip-name"><?php echo esc_html( $ai_scribe_hub_prompt['title'] ); ?></span>
									<span class="provider-chip-state"><?php echo esc_html( $ai_scribe_hub_prompt['type'] ); ?></span>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<p>
					<a class="btn btn-outline" data-testid="open-ai-core-prompt-library"
						href="<?php echo esc_url( $ai_scribe_hub_prompt_url ); ?>">
						<?php esc_html_e( 'Manage prompts in Opace AI Hub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</a>
				</p>
			</div>
		<?php else : ?>
			<div class="state-box empty-state" data-testid="hub-prompt-library-absent">
				<p class="state-box-message">
					<?php esc_html_e( 'Opace AI Hub is not active, so there is no shared prompt library to show. AI-Scribe is using its own prompts below, exactly as it always has — nothing is missing and nothing needs doing.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<h3 class="settings-section-title"><?php esc_html_e( 'AI-Scribe prompts', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>

		<?php
		foreach ( $ai_scribe_prompt_fields as $ai_scribe_key => $ai_scribe_label ) :
			$ai_scribe_step        = isset( $ai_scribe_prompt_steps[ $ai_scribe_key ] ) ? $ai_scribe_prompt_steps[ $ai_scribe_key ] : 0;
			$ai_scribe_applied_id  = ( $ai_scribe_step && isset( $ai_scribe_hub_applied[ $ai_scribe_step ] ) ) ? (int) $ai_scribe_hub_applied[ $ai_scribe_step ] : 0;
			$ai_scribe_show_picker = $ai_scribe_hub_library && $ai_scribe_step > 0 && ! empty( $ai_scribe_hub_prompts );

			// 2.6.2 stored these straight from the magic-slashed $_POST, so a
			// migrated value can carry several layers of backslash escaping.
			// Peel them for display; v3's save path stores what it is given.
			$ai_scribe_prompt_value = isset( $ai_scribe_prompts[ $ai_scribe_key ] ) ? (string) $ai_scribe_prompts[ $ai_scribe_key ] : '';
			if ( class_exists( 'AI_Scribe_Prompt_Manager' ) ) {
				$ai_scribe_prompt_value = AI_Scribe_Prompt_Manager::normalise_stored_text( $ai_scribe_prompt_value );
			}
			?>
			<div class="form-group">
				<label for="prompt-<?php echo esc_attr( $ai_scribe_key ); ?>"><?php echo esc_html( $ai_scribe_label ); ?></label>
				<textarea id="prompt-<?php echo esc_attr( $ai_scribe_key ); ?>" class="form-control prompt-library-field" rows="4"
					data-prompt-key="<?php echo esc_attr( $ai_scribe_key ); ?>"
					data-testid="prompt-<?php echo esc_attr( $ai_scribe_key ); ?>"><?php echo esc_textarea( $ai_scribe_prompt_value ); ?></textarea>
				<?php if ( isset( $ai_scribe_prompt_help[ $ai_scribe_key ] ) ) : ?>
					<p class="form-hint"><?php echo esc_html( $ai_scribe_prompt_help[ $ai_scribe_key ] ); ?></p>
				<?php endif; ?>

				<?php if ( $ai_scribe_show_picker ) : ?>
					<div class="input-with-button hub-prompt-apply" data-step="<?php echo esc_attr( (string) $ai_scribe_step ); ?>">
						<label class="visually-hidden" for="hub-prompt-step-<?php echo esc_attr( (string) $ai_scribe_step ); ?>">
							<?php
							/* translators: %s: step label, e.g. "Step 1 — Titles". */
							printf( esc_html__( 'Opace AI Hub prompt for %s', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), esc_html( $ai_scribe_label ) );
							?>
						</label>
						<select class="form-control hub-prompt-select"
							id="hub-prompt-step-<?php echo esc_attr( (string) $ai_scribe_step ); ?>"
							data-step="<?php echo esc_attr( (string) $ai_scribe_step ); ?>"
							data-applied="<?php echo esc_attr( (string) $ai_scribe_applied_id ); ?>"
							data-testid="hub-prompt-select-<?php echo esc_attr( (string) $ai_scribe_step ); ?>">
							<option value="0"><?php esc_html_e( 'Use the AI-Scribe prompt above (default)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
							<?php foreach ( $ai_scribe_hub_prompts as $ai_scribe_group_name => $ai_scribe_group_prompts ) : ?>
								<optgroup label="<?php echo esc_attr( $ai_scribe_group_name ); ?>">
									<?php foreach ( $ai_scribe_group_prompts as $ai_scribe_hub_prompt ) : ?>
										<option value="<?php echo esc_attr( (string) $ai_scribe_hub_prompt['id'] ); ?>" <?php selected( $ai_scribe_applied_id, $ai_scribe_hub_prompt['id'] ); ?>>
											<?php echo esc_html( $ai_scribe_hub_prompt['title'] ); ?>
										</option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
						<button type="button" class="btn btn-outline" data-action="revert-hub-prompt"
							data-step="<?php echo esc_attr( (string) $ai_scribe_step ); ?>"
							data-testid="hub-prompt-revert-<?php echo esc_attr( (string) $ai_scribe_step ); ?>"
							<?php disabled( 0, $ai_scribe_applied_id ); ?>>
							<?php esc_html_e( 'Use AI-Scribe default', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
						</button>
					</div>
					<p class="form-hint" data-hub-prompt-state="<?php echo esc_attr( (string) $ai_scribe_step ); ?>"
						data-testid="hub-prompt-state-<?php echo esc_attr( (string) $ai_scribe_step ); ?>">
						<?php
						if ( $ai_scribe_applied_id ) {
							$ai_scribe_applied_prompt = AI_Scribe_Hub_Prompt_Reader::find_prompt( $ai_scribe_applied_id );
							printf(
								/* translators: %s: Opace AI Hub prompt title. */
								esc_html__( 'This step uses the Opace AI Hub prompt "%s". The prompt above is kept as the fallback.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
								esc_html( $ai_scribe_applied_prompt ? $ai_scribe_applied_prompt['title'] : '' )
							);
						} else {
							esc_html_e( 'This step uses the AI-Scribe prompt above.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' );
						}
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</section>

	<div class="settings-actions">
		<button type="button" class="btn btn-primary" data-action="save-settings" data-testid="settings-save">
			<?php esc_html_e( 'Save Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
		</button>
		<?php
		/*
		 * Visible save confirmation. The only feedback used to be an
		 * aria-live region marked visually-hidden, so a sighted user
		 * clicked Save and saw nothing happen at all.
		 */
		?>
		<p class="settings-save-feedback" role="status" aria-live="polite" data-testid="settings-save-feedback" hidden></p>
	</div>
</div>
