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
$logProvisioning = "";
$NewUser = false;

// --------------------------------------------------------------------------------
// Récupérer une données dan le jeton, à défaut en GET
// --------------------------------------------------------------------------------
function getAttr($TokenAttrName, $defaultValue= "") {
  return isset($_SESSION['phpCAS']['attributes'][$TokenAttrName]) ? $_SESSION['phpCAS']['attributes'][$TokenAttrName] : $defaultValue; 
}

// --------------------------------------------------------------------------------
// setter une données dan la session $_SESSION
// --------------------------------------------------------------------------------
function setAttr($TokenAttrName, $value= "") {
  $_SESSION['phpCAS']['attributes'][$TokenAttrName] = $value; 
}

// --------------------------------------------------------------------------------
// test existence attribut
// --------------------------------------------------------------------------------
function existsAttr($TokenAttrName) {
  return isset($_SESSION['phpCAS']['attributes'][$TokenAttrName]);
}

// --------------------------------------------------------------------------------
// tester sur attribut vide
// --------------------------------------------------------------------------------
function emptyAttr($TokenAttrName) {
  return ($_SESSION['phpCAS']['attributes'][$TokenAttrName] == "");
}

// --------------------------------------------------------------------------------
// fonction création d'un utilisateur
// --------------------------------------------------------------------------------
function createUserWP($p_username, $p_useremail, $p_role, $p_domain) {
	global $NewUser;
	$mailExists = false;
	$loginExists = false;
	$userExists = false;
	$hasToUpdateUserData = true;
	logIt("___/ Fonction : createUserWP");
		
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
		logIt("V&eacute;rification de l'existence du compte, par rapport au login '".$p_username."'.");
		$loginExists = true;
		$userExists = true;
		//$userRec = get_user_by('login',$loginId);
		$userRec = get_userdata($loginId);
		logIt("R&eacute;cup&eacute;ration des infos de l'utilisateur #$loginId via son login.");
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

  //
  // L'utilisateur existe déjà.
	//
	if ($userExists) { 
		// récupération des informations de l'utilisateur 

		$userId = $userRec->ID;
		logIt("L'utilisateur existe d&eacute;j&agrave; dans WP : id=".$userId);
		// positionnement du booléen $NewUser
		$NewUser = false;
	
	}
	//
	// L'utilisateur n'existe pas.
	//
	else 
	{
		// création de l'utilisateur
		logIt("Cr&eacute;ation de l'utilisateur '".$p_username."'");
		wpmu_signup_user($p_username, $p_useremail, "");
   	
		// validation de l'utilisateur
		logIt("validation de l'utilisateur '".$p_username."'");
		$wpError = wpmu_validate_user_signup($p_username, $p_useremail); 
		
		if (is_wp_error($wpError) ) {
   			errMsg($wpError->get_error_message());
   	}
   	
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
		$userRec = get_user_by('login',$p_username);
		$userId = $userRec->ID;
		logIt("Id de l'utilisateur dans WP=".$userId);

		// Positionnement du booléen $NewUser
		$NewUser = true;
		logIt("#$userId est un nouvel utilisateur");
	}
	
	// maj des données utilisateur
	logIt("hasToUpdateUserData='$hasToUpdateUserData'");
	if ($hasToUpdateUserData) majWPUserMetData($userId);
	
	// cookie d'authentification WP
	setWPCookie($userId);
	
	logIt("___/ Fin Fonction : createUserWP.");
	
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
function creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $EtbUAI) {
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
	
	// Si ce type de blog est un blog d'établissement, on enregistre le code rne de cet établissement
	if ($TypeDeBlog == 'ETB') {
	   add_blog_option( $wpBlogId, 'etablissement_ENT', $EtbUAI );
	   logIt(" -> Ajout de l'option 'etablissement_ENT'='".$EtbUAI."'.");
	}
	
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
  global $ent;
	// Maj des données
	logIt("maj des donn&eacute;es de l'utilisateur");
	update_user_meta($p_userId, 'uid_ENT', 	         getAttr('uid'));
	update_user_meta($p_userId, 'etablissement_ENT', getAttr('ENTPersonStructRattachRNE'));
	update_user_meta($p_userId, 'profil_ENT', 		   getAttr('LaclasseProfil'));
	update_user_meta($p_userId, 'nom_ENT', 		       $ent);

  // FIXME : Comprends pas pourquoi $_SESSION ne comporte pas tojours les valeurs que je lui  mets.
  if (!emptyAttr('LaclassePrenom') && !emptyAttr('LaclasseNom')) {
     wp_update_user( 
            array (
              'ID' => $p_userId, 
              'first_name' => getAttr('LaclassePrenom'), 
              'last_name' => getAttr('LaclasseNom'),
              'display_name' => getAttr('LaclasseNom').' '.getAttr('LaclassePrenom')
                   )
     );
  }
	// Classe de l'élève
	if (getAttr('LaclasseProfil') == "ELEVE") {
		logIt("classe de l'utilisateur");
		update_user_meta($p_userId, 'classe_ENT', getAttr('ENTEleveClasses'));
	}
}

