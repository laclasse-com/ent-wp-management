<?php

////////////////////////////////////////////////////////////////////////////////
// HELPERS FUNCTIONS
////////////////////////////////////////////////////////////////////////////////

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
	if (isset($result->etablissement_ENT)) {
		$result->structure_id = $result->etablissement_ENT;
	}
	unset($result->etablissement_ENT);
	if ($result->type == 'CLS' && isset($result->classe_ENT))
		$result->group_id = $result->classe_ENT;
	if ($result->type == 'GRP' && isset($result->groupe_ENT))
		$result->group_id = $result->groupe_ENT;
	if ($result->type == 'GPL' && isset($result->groupelibre_ENT))
		$result->group_id = $result->groupelibre_ENT;
	unset($result->groupe_ENT);
	unset($result->classe_ENT);
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

function filter_blog($blog, $params) {
	$allowed_fields = array ('admin_email', 'domain', 'registered',
		'last_updated', 'public', 'archived', 'deleted', 'id', 'name',
		'description', 'type', 'url', 'structure_id', 'group_id');

	foreach ($params as $key => $value) {
		if (in_array($key, $allowed_fields)) {
			if (!isset($blog->$key) || ($value != $blog->$key))
				return false;
		}
	}
	return true;
}

function user_data($data) {
	$user = new stdClass();
	$user->id = $data->ID;
	if (isset($data->roles)) {
		$user->roles = $data->roles;
	}
	if (isset($data->data)) {
		$d = $data->data;
		if (isset($d->user_login))
			$user->login = $d->user_login;
		if (isset($d->user_email))
			$user->email = $d->user_email;
		if (isset($d->user_nicename))
			$user->nicename = $d->user_nicename;
		if (isset($d->display_name))
			$user->display_name = $d->display_name;
		if (isset($d->user_registered))
			$user->ctime = $d->user_registered;
		if (isset($d->deleted))
			$user->deleted = $d->deleted == 1;
	}
	return $user;
}

////////////////////////////////////////////////////////////////////////////////
// MAIN PROGRAM
////////////////////////////////////////////////////////////////////////////////

