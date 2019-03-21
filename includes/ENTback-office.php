<?php
/**
	Back-office pour les options du plugin ENT-WP-management.
	@file ENTback-office.php
	@author PGL pgl@erasme.org

*/
require_once(ABSPATH  . '/wp-admin/includes/template.php');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	G E S T I O N   D E   L ' A N O N Y M A T

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
 // ------------------------------------------------------------------
 // Add all your sections, fields and settings during admin_init
 // ------------------------------------------------------------------
 function gestionAnonymatEleves() {
 	// Ajout de la section dans 'Vie Privée'
 	add_settings_section('student_privacy_section',
		'Anonymat des élèves',
		'explicationAnonymat',
		'reading');

 	// Ajout du champs "student-privacy" dans cette section
 	add_settings_field('student-privacy',
		'Signature des élèves',
		'formulaireAnonymat',
		'reading',
		'student_privacy_section');

 	// Enregistrement dans $_POST
 	register_setting('reading','student-privacy');
 }

 // ------------------------------------------------------------------
 // Paragraphe d'explication
 // ------------------------------------------------------------------
 function explicationAnonymat() {
 	echo '<p>Decidez ici de ce que votre blog doit afficher pour la signature publique des
 		élèves dans les articles et les commentaires<br/>
 		Vous pouvez choisir d\'afficher leurs noms et prénoms, ou bien une chaîne de
 		caractères qui les représentera tout en leur permettant de rester anonyme.</p>';
 }

 // ------------------------------------------------------------------
 // Formulaire
 // ------------------------------------------------------------------
 function formulaireAnonymat() {
 	echo '<input name="student-privacy" id="student-privacy" type="radio" value="1" class="code" ' .
 		checked( 1, get_option('student-privacy'), false ) .
 		' /> Les noms et prénoms sont anonymisés.<br/>';
 	echo '<input name="student-privacy" id="student-privacy" type="radio" value="0" class="code" ' .
 		checked( 0, get_option('student-privacy'), false ) .
 		' /> Les noms et prénoms des élèves apparaîssent dans les articles et les commentaires.';
 }

 // ------------------------------------------------------------------
 // Fonction qui renvoie une signature anonyme ou pas en fonctions
 //	des options du blog
 // ------------------------------------------------------------------
  function getSignature($signature, $userId, $profil){
  	global $blog_id;
  	// Récupérer la valeur de l'option d'anonymat
  	$choixAnonymat = get_blog_option($blog_id, 'student-privacy', 0);

  	// Voir si on est en back-office ou en front-office.
	if (!defined('WP_ADMIN'))
	  $BackOffice = 0;
	else
	  $BackOffice = 1;

  	// si l'anonymat a été paramètré on affiche un nom anonymisé
  	if ($profil == 'ELV' && $choixAnonymat == 1 ) {
  		$uidAnonyme = get_user_meta( $userId, 'uid_ENT');
		if ($BackOffice == 1)
		  return $signature;
  		return "Un Élève";
  	}
  	return $signature;
  }

 // ------------------------------------------------------------------
 // Fonction d'affichage de la signature anonymisée sur les articles
 // ------------------------------------------------------------------
  function gereAnonymatArticle($post_author){
  	global $post;
	$profil = get_user_meta($post->post_author, 'profile_ENT', true);
  	return getSignature($post_author, $post->post_author, $profil);
  }

 // ------------------------------------------------------------------
 // Fonction d'affichage de la signature anonymisée sur les commentaires
 // ------------------------------------------------------------------
  function gereAnonymatCommentaire($comment_author, $comment_id, $comment){
  	$profil = get_user_meta($comment->user_id, 'profile_ENT', true);
  	return getSignature($comment_author, $comment->user_id, $profil);
  }

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	h o o k   G E S T I O N   D E   L ' A N O N Y M A T

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
 add_action('admin_init', 'gestionAnonymatEleves', 11);

 add_filter('the_author', 'gereAnonymatArticle', 10, 1);
 add_filter('get_comment_author', 'gereAnonymatCommentaire', 10, 3);


//
// Removes unwanted sub-menus
// Anyone wishing to do so should use our backend so we have better control on what happens
//
function admins_remove_menus () {
	remove_submenu_page( 'tools.php', 'ms-delete-site.php' );	//Delete Site
}
add_action( 'admin_menu', 'admins_remove_menus', 999 );

//
// Actually restrict accessing the sub-menu using the URL
//
function restrict_menus() {
	$screen = get_current_screen();
	$base   = $screen->id;
	if( 'ms-delete-site' == $base) {
		wp_die('You shouldn\'t try to access restricted menus');
	}

}
add_action( 'current_screen', 'restrict_menus' );
