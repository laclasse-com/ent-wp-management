<?php

/**
* Example for a simple cas 2.0 client
*
* PHP Version 5
*
* @file example_simple.php
* @category Authentication
* @package PhpCAS
* @author Joachim Fritschi <jfritschi@freenet.de>
* @author Adam Franco <afranco@middlebury.edu>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
* @link https://wiki.jasig.org/display/CASC/phpCAS


  	'cas_version' => '2.0',
  	'include_path' => '/var/www/html/CAS-1.2.0/CAS.php',

*/
//-----------------------------------------------------------------------------------------
function script_info() {
  echo "<dl style='border: 1px dotted; padding: 5px;'>
        <dt>Current script</dt><dd>".basename($_SERVER['SCRIPT_NAME'])."</dd>
        <dt>session_name():</dt><dd>".session_name()."</dd>
        <dt>session_id():</dt><dd>".session_id()."</dd>
        </dl>";
}
//-----------------------------------------------------------------------------------------

$cas_host = 'cas.cybercolleges42.fr';
//$cas_host = 'www.dev.laclasse.com';
$cas_port = 443;
$cas_context = '';
//$cas_context = '/sso';

// Load the CAS lib
require_once '/var/www/html/CAS-1.2.0/CAS.php';

// Uncomment to enable debugging
phpCAS::setDebug('/var/www/html/wpmu/wp-content/plugins/ent-wp-management/includes/casClient.log');

// Initialize phpCAS
/*
$server_version,
	$proxy,
	$server_hostname,
	$server_port,
	$server_uri,
	$start_session = true
*/
phpCAS::client(CAS_VERSION_2_0, $cas_host, $cas_port, $cas_context);

// For production use set the CA certificate that is the issuer of the cert
// on the CAS server and uncomment the line below
// phpCAS::setCasServerCACert($cas_server_ca_cert_path);

// For quick testing you can disable SSL validation of the CAS server.
// THIS SETTING IS NOT RECOMMENDED FOR PRODUCTION.
// VALIDATING THE CAS SERVER IS CRUCIAL TO THE SECURITY OF THE CAS PROTOCOL!
phpCAS::setNoCasServerValidation();

// set the language to french
phpCAS::setLang(PHPCAS_LANG_FRENCH);

// force CAS authentication
phpCAS::forceAuthentication();

// at this step, the user has been authenticated by the CAS server
// and the user's login name can be read with phpCAS::getUser().

// logout if desired
if (isset($_REQUEST['logout'])) {
phpCAS::logout();
}

// for this test, simply print that the authentication was successfull
?>
<html>
<head>
<title>phpCAS simple client</title>
</head>
<body>
<h1>Successfull Authentication!</h1>
<?php script_info(); ?>
<p>the user's login is <b><?php echo phpCAS::getUser(); ?></b>.</p>
<p>phpCAS version is <b><?php echo phpCAS::getVersion(); ?></b>.</p>
<p><a href="?logout=">Logout</a></p>
<?php print_r(phpCAS::getAttributes()); ?>
<?php print_r($_SESSION); ?>

</body>
</html>