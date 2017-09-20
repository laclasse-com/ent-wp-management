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

function filter_fields($data, $params, $allowed_fields) {
	foreach ($params as $key => $value) {
		if (in_array($key, $allowed_fields)) {
			if (!isset($data->$key) || ($value != $data->$key))
				return false;
		}
	}
	return true;
}

function filter_blog($blog, $params) {
	return filter_fields($blog, $params, array('admin_email', 'domain',
		'registered', 'last_updated', 'public', 'archived', 'deleted', 'id',
		'name', 'description', 'type', 'url', 'structure_id', 'group_id'));
}

function user_data($data) {
	$user = new stdClass();
	$user->id = $data->ID;
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
	$uid_ENT = get_user_meta($user->id, 'uid_ENT', true);
	if ($uid_ENT)
		$user->ent_id = $uid_ENT;

	return $user;
}

function filter_user($user, $params) {
	return filter_fields($user, $params, array('id', 'login', 'email',
		'deleted', 'ent_id'));
}

// Get the WP user corresponding to the given ENT user data
// Return: the WP user or null if not found
function get_wp_user_from_ent_user($userENT) {
	// search if a user exists using its ENT id
	$users_search = get_users(array('meta_key' => 'uid_ENT', 'meta_value' => $userENT->id));
	if (count($users_search) > 0)
		return $users_search[0];

	// search the user by its login
	$userWp = get_user_by('login', $userENT->login);
	if ($userWp != false)
		return $userWp;
	
	// search the user by its email
	$user_email = null;
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}
	if ($user_email != null) {
		$userWp = get_user_by('email', $user_email);
		if ($userWp != false)
			return $userWp;
	}
	// not found
	return null;
}

// Create a WP user from the ENT user data
// Return: the WP user
function create_wp_user_from_ent_user($userENT) {
	$user_email;
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}
	$password= substr(md5(microtime()), rand(0,26), 20);
	$user_id = wp_create_user($userENT->login, $password, $user_email);
	return get_user_by('id', $user_email);
}

// Update the WP user data with the given ENT ENT user data
function update_wp_user_from_ent_user($userWp, $userENT) {
	// update user data
	update_user_meta($userWp->ID, 'uid_ENT', $userENT->id);

	$user_email = $userENT->id . '@noemail.lan';
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}

	wp_update_user(array(
		'ID' => $userWp->ID,
		'first_name' => $userENT->firstname, 
		'last_name' => $userENT->lastname,
		'display_name' => $userENT->lastname.' '.$userENT->firstname,
		'user_email' => $user_email
	));
}

