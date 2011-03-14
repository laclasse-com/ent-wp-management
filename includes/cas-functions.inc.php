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

//----------------------------------------------------------------------------
//		ADMIN OPTION PAGE FUNCTIONS
//----------------------------------------------------------------------------
function wpcas_options_page_add() {
	add_options_page( __( 'wpCAS', 'wpcas' ), __( 'wpCAS', 'wpcas' ), 8, basename(__FILE__), 'wpcas_options_page');
} 

function wpcas_options_page() {
	global $wpdb;
	
	// Setup Default Options Array
	$optionarray_def = array(
				 'new_user' => FALSE,
				 'redirect_url' => '',
				 'email_suffix' => 'yourschool.edu',
				 'cas_version' => CAS_VERSION_1_0,
				 'include_path' => '',
				 'server_hostname' => 'yourschool.edu',
				 'server_port' => '443',
				 'server_path' => ''
				 );
	
	if (isset($_POST['submit']) ) {		 
		// Options Array Update
		$optionarray_update = array (
				 'new_user' => $_POST['new_user'],
				 'redirect_url' => $_POST['redirect_url'],
				 'email_suffix' => $_POST['email_suffix'],
				 'include_path' => $_POST['include_path'],
				 'cas_version' => $_POST['cas_version'],
				 'server_hostname' => $_POST['server_hostname'],
				 'server_port' => $_POST['server_port'],
				 'server_path' => $_POST['server_path']
				 );

		update_option('wpcas_options', $optionarray_update);
	}
	
	// Get Options
	$optionarray_def = get_option('wpcas_options');
	
	?>
	<div class="wrap">
	<h2>CAS Authentication Options</h2>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>&updated=true">
	<h3><?php _e( 'wpCAS options', 'wpcas' ) ?></h3>
	<h4><?php _e( 'Note', 'wpcas' ) ?></h4>
	<p><?php _e( 'Now that youÕve activated this plugin, WordPress is attempting to authenticate using CAS, even if itÕs not configured or misconfigured.', 'wpcas' ) ?></p>
	<p><?php _e( 'Save yourself some trouble, open up another browser or use another machine to test logins. That way you can preserve this session to adjust the configuration or deactivate the plugin.', 'wpcas' ) ?></p>
	<h4><?php _e( 'Also note', 'wpcas' ) ?></h4>
	<p><?php _e( 'These settings are overridden by the <code>wpcas-conf.php</code> file, if present.', 'wpcas' ) ?></p>

	<h4><?php _e( 'phpCAS include path', 'wpcas' ) ?></h4>
	<table width="700px" cellspacing="2" cellpadding="5" class="editform">
		<tr>
			<td colspan="2"><?php _e( 'Full absolute path to CAS.php script', 'wpcas' ) ?></td>
		</tr>
		<tr valign="center"> 
			<th width="300px" scope="row"><?php _e( 'CAS.php path', 'wpcas' ) ?></th> 
			<td><input type="text" name="include_path" id="include_path_inp" value="<?php echo $optionarray_def['include_path']; ?>" size="35" /></td>
		</tr>
	</table>		
	
	<h4><?php _e( 'phpCAS::client() parameters', 'wpcas' ) ?></h4>
	<table width="700px" cellspacing="2" cellpadding="5" class="editform">
		<tr valign="center"> 
			<th width="300px" scope="row">CAS versions</th> 
			<td><select name="cas_version" id="cas_version_inp">
				<option value="2.0" <?php echo ($optionarray_def['cas_version'] == '2.0')?'selected':''; ?>>CAS_VERSION_2_0</option>
				<option value="1.0" <?php echo ($optionarray_def['cas_version'] == '1.0')?'selected':''; ?>>CAS_VERSION_1_0</option>
			</td>
		</tr>
		<tr valign="center"> 
			<th width="300px" scope="row"><?php _e( 'server hostname', 'wpcas' ) ?></th> 
			<td><input type="text" name="server_hostname" id="server_hostname_inp" value="<?php echo $optionarray_def['server_hostname']; ?>" size="35" /></td>
		</tr>
		<tr valign="center"> 
			<th width="300px" scope="row"><?php _e( 'server port', 'wpcas' ) ?></th> 
			<td><input type="text" name="server_port" id="server_port_inp" value="<?php echo $optionarray_def['server_port']; ?>" size="35" /></td>
		</tr>
		<tr valign="center"> 
			<th width="300px" scope="row"><?php _e( 'server path', 'wpcas' ) ?></th> 
			<td><input type="text" name="server_path" id="server_path_inp" value="<?php echo $optionarray_def['server_path']; ?>" size="35" /></td>
		</tr>
	</table>

	<div class="submit">
		<input type="submit" name="submit" value="<?php _e('Update Options') ?> &raquo;" />
	</div>
	</form>
<?php
}
?>

