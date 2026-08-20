<?php
/**
 * AI-Scribe v3 — article builder screen.
 *
 * Rebuilt from the v4_frontend design reference (design-reference/index.html):
 * app header (logo, step progress, live cost meter, Start Again, theme toggle),
 * 11-step chip nav (ARIA tablist), per-step panels, Express mode screen.
 *
 * Conventions:
 * - Every interactive element has a stable data-testid (tests/e2e/selectors.ts).
 * - No inline onclick — controllers use event delegation on data-action.
 * - All strings through esc_html__/esc_attr__ with the plugin text domain.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Image generation needs a configured provider that actually offers an image
 * model. Anthropic-only installs have no such model, so the generate controls
 * are withheld rather than offered and then failed: a button that can only
 * error is worse than an explanation of what to add.
 */
$ai_scribe_images_available = ! class_exists( 'AI_Scribe_Image_Service' )
	|| AI_Scribe_Image_Service::images_available();
$ai_scribe_images_reason    = $ai_scribe_images_available || ! class_exists( 'AI_Scribe_Image_Service' )
	? ''
	: AI_Scribe_Image_Service::image_unavailable_message();

/** Render the shared article-local image studio for Body and Review. */
function ai_scribe_render_image_studio( $step, $available, $reason ) {
	?>
	<aside class="image-gallery image-studio<?php echo $available ? '' : ' is-unavailable'; ?>" aria-label="<?php esc_attr_e( 'Article image studio', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
		<div class="image-gallery-header">
			<div><h3 class="image-gallery-title"><i data-lucide="images" aria-hidden="true"></i><?php esc_html_e( 'Article images', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
			<p class="image-gallery-subtitle"><?php esc_html_e( 'Prompts, placement and article-only style controls in one place.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p></div>
			<button type="button" class="btn btn-outline image-insert-all" data-action="insert-all-images" data-testid="insert-all-images" disabled><?php esc_html_e( 'Place all unplaced', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
		</div>
		<?php if ( $available ) : ?>
			<div class="image-generation-status" data-testid="image-generation-status" role="status" aria-live="polite" aria-atomic="false" hidden>
				<span class="image-generation-status-icon" aria-hidden="true"><i data-lucide="sparkles"></i></span>
				<span class="image-generation-status-copy"><strong data-image-progress-title><?php esc_html_e( 'Preparing image studio', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong><span data-image-progress-detail></span></span>
				<time data-image-progress-time>0s</time>
				<ol class="image-progress-queue" data-testid="image-progress-queue" aria-label="<?php esc_attr_e( 'Image generation queue', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>" hidden></ol>
			</div>
			<div class="image-gallery-grid" id="<?php echo 10 === (int) $step ? 'review-image-gallery' : 'image-gallery'; ?>" data-testid="<?php echo 10 === (int) $step ? 'review-image-gallery' : 'image-gallery'; ?>"></div>
			<div class="image-bulk-placement image-bulk-placement-bottom" data-testid="image-bulk-placement-bottom">
				<p><?php esc_html_e( 'Finished reviewing the generated images?', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
				<button type="button" class="btn btn-outline image-insert-all" data-action="insert-all-images" data-testid="insert-all-images-bottom" disabled><?php esc_html_e( 'Place all unplaced', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
			</div>
			<div class="image-create-panel">
				<label class="image-prompt-label" for="image-prompt-<?php echo (int) $step; ?>"><?php esc_html_e( 'Create another image', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<p class="image-create-help"><?php esc_html_e( 'Describe the visual. A caption is created automatically; you can edit or remove it after generation.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
				<textarea id="image-prompt-<?php echo (int) $step; ?>" class="form-control image-prompt-input" rows="3" data-testid="image-prompt-input"></textarea>
				<div class="image-create-actions"><button type="button" class="btn btn-primary" data-action="add-image" data-testid="add-image"><i data-lucide="image-plus" aria-hidden="true"></i><span><?php esc_html_e( 'Generate image', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span></button>
				<button type="button" class="btn btn-outline" data-action="bulk-add-images" data-testid="bulk-add-images"><i data-lucide="layers" aria-hidden="true"></i><span><?php esc_html_e( 'Generate section set', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span></button></div>
				<div class="image-plan" data-testid="image-plan"></div>
			</div>
			<details class="image-override-panel" data-testid="image-override-panel">
				<summary><span><?php esc_html_e( 'Style & output for this article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span><strong data-testid="image-override-state"><?php esc_html_e( 'Using global settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong></summary>
				<label class="image-override-toggle"><input type="checkbox" data-action="toggle-image-overrides" data-testid="image-override-toggle"> <?php esc_html_e( 'Use custom settings for this article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
				<div class="image-override-grid" data-testid="image-override-fields">
					<label><?php esc_html_e( 'Visual style', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?><select class="form-control" data-image-option="style" disabled><option>Photorealistic</option><option>Cinematic lighting</option><option>Watercolour painting</option><option>Oil painting</option><option>Pencil sketch</option><option>Charcoal drawing</option><option>Line art</option><option>Vector illustration</option><option>Cartoon Illustration</option><option>Handdrawn Sketch</option><option>Pop art</option><option>Retro 80s</option><option>Cyberpunk</option><option>Fantasy art</option><option>Surrealist</option><option>Minimalist</option><option>3D render</option><option>Monochrome</option><option>Impressionist</option><option>Low-poly</option></select></label>
					<label><?php esc_html_e( 'Size', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?><select class="form-control" data-image-option="size" disabled><option value="auto">Auto</option><option value="1024x1024">Square</option><option value="1536x1024">Landscape</option><option value="1024x1536">Portrait</option></select></label>
					<label><?php esc_html_e( 'Quality', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?><select class="form-control" data-image-option="quality" disabled><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></label>
					<label><?php esc_html_e( 'Format', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?><select class="form-control" data-image-option="format" disabled><option value="png">PNG</option><option value="webp">WebP</option><option value="jpeg">JPEG</option></select></label>
					<label><?php esc_html_e( 'Background', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?><select class="form-control" data-image-option="background" disabled><option value="auto">Auto</option><option value="opaque">Opaque</option><option value="transparent">Transparent</option></select></label>
				</div>
				<button type="button" class="btn btn-link image-reset-overrides" data-action="reset-image-overrides"><?php esc_html_e( 'Reset to global settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
			</details>
		<?php else : ?><p class="editor-help-text" data-testid="images-unavailable"><?php echo esc_html( $reason ); ?></p><?php endif; ?>
		<span class="image-status-line" role="status" aria-live="polite" data-testid="image-status"></span>
	</aside>
	<?php
}

/**
 * Render the shared persistence card on Review and Evaluate.
 *
 * The server-confirmed state is painted by WizardFlowController. Keeping the
 * same card on both final steps means Evaluate never implies that evaluation
 * also saved the article.
 */
function ai_scribe_render_save_status( $context ) {
	$context       = in_array( $context, array( 'express', 'evaluate', 'review' ), true ) ? $context : 'review';
	$heading_id    = 'save-status-heading-' . $context;
	$post_testid   = 'review' === $context ? 'saved-post-link' : $context . '-saved-post-link';
	$draft_testid  = 'review' === $context ? 'publish-draft' : $context . '-publish-draft';
	$publish_testid = 'review' === $context ? 'publish-post' : $context . '-publish-post';
	$short_testid  = 'review' === $context ? 'save-shortcode' : $context . '-save-shortcode';
	?>
	<section class="save-status-card is-unsaved" data-save-status-card data-save-context="<?php echo esc_attr( $context ); ?>" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
		<div class="save-status-summary">
			<div class="save-status-copy">
				<h3 id="<?php echo esc_attr( $heading_id ); ?>"><?php esc_html_e( 'Save status', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
				<p data-save-status-message role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'Not saved. This article currently exists only in AI-Scribe.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
			</div>
			<span class="save-status-badge" data-save-status-badge><?php esc_html_e( 'Not saved', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
		</div>
		<ul class="save-destinations" data-save-destinations hidden>
			<li data-save-destination="post" hidden><strong data-save-destination-label></strong><span data-save-destination-state></span></li>
			<li data-save-destination="shortcode" hidden><strong><?php esc_html_e( 'Shortcode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong><span data-save-destination-state></span></li>
		</ul>
		<div class="save-status-actions" aria-label="<?php esc_attr_e( 'Save article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
			<button type="button" class="btn btn-outline btn-save" data-action="save-draft" data-testid="<?php echo esc_attr( $draft_testid ); ?>"<?php disabled( 'express' === $context ); ?>>
				<i data-lucide="save" aria-hidden="true"></i><span data-save-label><?php esc_html_e( 'Save as Draft', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
			</button>
			<button type="button" class="btn btn-outline btn-save" data-action="save-post" data-testid="<?php echo esc_attr( $publish_testid ); ?>"<?php disabled( 'express' === $context ); ?>>
				<i data-lucide="file-plus" aria-hidden="true"></i><span data-save-label><?php esc_html_e( 'Publish Post', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
			</button>
			<button type="button" class="btn btn-outline btn-save" data-action="save-shortcode" data-testid="<?php echo esc_attr( $short_testid ); ?>"<?php disabled( 'express' === $context ); ?>>
				<i data-lucide="code" aria-hidden="true"></i><span data-save-label><?php esc_html_e( 'Save as Shortcode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
			</button>
			<a class="btn btn-outline" data-saved-post-link data-testid="<?php echo esc_attr( $post_testid ); ?>" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'View saved post', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></a>
		</div>
		<code class="saved-shortcode-note" data-saved-shortcode-note data-testid="<?php echo esc_attr( $context ); ?>-saved-shortcode-note" hidden></code>
	</section>
	<?php
}

/**
 * Step definitions: number => [chip label, panel heading, lucide icon, continue label].
 */
$ai_scribe_steps = array(
	1  => array( __( 'Title', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Title Generation', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'type', __( 'Continue to Keywords', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	2  => array( __( 'Keywords', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Keyword Research', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'search', __( 'Continue to Outline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	3  => array( __( 'Outline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Article Outline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'list', __( 'Continue to Introduction', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	4  => array( __( 'Intro', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Introduction', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'play-circle', __( 'Continue to Tagline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	5  => array( __( 'Tagline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Tagline', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'tag', __( 'Continue to Article Body', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	6  => array( __( 'Body', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Article Body', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'file-text', __( 'Continue to Conclusion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	7  => array( __( 'Conclusion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Conclusion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'check-circle', __( 'Continue to Q&A', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	8  => array( __( 'Q&A', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Questions & Answers', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'help-circle', __( 'Continue to SEO', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	9  => array( __( 'SEO', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'SEO Meta', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'database', __( 'Continue to Review', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	10 => array( __( 'Review', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Review & Edit', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'eye', __( 'Continue to Evaluate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
	11 => array( __( 'Evaluate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), __( 'Article Quality Analysis', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), 'bar-chart-3', '' ),
);

$ai_scribe_step_names = array();
foreach ( $ai_scribe_steps as $ai_scribe_n => $ai_scribe_def ) {
	$ai_scribe_step_names[ $ai_scribe_n ] = $ai_scribe_def[1];
}
?>

<div id="ai-scribe-root" class="app-container ai-scribe-app" data-testid="app-root">

	<?php /* UI strings for the JS layer (merged into window.ai_scribe.i18n by main.js). */ ?>
	<script type="application/json" id="ai-scribe-i18n">
	<?php
	echo wp_json_encode(
		array(
			'generating'    => __( 'Generating…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'preparingRequest' => __( 'Preparing request…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'waitingForResponse' => __( 'Waiting for and checking the provider response…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'displayingResult' => __( 'Response received. Displaying the result…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'empty'         => __( 'Nothing generated yet. Use Generate to create options.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'genericError'  => __( 'Something went wrong. Your tokens were not wasted — retry re-renders the stored response.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'retry'         => __( 'Retry', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'tryAgain'      => __( 'Try again', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'topicRequired' => __( 'Enter a topic or idea first.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'qnaHeading'      => __( 'Questions & Answers', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'noKeysNotice'    => __( 'No AI provider is configured yet. Add an API key to start generating.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'openSettings'    => __( 'Open Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'noModelSelected' => __( 'No model selected yet', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'noCostData'      => __( 'No data yet', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'automaticStep'   => __( 'This step is automatic. There is no prompt to edit.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'featuredImage'   => __( 'Featured image', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'selectToPlace'   => __( 'Drag into the article, or select and click a spot to place it', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'stillRunning'    => __( 'Still working; longer responses can take up to a minute.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improveLength'   => __( 'Improve length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improvingLength' => __( 'Improving length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'retryImprovement' => __( 'Try improvement again', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improveLengthIdle' => __( 'AI-Scribe will extend thin sections without replacing the draft you can see.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improveLengthLoading' => __( 'Extending the current draft now. The existing version will stay here if the request cannot finish.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improveLengthError' => __( 'The improvement could not finish. Your existing draft is unchanged.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'improveLengthSuccess' => __( 'The draft was extended and the count has been checked again.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'qnaListHint'     => __( 'Untick any Q&A you do not want in the article. The wording can be edited before you continue.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'qnaInclude'      => __( 'Include in article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
			'deleteImage'     => __( 'Delete', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'replaceImage'    => __( 'Replace', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'replaceHint'     => __( 'Choose a gallery image, or describe a new one to generate.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'replacePromptPlaceholder' => __( 'Describe the replacement image…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'generateReplacement' => __( 'Generate replacement', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
				'stepNames'       => $ai_scribe_step_names,
		)
	);
	?>
	</script>

	<header class="app-header">
		<div class="header-content">
			<div class="logo-section">
				<img class="logo-image" src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/images/ai-scribe-logo-icon.png' ); ?>"
					alt="" aria-hidden="true" width="40" height="40" data-testid="brand-logo">
				<div class="logo-text">
					<h1><?php esc_html_e( 'AI-Scribe', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h1>
					<span class="version"><?php echo esc_html( defined( 'AI_SCRIBE_VERSION' ) ? 'v' . AI_SCRIBE_VERSION : 'v3.0' ); ?></span>
				</div>
			</div>

			<div class="header-center">
				<div class="mode-switcher" role="group" aria-label="<?php esc_attr_e( 'Generation mode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
					<button type="button" class="btn btn-outline mode-btn active" data-action="mode-wizard" data-testid="mode-wizard" aria-pressed="true">
						<i data-lucide="list-checks" aria-hidden="true"></i>
						<?php esc_html_e( 'Wizard', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</button>
					<button type="button" class="btn btn-outline mode-btn" data-action="mode-express" data-testid="mode-express" aria-pressed="false">
						<i data-lucide="zap" aria-hidden="true"></i>
						<?php esc_html_e( 'Express', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</button>
				</div>
				<div class="progress-container">
					<div class="progress-info">
						<span id="progress-text" data-testid="progress-text"><?php esc_html_e( 'Step 1 of 11 — Title Generation', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
						<span id="progress-percentage">9%</span>
						<button type="button" class="btn btn-outline btn-reset" data-action="start-again" data-testid="start-again">
							<i data-lucide="refresh-ccw" aria-hidden="true"></i>
							<?php esc_html_e( 'Start Again', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
						</button>
					</div>
					<div class="progress-bar" role="progressbar" aria-valuemin="1" aria-valuemax="11" aria-valuenow="1" aria-label="<?php esc_attr_e( 'Wizard progress', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
						<div class="progress-fill" id="progress-fill"></div>
					</div>
				</div>
			</div>

			<div class="header-right">
				<div class="cost-display" data-testid="cost-meter">
					<div class="cost-header">
						<span class="cost-label-main"><?php esc_html_e( 'Article Cost', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
					</div>
					<div class="cost-items">
						<div class="cost-item">
							<span class="cost-label"><?php esc_html_e( 'Total:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
							<span class="cost-value" id="header-total-cost" data-testid="cost-total"><?php esc_html_e( 'No data yet', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
						</div>
						<div class="cost-item">
							<span class="cost-label"><?php esc_html_e( 'Last step:', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
							<span class="cost-value" id="header-current-cost" data-testid="cost-current"><?php esc_html_e( 'No data yet', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
						</div>
						<?php /* Contract §6: what this step is about to cost, shown before the user commits to the run. Reads "—" while there is nothing to estimate, exactly like the two meters above it. */ ?>
						<div class="cost-item" data-testid="cost-estimate-item">
							<span class="cost-label"><?php esc_html_e( 'This step (est.):', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
							<span class="cost-value" id="header-estimate-cost" data-testid="cost-estimate"><?php esc_html_e( 'No data yet', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
						</div>
					</div>
				</div>
				<button type="button" class="btn btn-outline theme-toggle" data-action="toggle-theme" data-testid="theme-toggle" aria-pressed="false" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
					<i data-lucide="moon" class="theme-icon-dark" aria-hidden="true"></i>
					<i data-lucide="sun" class="theme-icon-light" aria-hidden="true"></i>
				</button>
			</div>
		</div>
	</header>

	<section class="resume-draft-notice" data-testid="resume-draft-notice" role="status" aria-labelledby="resume-draft-heading" hidden>
		<div>
			<h2 id="resume-draft-heading"><?php esc_html_e( 'Continue your saved article?', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
			<p><?php esc_html_e( 'A draft from this browser tab is available. Resume it explicitly, or keep this clean new session.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
		</div>
		<div class="resume-draft-actions">
			<button type="button" class="btn btn-primary" data-action="resume-draft" data-testid="resume-draft"><?php esc_html_e( 'Resume draft', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
			<button type="button" class="btn btn-outline" data-action="discard-resume" data-testid="discard-resume"><?php esc_html_e( 'Start clean', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
		</div>
	</section>

	<?php /* ================= Wizard screen ================= */ ?>
	<div class="mode-screen active" data-mode-screen="wizard" data-testid="wizard-screen">

		<nav class="step-navigation" id="step-navigation" aria-label="<?php esc_attr_e( 'Wizard steps', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
			<div class="steps-container" role="tablist" aria-orientation="horizontal">
				<?php foreach ( $ai_scribe_steps as $ai_scribe_n => $ai_scribe_def ) : ?>
					<button type="button"
						class="step<?php echo 1 === $ai_scribe_n ? ' active' : ''; ?>"
						role="tab"
						id="step-tab-<?php echo esc_attr( (string) $ai_scribe_n ); ?>"
						aria-controls="step-panel-<?php echo esc_attr( (string) $ai_scribe_n ); ?>"
						aria-selected="<?php echo 1 === $ai_scribe_n ? 'true' : 'false'; ?>"
						<?php echo 1 === $ai_scribe_n ? 'aria-current="step" tabindex="0"' : 'tabindex="-1"'; ?>
						data-step="<?php echo esc_attr( (string) $ai_scribe_n ); ?>"
						data-action="nav-step"
						data-testid="step-chip-<?php echo esc_attr( (string) $ai_scribe_n ); ?>">
						<span class="step-icon" aria-hidden="true"><i data-lucide="<?php echo esc_attr( $ai_scribe_def[2] ); ?>"></i></span>
						<span class="step-label"><?php echo esc_html( $ai_scribe_def[0] ); ?></span>
						<span class="step-number" aria-hidden="true"><?php echo esc_html( (string) $ai_scribe_n ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</nav>

		<div class="two-column-layout">
			<div class="main-panel">
				<div class="workflow-container" id="workflow-container">

					<?php foreach ( $ai_scribe_steps as $ai_scribe_n => $ai_scribe_def ) : ?>
					<section class="step-content<?php echo 1 === $ai_scribe_n ? ' active' : ''; ?>"
						id="step-panel-<?php echo esc_attr( (string) $ai_scribe_n ); ?>"
						role="tabpanel"
						aria-labelledby="step-tab-<?php echo esc_attr( (string) $ai_scribe_n ); ?>"
						data-step-panel="<?php echo esc_attr( (string) $ai_scribe_n ); ?>"
						data-state="idle"
						<?php echo 1 === $ai_scribe_n ? '' : 'hidden'; ?>>

						<h2 class="step-heading" data-step-heading tabindex="-1"><?php echo esc_html( $ai_scribe_def[1] ); ?></h2>
						<p class="visually-hidden" role="status" aria-live="polite" data-testid="step-status"></p>

						<?php if ( 1 === $ai_scribe_n ) : ?>
							<div class="input-section">
								<div class="form-group">
									<label for="topic-input"><?php esc_html_e( 'Article topic or idea', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
									<div class="input-with-button">
										<input type="text" id="topic-input" class="form-control" data-testid="idea-input"
											placeholder="<?php esc_attr_e( 'Enter your keyword or idea to begin…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
										<button type="button" class="btn btn-primary generate-btn-inline" data-action="generate" data-testid="generate">
											<i data-lucide="sparkles" aria-hidden="true"></i>
											<?php esc_html_e( 'Generate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
										</button>
									</div>
									<div class="form-row article-length-override" data-testid="wizard-length-override">
										<div class="form-group">
											<label for="wizard-length-mode"><?php esc_html_e( 'Length for this article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
											<select id="wizard-length-mode" class="form-control" data-article-length-mode>
												<option value="global"><?php esc_html_e( 'Use global default', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
												<option value="auto"><?php esc_html_e( 'Auto — planned from article scope', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
												<option value="concise"><?php esc_html_e( 'Concise — 800–1,100 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
												<option value="standard"><?php esc_html_e( 'Standard — 1,500–2,100 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
												<option value="in_depth"><?php esc_html_e( 'In-depth — 2,400–3,200 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
												<option value="custom"><?php esc_html_e( 'Custom — choose a target', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
											</select>
										</div>
										<div class="form-group" data-custom-word-count hidden>
											<label for="wizard-word-count"><?php esc_html_e( 'Custom target words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
											<input type="number" id="wizard-word-count" class="form-control" data-article-word-count min="400" max="8000" step="50" value="1800">
										</div>
									</div>
								</div>
								<p class="form-hint article-plan-summary" data-article-plan-summary role="status" aria-live="polite"></p>
							</div>
						<?php endif; ?>

						<?php if ( in_array( $ai_scribe_n, array( 1, 2, 3, 5 ), true ) ) : ?>
							<?php
							$ai_scribe_grid_ids = array(
								1 => 'titles-options',
								2 => 'keywords-options',
								3 => 'outline-options',
								5 => 'tagline-options',
							);
							?>
							<div class="results-section hidden">
								<div class="choice-guidance" data-testid="choice-guidance">
									<p><?php
										$ai_scribe_choice_help = array(
											1 => __( 'Choose one title. Current-year topics should use the explicit year, and familiar acronyms such as SEO remain capitalised.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
											2 => __( 'Choose one or more keyword suggestions. Demand bands are qualitative AI estimates, not measured search-volume data. Use Google Trends to check relative interest and seasonality.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
											3 => __( 'Keep the sections you want in the article. You can deselect any heading before continuing.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
											5 => __( 'One article-specific tagline is generated and selected. Use Try another only if you want an alternative.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
										);
										echo esc_html( $ai_scribe_choice_help[ $ai_scribe_n ] );
									?></p>
									<div class="choice-guidance-actions">
										<span class="choice-selection-status" role="status" aria-live="polite" data-testid="choice-selection-status"></span>
										<?php if ( 2 === $ai_scribe_n ) : ?>
											<button type="button" class="btn btn-link" data-choice-action="select-all"><?php esc_html_e( 'Select all', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
											<button type="button" class="btn btn-link" data-choice-action="deselect-all"><?php esc_html_e( 'Deselect all', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
											<a class="btn btn-link keyword-compare-link is-disabled" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true" tabindex="-1" data-keyword-action="compare-trends"><?php esc_html_e( 'Compare selected in Google Trends', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?> <span aria-hidden="true">↗</span></a>
										<?php endif; ?>
									</div>
									<?php if ( 2 === $ai_scribe_n ) : ?><p class="keyword-load-warning" data-testid="keyword-load-warning" role="status" hidden></p><?php endif; ?>
								</div>
								<div class="<?php echo 2 === $ai_scribe_n ? 'keywords-grid' : 'options-grid'; ?>" id="<?php echo esc_attr( $ai_scribe_grid_ids[ $ai_scribe_n ] ); ?>" data-testid="options-grid"></div>
								<?php if ( 5 === $ai_scribe_n ) : ?>
									<fieldset class="placement-options" data-testid="tagline-placement">
										<legend><?php esc_html_e( 'Tagline placement', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></legend>
										<label class="radio-label">
											<input type="radio" name="above_below_tagline" value="above" data-testid="tagline-above">
											<?php esc_html_e( 'Above the introduction', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
										</label>
										<label class="radio-label">
											<input type="radio" name="above_below_tagline" value="below" checked data-testid="tagline-below">
											<?php esc_html_e( 'Below the introduction', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
										</label>
									</fieldset>
								<?php endif; ?>
								<div class="results-actions">
									<?php if ( 1 !== $ai_scribe_n ) : ?>
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<?php endif; ?>
									<button type="button" class="btn btn-outline generate-more-btn" data-action="generate-more" data-testid="regenerate">
										<i data-lucide="refresh-cw" aria-hidden="true"></i>
										<?php 5 === $ai_scribe_n ? esc_html_e( 'Try another', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) : esc_html_e( 'Generate More', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<?php if ( 1 !== $ai_scribe_n ) : ?>
									<button type="button" class="btn btn-outline" data-action="skip-step" data-testid="skip">
										<?php esc_html_e( 'Skip', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<?php endif; ?>
									<button type="button" class="btn btn-primary next-step-btn" data-action="continue" data-testid="continue" disabled>
										<?php echo esc_html( $ai_scribe_def[3] ); ?>
										<i data-lucide="arrow-right" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( in_array( $ai_scribe_n, array( 4, 7 ), true ) ) : ?>
							<?php $ai_scribe_stream_id = 4 === $ai_scribe_n ? 'intro-stream-output' : 'conclusion-stream-output'; ?>
							<div class="results-section hidden">
								<p class="editable-prose-hint"><i data-lucide="pencil" aria-hidden="true"></i><?php esc_html_e( 'Edit the generated wording directly below, or regenerate it.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
								<div class="stream-output prose-output" id="<?php echo esc_attr( $ai_scribe_stream_id ); ?>" data-testid="stream-output" aria-live="off"></div>
								<div class="results-actions">
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<button type="button" class="btn btn-outline" data-action="generate" data-testid="regenerate">
										<i data-lucide="refresh-cw" aria-hidden="true"></i>
										<?php esc_html_e( 'Regenerate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-outline" data-action="skip-step" data-testid="skip">
										<?php esc_html_e( 'Skip', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-primary next-step-btn" data-action="continue" data-testid="continue" disabled>
										<?php echo esc_html( $ai_scribe_def[3] ); ?>
										<i data-lucide="arrow-right" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 6 === $ai_scribe_n ) : ?>
							<div class="results-section hidden">
								<div class="article-target-status wizard-target-status" data-wizard-target-status data-length-scope="body" hidden>
									<div class="article-target-status-copy"><strong data-target-status-heading></strong><span data-target-status-detail></span></div>
									<div class="article-target-track" aria-hidden="true"><span data-target-status-bar></span></div>
									<div class="article-target-action" data-target-status-action hidden>
										<div><strong><?php esc_html_e( 'Keep this draft and improve its length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong><p data-improve-length-status role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'AI-Scribe will add useful detail without replacing your current wording or outline.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p></div>
										<button type="button" class="btn btn-outline" data-action="wizard-improve-length" data-testid="body-improve-length"><i data-lucide="wand-2" aria-hidden="true"></i><span data-improve-length-label><?php esc_html_e( 'Improve length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span></button>
									</div>
								</div>
								<div class="editor-with-gallery">
									<?php ai_scribe_render_image_studio( 6, $ai_scribe_images_available, $ai_scribe_images_reason ); ?>
									<div class="quill-editor-container">
										<div class="stream-output prose-output" id="body-stream-output" data-testid="stream-output" aria-live="off" hidden></div>
										<div id="body-quill-editor" class="quill-editor" data-testid="content-editor"></div>
										<p class="editor-help-text">
											<i data-lucide="info" aria-hidden="true"></i>
											<span><?php esc_html_e( 'Tip: drag an image from the panel into the article, or click it and then click a spot. Hover an inserted image to replace or delete it.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
										</p>
									</div>
								</div>
								<div class="results-actions">
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<button type="button" class="btn btn-outline" data-action="generate" data-testid="regenerate">
										<i data-lucide="refresh-cw" aria-hidden="true"></i>
										<?php esc_html_e( 'Regenerate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-primary next-step-btn" data-action="continue" data-testid="continue" disabled>
										<?php echo esc_html( $ai_scribe_def[3] ); ?>
										<i data-lucide="arrow-right" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 8 === $ai_scribe_n ) : ?>
							<div class="results-section hidden">
								<div class="qa-options" id="qa-options" data-testid="options-grid"></div>
								<fieldset class="placement-options" data-testid="qna-placement">
									<legend><?php esc_html_e( 'Q&A placement', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></legend>
									<label class="radio-label">
										<input type="radio" name="above_below_conclusion" value="above" data-testid="qna-above">
										<?php esc_html_e( 'Above the conclusion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</label>
									<label class="radio-label">
										<input type="radio" name="above_below_conclusion" value="below" checked data-testid="qna-below">
										<?php esc_html_e( 'Below the conclusion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</label>
								</fieldset>
								<div class="results-actions">
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<button type="button" class="btn btn-outline" data-action="generate" data-testid="regenerate">
										<i data-lucide="refresh-cw" aria-hidden="true"></i>
										<?php esc_html_e( 'Regenerate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-outline" data-action="skip-step" data-testid="skip">
										<?php esc_html_e( 'Skip Q&A', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-primary next-step-btn" data-action="continue" data-testid="continue" disabled>
										<?php echo esc_html( $ai_scribe_def[3] ); ?>
										<i data-lucide="arrow-right" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 9 === $ai_scribe_n ) : ?>
							<div class="results-section hidden" id="meta-results">
								<p class="seo-meta-intro" id="seo-meta-help">
									<?php esc_html_e( 'Generated suggestions, not locked fields. Edit both before continuing; your edits are what Review and Save use.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
								</p>
								<div class="seo-combined-input" data-testid="seo-meta-card">
									<div class="seo-field">
										<label for="meta-title"><?php esc_html_e( 'Meta Title', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
										<input type="text" id="meta-title" data-testid="meta-title" aria-describedby="seo-meta-help meta-title-guidance meta-separator-guidance" autocomplete="off" placeholder="<?php esc_attr_e( 'Generated meta title…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
										<span class="meta-count" data-testid="meta-title-count">0 characters</span>
										<span class="seo-meta-guidance" id="meta-title-guidance" data-testid="meta-title-guidance"></span>
									</div>
									<div class="seo-field">
										<label for="meta-description"><?php esc_html_e( 'Meta Description', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
										<textarea id="meta-description" rows="3" data-testid="meta-description" aria-describedby="seo-meta-help meta-description-guidance" placeholder="<?php esc_attr_e( 'Generated meta description…', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>"></textarea>
										<span class="meta-count" data-testid="meta-description-count">0 characters</span>
										<span class="seo-meta-guidance" id="meta-description-guidance" data-testid="meta-description-guidance"></span>
									</div>
								</div>
								<div class="seo-meta-checks" data-testid="seo-meta-checks">
									<p class="seo-meta-check" id="meta-separator-guidance" data-testid="meta-separator-guidance"></p>
									<div class="seo-meta-keyword-coverage" data-testid="meta-keyword-guidance"></div>
									<p class="seo-meta-destination"><?php esc_html_e( 'On save, these values go to Yoast, Rank Math, AIOSEO or SEOPress when active. Otherwise AI-Scribe stores and outputs them for the post.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
									<span class="screen-reader-text" data-testid="seo-meta-live" role="status" aria-live="polite" aria-atomic="true"></span>
								</div>
								<section class="meta-optimise-panel" data-testid="meta-optimise-panel" hidden>
									<h3><?php esc_html_e( 'Metadata is above its display guide', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
									<p><?php esc_html_e( 'You can keep editing manually or make an optional model call to shorten it while preserving keyword coverage and accuracy.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
									<button type="button" class="btn btn-outline" data-action="optimise-meta-length" data-testid="optimise-meta-length"><?php esc_html_e( 'Optimise metadata length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<p class="meta-optimise-status" data-testid="meta-optimise-status" role="status" aria-live="polite"></p>
									<div class="meta-optimise-comparison" data-testid="meta-optimise-comparison" hidden>
										<h4><?php esc_html_e( 'Before and after', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h4>
										<dl><dt><?php esc_html_e( 'Original title', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></dt><dd data-testid="meta-original-title"></dd><dt><?php esc_html_e( 'Suggested title', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></dt><dd data-testid="meta-suggested-title"></dd><dt><?php esc_html_e( 'Original description', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></dt><dd data-testid="meta-original-description"></dd><dt><?php esc_html_e( 'Suggested description', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></dt><dd data-testid="meta-suggested-description"></dd></dl>
										<div class="meta-optimise-actions"><button type="button" class="btn btn-primary" data-action="apply-meta-optimisation"><?php esc_html_e( 'Apply suggestion', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button><button type="button" class="btn btn-outline" data-action="keep-original-meta"><?php esc_html_e( 'Keep original', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button></div>
									</div>
									<button type="button" class="btn btn-link" data-action="undo-meta-optimisation" hidden><?php esc_html_e( 'Undo metadata change', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
								</section>
								<div class="results-actions">
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<button type="button" class="btn btn-outline" data-action="generate" data-testid="regenerate">
										<i data-lucide="refresh-cw" aria-hidden="true"></i>
										<?php esc_html_e( 'Regenerate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-outline" data-action="skip-step" data-testid="skip">
										<?php esc_html_e( 'Skip', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-primary next-step-btn" data-action="continue" data-testid="continue" disabled>
										<?php echo esc_html( $ai_scribe_def[3] ); ?>
										<i data-lucide="arrow-right" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 10 === $ai_scribe_n ) : ?>
							<div class="review-content">
								<p class="step-subtitle"><?php esc_html_e( 'Review your completed article and make final edits.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
								<div class="article-target-status wizard-target-status" data-wizard-target-status data-length-scope="review" hidden>
									<div class="article-target-status-copy"><strong data-target-status-heading></strong><span data-target-status-detail></span></div>
									<div class="article-target-track" aria-hidden="true"><span data-target-status-bar></span></div>
									<div class="article-target-action" data-target-status-action hidden>
										<div><strong><?php esc_html_e( 'Article below the selected target', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong><p data-improve-length-status role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'Improve the exact reviewed draft; your edits, existing wording and outline stay in place.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p></div>
										<button type="button" class="btn btn-outline" data-action="wizard-improve-length" data-testid="review-improve-length"><i data-lucide="wand-2" aria-hidden="true"></i><span data-improve-length-label><?php esc_html_e( 'Improve length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span></button>
									</div>
								</div>
								<section class="featured-image-review" data-testid="featured-image-preview" aria-labelledby="featured-image-preview-heading" hidden>
									<div class="featured-image-review-copy">
										<h3 id="featured-image-preview-heading"><?php esc_html_e( 'Featured image preview', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
										<p><?php esc_html_e( 'Used as the WordPress featured image; it is not duplicated inside the article.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
									</div>
									<div class="featured-image-review-media" data-featured-preview-media></div>
								</section>
								<div class="editor-with-gallery">
									<?php ai_scribe_render_image_studio( 10, $ai_scribe_images_available, $ai_scribe_images_reason ); ?>
									<div class="quill-editor-container">
										<div id="review-quill-editor" class="quill-editor" data-testid="content-editor"></div>
									</div>
								</div>
								<section class="publishing-details" data-testid="publishing-details" aria-labelledby="publishing-details-heading">
									<div class="publishing-details-heading">
										<h3 id="publishing-details-heading"><?php esc_html_e( 'Publishing details', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
										<?php if ( current_user_can( 'manage_categories' ) ) : ?>
											<p><?php esc_html_e( 'Suggested from this article. WordPress will create or assign these terms when the post is saved.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
										<?php else : ?>
											<p><?php esc_html_e( 'Suggested from this article. Your account can assign existing terms; WordPress will confirm what was saved.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
										<?php endif; ?>
									</div>
									<div class="publishing-details-grid">
										<label><?php esc_html_e( 'Category', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
											<input type="text" class="form-control" data-publishing-category autocomplete="off">
										</label>
										<label><?php esc_html_e( 'Tags', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
											<input type="text" class="form-control" data-publishing-tags autocomplete="off" placeholder="<?php esc_attr_e( 'Comma-separated', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
										</label>
									</div>
									<p class="publishing-author"><?php
										/* translators: %s is the current WordPress user's display name. */
										echo esc_html( sprintf( __( 'Author: %s (your current WordPress account)', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ), wp_get_current_user()->display_name ) );
									?></p>
									<p class="publishing-assignment-result" data-publishing-result role="status" aria-live="polite"></p>
								</section>
								<?php ai_scribe_render_save_status( 'review' ); ?>
								<div class="review-actions results-actions">
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<button type="button" class="btn btn-primary next-step-btn" data-action="continue" data-testid="continue">
										<?php echo esc_html( $ai_scribe_def[3] ); ?>
										<i data-lucide="arrow-right" aria-hidden="true"></i>
									</button>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 11 === $ai_scribe_n ) : ?>
							<div class="results-section hidden">
								<p class="step-subtitle"><?php esc_html_e( 'A factual review of the final article. Measured facts come from the Review HTML; editorial judgements show their evidence.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
								<div class="evaluation-table-container stream-output" id="evaluation-output" data-testid="evaluation-output" aria-live="off"></div>
								<?php ai_scribe_render_save_status( 'evaluate' ); ?>
								<div class="results-actions">
									<button type="button" class="btn btn-outline" data-action="back" data-testid="back"><i data-lucide="arrow-left" aria-hidden="true"></i><?php esc_html_e( 'Back', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></button>
									<button type="button" class="btn btn-outline" data-action="generate" data-testid="regenerate">
										<i data-lucide="refresh-cw" aria-hidden="true"></i>
										<?php esc_html_e( 'Re-evaluate', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
									</button>
									<button type="button" class="btn btn-primary" data-action="start-again" data-testid="complete">
										<i data-lucide="check-circle" aria-hidden="true"></i>
										<span data-complete-label><?php esc_html_e( 'Finish & Start New', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
									</button>
								</div>
							</div>
						<?php endif; ?>

					</section>
					<?php endforeach; ?>

				</div>
			</div>

			<aside class="settings-panel" aria-label="<?php esc_attr_e( 'Generation options and prompt', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
				<div class="panel-header">
					<h2><?php esc_html_e( 'Options & Prompt', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
				</div>
				<div class="panel-content">
					<div class="settings-section">
						<h3 class="settings-section-title"><?php esc_html_e( 'Selected model', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
						<div class="model-info" data-testid="model-info">
							<?php
						// Server-rendered starting value (the JS hydrator swaps in
						// the live label/provider) — never a stuck "Loading…".
						$ai_scribe_engine_now  = get_option( 'ab_gpt_ai_engine_settings', array() );
						$ai_scribe_model_now   = isset( $ai_scribe_engine_now['model'] ) ? (string) $ai_scribe_engine_now['model'] : '';
						// Opace AI Hub owns provider configuration. With no model of its
						// own — the normal state on a fresh install — show the one
						// generation will actually use, rather than claiming none is
						// selected beside a populated picker.
						if ( '' === $ai_scribe_model_now ) {
							$ai_scribe_hub_now = get_option( 'ai_core_settings', array() );
							if ( is_array( $ai_scribe_hub_now ) ) {
								$ai_scribe_hub_provider = isset( $ai_scribe_hub_now['default_provider'] ) ? (string) $ai_scribe_hub_now['default_provider'] : '';
								if ( isset( $ai_scribe_hub_now['provider_models'][ $ai_scribe_hub_provider ] ) ) {
									$ai_scribe_model_now = (string) $ai_scribe_hub_now['provider_models'][ $ai_scribe_hub_provider ];
								} elseif ( ! empty( $ai_scribe_hub_now['provider_models'] ) && is_array( $ai_scribe_hub_now['provider_models'] ) ) {
									foreach ( $ai_scribe_hub_now['provider_models'] as $ai_scribe_candidate ) {
										if ( is_string( $ai_scribe_candidate ) && '' !== $ai_scribe_candidate ) {
											$ai_scribe_model_now = $ai_scribe_candidate;
											break;
										}
									}
								}
							}
						}
						?>
						<span class="model-details" id="active-model-details"><?php echo esc_html( '' !== $ai_scribe_model_now ? $ai_scribe_model_now : __( 'No model selected yet', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ); ?></span>
							<a class="settings-link" data-testid="open-settings" href="<?php echo esc_url( admin_url( 'admin.php?page=ai_scribe_settings' ) ); ?>"><?php esc_html_e( 'Change in Settings', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></a>
						</div>
					</div>
					<div class="settings-section">
						<h3 class="settings-section-title"><?php esc_html_e( 'Current prompt', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h3>
						<div class="form-group">
							<label for="prompt-editor" class="visually-hidden"><?php esc_html_e( 'Prompt for the current step', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
							<textarea id="prompt-editor" class="form-control prompt-editor" rows="8" data-testid="prompt-editor"
								aria-describedby="prompt-editor-hint prompt-run-status"
								placeholder="<?php esc_attr_e( 'The assembled prompt for the current step appears here. Edit it to override this run.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>"></textarea>
						</div>
						<p class="form-hint" id="prompt-editor-hint"><?php esc_html_e( 'Edit this prompt for the current step only. Placeholders like [Title] are resolved by the server.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
						<div class="prompt-run-actions">
							<button type="button" class="btn btn-primary prompt-run-button" data-action="run-amended-prompt" data-testid="run-amended-prompt" aria-describedby="prompt-run-status" disabled>
								<i data-lucide="play" aria-hidden="true"></i>
								<span data-prompt-run-label><?php esc_html_e( 'Run amended prompt', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
							</button>
							<p class="prompt-run-status" id="prompt-run-status" data-testid="prompt-run-status" role="status" aria-live="polite"><?php esc_html_e( 'Edit the prompt to enable this button.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
						</div>
					</div>
				</div>
			</aside>
		</div>
	</div>

	<?php /* ================= Express screen ================= */ ?>
	<div class="mode-screen express-screen" data-mode-screen="express" data-step-panel="express" data-state="idle" data-testid="express-screen" hidden>
		<div class="express-container">
			<h2 class="step-heading" data-step-heading tabindex="-1"><?php esc_html_e( 'Express Article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
			<p class="step-subtitle"><?php esc_html_e( 'One topic in, a complete article out — a single AI call instead of eleven. Refine any part in the wizard afterwards.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
			<p class="visually-hidden" role="status" aria-live="polite" data-testid="step-status"></p>

			<div class="input-section">
				<div class="form-group">
					<label for="express-topic-input"><?php esc_html_e( 'Article topic or idea', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
					<div class="input-with-button">
						<input type="text" id="express-topic-input" class="form-control" data-testid="express-topic"
							placeholder="<?php esc_attr_e( 'e.g. How heat pumps cut home energy bills', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>">
						<button type="button" class="btn btn-primary generate-btn-inline" data-action="express-generate" data-testid="express-generate">
							<i data-lucide="zap" aria-hidden="true"></i>
							<?php esc_html_e( 'Generate Full Article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
						</button>
					</div>
					<div class="form-row article-length-override" data-testid="express-length-override">
						<div class="form-group">
							<label for="express-length-mode"><?php esc_html_e( 'Length for this article', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
							<select id="express-length-mode" class="form-control" data-article-length-mode>
								<option value="global"><?php esc_html_e( 'Use global default', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
								<option value="auto"><?php esc_html_e( 'Auto — planned from article scope', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
								<option value="concise"><?php esc_html_e( 'Concise — 800–1,100 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
								<option value="standard"><?php esc_html_e( 'Standard — 1,500–2,100 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
								<option value="in_depth"><?php esc_html_e( 'In-depth — 2,400–3,200 words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
								<option value="custom"><?php esc_html_e( 'Custom — choose a target', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></option>
							</select>
						</div>
						<div class="form-group" data-custom-word-count hidden>
							<label for="express-word-count"><?php esc_html_e( 'Custom target words', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></label>
							<input type="number" id="express-word-count" class="form-control" data-article-word-count min="400" max="8000" step="50" value="1800">
						</div>
					</div>
					<p class="form-hint article-plan-summary" data-article-plan-summary role="status" aria-live="polite"></p>
				</div>
			</div>

			<div class="express-progress-slot" data-testid="express-progress-slot"></div>
			<p class="visually-hidden" data-article-target-live role="status" aria-live="polite"></p>

			<div class="results-section hidden">
				<div class="article-target-status" data-article-target-status hidden>
					<div class="article-target-status-copy"><strong data-target-status-heading></strong><span data-target-status-detail></span></div>
					<div class="article-target-track" aria-hidden="true"><span data-target-status-bar></span></div>
					<div class="article-target-action" data-target-status-action hidden>
						<div><strong><?php esc_html_e( 'Keep this draft and improve its length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></strong>
						<p data-improve-length-status role="status" aria-live="polite" aria-atomic="true"><?php esc_html_e( 'AI-Scribe will extend thin sections without replacing the draft you can see.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p></div>
						<button type="button" class="btn btn-outline" data-action="express-improve-length" data-testid="express-improve-length">
							<i data-lucide="wand-2" aria-hidden="true"></i><span data-improve-length-label><?php esc_html_e( 'Improve length', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></span>
						</button>
					</div>
				</div>
				<article class="stream-output prose-output express-article" id="express-stream-output" data-testid="express-article" aria-live="off"></article>
				<div class="results-actions">
					<button type="button" class="btn btn-primary" data-action="express-refine" data-testid="express-refine" hidden>
						<i data-lucide="edit-3" aria-hidden="true"></i>
						<?php esc_html_e( 'Refine in Wizard', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
					</button>
				</div>
				<?php ai_scribe_render_save_status( 'express' ); ?>
			</div>
		</div>
	</div>

</div>
