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
	$u = new WP_User($pUserId);
	if( $u->ID != 0 ) {
		// transformer l'objet user en tableau.
		$cu = (array) $u;	
		// Les roles sur le blogs son dans un tableau nommé en fct du blogid.
		if ($cu["wp_".$pBlogId."_capabilities"]) return true;
	  }
	 return false;
}

// --------------------------------------------------------------------------------
// fonction qui renvoie true si l'utilisateur est administrateur de son domaine.
// --------------------------------------------------------------------------------
function hasRoleOnDomain($user, $pDom, $pRole){
	//global $user;
	$blogId = getBlogIdByDomain($pDom);

	// L'objet user doit être correctement initialisé
	//if (phpCAS::isAuthenticated()) {
	//	$user = get_userdatabylogin(phpCAS::getUser());
		if( $user->ID != 0 ) {
			// transformer l'objet user en tableau.
			$cu = (array) $user;			
			// Les roles sur le blogs son dans un tableau nommé en fct du blogid.
			$rolesSurCeBlog = $cu["wp_".$blogId."_capabilities"];
			// analyse de la valeur
			if ($rolesSurCeBlog[strtolower($pRole)] == "1") 
				return true;
		}
	//}
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

	//wp_enqueue_script( "ent-wp-managment-iframe-detect", "/wp-content/plugins/ent-wp-management/js/ent-wp-managment-iframe-detect.js");
	// Forcer l'affichage du modèle simplifié.
	add_filter('stylesheet', 'modeIntegreIframeENT');
	add_filter('template', 'modeIntegreIframeENT');
}



?>