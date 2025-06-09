<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>AI-Scribe Shortcodes: ChatGPT SEO Content Creator</title>
	<link rel="stylesheet" href="<?php echo esc_url( AI_SCRIBE_URL . 'assets/css/article_builder.css' ); ?>">
	<style>
		body { margin: 0; padding: 0; background: #f1f1f1; }
		.shortcodes-wrapper { background: white; min-height: 100vh; width: 100%; }
		.shortcodes-container { width: 100%; max-width: none; margin: 0; padding: 0; }
	</style>
</head>
<body>
<div class="shortcodes-wrapper">
<div class="shortcodes-container">
<!-- Consistent header across all pages -->
<div class="ai-scribe-header">
	<div class="logo-container">
		<img class="opace-logo-compact"
		     src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/2023/03/AI-Scribe-Logo-simplified-80x80.png' ) ?>"
		     alt="AI-Scribe Logo">
		<div class="brand-info">
			<h1 class="brand-name">AI-Scribe</h1>
			<span class="version-badge">v<?php echo AI_SCRIBE_VER; ?></span>
		</div>
	</div>
</div>

<!-- Navigation menu -->
<div class="header-main">
	<div class="temp-progress-bar">
		<div class="step" onclick="document.location.href='./admin.php?page=ai_scribe_generate_article'">
			<p>Generate Article</p>
		</div>
		<div class="step" onclick="document.location.href='./admin.php?page=ai_scribe_settings'">
			<p>Settings</p>
		</div>
		<div class="step" onclick="document.location.href='./admin.php?page=ai_scribe_help'">
			<p>Help</p>
		</div>
		<div class="step active_step">
			<p>Saved Shortcodes</p>
		</div>
	</div>
</div>

<div class="shortcodes-content" style="padding: 30px; margin: 0 auto; width: 100%; max-width: 1200px;">
	<h1 class="page-title" style="text-align: center; margin-bottom: 30px;">Saved Shortcodes</h1>
	<table class="widefat fixed modern-table" cellspacing="0" style="width: 100%; max-width: none; margin: 0 auto;">
	<thead>
	<?php
	global $wpdb;
	$table_name = $wpdb->prefix . 'article_builder';
	$post_data  = $wpdb->get_results( "SELECT id,title,heading from $table_name ORDER BY id DESC " );

	$page = sanitize_text_field($_GET['page']);
	$current_page        = admin_url( "admin.php?page=" . $page );
	$current_page_edit   = '&action=edit&id=';
	$current_page_delete = '&action=delete&id=';
	if ( ! $post_data == null ) {
	?>
	<tr>
		<th id="columnname" class="manage-column column-cb check-column" scope="col"><h4>Title</h4></th>
		<th id="columnname" class="manage-column column-cb check-column" scope="col"><h4>Shortcode</h4></th>
		<th id="columnname" class="manage-column column-cb check-column" scope="col"><h4>Action</h4></th>
	</tr>
	</thead>
	<tbody>
	<?php
	foreach ( $post_data as $key => $value ) {
		?>
		<tr class="alternate">
			<td class="column-columnname"><?php echo esc_attr( $value->title ); ?></td>
			<td class="column-columnname">[article_builder_generate_data
				template_id="<?php echo esc_attr( $value->id ); ?>"]
			</td>
			<td>
				<button class="btn btn-danger delete" data-id= <?php echo esc_attr( $value->id ); ?>>Remove</button>
				<img src="<?php echo esc_url( AI_SCRIBE_URL . 'assets/loder.gif' ); ?>"
				     img-id="<?php echo esc_attr( $value->id ); ?>" id="loader-img-<?php echo esc_attr($value->id); ?>" style="display:none; width:20px;" / >
			</td>
		</tr>
		<?php
	}
	} else {
		/* update 24.03.23 */
		?>
		<br/><br/>No shortcodes have been saved.
	<?php 
	}
	?>
	</tbody>
</table>
</div>
</div> <!-- Close shortcodes-container -->
</div> <!-- Close shortcodes-wrapper -->
</body>
<script>
// Add HTML closing tag if missing
</script>
</html>