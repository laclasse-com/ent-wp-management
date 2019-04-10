<?php

// --------------------------------------------------------------------------------
// fonction création d'un nouveau blog
// --------------------------------------------------------------------------------
function creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $EtbUAI, $group_id = "", $blogdescription = "") {
	global $wpError;

	// Create blog metas
	$meta = new stdClass();
	$meta->type_de_blog = $TypeDeBlog;
	if ($EtbUAI)
		$meta->etablissement_ENT = $EtbUAI;

	if (isset($group_id) && $group_id != '')
		$meta->group_id_ENT = $group_id;

	$meta->admin_email = $user_email;
	$meta->wordpress_api_key = AKISMET_KEY;

	$meta->blogname = $sitename;

	if (!empty($blogdescription))
		$meta->blogdescription = $blogdescription;
	$meta->users_can_register = 0;
	$meta->mailserver_url = 'localhost';
	$meta->rss_language = 'fr';
	$meta->language = 'fr';
	$meta->WPLANG = 'fr_FR';
	$meta->blog_upload_space = 1000;
	$meta->comment_registration = 1 ;

	// Disable pingbacks
	$meta->default_ping_status = 'closed';
	$meta->default_pingback_flag = '';

	$scheme = is_ssl() ? 'https' : 'http';
	$meta->home = $meta->siteurl = esc_url( $scheme . '://' . $domain . $path );

	$wpBlogId = wpmu_create_blog($domain, $path, $sitename, $wpUsrId, $meta, $site_id);

	// Ajout du role administrator sur le blog crée
	add_user_to_blog($wpBlogId, $wpUsrId, "administrator");

	// Change theme of created blog
	// Add new widget for Posts (Pages)
	$laclasseTheme = wp_get_theme('wordpress-theme-laclasse');
	if($laclasseTheme->exists()) {
		switch_to_blog($wpBlogId);
		switch_theme('wordpress-theme-laclasse');
		restore_current_blog();

		insert_widget_in_blog_sidebar('pages',array('sortby' => 'menu_order'), $wpBlogId,'sidebar-1');
	}

	return $wpBlogId;
}

/**
 * Insert a widget a sidebar, widget always inserted at index 1
 *
 * @param string $widget_id   ID of the widget (search, recent-posts, etc.)
 * @param array $widget_data  Widget settings.
 * @param int $wp_blog_id	  ID of the blog in which to add the widget
 * @param string $sidebar     ID of the sidebar.
 */
function insert_widget_in_blog_sidebar( $widget_id, $widget_data, $wp_blog_id ,$sidebar ) {
	// Retrieve sidebars, widgets and their instances
	$sidebars_widgets = get_blog_option($wp_blog_id, 'sidebars_widgets', array() );
	$widget_instances = get_blog_option($wp_blog_id, 'widget_' . $widget_id, array() );
	// Retrieve the key of the next widget instance
	$numeric_keys = array_filter( array_keys( $widget_instances ), 'is_int' );
	$next_key = $numeric_keys ? max( $numeric_keys ) + 1 : 2;
	// Add this widget to the sidebar
	if ( ! isset( $sidebars_widgets[ $sidebar ] ) ) {
		$sidebars_widgets[ $sidebar ] = array();
	}
	array_splice($sidebars_widgets[ $sidebar ], 1, 0, array($widget_id . '-' . $next_key));
	// Add the new widget instance
	$widget_instances[ $next_key ] = $widget_data;
	// Store updated sidebars, widgets and their instances
	update_blog_option($wp_blog_id, 'sidebars_widgets', $sidebars_widgets );
	update_blog_option($wp_blog_id, 'widget_' . $widget_id, $widget_instances );
}