function laclasse_api_handle_request($method, $path) {
	global $_COOKIE;
	global $_REQUEST;
	global $wpdb;

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

	$user_email;
	foreach($user->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}
	if (!isset($user_email))
		$user_email = $user->id . '@noemail.lan';

	$tpath = explode('/', $path);

	// GET /setup
	if ($method == 'GET' && count($tpath) == 1 && $tpath[0] == 'setup')
	{
		header('Content-Type: application/json; charset=utf-8');
		$result = array("domain" => BLOG_DOMAINE);
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	}
	// GET /blogs
	else if ($method == 'GET' && count($tpath) == 1 && $tpath[0] == 'blogs')
	{
		header('Content-Type: application/json; charset=utf-8');
		$blogs = get_sites(array("number" => 100000));
		$result = [];
		foreach ($blogs as $blog) {
			if ($blog->blog_id == 1)
				continue;
			$blog_data = blog_data($blog);
			if (filter_blog($blog_data, $_REQUEST))
				array_push($result, $blog_data);
		}
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	}
	// GET /blogs/{id}
	else if ($method == 'GET' && count($tpath) == 2 && $tpath[0] == 'blogs')
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
	else if ($method == 'POST' && count($tpath) == 1 && $tpath[0] == 'blogs')
	{
		$json = json_decode(file_get_contents('php://input'));

		$blog_name = $json->name;
		$blog_domain = $json->domain;
		$blog_type = $json->type;
		$blog_structure_id = '';
		if (isset($json->structure_id)) {
			$blog_structure_id = $json->structure_id;
		}
		$blog_cls_id = '';
		$blog_grp_id = '';
		$blog_gpl_id = '';
		if ($blog_type == 'CLS') {
			$blog_cls_id = $json->group_id;
		}
		else if ($blog_type == 'GRP') { 
			$blog_grp_id = $json->group_id;
		}
		else if ($blog_type == 'GPL') { 
			$blog_gpl_id = $json->group_id;
		}

		// get or create a wordpress user for the current user
		$wp_user_id = createUserWP($user->login, $user_email);

		// create the blog and add the WP user as administrator
		$blog_id = creerNouveauBlog(
			$json->domain, '/', $json->name, $user->login, $user_email, 1,
			$wp_user_id, $json->type, $blog_structure_id, $blog_cls_id,
			$blog_grp_id, $blog_gpl_id, $json->description);

		error_log("DANIEL: creerNouveauBlog $blog_id");
		$data = get_site($blog_id);
		error_log("DANIEL: creerNouveauBlog data: " . (($data == null) ? "TRUE": "FALSE"));
		if ($data == null)
			http_response_code(404);
		else {
			$result = blog_data($data);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// PUT /blogs/{id}
	else if ($method == 'PUT' && count($tpath) == 2 && $tpath[0] == 'blogs')
	{
		$blog_id = intval($tpath[1]);
		$json = json_decode(file_get_contents('php://input'));

		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			if (isset($json->name))
				update_blog_option($blog_id, 'blogname', $json->name);
			if (isset($json->description))
				update_blog_option($blog_id, 'blogdescription', $json->description);
			if (isset($json->type))
				update_blog_option($blog_id, 'type_de_blog', $json->type);
			if (isset($json->archived))
				update_blog_status($blog_id, 'archived', $json->archived ? '1' : '0');
			if (isset($json->deleted))
				update_blog_status($blog_id, 'deleted', $json->deleted ? '1' : '0');

			$data = blog_data($data);

			if (isset($json->structure_id))
				update_blog_option($blog_id, 'etablissement_ENT', $json->structure_id);
			if (isset($json->group_id)) {
				if ($data->type == 'CLS')
					update_blog_option($blog_id, 'classe_ENT', $json->group_id);
				else if ($data->type == 'GRP')
					update_blog_option($blog_id, 'groupe_ENT', $json->group_id);
				else if ($data->type == 'GPL')
					update_blog_option($blog_id, 'groupelibre_ENT', $json->group_id);
			}

			$data = get_site($blog_id);
			$result = blog_data($data);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// DELETE /blogs/{id}
	else if ($method == 'DELETE' && count($tpath) == 2 && $tpath[0] == 'blogs')
	{
		$blog_id = intval($tpath[1]);
		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			// get the blog upload dir
			switch_to_blog($blog_id);
			$upload_base = wp_get_upload_dir()['basedir'];
			restore_current_blog();
			// remove the blog (DB tables + files)
			wpmu_delete_blog ($blog_id, true);
			// remove the blog upload dir
			if (is_dir($upload_base))
				rmdir($upload_base);
		}
	}
	// GET /users
	else if ($method == 'GET' && count($tpath) == 1 && $tpath[0] == 'users')
	{
		header('Content-Type: application/json; charset=utf-8');
		$users = get_users();
		$result = [];
		foreach ($users as $user) {
			$data = user_data($user);
			array_push($result, $data);
		}
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	}
	// GET /users/{id}
	else if ($method == 'GET' && count($tpath) == 2 && $tpath[0] == 'users')
	{
		if ($tpath[1] == 'current') {
			$user = get_user_by('login', $user->login);
		}
		else {
			$user_id = intval($tpath[1]);
			$user = get_user_by('id', $user_id);
		}
		if ($user == false)
			http_response_code(404);
		else {
			$result = user_data($user);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// GET /users/{id}/blogs
	else if ($method == 'GET' && count($tpath) == 3 && $tpath[0] == 'users' && $tpath[2] == 'blogs')
	{
		$userENT;
		if ($tpath[1] == 'current') {
			$userENT = $user;
			$user = get_user_by('login', $user->login);
		}
		else {
			$user_id = intval($tpath[1]);
			$user = get_user_by('id', $user_id);
			if ($user != false) {
				$userENT = get_http(ANNUAIRE_URL . "api/users?login=" . urlencode($user->login));
			}
		}
		if ($user == false)
			http_response_code(404);
		else {
			$result = userViewBlogList($userENT->id);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// DELETE /users/{id}
	else if ($method == 'DELETE' && count($tpath) == 2 && $tpath[0] == 'users')
	{
		$user_id = intval($tpath[1]);
		$user = get_user_by('id', $user_id);
		if ($user == false)
			http_response_code(404);
		else {
			// remove the user and all its work
			// TODO: reasign its work to a special user "deleted"
			wpmu_delete_user ($user_id);
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

/*
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
	}*/
}
 
function wp_rest_laclasse_api_handle_request($request) {
	laclasse_api_handle_request($request->get_method(), $request->get_url_params()['path']);
}


