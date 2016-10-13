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
	curl_setopt($ch, CURLOPT_USERPWD, ANNUAIRE_APP_ID . ":" . ANNUAIRE_API_KEY);

	
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
		<a href="mailto:support@laclasse.com" target="_blank">support@laclasse.com</a>.</p>'
		);
}

