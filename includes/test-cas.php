<?php

//
// phpCAS simple client
//
////////////////////////////////////////////////////////////////////////////////
// Variables ˆ dŽfinir pour le client CAS laclasse.com
////////////////////////////////////////////////////////////////////////////////
$casServer = 'sso.laclasse.com';
$casServerPort = 443;
$casServerPath = '/cas';
$casCanOpenPhpSession = true;

////////////////////////////////////////////////////////////////////////////////
// Attributs CAS2 ˆ rŽcupŽrer dans la session PHP
////////////////////////////////////////////////////////////////////////////////

$CAS2Attributes = array("user", "uid", "ENTPersonStructRattachRNE", "ENT_id",  "ENTPersonProfils", "ENTEleveClasses", "ENTEleveNivFormation");

////////////////////////////////////////////////////////////////////////////////
// Fin du paramŽtrage.
////////////////////////////////////////////////////////////////////////////////


function setCASdataInSession() {
	$tab = array();
	$content = $_SESSION['phpCAS']['response'];
	$p = xml_parser_create();
	
	xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($p, $content, $values, $index);
	// traitement des erreurs
	$ReturnStatus = xml_get_error_code($p);
	if ($ReturnStatus != 0) {
    	echo("erreur de parsing du fichier XML '$file': ".xml_get_error_code($p).
    				" - ".xml_error_string(xml_get_error_code($p)).
    				" ˆ la ligne ".xml_get_current_line_number($p).
    				", colonne ".xml_get_current_column_number($p).".");
    	return $tab;
	}
	xml_parser_free($p);
	// parsing et construction d'un tableau de valeurs
	if (is_array($index["cas:authenticationSuccess"])) {
		$debRequest=$index["cas:authenticationSuccess"][0]+1;
		$finRequest=$index["cas:authenticationSuccess"][1]-1;
		if ($debRequest <= $finRequest) {
			for($i=$debRequest+1; $i <= $finRequest; $i++) {
				// Ici on recupere la valeur de l'attribut dans les tableaux generes par le parseur
				if ($values[$i]["type"]== "complete" ) {
					echo "$i : ".$values[$i]["tag"]." =  ".$values[$i]["value"]."<br>";
					$_SESSION['phpCAS']['attributes'][str_replace('cas:', '', $values[$i]["tag"])] = $values[$i]["value"];
				}
			}		
		}
	}
}

// import phpCAS lib
include_once('/var/www/CAS-laclasse-1.1.1/CAS.php');

// initialize phpCAS
phpCAS::client(CAS_VERSION_2_0, $casServer, $casServerPort, $casServerPath, $casCanOpenPhpSession);

// Langage
phpCAS::setLang(PHPCAS_LANG_FRENCH);

// no SSL validation for the CAS server
phpCAS::setNoCasServerValidation();

// logout if desired
if (isset($_REQUEST['logout']))	phpCAS::logoutWithRedirectService($_REQUEST['service']); 


/*// ensure the user is authenticated via CAS
if( !phpCAS::isAuthenticated() || !$username = phpCAS::getUser() ){
	phpCAS::forceAuthentication();
	die( 'requires authentication' );
}
*/

// On force l'utilisateur ö s'authentifier en redirigeant l'utilisateur vers le serveur CAS
if(!phpCAS::checkAuthentication()) phpCAS::forceAuthentication();

//
// A partir d'ici l'authentification est terminŽe et on rŽcupre les attributs cas2 en session.
//
// Mise en session des attributs CAS2 

if ($casCanOpenPhpSession) {
	/////// phpCAS::setPHPSession($CAS2Attributes);
	setCASdataInSession();
}

// Commenter les lignes ci-dessous, qui ne sont lˆ que pour vŽrifier que l'authentification a fonctionŽ.
?>
<html>
  <head>
    <title>phpCAS simple client</title>
  </head>
  <body>
    <h1>Successfull Authentication!</h1>
	<p>the user's login is <b><?php echo phpCAS::getUser(); ?></b>.</p>
    <p>phpCAS version is <b><?php echo phpCAS::getVersion(); ?></b>.</p>
    <p><a href="?logout=&service=http://ldap.erasme.lan/tests/clientCAS/index.php">Logout</a></p>
		<h3>User Attributes : </h3> 	
		<pre>
			<?
			print_r($_SESSION);
			?>
		</pre>	
  </body>
</html>
