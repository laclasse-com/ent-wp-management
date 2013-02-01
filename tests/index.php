<?php
/********************************************************************************
	Fichier de tests unitaire pour le provisionning laclasse.
	@file UnitTest.php
	@author PGL pgl@erasme.org

********************************************************************************/

// Requires WordPress
require_once( $_SERVER["DOCUMENT_ROOT"].'/wp-load.php');
require_once( ABSPATH . WPINC . '/registration.php' );
//require_once( ABSPATH . 'wp-admin/includes/ms.php' );
require_once( ABSPATH . 'wp-admin/includes/user.php' );
// Requires applicatifs
require_once('../ENTconfig.inc.php');
require_once( '../includes/functions.inc.php');
require_once( '../includes/cas-token-functions.inc.php');
require_once( '../includes/provisionning-functions.inc.php');
require_once( '../includes/unittest.inc.php');

/********************************************************************************
  Paramétrage de la request pour simuler un pilotage deuis laclasse.Com
********************************************************************************/
// Pour loguer il nous faut debug à 'O'
$_GET['debug'] = 'O';
$_GET['mode_test'] = 'O';
// Paramétrage de la request par défaut
$_REQUEST['ENT_action'] = 'IFRAME';
$_REQUEST['ent'] = 'laclasse';
$_REQUEST['blogname'] = 'tests-unitaires-wp';
//$_REQUEST['ENT_action'] = '';

/********************************************************************************
  Suppression de l'authentification CAS pour les tests
********************************************************************************/
logit('Suppression de l\'authentification CAS pour les tests');
/* suppression de l'authentification CAS */
remove_all_actions('wp_authenticate');
remove_all_actions('wp_logout');
remove_all_actions('lost_password');
remove_all_actions('retrieve_password');
remove_all_actions('check_passwords');
remove_all_actions('password_reset');
remove_all_actions('show_password_fields');
remove_all_actions('show_network_site_users_add_new_form');

remove_filter('login_url',array('wpCAS', 'get_url_login'));
remove_filter('logout_url',array('wpCAS', 'get_url_logout'));

/********************************************************************************
  Tests unitaires
********************************************************************************/
logIt('<h1>Test unitaires du '.date("Y-m-d H:i:s").'</h1>');

$mockToken = array();
$mockToken['phpCAS'] = array();
$mockToken['phpCAS']['attributes'] = array();
$mockToken['phpCAS']['attributes']['LaclasseProfil'] = "";

// test setToken
setToken($mockToken);
equalType('Type doit etre', "array", getToken());

// test setAttr/getAttr
setAttr("LaclasseProfil", "123456789");
equal("Valeur avec setAttr,", "123456789", getAttr('LaclasseProfil'));

setAttr("LaclasseProfil", "");
equal("Valeur lue avec setAttr", "", getAttr('LaclasseProfil'));

// test de la fonction setSessionlaclasseProfil
$pIn = array ('National_1','National_2','National_3','National_4','National_5','National_6','National_7', 'Profil_Inexistant???');
$pOut = array ('ELEVE','PARENT','PROF','PRINCIPAL','CPE','INVITE','INVITE','INVITE');
foreach ($pIn as $i => $p) {
  setAttr("LaclasseProfil", "");
  setAttr("ENTPersonProfils", $p);
  setSessionlaclasseProfil();
  equal("Test du profil issu de setSessionlaclasseProfil (".$p.")", $pOut[$i], getAttr('LaclasseProfil'));
}

/********************************************************************************
  Le test consiste à avoir un utilisateur de test et un site de test.
  On lui affecte tous les profils à tour de role et on regarde si le 
  comportement est celui qu'on attend, dans les 3 modes suivants :
  
    1. Création du user et  creation du blog
    2. Création du user     rattachement à son blog
    3. Maj du User et       rattachement à son blog
    4. Maj du User et       creation du blog
    
  Pour les élèves et les parents :
    
    1. Création du user rattachement à son blog
    2. Maj du User et   rattachement à son blog
    3. Création du user NON creation de blog
    4. Maj du User et   NON creation de blog
    
  Et ça pour tous les types de blogs : CLS, GRP, ENV, ETB
    
********************************************************************************/
include('../includes/provisionning-laclasse.php');

