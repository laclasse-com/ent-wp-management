<?php
/*

Copyright (C) 2008 Casey Bisson

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307	 USA 

*/ 

/* 
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// Script de provisinning des blogs pour l'ENT laclasse.com.
// --------------------------------------------------------------------------------
 
	WORDPRESS ROLES
    *  Super Admin - Someone with access to the blog network administration features 
    				 controlling the entire network (See Create a Network).
    * Administrator - Somebody who has access to all the administration features
    * Editor - Somebody who can publish and manage posts and pages as well as manage 
    		   other users' posts, etc.
    * Author - Somebody who can publish and manage their own posts
    * Contributor - Somebody who can write and manage their posts but not publish them
    * Subscriber - Somebody who can only manage their profile 

// --------------------------------------------------------------------------------

	Les profils Laclasse.com suivants sont gérés :
	----------------------------------------------
		- ADMIN : Devient super-administreur de tout les blogs, pas de création de blog.
		
		- PROF, ADM_ETB, CPE : Devient administrateur de son domaine si le domaine n'existe pas,
							   avec création de blog, sinon devient éditeur du blog existant.
							   
		- ELEVE : Devient contributeur du blog existant dans le domaine, pas de création de blog.
		- PARENT : Devient souscripteur du blog existant, pas de création de blog.

	
	Les paramètres d'entrée sont les suivants :
	--------------------------------------------
		- blogname : Nom du sous-domaine de blog à créer. A ce nom est ajouté ".blogs.laclasse.com"
		- blogtype : Type de blog :
					- CLS : Blogs de classes.
					- ETB : BLogs d'établissement ( page d'établissement).
					- GRP : Blogs de groupe d'élèves
					- ENV : Blogs de groupes de travail.
					- USR : Blogs personnels.
		- debug :	mode traçage des actions (qui sont éralisées, mais sans redirection finale).
		- action : 	Par défaut l'action est le provisioning (création de user, de blog et redirection).
					SUPPRIMER_BLOG : suppression physique du blog.
		- idAncienBlogEnt : identifiant dans l'ENT de l'ancien blog à migrer vers WordPress. 
							Utilisé dans la création de l'article par défaut.

	Règle particulière pour le super Admin : 
	S'il arrive sur le wpmu sans les paramètres blogname et blogtype il est qd 
	même routé sur l'administration de tout les blogs.
/* --------------------------------------------------------------------------------
	Modifications :
		03/01/2011 - PGL :  Correction de la création de compte, ajout de vérification
							de l'existence de l'email.
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
*/
$logProvisioning = "";
$NewUser = false;

