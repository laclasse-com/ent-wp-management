<?php

////////////////////////////////////////////////////////////////////////////////
// HELPERS FUNCTIONS
////////////////////////////////////////////////////////////////////////////////

// Convert WP_Site to our own blog object
function blog_data($blogWp) {
	$result = new stdClass();
	foreach($blogWp as $k => $v) { $result->$k = $v; }

	$opts = Array('admin_email', 'siteurl', 'name', 'blogname',
		'blogdescription', 'blogtype', 'etablissement_ENT', 'display_name',
		'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT');

	foreach ($opts as $opt) {
		$val = get_blog_option($blogWp->blog_id, $opt);
		$val = html_entity_decode($val, ENT_QUOTES);
		if ($val != false) {
			$result->$opt = $val;
		}
	}

	$result->id = intval($result->blog_id);
	unset($result->blog_id);
	$result->name = $result->blogname;
	unset($result->blogname);
	$result->description = $result->blogdescription;
	unset($result->blogdescription);
	if (isset($result->type_de_blog))
		$result->type = $result->type_de_blog;
	else
		$result->type = 'ENV';
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

// Return the list of all blogs
function get_blogs() {
	$blogs = get_sites(array("number" => 100000));
	$result = [];
	foreach ($blogs as $blog) {
		if ($blog->blog_id == 1)
			continue;
		$blog_data = blog_data($blog);
		array_push($result, $blog_data);
	}
	return $result;
}

function get_blog($blog_id) {
	$data = get_site($blog_id);
	if ($data == null)
		return null;
	return blog_data($data);
}

function filter_fields($data, $params, $allowed_fields) {
	foreach ($params as $key => $value) {
		if (in_array($key, $allowed_fields)) {
			if (!isset($data->$key))
				return false;
			if (gettype($value) == 'array') {
				if (!in_array($data->$key, $value))
					return false;
			}
			else if ($value != $data->$key)
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

// Convert WP_User to our own user object
function user_data($userWp) {
	$user = new stdClass();
	$user->id = $userWp->ID;
	if (isset($userWp->data)) {
		$d = $userWp->data;
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

function get_user($userId) {
	$userWp = get_user_by('id', $userId);
	if ($userWp == false)
		return null;
	return user_data($userWp);
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


// Update the WP user roles on blogs with the given ENT user
// and the type of each blog. Means auto register user to their blogs
function update_roles_wp_user_from_ent_user($userWp, $userENT) {
	$blogs = get_blogs();

	$role_order['subscriber'] = 1;
	$role_order['contributor'] = 2;
	$role_order['author'] = 3;
	$role_order['editor'] = 4;
	$role_order['administrator'] = 5;

	$user_blogs = get_blogs_of_user($userWp->ID);

	foreach ($blogs as $blog) {
		if (is_forced_blog($blog, $userENT)) {
			// add rights on blog if needed
			$default_role = get_user_blog_default_role($userENT, $blog);
			$current_role = get_user_blog_role($userWp->ID, $blog->id);
			// if the default role is better than the current upgrade/create it
			if ($default_role != null && ($current_role == null || $role_order[$default_role] > $role_order[$current_role]))
				add_user_to_blog($blog->id, $userWp->ID, $default_role);
		}
	}
}

// Update the WP user data with the given ENT ENT user data
function update_wp_user_from_ent_user($userWp, $userENT) {

	if ($userENT->super_admin && !is_super_admin($userWp->ID))
		grant_super_admin($userWp->ID);
	else if (!$userENT->super_admin && is_super_admin($userWp->ID))
		revoke_super_admin($userWp->ID);

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
	update_roles_wp_user_from_ent_user($userWp, $userENT);
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

// Get the ENT user definition or null if the user
// doesn't exists in the ENT
function get_ent_user($ent_user_id) {
	if (empty($ent_user_id))
		return null;

	// get the user of the current session
	$userENT = get_http(ANNUAIRE_URL . "api/users/" . $ent_user_id . "?expand=true", $error, $status);
	if ($status != 200)
		return null;

	$userENT = json_decode($userENT);
	// get details for each child
    foreach ($userENT->children as $child)
        $child->detail = $childDetail = json_decode(get_http(ANNUAIRE_URL . "api/users/$child->child_id?expand=true"));
    return $userENT;
}

function get_ent_user_from_user($user) {
	if (isset($user->ent_id))
		return get_ent_user($user->ent_id);
	return null;
}

function get_ent_user_from_user_id($userId) {
	$user = get_user($userId);
	if ($user == null)
		return null;
	return get_ent_user_from_user($user);
}


////////////////////////////////////////////////////////////////////////////////
// MAIN PROGRAM
////////////////////////////////////////////////////////////////////////////////

function laclasse_api_handle_request($method, $path) {
	global $_COOKIE;
	global $_REQUEST;
	global $wpdb;

	$result = null;

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

	$userENT = get_ent_user($session->user);
	if ($userENT == null) {
		http_response_code(401);
		exit;
	}

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
		$result = array("domain" => BLOGS_DOMAIN);
	}
	// GET /blogs[?seen_by={ent_id}]
	else if ($method == 'GET' && count($tpath) == 1 && $tpath[0] == 'blogs')
	{
		$blogs = get_blogs();

		$seenBy = null;
		if (isset($_REQUEST['seen_by'])) {
			if ($_REQUEST['seen_by'] == $userENT->id)
				$seenBy = $userENT;
			else
				$seenBy = get_ent_user($_REQUEST['seen_by']);
		}

		$result = [];
		foreach ($blogs as $blog) {
			// if seen_by is set, filter by what the given ENT user can see
			if (($seenBy != null) && !has_right($seenBy, $blog))
				continue;

			if (filter_blog($blog, $_REQUEST))
				array_push($result, $blog);
		}
	}
	// GET /blogs/{id}
	else if ($method == 'GET' && count($tpath) == 2 && $tpath[0] == 'blogs')
	{
		$blog_id = intval($tpath[1]);
		$data = get_blog($blog_id);
		if ($data == null)
			http_response_code(404);
		else
			$result = $data;
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

		$data = get_blog($blog_id);
		if ($data == null)
			http_response_code(404);
		else
			$result = $data;
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

			$data = get_blog($blog_id);
			$result = $data;
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
		}
	}
	// POST /blogs/{id}/users
	else if ($method == 'POST' && count($tpath) == 3 && $tpath[0] == 'blogs' && $tpath[2] == 'users')
	{
		$json = json_decode(file_get_contents('php://input'));

		$blog_id = intval($tpath[1]);
		$blog = get_blog($blog_id);
		if ($blog == null)
			http_response_code(404);
		else {
			$user_id = $json->user_id;
			if (isset($json->role)) {
				$user_role = $json->role;
			}
			// find the default role
			else {
				$userENT = get_ent_user_from_user_id($user_id);
				if ($userENT != null)
					$user_role = get_user_blog_default_role($userENT, $blog);
			}
			if (isset($user_role)) {
				add_user_to_blog($blog_id, $user_id, $user_role);
				$result = new stdClass();
				$result->id = $user_id;
				$result->user_id = $user_id;
				$result->blog_id = $blog_id;
				$result->role = $user_role;
			}
			else
				http_response_code(404);
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
		$user = get_user($user_id);
		if ($user == null)
			http_response_code(404);
		else {
			$userENT = get_ent_user_from_user($user);
			$user_blogs = get_blogs_of_user($user_id);
			$result = [];
			foreach ($user_blogs as $user_blog) {
				$blog = get_blog($user_blog->userblog_id);
				if ($blog == null)
					continue;

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
				$data->forced = ($userENT != null && is_forced_blog($blog, $userENT));

				array_push($result, $data);
			}
		}
	}
	// POST /users/{id}/blogs
	else if ($method == 'POST' && count($tpath) == 3 && $tpath[0] == 'users' && $tpath[2] == 'blogs')
	{
		$json = json_decode(file_get_contents('php://input'));

		$user_id = intval($tpath[1]);
		$blog_id = $json->blog_id;
		$blog = get_blog($blog_id);
		if ($blog == null)
			http_response_code(404);
		else {
			if (isset($json->role)) {
				$user_role = $json->role;
			}
			// find the default role
			else {
				$userENT = get_ent_user_from_user_id($user_id);
				if ($userENT != null)
					$user_role = get_user_blog_default_role($userENT, $blog);
			}
			if (isset($user_role)) {
				add_user_to_blog($blog_id, $user_id, $user_role);
				$result = new stdClass();
				$result->id = $blog_id;
				$result->user_id = $user_id;
				$result->blog_id = $blog_id;
				$result->role = $user_role;
			}
			else
				http_response_code(404);
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
		$users = get_users(array('blog_id' => ''));
		$result = [];
		foreach ($users as $user) {
			$data = user_data($user);
			if (filter_user($data, $_REQUEST))
				array_push($result, $data);
		}
	}
	// GET /users/current
	else if ($method == 'GET' && count($tpath) == 2 && $tpath[0] == 'users' && $tpath[1] == 'current')
	{
		http_response_code(302);
		header('Location: ' . $userWp->ID);
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
		}
	}
	// PUT /users/{id}
	else if ($method == 'PUT' && count($tpath) == 2 && $tpath[0] == 'users')
	{
		$json = json_decode(file_get_contents('php://input'));

		$user_id = intval($tpath[1]);
		$userWp = get_user_by('id', $user_id);
		if ($userWp == false)
			http_response_code(404);
		else {
			if (isset($json->ent_id))
				update_user_meta($userWp->ID, 'uid_ENT', $json->id);
		
			$user_data = array('ID' => $userWp->ID);

			if (isset($json->login))
				$user_data['login'] = $json->login;
			if (isset($json->display_name))
				$user_data['display_name'] = $json->display_name;
			if (isset($json->email))
				$user_data['user_email'] = $json->email;
			wp_update_user($user_data);
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

	return $result;
}
 
function wp_rest_laclasse_api_handle_request($request) {
	// dont return the object because WP dont encode numeric as numeric
	echo json_encode(laclasse_api_handle_request($request->get_method(), $request->get_url_params()['path']),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
	exit;
}


