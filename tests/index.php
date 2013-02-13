<?php
/********************************************************************************
	Fichier de tests unitaire pour le provisionning laclasse.
	@file UnitTest.php
	@author PGL pgl@erasme.org
	
	
  Super Admin   - Someone with access to the blog network administration features controlling the entire network (See Create a Network).
  Administrator - Somebody who has access to all the administration features
  Editor        - Somebody who can publish and manage posts and pages as well as manage other users' posts, etc.
  Author        - Somebody who can publish and manage their own posts
  Contributor   - Somebody who can write and manage their posts but not publish them
  Subscriber    - Somebody who can only manage their profile 



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
  Paramétrage de la request pour simuler un pilotage depuis laclasse.Com
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
  Logout + login et lancement du script de provisionning
********************************************************************************/
function login_et_provisionne($profil){
  $usr = getAttr('login');
  $pwd = $usr;

  echo('<hr/><h1>profil '.$profil.'</h1><hr/>');
  echo('<!--Jeton simul&eacute; : <pre style="font-size:11px;">'.print_r(getToken(), true).'</pre>-->');

  // Logout
  wp_clear_auth_cookie();
  //login 
  //$r = wp_authenticate($usr, $pwd); // A priori pas besoin.  
  
  // test du provisionning
  resetLog();
  provision_comptes_laclasse($usr);
  
  //echo getLog();
  $userId = get_user_id_from_string( $usr );
  return $userId;
}

/********************************************************************************
  Fonction de test de tous les roles de wordPress : On passe un tableau de false 
  et de true et on vérifie pour chaque role qu'on a bien false ou true.
********************************************************************************/
function testerTousLesRoles($userId, $blogId, $shouldBe, $shouldBeSuperAdmin=false){
  $rolesATester = array('subscriber', 'contributor', 'author', 'editor', 'administrator');
  
  // test du profil sur le blog.
  equal("le user #".$userId." doit-il être super-admin ?", $shouldBeSuperAdmin, is_super_admin($userId));
  if ( $blogId ) {
    equal("le user #".$userId." doit avoir un role sur le blog #".$blogId, true, aUnRoleSurCeBlog($userId, $blogId));
  } else {
    equal("le user #".$userId." ne doit pas avoir un role sur le blog #".$blogId, false, aUnRoleSurCeBlog($userId, $blogId));
  }
  // Test de tout les profils
  foreach ($rolesATester as $k => $rol) {
    equal("le user #".$userId.", rôle '".$rol."', blog #".$blogId, $shouldBe[$k], aLeRoleSurCeBlog($userId, $blogId, $rol));
  }
}

/********************************************************************************
  Fonction de test de tous les roles de wordPress : On passe un tableau de false 
  et de true et on vérifie pour chaque role qu'on a bien false ou true.
********************************************************************************/
function trashUser($userId) {
  if (wpmu_delete_user($userId, 1)) echo 'Le user #'.$userId.' a &eacute;t&eacute; supprim&eacute; !';
}

/********************************************************************************
  Fonction de test globale.
********************************************************************************/
function teste($libelle, $shouldBe, $shouldBeSuperAdmin=false){
  // Récupérer le blogID
  $blogId = getBlogIdByDomain( $_REQUEST['blogname'] . '.' . BLOG_DOMAINE );
  $userId = login_et_provisionne($libelle);
  
  // Il se peut qu'il existe pas ce blogid quand on test la creation de blog par exemple.
  // On le resélectionne après provisionning pour voir...
  if (!$blogId) $blogId = getBlogIdByDomain( $_REQUEST['blogname'] . '.' . BLOG_DOMAINE );
  
  testerTousLesRoles($userId, $blogId, $shouldBe, $shouldBeSuperAdmin);
  // Renvoyer le userId pour d'autres usages
  return $userId;
}

/********************************************************************************
  Fonction de restauration du contexte de test pour les test suivants
********************************************************************************/
function restaurer_contexte_tests() {
  // Pour le blog
  $blogId = getBlogIdByDomain( $_REQUEST['blogname'] . '.' . BLOG_DOMAINE );
  wpmu_delete_blog($blogId, true );
  echo 'Le blog #'.$blogId.' a &eacute;t&eacute; supprim&eacute; !';
  $_REQUEST['blogname'] = 'tests-unitaires-wp';
}


/********************************************************************************
*********************************************************************************
*****************       S T A R T I N G   T E S T S         *********************
*********************************************************************************
********************************************************************************/
startTest('<h1>Test unitaires du '.date("Y-m-d H:i:s").'</h1>');

/********************************************************************************
  Suppression de l'authentification CAS pour les tests
********************************************************************************/
echo('Suppression de l\'authentification CAS pour les tests');
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

echo('<hr/><h1>Test de provisionning du '.date("Y-m-d H:i:s").'</h1><hr/>');

/********************************************************************************
Authentification obligatoire pour effectuer les tests
********************************************************************************/
if (!isset($_SESSION['phpCAS'])) $_SESSION['phpCAS'] = array();
if (!isset($_SESSION['phpCAS']['attributes'])) $_SESSION['phpCAS']['attributes'] = array();

// Mock de la session et du jeton d'authentification
$_SESSION['phpCAS']['user'] = 'tests-unitaires-wp';
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
setToken($_SESSION['phpCAS']['attributes']);

