<?php
/**
 * Shared Opace footer for AI-Scribe admin screens.
 *
 * @package AI_Scribe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the same useful related links after every AI-Scribe admin page.
 */
class AI_Scribe_Opace_Footer {

	/**
	 * Render the footer card.
	 *
	 * @return void
	 */
	public static function render() {
		?>
		<div class="wrap ai-scribe-opace-footer-wrap">
			<section class="ai-scribe-opace-footer" aria-labelledby="ai-scribe-more-from-opace">
				<p class="ai-scribe-opace-footer__byline">
					<?php
					printf(
						/* translators: %s: Link to the Opace website. */
						wp_kses_post( __( 'Built and supported by %s, a UK digital agency.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) ),
						'<a href="' . esc_url( 'https://opace.agency/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Opace', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ) . '</a>'
					);
					?>
				</p>
				<h2 id="ai-scribe-more-from-opace"><?php esc_html_e( 'More from Opace', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
				<ul class="ai-scribe-opace-footer__links">
					<?php
					self::render_link(
						'https://wordpress.org/plugins/opace-ai-prompt-library-api-hub/',
						__( 'Opace AI Hub — free WordPress plugin', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'dashicons-wordpress'
					);
					self::render_link(
						'https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator',
						__( 'AI-Scribe source code — GitHub', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'dashicons-editor-code'
					);
					self::render_link(
						'https://chatgpt.com/g/g-ZTkBnCIbA-gpt-seo-article-creator-writer-ai-scribe',
						__( 'AI-Scribe Custom GPT — ChatGPT', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'dashicons-format-chat'
					);
					self::render_link(
						'https://opace.agency/services/web-design/',
						__( 'Custom web design & WordPress support', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ),
						'dashicons-admin-site-alt3'
					);
					?>
				</ul>
			</section>
		</div>
		<?php
	}

	/**
	 * Render one external destination.
	 *
	 * @param string $url  Destination URL.
	 * @param string $text Visible link text.
	 * @param string $icon Dashicon class.
	 * @return void
	 */
	private static function render_link( $url, $text, $icon ) {
		?>
		<li>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $text ); ?></span>
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>
		</li>
		<?php
	}
}
