<?php
// --------------------------------------------------------------------------------
// Toutes les fonctions li�es aux hooks et aux fitres WordPress.
// --------------------------------------------------------------------------------

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	hooks et filtres g�n�raux

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// fonction pour ajouter la marque de l'ENT dans le footer du back-office.
// hook : admin_footer_text
// --------------------------------------------------------------------------------
function addEntName () {
  echo "Plateforme de blogs de <a href='http://".SERVEUR_ENT."/' target='_blank'>".NOM_ENT."</a>.";
}

// --------------------------------------------------------------------------------
// fonction pour ajouter la marquage du Minist�re de l'�ducation nationale sur tous 
// les footer des blogs 
// hook : admin_footer
// --------------------------------------------------------------------------------
function xiti_MEN_et_google(){
	global $current_user;
	if (strtoupper(MODE_SERVEUR) == 'PROD') {
		if ($current_user->user_login == "")
			echo '<!-- Page publique non marqu�e par xiti_men -->';
		else {
			echo '<SCRIPT TYPE="text/javascript" src="http://'.SERVEUR_ENT.'/v2/js/marqueur_men/xtfirst_ENT.js"></SCRIPT>';
			echo '<SCRIPT TYPE="text/javascript" src="http://'.SERVEUR_ENT.'/pls/public/xiti_men.get_marqueur_blogs?plogin='.$current_user->user_login.'"></SCRIPT>';
		}
		// Notre marquage Google
		echo '
<!-- Marqueur Google Analytics -->
<script src="http://www.google-analytics.com/urchin.js" type="text/javascript"></script>
<script type="text/javascript">
	_uacct = "UA-910479-6";
	urchinTracker();
</script>';
	}
	else echo '<!-- Site de d�veloppement, pas de marquage. -->';
}


// --------------------------------------------------------------------------------
// Quelques filtres pour le back-office utilisa� dans la connexion avec CAS
// --------------------------------------------------------------------------------
function disableThisFunc() {
	return false;
}


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   u t i l i s a t e u r s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

/*************************************************************************************
fonction addUsersManagmentScript : Ajouter les javascipt de management des users avec 
								   les nouvelles colonnes custos.
filter : wp_print_scripts
*************************************************************************************/
function addUsersManagmentScript() {
	$plugin_js_url = WP_PLUGIN_URL.'/ent-wp-management/js';
	wp_enqueue_script('wp_wall_script', $plugin_js_url.'/custom-user-managment.js');
}

/*************************************************************************************
fonction getUserCols : modifier l'ent�te des colonnes de la liste des utilisateurs
					   pour ajouter des donn�es issues de l'ENT.
filter : wpmu_users_columns
*************************************************************************************/
function getUserCols($userCols){
	$customCols = array('profil_ENT'  		=> __( 'profil ENT' ),
 	                	'classe_ENT'  		=> __( 'classe'),
 	                	'etablissement_ENT' => __( '&eacute;tablissement')
 	                   );
	return array_merge($userCols, $customCols);
}

/*************************************************************************************
fonction getCustomUserMeta : Fonction de r�cup�ration de la valeur des champs usermeta.
filter : manage_users_custom_column
*************************************************************************************/
function getCustomUserMeta($ignore, $colName, $userID){
	global $wpdb;
	$metaValue = "";
	$metaValue = get_user_meta( $userID, $colName, true);
	
	if ($metaValue == "") // cas des super admin du r�seau (bug ci-dessus)
		$metaValue = "-";
		
	echo apply_filters( 'ENT_WP_MGMT_format_output', $colName, $metaValue );
}


/*************************************************************************************
fonction formatMeta : Fonction de rformatage des champs custom usermeta : en fonction 
					  de leur nom.
*************************************************************************************/
function formatMeta($key, $val) {
	if ( $key == "etablissement_ENT" ) return "<a href=\"javascript:detailEtab(this, '$val');\">$val</a>";
	// sinon on retourne la valeur telle quelle.
	return  $val;
}

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	l i s t e   d e s   s i t e s 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

function formatTypeBlog($pBlogID, $pTypeBlog) {
	switch ($pTypeBlog) {
		case "CLS" : $LibTypeBlog = "Blog de classe"; $color = "chocolate"; break;
		case "GRP" : $LibTypeBlog = "Blog de groupe d'&eacute;l&egrave;ves"; $color = "lightgreen"; break;
		case "ENV" : $LibTypeBlog = "Blog de groupe de travail"; $color = "green"; break;
		case "ETB" : $LibTypeBlog = "Blog d'&eacute;tablissement"; $color = "saddlebrown"; break;
		default	   : if ($pBlogID == 1) $LibTypeBlog = "<strong>Blog principal</strong>";
					 else $LibTypeBlog = "inconnu...";
					 $color = "red";  
					 break;
	}
	return "<span style='color:".$color.";'>".$LibTypeBlog."</span>";
}

