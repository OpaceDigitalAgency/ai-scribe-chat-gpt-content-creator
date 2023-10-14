<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>AI-Scribe Shortcodes: ChatGPT SEO Content Creator</title>
</head>
<body>
<h1>
	Saved Shortcodes
</h1>
<table class="widefat fixed" cellspacing="0">
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
				     img-id= <?php echo esc_attr( $value->id ); ?> id="loader-img" style="display:none; width:20px;" / >
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
</body>