// Create a WP user from the ENT user data if needed
// and sync its data with the ENT user data
// Return: the WP user
function sync_ent_user_to_wp_user($userENT) {
	$userWp = get_wp_user_from_ent_user($userENT);
	if ($userWp == null)
		$userWp = create_wp_user_from_ent_user($userENT);
	update_wp_user_from_ent_user($userWp, $userENT);
	return $userWp;
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
	$userENT = get_http(ANNUAIRE_URL . "api/users/" . $session->user, $error, $status);

	if ($status != 200) {
		http_response_code(401);
		exit;
	}

	$userENT = json_decode($userENT);
	// get/create and update the corresponding WP user
	$userWp = sync_ent_user_to_wp_user($userENT);

	$user_email;
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}
	if (!isset($user_email))
		$user_email = $userENT->id . '@noemail.lan';

	$tpath = explode('/', $path);

	// GET /setup
	if ($method == 'GET' && count($tpath) == 1 && $tpath[0] == 'setup')
	{
		header('Content-Type: application/json; charset=utf-8');
		$result = array("domain" => BLOGS_DOMAIN);
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

		// create the blog and add the WP user as administrator
		$blog_id = creerNouveauBlog(
			$json->domain, '/', $json->name, $userENT->login, $user_email, 1,
			$userWp->ID, $json->type, $blog_structure_id, $blog_cls_id,
			$blog_grp_id, $blog_gpl_id, $json->description);

		$data = get_site($blog_id);
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

	// GET /blogs/{id}/users
	else if ($method == 'GET' && count($tpath) == 3 && $tpath[0] == 'blogs' && $tpath[2] == 'users')
	{
		$blog_id = intval($tpath[1]);
		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			switch_to_blog($blog_id);
			$blog_users = get_users();
			$result = [];
			foreach ($blog_users as $blog_user) {
				error_log(print_r($blog_user, true));
				$data = new stdClass();
				$data->id = $blog_user->ID;
				$data->user_id = $blog_user->ID;
				$data->blog_id = $blog_id;
				if (isset($blog_user->roles) && count($blog_user->roles) > 0)
					$data->role = $blog_user->roles[0];
				array_push($result, $data);
			}
			restore_current_blog();
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// POST /blogs/{id}/users
	else if ($method == 'POST' && count($tpath) == 3 && $tpath[0] == 'blogs' && $tpath[2] == 'users')
	{
		$json = json_decode(file_get_contents('php://input'));

		$blog_id = intval($tpath[1]);
		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			//$blogData = getBlogData($blog_id);
			$user_id = $json->user_id;
			$user_role = $json->role;

			// Déterminer le role WordPress de l'utilisateur en fonction de son role ENT.
			//$user_role = getUserWpRole($user, $blogData);

			add_user_to_blog($blog_id, $user_id, $user_role);
			header('Content-Type: application/json; charset=utf-8');
			$result = new stdClass();
			$result->id = $user_id;
			$result->user_id = $user_id;
			$result->blog_id = $blog_id;
			$result->role = $user_role;
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// DELETE /blogs/{id}/users/{user_id}
	else if ($method == 'DELETE' && count($tpath) == 4 && $tpath[0] == 'blogs' && $tpath[2] == 'users')
	{
		$blog_id = intval($tpath[1]);
		$user_id = intval($tpath[3]);

		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			remove_user_from_blog($user_id, $blog_id);
			http_response_code(200);
		}
	}

	// GET /users/{id}/blogs
	else if ($method == 'GET' && count($tpath) == 3 && $tpath[0] == 'users' && $tpath[2] == 'blogs')
	{
		$user_id = intval($tpath[1]);
		$user = get_user_by('id', $user_id);
		if ($user == false)
			http_response_code(404);
		else {
			$user_blogs = get_blogs_of_user($user_id);
			$result = [];
			foreach ($user_blogs as $user_blog) {
				$data = new stdClass();
				$data->id = $user_blog->userblog_id;
				$data->blog_id = $user_blog->userblog_id;
				$data->user_id = $user_id;
				// try to find the user role
				$users_search = get_users(
					array(
						'blog_id' => $user_blog->userblog_id,
						'search'  => $user_id
					)
				);
				if (count($users_search) > 0 && count($users_search[0]->roles) > 0)
					$data->role = $users_search[0]->roles[0];
				array_push($result, $data);
			}
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// POST /users/{id}/blogs
	else if ($method == 'POST' && count($tpath) == 3 && $tpath[0] == 'users' && $tpath[2] == 'blogs')
	{
		$json = json_decode(file_get_contents('php://input'));

		$user_id = intval($tpath[1]);
		$blog_id = $json->blog_id;
		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			$blogData = getBlogData($blog_id);
			$user_role = $json->role;
			add_user_to_blog($blog_id, $user_id, $user_role);
			header('Content-Type: application/json; charset=utf-8');
			$result = new stdClass();
			$result->id = $blog_id;
			$result->user_id = $user_id;
			$result->blog_id = $blog_id;
			$result->role = $user_role;
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		}
	}
	// DELETE /users/{user_id}/blogs/{blog_id}
	else if ($method == 'DELETE' && count($tpath) == 4 && $tpath[0] == 'users' && $tpath[2] == 'blogs')
	{
		$user_id = intval($tpath[1]);
		$blog_id = intval($tpath[3]);
		
		$data = get_site($blog_id);
		if ($data == null)
			http_response_code(404);
		else {
			remove_user_from_blog($user_id, $blog_id);
			http_response_code(200);
		}
	}

	// GET /users
	else if ($method == 'GET' && count($tpath) == 1 && $tpath[0] == 'users')
	{
		header('Content-Type: application/json; charset=utf-8');
		$users = get_users(array('blog_id' => ''));
		$result = [];
		foreach ($users as $user) {
			$data = user_data($user);
			if (filter_user($data, $_REQUEST))
				array_push($result, $data);
		}
		echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	}
	// GET /users/{id}
	else if ($method == 'GET' && count($tpath) == 2 && $tpath[0] == 'users')
	{
		if ($tpath[1] == 'current') {
			$user = get_user_by('login', $userENT->login);
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
			wpmu_delete_user($user_id);
		}
	}
	// default 404
	else
	{
		http_response_code(404);
	}

	exit;

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


