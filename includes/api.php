<?php

////////////////////////////////////////////////////////////////////////////////
// HELPERS FUNCTIONS
////////////////////////////////////////////////////////////////////////////////

// get multiples options of a blog. get_blog_option is too slow
function get_blog_options($blogId, $options) {
	global $wpdb;

	$result = new stdClass();
	$options_string = '';
	foreach($options as $option) {
		if ($options_string != '')
			$options_string .= ',';
		$options_string .= "'" . $option . "'";
	}
	$options_string = '(' . $options_string . ')';

	switch_to_blog($blogId);
	$rows = $wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM $wpdb->options WHERE option_name IN $options_string AND 1=%d", 1));
	foreach($rows as $row) {
		$option_name = $row->option_name;
		$result->$option_name = html_entity_decode($row->option_value, ENT_QUOTES);
	}
	restore_current_blog();
	return $result;
}

// Convert WP_Site to our own blog object
function blog_data($blogWp) {
	$opts = Array('admin_email', 'siteurl', 'name', 'blogname',
		'blogdescription', 'blogtype', 'etablissement_ENT', 'display_name',
		'type_de_blog', 'group_id_ENT','student-privacy','default_comment_status', 'blog_public');

	$result = get_blog_options($blogWp->blog_id, $opts);
	foreach($blogWp as $k => $v) { $result->$k = $v; }

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
	if (isset($result->etablissement_ENT) && !empty($result->etablissement_ENT)) {
		$result->structure_id = $result->etablissement_ENT;
	}
	unset($result->etablissement_ENT);
	if ($result->type == 'CLS' && isset($result->classe_ENT))
		$result->group_id = intval($result->classe_ENT);
	if ($result->type == 'GRP' && isset($result->groupe_ENT))
		$result->group_id = intval($result->groupe_ENT);
	if ($result->type == 'GPL' && isset($result->groupelibre_ENT))
		$result->group_id = intval($result->groupelibre_ENT);
	unset($result->groupe_ENT);
	unset($result->classe_ENT);
	unset($result->groupelibre_ENT);

	if (isset($result->group_id_ENT))
		$result->group_id = intval($result->group_id_ENT);
	unset($result->group_id_ENT);

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

	$result->student_privacy = isset( $result->{'student-privacy'} ) && $result->{'student-privacy'};
	unset( $result->{'student-privacy'} );
	$result->comments_enabled = $result->default_comment_status == "open";
	unset($result->default_comment_status);
	$result->discourage_index = $result->blog_public == 1;
	unset($result->blog_public);

	switch_to_blog($result->id);
	$result->quota_max = intval(get_space_allowed() * 1024 * 1024);
	$result->quota_used = intval(get_space_used() * 1024 * 1024);
	if(function_exists('is_plugin_active'))
		$result->force_login = is_plugin_active(WP_FORCE_LOGIN);
	restore_current_blog();

	return $result;
}

// Return the list of all blogs
function get_blogs() {
	$blogs = get_sites(array("number" => 100000,'site__not_in' => [ 1 ]));
	$result = [];
	foreach ($blogs as $blog) {
		$blog_data = blog_data($blog);
		array_push($result, $blog_data);
	}
	return $result;
}

