<?php
/**
 * AI-Scribe v3 — Saved Shortcodes screen.
 *
 * Rebuilt in P5 (UAT §12.5): the legacy template emitted a full <html>
 * document inside wp-admin and referenced assets pruned in v3. This is a
 * proper admin fragment styled with the v3 token system.
 *
 * @package AI_Scribe
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$ai_scribe_table = $wpdb->prefix . 'article_builder';
$ai_scribe_rows  = array();
// The table exists on any install that activated 2.6.2 or v3; guard anyway.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only admin listing from the plugin-owned shortcode table.
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ai_scribe_table ) ) === $ai_scribe_table ) {
	$ai_scribe_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title FROM %i ORDER BY id DESC', $ai_scribe_table ) );
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
?>

<div class="wrap ai-scribe-app" id="ai-scribe-shortcodes-root" data-testid="shortcodes-root">
	<div class="page-brand">
		<img class="logo-image logo-image-large" src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/images/ai-scribe-logo.png' ); ?>"
			alt="" aria-hidden="true" width="72" height="72" data-testid="brand-logo">
		<h1><?php esc_html_e( 'Saved Shortcodes', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></h1>
	</div>

	<p class="form-hint">
		<?php esc_html_e( 'Each saved article template has a shortcode you can place in any post or page.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
	</p>

	<?php if ( empty( $ai_scribe_rows ) ) : ?>
		<div class="state-box empty-state" data-testid="shortcodes-empty">
			<p class="state-box-message"><?php esc_html_e( 'No shortcodes have been saved yet. Save an article from the wizard\'s Review step to create one.', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></p>
			<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ai-scribe' ) ); ?>">
				<?php esc_html_e( 'Open the Article Wizard', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="widefat striped ai-scribe-table" data-testid="shortcodes-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Title', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $ai_scribe_rows as $ai_scribe_row ) : ?>
					<tr>
						<td><?php echo esc_html( $ai_scribe_row->title ); ?></td>
						<td><code>[article_builder_generate_data template_id="<?php echo esc_attr( (string) $ai_scribe_row->id ); ?>"]</code></td>
						<td>
							<button type="button" class="btn btn-outline btn-danger delete" data-id="<?php echo esc_attr( (string) $ai_scribe_row->id ); ?>">
								<?php esc_html_e( 'Remove', 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
