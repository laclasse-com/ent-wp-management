<?php

// --------------------------------------------------------------------------------
// fonction création d'un nouvel article
// --------------------------------------------------------------------------------
function creerPremierArticle($domain, $wpBlogId, $pUserId, $pTypeBlog) {
	global $wp_error;
	
	switch ($pTypeBlog) {
		case 'CLS' : $libType = "de classe"; break;
		case 'ETB' : $libType = "d'&eacute;tablissement"; break;
		case 'GRP' : $libType = "de groupe"; break;
		case 'ENV' : $libType = "de groupe de travail"; break;
		case 'USR' : $libType = "personnel"; break;
		default : $libType = ""; break;
	}
	
	$texteArticle = "
	<p>Votre nouveau blog est h&eacute;berg&eacute; par le <a href='http://www.grandlyon.com/'>La M&eacute;tropole de Lyon</a>, 
	en lien avec votre ENT <a href='https://www.laclasse.com/'>https://www.laclasse.com/</a></p>
	<p>Cette plateforme est int&eacute;gr&eacute;e &agrave; l'ENT et partage donc le m&ecirc;me service d'authentification. 
	<u>Vous avez donc deux fa&ccedil;ons d'y acc&eacute;der</u> : <br/>
	<ul>
		<li>En vous connectant &agrave; l'ENT,</li>
		<li>En utilisant directement l'adresse <a href='http://".$domain."/'>http://".$domain."/</a></li>
	</ul><br/>";
	
	$texteArticle .="</p>
	<p>Vous avez tout le loisir de supprimer cet article en vous connectant sur 
	<a href='http://".$domain."/wp-admin/'>l'interface d'administration</a>.</p>";
	
	$post = array(
  		'ID' 				=> 0,				//Are you updating an existing post?
  		'comment_status'	=> 'open', 			// 'closed' means no comments.
  		'ping_status' 		=> 'closed',  		// 'closed' means pingbacks or trackbacks turned off
  		'post_author' 		=> $pUserId, 		//The user ID number of the author.
  		'post_content' 		=> $texteArticle,	//The full text of the post.
  		'post_status' 		=> 'publish', 		//Set the status of the new post. 
  		'post_title' 		=> "Bienvenue dans votre nouveau blog $libType", //The title of your post.
  		'post_type' 		=> 'post' 			//Sometimes you want to post a page.
	);  
	// insertion du post.
	switch_to_blog($wpBlogId);
	wp_insert_post( $post, $wp_error );
	
}

// --------------------------------------------------------------------------------
// fonction création d'un nouveau blog
// --------------------------------------------------------------------------------
function creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $EtbUAI, $ClsID ="", $GrpID="", $GplID="", $blogdescription = "") {
	global $wpError;

	$meta = new stdClass();
	$meta->type_de_blog = $TypeDeBlog;
	if ($EtbUAI)
		$meta->etablissement_ENT = $EtbUAI;
	if ($TypeDeBlog == 'CLS' && $ClsID)
		$meta->classe_ENT = $ClsID;
	if ($TypeDeBlog == 'GRP' && $GrpID)
		$meta->groupe_ENT = $GrpID;
	if ($TypeDeBlog == 'GPL' && $GplID)
		$meta->groupelibre_ENT = $GplID;
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
	
	// Creer un premier article publié qui parle de la reprise des données.
	creerPremierArticle($domain, $wpBlogId, $wpUsrId, $TypeDeBlog);
  	
	return $wpBlogId;
}
