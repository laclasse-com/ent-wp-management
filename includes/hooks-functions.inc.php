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
	echo "";
	$plugin_url = trailingslashit( get_bloginfo('wpurl') ).PLUGINDIR.'/'. dirname( plugin_basename(__FILE__) );
	wp_enqueue_script('wp_wall_script', $plugin_url.'/custom-user-managment.js');
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

/*************************************************************************************
fonction getCustomSiteMeta : Fonction de r�cup�ration de la valeur des champs blogmeta.
filter : manage_blogs_custom_column
*************************************************************************************/
function getCustomSiteMeta($colName, $blogID) {
	$typeBlog = get_blog_option($blogID, 'type_de_blog');
	switch ($typeBlog) {
		//case "MASTER" : echo "Blog principal"; break;
		case "CLS" : $LibTypeBlog = "Classe"; $color = "chocolate"; break;
		case "GRP" : $LibTypeBlog = "Groupe d'&eacute;l&egrave;ves"; $color = "lightgreen"; break;
		case "ENV" : $LibTypeBlog = "Groupe de travail"; $color = "green"; break;
		case "ETB" : $LibTypeBlog = "Etablissement"; $color = "saddlebrown"; break;
		default	   : if ($blogID == 1) $LibTypeBlog = "<strong>Blog principal</strong>";
					 else $LibTypeBlog = "inconnu...";
					 $color = "red";  
					 break;
	}
	
	echo "<span style='color:".$color.";'>".$LibTypeBlog."</span>";

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