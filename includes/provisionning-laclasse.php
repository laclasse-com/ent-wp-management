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
// Requires WordPress
require_once( $_SERVER["DOCUMENT_ROOT"] . '/wp-load.php' );
require_once( $_SERVER["DOCUMENT_ROOT"] . '/wp-blog-header.php' );
require_once( ABSPATH . WPINC . '/registration.php' );
require_once( ABSPATH . 'wp-admin/includes/ms.php' );
// Requires applicatifs
require_once(dirname(__FILE__) . '/cas-token-functions.inc.php');
require_once(dirname(__FILE__) . '/provisionning-functions.inc.php');


global $domain, $base;
// $url est l'url vers laquelle on va rediriger
$url = "";
// Compl�ment de query http
$qry = "";
// nom du site, s'il faut le cr�er
$sitename = "";
// Le domaine est le sitename+ le domai,e de bog
$domain = "";
// Le path sert � cr�er les blogs.
$path = '/'; 
// Le username est donn� par l'authentification CAS.
$username = "";
// Setting des variables couramment utilis�es dans les api WP
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
// D�finition du routage
//

logIt("____________________D&eacute;finition Routage____________________");
// si blogname n'est pas renseign�, on le calcule.
if (isset($_REQUEST['blogname']) && $_REQUEST['blogname'] != "") {
  logIt("Blogname est renseign&eacute;.");
  $url = str_replace("http://", "", $_REQUEST['blogname'].".".BLOG_DOMAINE);
  $sitename = $_REQUEST['blogname'];
}
else {
  logIt("Blogname n'est pas renseign&eacute;.");
  $url = str_replace("http://", "", home_url());
  $sitename = str_replace(".".BLOG_DOMAINE, "", $url);
}

