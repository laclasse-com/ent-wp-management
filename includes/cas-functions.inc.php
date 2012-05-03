<?php
/* 
 Copyright (C) 2008 Casey Bisson

 This plugin owes a huge debt to 
 Stephen Schwink's CAS Authentication plugin, copyright (C) 2008 
 and released under GPL. 
 http://wordpress.org/extend/plugins/cas-authentication/

 This plugin honors and extends Schwink's work, and is licensed under the same terms.

 This Plugin was hardy modified by Pierre-Gilles Levallois for www.laclasse.com.

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
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA	 02111-1307	 USA 
*/

// --------------------------------------------------------------------------------
// Une fonction de mise en session des donnŽes du jeton CAS. (connexion avec CAS)
// --------------------------------------------------------------------------------
function setCASdataInSession() {
	
	$content = $_SESSION['phpCAS']['response'];
	$content = str_replace("\t", "", $content);
	$content = str_replace("\n", "", $content);
	$p = xml_parser_create();
	
	xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($p, $content, $values, $index);
	// traitement des erreurs
	$ReturnStatus = xml_get_error_code($p);
	if ($ReturnStatus != 0 && $conent != "") {
    	echo("erreur de parsing du fichier XML '$file': ".xml_get_error_code($p).
    				" - ".xml_error_string(xml_get_error_code($p)).
    				" à la ligne ".xml_get_current_line_number($p).
    				", colonne ".xml_get_current_column_number($p).".");
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
					$_SESSION['phpCAS']['attributes'][str_replace('cas:', '', $values[$i]["tag"])] = $values[$i]["value"];
				}
			}		
		}
	}
}

// --------------------------------------------------------------------------------
//  Fonction de provisionning CAS
// --------------------------------------------------------------------------------
function wpcas_provisioning( $user_name ){
	$ret = include( WP_PLUGIN_DIR."/".dirname( plugin_basename( __FILE__ )).'/provisionning-laclasse.php');
}

// --------------------------------------------------------------------------------
//  Formulaire de saisie de données complémentaires
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------------
//  Formulaire de choix d'un serveur de sso
// --------------------------------------------------------------------------------
function select_sso() {
  global $wpcas_options;
  $html = "\n<form action='".network_site_url()."/wp-admin/'>\n<select name='ent' id='ent' class=''>
	<option value=''>Choisir un serveur d'authentification</option>";
	foreach($wpcas_options as $k => $v) {
	 $html .= "<option value='$k'>$k</option>\n";
	}
  $html .= "</select><br/><br/><input type='submit' value='Valider'/></form>";
  return $html;
}

// --------------------------------------------------------------------------------
//  Classe cd client CAS
// --------------------------------------------------------------------------------
class wpCAS {
	/*
	 We call phpCAS to authenticate the user at the appropriate time 
	 (the script dies there if login was unsuccessful)
	 If the user is not provisioned, wpcas_nowpuser() is called
	*/
	function authenticate() {
		global $wpcas_options, $cas_configured, $ent;
		logIt("<h1>ENT : $ent</h1>");
		$proto = ($wpcas_options[$ent]['server_port'] == '443') ? 's': '';
		logIt("Serveur d'authentification : http".$proto."://".$wpcas_options[$ent]['server_hostname'].":".$wpcas_options[$ent]['server_port'].$wpcas_options[$ent]['server_path'].".");
		
		if ( !$cas_configured )
			//die( __( 'Pas de configuration SSO pour l\'ENT "'.$ent.'".', 'wpcas' ));
			message('<h1>Aucun serveur d\'authentification trouv&eacute; pour l\'ENT "'.$ent.'".</h1><br/>S&eacute;lectionnez votre ENT : <br/><br/>' . select_sso());
			die();
		if( phpCAS::isAuthenticated() ){
			// CAS was successful so sets session variables
			setCASdataInSession();
			
			$user = get_user_by('login', phpCAS::getUser());
			if ( $user ){ // user already exists
				// the CAS user has a WP account
				wp_set_auth_cookie( $user->ID );
				wp_set_current_user( $user->ID );
			}
			// On provisionne ce qu'il manque : user ou blog, ou les deux
			wpcas_provisioning( phpCAS::getUser() );
		
		}else{
			// hey, authenticate
			phpCAS::forceAuthentication();
			die();
		}
	}
	
