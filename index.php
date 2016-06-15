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
// fonctions gÈnÈriques
require_once('includes/functions.inc.php');
// Fonctions liÈes aux hooks WordPress
require_once('includes/hooks-functions.inc.php');
// Fonctions liÈes au pilotage d'action sur WordPress depuis l'ENT.
require_once('includes/pilotage-functions.inc.php');
// Fonctions liÈes ‡ la CASification de WordPress.
require_once('includes/cas-functions.inc.php');
// Fonctions de paramÈtrage du back-office des options du plugin.
require_once('includes/ENTback-office.php'); 
// Fonctions de signature des requetes
require_once('includes/signature-functions.php'); 

require_once(ABSPATH . WPINC . '/registration.php');
require_once(ABSPATH . WPINC . '/formatting.php');
require_once(ABSPATH . WPINC . '/wp-db.php');
require_once(ABSPATH . WPINC . '/pluggable.php');
require_once(ABSPATH . "wp-admin" . '/includes/image.php');
require_once(ABSPATH . WPINC . '/capabilities.php');
// fonctions MU
require_once(ABSPATH.'/wp-admin/includes/ms.php');


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	ParamÈtrage des assertion : rendre l'assertion silencieuxse 
	pour gÈrer une erreur perso.

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_QUIET_EVAL, 1);
assert_options(ASSERT_CALLBACK, 'message_erreur_assertion');

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
add_action('show_network_site_users_add_new_form', array('wpCAS', 'disable_function_user'));

add_filter('login_url',array('wpCAS', 'get_url_login'));
add_filter('logout_url',array('wpCAS', 'get_url_logout'));

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	h o o k s   e t   f i l t r e s   g È n È r a u x 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// ajouter le role ‡ cÙtÈ du nom ‡ la place de "Howdy"
add_filter( 'admin_bar_menu', 'bienvenue');
// Ajout d'un texte perso dans le footer.
add_filter('admin_footer_text', 'addEntName', 10, 0);
// Marquage MinistÈriel
add_action('wp_footer', 'xiti_MEN_et_google', 10, 0);

// Maîtriser les headers http qui sont envoyÈs
add_action( 'login_init', 'remove_frame_options_header',12, 0 );
add_action( 'admin_init', 'remove_frame_options_header', 12, 0 );

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
// liste des blogs de l'utilisateur
add_filter('myblogs_options', 'getCustomExtraInfoBlog', 10, 2);
add_filter('myblogs_blog_actions', 'getCustomActionBlog', 10, 2);
// Hook pour la dÈsinscription d'un blog.
add_action( 'myblogs_allblogs_options', 'actionsBlog', 10, 0);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   a r t i c l e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// selectbox pour rÈduire la liste par auteur
add_action('restrict_manage_posts', 'restrict_manage_authors');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	f o n c t i o n s   d e   m o d i f i c a t i o n  d u   b l o g 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// Cette action est envoyÈe ‡ chaque mise ‡ jour d'une option. Il faut donc filtrer 
// par rapport ‡ la page du back-office en cours et aux noms des options.
add_action( 'update_option', 'synchroENT', 10, 3);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	s u p p r e s s i o n   d e   l a   n o t i f i c a t i o n 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_filter('wpmu_signup_blog_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_signup_user_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_user_notification', 'disableThisFunc', 10, 2);


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	s È c u r i s a t i o n   d e   l a   p l a t e f o r m e 

http://www.geekpress.fr/wordpress/guide/7-conseils-securite-wordpress-802/
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// Supprimer la version de WP dans l'entête publique
remove_action('wp_head', 'wp_generator');
// Supprimer l'accès ‡ la modification des tËhèmes : editeur de thème
add_action( 'admin_init', 'remove_editor_menu', 20);
add_action( '_admin_menu', 'user_role_editor_settings', 25);


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	h o o k s   p o u r   a j o u t e r   d e s   a p i   ‡  R E S T _ A P I .

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_action( 'rest_api_init', function () {
    // register_rest_route( 'ent-wp-management/v1', '/blog/([A-z0-9])/subscribe/(?P<id>\d+)', array(
    register_rest_route( 'ent-wp-management/v1', '/blog/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'blog_subscribe',
    ) );
} );

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	a c t i o n s   d i v e r s e s   d e   p i l o t a g e  

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// ContrÙleur d'actions
// --------------------------------------------------------------------------------