logIt("url : '".$url."'.");
// On regarde si le blog existe car s'il faut le cr�er, il nous faut le blogtype.
$wpBlgId = get_blog_id_by_domain($url);
if ($wpBlgId > 0) {
  logIt("le blog existe, #".$wpBlgId.".");
} 
else {
  logIt("le blog n'existe pas.");
  // on doit v�rifier que'on a bien blogtype sinon, pas de cr�ation possible... c'est comme �a.
  if (isset($_REQUEST['blogtype']) && $_REQUEST['blogtype'] != "") {
    $TypeDeBlog = $_REQUEST['blogtype'];
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
  Le jeton par d�faut est celui de laclasse.com d�fini par laclasse.com pour laclasse.com
  Rien � voir donc avec le jeton type 3 du SDET.

  Le jeton du SDET �, quant � lui les attributs suivants.
  uid,
  ENTPersonStructRattach,
  ENTEleveClasses,
  ENTPersonStructRattachRNE,
  ENTPersonProfils,
  ENTEleveNivFormation
  
*/

logIt("____________________Traitement du jeton et compl&eacute;ments d'information____________________");
setToken($_SESSION['phpCAS']['attributes']);

logIt("Jeton xml issue de CAS : <pre>".print_r(getToken(), true)."</pre>");

// Si certaines donn�es sont vide, il faut les compl�ter :
// Si ce jeton n'a pas ce qu'on attend, il faut proposer un formulaire � l'utilisateur 
// Pour saisir les donn�es manquantes :email acad�mique ou pas, nom, pr�nom.
// Tout �a c'est valable que si l'ent n'est pas *laclasse*.
if (
//    (isset($_REQUEST['ent']) &&  $_REQUEST['ent'] != 'laclasse' ) && 
    (!existsAttr('LaclasseNom') || emptyAttr('LaclasseNom') || !existsAttr('LaclasseEmail') || emptyAttr('LaclasseEmail'))
   ) {
  // Si l'utilisateur n'existe pas on lui pr�sente le formulaire, sinon, on va chercher ses donn�es dans la base.
  $uId = username_exists($username);
  if ($uId > 0) {
    logIt("L'utilisateur existe (#".$uId."), pas besoin de compl&eacute;ment d'information."); 
    // On set ses email,nom et pr�nom correctement pour que la suite se passe bien.
    $uRec = get_userdata($uId);
    setAttr('LaclasseEmail', $uRec->user_email);
    setAttr('laclasseNom', $uRec->last_name);
    setAttr('laclassePrenom', $uRec->first_name);
    setAttr('LaclasseProfil', get_user_meta( $uRec->ID, "profil_ENT", true));
  }
  else {
    //
    // Demande d'informations compl�mentaires
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


// Gestion de l'email acad�mique.
if ($laclasseUserMail != "" ) $user_email = $laclasseUserMail; 
else if ($laclasseUserMailAca != "" && in_array($laclasseUserProfil, array("PROF","ADM_ETB","ADMIN","CPE", "PRINCIPAL"))) 
		$user_email = $laclasseUserMailAca; 
	 else $user_email = ""; 
	 
// Si le profil est nul, il faut qu'on y mette le profil par d�faut : INVITE
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
	// Si l'utilisateur est SUPER ADMIN de laclasse.com, il a des droits sur le bakoffice g�n�ral de WP.
	// donc admin sur tout les blogs.
	// --------------------------------------------------------------------------------
	if ($laclasseUserProfil == "ADMIN") {
		$wpUsrId = createUserWP($username, $user_email, "administrator", $domain);
		// Si le domaine existe

		if (domain_exists($domain, $path, $site_id)) {	
			// L'utilisateur n'est pas le premier � venir pour ce domaine, 
			// il est par d�faut "administrateur de la plateforme" car il est super admin dans l'ENT
			rattachSuperUserToTheBLog($wpUsrId, "administrator");
		}
		else {
			// ici le domaine n'existe pas : 
			// le premier qui arrive est administrateur du nouveau blog !
			logIt("le domaine '".$domain."' n'existe pas.");
			// Maintenant il faut cr�er un blog.
			creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $laclasseUserCodeRne);
		}
	}

	// --------------------------------------------------------------------------------
	// Profil des personnels de l'�ducation nationale PROF, ADM_ETB, CPE, PRINCIPAL
	//	Ces profils peuvent cr�er des blogs et sont donc administrateur du blog cr��.
	// Si le blog existe d�j�, alors l'utilisateur est rattach� au blog avec des droits
	// d'�diteur ("Editor") -> peut ecrire, valider des posts et valider les posts des autres.
	// --------------------------------------------------------------------------------
	if (in_array($laclasseUserProfil, array("PROF", "ADM_ETB", "CPE", "PRINCIPAL"))) {

		// Si le domaine existe
		if (domain_exists($domain, $path, $site_id)) {	
			// L'utilisateur n'est pas le premier � venir pour ce domaine, 
			// il est par d�faut "�diteur" car c'est un un adulte de l'Ed.Nat.
			$profilBlog = "editor";
			
			// Si c'est un principal et qu'il vient sur le blog de son �tablissement, il est admin
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
			// Maintenant il faut cr�er un blog.
			creerNouveauBlog($domain, $path, $sitename, $username, $user_email, $site_id, $wpUsrId, $TypeDeBlog, $laclasseUserCodeRne);
   		}
	}

	// --------------------------------------------------------------------------------
	// Profil ELEVE : Il est contributeur sur le blog mais ne doit pas pouvoir publier.
	// Ses publication doivent �tre valid�es.
	// --------------------------------------------------------------------------------
	if ($laclasseUserProfil == "ELEVE") {
		$wpUsrId = createUserWP($username, $user_email, "contributor", $domain);
		rattachUserToHisBlog($domain, $path, $site_id, $wpUsrId, "contributor");
	}

	// --------------------------------------------------------------------------------
	// Profil PARENT ou INVITE: Il est uniquement lecteur du blog et peut g�rer son profil.	
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
	// rediretion si le script n'est pas en mode d�bug.
	redirection($domain);


?>