	// renvoie l'url de login, selon le contexte : intégré dans une IFRAME ou normal
	function get_url_login() {
		global $ent, $wpcas_options;
		if ($_REQUEST['ENT_action'] == 'IFRAME') {
			$qry = '?ENT_action=IFRAME';
			$url =  home_url().'/wp-login.php'.$qry;
		}
		// Si on n'est pas en mode intégré
		else {		
  		if ($wpcas_options[$ent]['server_port'] == 443) $protoc = "https://";
  		else $protoc = "http://";
  		$url = $protoc.
  			   $wpcas_options[$ent]['server_hostname'].
  			   (($wpcas_options[$ent]['server_port'] != 80 )? ":".$wpcas_options[$ent]['server_port'] : "").
  			   $wpcas_options[$ent]['server_path'].
  			   "/login?service=".urlencode(home_url()."/wp-login.php");
			   
		}
		return $url;
	}
	
	// hook CAS logout to WP logout
	function logout() {
		global $cas_configured;

		if (!$cas_configured)
			die( __( 'wpCAS plugin not configured', 'wpcas' ));
			
		if ($_REQUEST['ENT_action'] == 'IFRAME') 
			$qry = '?ENT_action=IFRAME';

        // Supprimer les cookies de WP
        wp_clear_auth_cookie();
		phpCAS::logout( array( 'url' => get_option( 'siteurl' ).$qry ));
		exit();
	}

	// hide password fields on user profile page.
	function show_password_fields( $show_password_fields ) {
		return false;
	}

	// disabled reset, lost, and retrieve password features
	function disable_function_user() {
		echo( __( 'La  fonction d\'ajout d\'un nouvel utilisateur est d&eacute;sactiv&eacute;e. Passez par l\'ENT '.NOM_ENT.' pour ajouter des utilisateurs &agrave; votre blog.', 'wpcas' ));
	}

	// disabled reset, lost, and retrieve password features
	function disable_function_pwd() {
		echo( __( 'Les fonctions de gestion des mots de passe sont d&eacute;sactiv&eacute;es car la plateforme est connectée &agrave; l\'ENT '.NOM_ENT.'.', 'wpcas' ));
	}

	// set the passwords on user creation
	// patched Mar 25 2010 by Jonathan Rogers jonathan via findyourfans.com
	function check_passwords( $user, $pass1, $pass2 ) {
		$random_password = substr( md5( uniqid( microtime( ))), 0, 8 );
		$pass1=$pass2=$random_password;
	}
}

// --------------------------------------------------------------------------------
//  D é b u t   d u   s c r i p t   d e   C A S i f i c a t i o n 
// --------------------------------------------------------------------------------
$ent = 'laclasse';
if (isset($_REQUEST['ent']) && ($_REQUEST['ent'] != "")) $ent = $_REQUEST['ent'];

$cas_configured = true;

// try to configure the phpCAS client
if ($wpcas_options[$ent]['include_path'] == '' ||
		(include_once $wpcas_options[$ent]['include_path']) != true)
	$cas_configured = false;

if ($wpcas_options[$ent]['server_hostname'] == '' ||
		intval($wpcas_options[$ent]['server_port']) == 0)
	$cas_configured = false;

if ($cas_configured) {
	phpCAS::client($wpcas_options[$ent]['cas_version'], 
		$wpcas_options[$ent]['server_hostname'], 
		intval($wpcas_options[$ent]['server_port']), 
		$wpcas_options[$ent]['server_path']);
	
	// function added in phpCAS v. 0.6.0
	// checking for static method existance is frustrating in php4
	$phpCas = new phpCas();
	if (method_exists($phpCas, 'setNoCasServerValidation'))
		phpCAS::setNoCasServerValidation();
	unset($phpCas);
	
	// if you want to set a cert, replace the above few lines
 }

?>