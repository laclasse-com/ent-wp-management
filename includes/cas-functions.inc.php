<?php

// --------------------------------------------------------------------------------
//  Fonction de provisionning CAS
// --------------------------------------------------------------------------------
function wpcas_provisioning($userENT, $user_email){
	global $domain;
	$user_id = $userENT->id;
	$user_login = $userENT->login;

	$blogId = get_blog_id_by_domain($domain);
	$blogData = getBlogData($blogId);

    $role = getUserWpRole($userENT, $blogData);

	createUserWP($user_login, $user_email, $userENT);
	header('Location: http://'.$domain);
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
					$userENT = json_decode(get_http(ANNUAIRE_URL."api/users/$uid?expand=true"));

					$user_email;
					foreach($userENT->emails as $email) {
						if (!isset($user_email) || $email->primary)
							$user_email = $email->address;
					}
					if (!isset($user_email))
						$user_email = $user->id . '@noemail.lan';

					$user = get_user_by('login', $userENT->login);
					// if user already exists
					if ($user) {
						// the CAS user has a WP account
						wp_set_auth_cookie($user->ID);
						wp_set_current_user($user->ID);

		                // Met � jour les donn�es de l'utilisateur
						wp_update_user( 
							array (
								'ID' => $user->ID, 
								'first_name' => $userENT->firstname, 
								'last_name' => $userENT->lastname,
								'display_name' => $userENT->lastname . ' '.
									$userENT->firstname,
								'user_email' => $user_email
							)
						);
					}
					// On provisionne ce qu'il manque : user ou blog, ou les deux
					wpcas_provisioning($userENT, $user_email);
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
		echo( __( 'Les fonctions de gestion des mots de passe sont d&eacute;sactiv&eacute;es car la plateforme est connect�e &agrave; l\'ENT '.ENT_NAME.'.', 'wpcas' ));
	}

	// set the passwords on user creation
	// patched Mar 25 2010 by Jonathan Rogers jonathan via findyourfans.com
	public static function check_passwords( $user, $pass1, $pass2 ) {
		$random_password = substr( md5( uniqid( microtime( ))), 0, 8 );
		$pass1=$pass2=$random_password;
	}
}
