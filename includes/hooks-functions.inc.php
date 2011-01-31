<?php
// --------------------------------------------------------------------------------
// Toutes les fonctions liées aux hooks et aux fitres WordPress.
// --------------------------------------------------------------------------------

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	
	hooks et filtres généraux

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// --------------------------------------------------------------------------------
// fonction pour ajouter la marque de l'ENT dans le footer du back-office.
// hook : admin_footer_text
// --------------------------------------------------------------------------------
function addEntName () {
  echo "Plateforme de blogs de <a href='http://".SERVEUR_ENT."/' target='_blank'>".NOM_ENT."</a>.";
}


// --------------------------------------------------------------------------------
// Quelques filtres pour le back-office utilisaé dans la connexion avec CAS
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
fonction getUserCols : modifier l'entête des colonnes de la liste des utilisateurs
					   pour ajouter des données issues de l'ENT.
filter : wpmu_users_columns
*************************************************************************************/
function getUserCols(){
	return array(
 	                'id'           	=> __( 'ID' ),
 	                'login'      	=> __( 'Username' ),
 	                'name'       	=> __( 'Name' ),
 	                'email'      	=> __( 'E-mail' ),
 	                'registered' 	=> _x( 'Registered', 'user' ),
 	                'blogs'      	=> __( 'Blogs' ),
 	                'profil_ENT'  	=> __( 'profil ENT' ),
 	                'classe_ENT'  	=> __( 'classe'),
 	                'etablissement_ENT' => __( '&eacute;tablissement')
 	            );
}

/*************************************************************************************
fonction getCustomUserMeta : Fonction de récupération de la valeur des champs usermeta.
filter : manage_users_custom_column
*************************************************************************************/
function getCustomUserMeta($colName, $userID){
	global $wpdb;
	$metaValue = "";
	// La dernière requête SQL porte justement sur la table wp_usermeta.
	// SELECT user_id, meta_key, meta_value FROM wp_usermeta WHERE user_id IN (id du user)
	// sauf pour les supers admin du site où le user id est toujours 1 (Pourquoi ? bug WP ?)
	// SELECT user_id, meta_key, meta_value FROM wp_usermeta WHERE user_id IN (1)
	
	foreach ($wpdb->last_result as $elt => $obj) {
		if ($obj->meta_key == $colName) {
			$metaValue = $obj->meta_value;
			quit;
		}
	}
	if ($metaValue == "") // cas des super admin du réseau (bug ci-dessus)
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
fonction getCustomSiteMeta : Fonction de récupération de la valeur des champs blogmeta.
filter : manage_blogs_custom_column
*************************************************************************************/
function getCustomSiteMeta($colName, $blogID) {
	$typeBlog = get_blog_option($blogID, 'type_de_blog');
	switch ($typeBlog) {
		case "MASTER" : echo "Blog principal"; break;
		case "CLS" : echo "classe"; break;
		case "GRP" : echo "Groupe d'&eacute;l&egrave;ves"; break;
		case "ENV" : echo "Groupe de travail"; break;
		case "ETB" : echo "Etablissement"; break;
		default	   : echo "inconnu !"; break;
	}
}

/*************************************************************************************
fonction getBlogsCols : modifier l'entête des colonnes de la liste des sites
					   pour ajouter des données issues de l'ENT.
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
// hook de modif des paramètres généraux pour mise à jour de l'ENT.
// La technique consiste à rajouter un hook sur la validation des options générales
// transmettre les modifications de blogtitle et blogdescription à l'ENT.
//
// hook : update_option
// --------------------------------------------------------------------------------
function synchroENT ($optionName, $old, $new) {
	// Ce hook est lancé lors de toutes les mise à jours d'options, 
	// il faut donc filtrer par rapport à la page.
	if ($_SERVER['SCRIPT_NAME'] == "/wp-admin/options.php") {
		// On ne fait ça que si on a changé quelquechose.
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