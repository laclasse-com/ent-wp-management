<?php
// --------------------------------------------------------------------------------
// Toutes les fonctions liées aux hooks et aux fitres WordPress.
// --------------------------------------------------------------------------------

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	hooks et filtres généraux

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// Proposer une selectbox de restriction par auteur sur la liste des articles
// filter : restrict_manage_posts
// http://www.geekpress.fr/wordpress/astuce/ajouter-filtre-auteur-administration-wordpress-563/
// --------------------------------------------------------------------------------
function restrict_manage_authors() {
  global $wpdb, $typenow;
  // On prepare la requete pour recuperer tous les auteurs qui ont publiés au moins 1 article
  $query = $wpdb->prepare( 'SELECT DISTINCT post_author
      FROM '. $wpdb->posts . '
      WHERE post_type = %s
  ', $typenow );

  // On recupere les id
  $users = $wpdb->get_col($query);

 // On génére le select avec la liste des auteurs
  wp_dropdown_users(array(
          'show_option_all'       => __('Voir tous les auteurs'),
          'show_option_none'      => false,
          'name'                  => 'author',
          'include'		            => $users,
          'selected'              => !empty($_GET['author']) ? (int)$_GET['author'] : 0,
          'include_selected'      => true
  ));

}
// --------------------------------------------------------------------------------
// Paramétrage de l'extension USER_ROLE_EDITOR si elle est installée.
// filter : admin_init
// http://www.shinephp.com/user-role-editor-wordpress-plugin/#faq
// --------------------------------------------------------------------------------
function user_role_editor_settings() {
    // Voir si user_role_editor est installé
    if (function_exists('ure_init') || function_exists('ure_install')) {
        // Voir quelle version est installée
        define("URE_ENABLE_SIMPLE_ADMIN_FOR_MULTISITE", 1); 
        define('URE_SHOW_ADMIN_ROLE', 0);
    } // user role editor n'est pas installé
}

// --------------------------------------------------------------------------------
// Suppression de l'éditeur de thème
// filter : admin_init
// http://www.geekpress.fr/wordpress/astuce/supprimer-sous-menu-editeur-theme-wordpress-615/
// --------------------------------------------------------------------------------
function remove_editor_menu() {
	remove_submenu_page( 'themes.php', 'theme-editor.php' );
}

// --------------------------------------------------------------------------------
// fonction pour ajouter la marque de l'ENT dans le footer du back-office.
// hook : admin_footer_text
// --------------------------------------------------------------------------------
function addEntName () {
	echo "Plateforme de blogs de <a href='".ENT_URL."' target='_blank'>".ENT_NAME."</a>.";
}

// --------------------------------------------------------------------------------
// Quelques filtres pour le back-office utilis� dans la connexion avec CAS
// --------------------------------------------------------------------------------
function disableThisFunc() {
	return false;
}

/*************************************************************************************
fonction remove_frame_options_header : Maitriser le paramètre de XSS dans les IFRAMES.
action : login_init, admin_init
*************************************************************************************/
function remove_frame_options_header() {
  header_remove("x-frame-options");
}

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   u t i l i s a t e u r s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

/*************************************************************************************
fonction getUserCols : modifier l'ent�te des colonnes de la liste des utilisateurs
					   pour ajouter des donn�es issues de l'ENT.
filter : wpmu_users_columns
*************************************************************************************/
function getUserCols($userCols){
	$customCols = array(
		'profil_ENT'  		=> __( 'profil ENT' ),
		'classe_ENT'  		=> __( 'classe'),
		'etablissement_ENT' => __( '&eacute;tablissement')
	);
	return array_merge($userCols, $customCols);
}

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   s i t e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

function formatTypeBlog($pBlogID, $pTypeBlog) {
	switch ($pTypeBlog) {
		case "CLS" : $LibTypeBlog = "Blog de classe"; $color = "chocolate"; break;
		case "GRP" : $LibTypeBlog = "Blog de groupe d'&eacute;l&egrave;ves"; $color = "lightgreen"; break;
		case "GPL" : $LibTypeBlog = "Blog de groupe de travail"; $color = "green"; break;
		case "ETB" : $LibTypeBlog = "Blog d'&eacute;tablissement"; $color = "saddlebrown"; break;
		case "ENV" : $LibTypeBlog = "Blog public"; $color = "green"; break;
		default	   :
			if ($pBlogID == 1)
				$LibTypeBlog = "<strong>Blog principal</strong>";
			else
				$LibTypeBlog = "inconnu...";
			$color = "red";  
			break;
	}
	return "<span style='color:".$color.";'>".$LibTypeBlog."</span>";
}

/*************************************************************************************
fonction getCustomSiteMeta : Fonction de récupération de la valeur des champs blogmeta.
filter : manage_blogs_custom_column
*************************************************************************************/
function getCustomSiteMeta($colName, $blogID) {
	$value = get_blog_option($blogID, $colName);
	echo ($colName == 'type_de_blog') ? formatTypeBlog($blogID, $value) : $value;
}

/*************************************************************************************
fonction getBlogsCols : modifier l'entête des colonnes de la liste des sites
					   pour ajouter des données issues de l'ENT.
filter : wpmu_blogs_columns
*************************************************************************************/
function getBlogsCols() {
	$blogname_columns = ( is_subdomain_install() ) ? __( 'Domain' ) : __( 'Path' );
	return array(
		'id'           => __( 'ID' ),
		'blogname'     => $blogname_columns,
		'lastupdated'  => __( 'Last Updated'),
		'registered'   => _x( 'Registered', 'site' ),
		'users'        => __( 'Users' ),
		'type_de_blog' => 'type de blog'
	);
}

/*************************************************************************************
fonction getCustomExtraInfoBlog : Fonction de récupération de la valeur des champs blogmeta.
filter : myblogs_options
*************************************************************************************/
function getCustomExtraInfoBlog($ignore, $user_blog) {
	if ( !is_string($user_blog) && $user_blog != 'global') {
		$typeBlog = get_blog_option($user_blog->userblog_id, 'type_de_blog');
		echo "<div style='text-align:right;padding-right:40px;'>".formatTypeBlog($user_blog->userblog_id, $typeBlog)."</div>";
	}
}