//$blogId = getBlogIdByDomain( $_REQUEST['blogname'] . '.' . BLOG_DOMAINE );

/****************************************************************************
 B L O G   D E   C L A S S E  :  'CLS'
****************************************************************************/
$_REQUEST['blogtype'] = 'CLS';


  //-------------------------------------------------------------------------
  // Profils enfant : ELEVE
  //-------------------------------------------------------------------------  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "ELEVE";
  $_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = "6EME5";
  $_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = "6EME";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_1';
  setToken($_SESSION['phpCAS']['attributes']);
  
  // 1. BLOG EXISTANT et COMPTE A CREER
  // 'subscriber','contributor','author','editor','administrator'
  $droits = array(false, true, false, false, false);
  $userId = teste('ELEVE, BLOG EXISTANT et COMPTE A CREER', $droits);
  
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, false);
  $userId = teste('ELEVE, BLOG A CREER et COMPTE EXISTANT', $droits);

  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
  trashUser($userId);
   
  //-------------------------------------------------------------------------
  // Profils adulte : PARENT
  //-------------------------------------------------------------------------  
  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "PARENT";
  $_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = "";
  $_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = "";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_2';
  setToken($_SESSION['phpCAS']['attributes']);

  // 1. BLOG EXISTANT et COMPTE A CREER
  $droits = array(true, false, false, false, false);
  $userId = teste('PARENT, BLOG EXISTANT et COMPTE A CREER', $droits);
  
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, false);
  $userId = teste('PARENT, BLOG A CREER et COMPTE EXISTANT', $droits);

  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
  trashUser($userId);
  
  //-------------------------------------------------------------------------
  // Profils adulte : PROF
  //-------------------------------------------------------------------------  
  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "PROF";
  $_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = "";
  $_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = "";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_3';
  setToken($_SESSION['phpCAS']['attributes']);
    
  // 1. BLOG EXISTANT et COMPTE A CREER
  $droits = array(false, false, false, true, false);
  $userId = teste('PROF, BLOG EXISTANT et COMPTE A CREER', $droits);
  
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, true);
  $userId = teste('PROF, BLOG A CREER et COMPTE EXISTANT', $droits);

  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
  trashUser($userId);

  //-------------------------------------------------------------------------
  // Profils adulte : ADM_ETB
  //-------------------------------------------------------------------------  
  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "ADM_ETB";
  $_SESSION['phpCAS']['attributes']['ENTEleveClasses'] = "";
  $_SESSION['phpCAS']['attributes']['ENTEleveNivFormation'] = "";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_3';
  setToken($_SESSION['phpCAS']['attributes']);
  
  // 1. BLOG EXISTANT et COMPTE A CREER
  $droits = array(false, false, false, true, false);
  $userId = teste('ADM_ETB, BLOG EXISTANT et COMPTE A CREER', $droits);
  
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, true);
  $userId = teste('ADM_ETB, BLOG A CREER et COMPTE EXISTANT', $droits);

  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
  trashUser($userId);
  
  //-------------------------------------------------------------------------
  // Profils adulte : PRINCIPAL
  //-------------------------------------------------------------------------  
  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "PRINCIPAL";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_4';
  setToken($_SESSION['phpCAS']['attributes']);
  
  // 1. BLOG EXISTANT et COMPTE A CREER
  $droits = array(false, false, false, true, false);
  $userId = teste('PRINCIPAL, BLOG EXISTANT et COMPTE A CREER', $droits);
  
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, true);
  $userId = teste('PRINCIPAL, BLOG A CREER et COMPTE EXISTANT', $droits);

  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
  trashUser($userId);
  
  //-------------------------------------------------------------------------
  // Profils adulte : CPE
  //-------------------------------------------------------------------------  
  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "CPE";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_5';
  setToken($_SESSION['phpCAS']['attributes']);
  
  // 1. BLOG EXISTANT et COMPTE A CREER
  $droits = array(false, false, false, true, false);
  $userId = teste('CPE, BLOG EXISTANT et COMPTE A CREER', $droits);
  
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, true);
  $userId = teste('CPE, BLOG A CREER et COMPTE EXISTANT', $droits);

  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
  trashUser($userId);
  
  //-------------------------------------------------------------------------
  // Profils adulte : ADMINISTRATEUR
  //-------------------------------------------------------------------------  
  
  $_SESSION['phpCAS']['attributes']['LaclasseProfil'] = "ADMIN";
  $_SESSION['phpCAS']['attributes']['ENTPersonProfils'] = 'National_3';
  setToken($_SESSION['phpCAS']['attributes']);
  
  // 1. BLOG EXISTANT et COMPTE A CREER
  $droits = array(false, false, false, false, true);
  $userId = teste('ADMIN, BLOG EXISTANT et COMPTE A CREER', $droits, true);
  echo getLog();
  // 2. BLOG A CREER et COMPTE EXISTANT
  $_REQUEST['blogname'] = 'tests-unitaires-wp-ne-devrait-pas-exister';

  $droits = array(false, false, false, false, true);
  $userId = teste('ADMIN, BLOG A CREER et COMPTE EXISTANT', $droits, true);
  echo getLog();
  // 3. Restauration des variables de tests pour la suite
  restaurer_contexte_tests();
   trashUser($userId);
  
  
  /*
  @TODO : 
    - BLOG A CREER et COMPTE EXISTANT
    - BLOG A CREER et COMPTE A CREER
  */
  
endTest();