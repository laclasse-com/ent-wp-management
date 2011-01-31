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
		'privacy');
 	
 	// Ajout du champs "student-privacy" dans cette section
 	add_settings_field('student-privacy',
		'Signature des élèves',
		'formulaireAnonymat',
		'privacy',
		'student_privacy_section');
 	
 	// Enregistrement dans $_POST
 	register_setting('privacy','student-privacy');
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
  	if (!defined('WP_ADMIN')) $BackOffice = 0;
  	else  $BackOffice = 1;
  	
  	// si l'anonymat a été paramètré on affiche un nom anonymisé
  	if ($profil == 'ELEVE' && $choixAnonymat == 1 ) {
  		$uidAnonyme = get_usermeta( $userId, 'uid_ENT');
  		if ($BackOffice == 1) return strtolower($uidAnonyme)." <br/><small>(".$signature.")</small>";
  		return strtolower($uidAnonyme);
  	}
  	// Ici FO ou BO oun s'en fout on affiche la signature
  	return $signature;
  
  }

 // ------------------------------------------------------------------
 // Fonction d'affichage de la signature anonymisée sur les articles
 // ------------------------------------------------------------------
  function gereAnonymatArticle($post_author){
  	global $post;
  	$profil = get_usermeta( $post->post_author, 'profil_ENT');
  	return getSignature($post_author, $post->post_author, $profil);
  }

 // ------------------------------------------------------------------
 // Fonction d'affichage de la signature anonymisée sur les commentaires
 // ------------------------------------------------------------------
  function gereAnonymatCommentaire($comment_author){
  	global $comment;
  	$profil = get_usermeta( $comment->user_id, 'profil_ENT');
  	return getSignature($comment_author, $comment->user_id, $profil);  	
  }

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	h o o k   G E S T I O N   D E   L ' A N O N Y M A T 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
 add_action('admin_init', 'gestionAnonymatEleves');
 
 add_filter('the_author', 'gereAnonymatArticle', 10, 1);
 add_filter('get_comment_author', 'gereAnonymatCommentaire', 10, 1);
?>