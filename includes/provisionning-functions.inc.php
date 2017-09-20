<?php
/* 
// --------------------------------------------------------------------------------
// Fonctions de provisinning des blogs pour l'ENT laclasse.com.
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
		
		- PROF, 
		- ADM_ETB, CPE, PRINCIPAL : Deviennent administrateur de leur domaine si le domaine n'existe pas,
                       avec création de blog, sinon devient éditeur du blog existant.
                       
        - PRINCIPAL  : Si le blog est celui de son établissement : Devient administrateur de son domaine. 
                       Pour tous les autres blogs, voir la règle ci dessus (profs, cpe, adm_etb).
							   
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

$NewUser = false;


// --------------------------------------------------------------------------------
// fonction création d'un utilisateur
// --------------------------------------------------------------------------------
function createUserWP($p_username, $p_useremail, $userENT) {
	global $NewUser;
	$mailExists = false;
	$loginExists = false;
	$userExists = false;

	// Vérification de l'existance du compte, par rapport à l'email (donnée unique de Wordpress).
	$userId;
	$userRec = get_user_by( 'email', $p_useremail );
	if ($userRec) {
		$userId = $userRec->ID;
		$mailExists = true;
		$userExists = true;
	}

	$loginId = username_exists( $p_username );
	if ($loginId > 0 && !isset($userRec)) {
		$loginExists = true;
		$userExists = true;
		$userRec = get_userdata($loginId);
	}

	//
	// L'utilisateur existe déjà.
	//
	if ($userExists) { 
		// récupération des informations de l'utilisateur 
		$userId = $userRec->ID;
		// positionnement du booléen $NewUser
		$NewUser = false;
	}
	//
	// L'utilisateur n'existe pas.
	//
	else 
	{
		// création de l'utilisateur
		wpmu_signup_user($p_username, $p_useremail, "");
		
		// validation de l'utilisateur
		$wpError = wpmu_validate_user_signup($p_username, $p_useremail); 
		   	
 		// récupérer la clé d'activation du username créé
 		$validKey = get_activation_key($p_username);
 	
 		// activer le username nouvellement créé.
 		$activated = wpmu_activate_signup($validKey);
   	
		// récupération des information de l'utilisateur 
		$userRec = get_user_by('login',$p_username);
		$userId = $userRec->ID;

		// Positionnement du booléen $NewUser
		$NewUser = true;
			
		// Suppression du droit même minimum sur le blog des blogs.
		switch_to_blog(1);
		remove_user_from_blog($userId, 1, 1);
		restore_current_blog();
	}
	
	// maj des données utilisateur
	majWPUserMetData($userId, $userENT);
	
	// cookie d'authentification WP
	setWPCookie($userId);
	
	return $userId;
}

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

//	$wpBlogId = create_empty_blog( $domain, $path, $sitename, $site_id);

	// HACK: problème de droit lors de la création d'un blog MU
	// dans wp_x_options l'option_name = wp_user_roles est crée
	// alors qu'il faudrait l'option_name wp_x_user_roles
//    add_blog_option($wpBlogId, 'wp_'.$wpBlogId.'_user_roles', get_blog_option( $wpBlogId, 'wp_user_roles'));
    	
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


// --------------------------------------------------------------------------------
// fonction de mise à jour des données utilisateurs en fonction de la session php
// --------------------------------------------------------------------------------
function majWPUserMetData($p_userId, $userENT) {
	global $ent;
	// update user data
	update_user_meta($p_userId, 'uid_ENT', $userENT->id);

	$user_email = $userENT->id . '@noemail.lan';
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}

	wp_update_user(array(
		'ID' => $p_userId,
		'first_name' => $userENT->firstname, 
		'last_name' => $userENT->lastname,
		'display_name' => $userENT->lastname.' '.$userENT->firstname,
		'user_email' => $user_email
	));
}

// --------------------------------------------------------------------------------
// fonction pose du cookie d'authentification à WP.
// --------------------------------------------------------------------------------
function setWPCookie($p_usrId) {
	wp_set_auth_cookie( $p_usrId );
	wp_set_current_user( $p_usrId );
}

// --------------------------------------------------------------------------------
// fonction de rattachement d'un utilisateur à son blog de domaine.
// --------------------------------------------------------------------------------
function rattachUserToHisBlog($p_domain, $p_path, $p_site_id, $p_wpUsrId, $p_role) {
	global $NewUser;

	// Suppression du droit même minimum sur le blog des blogs.
	remove_user_from_blog($p_wpUsrId, 1, 1);
    
	// Pour tout nouvel utilisateur, on s'assure qu'il n'aura pas les droit de super admin.
	if ($NewUser)
		dontBeChief($p_wpUsrId);

	// Si le domaine est identique au blog principal, 
	// ce qui est le cas lorsqu'on provisionne sur l'adresse https://blogs.laclasse.com/wp-login.php,
	// On n'ajoute aucun droit sur le blog principal pour l'utilisateur.
	if ($p_domain != BLOGS_DOMAIN) {
		// Si le domaine existe
		if (domain_exists($p_domain, $p_path, $p_site_id)) {	
			// récupération du blog_id 
			$wpBlogId = get_blog_id_by_domain($p_domain);
					
			// Ajout des droits sur le blog. Si le user est nouveau OU qu'il n'a pas de droits sur le blog, 
			// on lui affecte un role, 
			// sinon on n'y touche pas, ce role peut avoir été changé manuellement dans 
			// le back-office de WordPress.
			$aUnRole = aUnRoleSurCeBlog($p_wpUsrId, $wpBlogId);
			if (!$aUnRole || $NewUser) 
				 add_user_to_blog($wpBlogId, $p_wpUsrId, $p_role);
		}
	}	
}

// --------------------------------------------------------------------------------
//  Fonction qui set l'utilisateur admin du réseau
// --------------------------------------------------------------------------------
function beChief($p_userId) {
	global $super_admins;

	if (!is_super_admin()) {
		// On supprime tout override de cette variable globale sinon grant_super_admin() ne fonctionne pas ....
		// Ca pue le BUG WORDPRESS ?????
		$super_admins = null;
		grant_super_admin($p_userId);
	}
}

// --------------------------------------------------------------------------------
//  Fonction qui unset l'utilisateur admin du réseau
// --------------------------------------------------------------------------------
function dontBeChief( $p_userId ) {
	global $super_admins;
	// On supprime tout override de cette variable globale sinon grant_super_admin() ne fonctionne pas .... 
	// Ca pue le BUG WORDPRESS ?????
	$super_admins = null;
	revoke_super_admin($p_userId);
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