// --------------------------------------------------------------------------------
// fonction création d'un utilisateur
// --------------------------------------------------------------------------------
function createUserWP($p_username, $p_useremail, $p_role, $p_domain) {
	global $NewUser;
	$mailExists = false;
	$loginExists = false;
	$hasToUpdateUserData = true;
	$userExists = false;
	logIt("___Fonction : createUserWP");
	
	// Vérification de l'existance du compte, par rapport à l'email (donnée unique de Wordpress).
	$userId = get_user_id_from_string( $p_useremail );
	if ($userId > 0) {
		logIt("V&eacute;rification de l'existence du compte, par rapport &agrave; l'email '".$p_useremail."'.");
		$mailExists = true;
		$userExists = true;
		$userRec = get_userdata($userId);
		logIt("R&eacute;cup&eacute;ration des infos de l'utilisateur #$userId via son email.");
		
	}

	$loginId = username_exists( $p_username );
	if ($loginId > 0 && !isset($userRec)) {
		logIt("V&eacute;rification de l'existence du compte, par rapport &agrave; au login '".$p_username."'.");
		$loginExists = true;
		$userExists = true;
		$userRec = get_userdatabylogin($loginId);
		logIt("R&eacute;cup&eacute;ration des infos de l'utilisateur #$userId via son login.");
	}
	
	// Récupération des données du user s'il existe.
	if (($mailExists && !$loginExists) 
		&&
		($p_username != $userRec->user_login)) {
		$hasToUpdateUserData = false;
		logIt("L'utilisateur <b>$p_username</b> ne sera cr&eacute;&eacute;, 
		car un compte existe d&eacute;j&agrave; avec l'email '$p_useremail' : '".$userRec->user_login."'. 
		Les donn&eacute;es de '".$userRec->user_login."' ne seront pas mise &agrave; jour avec les donn&eacute;es de '$p_username', 
		mais l'authentification aura bien lieu avec le compte existant.");
		}


	if ($userExists) { // L'utilisateur existe déjà.
		// récupération des informations de l'utilisateur 

		$userId = $userRec->ID;
		logIt("L'utilisateur existe d&eacute;j&agrave; dans WP : id=".$userId);
		// positionnement du booléen $NewUser
		$NewUser = false;
	
	}
	else // L'utilisateur n'existe pas.
	{
		// création de l'utilisateur
		logIt("Cr&eacute;ation de l'utilisateur '".$p_username."'");
		wpmu_signup_user($p_username, $p_useremail, "");
   	
		// validation de l'utilisateur
		logIt("validation de l'utilisateur '".$p_username."'");
		$wpError = wpmu_validate_user_signup($p_username, $p_useremail); 
		
		if (is_wp_error($wpError) )
   			errMsg($wpError->get_error_message());
   	
   		// récupérer la clé d'activation du username créé
   		$validKey = get_activation_key($p_username);
   		logIt("validKey=".$validKey);
   	
   		// activer le username nouvellement créé.
   		$activated = wpmu_activate_signup($validKey);
   		logIt("Activation automatique du compte");

		if (is_wp_error($activated) ) {
   			errMsg($activated->get_error_message());
   		}
   	
		// récupération des information de l'utilisateur 
		$userRec = get_userdatabylogin($p_username);
		$userId = $userRec->ID;
		logIt("Id de l'utilisateur dans WP=".$userId);

		// Positionnement du booléen $NewUser
		$NewUser = true;
		logIt("#$userId est un nouvel utilisateur");
	}
	
	// maj des données utilisateur
	if ($hasToUpdateUserData) majWPUserMetData($userId);
	
	// cookie d'authentification WP
	setWPCookie($userId);
	
	logIt("___Fin Fonction : createUserWP.");
	
	return $userId;
}

// --------------------------------------------------------------------------------
// fonction création d'un nouvel article
// --------------------------------------------------------------------------------
function creerPremierArticle($domain, $wpBlogId, $pUserId, $pTypeBlog) {

	$idAncienBlogENT = $_REQUEST['idAncienBlogEnt'];
	
	logIt("Publication d'un article par d&eacute;faut.");
	
	switch ($pTypeBlog) {
		case 'CLS' : $libType = "de classe"; break;
		case 'ETB' : $libType = "d'&eacute;tablissement"; break;
		case 'GRP' : $libType = "de groupe"; break;
		case 'ENV' : $libType = "de groupe de travail"; break;
		case 'USR' : $libType = "personnel"; break;
		default : $libType = ""; break;
	}
	
	logIt("Cr&eacute;ation de l'article...");
	$texteArticle = "
	<p>Votre nouveau weblog est h&eacute;berg&eacute; par le <a href='http://www.rhone.fr/'>D&eacute;partement du Rh&ocirc;ne</a>, 
	en lien avec votre ENT <a href='http://www.laclasse.com/'>http://www.laclasse.com/</a></p>
	<p>Cette plateforme est int&eacute;gr&eacute;e &agrave; l'ENT et partage donc le m&ecirc;me service d'authentification. 
	<u>Vous avez donc deux fa&ccedil;ons d'y acc&eacute;der</u> : <br/>
	<ul>
		<li>En vous connectant &agrave; l'ENT,</li>
		<li>En utilisant directement l'adresse <a href='http://".$domain."/'>http://".$domain."/</a></li>
	</ul><br/>";
	
	logIt("idAncienBlogENT=$idAncienBlogENT");
	if ($idAncienBlogENT > 0 && isset($idAncienBlogENT)) {
		logIt("Insertion des urls de reprise des données.");
		$texteArticle .="
			<ul>
				<li>Si vous vous serviez d&eacute;j&agrave; d'un blog dans l'ENT avant l'installation de cette nouvelle plateforme, 
	vous avez la possibilit&eacute; de <a href='http://".$domain."/?ENT_action=MIGRER_DATA&pblogid=$idAncienBlogENT'>r&eacute;-importer vos articles</a>.</li>
				<li>Vous pouvez aussi d&eacute;cider de repartir &agrave; z&eacute;ro avec un blog vide. 
		Dans ce cas vous devez <a href='http://".$domain."/?ENT_action=MIGRER_DATA&pblogid=$idAncienBlogENT'>supprimer</a> les donn&eacute;es de cet ancien blog.</li>	
			</ul>";
	}
	// http://".SERVEUR_ENT."/pls/education/blogv2.supprimer_ancien_blog?pblogid=".$ancienBlogIENT
	$texteArticle .="</p>
	<p>Vous aurez tout le loisir de supprimer cet article en vous connectant sur 
	<a href='http://".$domain."/wp-admin/'>l'interface d'administration</a>.</p>";
	
	
	
	$post = array(
  		'ID' 				=> 0,				//Are you updating an existing post?
  		'comment_status'	=> 'open', 			// 'closed' means no comments.
  		'ping_status' 		=> 'closed',  		// 'closed' means pingbacks or trackbacks turned off
  		'post_author' 		=> $pUserId, 		//The user ID number of the author.
  		'post_content' 		=> $texteArticle,	//The full text of the post.
  		'post_status' 		=> 'publish', 		//Set the status of the new post. 
  		'post_title' 		=> "Bienvenue dans votre nouveau weblog $libType", //The title of your post.
  		'post_type' 		=> 'post' 			//Sometimes you want to post a page.
	);  
	// insertion du post.
	switch_to_blog($wpBlogId);
	wp_insert_post( $post, $wp_error );
	
}
// --------------------------------------------------------------------------------
// fonction création d'un nouveau blog
// --------------------------------------------------------------------------------
function creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog) {
	logIt("___Fonction : creerNouveauBlog");
	logIt("Cr&eacute;ation du blog pour le domaine '".$domain."'.");
	$wpBlogId = create_empty_blog( $domain, $path, $sitename, $site_id);
	logIt("Ce blog a pour id #".$wpBlogId.".");
	
	logIt("Param&eacute;trage des options par d&eacute;faut pour le blog #".$wpBlogId.".");
	// Ajout du role administrator sur le blog crée
	add_user_to_blog($wpBlogId, $wpUsrId, "administrator");
	logIt(" -> Ajout de l'utilisateur '".$username."' comme administrateur du blog #".$wpBlogId.".");
	
	// Ajout des options pour ce nouveau blog.
	add_blog_option( $wpBlogId, 'type_de_blog', $TypeDeBlog );
	logIt(" -> Ajout du type de blog '".$TypeDeBlog."'.");
	
	add_blog_option( $wpBlogId, 'idBLogENT', $_REQUEST['idAncienBlogEnt'] );
	logIt(" -> Ce blog est identifi&eacute; comme #".$_REQUEST['idAncienBlogEnt']." dans l'ENT.");
	
	add_blog_option( $wpBlogId, 'wordpress_api_key', AKISMET_KEY);
	logIt(" -> Ajout de la cle ASKIMET pour l'anti-spams sur les commentaires.");
	
	update_blog_option($wpBlogId, 'blogname', $sitename);
	logIt(" -> Nom du blog : '".$sitename."'.");
	
	update_blog_option($wpBlogId, 'admin_email', $user_email);
	logIt(" -> mail de l'administrateur : '".$user_email."'.");

	update_blog_option($wpBlogId, 'users_can_register', 0);
	logIt(" -> Suppression de l'inscription.");
	
	update_blog_option($wpBlogId, 'mailserver_url', 'smtp.laclasse.com');
	logIt(" -> Param&eacute;trage serveur smtp.");

	update_blog_option($wpBlogId, 'mailserver_login', substr($user_email, 0, strpos($user_email,'@')));
	logIt(" -> Param&eacute;trage login smtp : par d&eacute;faut la premi&egrave;re partie du mail.");

	update_blog_option($wpBlogId, 'rss_language', 'fr');
	logIt(" -> Param&eacute;trage langue FR pour les flux RSS..");

	update_blog_option($wpBlogId, 'language', 'fr');
	update_blog_option($wpBlogId, 'WPLANG', 'fr_FR');
	logIt(" -> Param&eacute;trage langue FR pour l'interface d'aministration et le blog.");

	update_blog_option($wpBlogId, 'blog_upload_space', 100);
	logIt(" -> Param&eacute;trage Quota du blog : 100M.");

	update_blog_option($wpBlogId, 'comment_registration', 1 );
	logIt(" -> Param&eacute;trage du mode de commentaire par d&eacute;faut : Il faut &ecirc;tre enregistrer pour pouvoir commenter.");
	
	$wpError = wpmu_validate_blog_signup();
	logIt("Validation du blog.");
	
	// Suppression du droit même minimum sur le blog des blogs.
 	remove_user_from_blog($wpUsrId, 1, 1);
	
	// Creer un premier article publié qui parle de la reprise des données.
	creerPremierArticle($domain, $wpBlogId, $wpUsrId, $TypeDeBlog);
  	
	if (is_wp_error($wpError) )	errMsg($wpError->get_error_message());
	logIt("___Fin Fonction : creerNouveauBlog");
}
// --------------------------------------------------------------------------------
// fonction de mise à jour des données utilisateurs en fonction de la sesison php
// --------------------------------------------------------------------------------
function majWPUserMetData($p_userId) {
	// Maj des données
	logIt("maj des donn&eacute;es de l'utilisateur");
	if (isset($_GET['debug']) && $_GET['debug'] == "O") print_r($_SESSION['phpCAS']['attributes']);
	update_user_meta($p_userId, 'uid_ENT', 			$_SESSION['phpCAS']['attributes']['uid']);
	update_user_meta($p_userId, 'etablissement_ENT', $_SESSION['phpCAS']['attributes']['ENTPersonStructRattachRNE']);
	update_user_meta($p_userId, 'display_name',  	$_SESSION['phpCAS']['attributes']['LaclasseNom'].' '.$_SESSION['phpCAS']['attributes']['LaclassePrenom']);
	update_user_meta($p_userId, 'first_name', 		$_SESSION['phpCAS']['attributes']['LaclassePrenom']);
	update_user_meta($p_userId, 'last_name', 		$_SESSION['phpCAS']['attributes']['LaclasseNom']);
	update_user_meta($p_userId, 'profil_ENT', 		$_SESSION['phpCAS']['attributes']['LaclasseProfil']);
	// Classe de l'élève
	if ($_SESSION['phpCAS']['attributes']['LaclasseProfil'] == "ELEVE") {
		logIt("classe de l'utilisateur");
		update_user_meta($p_userId, 'classe_ENT', $_SESSION['phpCAS']['attributes']['ENTEleveClasses']);
	}
}