// --------------------------------------------------------------------------------
// fonction pose du cookie d'authentification à WP.
// --------------------------------------------------------------------------------
function setWPCookie($p_usrId) {
	// authentification
	logIt("Authentification userId #$p_usrId.");
	wp_set_auth_cookie( $p_usrId );
	wp_set_current_user( $p_usrId );
}

// --------------------------------------------------------------------------------
// fonction de rattachement d'un utilisateur à son blog de domaine.
// --------------------------------------------------------------------------------
function rattachUserToHisBlog($p_domain, $p_path, $p_site_id, $p_wpUsrId, $p_role) {
	global $NewUser;
    logIt("___/ Fonction : rattachUserToHisBlog");	
    // Suppression du droit même minimum sur le blog des blogs.
    remove_user_from_blog($p_wpUsrId, 1, 1);

	// Si le domaine existe
	if (domain_exists($p_domain, $p_path, $p_site_id)) {
		logIt("Le domaine '".$p_domain."' existe.");
		
		// récupération du blog_id 
		$wpBlogId = get_blog_id_by_domain($p_domain);
		logIt("get_blog_id_by_domain a renvoy&eacute; #".$wpBlogId.".");
				
		// Ajout des droits sur le blog. Si le user est nouveau OU qu'il n'a pas de droits sur le blog, 
		// on lui affecte un role, 
		// sinon on n'y touche pas, ce role peut avoir été changé manuellement dans 
		// le back-office de WordPress.
		$aUnRole = aUnRoleSurCeBlog($p_wpUsrId, $wpBlogId);
		logIt("aUnRoleSurCeBlog a renvoy&eacute; '".(($aUnRole)? "true" : "false")."'.");
		if (!$aUnRole || $NewUser) {
			 logIt("Ajout du role '".$p_role."' sur le blog #".$wpBlogId.".");
			 add_user_to_blog($wpBlogId, $p_wpUsrId, $p_role);
		}
		else logIt("L'utilisateur #".$p_wpUsrId." a d&eacute;j&agrave; un role sur le blog #".$wpBlogId." : on ne modifie pas son role.");

	}
	else {
		// le domaine n'existe pas : cette connexion ne vient sans doute pas de laclasse.com => message d'erreur 
		errMsg("Vous n'avez pas le profil requis pour cr&eacute;er un blog");
	}
    logIt("___/ Fin Fonction : rattachUserToHisBlog");	
}

