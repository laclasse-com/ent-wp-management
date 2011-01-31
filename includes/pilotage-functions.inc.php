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
// fonction de suppression d'un blog.
// --------------------------------------------------------------------------------
function supprimerBlog($domain) {
	$blogId = getBlogIdByDomain($domain);
	if (!$blogId) {
		echo "L'identifiant de '$domain' n'a pas &eacute;t&eacute; trouv&eacute;. Ce blog existe-t-il ?";
		exit;
	}
	else {
		switch_to_blog($blogId);
		if(hasRoleOnDomain($domain, "administrator") || is_super_admin())  {
			wpmu_delete_blog ($blogId, true);
			message("Le blog '$domain' a &eacute;t&eacute; supprim&eacute;.");
		}
		else message("Vous n'&ecirc;tes pas administrateur du blog '$domain', vous ne pouvez pas le supprimer.");
	}
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
// fonction qui renvoie true si l'utilisateur est administrateur de son domaine.
// --------------------------------------------------------------------------------
function hasRoleOnDomain($pDom, $pRole){
	global $current_user;
	$blogId = getBlogIdByDomain($pDom);
	// transformer l'objet current_user en tableau.
	$cu = (array) $current_user;
	// Les roles sur le blogs son dans un tableau nommé en fct du blogid.
	$rolesSurCeBlog = $cu["wp_".$blogId."_capabilities"];
	// analyse de la valeur
	if ($rolesSurCeBlog[strtolower($pRole)] == "1") 
		return true;
	return false;
}

// --------------------------------------------------------------------------------
// fonction qui permet de forcer l'usage d'un template simplifé pour 
// le mode "intégration dans l'ENT" en Iframe.
// --------------------------------------------------------------------------------
function setIframeTemplate() {
	wp_enqueue_script('jquery'); 
	// script de detection d'IFRAME qui ajoute le contexte de navigation à toutes les urls.
	wp_enqueue_script( "ent-wp-managment-iframe-detect", "/wp-content/plugins/ENT-WP-management/js/ENT-WP-managment-iframe-detect.js");
	// Forcer l'affichage du modèle simplifié.
	add_filter('stylesheet', 'modeIntegreIframeENT');
	add_filter('template', 'modeIntegreIframeENT');
}



?>