// --------------------------------------------------------------------------------
// fonction pose du cookie d'authentification à WP.
// --------------------------------------------------------------------------------
function setWPCookie($p_usrId) {
	// authentification
	logIt("Authentification userId #$p_usrId.");
	wp_set_auth_cookie( $p_usrId );
}

// --------------------------------------------------------------------------------
// fonction de rattachement d'un utilisateur à son blog de domaine.
// --------------------------------------------------------------------------------
function rattachUserToHisBlog($p_domain, $p_path, $p_site_id, $p_wpUsrId, $p_role) {
	global $NewUser;
	// Suppression du droit même minimum sur le blog des blogs.
    remove_user_from_blog($p_wpUsrId, 1, 1);

	// Si le domaine existe
	if (domain_exists($p_domain, $p_path, $p_site_id)) {
		logIt("Le domaine '".$p_domain."' existe.");
		
		// récupération du blog_id 
		$wpBlogId = get_blog_id_by_domain($p_domain);
		logIt("get_blog_id_by_domain a renvoy&eacute; #".$wpBlogId.".");
				
		// Ajout des droits sur le blog. Si le user est noueau, on lui affecte un role, 
		// sinon on n'y touche pas, ce role peut avoir été changé manuellement dans 
		// le back-office de WordPress.
		if ($NewUser) {
			logIt("Ajout du role '".$p_role."' sur le blog #".$wpBlogId.".");
			add_user_to_blog($wpBlogId, $p_wpUsrId, $p_role);
		}
		else logIt("L'utilisateur #".$p_wpUsrId." n'est pas nouveau. on ne modifie pas son role sur le blog #".$wpBlogId.".");

	}
	else {
		// le domaine n'existe pas : cette connexion ne vient sans doute pas de laclasse.com => message d'erreur 
		errMsg("Vous n'avez pas le profil requis pour cr&eacute;er un blog");
	}
}

