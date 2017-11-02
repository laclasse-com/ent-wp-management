<?php

// --------------------------------------------------------------------------------
// fonction création d'un nouveau blog
// --------------------------------------------------------------------------------
function creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $EtbUAI, $group_id = "", $blogdescription = "") {
	global $wpError;

	$meta = new stdClass();
	$meta->type_de_blog = $TypeDeBlog;
	if ($EtbUAI)
		$meta->etablissement_ENT = $EtbUAI;

	if (isset($group_id) && $group_id != '')
		$meta->group_id_ENT = $group_id;

	$meta->admin_email = $user_email;
	$meta->wordpress_api_key = AKISMET_KEY;

	$wpBlogId = wpmu_create_blog($domain, $path, $sitename, $wpUsrId, $meta, $site_id);
    	
	// Ajout du role administrator sur le blog crée
	add_user_to_blog($wpBlogId, $wpUsrId, "administrator");
	
	update_blog_option($wpBlogId, 'blogname', $sitename);

	if (!empty($blogdescription))
		update_blog_option($wpBlogId, 'blogdescription', $blogdescription);

	update_blog_option($wpBlogId, 'users_can_register', 0);
	
	update_blog_option($wpBlogId, 'mailserver_url', 'localhost');

	update_blog_option($wpBlogId, 'rss_language', 'fr');

	update_blog_option($wpBlogId, 'language', 'fr');
	update_blog_option($wpBlogId, 'WPLANG', 'fr_FR');

	update_blog_option($wpBlogId, 'blog_upload_space', 300);

	update_blog_option($wpBlogId, 'comment_registration', 1 );
	
	return $wpBlogId;
}
