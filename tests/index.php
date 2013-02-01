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

// Pour loguer il nous faut debug à 'O'
$_GET['debug'] = 'O';
// Paramétrage de la request par défaut
$_REQUEST['ENT_action'] = 'IFRAME';
$_REQUEST['ent'] = 'laclasse';
$_REQUEST['blogname'] = 'tests-unitaires-wp';

$domain = $_REQUEST['blogname'] . '.' .BLOG_DOMAINE; 
$site_id = 1;
$path = '/';

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
equal("Valeur lue doit etre", "123456789", getAttr('LaclasseProfil'));

setAttr("LaclasseProfil", "");
equal("Valeur lue doit etre", "", getAttr('LaclasseProfil'));

// test de la fonction setSessionlaclasseProfil
$pIn = array ('National_1','National_2','National_3','National_4','National_5','National_6','National_7', 'Profil_Inexistant???');
$pOut = array ('ELEVE','PARENT','PROF','PRINCIPAL','CPE','INVITE','INVITE','INVITE');
foreach ($pIn as $i => $p) {
  setAttr("LaclasseProfil", "");
  setAttr("ENTPersonProfils", $p);
  setSessionlaclasseProfil();
  equal("Profil doit etre", $pOut[$i], getAttr('LaclasseProfil'));
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

/********************************************************************************
Authentification obligatoire pour effectuer les tests
********************************************************************************/

phpCAS::setNoCasServerValidation();
// ensure the user is authenticated via CAS
if( !phpCAS::isAuthenticated())	wpCAS::authenticate();        
	                               

logIt ('auhtentifi&eacute; ! username='.strtolower(phpCAS::getUser()));

// Mock de la session et du jeton d'authentification
$_SESSION['phpCAS'] = array();
$_SESSION['phpCAS']['attributes'] = array();
$_SESSION['phpCAS']['attributes']['uid'] = '';
$_SESSION['phpCAS']['attributes']['login'] = '';
$_SESSION['phpCAS']['attributes']['ENT_id'] = '';
$_SESSION['phpCAS']['attributes']['laclasseNom'] = '';
$_SESSION['phpCAS']['attributes']['LaclasseSexe'] = '';
$_SESSION['phpCAS']['attributes']['LaclasseEmail'] = '';
$_SESSION['phpCAS']['attributes']['laclassePrenom'] = '';
$_SESSION['phpCAS']['attributes']['LaclasseProfil'] = '';
$_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = '';
$_SESSION['phpCAS']['attributes']['LaclasseCivilite'] = '';
$_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = '';
$_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = '';
$_SESSION['phpCAS']['attributes']['ENTPersonStructRattachRNE'] = '';

setToken($_SESSION['phpCAS']['attributes']);

setAttr('uid', 'VZZ69999');
setAttr('login', "tests-unitaires-wp");
setAttr('ENT_id', "0");
setAttr('laclasseNom', "tests-unitaires-wp");
setAttr('LaclasseSexe', "F");
setAttr('LaclasseEmail', "tests-unitaires-wp@laclasse.com");
setAttr('laclassePrenom', "tests-unitaires-wp");
setAttr('LaclasseCivilite', "Mme");
setAttr('ENTPersonStructRattachRNE', "0699990Z");


/* création du user WP de test*/
$p_username = getAttr('login');
$p_useremail = getAttr('LaclasseEmail');
$userId = get_user_id_from_string($p_useremail);
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


/****************************************************************************
 B L O G   D E   C L A S S E  :  'CLS'
****************************************************************************/
$_REQUEST['blogtype'] = 'CLS';

  //-------------------------------------------------------------------------
  // Profils enfant : ELEVE
  //-------------------------------------------------------------------------
  // Logout avant tout chose
//  wp_clear_auth_cookie();
  
  setAttr('ENTEleveClasses', "6EME5");
  setAttr('ENTEleveNivFormation', "6EME");
  
  setAttr('LaclasseProfil', "ELEVE");
  setAttr("ENTPersonProfils", 'National_1');
  
  logit('<pre>'.print_r(getToken(), true).'</pre>');
  
  rattachUserToHisBlog($domain, $path, $site_id, $userId, "contributor");
  
//  setWPCookie($userId);

//endMessage("<ul>".getLog() ."</ul>");
//die();
//
  // Appel du script de provisionning
//  include('../includes/provisionning-laclasse.php');

 
//$USERID = createUserWP('Tests-Unitaires', 'tests-unitaires@laclasse.com', 'administrator', 'tests-unitaires'.'.' .BLOG_DOMAINE);//
//echo "UserId = ".$USERID;
//echo "<br>deleted ? " .wp_delete_user( $USERID );


//
// Profils adultes 
//
//setAttr('LaclasseEmailAca', "");
