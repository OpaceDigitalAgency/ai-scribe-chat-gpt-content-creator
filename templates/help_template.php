<?php
/**
 * AI-Scribe v3 — Help screen (UAT §12.5: previously empty).
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ai-scribe-app" id="ai-scribe-help-root" data-testid="help-root">
	<div class="page-brand">
		<img class="logo-image logo-image-large" src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/images/ai-scribe-logo-320.png' ); ?>"
			alt="" aria-hidden="true" width="72" height="72" data-testid="brand-logo">
		<h1><?php esc_html_e( 'AI-Scribe Help', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h1>
	</div>

	<section class="help-section">
		<h2><?php esc_html_e( 'Getting started', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<ol>
			<li>
				<?php if ( function_exists( 'ai_core' ) || class_exists( 'AI_Core' ) ) : ?>
					<?php esc_html_e( 'Provider API keys are managed centrally by the AI-Core plugin (AI-Core → Settings) and shared by every AI-Core add-on. On WordPress 7.0+ you can also route through the core WordPress AI credentials.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Add at least one provider API key under Settings → Providers & Model (OpenAI, Anthropic or Google Gemini). On WordPress 7.0+ you can also route through the core WordPress AI credentials.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
				<?php endif; ?>
			</li>
			<li><?php esc_html_e( 'Pick a model — the list is fetched live from your configured providers and refreshed hourly (or on demand with Refresh models).', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
			<li><?php esc_html_e( 'Open Generate Article and enter your topic. The 11-step wizard walks from titles to a published post; Express mode produces a complete article from a single request.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></li>
		</ol>
	</section>

	<section class="help-section">
		<h2><?php esc_html_e( 'The 11 wizard steps', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p><?php esc_html_e( 'Titles → Keywords → Outline → Introduction → Tagline → Article Body → Conclusion → Q&A (optional) → SEO Meta → Review & Edit → Evaluate. Every step sees the full article so far — conclusions, Q&A and meta are written with complete context, and every response is stored server-side so a page reload never loses work or re-bills tokens.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
	</section>

	<section class="help-section">
		<h2><?php esc_html_e( 'Humanizer modes', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p><?php esc_html_e( 'Under Settings → Generation → Writing mode you can choose Standard, Humanizer, or Humanizer with Personality — the same instruction sets as 2.6. Humanizer rewrites the system instructions so the output reads like natural human writing: direct address, varied sentence and paragraph lengths, conversational flow and deliberate imperfections. Humanizer with Personality layers on a witty, sarcastic, opinionated edge. Both apply to every wizard step and to Express mode, and combine with your own Global Instructions from the Prompt Library.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
	</section>

	<section class="help-section">
		<h2><?php esc_html_e( 'Costs', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p><?php esc_html_e( 'The header cost meter shows the running spend for the current article. Conversation threading with prompt caching means resent context is billed at a fraction of fresh input on supported providers, and Express mode produces a whole article for roughly the cost of one call.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
	</section>

	<section class="help-section">
		<h2><?php esc_html_e( 'SEO plugins', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p><?php esc_html_e( 'Generated meta titles and descriptions are written to Yoast SEO, All in One SEO, Rank Math and SEOPress automatically when one of them is active, and always stored with the post so nothing is lost.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
	</section>

	<section class="help-section">
		<h2><?php esc_html_e( 'Support', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h2>
		<p>
			<?php esc_html_e( 'Opace Digital Agency — ', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			<a href="https://opace.agency" target="_blank" rel="noopener">opace.agency</a> ·
			<a href="https://wordpress.org/support/plugin/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/" target="_blank" rel="noopener"><?php esc_html_e( 'Support forum', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></a>
		</p>
	</section>
</div>
