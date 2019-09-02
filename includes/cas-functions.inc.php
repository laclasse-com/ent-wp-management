<?php

// --------------------------------------------------------------------------------
//  Class CAS
// --------------------------------------------------------------------------------
class wpCAS {
	public static function authenticate() {
		global $_REQUEST;

		if (isset($_REQUEST['ticket'])) {
			$req = curl_init();
	        curl_setopt($req, CURLOPT_URL, CAS_URL . 'serviceValidate?ticket=' .
				urlencode($_REQUEST['ticket']) . '&service=' . wpCAS::get_url_login());
			curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
			$data = curl_exec($req);
			$error = curl_errno($req);
			$http_status = curl_getinfo($req, CURLINFO_HTTP_CODE);
			curl_close($req);

			if ($http_status == 200) {
				// parse the ticket content
				$xml = simplexml_load_string($data);
				// get the uid attribute
				$xml->registerXPathNamespace('cas', 'http://www.yale.edu/tp/cas');
				$uids = $xml->xpath("//cas:uid");
				if (count($uids) > 0) {
					$uid = (string)$uids[0];

					$userENT = get_ent_user($uid);
					$user = sync_ent_user_to_wp_user($userENT);

					// the CAS user has a WP account
					wp_set_auth_cookie($user->ID);
					wp_set_current_user($user->ID);

					// redirect to the /, we dont want to end on the wp-login.php page
					wp_redirect('/');
				}
			}
		}
		else {
			header('Location: ' . wpCAS::get_url_login());
			die();
		}
	}

	// renvoie l'url de login, selon le contexte : intégré dans une IFRAME ou normal
	public static function get_url_login() {
		return CAS_URL . "login?service=" . urlencode(home_url() . "/wp-login.php");
	}

	// Revoie l'url de logout selon l'ent de provenance.
	public static function get_url_logout($wpLogoutUrl) {
		return $wpLogoutUrl;
	}

	// hook CAS logout to WP logout
	public static function logout() {
		header('Location: ' . CAS_URL . "logout?service=" . urlencode(home_url() . "/wp-login.php"));
		die();
	}

	// hide password fields on user profile page.
	public static function show_password_fields( $show_password_fields ) {
		return false;
	}

	// disabled reset, lost, and retrieve password features
	public static function disable_function_user() {
		echo( __( 'La  fonction d\'ajout d\'un nouvel utilisateur est d&eacute;sactiv&eacute;e. Passez par l\'ENT '.ENT_NAME.' pour ajouter des utilisateurs &agrave; votre blog.', 'wpcas' ));
	}

	// disabled reset, lost, and retrieve password features
	public static function disable_function_pwd() {
		echo( '<p class="message">'.__( 'Les fonctions de gestion des mots de passe de Wordpress sont d&eacute;sactiv&eacute;es car la plateforme est connect&eacute;e &agrave; l\'ENT <a href="'.ENT_URL.'">'.ENT_NAME.'</a>.</p>', 'wpcas' ));
		// return false;
	}

	public static function hide_forms() {
		echo( "<script> alert('if this happen don\'t use it');const forms = document.getElementsByTagName('form'); if(forms.length > 0) for(var i=0;i<forms.length;i++) { forms[i].hidden = true; }</script>");
		// return false;
	}
	// set the passwords on user creation
	// patched Mar 25 2010 by Jonathan Rogers jonathan via findyourfans.com
	public static function check_passwords( $user, $pass1, $pass2 ) {
		$random_password = substr( md5( uniqid( microtime( ))), 0, 8 );
		$pass1=$pass2=$random_password;
	}
}
