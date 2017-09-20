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
// fonctions génériques
require_once('includes/functions.inc.php');
// Fonctions liées aux hooks WordPress
require_once('includes/hooks-functions.inc.php');
// Fonctions liées au pilotage d'action sur WordPress depuis l'ENT.
require_once('includes/pilotage-functions.inc.php');
require_once('includes/provisionning-functions.inc.php');
// Fonctions liées à la CASification de WordPress.
require_once('includes/cas-functions.inc.php');
// Fonctions de paramétrage du back-office des options du plugin.
require_once('includes/ENTback-office.php'); 
// API JSON d'administration
require_once('includes/api.php'); 

//require_once(ABSPATH . WPINC . '/formatting.php');
//require_once(ABSPATH . WPINC . '/wp-db.php');
require_once(ABSPATH . WPINC . '/pluggable.php');
//require_once(ABSPATH . "wp-admin" . '/includes/image.php');
//require_once(ABSPATH . WPINC . '/capabilities.php');
//require_once(ABSPATH . '/wp-admin/includes/user.php');
// fonctions MU
require_once(ABSPATH . '/wp-admin/includes/ms.php');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	Paramétrage des assertion : rendre l'assertion silencieuxse 
	pour gérer une erreur perso.

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
	
	h o o k s   e t   f i l t r e s   g é n é r a u x 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// Ajout d'un texte perso dans le footer.
add_filter('admin_footer_text', 'addEntName', 10, 0);

// Maîtriser les headers http qui sont envoyés
add_action( 'login_init', 'remove_frame_options_header',12, 0 );
add_action( 'admin_init', 'remove_frame_options_header', 12, 0 );

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   u t i l i s a t e u r s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

add_filter('wpmu_users_columns', 'getUserCols', 10, 1);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   s i t e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

add_filter('wpmu_blogs_columns', 'getBlogsCols', 10, 0);
add_filter('manage_sites_custom_column', 'getCustomSiteMeta', 10, 2);
// liste des blogs de l'utilisateur
add_filter('myblogs_options', 'getCustomExtraInfoBlog', 10, 2);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   a r t i c l e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// selectbox pour réduire la liste par auteur
add_action('restrict_manage_posts', 'restrict_manage_authors');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	s u p p r e s s i o n   d e   l a   n o t i f i c a t i o n 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_filter('wpmu_signup_blog_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_signup_user_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_user_notification', 'disableThisFunc', 10, 2);


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	s é c u r i s a t i o n   d e   l a   p l a t e f o r m e 

http://www.geekpress.fr/wordpress/guide/7-conseils-securite-wordpress-802/
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// Supprimer la version de WP dans l'entête publique
remove_action('wp_head', 'wp_generator');
// Supprimer l'accès à la modification des thèmes : editeur de thème
add_action( 'admin_init', 'remove_editor_menu', 20);
add_action( '_admin_menu', 'user_role_editor_settings', 25);


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	h o o k s   p o u r   a j o u t e r   d e s   a p i   R E S T _ A P I .

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_action( 'rest_api_init', function () {
	// Add admin JSON API available at /wp-json/api/...
    register_rest_route( 'api', '/(?P<path>.*)', array(
		'methods' => array('GET', 'POST', 'PUT', 'DELETE'),
		'callback' => 'wp_rest_laclasse_api_handle_request'
    ));
} );

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	a c t i o n s   d i v e r s e s   d e   p i l o t a g e  

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// Contrôleur d'actions
// --------------------------------------------------------------------------------