function get_cached_blogs() {
	global $_cached_blogs;
	if (!isset($_cached_blogs))
		$_cached_blogs = get_blogs();
	return $_cached_blogs;
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

function filter_blog_regex($blog, $params) {
	return filter_fields_regex($blog, $params, array('admin_email', 'domain',
		'registered', 'last_updated', 'public', 'archived', 'deleted', 'id',
		'name', 'description', 'type', 'url', 'structure_id', 'group_id'));
}

function filter_fields_regex($data, $params, $allowed_fields) {
	foreach ($params as $key => $value) {
		if (in_array($key, $allowed_fields)) {
			if (!isset($data->$key))
				return false;

			if ( preg_match ( '/' . $value . '/i', $data->$key ) === 1 )
				return true;
		}
	}
	return false;
}

function filter_blog($blog, $params) {
	return filter_fields($blog, $params, array('admin_email', 'domain',
		'registered', 'last_updated', 'public', 'archived', 'deleted', 'id',
		'name', 'description', 'type', 'url', 'structure_id', 'group_id'));
}

function delete_blog($blog_id) {
	$data = get_site($blog_id);
	if ($data == null)
		return false;
	else {
		// get the blog upload dir
		switch_to_blog($blog_id);
		$upload_base = wp_get_upload_dir()['basedir'];
		restore_current_blog();
		// remove the blog (DB tables + files)
		wpmu_delete_blog ($blog_id, true);
		// remove the blog upload dir
		if (is_dir($upload_base)) {
			rmdir($upload_base);
			if (basename($upload_base) == 'files') {
				$dirname = dirname($upload_base);
				if (is_dir($dirname)) {
					rmdir($dirname);
				}
			}
		}
		return true;
	}
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

	$profile_ENT = get_user_meta($user->id, 'profile_ENT', true);
	if (isset($profile_ENT))
		$user->ent_profile = $profile_ENT;

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
// The ent_id is now used as login since it is unique contrary to login
// which are recycled
// Return: the WP user or null if not found
function get_wp_user_from_ent_user($userENT) {
	return  get_user_by('login', $userENT->id);
}

// Create a WP user from the ENT user data
// Return: the WP user
function create_wp_user_from_ent_user($userENT) {
	$user_email;
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}
	$password = substr(md5(microtime()), rand(0,26), 20);
	$user_id = wp_create_user($userENT->id, $password, $user_email);
	// remove the user for blog 1
	remove_user_from_blog($user_id, 1);
	// remove automatic role create on the current blog
	remove_user_from_blog($user_id, get_current_blog_id());

	return get_user_by('id', $user_id);
}


// Update the WP user roles on blogs with the given ENT user
// and the type of each blog. Means auto register user to their blogs
function update_roles_wp_user_from_ent_user($userWp, $userENT) {
	$blogs = get_cached_blogs();

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

function get_user_best_profile($userENT) {
	$profiles_order = array(
		'ADM' => 9,
		'ACA' => 8,
		'DIR' => 7,
		'DOC' => 6,
		'ENS' => 5,
		'ETA' => 4,
		'EVS' => 3,
		'ELV' => 2,
		'TUT' => 1
	);

	$profile = null;
	if (count($userENT->profiles) == 1)
		$profile = $userENT->profiles[0]->type;
	else if (count($userENT->profiles) > 1) {
		foreach($userENT->profiles as $user_profile) {
			if (!isset($profiles_order[$user_profile->type]))
				continue;
			if ($profile == null)
				$profile = $user_profile->type;
			else if($profiles_order[$user_profile->type] > $profiles_order[$profile])
				$profile = $user_profile->type;
		}
	}
	return $profile;
}

// Update the WP user data with the given ENT ENT user data
function update_wp_user_from_ent_user($userWp, $userENT, $sync_role = true) {

	if ($userENT->super_admin && !is_super_admin($userWp->ID))
		grant_super_admin($userWp->ID);
	else if (!$userENT->super_admin && is_super_admin($userWp->ID))
		revoke_super_admin($userWp->ID);

	// update user data
	update_user_meta($userWp->ID, 'uid_ENT', $userENT->id);

	$profile = get_user_best_profile($userENT);
	if ($profile != null)
		update_user_meta($userWp->ID, 'profile_ENT', $profile);

	$user_email;
	foreach($userENT->emails as $email) {
		if (!isset($user_email) || $email->primary)
			$user_email = $email->address;
	}

	$user_data = array(
		'ID' => $userWp->ID,
		'first_name' => $userENT->firstname,
		'last_name' => $userENT->lastname,
		'display_name' => $userENT->lastname.' '.$userENT->firstname,
	);

	if ( isset($user_email) )
		$user_data['user_email'] = $user_email;

	wp_update_user($user_data);
	if ($sync_role)
		update_roles_wp_user_from_ent_user($userWp, $userENT);
}

// Create a WP user from the ENT user data if needed
// and sync its data with the ENT user data
// Return: the WP user
function sync_ent_user_to_wp_user($userENT, $sync_role = true) {
	$userWp = get_wp_user_from_ent_user($userENT);

	if ($userWp == null)
		$userWp = create_wp_user_from_ent_user($userENT);

	update_wp_user_from_ent_user($userWp, $userENT, $sync_role);
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

function get_special_delete_user_id() {
	$user_id = '';
	$userWp = get_user_by('login', 'wp_deleted_user');
	if ($userWp == false) {
		$password= substr(md5(microtime()), rand(0,26), 20);
		$user_id = wp_create_user('wp_deleted_user', $password);
	}
	else {
		$user_id = $userWp->ID;
	}
	return $user_id;
}

function delete_user($user_id) {
	$user = get_user_by('id', $user_id);
	if ($user == false)
		return false;
	else {
		// get the special deleted user to reasign the posts
		$delete_user_id = get_special_delete_user_id();
		$user_blogs = get_blogs_of_user($user_id);
		foreach ($user_blogs as $user_blog) {
			// ensure the deleted user has a role on the blog
			add_user_to_blog($user_blog->userblog_id, $delete_user_id, 'contributor');
			// delete the user from the blog and reasign its posts
			switch_to_blog($user_blog->userblog_id);
			wp_delete_user($user_id, $delete_user_id);
			restore_current_blog();
		}
		// if the user is a super admin, revoke super admin right because
		// wpmu_delete_user prevent delete of super admin
		if (is_super_admin($user_id))
			revoke_super_admin($user_id);
		// remove the user and all its work
		wpmu_delete_user($user_id);
		return true;
	}
}