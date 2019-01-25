<?php
/*
Plugin Name: ent-wp-managment
Plugin URI:
Description: <strong>Plugin Laclasse.com</strong>. Valable pour tout ENT s'appuyant sur CAS pour son sso. Pour l'int&eacute;gration de Wordpress dans un ENT. Gestion des utilisateurs d'un ENT pour WordPress Multi-Utilisateurs, Gestion des actions de pilotage des blogs depuis un ENT. <strong>Pr&eacute;-requis :</strong>Le plugin Akismet doit &ecirc;tre activ&eacute; pour le r&eacute;seau.
Author: Pierre-Gilles Levallois
Author: Nelson Gonçalves
Version: 0.4
Author URI: http://www.laclasse.com/
*/

/*  Copyright 2010  Pgl  (email : pgl@erasme.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
// Fichier de configuration
require_once('ENTconfig.inc.php');
// fonctions génériques
require_once('includes/functions.inc.php');
// Fonctions liées aux hooks WordPress
require_once('includes/hooks-functions.inc.php');
// Fonctions liées au pilotage d'action sur WordPress depuis l'ENT.
require_once('includes/pilotage-functions.inc.php');
require_once('includes/provisionning-functions.inc.php');
// Fonctions liées à la CASification de WordPress.
require_once('includes/cas-functions.inc.php');
// Fonctions de paramétrage du back-office des options du plugin.
require_once('includes/ENTback-office.php');
// API JSON d'administration
require_once('includes/api.php');
require_once('includes/laclasse-rest-controller.php');
require_once('includes/users-rest-controller.php');
require_once('includes/blogs-rest-controller.php');
require_once('includes/posts-rest-controller.php');

require_once(ABSPATH . WPINC . '/pluggable.php');
require_once(ABSPATH . '/wp-admin/includes/user.php');
// fonctions MU
require_once(ABSPATH . '/wp-admin/includes/ms.php');

// Classes contenant les metadonnées essentielles
// Objectif: Améliorer le temps de requêtes pour chercher les blogs visibles par
// un utilisateur
require_once('includes/ent-blog-meta.php');
require_once('includes/ent-blog-meta-query.php');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	h o o k s   p o u r   l a   C A S i f i c a t i o n   d e   W o r p r e s s .

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// plugin hooks into authentication system
add_action('wp_authenticate', array('wpCAS', 'authenticate'), 10, 2);
add_action('wp_logout', array('wpCAS', 'logout'));
add_action('lost_password', array('wpCAS', 'disable_function_pwd'));
add_action('retrieve_password', array('wpCAS', 'disable_function_pwd'));
add_action('check_passwords', array('wpCAS', 'check_passwords'), 10, 3);
add_action('password_reset', array('wpCAS', 'disable_function_pwd'));
add_filter('show_password_fields', array('wpCAS', 'show_password_fields'));
add_action('show_network_site_users_add_new_form', array('wpCAS', 'disable_function_user'));

add_filter('login_url',array('wpCAS', 'get_url_login'));
add_filter('logout_url',array('wpCAS', 'get_url_logout'));

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	h o o k s   e t   f i l t r e s   g é n é r a u x

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

// Register function to be called when plugin is activated
register_activation_hook( __FILE__, 'ent_wp_management_activation' );

// Ajout d'un texte perso dans le footer.
add_filter('admin_footer_text', 'addEntName', 10, 0);

// Maîtriser les headers http qui sont envoyés
add_action( 'login_init', 'remove_frame_options_header',12, 0 );
add_action( 'admin_init', 'remove_frame_options_header', 12, 0 );

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	l i s t e   d e s   s i t e s

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/

add_filter('wpmu_blogs_columns', 'getBlogsCols', 10, 0);
add_filter('manage_sites_custom_column', 'getCustomSiteMeta', 10, 2);
// liste des blogs de l'utilisateur
add_filter('myblogs_options', 'getCustomExtraInfoBlog', 10, 2);

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	l i s t e   d e s   a r t i c l e s

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// selectbox pour réduire la liste par auteur
add_action('restrict_manage_posts', 'restrict_manage_authors');

/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	s u p p r e s s i o n   d e   l a   n o t i f i c a t i o n

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_filter('wpmu_signup_blog_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_signup_user_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_notification', 'disableThisFunc', 10, 2);
add_filter('wpmu_welcome_user_notification', 'disableThisFunc', 10, 2);
// Disable mail on email change
add_filter( 'send_email_change_email', '__return_false' );
/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	s é c u r i s a t i o n   d e   l a   p l a t e f o r m e

http://www.geekpress.fr/wordpress/guide/7-conseils-securite-wordpress-802/
=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
// Supprimer la version de WP dans l'entête publique
remove_action('wp_head', 'wp_generator');
// Supprimer l'accès à la modification des thèmes : editeur de thème
add_action( 'admin_init', 'remove_editor_menu', 20);
add_action( '_admin_menu', 'user_role_editor_settings', 25);


/*-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-

	h o o k s   p o u r   a j o u t e r   d e s   a p i   R E S T _ A P I .

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=*/
add_action( 'rest_api_init', function () {
	(new Users_Controller())->register_routes();
	(new Blogs_Controller())->register_routes();
	(new Posts_Controller())->register_routes();
} );

add_filter( 'rest_request_after_callbacks', 'laclasse_rest_request_after_callbacks');
add_filter( 'pre_user_query', 'query_meta_OR_search' );


// Activation Callback
function ent_wp_management_activation() {
	// Get access to global database access class
	global $wpdb;

	// Create table on main blog in network mode or single blog
	if( blog_ent_meta_create_table( $wpdb->base_prefix ) ) {
		// If the table didn't exist before, we fetch all current blogs to update the
		$blogs = get_cached_blogs();
		$prepared_values = array();
		foreach($blogs as $blog) {
			// We're preparing each DB item on it's own. Makes the code cleaner.
			$prepared_values[] = sprintf("(%d, %s, %s, %s, %s)",
				$blog->id,
				isset( $blog->structure_id ) ? "'" . esc_sql( $blog->structure_id ) . "'" : 'NULL',
				isset( $blog->group_id ) ?  "'" . esc_sql( $blog->group_id ) .  "'" : 'NULL',
				"'" . esc_sql( $blog->type ) . "'",
				"'" . esc_sql( $blog->name ) . "'" );
		}

		$query = "INSERT INTO " . $wpdb->base_prefix . Ent_Blog_Meta_Query::$table_name ." (blog_id, structure_id, group_id, type, name) VALUES ";
		$query .= implode( ",\n", $prepared_values );

		$wpdb->query( $query );
	}
}

// Function to create new database table
function blog_ent_meta_create_table( $prefix ) {
	// Prepare SQL query to create database table
	// using received table prefix
	$creation_query =
		'CREATE TABLE IF NOT EXISTS ' . $prefix . Ent_Blog_Meta_Query::$table_name .' (
			`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
			`blog_id` BIGINT(20) NOT NULL,
			`structure_id` VARCHAR(10) DEFAULT NULL,
			`group_id` INT(11) DEFAULT NULL,
			`type` ENUM("CLS", "ENV", "ETB", "GPL","GRP") DEFAULT "ENV",
			`name` longtext NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE (`blog_id`),
			FOREIGN KEY (`blog_id`) REFERENCES `'. $prefix .'blogs`(`blog_id`) ON DELETE CASCADE
			);';

	global $wpdb;
	return $wpdb->query( $creation_query );
}