if (isset($_REQUEST['ENT_action'])) {

	$ENT_action 		= $_REQUEST['ENT_action'];
	//$ENTblogid 			= $_REQUEST['ENTblogid'];
	$ENTblogid 			= $_REQUEST['pblogid'];
	$blogname 			= $_REQUEST['blogname'];
	$blogtype 			= $_REQUEST['blogtype'];
	$username			= $_REQUEST['username'];
	$uid				= $_REQUEST['uid'];
	$uid_admin			= $_REQUEST['uid_admin'];
	$signature 			= $_REQUEST['signature'];
	$blogdescription 	= $_REQUEST['blogdescription'];
	$mustDieAfterAction = false;  // UtilisÈ pour les actions qui ne nÈcessitent pas d'affichage aprËès s'êÍtre dÈroulÈes.
	
	switch ($ENT_action) {
	//
	// Se mettre en mode INTEGRE dans une IFRAME
	//
	case 'IFRAME' :
		setIframeTemplate();
		$mustDieAfterAction = false;	// Maintenant qu'on a ajoutÈ des filtres, on veut afficher le site.
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
	// Renvoie l'ID WP d'un blog identifiÈ par son nom.
	// ?ENT_action=BLOG_ID
	case 'BLOG_ID' :
		$t = Array();
		$t['id'] = getBlogIdByDomain($blogname);
		header('Content-Type: application/json');	
		echo json_encode($t);
		$mustDieAfterAction = true;
		break;
	//
	// Liste des blogs de la plateforme
	// ?ENT_action=BLOG_LIST
	case 'BLOG_LIST' :
		header('Content-Type: application/json');
		echo json_encode(blogList());	
		$mustDieAfterAction = true;
		break;

	//
	// Liste des blogs de la plateforme
	// ?ENT_action=USER_BLOG_LIST&username=[login]
	case 'USER_BLOG_LIST' :
		header('Content-Type: application/json');
		echo json_encode(userBlogList($username));	
		$mustDieAfterAction = true;
		break;

	//
	// Logout de WP.
	//
	case 'LOGOUT' :	
		global $current_user;
		if (phpCAS::isAuthenticated()) {
			$current_user = get_user_by('login',phpCAS::getUser());
			$urlLogOut = htmlspecialchars_decode(wp_logout_url());
			header('Location: '.$urlLogOut);
		}
		$mustDieAfterAction = true;
		break;

	//
	// Modifier les paramètres du blog dans Worpress et mettre ‡ jour dans l'ENT
	//
	case 'MODIFIER_PARAMS' :
		modifierParams($domain);	
		$mustDieAfterAction = true;
		break;

		//
		// inscription d'un blog.
		// Cette action est normalement gÈrÈe en HOOK pour le Back-office, mais
		// peut aussi s'appeler ‡ distance, d'o˘ sa prÈsence dans ce controleur.
		//

	case 'INSCRIRE' :
		// --------------------------------------------------------------------------------
		// On suppose que le compte a dÈj‡ ÈtÈ provisionnÈ, ET l'utilisateur est connectÈ
		// wp_get_current_user() est donc renseignÈ
		// - Il faut vÈrifier que l'utilisateur a le droit de s'inscrire
		// Si blog ETB => UAI utilisateur == UAI 
		// Si Blog de classe Classe utilisateur == classe_ENT pour les ELEVE
		// Si BLog de groupe Groupe utilisateur == Groupe_ENT pour les ELEVE
		// --------------------------------------------------------------------------------
		$inscrire = false;
		$status = "error";
		$message_retour = "";

		assert('$blogname != ""', "Le paramËtre \$blogname doit Ítre renseignÈ.");

		$current_user = wp_get_current_user();
		// VÈrifier si l'utilisateur est bien connectÈ
		assert ('$current_user->ID  != ""', "L'utilisateur n'existe pas sur la plateforme WordPress de laclasse.com.");

		// RÈcupÈration des champs meta de l'utilisateur 
		$userMeta = get_user_meta($current_user->ID);
		assert ('$userMeta[\'profil_ENT\'][0] != ""', "Cet utilisateur n'a pas de profil sur la plateforme WordPress de laclasse.com.");

		$uid_ent =  $userMeta['uid_ENT'][0];
		$profil_ent = $userMeta['profil_ENT'][0];
		$uai_user = $userMeta['etablissement_ENT'][0];
		// $classe_user = $userMeta['classe_ENT'][0];

		// RÈcupÈration des dÈtails sur le blog
		$blogid = getBlogIdByDomain($blogname.".".BLOG_DOMAINE);
		assert ('$blogid != ""', "Le blog '$blogname.".BLOG_DOMAINE."' n'existe pas.");

		$uai_blog =  get_blog_option($blogid, "etablissement_ENT");
		$classe_ent = get_blog_option($blogid, "classe_ENT");
		$groupe_ent = get_blog_option($blogid, "groupe_ENT");
		$type_de_blog = get_blog_option($blogid, "type_de_blog");
		assert('$type_de_blog != ""', "Le paramËtre \$blogtype doit Ítre renseignÈ.");

		// Interrogation de l'annuaireV3 de l'ENT
		$userENT =json_decode(get_http(generate_url(ANNUAIRE_URL."api/app/users/$uid_ent", Array("expand" => "true"))));

		// DÈterminer le role WordPress de l'utilisateur en fonction de son role ENT.
		$role_wp = get_WP_role_from_ent_profil($profil_ent, false);

		// Traiter tous les cas d'inscription en fonction du type de blog
		switch ($type_de_blog) {
			case "ETB":
				if($uai_blog == $uai_user) {
					$inscrire = true;
					$message_retour = "Inscription de l'utilisateur $current_user->display_name ($profil_ent / $uid_ent) ".
				 					  "au blog de son Ètablissement $blogname.".BLOG_DOMAINE;
				}
				break;
			
			case "CLS":
				foreach($userENT->classes as $c) {
					if ($c->classe_id == $classe_ent) {
						$inscrire = true;
						$message_retour = "Inscription de l'utilisateur $current_user->display_name ($profil_ent / $uid_ent) ".
				 					  "au blog de sa classe $blogname.".BLOG_DOMAINE;
					break;
					} else {
						$message_retour = "Vous ne pouvez pas vous inscrire sur ce blog de classe.";
					}
				}
				break;
			
			case "GRP":
				foreach($userENT->groupes_eleves as $g) {
					if ($g->groupe_id == $groupe_ent) {
						$inscrire = true;
						$message_retour = "Inscription de l'utilisateur $current_user->display_name ($profil_ent / $uid_ent) ".
				 					  "au blog de sa groupe $blogname.".BLOG_DOMAINE;
					break;
					} else {
						$message_retour = "Vous ne pouvez pas vous inscrire sur ce blog de groupe.";
					}
				}
				break;
			
			case "ENV":
				// Tout le monde peut s'inscrire, avec un profil contributeur, 
				// car les droits doivent Ítre dÈlÈguÈs par le propiÈtaire du blog (Plus de structure Ètablissement ici)
				$role_wp = "contributor";
				$message_retour = "Inscription de l'utilisateur $current_user->display_name ($profil_ent / $uid_ent) ".
								  "au blog partagÈ $blogname.".BLOG_DOMAINE;
				$inscrire = true;
				break;
			
			default:
				// Pas d'inscription
				$message_retour = "Pas d'inscription, type de blog inconnu";
				$status = "error";
				break;
		}


		if ($inscrire) {
			add_user_to_blog($blogid, $current_user->ID, $role_wp);
			$status = "success";
			$message_retour .= ", role '$role_wp'";
		}

		header('Content-Type: application/json');
    	echo '{ "'.$status.'" :  "'.str_replace('"', "'", $message_retour).'" }';
		$mustDieAfterAction = true;
		break;
	// --------------------------------------------------------------------------------
	// --------------------------------------------------------------------------------

		//
		// Desinscription d'un blog.
		// Cette action est normalement gÈrÈe en HOOK pour le Back-office, mais
		// peut aussi s'appeler ‡ distance, d'o˘ sa prÈsence dans ce controleur.
		//
	case 'DESINSCRIRE' :
		global $current_user;

		if (phpCAS::isAuthenticated()) {
			$current_user = get_user_by('login',phpCAS::getUser());
		} else phpCAS::forceAuthentication();
		$_REQUEST["action"] = $ENT_action;
		$_REQUEST['blogid'] = getBlogIdByDomain($blogname);
		actionsBlog();
		$mustDieAfterAction = true;
		break;

	//
	// Supprimer un blog
	//
	case 'SUPPRIMER_BLOG' :
		if (phpCAS::isAuthenticated()) {
			$user = get_user_by('login',phpCAS::getUser());
		} else phpCAS::forceAuthentication();

		$blogId = getBlogIdByDomain($domain);
		if (!$blogId) {
			echo "L'identifiant de '$domain' n'a pas &eacute;t&eacute; trouv&eacute;. Ce blog existe-t-il ?";
			exit;
		}
		else {
			if(aLeRoleSurCeBlog($user, $blogId, "administrator") || is_super_admin())  {
				wpmu_delete_blog ($blogId, true);	
				message("Le blog '$domain' a &eacute;t&eacute; supprim&eacute;.");
			}
			else message("Vous n'&ecirc;tes pas administrateur du blog '$domain'.");
		}
		$mustDieAfterAction = true;
		break;
	//
	// Action par dÈfaut.
	//
	default  :
		echo "L'action $ENT_action n'est pas prise en charge.";
		$mustDieAfterAction = true;
		break;
	}
	
// ici, pour certaines actions, on veut juste piloter Wordpress avec nos actions Èmanant de l'ENT
// et ne rien afficher. La pluspart du temps il s'agit d'actions de mise ‡ jours  ou de provisionning.
//	Dans ces cas, on arrête tout traitement d'affichage si nÈcessaire. 
if ($mustDieAfterAction) die();
}

?>