<?php
// --------------------------------------------------------------------------------
// Une fonction de mise en session des donnŽes du jeton CAS. (connexion avec CAS)
// --------------------------------------------------------------------------------
function setCASdataInSession() {
	$tab = array();
	
	$content = $_SESSION['phpCAS']['response'];
	$content = str_replace("\t", "", $content);
	$content = str_replace("\n", "", $content);
	$p = xml_parser_create();
	
	xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($p, $content, $values, $index);
	// traitement des erreurs
	$ReturnStatus = xml_get_error_code($p);
	if ($ReturnStatus != 0) {
    	echo("erreur de parsing du fichier XML '$file': ".xml_get_error_code($p).
    				" - ".xml_error_string(xml_get_error_code($p)).
    				" à la ligne ".xml_get_current_line_number($p).
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
//  Classe cd client CAS
// --------------------------------------------------------------------------------
class wpCAS {
	/*
	 We call phpCAS to authenticate the user at the appropriate time 
	 (the script dies there if login was unsuccessful)
	 If the user is not provisioned, wpcas_nowpuser() is called
	*/
	function authenticate() {
		global $wpcas_options, $cas_configured;
		
		
		if ( !$cas_configured )
			die( __( 'wpCAS plugin not configured', 'wpcas' ));
		if( phpCAS::isAuthenticated() ){
			// CAS was successful so sets session variables
			setCASdataInSession();
			
			if ( $user = get_userdatabylogin( phpCAS::getUser() )){ // user already exists
				// the CAS user has a WP account
				wp_set_auth_cookie( $user->ID );
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
		global $wpcas_options;
		if ($_REQUEST['ENT_action'] == 'IFRAME') {
			$qry = '?ENT_action=IFRAME';
			$url =  home_url().'/wp-login.php'.$qry;
		}
		// Si on n'est pas en mode intégré
		else {
			//$url = home_url().'/wp-login.php';
		
		if ($wpcas_options['server_port'] == 443) $protoc = "https://";
		else $protoc = "http://";
		$url = $protoc.
			   $wpcas_options['server_hostname'].
			   (($wpcas_options['server_port'] != 80 )? ":".$wpcas_options['server_port'] : "").
			   $wpcas_options['server_path'].
			   "login?service=".urlencode(home_url()."/wp-login.php");
			   
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
			
		phpCAS::logout( array( 'url' => get_option( 'siteurl' ).$qry ));
		exit();
	}

	// hide password fields on user profile page.
	function show_password_fields( $show_password_fields ) {
		return false;
	}

	// disabled reset, lost, and retrieve password features
	function disable_function_user() {
		echo( __( 'La  fonction d\'ajout d\'utilisateur est d&eacute;sactiv&eacute;e. Passez par l\'ENT '.NOM_ENT.' pour ajouter des utilisateurs &agrave; votre blog.', 'wpcas' ));
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

// do we have a valid options array? fetch the options from the DB if not
if( !is_array( $wpcas_options )){
	$wpcas_options = get_option( 'wpcas_options' );
	add_action( 'admin_menu', 'wpcas_options_page_add' );
}

$cas_configured = true;

// try to configure the phpCAS client
if ($wpcas_options['include_path'] == '' ||
		(include_once $wpcas_options['include_path']) != true)
	$cas_configured = false;

if ($wpcas_options['server_hostname'] == '' ||
		$wpcas_options['server_path'] == '' ||
		intval($wpcas_options['server_port']) == 0)
	$cas_configured = false;

if ($cas_configured) {
	phpCAS::client($wpcas_options['cas_version'], 
		$wpcas_options['server_hostname'], 
		intval($wpcas_options['server_port']), 
		$wpcas_options['server_path']);
	
	
	// function added in phpCAS v. 0.6.0
	// checking for static method existance is frustrating in php4
	$phpCas = new phpCas();
	if (method_exists($phpCas, 'setNoCasServerValidation'))
		phpCAS::setNoCasServerValidation();
	unset($phpCas);
	
	// if you want to set a cert, replace the above few lines
 }

?>