// --------------------------------------------------------------------------------
// fonction de rattachement d'un super Admin au blog principal.
// --------------------------------------------------------------------------------
function rattachSuperUserToTheBLog($p_userId, $p_role) {
		// On s'occupe du profil
		if ($_SESSION['phpCAS']['attributes']['LaclasseProfil'] == "ADMIN" && $p_role == "administrator") {
			// Ajout des droits super administrateur sur le blog des blogs.
			add_user_to_blog(1, $p_userId, $p_role);
			logIt("Ajout des droits administrateur sur le blog des blogs");
		
			if (grant_super_admin($p_userId)) 
			 	logIt("Ajout des droits super administrateur sur le blog des blogs");
			else logIt("Pas de droits SUPER ADMIN, mais je ne sais pas pourquoi...");
		}
}

// --------------------------------------------------------------------------------
//  Fonction qui renvoie le dernier blog_id créé par l'utilisateur en fct de son domaine
// --------------------------------------------------------------------------------
function get_blog_id_by_domain( $domain ) {
	global $wpdb;
	$rowBlog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->blogs WHERE domain = %s AND spam = '0' AND deleted = '0' and archived = '0'", $domain)  );
	return $rowBlog->blog_id;
}

// --------------------------------------------------------------------------------
// Fonction qui renvoie la clé d'activation du username juste créé
// --------------------------------------------------------------------------------
function get_activation_key($user) {
	global $wpdb;
	$lastDateIn = $wpdb->get_var( $wpdb->prepare( "SELECT max(registered) FROM $wpdb->signups WHERE user_login = %s AND active = '0'", $user));
	$myKey = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM $wpdb->signups WHERE user_login = %s AND active = '0' and registered = %s", $user, $lastDateIn));
	return $myKey;
}


