<?php
// --------------------------------------------------------------------------------
//
// Fonctions de pilotage des actions sur WorPress par l'ENT.
//
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------------
//  Fonction qui renvoie le dernier blog_id créé par l'utilisateur en fct de son domaine
// --------------------------------------------------------------------------------
function getBlogIdByDomain( $domain ) {
	global $wpdb;
	$rowBlog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->blogs WHERE domain = %s AND spam = '0' AND deleted = '0' and archived = '0'", $domain)  );
	return $rowBlog->blog_id;
}

// --------------------------------------------------------------------------------
// fonction de controle de l'existence d'un blog. Service web appelé depuis l'ENT
// --------------------------------------------------------------------------------
function blogExists($pblogname) {
	if (domain_exists($pblogname, '/', 1)) echo "OK";
	else echo "NOK";
}

// --------------------------------------------------------------------------------
// fonction de controle de l'existence d'un utilisateur. Service web appelé depuis l'ENT
// --------------------------------------------------------------------------------
function userExists($pusername) {
	global $wpdb;
	$usrId = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_login = %s", strtolower($pusername))  );
	if (isset($usrId) && $usrId > 0) 
		echo "OK";
	else 
		echo "NOK";

}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de la plateforme, 
// avec les données relatives à l'ENT
/*
    [0] => Array
        (
            [blog_id] => 1
            [site_id] => 1
            [domain] => blogs.dev.laclasse.com
            [path] => /
            [registered] => 2011-04-26 13:26:05
            [last_updated] => 2014-02-24 14:47:30
            [public] => 1
            [archived] => 0
            [mature] => 0
            [spam] => 0
            [deleted] => 0
            [lang_id] => 0
        )
*/
// --------------------------------------------------------------------------------
function blogList() {
    global $wpdb;
    $blogs = wp_get_sites();
    $list = [];
    foreach ($blogs as $blog) {
        // Pas de détail sur la liste des nblogs d'un utilisateur
            $blog_details = $wpdb->get_results( "SELECT * ".  //option_name, option_value
                                                "FROM wp_". $blog['blog_id'] ."_options ".
                                               // "where option_name in ('admin_email', 'blogname', 'idBLogENT', 'type_blog', '') ".
                                                "order by option_name");
            foreach ($blog_details as $opt) {
                switch ($opt->option_name) {
                    case 'admin_email':
                        $blog['admin_email'] = $opt->option_value;
                        break;
                    case 'blogname':
                        $blog['blogname'] = $opt->option_value;
                        break;
                    case 'idBLogENT':
                        $blog['idBLogENT'] = $opt->option_value;
                        break;
                    case 'type_de_blog':
                        $blog['type_de_blog'] = $opt->option_value;
                        break;
                    case 'etablissement_ENT':
                        $blog['etablissement_ENT'] = $opt->option_value;
                        break;
                    
                    default:
                        break;
                }
            }
        $list[] = $blog;
    }
    echo json_encode($list);
}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de l'utilisateur
// --------------------------------------------------------------------------------
function userBlogList($username) {
    $user_id = username_exists($username);
    $blogs = get_blogs_of_user( $user_id );
    $list = [];
    foreach ($blogs as $blog) {
        $list[] = $blog;
    }
    echo json_encode($list);
}

// --------------------------------------------------------------------------------
// fonction de controle de l'intégration dans une Iframe.
// --------------------------------------------------------------------------------
function modeIntegreIframeENT() {	
	// utiliser le template "headless" spécial intégration dans l'ENT.
	return "headless";	
}

// --------------------------------------------------------------------------------
// fonction de modification des paramètres d'un blog.
// --------------------------------------------------------------------------------
function modifierParams($domain) {
	wp_redirect("http://$domain/wp-admin/options-general.php");
}

// --------------------------------------------------------------------------------
// fonction qui renvoie vrai si l'utilisateur a un role quelconque sur le blog donné.
// --------------------------------------------------------------------------------
function aUnRoleSurCeBlog($pUserId, $pBlogId){
  switch_to_blog($pBlogId);
  $res = is_blog_user($pBlogId);
  restore_current_blog();
  return $res;
}

// --------------------------------------------------------------------------------
// fonction qui renvoie true si l'utilisateur est administrateur de son domaine.
/*

Structure de l'objet WP_User une fois qu'on a switché sur le bon blog.

WP_User Object
(
    [data] => stdClass Object
        (
            [ID] => 99
            [user_login] => tests-unitaires-wp
            [user_pass] => $P$BUx4pmexHS38a2AJ.IuoXOvtetM2a7.
            [user_nicename] => tests-unitaires-wp
            [user_email] => tests-unitaires-wp@laclasse.com
            [user_url] => 
            [user_registered] => 2013-01-30 14:53:48
            [user_activation_key] => 
            [user_status] => 0
            [display_name] => tests-unitaires-wp
            [spam] => 0
            [deleted] => 0
        )

    [ID] => 99
    [caps] => Array
        (
            [author] => 1
        )

    [cap_key] => wp_125_capabilities
    [roles] => Array
        (
            [0] => author
        )

    [allcaps] => Array
        (
            [upload_files] => 1
            [edit_posts] => 1
            [edit_published_posts] => 1
            [publish_posts] => 1
            [read] => 1
            [level_2] => 1
            [level_1] => 1
            [level_0] => 1
            [delete_posts] => 1
            [delete_published_posts] => 1
            [author] => 1
        )

    [filter] => 
)
*/
// --------------------------------------------------------------------------------
function aLeRoleSurCeBlog($userId, $pBlogId, $role){
  $can = false;
  if ( is_multisite() ) {
    // D'abord swicher sur le bon blog, sinon rien ne fonctionne en terme de capabilities.
    switch_to_blog($pBlogId);
    // ensuite récupérer l'objet current_user, qui devrait être peuplé correctement
    $cu = (array) wp_get_current_user();
    // Le [cap_key] doit être égal à "wp_".$blogId."_capabilities"	
    if ($cu['cap_key'] =="wp_".$pBlogId."_capabilities") {  
      // Alors $cu[roles] donne le tableau des roles sur ce blog.
      if (in_array($role, $cu['roles'])) {
        $can = true;
      }
    }
    restore_current_blog();
    return $can;
  }
  return false;
}

// --------------------------------------------------------------------------------
// fonction qui permet de forcer l'usage d'un template simplifé pour 
// le mode "intégration dans l'ENT" en Iframe.
// --------------------------------------------------------------------------------
function setIframeTemplate() {
	wp_enqueue_script('jquery'); 
	// script de detection d'IFRAME qui ajoute le contexte de navigation à toutes les urls.
	$plugin_js_url = WP_PLUGIN_URL.'/ent-wp-management/js';
	wp_enqueue_script('wp_wall_script', $plugin_js_url.'/ent-wp-managment-iframe-detect.js');

	// Forcer l'affichage du modèle simplifié.
	add_filter('stylesheet', 'modeIntegreIframeENT');
	add_filter('template', 'modeIntegreIframeENT');
}



?>