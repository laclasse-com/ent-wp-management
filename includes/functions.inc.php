<?php
// --------------------------------------------------------------------------------
// fonctions utilitaires du plugin ENT-WP-Management.
// --------------------------------------------------------------------------------
$logProvisioning = "";

// --------------------------------------------------------------------------------
// fonction d'envoie d'un GET HTTP.
// --------------------------------------------------------------------------------
function get_http($url){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	
	$data = curl_exec($ch);
	if (curl_errno($ch)) {
		return curl_error($ch);
	}
	curl_close($ch);
	return $data;
}

// --------------------------------------------------------------------------------
//  Fonction d'affichage d'un message de retour d'une action de pilotage.
// --------------------------------------------------------------------------------
function message($pmessage){
	echo "
		<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
		<html xmlns=\"http://www.w3.org/1999/xhtml\" dir=\"ltr\" lang=\"fr-FR\">
			<head>
				<title>WordPress &rsaquo; message de retour</title>

				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
				<link rel='stylesheet' id='install-css'  href='".network_site_url()."wp-admin/css/install.css?ver=20100605' type='text/css' media='all' />
<!--[if lte IE 7]>
<link rel='stylesheet' id='ie-css'  href='".network_site_url()."wp-admin/css/ie.css?ver=20100610' type='text/css' media='all' />
<![endif]-->
<style>
body {width:auto;}
</style>
			</head>

			<body>
				<h1 id=\"logo\"><img alt=\"WordPress\" src=\"".network_site_url()."wp-admin/images/wordpress-logo.png\" /></h1>
				<p>$pmessage</p>
			</body>
		</html>";
		
}

// --------------------------------------------------------------------------------
// fonction de log des traitements
// --------------------------------------------------------------------------------
function logIt($msg) {
	global $logProvisioning;
	if (isset($_GET['debug']) && $_GET['debug'] == "O")	$logProvisioning .= "<li>".$msg."</li>\n";
}

// --------------------------------------------------------------------------------
// fonction de renvoie du log pour affichage
// --------------------------------------------------------------------------------
function getLog() {
	global $logProvisioning;
  return $logProvisioning;
}

// --------------------------------------------------------------------------------
// fonction de reset du log.
// --------------------------------------------------------------------------------
function resetLog() {
	global $logProvisioning;
  $logProvisioning = "";
}

// --------------------------------------------------------------------------------
//  Fonction d'affichage d'un message de retour d'une action de pilotage.
// --------------------------------------------------------------------------------
function endMessage($pmessage){
  message($pmessage);
	exit;
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
// Fonction qui renvoie le role WP en fonction du profil des ENTs.
// --------------------------------------------------------------------------------
// - ADMIN : Devient super-administreur de tout les blogs, pas de création de blog.

// - PROF, 
// - ADM_ETB, CPE, PRINCIPAL : Deviennent administrateur de leur domaine si le domaine n'existe pas,
//                   avec création de blog, sinon devient éditeur du blog existant.
               
//    - PRINCIPAL  : Si le blog est celui de son établissement : Devient administrateur de son domaine. 
//                   Pour tous les autres blogs, voir la règle ci dessus (profs, cpe, adm_etb).
					   
// - ELEVE : Devient contributeur du blog existant dans le domaine, pas de création de blog.
// - PARENT : Devient souscripteur du blog existant, pas de création de blog.
// --------------------------------------------------------------------------------
function get_WP_role_from_ent_profil($profil_ent, $is_new_domain){
	switch($profil_ent) {
	case "ADMIN" : 
	    $role_wp = "administrator";
	    break;
	  case "PROF" : 
	  case "ADM_ETB" : 
	  case "CPE" : 
	  case "PRINCIPAL" : 
	    $role_wp = ($is_new_domain) ? "administrator" : "editor";
	    break;
	  case "ELEVE" : 
	    $role_wp = "contributor";
	    break;
	  case "PARENT" : 
	    $role_wp = "subscriber";
	    break;
	  case "INVITE" : 
	    $role_wp = "subscriber";
	    break;
	  default :
	    $role_wp = "subscriber";
	}
	return $role_wp;
}