// --------------------------------------------------------------------------------
// Fonction de'affichage dun message d'erreur.
// --------------------------------------------------------------------------------
function errMsg($msg){
	if (isset($_GET['debug']) && $_GET['debug'] == "O") logIt("<span style='color:red;'>".$msg."</span>");
	else
		endMessage('
		<h2>Oops...</h2>
		<p>Il semble qu\'il se soit produit une erreur.</p>
		<p>'.$msg.'.</p>
		<p>Vous pouvez contacter le support 
		<a href="mailto:supportblog@laclasse.com" target="_blank">supportblog@laclasse.com</a>.</p>'
		);
}

// --------------------------------------------------------------------------------
// fonction de log des traitements
// --------------------------------------------------------------------------------
function logIt($msg) {
	global $logProvisioning;
	if (isset($_GET['debug']) && $_GET['debug'] == "O")	$logProvisioning .= '<li>'.$msg.'</li>';
}

// --------------------------------------------------------------------------------
// fonction de redirection
// --------------------------------------------------------------------------------
function redirection($p_domaine) {
	global $logProvisioning;
	// Lorsqu'on arrive ici, tout s'est bien passé, les blogs et les users sont créés
	// On redirige donc vers le bon domaine.
	logIt("Ici on va rediriger vers <a href='http://".$p_domaine."/?ENT_action=IFRAME'>http://".$p_domaine."/?ENT_action=IFRAME</a>");
	
	if (isset($_GET['debug']) && $_GET['debug'] == "O") {
		endMessage("<ul>".$logProvisioning ."</ul>". "Mode DEBUG activ&eacute; : Pas de redirection.");
	}
	else {
		// Si le blog est de type Etablissement (ETB) on enlève la sidebar
		// Car la place dans la page est étroite.
		if ($_REQUEST["blogtype"] == 'ETB')	header('Location: http://'.$p_domaine.'/?ENT_action=IFRAME&ENT_display=CENTRAL');
		else header('Location: http://'.$p_domaine.'/?ENT_action=IFRAME');
	}
}


