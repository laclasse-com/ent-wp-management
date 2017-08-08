<?php

// load setup
require_once("ENTconfig.inc.php");

// check if basic HTTP authentication is available
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="LaclasseAPI"');
	http_response_code(401);
    exit;
}

// check the allowed user and password
if (($_SERVER['PHP_AUTH_USER'] != API_USER) || ($_SERVER['PHP_AUTH_PW'] != API_PASSWORD)) {
    header('WWW-Authenticate: Basic realm="LaclasseAPI"');
    //header('HTTP/1.0 401 Unauthorized');
	http_response_code(401);
    exit;
}

// load wordpress functions
require_once("../../../wp-load.php");

require_once("includes/pilotage-functions.inc.php");

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

