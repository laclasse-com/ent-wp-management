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
//  Fonction de provisionning CAS
// --------------------------------------------------------------------------------
function wpcas_provisioning(){
	global $domain;
	$user_id = phpCAS::getAttribute('uid');
	$user_login = phpCAS::getAttribute('login');
	$user_email = phpCAS::getAttribute('uid') . "@noemail.lan";
	if (phpCAS::hasAttribute('MailAdressePrincipal') && (phpCAS::getAttribute('MailAdressePrincipal') != ""))
		$user_email = phpCAS::getAttribute('MailAdressePrincipal');
	else if (phpCAS::hasAttribute('LaclasseEmail') && (phpCAS::getAttribute('LaclasseEmail') != ""))
		$user_email = phpCAS::getAttribute('LaclasseEmail');

	$blogId = get_blog_id_by_domain($domain);
	$blogData = getBlogData($blogId);

	$userENT = json_decode(get_http(ANNUAIRE_URL."api/users/$user_id?expand=true"));
    $role = getUserWpRole($userENT, $blogData);

	createUserWP($user_login, $user_email);
	redirection($domain);
}

// --------------------------------------------------------------------------------
//  Formulaire de choix d'un serveur de sso
// --------------------------------------------------------------------------------
function select_sso() {
  global $wpcas_options;
  $html = "\n<form action='".home_url()."/wp-login.php'>\n<select name='ent' id='ent' class=''>
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
	public static function authenticate() {
		global $wpcas_options, $cas_configured, $ent, $_REQUEST;

		error_log("DANIEL authenticate");
		error_log(print_r($_REQUEST, true));
		error_log("TICKET: " . $_REQUEST['ticket']);

		logIt("<h1>ENT : $ent</h1>");
		$proto = ($wpcas_options[$ent]['server_port'] == '443') ? 's': '';
		logIt("Serveur d'authentification : http".$proto."://".$wpcas_options[$ent]['server_hostname'].":".$wpcas_options[$ent]['server_port'].$wpcas_options[$ent]['server_path'].".");

		if ( !$cas_configured ) {
			message('<h1>Aucun serveur d\'authentification trouv&eacute;'.((!isset($ent)||$ent =='')? '' : ' pour l\'ENT "'.$ent.'"').'.</h1>'.
			        '<br/>S&eacute;lectionnez votre ENT : <br/><br/>' . 
			        select_sso()
			       );
			die();
		}

/*		if (isset($_REQUEST['ticket'])) {
			$req = curl_init();
	        curl_setopt($req, CURLOPT_URL, 'https://v3dev.laclasse.lan/sso/serviceValidate?ticket=' .
				urlencode($_REQUEST['ticket']) . '&service=' . get_url_login());
			curl_exec($req);
			$error = curl_errno($req);
		}*/

		//if(is_user_logged_in()) {
		//}

		if( phpCAS::isAuthenticated() ){
			// CAS was successful so sets session variables
			$user = get_user_by('login', phpCAS::getUser());
			if ( $user ){ // user already exists
				// the CAS user has a WP account
				wp_set_auth_cookie( $user->ID );
				wp_set_current_user( $user->ID );
				
				// Enregistrement de l'ENT de provenance pour ce username
	   			if(phpCAS::getAttribute('LaclassePrenom') && phpCAS::getAttribute('LaclasseNom')) {

					$user_email = phpCAS::getAttribute('uid') . "@noemail.lan";
					if (phpCAS::hasAttribute('MailAdressePrincipal') && (phpCAS::getAttribute('MailAdressePrincipal') != ""))
						$user_email = phpCAS::getAttribute('MailAdressePrincipal');
					else if (phpCAS::hasAttribute('LaclasseEmail') && (phpCAS::getAttribute('LaclasseEmail') != ""))
						$user_email = phpCAS::getAttribute('LaclasseEmail');

	                // Met ï¿½ jour les donnï¿½es de l'utilisateur
					wp_update_user( 
						array (
							'ID' => $user->ID, 
							'first_name' => phpCAS::getAttribute('LaclassePrenom'), 
							'last_name' => phpCAS::getAttribute('LaclasseNom'),
							'display_name' => phpCAS::getAttribute('LaclasseNom').' '.
								phpCAS::getAttribute('LaclassePrenom'),
							'user_email' => $user_email
						)
					);
				}
			}
			// On provisionne ce qu'il manque : user ou blog, ou les deux
			wpcas_provisioning();
		
		}
		else {
			// hey, authenticate
			phpCAS::forceAuthentication();
			die();
		}
	}
	
	// renvoie l'url de login, selon le contexte : intï¿½grï¿½ dans une IFRAME ou normal
	public static function get_url_login() {
		global $ent, $wpcas_options, $cas_configured;

		error_log("DANIEL get_url_login");

		// Si CAS n'est pas configuré, on retourne une url de login standard.
		if (!$cas_configured){
		  return home_url()."/wp-login.php?ent=$ent";
		}
		
		$protoc = ($wpcas_options[$ent]['server_port'] == 443) ? "https://" : "http://";
		$url = $protoc.
			$wpcas_options[$ent]['server_hostname'].
			(($wpcas_options[$ent]['server_port'] != 80 )? ":".$wpcas_options[$ent]['server_port'] : "").
			$wpcas_options[$ent]['server_path'].
			"/login?service=".urlencode(home_url()."/wp-login.php?ent=".$ent);
			   
		return $url;
	}
	
	// Revoie l'url de logout selon l'ent de provenance.
	public static function get_url_logout($wpLogoutUrl) {
		error_log("DANIEL get_url_logout");

		$current_user = wp_get_current_user();
		$nomEnt = get_user_meta( $current_user->ID, "nom_ENT", true);
		return $wpLogoutUrl . "&ent=".$nomEnt;
	}
	
	// hook CAS logout to WP logout
	public static function logout() {
	    // Supprimer les cookies de WP
	    wp_destroy_current_session();
	    wp_clear_auth_cookie();
		phpCAS::logout( array( 'url' => get_option( 'siteurl' )."?ent=laclasse".$_REQUEST['ent']));
		exit();
	}

	// hide password fields on user profile page.
	public static function show_password_fields( $show_password_fields ) {
		return false;
	}

	// disabled reset, lost, and retrieve password features
	public static function disable_function_user() {
		echo( __( 'La  fonction d\'ajout d\'un nouvel utilisateur est d&eacute;sactiv&eacute;e. Passez par l\'ENT '.NOM_ENT.' pour ajouter des utilisateurs &agrave; votre blog.', 'wpcas' ));
	}

	// disabled reset, lost, and retrieve password features
	public static function disable_function_pwd() {
		echo( __( 'Les fonctions de gestion des mots de passe sont d&eacute;sactiv&eacute;es car la plateforme est connectï¿½e &agrave; l\'ENT '.NOM_ENT.'.', 'wpcas' ));
	}

	// set the passwords on user creation
	// patched Mar 25 2010 by Jonathan Rogers jonathan via findyourfans.com
	public static function check_passwords( $user, $pass1, $pass2 ) {
		$random_password = substr( md5( uniqid( microtime( ))), 0, 8 );
		$pass1=$pass2=$random_password;
	}
}

// --------------------------------------------------------------------------------
//  D ï¿½ b u t   d u   s c r i p t   d e   C A S i f i c a t i o n 
// --------------------------------------------------------------------------------
$ent = "laclasse";
if (isset($_REQUEST['ent']) && ($_REQUEST['ent'] != "")) $ent = $_REQUEST['ent'];

$cas_configured = true;

// try to configure the phpCAS client
if ($wpcas_options[$ent]['include_path'] == '' ||
		(include_once $wpcas_options[$ent]['include_path']) != true) {
	$cas_configured = false;
}

if ($wpcas_options[$ent]['server_hostname'] == '' ||
		intval($wpcas_options[$ent]['server_port']) == 0) {
	$cas_configured = false;
}

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