/*************************************************************************************
fonction getCustomSiteMeta : Fonction de r�cup�ration de la valeur des champs blogmeta.
filter : manage_blogs_custom_column
*************************************************************************************/
function getCustomSiteMeta($colName, $blogID) {
	$typeBlog = get_blog_option($blogID, 'type_de_blog');
	echo formatTypeBlog($blogID, $typeBlog);
}

/*************************************************************************************
fonction getBlogsCols : modifier l'ent�te des colonnes de la liste des sites
					   pour ajouter des donn�es issues de l'ENT.
filter : wpmu_blogs_columns
*************************************************************************************/
function getBlogsCols() {
	$blogname_columns = ( is_subdomain_install() ) ? __( 'Domain' ) : __( 'Path' );
	return array(
 	                'id'           => __( 'ID' ),
 	                'blogname'     => $blogname_columns,
	                'lastupdated'  => __( 'Last Updated'),
	                'registered'   => _x( 'Registered', 'site' ),
 	                'users'        => __( 'Users' ),
 	                'type_de_blog' => __('type de blog')
 	           );
}

/*************************************************************************************
fonction getCustomExtraInfoBlog : Fonction de r�cup�ration de la valeur des champs blogmeta.
filter : myblogs_options
*************************************************************************************/
function getCustomExtraInfoBlog($ignore, $user_blog) {
	if ( !is_string($user_blog) && $user_blog != 'global') {
	$typeBlog = get_blog_option($user_blog->userblog_id, 'type_de_blog');
	echo "<div style='text-align:right;padding-right:40px;'>".formatTypeBlog($user_blog->userblog_id, $typeBlog)."</div>";
	}
}

/*************************************************************************************
fonction getCustomActionBlog : Ajout d'action aux blogs de l'utilisateur
filter : myblogs_blog_actions
*************************************************************************************/
function getCustomActionBlog($ActionExistantes, $user_blog) {
	
	echo $ActionExistantes. "&nbsp;|&nbsp;<a href='?blogid=".$user_blog->userblog_id."&action=DESINSCRIRE'>Me d&eacute;sinscrire</a>";
}

/*************************************************************************************
fonction actionsBlog : Traitement de l'action de d�sinscription
action : myblogs_allblogs_options
*************************************************************************************/
function actionsBlog() {
	global $current_user;
	$action = $_REQUEST["action"];
	$blogid = $_REQUEST["blogid"];
	if ($action == 'DESINSCRIRE') {
		if (aUnRoleSurCeBlog($current_user->ID, $blogid)) {
			$cu = (array) $current_user;
			foreach ($cu["wp_".$blogid."_capabilities"] as $role => $val) {

				remove_user_from_blog($current_user->ID, $blogid);
			}
		}
	}
}

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	f o n c t i o n s   d e   m o d i f i c a t i o n  d u   b l o g 

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// hook de modif des param�tres g�n�raux pour mise � jour de l'ENT.
// La technique consiste � rajouter un hook sur la validation des options g�n�rales
// transmettre les modifications de blogtitle et blogdescription � l'ENT.
//
// hook : update_option
// --------------------------------------------------------------------------------
function synchroENT ($optionName, $old, $new) {
	// Ce hook est lanc� lors de toutes les mise � jours d'options, 
	// il faut donc filtrer par rapport � la page.
	if ($_SERVER['SCRIPT_NAME'] == "/wp-admin/options.php") {
		// On ne fait �a que si on a chang� quelquechose.
		if (($optionName == "blogname" || $optionName == "blogdescription") && $old != $new) {
			$idBlogEnt = get_blog_option(getBlogIdByDomain($domain),'idBlogENT');
			// On ne fait des modif que si on n'est pas sur le blog des blogs.
			if ($idBlogEnt > 1) {
				$urlMajENT = "http://".SERVEUR_ENT."/pls/education/blogv2.setBlogMeta";
				$urlMajENT .= "?pBlogId=".$idBlogEnt."&pname=".urlencode("$optionName")."&pvalue=".urlencode("$new");
				$ret = get_http($urlMajENT);
				if ($ret != "OK") {
					message($ret);
					die();
				}
			}
		}
	}
}


?>