// --------------------------------------------------------------------------------
//  Fonction d'affichage d'un message de retour d'une action de pilotage.
// --------------------------------------------------------------------------------
function endMessage($pmessage){
    message($pmessage);
	exit;
}

// --------------------------------------------------------------------------------
//  T R A I T E M E N T   D E   P R O V I S I O N I N G
// --------------------------------------------------------------------------------
// setup the WordPress environment
define( "WP_INSTALLING", true );
require_once( './wp-load.php' );
require( 'wp-blog-header.php' );
require_once( ABSPATH . WPINC . '/registration.php' );
require_once( ABSPATH . 'wp-admin/includes/ms.php' );
global $domain, $base; // these variables aren't reliable. It's actually better to force them as you'll see below.

// no SSL validation for the CAS server
phpCAS::setNoCasServerValidation();

// ensure the user is authenticated via CAS
if( !phpCAS::isAuthenticated() || !$username = phpCAS::getUser() ){
	wpCAS::authenticate();
	die( 'requires authentication' );
}

// we don't want crawlers to index this page, if they ever get here.
function signuppageheaders() {
	echo "<meta name='robots' content='noindex,nofollow' />\n";
}
add_action( 'wp_head', 'signuppageheaders' ) ;
add_filter("redirect_to", get_site_url()."?ENT_action=IFRAME"); 

/* récupérer les variable du jeton CAS */
logIt("r&eacute;cup&eacute;rer les variables du jeton CAS"); 

/////////// Est-ce nécessaire ? On l'a fait avant logiquement ...
////////setCASdataInSession();
$LaclasseAttributes = $_SESSION['phpCAS']['attributes'];

if (isset($_GET['debug']) && $_GET['debug'] == "O") print_r($LaclasseAttributes);

$laclasseUserUid 		= $LaclasseAttributes['uid'];
$laclasseUserCodeRne 	= $LaclasseAttributes['ENTPersonStructRattachRNE'];
$laclasseUserId 		= $LaclasseAttributes['ENT_id'];
$laclasseUserProfil		= $LaclasseAttributes['LaclasseProfil'];
$laclasseUserClasse		= $LaclasseAttributes['ENTEleveClasses'];
$laclasseUserNivClasse	= $LaclasseAttributes['ENTEleveNivFormation'];
$laclasseUserMail		= $LaclasseAttributes['LaclasseEmail'];
$laclasseUserMailAca	= $LaclasseAttributes['LaclasseEmailAca'];

