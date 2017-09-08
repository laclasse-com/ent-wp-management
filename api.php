<?php

// load wordpress functions
require_once("../../../wp-load.php");

// load setup
require_once("ENTconfig.inc.php");

require_once("includes/pilotage-functions.inc.php");

if (!array_key_exists("LACLASSE_AUTH", $_COOKIE)) {
	http_response_code(401);
	exit;
}

// get the current session
$error; $status;
$session = get_http(ANNUAIRE_URL . "api/sessions/" . $_COOKIE["LACLASSE_AUTH"], $error, $status);

if ($status != 200) {
	http_response_code(401);
	exit;
}

$session = json_decode($session);

// get the user of the current session
$user = get_http(ANNUAIRE_URL . "api/users/" . $session->user, $error, $status);

if ($status != 200) {
	http_response_code(401);
	exit;
}

$user = json_decode($user);

if (!array_key_exists('path', $_REQUEST)) {
	echo "Invalid protocol, \"path\" parameter is requested";
	http_response_code(404);
	exit;
}

function blog_data($data) {
	$result = getBlogData($data->blog_id);
	foreach($data as $k => $v) { $result->$k = $v; }
	$result->id = $result->blog_id;
	unset($result->blog_id);
	$result->name = $result->blogname;
	unset($result->blogname);
	$result->description = $result->blogdescription;
	unset($result->blogdescription);
	$result->type = $result->type_de_blog;
	unset($result->type_de_blog);
	$result->url = $result->siteurl;
	unset($result->siteurl);
	if ($result->etablissement_ENT) {
		$result->structure_id = $result->etablissement_ENT;
	}
	unset($result->etablissement_ENT);
	if ($result->classe_ENT) {
		$result->group_id = $result->classe_ENT;
	}
	unset($result->classe_ENT);
	if ($result->groupe_ENT) {
		$result->group_id = $result->groupe_ENT;
	}
	unset($result->groupe_ENT);
	if ($result->groupelibre_ENT) {
		$result->group_id = $result->groupelibre_ENT;
	}
	unset($result->groupelibre_ENT);
	$result->public = $result->public == 1;
	$result->archived = $result->archived == 1;
	$result->mature = $result->mature == 1;
	$result->spam = $result->spam == 1;
	$result->deleted = $result->deleted == 1;
	if ($result->type == 'ENV') {
		unset($result->structure_id);
		unset($result->group_id);
	}
	if ($result->type == 'ETB') {
		unset($result->group_id);
	}
	return $result;
}

function rrmdir($src) {
	if(!is_dir($src))
		return;
    $dir = opendir($src);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $full = $src . '/' . $file;
            if ( is_dir($full) ) {
                rrmdir($full);
            }
            else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
}

$path = $_REQUEST['path'];
$tpath = explode('/', $path);

// GET /blogs
if ($_SERVER['REQUEST_METHOD'] == 'GET' && count($tpath) == 1 && $tpath[0] == 'blogs')
{
	header('Content-Type: application/json; charset=utf-8');
	$blogs = get_sites(array("number" => 100000));
	$result = [];
	foreach ($blogs as $blog) {
		if ($blog->blog_id == 1)
			continue;
		array_push($result, blog_data($blog));
	}
	echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
}
// GET /blogs/{id}
else if ($_SERVER['REQUEST_METHOD'] == 'GET' && count($tpath) == 2 && $tpath[0] == 'blogs')
{
	$blog_id = intval($tpath[1]);
	$data = get_site($blog_id);
	if ($data == null)
		http_response_code(404);
	else {
		$result = blog_data($data);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	}
}
// POST /blogs
else if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($tpath) == 1 && $tpath[0] == 'blogs')
{
	$json = json_decode(file_get_contents('php://input'));

	$blog_name = $json->name;
	$blog_domain = $json->domain;
	$blog_type = $json->type;
	$blog_structure_id;
	if (isset($json->structure_id)) {
		$blog_structure_id = $json->structure_id;
	}
	$blog_cls_id;
	$blog_grp_id;
	$blog_gpl_id;
	if ($blog_type == 'CLS') {
		$blog_cls_id = $json->group_id;
	}
	else if ($blog_type == 'GRP') { 
		$blog_grp_id = $json->group_id;
	}
	else if ($blog_type == 'GPL') { 
		$blog_gpl_id = $json->group_id;
	}

	$blog_id = creerNouveauBlog(
		$blog_domain, '/', $blog_name, $user->login, 
		$user->data->user_email, 1,
		$user->ID, $blog_type, $blog_structure_id, $blog_cls_id,
		$blog_grp_id, $blog_gpl_id);

	echo print_r($json, true);
}
// DELETE /blogs/{id}
else if ($_SERVER['REQUEST_METHOD'] == 'DELETE' && count($tpath) == 2 && $tpath[0] == 'blogs')
{
	$blog_id = intval($tpath[1]);
	$data = get_site($blog_id);
	if ($data == null)
		http_response_code(404);
	else {
	    $tables = $wpdb->get_results("SHOW TABLES LIKE 'wp\_${blog_id}\_%'", ARRAY_N);
		// delete the tables
		foreach ($tables as $table) {
			$table_name = $table[0];
			$wpdb->get_results("DROP TABLE `$table_name`", ARRAY_N);
		}
		// delete the blog from the main wordpress tables
		$wpdb->get_results("DELETE FROM wp_blogs WHERE blog_id=$blog_id", ARRAY_N);
		$wpdb->get_results("DELETE FROM wp_blogs_versions WHERE blog_id=$blog_id", ARRAY_N);
		$wpdb->get_results("DELETE FROM wp_registration_log WHERE blog_id=$blog_id", ARRAY_N);
		// delete the blog file directory
		rrmdir(BLOG_FILES . '/' . $blog_id);
	}
}
// default 404
else
{
	http_response_code(404);
}

exit;

// check if basic HTTP authentication is available
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="LaclasseAPI"');
	http_response_code(401);
    exit;
}

// check the allowed user and password
if (($_SERVER['PHP_AUTH_USER'] != API_USER) || ($_SERVER['PHP_AUTH_PW'] != API_PASSWORD)) {
    header('WWW-Authenticate: Basic realm="LaclasseAPI"');
	http_response_code(401);
    exit;
}


switch($_REQUEST['action']) {
	case 'user':
        header('Content-Type: application/json; charset=utf-8');
        $user = get_user_by('login', $_REQUEST['login']);
		if ($user)
			echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		else
		    header('HTTP/1.0 404 Not Found');
		break;
	case 'user_blogs':
		$result = userBlogList($_REQUEST['login']);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		break;
	// get all user of a given blog
	case 'blog_users':
		$blogusers = get_users('blog_id=' . $_REQUEST['blog_id']);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($blogusers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		break;
	case 'blogs':
        header('Content-Type: application/json; charset=utf-8');
		$blogs = wp_get_sites(array("limit" => 100000));
		$result = [];
		foreach ($blogs as $blog) {
			$details = getBlogData($blog['blog_id']);
			$blog['details'] = $details;
			error_log('BLOG NAME: ' . $details['blogname']);
			array_push($result, $blog);
		}
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
		break;
	case 'create':
        header('Content-Type: application/json; charset=utf-8');
		break;
	case 'delete':
        header('Content-Type: application/json; charset=utf-8');
		break;
}