if (isset($_REQUEST['ENT_action'])) {

	if (!array_key_exists("LACLASSE_AUTH", $_COOKIE)) {
		wpCAS::authenticate();
	}

	// get the current session
	$error; $status;
	$session = get_http(ANNUAIRE_URL . "api/sessions/" . $_COOKIE["LACLASSE_AUTH"], $error, $status);

	if ($status != 200) {
		wpCAS::authenticate();
	}

	$session = json_decode($session);

	// get the user of the current session
	$userENT = get_http(ANNUAIRE_URL . "api/users/" . $session->user, $error, $status);

	if ($status != 200) {
		wpCAS::authenticate();
	}
	$userENT = json_decode($userENT);

	$ENT_action = $_REQUEST['ENT_action'];
	if (isset($_REQUEST['pblogid']))
		$ENTblogid  = $_REQUEST['pblogid'];
	if (isset($_REQUEST['blogid']))
		$blogid	= $_REQUEST['blogid'];
	if (isset($_REQUEST['blogname']))
		$blogname = $_REQUEST['blogname'];
	if (isset($_REQUEST['blogtype']))
		$blogtype = $_REQUEST['blogtype'];
	if (isset($_REQUEST['username']))
		$username = $_REQUEST['username'];
	if (isset($_REQUEST['uid']))
		$uid = $_REQUEST['uid'];
	if (isset($_REQUEST['uid_admin']))
		$uid_admin = $_REQUEST['uid_admin'];
	if (isset($_REQUEST['blogdescription']))
		$blogdescription = $_REQUEST['blogdescription'];
	$mustDieAfterAction = false;  // Utilisé pour les actions qui ne nécessitent pas d'affichage après s'être déroulées.
	
	switch ($ENT_action) {

	//  --------------------------------------------------------------------------------
	//
	// API pour récupérer le current user depuis l'annuaire v3
	//
	// ---------------------------------------------------------------------------------
	case 'CURRENT_USER' :
		header('Content-Type: application/json; charset=UTF-8');
		$json = $userENT;
		// load all user's structures info
		$structIds = "";
		$groupStructIds = "";
		foreach($json->profiles as $p) {
			$structIds .= urlencode("id[]")."=".urlencode($p->structure_id)."&";
			if (($p->type != 'TUT') && ($p->type != 'ELV') && ($p->type != 'ENS'))
				$groupStructIds .= urlencode("structure_id[]")."=".urlencode($p->structure_id)."&";
		}
		$res = get_http(ANNUAIRE_URL . "api/structures?".$structIds."expand=false");
		$json->user_structures = json_decode($res);

		// load all user's groups
		$groupsIds = "";
		foreach($json->groups as $g) {
			$groupsIds .= urlencode("id[]")."=".urlencode($g->group_id)."&";
		}
		if ($groupsIds == "") {
			$json->user_groups = [];
		}
		else {
			$res = get_http(ANNUAIRE_URL . "api/groups?".$groupsIds."expand=false");
			$json->user_groups = json_decode($res);
		}

		// add all structures ids if user has required right
		if ($groupStructIds != "") {
			$res = get_http(ANNUAIRE_URL . "api/groups?".$groupStructIds."expand=false");
			$groups = json_decode($res);
			foreach ($groups as $group) {
				$found = false;
				foreach ($json->user_groups as $g) {
					if ($g->id == $group->id) {
						$found = true;
					}
				}
				if (!$found) {
					array_push($json->user_groups, $group);
				}
			}
		}
		echo json_encode($json);
		$mustDieAfterAction = true;
		break;	

	//  --------------------------------------------------------------------------------
	//
	// Front office de présentation des blogs.
	//
	// ---------------------------------------------------------------------------------
	case 'FRONT' :
		include(plugin_dir_path( __FILE__ ) . 'front/index.php');
		$mustDieAfterAction = true;
		break;	

	//  --------------------------------------------------------------------------------
	//
	// Création de blogs
	//
	// ---------------------------------------------------------------------------------
	case 'CREATE_BLOG' :
		$user = get_user_by('login', $userENT->login);

		$blogId = creerNouveauBlog(
			$_REQUEST['domain'] . '.' . BLOGS_DOMAIN, '/', $_REQUEST['blogname'],
			$userENT->login, $user->data->user_email, 1,
			$user->ID, $_REQUEST['blogtype'], $_REQUEST['etbid'], $_REQUEST['clsid'],
			$_REQUEST['grpid'], $_REQUEST['gplid'], $_REQUEST['blogdescription']);

		add_user_to_blog($blogId, $user->ID, 'administrator');
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('success' => 'Création réussie'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;	

	// --------------------------------------------------------------------------------
	//
	// Tester l'existence d'un blog
	//
	// --------------------------------------------------------------------------------
	case 'BLOG_EXISTS' :
		header('Content-Type: application/json; charset=UTF-8');	
		$existance = blogExists($blogname);	
		echo json_encode( array("result" => $existance ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	//
	// Tester l'existence d'un utilisateur sur la plateforme WP.
	//
	// --------------------------------------------------------------------------------
	case 'USER_EXISTS' :
		userExists($username);	
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	// Liste des blogs de la plateforme
	// ?ENT_action=BLOG_LIST
	// --------------------------------------------------------------------------------
	case 'BLOG_LIST' :
		header('Content-Type: application/json; charset=UTF-8');
		$interests = blogList($userENT->id);
		$mines = userViewBlogList($userENT->id);

		foreach ($mines as $mine) {
			$mine = $mine;
			foreach($interests as $k => $interest) {
				if ($mine->blog_id == $interest->blog_id) {
					unset($interests[$k]);
					break;
				}
			}
		}
		$diff = array_values($interests);
		echo json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	// Liste des blogs de la plateforme auquel l'utilisateur est inscrit
	// ?ENT_action=USER_BLOG_LIST
	// --------------------------------------------------------------------------------
	case 'USER_BLOG_LIST' :
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(userViewBlogList($userENT->id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);	
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	// On suppose que le compte a déjà été provisionné, ET l'utilisateur est connecté
	// wp_get_current_user() est donc renseigné
	// - Il faut vérifier que l'utilisateur a le droit de s'inscrire
	// Si blog ETB => UAI utilisateur == UAI 
	// Si Blog de classe Classe utilisateur == classe_ENT pour les ELEVE
	// Si BLog de groupe Groupe utilisateur == Groupe_ENT pour les ELEVE
	// --------------------------------------------------------------------------------
	case 'INSCRIRE' :
		$inscrire = false;
		$status = "error";
		$message_retour = "";

		header('Content-Type: application/json; charset=UTF-8');
		if($blogid == '') {
			http_response_code(400);
			$t = Array("error" => "Le paramètre blogid doit être enseigné.");
			echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		$current_user = get_user_by('login', $userENT->login);

		// Récupération des détails sur le blog
        $blogData = getBlogData($blogid);

		// Déterminer le role WordPress de l'utilisateur en fonction de son role ENT.
        $role_wp = getUserWpRole($userENT, $blogData);

        if($role_wp != null) {
            $inscrire = true;
            $message_retour = "Inscription de l'utilisateur $current_user->display_name ".
                              "au blog ".$blogData->blogname.".";
        }
        else {
            $message_retour = "Vous ne pouvez pas vous inscrire sur ce blog.";
            $status = "error";
        }

		if ($inscrire) {
			add_user_to_blog($blogid, $current_user->ID, $role_wp);
			$status = "success";
			$message_retour .= ", role '$role_wp'";
		}

		echo json_encode(Array($status => $message_retour), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;
	// --------------------------------------------------------------------------------
	//
	// Desinscription d'un blog.
	// On suppose que l'utilisateur est logué et provisionné.
	//
	// --------------------------------------------------------------------------------
	case 'DESINSCRIRE' :
		header('Content-Type: application/json; charset=UTF-8');
		if($blogid == '') {
			http_response_code(400);
			echo json_encode(Array("error" => "Le paramètre blogid doit être renseigné."),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		$current_user = get_user_by('login', $userENT->login);

		// Désinscrire l'utilisateur
		remove_user_from_blog($current_user->ID, $blogid);

		echo json_encode(Array("success" => "L'utilisateur $current_user->display_name est désinscrit du blog"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	//
	// Action par défaut.
	//
	// --------------------------------------------------------------------------------
	default  :
		echo "L'action $ENT_action n'est pas prise en charge.";
		$mustDieAfterAction = true;
		break;
	}
	
// ici, pour certaines actions, on veut juste piloter Wordpress avec nos actions émanant de l'ENT
// et ne rien afficher. La pluspart du temps il s'agit d'actions de mise à jours  ou de provisionning.
//	Dans ces cas, on arrête tout traitement d'affichage si nécessaire. 
if ($mustDieAfterAction) die();
}