// --------------------------------------------------------------------------------
// fonction de rattachement d'un super Admin au blog principal.
// --------------------------------------------------------------------------------
function rattachSuperUserToTheBLog($p_userId, $p_role) {
		// On s'occupe du profil
		if (getAtttr('LaclasseProfil') == "ADMIN" && $p_role == "administrator") {
			// Ajout des droits super administrateur sur le blog des blogs.
			add_user_to_blog(1, $p_userId, $p_role);
			logIt("Ajout des droits administrateur sur le blog des blogs");
			logIt("is_super_admin() renvoie ".is_super_admin());
		
			if (!is_super_admin()) {
				if (grant_super_admin($p_userId) ) 
			 		logIt("Ajout des droits super administrateur sur le blog des blogs");
			 	else logIt("Pas de droits SUPER ADMIN, mais je ne sais pas pourquoi...");
			 	}
			else logIt("L'utilisateur est d&eacute;j&agrave; superAdmin.");
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
// fonction de redirection
// --------------------------------------------------------------------------------
function redirection($p_domaine) {
	global $logProvisioning;
	$scriptName = ""; 
	$qry = Array();
	// S'occuper de l'embeded
	if ($_REQUEST['ENT_action'] == 'IFRAME' || isset($_REQUEST['blogname'])) {
   logIt("On est dans une IFRAME.");
    $qry[] = 'ENT_action=IFRAME';
  }
  
  // Si le blog est un blog d'établisement on supprime la 2° colonne
  if ($_REQUEST["blogtype"] == 'ETB') {
    $qry[] = 'ENT_display=CENTRAL';
  }
  
  // Si le paramètre 'ent' est défini, il faut le reporter pour le log out
  if (isset($_REQUEST['ent']) || $_REQUEST['ent'] != 'laclasse') {
    $qry[] = "ent=".$_REQUEST['ent'];
  }

  $query = '?';
  foreach($qry as $v) {
    $query .= $v . "&";
  }
  $query = substr($query, 0, -1);
  
  // Lorsqu'on arrive ici, tout s'est bien passé, les blogs et les users sont créés
	// On redirige donc vers le bon domaine.
	logIt("Ici on va rediriger vers <a href='http://".$p_domaine.$scriptName.$query."'>http://".$p_domaine.$scriptName.$query."</a>");
	
	if (isset($_GET['debug']) && $_GET['debug'] == "O") {
		endMessage("<ul>".$logProvisioning ."</ul>". "Mode DEBUG activ&eacute; : Pas de redirection.");
	}
	else {
		// Si le blog est de type Etablissement (ETB) on enlève la sidebar
		// Car la place dans la page est étroite.
		header('Location: http://'.$p_domaine.$scriptName.$query);
	}
}

// --------------------------------------------------------------------------------
//  Formulaire de saisie de données complémentaires pour un sso extérieur, avec un
//  jeton de type 3 (annexe sso du SDET).
//  Ce formulaire va permettre de mettre en session les bonnes variables avant de provisionner 
//  le compte.
// --------------------------------------------------------------------------------
function formulaire_sso($pSiteName) {
  $complement_passer_etape = isset($_REQUEST['complement_passer_etape']) ? $_REQUEST['complement_passer_etape'] : "";
  $complement_first_name = isset($_REQUEST['complement_first_name']) ? $_REQUEST['complement_first_name'] : "";
  $complement_last_name = isset($_REQUEST['complement_last_name']) ? $_REQUEST['complement_last_name'] : "";
  $complement_email = isset($_REQUEST['complement_email']) ? $_REQUEST['complement_email'] : "";
  $complement_profil = isset($_REQUEST['complement_profil']) ? $_REQUEST['complement_profil'] : "";
  
  setSessionlaclasseProfil();
  $laclasseProfil =  getAttr('LaclasseProfil', "");
  $laclasseNom =  getAttr('LaclasseNom', "");
  $laclassePrenom =  getAttr('LaclassePrenom', "");
  
  // Complement pour les PEN
  if ($laclasseProfil != "ELEVE" && $laclasseProfil != "PARENT" ) {
    $laiusEmail = "Saississez ici votre email acad&eacute;mique.";
  }
  
  //
  // Affichage du formulaire
  //
  if (
        $complement_first_name == "" 
        && $complement_passer_etape == "" 
        && $complement_email == ""
        && $complement_passer_etape == ""   
    ) {
    message("<h1>C'est votre premi&egrave;re connexion sur <strong>".$pSiteName."</strong></h1><h4>Merci de renseigner les champs suivants :</h4>
          <form id='your-profile' method='post' action='".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."'>
            <table class='form-table'>
              <tbody>
              <tr>
                <th>
                  <label for='complement_first_name'>Pr&eacute;nom</label>
                </th>
                <td>
                  <input id='complement_first_name' class='regular-text' type='text' value='".$laclassePrenom."' name='complement_first_name'>
                </td>
              </tr>
              <tr>
                <th>
                  <label for='complement_last_name'>Nom</label>
                </th>
                <td>
                  <input id='complement_last_name' class='regular-text' type='text' value='".$laclasseNom."' name='complement_last_name'>
                </td>
              </tr>
              <tr>
                <th>
                  <label for='complement_profil'>Profil</label>
                </th>
                <td>
                  <div class='regular-text'><em>".$laclasseProfil."</em></div>
                  <input id='complement_profil' type='hidden' value='".$laclasseProfil."' name='complement_profil'>
                </td>
              </tr>
              <tr>
                <th>
                  <label for='complement_email'>E-mail</label>
                </th>
                <td>
                  <input id='complement_email' class='regular-text' type='text' value='' name='complement_email'>
                  <br/><small>".$laiusEmail."</small>
                </td>
              </tr>
              </tbody>
            </table>
            
            <p class='submit'>
              <!--input id='complement_passer_etape' class='button-secondary' type='submit' value='Passer cette &eacute;tape' name='complement_passer_etape'-->
              <input id='submit' class='button-primary' type='submit' value='Valider' name='submit'>
            </p>
          </form>  
            <hr/>Ces informations ne vous seront plus re-demand&eacute;es par la suite, mais vous y aurez acc&egrave;s en consultant votre profil dans WordPress.");
      die();
      // On ne va pas plus loin
  }
  //
  // traitement des données
  //
  else 
  {
    setAttr('ENT_id', getAttr('uid')); // Pas de login dans ce jeton T3Sdet
    setAttr('LOGIN', getAttr('uid')); // Pas de login dans ce jeton T3Sdet
    setAttr('LaclasseProfil', $complement_profil);
    // Si l'utilisateur a décidé de passer cette étape, il faut enregistrer des valeurs par défaut dans la session
    if ($complement_passer_etape != "") {
      setAttr('LaclasseEmail', "no_mail_" .substr( md5( uniqid( microtime( ))), 0, 6 ) . "@laclasse.com");
      setAttr('LaclasseNom', getAttr('uid'));
      setAttr('LaclassePrenom', "");
    }
    else // on met en session les nouveaux paramètres
    {
      setAttr('LaclasseEmail', $complement_email);
      setAttr('LaclasseNom', $complement_last_name);
      setAttr('LaclassePrenom', $complement_first_name);
    }
    // Si l'utilisateur a rentré un mail académique, on lui passe son profil de "INVITE" à "PROF"
    $pos = strrpos(getAttr('LaclasseEmail'), "ac-lyon.fr");
    if ($pos !== false) { 
      setAttr('LaclasseEmailAca', getAttr('LaclasseEmail'));
  		setAttr('LaclasseProfil', "PROF");
    }
  }
}

// --------------------------------------------------------------------------------
// Une fonction de mise en session du profil, quel que soit le type de jeton CAS
// --------------------------------------------------------------------------------
function setSessionlaclasseProfil() {
  // si le laclasseProfil n'est pas renseigné, on ,le renseigne.
  if (!existsAttr('LaclasseProfil') || emptyAttr('LaclasseProfil') ) {
    $profil =  getAttr('ENTPersonProfils', "");
    switch ($profil) {
      case 'National_1' : // $libProfil = "&eacute;l&egrave;ve";
                          $laclasseProfil = 'ELEVE';
                          break;
      case 'National_2' : // $libProfil = "parent";
                          $laclasseProfil = 'PARENT';
                          break;
      case 'National_3' : // $libProfil = "enseignant";
                          $laclasseProfil = 'PROF';
                          break;
      case 'National_4' : // $libProfil = "principal ou adjoint";
                          $laclasseProfil = 'PRINCIPAL';
                          break;
      case 'National_5' : // $libProfil = "personnel de vie scolaire"; 
                          $laclasseProfil = 'CPE';
                          break;
      case 'National_6' : // $libProfil = "personnel administratif";
                          $laclasseProfil = 'INVITE';
                          break;
      case 'National_7' : // $libProfil = "personnel du rectorat";
                          $laclasseProfil = 'INVITE';
                          break;
      default : $libProfil = "invit&eacute;";
                $laclasseProfil = 'INVITE';
                break;
    }
    setAttr('LaclasseProfil', $laclasseProfil);
  }
}

// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
//
//              T R A I T E M E N T   D E   P R O V I S I O N I N G
//
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
// --------------------------------------------------------------------------------
require_once( './wp-load.php' );
require( 'wp-blog-header.php' );
require_once( ABSPATH . WPINC . '/registration.php' );
require_once( ABSPATH . 'wp-admin/includes/ms.php' );
global $domain, $base;

// $url est l'url vers laquelle on va rediriger
$url = "";
// Complément de query http
$qry = "";
// nom du site, s'il faut le créer
$sitename = "";
// Le domaine est le sitename+ le domai,e de bog
$domain = "";
// Le path sert à créer les blogs.
$path = '/'; 
// Le username est donné par l'authentification CAS.
$username = "";
// Setting des variables couramment utilisées dans les api WP
$site_id = 1;

// no SSL validation for the CAS server
phpCAS::setNoCasServerValidation();

// ensure the user is authenticated via CAS
if( !phpCAS::isAuthenticated() || !$username = strtolower(phpCAS::getUser()) ){
	wpCAS::authenticate();
	die( 'requires authentication' );
}

// we don't want crawlers to index this page, if they ever get here.
function signuppageheaders() {
	echo "<meta name='robots' content='noindex,nofollow' />\n";
}
add_action( 'wp_head', 'signuppageheaders' ) ;
add_filter("redirect_to", get_site_url()."?ENT_action=IFRAME"); 

// activer l'affichage des erreurs
error_reporting("E_ALL");

//
// Définition du routage
//

logIt("____________________D&eacute;finition Routage____________________");
// si blogname n'est pas renseigné, on le calcule.
if (isset($_REQUEST['blogname']) && $_REQUEST['blogname'] != "") {
  logIt("Blogname est renseign&eacute;.");
  $url = str_replace("http://", "", $_REQUEST['blogname'].".".BLOG_DOMAINE);
  $sitename = $_GET['blogname'];
}
else {
  logIt("Blogname n'est pas renseign&eacute;.");
  $url = str_replace("http://", "", home_url());
  $sitename = str_replace(".".BLOG_DOMAINE, "", $url);
}

logIt("url : '".$url."'.");
// On regarde si le blog existe car s'il faut le créer, il nous faut le blogtype.
$wpBlgId = get_blog_id_by_domain($url);
if ($wpBlgId > 0) {
  logIt("le blog existe, #".$wpBlgId.".");
} 
else {
  logIt("le blog n'existe pas.");
  // on doit vérifier que'on a bien blogtype sinon, pas de création possible... c'est comme ça.
  if (isset($_REQUEST['blogtype']) && $_REQUEST['blogtype'] != "") {
    $TypeDeBlog = $_GET['blogtype'];
  }
  else {
    message("<h1>erreur</h1>Impossible de cr&eacute;er le blog '".$url."', son type n'a pas &eacute;t&eacute; pr&eacute;cis&eacute;.");
    die();
    // FIN.
  }
}

// Si on ne peut pas le calculer le blogname, on redirige vers le blog des blogs #1.
if ($url == "") {
  redirection(BLOG_DOMAINE);
  // FIN.
}

$domain = $sitename . '.' .BLOG_DOMAINE; 

logIt("sitename=".$sitename);
logIt("domain=".$domain);
logIt("TypeDeBlog=".$TypeDeBlog);

/*
  Le jeton par défaut est celui de laclasse.com défini par laclasse.com pour laclasse.com
  Rien à voir donc avec le jeton type 3 du SDET.

  Le jeton du SDET à, quant à lui les attributs suivants.
  uid,
  ENTPersonStructRattach,
  ENTEleveClasses,
  ENTPersonStructRattachRNE,
  ENTPersonProfils,
  ENTEleveNivFormation
  
*/
logIt("____________________Traitement du jeton et compl&eacute;ments d'information____________________");

// Si certaines données sont vide, il faut les complèter :
// Si ce jeton n'a pas ce qu'on attend, il faut proposer un formulaire à l'utilisateur 
// Pour saisir les données manquantes :email académique ou pas, nom, prénom.
// Tout ça c'est valable que si l'ent n'est pas *laclasse*.
if (
//    (isset($_REQUEST['ent']) &&  $_REQUEST['ent'] != 'laclasse' ) && 
    (!existsAttr('LaclasseNom') || emptyAttr('LaclasseNom') || !existsAttr('LaclasseEmail') || emptyAttr('LaclasseEmail'))
   ) {
  // Si l'utilisateur n'existe pas on lui présente le formulaire, sinon, on va chercher ses données dans la base.
  $uId = username_exists($username);
  if ($uId > 0) {
    logIt("L'utilisateur existe (#".$uId."), pas besoin de compl&eacute;ment d'information."); 
    // On set ses email,nom et prénom correctement pour que la suite se passe bien.
    $uRec = get_userdata($uId);
    setAttr('LaclasseEmail', $uRec->user_email);
    setAttr('laclasseNom', $uRec->last_name);
    setAttr('laclassePrenom', $uRec->first_name);
    setAttr('LaclasseProfil', get_user_meta( $uRec->ID, "profil_ENT", true));
  }
  else {
    //
    // Demande d'informations complémentaires
    //
    formulaire_sso($sitename);
    }
}

$laclasseUserUid        = getAttr('uid', $username);
$laclasseUserCodeRne    = getAttr('ENTPersonStructRattachRNE', "");
$laclasseUserId         = getAttr('ENT_id', "");
$laclasseUserProfil     = getAttr('LaclasseProfil', "");
$laclasseUserClasse     = getAttr('ENTEleveClasses', "");
$laclasseUserNivClasse  = getAttr('ENTEleveNivFormation', "");
$laclasseUserMail       = getAttr('LaclasseEmail', "");
$laclasseUserMailAca    = getAttr('LaclasseEmailAca', "");
$laclasseUserNom        = getAttr('laclasseNom', "");
$laclasseUserPrenom     = getAttr('laclassePrenom', "");


// Gestion de l'email académique.
if ($laclasseUserMail != "" ) $user_email = $laclasseUserMail; 
else if ($laclasseUserMailAca != "" && in_array($laclasseUserProfil, array("PROF","ADM_ETB","ADMIN","CPE", "PRINCIPAL"))) 
		$user_email = $laclasseUserMailAca; 
	 else $user_email = ""; 
	 
// Si le profil est nul, il faut qu'on y mette le profil par défaut : INVITE
if ($laclasseUserProfil == "" ) {
  $laclasseUserProfil = 'INVITE';
}

logIt("-> uid=".$laclasseUserUid);
logIt("-> rne=".$laclasseUserCodeRne);
logIt("-> userId=".$laclasseUserId);
logIt("-> profil=".$laclasseUserProfil);
logIt("-> Classe=".$laclasseUserClasse);
logIt("-> Niveau=".$laclasseUserNivClasse);
logIt("-> mail=".$laclasseUserMail);
logIt("-> mailAca=".$laclasseUserMailAca);
logIt("-> username=".$username);
logIt("-> nom=".$laclasseUserNom);
logIt("-> prenom=".$laclasseUserPrenom);


//
// ICI ON ENTRE DANS LA PARTIE "CREATION DE BLOGS ET DE USERS"
//
logIt("____________________Provisionning Blog et User____________________");

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
			creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $laclasseUserCodeRne);
		}
	}

	// --------------------------------------------------------------------------------
	// Profil des personnels de l'éducation nationale PROF, ADM_ETB, CPE, PRINCIPAL
	//	Ces profils peuvent créer des blogs et sont donc administrateur du blog créé.
	// Si le blog existe déjà, alors l'utilisateur est rattaché au blog avec des droits
	// d'éditeur ("Editor") -> peut ecrire, valider des posts et valider les posts des autres.
	// --------------------------------------------------------------------------------
	if (in_array($laclasseUserProfil, array("PROF", "ADM_ETB", "CPE", "PRINCIPAL"))) {

		// Si le domaine existe
		if (domain_exists($domain, $path, $site_id)) {	
			// L'utilisateur n'est pas le premier à venir pour ce domaine, 
			// il est par défaut "éditeur" car c'est un un adulte de l'Ed.Nat.
			$profilBlog = "editor";
			
			// Si c'est un principal et qu'il vient sur le blog de son établissement, il est admin
			$codeUAIBlog = get_blog_option($site_id, 'etablissement_ENT');
			logIt(
			       (
			         ($codeUAIBlog == "") ? 
			         "ce blog n'est pas rattach&eacute; &agrave; un &eacute;tablissement." : 
			         "ce blog est rattach&eacute; &agrave; l'&eacute;tablissement  '".$codeUAIBlog."'."
			       )
			     );
			
			if ( $laclasseUserProfil == "PRINCIPAL" && $codeUAIBlog == $laclasseUserCodeRne ) $profilBlog = "administrator";
			
			$wpUsrId = createUserWP($username, $user_email, $profilBlog, $domain);
			rattachUserToHisBlog($domain, $path, $site_id, $wpUsrId, $profilBlog);
		}
		else {
			// ici le domaine n'existe pas : 
			// le premier qui arrive est administrateur du nouveau blog !
			logIt("le domaine '".$domain."' n'existe pas.");
			$wpUsrId = createUserWP($username, $user_email, "administrator", $domain);
			// Maintenant il faut créer un blog.
			creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $laclasseUserCodeRne);
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
	// Profil PARENT ou INVITE: Il est uniquement lecteur du blog et peut gérer son profil.	
	// le profil WP s'appelle "Subscriber"
	// --------------------------------------------------------------------------------
	if (in_array($laclasseUserProfil, array("PARENT","INVITE"))){
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