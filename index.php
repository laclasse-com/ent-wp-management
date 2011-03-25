<?php
/*
Plugin Name: ent-wp-managment
Plugin URI: 
Description: <strong>Plugin Laclasse.com</strong>. Valable pour tout ENT s'appuyant sur CAS pour son sso. Pour l'int&eacute;gration de Wordpress dans un ENT. Gestion des utilisateurs d'un ENT pour WordPress Multi-Utilisateurs, Gestion des actions de pilotage des blogs depuis un ENT. <strong>Pr&eacute;-requis :</strong>Le plugin Akismet doit &ecirc;tre activ&eacute; pour le r&eacute;seau. 
Author: Pierre-Gilles Levallois
Version: 0.3
Author URI: http://www.laclasse.com/
*/

/*  Copyright 2010  Pgl  (email : pgl@erasme.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
// Fichier de configuration
require_once('ENTconfig.inc.php');
// fonctions gŽnŽriques
require_once('includes/functions.inc.php');
// Fonctions liŽes aux hooks WordPress
require_once('includes/hooks-functions.inc.php');
// Fonctions liŽes au pilotage d'action sur WordPress depuis l'ENT.
require_once('includes/pilotage-functions.inc.php');
// Fonctions liŽes ˆ la CASification de WordPress.
require_once('includes/cas-functions.inc.php');
// Fonctions de paramŽtrage du back-office des options du plugin.
require_once('includes/ENTback-office.php'); 

require_once(ABSPATH . WPINC . '/registration.php');
require_once(ABSPATH . WPINC . '/formatting.php');
require_once(ABSPATH . WPINC . '/wp-db.php');
require_once(ABSPATH . WPINC . '/pluggable.php');
require_once(ABSPATH . "wp-admin" . '/includes/image.php');
require_once(ABSPATH . WPINC . '/capabilities.php');
// fonctions MU
require_once(ABSPATH.'/wp-admin/includes/ms.php');


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	h o o k s   p o u r   l a   C A S i f i c a t i o n   d e   W o r p r e s s .

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// plugin hooks into authentication system
add_action('wp_authenticate', array('wpCAS', 'authenticate'), 10, 2);
add_action('wp_logout', array('wpCAS', 'logout'));
add_action('lost_password', array('wpCAS', 'disable_function_pwd'));
add_action('retrieve_password', array('wpCAS', 'disable_function_pwd'));
add_action('check_passwords', array('wpCAS', 'check_passwords'), 10, 3);
add_action('password_reset', array('wpCAS', 'disable_function_pwd'));
add_filter('show_password_fields', array('wpCAS', 'show_password_fields'));

// supprimer l'apparition du formulaire d'ajout d'un utilisateurs (connexion avec CAS).
add_action('show_adduser_fields', array('wpCAS', 'disable_function_user'));

add_filter('login_url',array('wpCAS', 'get_url_login'));

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	h o o k s   e t   f i l t r e s   g Ž n Ž r a u x 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// Ajout d'un texte perso dans le footer.
add_filter('admin_footer_text', 'addEntName', 10, 0);


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   u t i l i s a t e u r s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

add_filter('wpmu_users_columns', 'getUserCols', 10, 1);
add_filter('manage_users_custom_column', 'getCustomUserMeta', 10, 3);
add_filter('ENT_WP_MGMT_format_output', 'formatMeta', 10, 2);
add_filter('wp_print_scripts', 'addUsersManagmentScript', 10, 0);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   s i t e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

add_filter('wpmu_blogs_columns', 'getBlogsCols', 10, 0);
add_filter('manage_sites_custom_column', 'getCustomSiteMeta', 10, 2);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	f o n c t i o n s   d e   m o d i f i c a t i o n  d u   b l o g 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// Cette action est envoyŽe ˆ chaque mise ˆ jour d'une option. Il faut donc filtrer 
// par rapport ˆ la page du back-office en cours et aux noms des options.
add_action( 'update_option', 'synchroENT', 10, 3);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	s u p p r e s s i o n   d e   l a   n o t i f i c a t i o n 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_filter('wpmu_signup_blog_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_signup_user_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_user_notification', 'disableThisFunc', 10, 2);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	a c t i o n s   d i v e r s e s   d e   p i l o t a g e  

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// Contr™leur d'actions
// --------------------------------------------------------------------------------

if (isset($_REQUEST['ENT_action'])) {

	$ENT_action 		= $_REQUEST['ENT_action'];
	//$ENTblogid 			= $_REQUEST['ENTblogid'];
	$ENTblogid 			= $_REQUEST['pblogid'];
	$blogname 			= $_REQUEST['blogname'];
	$username 			= $_REQUEST['username'];
	$blogdescription 	= $_REQUEST['blogdescription'];
	$mustDieAfterAction = false;  // UtilisŽ pour es actions qui ne nŽcessite pas d'afficage aprs s'tre dŽroulŽes.
	
	switch ($ENT_action) {
	//
	// Se mettre en mode INTEGRE dans une IFRAME
	//
	case 'IFRAME' :
		setIframeTemplate();
		$mustDieAfterAction = false;	// Maintenant qu'on a ajoutŽ des filtres, on veut afficher le site.
		break;
	//
	// Tester l'existence d'un blog
	//
	case 'BLOG_EXISTS' :
		blogExists($blogname);	
		$mustDieAfterAction = true;
		break;
	//
	// Tester l'existence d'un utilisateur sur la plateforme WP.
	//
	case 'USER_EXISTS' :
		userExists($username);	
		$mustDieAfterAction = true;
		break;
	//
	// Logout de WP.
	//
	case 'LOGOUT' :	
		wpCas::logout();
		$mustDieAfterAction = true;
		break;
	//
	// Modifier les paramtres du blog dans Worpress et mettre ˆ jour dans l'ENT
	//
	case 'MODIFIER_PARAMS' :
		modifierParams($domain);	
		$mustDieAfterAction = true;
		break;
	//
	// Supprimer un ancien blog de l'ENT (aprs la repise des donnŽes en gŽnŽral)
	//
	/*
	case 'SUPPRIMER_ANCIEN_BLOG' :
		if(hasRoleOnDomain($domain, "administrator") || is_super_admin()) supprimerAncienBlogDansENT($ENTblogid);	
		else 
			message("Vous n'&ecirc;tes pas administrateur du site '$domain'.");
		$mustDieAfterAction = true;
		break;
	*/
	//
	// Supprimer un blog
	//
	case 'SUPPRIMER_BLOG' :
		supprimerBlog($domain);	
		$mustDieAfterAction = true;
		break;
	//
	// Migration des donnŽes de l'ancien blog.
	//
	case 'MIGRER_DATA' :
		
		if(hasRoleOnDomain($domain, "administrator") || is_super_admin()) 
			include_once('scripts/migrer_data_ENT.php');
		else 
			message("Vous n'&ecirc;tes pas administrateur du site '$domain'.");
		$mustDieAfterAction = true; // on va tre redirigŽ par le script de reprise, tranquillement.		
		break;
	//
	// Action par dŽfaut.
	//
	default  :
		echo "L'action $ENT_action n'est pas prise en charge.";
		$mustDieAfterAction = true;
		break;
	}
	
// ici, pour certaines actions, on veut juste piloter Wordpress avec nos actions Žmanant de l'ENT
// et ne rien afficher. La pluspart du temps il s'agit d'actions de mise ˆ jours  ou de provisionning.
//	Dans ces cas, on arrte tout traitement d'affichage si nŽcessaire. 
if ($mustDieAfterAction) die();
}
?>