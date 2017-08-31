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
// Fonctions de paramêtrage du back-office des options du plugin.
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
// ajouter le role du nom à la place de "Howdy"
add_filter( 'admin_bar_menu', 'bienvenue');
// Ajout d'un texte perso dans le footer.
add_filter('admin_footer_text', 'addEntName', 10, 0);
// Marquage Ministèriel
add_action('wp_footer', 'xiti_MEN_et_google', 10, 0);

// Maîtriser les headers http qui sont envoyés
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
// Hook pour la désinscription d'un blog.
// add_action( 'myblogs_allblogs_options', 'actionsBlog', 10, 0);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   a r t i c l e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// selectbox pour réduire la liste par auteur
add_action('restrict_manage_posts', 'restrict_manage_authors');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	f o n c t i o n s   d e   m o d i f i c a t i o n  d u   b l o g 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// Cette action est envoyé à chaque mise à jour d'une option. Il faut donc filtrer 
// par rapport à la page du back-office en cours et aux noms des options.
add_action( 'update_option', 'synchroENT', 10, 3);

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
// Contrôleur d'actions
// --------------------------------------------------------------------------------

if (isset($_REQUEST['ENT_action'])) {

	$ENT_action 		= $_REQUEST['ENT_action'];
	//$ENTblogid 			= $_REQUEST['ENTblogid'];
	$ENTblogid 			= $_REQUEST['pblogid'];
	$blogid 			= $_REQUEST['blogid'];
	$blogname 			= $_REQUEST['blogname'];
	$blogtype 			= $_REQUEST['blogtype'];
	$username			= $_REQUEST['username'];
	$uid				= $_REQUEST['uid'];
	$uid_admin			= $_REQUEST['uid_admin'];
	$blogdescription 	= $_REQUEST['blogdescription'];
	$mustDieAfterAction = false;  // Utilisé pour les actions qui ne nécessitent pas d'affichage après s'être déroulées.
	
	switch ($ENT_action) {

	//  --------------------------------------------------------------------------------
	//
	// API pour récupérer le current user depuis l'annuaire v3
	//
	// ---------------------------------------------------------------------------------
	case 'CURRENT_USER' :
		if (phpCAS::isAuthenticated()) {
			header('Content-Type: application/json; charset=UTF-8');
			$res = get_http(ANNUAIRE_URL . "api/users/" . phpCAS::getAttribute('uid') . "?expand=true");

			$json = json_decode($res);
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
		} else { 
			// Si pas authentifié, ion force l'authentification. Du coup, le provisionning du user se fait.
			phpCAS::forceAuthentication(); 
		}
		$mustDieAfterAction = true;
		break;	

	//  --------------------------------------------------------------------------------
	//
	// Front office de présentation des blogs.
	//
	// ---------------------------------------------------------------------------------
	case 'FRONT' :
		if (phpCAS::isAuthenticated()) {
			include(plugin_dir_path( __FILE__ ) . 'front/index.php');
		} else { 
			// Si pas authentifié, ion force l'authentification. Du coup, le provisionning du user se fait.
			phpCAS::forceAuthentication(); 
		}
		$mustDieAfterAction = true;
		break;	

	//  --------------------------------------------------------------------------------
	//
	// Création de blogs
	//
	// ---------------------------------------------------------------------------------
	case 'CREATE_BLOG' :
		if (phpCAS::isAuthenticated()) {
			$user = get_user_by('login', phpCAS::getAttribute('login'));

			$blogId = creerNouveauBlog(
				$_REQUEST['domain'] . '.' . BLOG_DOMAINE, '/', $_REQUEST['blogname'],
				phpCAS::getAttribute('login'), $user->data->user_email, 1,
				$user->ID, $_REQUEST['blogtype'], $_REQUEST['etbid'], $_REQUEST['clsid'],
				$_REQUEST['grpid'], $_REQUEST['gplid']);

			add_user_to_blog($blogId, $user->ID, 'administrator');

			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(array('success' => 'Création réussie'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		} else { 
			// Si pas authentifié, ion force l'authentification. Du coup, le provisionning du user se fait.
			phpCAS::forceAuthentication(); 
		}
		$mustDieAfterAction = true;
		break;	

	// --------------------------------------------------------------------------------
	//
	// Se mettre en mode INTEGRE dans une IFRAME
	//
	// --------------------------------------------------------------------------------
	case 'IFRAME' :
		setIframeTemplate();
		$mustDieAfterAction = false;	// Maintenant qu'on a ajouté des filtres, on veut afficher le site.
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
	// Renvoie l'ID WP d'un blog identifié par son nom.
	// ?ENT_action=BLOG_ID
	// --------------------------------------------------------------------------------
	case 'BLOG_ID' :
		$t = Array();
		$t['id'] = getBlogIdByDomain($blogname);
		header('Content-Type: application/json; charset=UTF-8');	
		echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;
	// --------------------------------------------------------------------------------
	// Liste des blogs de la plateforme
	// ?ENT_action=BLOG_LIST
	// --------------------------------------------------------------------------------
	case 'BLOG_LIST' :
		header('Content-Type: application/json; charset=UTF-8');
		// if user is not connected, HTTP 401
		if (!phpCAS::isAuthenticated()) {
			http_response_code(401);
			$t = Array("error" => "Vous devez vous authentifier...");
			echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		$interests = blogList(phpCAS::getAttribute('uid'));
		$mines = userViewBlogList(phpCAS::getAttribute('uid'));

		foreach ($mines as $mine) {
			$mine = (array)$mine;
			foreach($interests as $k => $interest) {
				if ($mine['blog_id'] == $interest['blog_id']) {
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
		// if user is not connected, HTTP 401
		if (!phpCAS::isAuthenticated()) {
			http_response_code(401);
			$t = Array("error" => "Vous devez vous authentifier...");
			echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		echo json_encode(userViewBlogList(phpCAS::getAttribute('uid')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);	
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	//
	// Logout de WP.
	//
	// --------------------------------------------------------------------------------
	case 'LOGOUT' :	
		wpCAS::logout();
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	//
	// Modifier les paramétres du blog dans Worpress et mettre à jour dans l'ENT
	//
	// --------------------------------------------------------------------------------
	case 'MODIFIER_PARAMS' :
		modifierParams($domain);	
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
		// if user is not connected, HTTP 401
		if (!phpCAS::isAuthenticated()) {
			http_response_code(401);
			$t = Array("error" => "Vous devez vous authentifier...");
			echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		if($blogid == '') {
			http_response_code(400);
			$t = Array("error" => "Le paramètre blogid doit être enseigné.");
			echo json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		$current_user = get_user_by('login', phpCAS::getAttribute('login'));

		// Vérifier si l'utilisateur est bien connecté
		assert ('$current_user->ID  != ""', "L'utilisateur n'est pas connecté sur la plateforme WordPress de laclasse.com.");

		// Récupération des détails sur le blog
        $blogData = getBlogData($blogid);

		// Interrogation de l'annuaireV3 de l'ENT
		$userENT = json_decode(get_http(ANNUAIRE_URL."api/users/".phpCAS::getAttribute('uid')."?expand=true"));

		// Déterminer le role WordPress de l'utilisateur en fonction de son role ENT.
        $role_wp = getUserWpRole($userENT, $blogData);

        if($role_wp != null) {
            $inscrire = true;
            $message_retour = "Inscription de l'utilisateur $current_user->display_name ".
                              "au blog ".$blogData['blogname'].".";
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
		// if user is not connected, HTTP 401
		if (!phpCAS::isAuthenticated()) {
			http_response_code(401);
			echo json_encode(Array("error" => "Vous devez vous authentifier..."),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		if($blogid == '') {
			http_response_code(400);
			echo json_encode(Array("error" => "Le paramètre blogid doit être renseigné."),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
			$mustDieAfterAction = true;
			break;
		}

		$current_user = get_user_by('login', phpCAS::getAttribute('login'));
		// Vérifier si l'utilisateur est bien connecté
		assert ('$current_user->ID  != ""', "L'utilisateur n'existe pas sur la plateforme WordPress de laclasse.com.");

		// Désinscrire l'utilisateur
		remove_user_from_blog($current_user->ID, $blogid);

		echo json_encode(Array("success" => "L'utilisateur $current_user->display_name est désinscrit du blog"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		$mustDieAfterAction = true;
		break;

	case 'LISTE_ARCHIVAGE' : 
		// Liste des blogs à conserver 
		$liste_a_conserver = array (
		'http://actualitesaqueduc.blogs.laclasse.com/',		'http://actualitesphilo.blogs.laclasse.com/',
		'http://actualitestheatre.blogs.laclasse.com/',		'http://alacroiseedesfutursconfluence2115.blogs.laclasse.com/',
		'http://alafond.blogs.laclasse.com/',		'http://albermouton13.blogs.laclasse.com/',
		'http://andalousie.blogs.laclasse.com/',		'http://anglais.blogs.laclasse.com/',
		'http://animots-valises-victor-grignard.blogs.laclasse.com/',
		'http://ansecassin.blogs.laclasse.com/',		'http://antoineremondce2c.blogs.laclasse.com/',
		'http://aps-parfums.blogs.laclasse.com/',		'http://aqueduc.blogs.laclasse.com/',
		'http://arnas.blogs.laclasse.com/',		'http://artetenvironnement.blogs.laclasse.com/',
		'http://asducolegedelahauteazergues.blogs.laclasse.com/',		'http://associationsportivecollegerenecassin.blogs.laclasse.com/',
		'http://associationsportiveducollege.blogs.laclasse.com/',		'http://atelierartistiquecinemasaintlaurentdechamousset.blogs.laclasse.com/',
		'http://ateliercodagellr.blogs.laclasse.com/',		'http://atelierdartsplastiques.blogs.laclasse.com/',
		'http://atelierdesign.blogs.laclasse.com/',		'http://aufondducouloir.blogs.laclasse.com/',
		'http://baladolangue.blogs.laclasse.com/',		'http://balado3e23e6ciacovella.blogs.laclasse.com/',
		'http://battieres.blogs.laclasse.com/',		'http://battieresjournal.blogs.laclasse.com/',
		'http://bejuitdanslacourse.blogs.laclasse.com/',		'http://bilanguesvendome4emeitalien.blogs.laclasse.com/',
		'http://blogsdelatour.blogs.laclasse.com/',		'http://blogtechnologierobok3f.blogs.laclasse.com/',
		'http://blog123456.blogs.laclasse.com/',		'http://blog66.blogs.laclasse.com/',
		'http://bobleponge.blogs.laclasse.com/',		'http://bonsusagesmourguet.blogs.laclasse.com/',
		'http://bonyenvoyage.blogs.laclasse.com/',		'http://buissonelem.blogs.laclasse.com/',
		'http://bully.blogs.laclasse.com/',		'http://cadavresexquisalaconfluence.blogs.laclasse.com/',
		'http://calabres.meyzieu.blogs.laclasse.com/',		'http://cavaldargent.blogs.laclasse.com/',
		'http://cb211b4.blogs.laclasse.com/',		'http://cdi-college-champagnat.blogs.laclasse.com/',
		'http://cdi-college-eugenie-de-pomey.blogs.laclasse.com/',
		'http://cdillr2.blogs.laclasse.com/',		'http://cdi.lucieaubrac.blogs.laclasse.com/',
		'http://centrestefoy.blogs.laclasse.com/',		'http://ce1a.blogs.laclasse.com/',		'http://ce1b.blogs.laclasse.com/',
		'http://ce1centredarai.blogs.laclasse.com/',		'http://ce1-ce2-ecoleducentre.blogs.laclasse.com/',
		'http://ce1-ce2-garel.blogs.laclasse.com/',		'http://ce1ce2paulbert.blogs.laclasse.com/',
		'http://ce1c4delorme.blogs.laclasse.com/',		'http://ce1everat.blogs.laclasse.com/',
		'http://ce1mmemeyer.blogs.laclasse.com/',		'http://ce1-1.blogs.laclasse.com/',
		'http://ce1-1415.blogs.laclasse.com/',		'http://ce12014.blogs.laclasse.com/',
		'http://ce2a.blogs.laclasse.com/',		'http://ce2b.blogs.laclasse.com/',
		'http://ce2cm1centre.blogs.laclasse.com/',		'http://ce2cm1ecoleducentre.blogs.laclasse.com/',
		'http://ce2cm1mmesorbiermoise.blogs.laclasse.com/',		'http://ce2cm1montaigne.blogs.laclasse.com/',
		'http://ce2combeblanche.blogs.laclasse.com/',		'http://ce2-ecoleprimairecentre.blogs.laclasse.com/',
		'http://ce2mmechamproux.blogs.laclasse.com/',		'http://ce2paulbert.blogs.laclasse.com/',
		'http://ce2robertdoisneau.blogs.laclasse.com/',		'http://ce2villie-morgon-ecoleprimaire.blogs.laclasse.com/',
		'http://ce2-2013-2014.blogs.laclasse.com/',		'http://chaigneau.blogs.laclasse.com/',
		'http://champvertouest.blogs.laclasse.com/',		'http://champvertouest.blogs.laclasse.com/',
		'http://christelpaulbert.blogs.laclasse.com/',		'http://cinema.blogs.laclasse.com/',
		'http://citedudesign.blogs.laclasse.com/',		'http://classedemmenunez.blogs.laclasse.com/',
		'http://classemmecano.blogs.laclasse.com/',		'http://classepenicheneuville.blogs.laclasse.com/',
		'http://classesdecouverte2chenes.blogs.laclasse.com/',		'http://classeultramobile.blogs.laclasse.com/',
		'http://classe1.blogs.laclasse.com/',		'http://classe10.blogs.laclasse.com/',
		'http://classe10.blogs.laclasse.com/',		'http://classe2.blogs.laclasse.com/',
		'http://classe3.blogs.laclasse.com/',		'http://classe4.blogs.laclasse.com/',
		'http://classe5.blogs.laclasse.com/',		'http://classe5.lesmuguets.blogs.laclasse.com/',
		'http://classe6.blogs.laclasse.com/',		'http://classe7.blogs.laclasse.com/',
		'http://classe8.blogs.laclasse.com/',		'http://classe9.blogs.laclasse.com/',
		'http://clemiacademique.blogs.laclasse.com/',		'http://clg.ampere.blogs.laclasse.com/',
		'http://clg-andre-lassagne-3c.blogs.laclasse.com/',		'http://clg-boisfranc-hd.blogs.laclasse.com/',
		'http://clg-boisfranc-6e.blogs.laclasse.com/',		'http://clg-boisfranc-6f.blogs.laclasse.com/',
		'http://clgjacquescoeur.blogs.laclasse.com/',		'http://clgjeanjacquesrousseau.blogs.laclasse.com/',
		'http://clgllr.blogs.laclasse.com/',		'http://clincri-fc.blogs.laclasse.com/',
		'http://clio-sur-le-net-6eme.blogs.laclasse.com/',		'http://clishugo.blogs.laclasse.com/',
		'http://clubgrece.blogs.laclasse.com/',		'http://clubinternetraisonneleprinceringuet.blogs.laclasse.com/',
		'http://clubmemoiredeladeportation.blogs.laclasse.com/',		'http://clubphoto-2015-2016.blogs.laclasse.com/',
		'http://club-sciences.blogs.laclasse.com/',		'http://cluster1.blogs.laclasse.com/',
		'http://cluster2.blogs.laclasse.com/',		'http://cluster3.blogs.laclasse.com/',
		'http://cluster4.blogs.laclasse.com/',		'http://cluster5.blogs.laclasse.com/',
		'http://cmdorier-lozanne.blogs.laclasse.com/',		'http://cmguillemot.blogs.laclasse.com/',
		'http://cm-simone-de-beauvoir.blogs.laclasse.com/',		'http://cm1a.blogs.laclasse.com/',
		'http://cm1b.blogs.laclasse.com/',		'http://cm1-bonnet-2015-2016.blogs.laclasse.com/',
		'http://cm1-classe16.afrance.blogs.laclasse.com/',		'http://cm1-cm2chambost.blogs.laclasse.com/',
		'http://cm1-cm2-line-2013-2014.blogs.laclasse.com/',		'http://cm1-cm2-line-2013-2014.blogs.laclasse.com/',
		'http://cm1galligani-ecolebrindas.blogs.laclasse.com/',		'http://cm1-grandclement.blogs.laclasse.com/',
		'http://cm1madameriviere.blogs.laclasse.com/',		'http://cm1madameriviere2015.blogs.laclasse.com/',
		'http://cm1m.daniere-2010-2011.blogs.laclasse.com/',		'http://cm1mmelescure.blogs.laclasse.com/',
		'http://cm1-mr-f-perrin-2014-2015.blogs.laclasse.com/',		'http://cm1-rocher-2012-2013.blogs.laclasse.com/',
		'http://cm2a.blogs.laclasse.com/',		'http://cm2apasteursud.blogs.laclasse.com/',
		'http://cm2b.blogs.laclasse.com/',		'http://cm2-bergeret-2014-2015.blogs.laclasse.com/',
		'http://cm2cerisiers.blogs.laclasse.com/',		'http://cm2cgauss.blogs.laclasse.com/',
		'http://cm2classe16-2012-2013.blogs.laclasse.com/',		'http://cm2-de-lecole-elementaire-le-perollier.blogs.laclasse.com/',
		'http://cm2ecoleducentre.blogs.laclasse.com/',		'http://cm2-gayotf-2014-2015.blogs.laclasse.com/',
		'http://cm2-langevin-2015-2016.blogs.laclasse.com/',		'http://cm2-lelay-2012-2013.blogs.laclasse.com/',
		'http://cm2martine.blogs.laclasse.com/',		'http://cm2mmeresseguier.blogs.laclasse.com/',
		'http://cm2paulbert.blogs.laclasse.com/',		'http://cm2-rocher-2014-2015.blogs.laclasse.com/',
		'http://cm2-rocher-2015-2016.blogs.laclasse.com/',		'http://cm2-seve-2015-2016.blogs.laclasse.com/',
		'http://cm2vittorelli.blogs.laclasse.com/',		'http://coll-du-val-dargent.blogs.laclasse.com/',
		'http://college-alain.blogs.laclasse.com/',		'http://college-alexis-kandelaft.blogs.laclasse.com/',
		'http://collegeasapaulini.blogs.laclasse.com/',		'http://collegeboisfranc.blogs.laclasse.com/',
		'http://collegeclemenceaulyon.blogs.laclasse.com/',		'http://collegedargent.blogs.laclasse.com/',
		'http://collegedutonkin.blogs.laclasse.com/',		'http://collegeelsatriolet.blogs.laclasse.com/',
		'http://collegefredericmistral.blogs.laclasse.com/',		'http://collegehauteazergues.blogs.laclasse.com/',
		'http://collegejeancharcot.blogs.laclasse.com/',		'http://collegejeanmoulin.blogs.laclasse.com/',
		'http://collegejeanperrin.blogs.laclasse.com/',		'http://college-jean-philippe-rameau.blogs.laclasse.com/',
		'http://collegejoliot.blogs.laclasse.com/',		'http://collegelaclaveliere.blogs.laclasse.com/',
		'http://collegelamartine.blogs.laclasse.com/',		'http://collegelamartine.blogs.laclasse.com/',
		'http://college-laurent-mourguet.blogs.laclasse.com/',		'http://college-louis-lachenal.blogs.laclasse.com/',
		'http://collegelucieaubrac.blogs.laclasse.com/',		'http://collegemariacasares.blogs.laclasse.com/',
		'http://collegeolivierdeserres.blogs.laclasse.com/',		'http://collegepublicgeorgescharpak.blogs.laclasse.com/',
		'http://collegerenecassincorbas.blogs.laclasse.com/',		'http://collegesaucinema.blogs.laclasse.com/',
		'http://collegesettic.blogs.laclasse.com/',		'http://collegevictorgrignard.blogs.laclasse.com/',
		'http://colljulesmichelet.blogs.laclasse.com/',		'http://collprivlasidoine.blogs.laclasse.com/',
		'http://coll-simone-veil.blogs.laclasse.com/',		'http://concoursnationaldelaresistance2015.blogs.laclasse.com/',
		'http://condorcetmeyzieu.blogs.laclasse.com/',		'http://conseilgeneraldesjeunes.blogs.laclasse.com/',
		'http://couleurprimaire.blogs.laclasse.com/',		'http://cpantoineremond.blogs.laclasse.com/',
		'http://cpa-2014-2015.blogs.laclasse.com/',		'http://cp-b-2013-2014.blogs.laclasse.com/',
		'http://cp-ce1bonnepart.blogs.laclasse.com/',		'http://cpce1centre.blogs.laclasse.com/',
		'http://cpce1montrottier.blogs.laclasse.com/',		'http://cpce1paulbert.blogs.laclasse.com/',
		'http://cp-crespin-charly.blogs.laclasse.com/',		'http://cpgsannick.blogs.laclasse.com/',
		'http://cpmadamegaubert.blogs.laclasse.com/',		'http://cpnallet.blogs.laclasse.com/',
		'http://cps.blogs.laclasse.com/',		'http://cp1.blogs.laclasse.com/',
		'http://cp1.victorhugo.blogs.laclasse.com/',		'http://cp2.blogs.laclasse.com/',
		'http://cp9-2015-2016.blogs.laclasse.com/',		'http://cranium.blogs.laclasse.com/',
		'http://crdplyon.blogs.laclasse.com/',		'http://croqbook.blogs.laclasse.com/',
		'http://croqbook.blogs.laclasse.com/',		'http://cuisiniers69.blogs.laclasse.com/',
		'http://cybermobil.blogs.laclasse.com/',		'http://cycle2.lestilleuls.blogs.laclasse.com/',
		'http://cycle2oingt.blogs.laclasse.com/',		'http://cycle3.blogs.laclasse.com/',
		'http://c2i2eduvendredi.blogs.laclasse.com/',		'http://c2i2enseignement.blogs.laclasse.com/',
		'http://c2i2e1213l3g4.blogs.laclasse.com/',		'http://dechetscharcot.blogs.laclasse.com/',
		'http://defilecture.blogs.laclasse.com/',		'http://defis-scientifiques-larbresle.blogs.laclasse.com/',
		'http://delactiondanslecartable.blogs.laclasse.com/',		'http://de-roma-et-graecia.blogs.laclasse.com/',
		'http://des-animots-des-poemes-des-voix-2012-2013.blogs.laclasse.com/',		'http://desmathematiquespourleplaisir.blogs.laclasse.com/',
		'http://deutschlernen.blogs.laclasse.com/',		'http://deutschmmehaond.blogs.laclasse.com/',
		'http://documentalisteslyonnord.blogs.laclasse.com/',		'http://documentation.blogs.laclasse.com/',
		'http://dp3.blogs.laclasse.com/',		'http://dp3.blogs.laclasse.com/',
		'http://eco-collegiens.blogs.laclasse.com/',		'http://eco-joliot.blogs.laclasse.com/',
		'http://ecole-albert-jacquard.blogs.laclasse.com/',		'http://ecole-amberieux-azergues.blogs.laclasse.com/',
		'http://ecoleandremarieampere.blogs.laclasse.com/',		'http://ecolebelair.blogs.laclasse.com/',
		'http://ecole-blace.blogs.laclasse.com/',		'http://ecolebrullioles.blogs.laclasse.com/',
		'http://ecole-camus.blogs.laclasse.com/',		'http://ecolecartier.blogs.laclasse.com/',
		'http://ecolecercie.blogs.laclasse.com/',		'http://ecole.chambost.blogs.laclasse.com/',
		'http://ecolechampbouvier.blogs.laclasse.com/',		'http://ecolechatelain.blogs.laclasse.com/',
		'http://ecoleclaudiusfournion.blogs.laclasse.com/',		'http://ecoleclaudiusfournioncycle2.blogs.laclasse.com/',
		'http://ecoleclaudiusfournioncycle3.blogs.laclasse.com/',		'http://ecoleclaudiusfournionmaternelle.blogs.laclasse.com/',
		'http://ecolecommandantarnaud.blogs.laclasse.com/',		'http://ecolecondorcetlyon.blogs.laclasse.com/',
		'http://ecoledancy.blogs.laclasse.com/',		'http://ecoledelatourbrindas.blogs.laclasse.com/',
		'http://ecoledemeys.blogs.laclasse.com/',	'http://ecoledepomeys.blogs.laclasse.com/',
		'http://ecolederonno.blogs.laclasse.com/',		'http://ecolediderot.blogs.laclasse.com/',
		'http://ecoleduvillage.blogs.laclasse.com/',		'http://ecole-ebourgeois-lozanne.blogs.laclasse.com/',
		'http://ecoleelementairealphonsedaudet.blogs.laclasse.com/',		'http://ecole-elementaire-anatolefrancea.blogs.laclasse.com/',
		'http://ecoleelementairebillon.blogs.laclasse.com/',		'http://ecoleelementairedugolf.blogs.laclasse.com/',
		'http://ecoleelementairejoliotcurie.blogs.laclasse.com/',		'http://ecoleelementairelatatiere.blogs.laclasse.com/',
		'http://ecoleelementairelucieguimet.blogs.laclasse.com/',		'http://ecole-elementaire-montanay.blogs.laclasse.com/',
		'http://ecoleelementairestefoylargentiere.blogs.laclasse.com/',		'http://ecole-fleurieusursaone.blogs.laclasse.com/',
		'http://ecolefrederiquemistral.blogs.laclasse.com/',		'http://ecole-gilbert-dru-ce1a.blogs.laclasse.com/',
		'http://ecolegrandris.blogs.laclasse.com/',		'http://ecolejeanmoulincaluire.blogs.laclasse.com/',
		'http://ecole-jul.blogs.laclasse.com/',		'http://ecole-julienas.blogs.laclasse.com/',
		'http://ecolejulie-victoiredaubie.blogs.laclasse.com/',		'http://ecolelachatelaise.blogs.laclasse.com/',
		'http://ecolelachaussonniere.blogs.laclasse.com/',		'http://ecolelafontaine.blogs.laclasse.com/',
		'http://ecolelafontainechasselay.blogs.laclasse.com/',		'http://ecolelamure.blogs.laclasse.com/',
		'http://ecolelaplaine.blogs.laclasse.com/',		'http://ecole-le-perreon.blogs.laclasse.com/',
		'http://ecole.les.grillons.blogs.laclasse.com/',		'http://ecolelouispingon.blogs.laclasse.com/',
		'http://ecolemariecurie.blogs.laclasse.com/',		'http://ecolemariecurie.blogs.laclasse.com/',
		'http://ecolemariecurie.blogs.laclasse.com/',		'http://ecolemariecurie.blogs.laclasse.com/',
		'http://ecolematernelleandrelassagne.blogs.laclasse.com/',		'http://ecolematernelle.blogs.laclasse.com/',
		'http://ecole-maternelle-condorcet-maternelle-mourey.blogs.laclasse.com/',		'http://ecolematernelledesbattieres.blogs.laclasse.com/',
		'http://ecolematernelleducentre.blogs.laclasse.com/',		'http://ecolematernelleduplateau.blogs.laclasse.com/',
		'http://ecolematernelleetelementairevancia.blogs.laclasse.com/',		'http://ecolematernellehilairedunand.blogs.laclasse.com/',
		'http://ecolematernellejacquesprevert.blogs.laclasse.com/',		'http://ecolematernellejeandelafontaine1.blogs.laclasse.com/',
		'http://ecolematernellejeangerson.blogs.laclasse.com/',		'http://ecolematernellelecerfvolant.blogs.laclasse.com/',
		'http://ecolematernelleleflachat.blogs.laclasse.com/',		'http://ecolematernellelepetitprince-mornant.blogs.laclasse.com/',
		'http://ecolematernellelescharmilles.blogs.laclasse.com/',		'http://ecolematernellelouisechassagne.blogs.laclasse.com/',
		'http://ecolematernellemathildesiraud.blogs.laclasse.com/',		'http://ecolematernellepaulchevallier.blogs.laclasse.com/',
		'http://ecolematernelle1.blogs.laclasse.com/',		'http://ecole.monsols.blogs.laclasse.com/',
		'http://ecolemontmelas.blogs.laclasse.com/',		'http://ecolemontromant.blogs.laclasse.com/',
		'http://ecolenuelles.blogs.laclasse.com/',		'http://ecoleoingt.blogs.laclasse.com/',
		'http://ecoleparmentier-ps.blogs.laclasse.com/',		'http://ecoleparmentier-psms.blogs.laclasse.com/',
		'http://ecoleplateauclassecm2.blogs.laclasse.com/',		'http://ecoleprimaireanatolefrance.blogs.laclasse.com/',
		'http://ecoleprimaireanatolefrance-cm1bmmeartero.blogs.laclasse.com/',		'http://ecoleprimaireanatolefrance2.blogs.laclasse.com/',
		'http://ecoleprimaireberthiealbrecht.blogs.laclasse.com/',		'http://ecoleprimairebonyaventuriere.blogs.laclasse.com/',
		'http://ecoleprimairebourg.blogs.laclasse.com/',		'http://ecoleprimairecastellane.blogs.laclasse.com/',
		'http://ecoleprimairecentre.blogs.laclasse.com/',		'http://ecoleprimairecentre1.blogs.laclasse.com/',
		'http://ecoleprimairecharlesperrault-ce2cm1galou-2015-2016.blogs.laclasse.com/',		'http://ecoleprimairechater.blogs.laclasse.com/',
		'http://ecoleprimairedanislesgrainsdeble.blogs.laclasse.com/',		'http://ecoleprimairedapplicationvictorhugo.blogs.laclasse.com/',
		'http://ecoleprimairedemontrottier.blogs.laclasse.com/',		'http://ecoleprimairedestablesclaudiennes.blogs.laclasse.com/',
		'http://ecoleprimairedeurope.blogs.laclasse.com/',		'http://ecoleprimairedommartin69.blogs.laclasse.com/',
		'http://ecoleprimairegeorgesbrassens-cm1.blogs.laclasse.com/',		'http://ecoleprimairegeorgeslamarque.blogs.laclasse.com/',
		'http://ecoleprimairejacquesprevert.blogs.laclasse.com/',		'http://ecoleprimairejeangerson.blogs.laclasse.com/',
		'http://ecoleprimairejoliotcurie.blogs.laclasse.com/',		'http://ecoleprimairejulesferry.blogs.laclasse.com/',
		'http://ecoleprimairejulesverne.blogs.laclasse.com/',		'http://ecoleprimairelagraviere.blogs.laclasse.com/',
		'http://ecoleprimairelaplaine.blogs.laclasse.com/',		'http://ecoleprimairelasoie.blogs.laclasse.com/',
		'http://ecoleprimairelechatenay.blogs.laclasse.com/',		'http://ecoleprimairelescerisiers.blogs.laclasse.com/',
		'http://ecoleprimairelesgemeaux.blogs.laclasse.com/',		'http://ecoleprimairelesgeraniums-cp.blogs.laclasse.com/',
		'http://ecoleprimairelesmarronniers.blogs.laclasse.com/',		'http://ecoleprimairelouispergaud1.blogs.laclasse.com/',
		'http://ecoleprimairelumiere.blogs.laclasse.com/',		'http://ecoleprimairemathieudumoulin.blogs.laclasse.com/',
		'http://ecoleprimairemichelservet.blogs.laclasse.com/',		'http://ecoleprimairerevaison.blogs.laclasse.com/',
		'http://ecoleprimairerontalon.blogs.laclasse.com/',		'http://ecole-primaire-saint-romain.blogs.laclasse.com/',
		'http://ecoleprimairesalvadorallende-maternellems.blogs.laclasse.com/',		'http://ecoleprimairesalvadorallende-maternelletpsps.blogs.laclasse.com/',
		'http://ecoleprimairesimonedebeauvoir-ce2-1.blogs.laclasse.com/',		'http://ecoleprimairevallongrandvaux.blogs.laclasse.com/',
		'http://ecoleprimaire-villie-morgon.blogs.laclasse.com/',		'http://ecoleprimaire1.blogs.laclasse.com/',
		'http://ecolepublique-stsorlin.blogs.laclasse.com/',		'http://ecolerobertdoisneau.blogs.laclasse.com/',
		'http://ecole-robert-schuman.blogs.laclasse.com/',		'http://ecolesainbel.blogs.laclasse.com/',
		'http://ecolesaintexupery.blogs.laclasse.com/',		'http://ecolesoupault.blogs.laclasse.com/',
		'http://ecolestmartinenhaut.blogs.laclasse.com/',		'http://ecole-stverand.blogs.laclasse.com/',
		'http://ecoletaponas.blogs.laclasse.com/',		'http://ecoletoussieu.blogs.laclasse.com/',
		'http://ecolevaugneray.blogs.laclasse.com/',		'http://ecolevauxenbeaujolais.blogs.laclasse.com/',
		'http://edurhone.blogs.laclasse.com/',		'http://ehlisez.blogs.laclasse.com/',
		'http://elementaire-lapierre-lyon4.blogs.laclasse.com/',		'http://elementairelfpv.blogs.laclasse.com/',
		'http://elemjeanmacebelleville.blogs.laclasse.com/',		'http://elievignal.blogs.laclasse.com/',
		'http://emile-malfroy.blogs.laclasse.com/',		'http://englishatfaubert.blogs.laclasse.com/',
		'http://englishwithmrbutterbach.blogs.laclasse.com/',		'http://ent-laclasse.blogs.laclasse.com/',
		'http://esemlyon.blogs.laclasse.com/',		'http://esempro.blogs.laclasse.com/',
		'http://espacelibreexpression.blogs.laclasse.com/',		'http://espacenumerique.blogs.laclasse.com/',
		'http://espagnolen4llr.blogs.laclasse.com/',		'http://espagnol.thizy.blogs.laclasse.com/',
		'http://espelyon4.blogs.laclasse.com/',		'http://eugegnol1.blogs.laclasse.com/',
		'http://evaluation3e1.blogs.laclasse.com/',		'http://evaluation3e2-2012-2013.blogs.laclasse.com/',
		'http://eval3.blogs.laclasse.com/',		'http://eval3e1.blogs.laclasse.com/',
		'http://excellence-scientifique.blogs.laclasse.com/',		'http://experimentationtabletactile.blogs.laclasse.com/',
		'http://fabnadfra.blogs.laclasse.com/',		'http://fastfight.blogs.laclasse.com/',
		'http://finisterrae.blogs.laclasse.com/',		'http://firstok.blogs.laclasse.com/',
		'http://flashgroup.blogs.laclasse.com/',		'http://fontaines-centre-elementaire.blogs.laclasse.com/',
		'http://formationblog.blogs.laclasse.com/',		'http://fseducollegejeanrostand.blogs.laclasse.com/',
		'http://fulchiron.blogs.laclasse.com/',		'http://gemeauxmat.blogs.laclasse.com/',
		'http://genevievepaulbert.blogs.laclasse.com/',		'http://germanistesvendome.blogs.laclasse.com/',
		'http://groupedetravailpreparationc2i2e.blogs.laclasse.com/',		'http://groupef.blogs.laclasse.com/',
		'http://gs-bernard-clavel.blogs.laclasse.com/',		'http://gs.lespierres.blogs.laclasse.com/',
		'http://gsveyret.blogs.laclasse.com/',		'http://hauterivoire.blogs.laclasse.com/',
		'http://h.blogs.laclasse.com/',		'http://hda-boisfranc.blogs.laclasse.com/',
		'http://hda-boisfranc-2014-2015.blogs.laclasse.com/',		'http://hda-faubert.blogs.laclasse.com/',
		'http://hda-fmistral-feyzi.blogs.laclasse.com/',		'http://hda3a.blogs.laclasse.com/',
		'http://hda3b.blogs.laclasse.com/',		'http://hda-3c.blogs.laclasse.com/',
		'http://hda3c.blogs.laclasse.com/',	'http://hda.3d.blogs.laclasse.com/',
		'http://hda3d.blogs.laclasse.com/',		'http://hda3e.blogs.laclasse.com/',
		'http://hda-3f.blogs.laclasse.com/',		'http://hda3f.blogs.laclasse.com/',
		'http://hda3g.blogs.laclasse.com/',		'http://hda3jmoulin.blogs.laclasse.com/',
		'http://hda.32.vonnas.blogs.laclasse.com/',		'http://hda.34.vonnas.blogs.laclasse.com/',
		'http://henrygormand.blogs.laclasse.com/',		'http://hgchanal.blogs.laclasse.com/',
		'http://hghghgh.blogs.laclasse.com/',		'http://histoiredesartsaucollegedebeaujeu.blogs.laclasse.com/',
		'http://histoiredesartsducollegedemonsols.blogs.laclasse.com/',		'http://histoire-des-arts-2012-2013.blogs.laclasse.com/',
		'http://histoire-geographie-auxlaz.blogs.laclasse.com/',		'http://histoire.geographie.blogs.laclasse.com/',
		'http://housni.boudaou.blogs.laclasse.com/',		'http://icidargent.blogs.laclasse.com/',
		'http://iddblog.blogs.laclasse.com/',		'http://ier69.blogs.laclasse.com/',
		'http://imelesgrillons.blogs.laclasse.com/',		'http://informationspartagees.blogs.laclasse.com/',
		'http://internetresponsablejouvetjaures.blogs.laclasse.com/',		'http://italie2015.blogs.laclasse.com/',
		'http://iufmdelyon.blogs.laclasse.com/',		'http://joliotvacances.blogs.laclasse.com/',
		'http://jonagepaulclaudel.blogs.laclasse.com/',		'http://journalduclos.blogs.laclasse.com/',
		'http://journalducollege.blogs.laclasse.com/',		'http://journal-vendome.blogs.laclasse.com/',
		'http://julesferry69124.blogs.laclasse.com/',		'http://jvd-classe12-2015-2016.blogs.laclasse.com/',
		'http://kittysfamily.blogs.laclasse.com/',		'http://labugattidu69.blogs.laclasse.com/',
		'http://lachassagne.blogs.laclasse.com/',		'http://lacigale.blogs.laclasse.com/',
		'http://laclassedemadameollagnon.blogs.laclasse.com/',		'http://laclassedemagali.blogs.laclasse.com/',
		'http://laclassedespetits.blogs.laclasse.com/',		'http://lagazettedelatourette.blogs.laclasse.com/',
		'http://la-gourmandise.blogs.laclasse.com/',		'http://lamaternelledesecureuils.blogs.laclasse.com/',
		'http://lamsdepauletvirginie.blogs.laclasse.com/',		'http://lamusiqueavecmvialatte.blogs.laclasse.com/',
		'http://laparoleauxparents.blogs.laclasse.com/',		'http://lartengage.blogs.laclasse.com/',
		'http://latin5emechampagnat.blogs.laclasse.com/',		'http://latireliredesecoliers.blogs.laclasse.com/',
		'http://latourette.blogs.laclasse.com/',		'http://leblogdesmediateurs.blogs.laclasse.com/',
		'http://lebloghistgeodes5e.blogs.laclasse.com/',		'http://lechtitsarcey.blogs.laclasse.com/',
		'http://lecoindesppre.blogs.laclasse.com/',		'http://lefrancaisauxiris.blogs.laclasse.com/',
		'http://lefrancaiscestpasquedesdictees.blogs.laclasse.com/',		'http://le-labeur-et-la-peine.blogs.laclasse.com/',
		'http://lepetitprince.blogs.laclasse.com/',		'http://leptitlu.blogs.laclasse.com/',
		'http://lesardillats.blogs.laclasse.com/',		'http://lesbisounours.blogs.laclasse.com/',
		'http://lescertifiables.blogs.laclasse.com/',		'http://lescpdeleclerc2012-2013.blogs.laclasse.com/',
		'http://lescpencriersdethurins.blogs.laclasse.com/',		'http://lesenfantsdabord.blogs.laclasse.com/',
		'http://lesiriscotecour.blogs.laclasse.com/',		'http://lesjeunesetinternet.blogs.laclasse.com/',
		'http://leslecteursdesaintlau.blogs.laclasse.com/',		'http://lesmiserables-4eme2.blogs.laclasse.com/',
		'http://lesmiserables-4eme2.blogs.laclasse.com/',		'http://lespetitsdemagali.blogs.laclasse.com/',
		'http://lespoissonsdetamarin.blogs.laclasse.com/',		'http://lesstreetniciens.blogs.laclasse.com/',
		'http://lestilleuls.blogs.laclasse.com/',		'http://letslearnenglish.blogs.laclasse.com/',
		'http://lezardsplastiques.blogs.laclasse.com/',		'http://lurcat.blogs.laclasse.com/',
		'http://lyceefrancaisportvilaauvanuatu.blogs.laclasse.com/',		'http://lyonprofdoc.blogs.laclasse.com/',
		'http://madamechambe.blogs.laclasse.com/',		'http://madamechavanon.blogs.laclasse.com/',
		'http://madameperret.blogs.laclasse.com/',		'http://madameriviere2015.blogs.laclasse.com/',
		'http://magali.ps.blogs.laclasse.com/',		'http://manchot.blogs.laclasse.com/',
		'http://masterprofdoc.blogs.laclasse.com/',		'http://materfred.blogs.laclasse.com/',
		'http://maternellaville.blogs.laclasse.com/',		'http://maternelle-alix-gs.blogs.laclasse.com/',
		'http://maternelle-allouche-2015-2016.blogs.laclasse.com/',		'http://maternelle-berthie-albrecht.blogs.laclasse.com/',
		'http://maternellecastellane.blogs.laclasse.com/',		'http://maternellechatelain.blogs.laclasse.com/',
		'http://maternelleduclosbrindas.blogs.laclasse.com/',		'http://maternelledumoulin.blogs.laclasse.com/',
		'http://maternelledurevoyet.blogs.laclasse.com/',		'http://maternelle-etienne-dolet.blogs.laclasse.com/',
		'http://maternelle.florence.vignon.blogs.laclasse.com/',		'http://maternelle-guilhot-2015-2016.blogs.laclasse.com/',
		'http://maternelle-jean-jaures-caluire.blogs.laclasse.com/',		'http://maternellelapalud.blogs.laclasse.com/',
		'http://maternelle-lozanne.blogs.laclasse.com/',		'http://maternellemontanay.blogs.laclasse.com/',
		'http://maternellemsmonique.blogs.laclasse.com/',		'http://maternelleoingt.blogs.laclasse.com/',
		'http://maternellephilippesoupault.blogs.laclasse.com/',		'http://maternelle-sophie-2013-2014.blogs.laclasse.com/',
		'http://mathematiques.blogs.laclasse.com/',		'http://matroisiemea.blogs.laclasse.com/',
		'http://mediamomesbeaujolais.blogs.laclasse.com/',		'http://mediation-2015.blogs.laclasse.com/',
		'http://meef-eco.blogs.laclasse.com/',		'http://memoiredelashoah.blogs.laclasse.com/',
		'http://mib.blogs.laclasse.com/',		'http://mickorero.blogs.laclasse.com/',
		'http://mionsfumeux.blogs.laclasse.com/',		'http://modedemploi.blogs.laclasse.com/',
		'http://monsieurandries.blogs.laclasse.com/',		'http://montaigne-ferry.blogs.laclasse.com/',
		'http://ms.lafontaine.blogs.laclasse.com/',		'http://museedesconfluences.blogs.laclasse.com/',
		'http://museegalloromainfourviere.blogs.laclasse.com/',		'http://musiqueavecolivier.blogs.laclasse.com/',
		'http://mystereamonnetpac5e3.blogs.laclasse.com/',		'http://nathaliepaulbert.blogs.laclasse.com/',
		'http://notrecm2.blogs.laclasse.com/',		'http://notrecm2davant.blogs.laclasse.com/',
		'http://nourisobramiso.blogs.laclasse.com/',		'http://nouslescp.blogs.laclasse.com/',
		'http://ocinaee.blogs.laclasse.com/',		'http://odyssee.blogs.laclasse.com/',
		'http://oingtcycle3.blogs.laclasse.com/',		'http://olympedegouges.blogs.laclasse.com/',
		'http://option-sciences-2013.blogs.laclasse.com/',		'http://orientation.blogs.laclasse.com/',
		'http://pac5e2cinemadanimation.blogs.laclasse.com/',		'http://pagnol.anse.blogs.laclasse.com/',
		'http://parcoursavenir.blogs.laclasse.com/',		'http://parentsdelevessimoneveil.blogs.laclasse.com/',
		'http://parmentiermaternelle.blogs.laclasse.com/',		'http://pasteur.blogs.laclasse.com/',
		'http://pasteurlyon8.blogs.laclasse.com/',		'http://paulbert.blogs.laclasse.com/',
		'http://pbrossolette.blogs.laclasse.com/',		'http://pbrossolette.blogs.laclasse.com/',
		'http://periscovictorhugo.blogs.laclasse.com/',		'http://physique.coeur.lentilly.blogs.laclasse.com/',
		'http://pirates.blogs.laclasse.com/',		'http://pleins-feux-sur-lhistoire-3eme.blogs.laclasse.com/',
		'http://plumedor.blogs.laclasse.com/',		'http://poleymieux.blogs.laclasse.com/',
		'http://potagerdudomainedelacroixlaval.blogs.laclasse.com/',		'http://pourlesgestionnairesetablissements.blogs.laclasse.com/',
		'http://prixdesincos.blogs.laclasse.com/',		'http://profdoc.blogs.laclasse.com/',
		'http://projet-environnement.blogs.laclasse.com/',		'http://ps-taluyers.blogs.laclasse.com/',
		'http://pteacamplepuisthizycours.blogs.laclasse.com/',		'http://pteac-oullins2.blogs.laclasse.com/',
		'http://pteac-villefranche-sur-saone.blogs.laclasse.com/',
		'http://quatriemedecouverture.blogs.laclasse.com/',		'http://quelmetier.blogs.laclasse.com/',
		'http://quincieux-maternelle.blogs.laclasse.com/',		'http://radio-aime-cesaire.blogs.laclasse.com/',
		'http://rdr.blogs.laclasse.com/',		'http://reformecollege.blogs.laclasse.com/',
		'http://reneemayou.blogs.laclasse.com/',
		'http://reseau-richesses.blogs.laclasse.com/',		'http://reseautechnobeaujolaisvaldesaone.blogs.laclasse.com/',
		'http://riviere2014.blogs.laclasse.com/',		'http://rmtf1213.blogs.laclasse.com/',
		'http://rmtf1314.blogs.laclasse.com/',		'http://robotblog.blogs.laclasse.com/',
		'http://robveiler.blogs.laclasse.com/',		'http://robwinner.blogs.laclasse.com/',
		'http://rocher2013-2014.blogs.laclasse.com/',		'http://romignons.blogs.laclasse.com/',
		'http://ronalbot.blogs.laclasse.com/',		'http://rostand.blogs.laclasse.com/',		'http://roy.e.blogs.laclasse.com/',
		'http://rpi-propieres-et-saint-clement-de-vers.blogs.laclasse.com/',		'http://sante.blogs.laclasse.com/',
		'http://sathonay-camp-elementaire.blogs.laclasse.com/',		'http://sathonay-camp-maternelle.blogs.laclasse.com/',
		'http://schoolexchangelyontelford.blogs.laclasse.com/',		'http://schumanclis.blogs.laclasse.com/',
		'http://secourisme-et-citoyennete.blogs.laclasse.com/',		'http://segpa.blogs.laclasse.com/',
		'http://segpagrigny69.blogs.laclasse.com/',		'http://segpa-jean-renoir-neuville.blogs.laclasse.com/',
		'http://segpa2.blogs.laclasse.com/',		'http://sharingproject.blogs.laclasse.com/',
		'http://sortie6emeintegration.blogs.laclasse.com/',		'http://soutienfrancais4eme.blogs.laclasse.com/',
		'http://stclementsousvalsonne.blogs.laclasse.com/',		'http://sujetstravauxdelevesartsplastiques3.blogs.laclasse.com/',
		'http://sujetstravauxdelevesetreferencesenartsplastiques4.blogs.laclasse.com/',
		'http://sujetstravauxdelevesetreferencesenartsplastiques5.blogs.laclasse.com/',
		'http://sujetstravaux6.blogs.laclasse.com/',		'http://super-clis.blogs.laclasse.com/',
		'http://super2013.blogs.laclasse.com/',		'http://svtbrottet.blogs.laclasse.com/',
		'http://svtcharcot.blogs.laclasse.com/',		'http://svtclasseinversee.blogs.laclasse.com/',		'http://svtfaubert.blogs.laclasse.com/',
		'http://tablettes5eme.blogs.laclasse.com/',		'http://tapjulienas.blogs.laclasse.com/',
		'http://technobassinsudest.blogs.laclasse.com/',		'http://technologie.blogs.laclasse.com/',
		'http://technolucieaubrac.blogs.laclasse.com/',		'http://term.2-universitedelyon.blogs.laclasse.com/',
		'http://term-6-universite-lyon.blogs.laclasse.com/',		'http://testblog1.blogs.laclasse.com/',
		'http://test-youcef.blogs.laclasse.com/',		'http://test3.blogs.laclasse.com/',
		'http://test4.blogs.laclasse.com/',		'http://test7.blogs.laclasse.com/',
		'http://theteamminion.blogs.laclasse.com/',		'http://udl.blogs.laclasse.com/',
		'http://ulis-claudel.blogs.laclasse.com/',		'http://ulisjulesmichelet.blogs.laclasse.com/',
		'http://ulislpcanuts.blogs.laclasse.com/',		'http://uneclasseunpeuinversee.blogs.laclasse.com/',
		'http://upe2a.lyoncroixrousse.blogs.laclasse.com/',		'http://upe2aquesnel.blogs.laclasse.com/',
		'http://usepvillefranche69.blogs.laclasse.com/',		'http://vgblogeleves.blogs.laclasse.com/',
		'http://vifdor4e3.blogs.laclasse.com/',		'http://vivaespana.blogs.laclasse.com/',
		'http://voyageculturelallemagneitalie.blogs.laclasse.com/',		'http://voyageechangeaveclitalie.blogs.laclasse.com/',
		'http://voyageenangleterre.blogs.laclasse.com/',		'http://voyageenangleterre1.blogs.laclasse.com/',
		'http://voyageenespagne.blogs.laclasse.com/',		'http://zerogaspi.blogs.laclasse.com/',
		'http://296.blogs.laclasse.com/',		'http://3a-2013-2014.blogs.laclasse.com/',
		'http://3a-2015-2016.blogs.laclasse.com/',		'http://3bgr4sciences.blogs.laclasse.com/',
		'http://3bulis.blogs.laclasse.com/',		'http://3cegr2sciences.blogs.laclasse.com/',
		'http://3cgr6sciences.blogs.laclasse.com/',		'http://3-c2-2013-2014.blogs.laclasse.com/',
		'http://3dfgr2sciences.blogs.laclasse.com/',		'http://3-d1-2013-2014.blogs.laclasse.com/',
		'http://3-d2e1-2013-2014.blogs.laclasse.com/',		'http://3egr3sciences.blogs.laclasse.com/',
		'http://3-eme-2013-2014.blogs.laclasse.com/',		'http://3e-vanuatu.blogs.laclasse.com/',
		'http://3-e2-2013-2014.blogs.laclasse.com/',		'http://3fgr3sciences.blogs.laclasse.com/',
		'http://3-3e6-2015-2016.blogs.laclasse.com/',		'http://4bc-maths-eugeniedepomey.blogs.laclasse.com/',
		'http://4d-clg-gabriel-rosset.blogs.laclasse.com/',		'http://4d-2015-2016.blogs.laclasse.com/',
		'http://402-maths-mourguet.blogs.laclasse.com/',		'http://403-maths-mourguet.blogs.laclasse.com/',
		'http://41agl1.blogs.laclasse.com/',		'http://5c-maths-eugeniedepomey.blogs.laclasse.com/',
		'http://5-c-2015-2016.blogs.laclasse.com/',		'http://5evendome1.blogs.laclasse.com/',
		'http://5evendome2.blogs.laclasse.com/',		'http://501-maths-clg-mourguet.blogs.laclasse.com/',
		'http://503-clg-mourguet.blogs.laclasse.com/',		'http://51-clg-jean-moulin.blogs.laclasse.com/',
		'http://5-5e7-2015-2016.blogs.laclasse.com/',		'http://5-7-projet-plongee-clg-les-cotes.blogs.laclasse.com/',
		'http://6anglgr3.blogs.laclasse.com/',		'http://6cd-maths-eugeniedepomey.blogs.laclasse.com/',
		'http://6eme-1-bacasable-2012-2013.blogs.laclasse.com/',		'http://6eme-2-bacasable-2015-2016.blogs.laclasse.com/',
		'http://6e1multimedia20132014clgfeyzin.blogs.laclasse.com/',		'http://6e2-2015-2016.blogs.laclasse.com/',
		'http://6e4-2015-2016.blogs.laclasse.com/',		'http://6f-clg-evaristegalois.blogs.laclasse.com/');

		// Gestion des actions d'archivage.
		$action2 = $_REQUEST['action2'];		
		if (isset($action2) && $action2 != "") {
			$id = $_REQUEST['id'];
			update_blog_status( $id, 'archived', ( 'archiveblog' === $action2 ) ? '1' : '0' );
		}
		// Extraction bdd
		global $wpdb;
		$query = "";
		$liste = $wpdb->get_results( "SELECT blog_id, domain, archived FROM $wpdb->blogs WHERE domain != '".BLOG_DOMAINE."' order by domain", ARRAY_A );

		$html = "<html><head><title>Liste des sites à archiver</title>
		<style>
			  table td {padding:3px 20px 3px 20px;}
			  table td {border:black solid 1px;}
			  .gris-sale {background-color:#aaa;}
			  .lilipute {font-size:0.6em;}
		</style>\n</head><body><div style='margin:40px;'><h1>Liste des sites &agrave; archiver</h1>\n<table>\n";
		$html .= "<p>Voici la liste des blogs à archiver. Si vous avez un doute, vous pouvez toujours aller visiter le blog pour être sûr. Lorsque vous êtes sûrs cliquez sur l'archiver. Ce processus est réversible, pas de panique, donc...</p>";
		foreach($liste as $k => $blog) {
			if (!in_array("http://".$blog['domain']."/", $liste_a_conserver) && $blog['domain'] != "") {
				$gris_sale = ( $blog['archived'] == 0 ) ? '' : 'gris-sale';
				$html .= "<tr class='$gris_sale'>";
				$html .= "<td><a name='".($k+1)."'></a>".($k+1)."</td>";

				$html .= "<td><a href='http://".$blog['domain']."/' target='_blank'>".$blog['domain']."</a></td>";
				if ($blog['archived'] == 0) {
					$html .= "<td><a href='?ENT_action=$ENT_action&action2=archiveblog&id=".$blog['blog_id']."#".($k+1)."'>Archiver</a></td>";				
				} else {
					$html .= "<td>Archivé !&nbsp;&nbsp;&nbsp;<a href='?ENT_action=$ENT_action&action2=unarchiveblog&id=".$blog['blog_id']."#".($k+1)."'><span class='lilipute'>Désarchiver</span></a></td>";				
				}
				$html .= "</tr>\n";
			}

		}
		$html .= "</table>\n</div></body></html>";
		echo $html;
		$mustDieAfterAction = true;
		break;


	//  --------------------------------------------------------------------------------
	//
	// Reprise de données pour les blogs, 
	//
	// ---------------------------------------------------------------------------------
	case 'REPRISE_DATA' :
		if (phpCAS::isAuthenticated()) {
			reprise_data_blogs();
		} else { 
			phpCAS::forceAuthentication(); 
		}
		$mustDieAfterAction = true;
		break;

	// --------------------------------------------------------------------------------
	//
	// Supprimer un blog
	//
	// --------------------------------------------------------------------------------
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