logIt("-> uid=".$laclasseUserUid);
logIt("-> rne=".$laclasseUserCodeRne);
logIt("-> userId=".$laclasseUserId);
logIt("-> profil=".$laclasseUserProfil);
logIt("-> Classe=".$laclasseUserClasse);
logIt("-> Niveau=".$laclasseUserNivClasse);
logIt("-> mail=".$laclasseUserMail);
logIt("-> mailAca=".$laclasseUserMailAca);

//
// Vérification des paramètres d'entrée OBLIGATOIRES si l'utilisateur n'est pas admin
//
error_reporting("E_ALL");

//// => blogname
logIt("V&eacute;rification des param&egrave;tres.");
if (!isset($_GET['blogname']) || $_GET['blogname'] == "") {
	// Si l'utilisateur existe déjà, on le route vers son blog en posant le cookie d'authentification.		
	if ( username_exists( $username ) ) { 
		if ($_REQUEST['ENT_action'] == 'IFRAME') 
			$qry = '?ENT_action=IFRAME';
		    $redir = home_url().$qry;
	header("Location: ".$redir);
	die();
/*
		// récupération de l'id de l'utilisateur 
		$userRec = get_userdatabylogin($username);
		$userId = $userRec->ID;
		$tabBlogs = get_blogs_of_user($userId);
		
		$messagePlusieursBlogs  = "<h2>Vous &ecirc;tes rattach&eacute; &agrave; plusieurs blogs. O&ugrave; aller ?</h2><ul>";
		foreach($tabBlogs as $blog) {
			$messagePlusieursBlogs .=  "<li>&nbsp;<a href='".$blog->siteurl.$blog->path."'>".$blog->blogname."</a>&nbsp;</li>";
		}
		$messagePlusieursBlogs .=  "</ul><br />";
		endMessage($messagePlusieursBlogs);
		// Et la on continue d'afficher le site...
*/
	}
	else errMsg("Le paramètre 'blogname' n'est pas renseigné.");
}

logIt("blogname=".$_GET['blogname']);
	
if ($laclasseUserProfil != "ADMIN") {
	//// => blogtype
	if (!isset($_GET['blogtype']) || $_GET['blogtype'] == "") {
		errMsg("Le paramètre 'blogtype' n'est pas renseigné.");
	}
	if (!in_array($_GET['blogtype'], array("CLS", "ENV", "ETB", "GRP", "USR"))) {
		errMsg("La valeur du param&egrave;tre 'blogtype' doit &ecirc;tre prise dans la liste ['CLS', 'ENV', 'ETB', 'GRP', 'USR'].");
	}
	logIt("blogtype=".$_GET['blogtype']);
}

//
// Setting des variables couramment utilisées dans les api WP
//
$site_id = 1;

/*
	Set the information about the user and his/her new blog.
	Make changes here as appropriate for your site.

*/
if ($laclasseUserMail != "" ) $user_email = $laclasseUserMail; 
else if ($laclasseUserMailAca != "" && in_array($laclasseUserProfil, array("PROF","ADM_ETB","ADMIN","CPE"))) 
		$user_email = $laclasseUserMailAca; 
	 else $user_email = ""; 

$username = strtolower($username);

/*
	Nom du site à créer.
	La plateforme laclasse DOIT générer un nom de site unique.
	A défaut d'un nom passé en get, le user name est utilisé.
		
*/

if (isset($_GET['blogname']) && $_GET['blogname'] != "") $sitename = $_GET['blogname'];
else $sitename = str_replace('_','-',$username);

$sitename = strtolower($sitename);

/*
	Type de blog à créer : ETB, CLS, GRP, ENV, ou USR.
*/
if (isset($_GET['blogtype']) && $_GET['blogtype'] != "") $TypeDeBlog = $_GET['blogtype'];
else $TypeDeBlog = "USR";