logIt('<h1>Test de provisionning du '.date("Y-m-d H:i:s").'</h1>');

/********************************************************************************
Authentification obligatoire pour effectuer les tests
********************************************************************************/
if (!isset($_SESSION['phpCAS'])) $_SESSION['phpCAS'] = array();
if (!isset($_SESSION['phpCAS']['attributes'])) $_SESSION['phpCAS']['attributes'] = array();

// Mock de la session et du jeton d'authentification
$_SESSION['phpCAS']['attributes']['uid'] = 'VZZ69999';
$_SESSION['phpCAS']['attributes']['login'] = 'tests-unitaires-wp';
$_SESSION['phpCAS']['attributes']['ENT_id'] = '0';
$_SESSION['phpCAS']['attributes']['LaclasseNom'] = 'tests-unitaires-wp';
$_SESSION['phpCAS']['attributes']['LaclasseSexe'] = 'F';
$_SESSION['phpCAS']['attributes']['LaclasseEmail'] = 'tests-unitaires-wp@laclasse.com';
$_SESSION['phpCAS']['attributes']['laclassePrenom'] = 'tests-unitaires-wp';
$_SESSION['phpCAS']['attributes']['LaclasseProfil'] = '';
$_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = '';
$_SESSION['phpCAS']['attributes']['LaclasseCivilite'] = 'Mme';
$_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = '';
$_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = '';
$_SESSION['phpCAS']['attributes']['ENTPersonStructRattachRNE'] = '0699990Z';

$_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "ELEVE";
$_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = "6EME5";
$_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = "6EME";
$_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_1';
setToken($_SESSION['phpCAS']['attributes']);

/* création du user WP de test si besoin */
$p_username = getAttr('login');
$p_useremail = getAttr('LaclasseEmail');
$userId = get_user_id_from_string($p_username);
if ($userId == 0) {
    wpmu_signup_user($p_username, $p_useremail, "");
    $wpError = wpmu_validate_user_signup($p_username, $p_useremail); 
    if (is_wp_error($wpError) ) logIt($wpError->get_error_message());
    $validKey = get_activation_key($p_username);
    $activated = wpmu_activate_signup($validKey);
    if (is_wp_error($activated) ) logIt($activated->get_error_message());
    $userRec = get_user_by('login',$p_username);
    $userId = $userRec->ID;
}
logit('Utilisateur de test #'.$userId);
$blogId = getBlogIdByDomain( $_REQUEST['blogname'] . '.' . BLOG_DOMAINE );
/****************************************************************************
 B L O G   D E   C L A S S E  :  'CLS'
****************************************************************************/
$_REQUEST['blogtype'] = 'CLS';

  //-------------------------------------------------------------------------
  // Profils enfant : ELEVE
  //-------------------------------------------------------------------------  
  
  logit('Jeton simul&eacute; : <pre style="font-size:11px;">'.print_r(getToken(), true).'</pre>');
  
  // Logout 
  wp_clear_auth_cookie();
  // Login
  setWPCookie($userId);
  // test du provisionning
  provision_comptes_laclasse();
  // test du profil sur le blog.
  equal("le user #".$userId." doit avoir un role sur le blog #".$blogId, true, aUnRoleSurCeBlog($userId, $blogId));
  
  global $current_user;
  get_currentuserinfo();
  // ?????????????????????????????????
  print_r($GLOBALS);
  
  equal("le user #".$userId." doit avoir le role 'contributor' sur le blog #".$blogId, true, aLeRoleSurCeBlog($userId, $blogId, 'contributor'));
  
  
  
  //
  
// endMessage("<ul>".getLog() ."</ul>");
// die();

  // Appel du script de provisionning
  
   
//$USERID = createUserWP('Tests-Unitaires', 'tests-unitaires@laclasse.com', 'administrator', 'tests-unitaires'.'.' .BLOG_DOMAINE);//
//echo "UserId = ".$USERID;
//echo "<br>deleted ? " .wp_delete_user( $USERID );


//
// Profils adultes 
//
//setAttr('LaclasseEmailAca', "");
