<?php
/**
 * Handles redirection of old links using the Multisite pre 3.5 (i.e: /files/{YEAR}/{MONTH}/{FILE_NAME})
 * to the default way of storing files in the version currently used (4.9.8)
 * 
 * This script allows to serve the files using Apache instead of Worpdress 
 * 
 */

define( 'SHORTINIT', true );
require_once(   dirname(dirname(dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

if ( !is_multisite() )
	die( 'Multisite support not enabled' );

if ( $current_blog->archived == '1' || $current_blog->spam == '1' || $current_blog->deleted == '1' ) {
	status_header( 404 );
	die( '404 &#8212; File not found.' );
}

header('Status: 301 Moved Permanently', false, 301); 
header('Location: /wp-content/uploads/sites/'. $current_blog->blog_id .'/' . $_GET[ 'file' ] );
die();