/*
	We can't use the global $domain, it turns out, because it isn't set to the 
	base domain, but to the subdomain of whatever blog the user is currently visiting.
*/

$domain = $sitename . '.' .BLOG_DOMAINE; //'.blogs.laclasse.lan';

$path = '/'; 


logIt("site_id=".$site_id);
logIt("username=".$username);
logIt("sitename=".$sitename);
logIt("TypeDeBlog=".$TypeDeBlog);
logIt("domain=".$domain);
logIt("path=".$path);


	// --------------------------------------------------------------------------------
	// Si l'utilisateur est SUPER ADMIN de laclasse.com, il a des droits sur le bakoffice général de WP.
	// donc admin sur tout les blogs.
	// --------------------------------------------------------------------------------
	if ($laclasseUserProfil == "ADMIN") {
		$wpUsrId = createUserWP($username, $user_email, "administrator", $domain);
		// Si le domaine existe
		if (domain_exists($domain, $path, $site_id)) {	
			// L'utilisateur n'est pas le premier à venir pour ce domaine, 
			// il est par défaut "administrateur de la plateforme" car il est super admin dans l'ENT
			rattachSuperUserToTheBLog($wpUsrId, "administrator");
		}
		else {
			// ici le domaine n'existe pas : 
			// le premier qui arrive est administrateur du nouveau blog !
			logIt("le domaine '".$domain."' n'existe pas.");
			// Maintenant il faut créer un blog.
			creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog);
		}
	}

	// --------------------------------------------------------------------------------
	// Profil des personnel de l'éducation nationale PROF or ADM_ETB or CPE
	//	Ces profils peuvent créer des blogs et sont donc administrateur du blog créé.
	// Si le blog existe déjà, alors l'utilisateur est rattaché au blog avec des droits
	// d'éditeur ("Editor") -> peut ecrire, valider des posts et valider les posts des autres.
	// --------------------------------------------------------------------------------
	if (in_array($laclasseUserProfil, array("PROF","ADM_ETB","CPE"))) {

		// Si le domaine existe
		if (domain_exists($domain, $path, $site_id)) {	
			// L'utilisateur n'est pas le premier à venir pour ce domaine, 
			// il est par défaut "éditeur" car c'est un un adulte de l'Ed.Nat.
			$wpUsrId = createUserWP($username, $user_email, "editor", $domain);
			rattachUserToHisBlog($domain, $path, $site_id, $wpUsrId, "editor");
		}
		else {
			// ici le domaine n'existe pas : 
			// le premier qui arrive est administrateur du nouveau blog !
			logIt("le domaine '".$domain."' n'existe pas.");
			$wpUsrId = createUserWP($username, $user_email, "administrator", $domain);
			// Maintenant il faut créer un blog.
			creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog);
   		}
	}

	// --------------------------------------------------------------------------------
	// Profil ELEVE : Il est contributeur sur le blog mais ne doit pas pouvoir publier.
	// Ses publication doivent être validées.
	// --------------------------------------------------------------------------------
	if ($laclasseUserProfil == "ELEVE") {
		$wpUsrId = createUserWP($username, $user_email, "contributor", $domain);
		rattachUserToHisBlog($domain, $path, $site_id, $wpUsrId, "contributor");
	}

	// --------------------------------------------------------------------------------
	// Profil PARENT : Il est uniquement lecteur du blog et peut gérer son profil.	
	// le profil WP s'appelle "Subscriber"
	// --------------------------------------------------------------------------------
	if ($laclasseUserProfil == "PARENT"){
		$wpUsrId = createUserWP($username, $user_email, "subscriber", $domain);	
		rattachUserToHisBlog($domain, $path, $site_id, $wpUsrId, "subscriber");
	}

	// --------------------------------------------------------------------------------
	// Pour tous les profils 
	// --------------------------------------------------------------------------------
	logIt("Redirection");
	// rediretion si le script n'est pas en mode débug.
	redirection($domain);

?>