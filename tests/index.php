<?php
/**
	Fichier de tests unitaire pour le provisionning laclasse.
	@file UnitTest.php
	@author PGL pgl@erasme.org

*/

// Requires WordPress
require_once( $_SERVER["DOCUMENT_ROOT"].'/wp-load.php');
require_once( ABSPATH . WPINC . '/registration.php' );
//require_once( ABSPATH . 'wp-admin/includes/ms.php' );
require_once( ABSPATH . 'wp-admin/includes/user.php' );
// Requires applicatifs
require_once('../ENTconfig.inc.php');
require_once( '../includes/unittest.inc.php');
require_once( '../includes/functions.inc.php');
require_once( '../includes/cas-token-functions.inc.php');
require_once( '../includes/provisionning-functions.inc.php');

$mockToken = array();
$mockToken['phpCas'] = array();
$mockToken['phpCAS']['attributes'] = array();
$mockToken['phpCAS']['attributes']['LaclasseProfil'] = "";

// test setToken
setToken($mockToken['phpCAS']['attributes']);
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

/*
  Le test consiste à avoir un utilisateur de test et un site de test.
  On lui affecte tous les profils à tour de role et on regarde si le comportement est celui qu'on attend.
   
*/
// Mock du jeton d'uthentification
$laclasseUserUid        = getAttr('uid', $username);
$laclasseUserCodeRne    = getAttr('ENTPersonStructRattachRNE', "");
$laclasseUserId         = getAttr('ENT_id', "");
$laclasseUserProfil     = getAttr('LaclasseProfil', "");
$laclasseUserClasse     = getAttr('ENTEleveClasses', "");
$laclasseUserNivClasse  = getAttr('ENTEleveNivFormation', "");
$laclasseUserMail       = getAttr('LaclasseEmail', "");
$laclasseUserMailAca    = getAttr('LaclasseEmailAca', "");
$laclasseUserNom        = getAttr('laclasseNom', "");
$laclasseUserPrenom     = getAttr('laclassePrenom', "");

// Création de l'utilisateur. Si celui-ci existe, il est juste mis a jour.

$USERID = createUserWP('Tests-Unitaires', 'tests-unitaires@laclasse.com', 'administrator', 'tests-unitaires'.'.' .BLOG_DOMAINE);
echo "UserId = ".$USERID;
echo "<br>deleted ? " .wp_delete_user( $USERID );
