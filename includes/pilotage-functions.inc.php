<?php
// --------------------------------------------------------------------------------
//
// Fonctions de pilotage des actions sur WorPress par l'ENT.
//
// --------------------------------------------------------------------------------

//
// vérifier que l'utilisateur a un profil donné sur un etab donné.
//
function has_profile($user, $uai="", $wanted_profiles = null) {
    foreach ($user->profiles as $profile)
    {
		if ($profile->structure_id != $uai)
			continue;

		if ($wanted_profiles == null)
			return true;

		foreach ($wanted_profiles as $wanted_profile)
			if ($profile->type == $wanted_profile)
				return true;
    }
    return false;
}


function has_group($groups, $wanted_group) {
    foreach ($groups as $group) {
        if ($group->group_id == $wanted_group) {
            return true;
        }
    }
    return false;
}

function has_group_profile($user, $wanted_group, $profiles) {
    foreach ($user->groups as $user_group) {
        if ($user_group->group_id != $wanted_group)
            continue;

        foreach ($profiles as $profile) {
            if ($profile == $user_group->type)
                return true;
        }
    }
    return false;
}

//
// Return a WordPress role for the given user on the given blog
//
function get_user_blog_default_role($userENT, $blog) {
    // if the user if a super admin allow all blogs
    if($userENT->super_admin) {
        return 'administrator';
    }

    // if a public blog
    if($blog->type == 'ENV') {
        return 'subscriber';
    }

    // depending on the blog type
    if($blog->type == 'ETB') {
        if(has_profile($userENT, $blog->structure_id, ['DIR','ADM']))
            return 'administrator';
        if(has_profile($userENT, $blog->structure_id, ['ENS','ETA','DOC','EVS']))
            return 'author';
        if(has_profile($userENT, $blog->structure_id, ['ELV','TUT']))
            return 'subscriber';
    }
    elseif(($blog->type == 'CLS') || ($blog->type == 'GRP')) {
        if(has_profile($userENT, $blog->structure_id, ['DIR','ADM']))
            return 'administrator';
        if(has_group_profile($userENT, $blog->group_id, ['PRI','ADM','ENS']))
            return 'administrator';
        if(has_group_profile($userENT, $blog->group_id, ['MBR','ELV']))
            return 'contributor';
        if(has_profile($userENT, $blog->structure_id, ['ENS','ETA','DOC','EVS']))
            return 'subscriber';
    }
    elseif($blog->type == 'GPL') {
        if(has_group_profile($userENT, $blog->group_id, ['PRI','ADM','ENS']))
            return 'administrator';
        if(has_group_profile($userENT, $blog->group_id, ['MBR','ELV']))
            return 'contributor';
    }

    foreach($userENT->children as $child) {
        $childRole = get_user_blog_default_role($child->detail, $blog);
        if ($childRole != null)
            return 'subscriber';
    }

    return null;
}

function has_right($userENT, $blog) {
    // if a public blog
    if($blog->type == 'ENV') {
        return true;
    }

    // if the user if a super adminallow all blogs
    if($userENT->super_admin) {
        return true;
    }

    // view all blogs a user can view with its profiles
    foreach($userENT->profiles as $profile) {
        if (!isset($blog->structure_id))
            continue;
        if ($profile->structure_id != $blog->structure_id)
            continue;
        // depending on the blog type
        if($blog->type == 'ETB') {
            // like we have an active profil in the etablissement
            return true;
        }
        elseif($blog->type == 'CLS') {
           if(has_group($userENT->groups, $blog->group_id))
               return true;
            if (isset($blog->structure_id) && has_profile($userENT, $blog->structure_id, ['DIR','ADM']))
               return true;
        }
        elseif($blog->type == 'GRP') {
           if(has_group($userENT->groups, $blog->group_id))
               return true;
            if (isset($blog->structure_id) && has_profile($userENT, $blog->structure_id, ['DIR','ADM']))
               return true;
        }
    }

    if($blog->type == 'GPL') {
       if(has_group($userENT->groups, $blog->group_id))
           return true;
        if (isset($blog->structure_id) && has_profile($userENT, $blog->structure_id, ['DIR','ADM']))
           return true;
    }

    // test for children rights
    foreach ($userENT->children as $child) {
        if (has_right($child->detail, $blog))
            return true;
    }

    return false;
}

function has_admin_right($userENT, $user_id, $blog = null) {
    // if the user if a super admin
    if($userENT->super_admin)
        return true;

    if ($blog != null) {
        // check rights on the structure
        if (isset($blog->structure_id) && has_profile($userENT, $blog->structure_id, ['DIR','ADM']))
            return true;

        // check rights on the group
        if (isset($blog->group_id) && has_group_profile($userENT, $blog->group_id, ['PRI','ADM','ENS']))
            return true;

        // check WP rights on the blog
        if (isset($blog->id) && get_user_blog_role($user_id, $blog->id) == 'administrator')
            return true;
    }
    return false;
}

function ensure_admin_right($userENT, $user_id, $blog = null) {
    if (!has_admin_right($userENT, $user_id, $blog)) {
        http_response_code(403);
        exit;
    }
}

function has_read_right($userENT, $user_id, $blog) {
    return (has_right($userENT, $blog) || (get_user_blog_role($user_id, $blog->id) != null));
}

function ensure_read_right($userENT, $user_id, $blog) {
    if (!has_read_right($userENT, $user_id, $blog)) {
        http_response_code(403);
        exit;
    }
}


// --------------------------------------------------------------------------------
// Return true if a given blog MUST be displayed without choice for a given user
// --------------------------------------------------------------------------------

function is_forced_blog($blog, $userENT) {
	// depending on the blog type
	if($blog->type == 'ETB') {
		if (has_profile($userENT, $blog->structure_id))
			return true;
	}
	elseif($blog->type == 'CLS') {
		if(has_group($userENT->groups, $blog->group_id))
			return true;
	}
	elseif($blog->type == 'GRP') {
		if(has_group($userENT->groups, $blog->group_id))
			return true;
	}
	elseif($blog->type == 'GPL') {
		if(has_group($userENT->groups, $blog->group_id))
			return true;
	}

    // test for children rights
    foreach ($userENT->children as $child) {
        if (is_forced_blog($blog, $child->detail))
			return true;
    }
	return false;
}

// Return the current role of a user on a given blog or null if no role
function get_user_blog_role($user_id, $blog_id) {
    switch_to_blog($blog_id);

	$role = null;
    $user = get_userdata($user_id);

    if ($user && $user->roles && count($user->roles) > 0) {
		$role = $user->roles[0];
    }
    restore_current_blog();
    return $role;
}

