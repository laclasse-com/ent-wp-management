<?php
// --------------------------------------------------------------------------------
//
// Fonctions de pilotage des actions sur WorPress par l'ENT.
//
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------------
// fonction de controle de l'existence d'un blog. Service web appelé depuis l'ENT
// --------------------------------------------------------------------------------
function blogExists($pblogname) {
	if (domain_exists($pblogname, '/', 1)) {
        return 1;
    }
	return 0;
}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de la plateforme, 
// avec les données relatives à l'ENT
function flatten($a, $name, $val) {
    $res = array();
    foreach($a as $k => $v) {
        $res[$v[$name]] = $v[$val];
    }
    return $res;
}

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
            return 'editor';
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
        if ($profile->structure_id != $blog->structure_id)
            continue;
        // depending on the blog type
        if($blog->type == 'ETB') {
            // like we have an active profil in the etablissement
            return true;
        }
        elseif($blog->type == 'CLS') {
           if(has_group($userENT->groups, $blog->group_id)) {
               return true;
           }
        }
        elseif($blog->type == 'GRP') {
           if(has_group($userENT->groups, $blog->group_id)) {
               return true;
           }
        }
    }

    if($blog->type == 'GPL') {
       if(has_group($userENT->groups, $blog->group_id)) {
           return true;
       }
    }

    // test for children rights
    foreach ($userENT->children as $child) {
        if (has_right($child->detail, $blog))
            return true;
    }

    return false;
}

function order_type($type) {
    if($type == 'ETB')
       return 4;
    else if($type == 'CLS')
        return 3;
    elseif($type == 'GRP')
        return 2;
    elseif($type == 'GPL')
        return 1;
    else
       return 0;
}

function getBlogData($blogId) {
    $opts = Array('admin_email', 'siteurl', 'name', 'blogname',
        'blogdescription', 'blogtype', 'etablissement_ENT', 'display_name',
        'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT');
    $blog = new stdClass();

	foreach ($opts as $opt) {
		$val = get_blog_option($blogId, $opt);
		$val = html_entity_decode($val, ENT_QUOTES);
		if ($val != false) {
			$blog->$opt = $val;
		}
	}
    $blog->blog_id = $blogId;
    return $blog;
}

// --------------------------------------------------------------------------------
// Liste des blogs visibles par un utilisateur selon son profil
//  TECH :  il voit tout dans l'établissement du profil actif
//  ADM_ETB/DIRECTION/DOC/PROF : Tous ceux de son établissement ETB + CLS + GRP + Transverses
//  ELEVE/PARENT : Sa Classe (celle de ses enfants), ses groupes et transverses
// --------------------------------------------------------------------------------
function blogList($uid_ent) {
    // Interrogation de l'annuaire de l'ENT
    $userENT = get_ent_user($uid_ent);
    if ($userENT == null)
        return [];

    $all_blogs = get_blogs();
    $blogs = [];
    foreach($all_blogs as $blog) {
		if (has_right($userENT, $blog) && !$blog->archived && !$blog->deleted)
            array_push($blogs, $blog);
    }

    // sort (ETB, CLS, GRP, GPL and others)
    usort($blogs, function($a, $b) {
       $ao = order_type($a->type);
       $bo = order_type($b->type);
       if($ao < $bo)
           return 1;
       if($ao > $bo)
           return -1;
       else
           return strcmp ($a->name, $b->name);
    });

    return $blogs;
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
			array_push($list, $blog);
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

// --------------------------------------------------------------------------------
// Return all blogs a user is registered plus the blogs a user is forced to see
// --------------------------------------------------------------------------------

function userViewBlogList($uid_ent) {

    // Interrogation de l'annuaire de l'ENT
    $userENT = get_ent_user($uid_ent);
    if ($userENT == null)
        return [];

	// récupération des information de l'utilisateur WordPress
	$userRec = get_user_by('login',$userENT->login);
	$userId = $userRec->ID;

	$blogs = blogList($uid_ent);
	$list = [];

	$role_order['subscriber'] = 1;
	$role_order['contributor'] = 2;
	$role_order['editor'] = 3;
	$role_order['administrator'] = 4;

    $user_blogs = get_blogs_of_user($userId);

	foreach ($blogs as $blog) {
		if (is_forced_blog($blog, $userENT)) {
			$blog->forced = true;
			array_push($list, $blog);
			// Add rights on blog if needed
			if ($userId) {
		        $default_role = get_user_blog_default_role($userENT, $blog);
				$current_role = get_user_blog_role($userId, $blog->id);
				// if the default role is better than the current upgrade/create it
				if ($default_role != null) {
					if ($current_role == null || $role_order[$default_role] > $role_order[$current_role]) {
						add_user_to_blog($blog->id, $userId, $default_role);
					}
				}
			}
        }
        else {
            foreach ($user_blogs as $mine) {
                if ($mine->userblog_id == $blog->id) {
                    array_push($list, $blog);
                    break;
                }
            }
        }
	}
	return $list;
}
