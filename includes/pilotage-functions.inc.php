<?php
// --------------------------------------------------------------------------------
//
// Fonctions de pilotage des actions sur WorPress par l'ENT.
//
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------------
//  Fonction qui renvoie le dernier blog_id créé par l'utilisateur en fct de son domaine
// --------------------------------------------------------------------------------
function getBlogIdByDomain( $domain ) {
	global $wpdb;
	$rowBlog = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->blogs WHERE domain = %s AND spam = '0' AND deleted = '0' and archived = '0'", $domain)  );
	return $rowBlog->blog_id;
}

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
// fonction de controle de l'existence d'un utilisateur. Service web appelé depuis l'ENT
// --------------------------------------------------------------------------------
function userExists($pusername) {
	global $wpdb;
	$usrId = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_login = %s", strtolower($pusername))  );
	if (isset($usrId) && $usrId > 0) 
		echo "OK";
	else 
		echo "NOK";

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
function getUserWpRole($userENT, $blog) {
    // if the user if a super admin allow all blogs
    if($userENT->super_admin) {
        return 'administrator';
    }

    // if a public blog
    if($blog->type_de_blog == 'ENV') {
        return 'subscriber';
    }

    // depending on the blog type
    if($blog->type_de_blog == 'ETB') {
        if(has_profile($userENT, $blog->etablissement_ENT, ['DIR','ADM']))
            return 'administrator';
        if(has_profile($userENT, $blog->etablissement_ENT, ['ENS','ETA','DOC','EVS']))
            return 'editor';
        if(has_profile($userENT, $blog->etablissement_ENT, ['ELV','TUT']))
            return 'subscriber';
    }
    elseif(($blog->type_de_blog == 'CLS') || ($blog->type_de_blog == 'GRP')) {
        if(has_profile($userENT, $blog->etablissement_ENT, ['DIR','ADM']))
            return 'administrator';
		$blog_id = ($blog->type_de_blog == 'GRP') ? $blog->groupe_ENT : $blog->classe_ENT;
        if(has_group_profile($userENT, $blog_id, ['PRI','ADM','ENS']))
            return 'administrator';
        if(has_group_profile($userENT, $blog_id, ['MBR','ELV']))
            return 'contributor';
        if(has_profile($userENT, $blog->etablissement_ENT, ['ENS','ETA','DOC','EVS']))
            return 'subscriber';
    }
    elseif($blog->type_de_blog == 'GPL') {
        if(has_group_profile($userENT, $blog->groupelibre_ENT, ['PRI','ADM','ENS']))
            return 'administrator';
        if(has_group_profile($userENT, $blog->groupelibre_ENT, ['MBR','ELV']))
            return 'contributor';
    }

    foreach($userENT->children as $child) {
        $child->detail = $childDetail = json_decode(get_http(ANNUAIRE_URL."api/users/$child->child_id?expand=true"));
        $childRole = getUserWpRole($child->detail, $blog);
        if ($childRole != null)
            return 'subscriber';
    }

    return null;
}

function has_right($userENT, $blog) {
    // if a public blog
    if($blog->type_de_blog == 'ENV') {
        return true;
    }

    // if the user if a super adminallow all blogs
    if($userENT->super_admin) {
        return true;
    }

    // view all blogs a user can view with its profiles
    foreach($userENT->profiles as $profile) {
        if ($profile->structure_id != $blog->etablissement_ENT)
            continue;
        // depending on the blog type
        if($blog->type_de_blog == 'ETB') {
            // like we have an active profil in the etablissement
            return true;
        }
        elseif($blog->type_de_blog == 'CLS') {
           if(has_group($userENT->groups, $blog->classe_ENT)) {
               return true;
           }
        }
        elseif($blog->type_de_blog == 'GRP') {
           if(has_group($userENT->groups, $blog->groupe_ENT)) {
               return true;
           }
        }
    }

    if($blog->type_de_blog == 'GPL') {
       if(has_group($userENT->groups, $blog->groupelibre_ENT)) {
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
    global $wpdb;
    $liste = array();

    // Interrogation de l'annuaireV3 de l'ENT
    $userENT = json_decode(get_http(ANNUAIRE_URL."api/users/$uid_ent?expand=true"));
    // get details for each child
    foreach ($userENT->children as $child) {
        $child->detail = $childDetail = json_decode(get_http(ANNUAIRE_URL."api/users/$child->child_id?expand=true"));
    }

    // Constitution de la liste
    $blogs = $wpdb->get_results( 
        "SELECT * FROM $wpdb->blogs WHERE domain != '".BLOGS_DOMAIN."'
        and archived = 0 and deleted = 0 and blog_id > 1 order by domain", 
        OBJECT );

	// Get all blogs
    foreach ($blogs as $blog) {
        $blogData = getBlogData($blog->blog_id);
        if(has_right($userENT, $blogData)) {
            $liste[] = $blogData;
        }
    }

    // sort (ETB, CLS, GRP, GPL and others)
    usort($liste, function($a, $b) {
       $ao = order_type($a->type_de_blog);
       $bo = order_type($b->type_de_blog);
       if($ao < $bo)
           return 1;
       if($ao > $bo)
           return -1;
       else
           return strcmp ($a->blogname, $b->blogname);
    });

    return $liste;
}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de l'utilisateur
// --------------------------------------------------------------------------------
function userBlogList($username) {
    global $wpdb;
    $user_id = username_exists($username);
	if (!$user_id)
		return [];
    $blogs = get_blogs_of_user( $user_id );
    $opts = Array('admin_email','siteurl','name','blogname','blogdescription','blogtype','etablissement_ENT','display_name', 'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT');
    $opts_str = implode("','", $opts);
    $list = array();
    foreach ($blogs as $blog) {
        // Supprimer le blog es blogs #1.
        if ($blog->userblog_id == 1) {
            continue;
        }
        // Virer ce champ tout batard 
        $blog->blog_id = "$blog->userblog_id";
        unset($blog->userblog_id);

        $blog_details = $wpdb->get_results( "SELECT option_name, option_value ". 
                                            "FROM wp_". $blog->blog_id ."_options ".
                                            "where option_name in ('".$opts_str."') order by option_name", ARRAY_A);
        $blog_opts = flatten($blog_details, 'option_name', 'option_value');

        foreach ($blog_opts as $n => $v) {
            $blog->$n = $v;
        }

        unset($blog->registered);
        unset($blog->last_updated);
        $list[] = $blog;
    }
    usort($list, function ($a, $b) { return strcmp($a->domain, $b->domain); });
    return $list;
}

// --------------------------------------------------------------------------------
// Return true if a given blog MUST be displayed without choice for a given user
// --------------------------------------------------------------------------------

function is_forced_blog($blog, $userENT) {
//	$blog = (array)$blog;
	// depending on the blog type
	if($blog->type_de_blog == 'ETB') {
		if (has_profile($userENT, $blog->etablissement_ENT))
			return true;
	}
	elseif($blog->type_de_blog == 'CLS') {
		if(has_group($userENT->groups, $blog->classe_ENT))
			return true;
	}
	elseif($blog->type_de_blog == 'GRP') {
		if(has_group($userENT->groups, $blog->groupe_ENT))
			return true;
	}
	elseif($blog->type_de_blog == 'GPL') {
		if(has_group($userENT->groups, $blog->groupelibre_ENT))
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

    // Interrogation de l'annuaireV3 de l'ENT
    $userENT = json_decode(get_http(ANNUAIRE_URL."api/users/$uid_ent?expand=true"));
    // get details for each child
    foreach ($userENT->children as $child) {
        $child->detail = $childDetail = json_decode(get_http(ANNUAIRE_URL."api/users/$child->child_id?expand=true"));
    }

	// récupération des information de l'utilisateur WordPress
	$userRec = get_user_by('login',$userENT->login);
	$userId = $userRec->ID;

	$blogs = blogList($uid_ent);
	$list = [];

	$role_order['subscriber'] = 1;
	$role_order['contributor'] = 2;
	$role_order['editor'] = 3;
	$role_order['administrator'] = 4;

	foreach ($blogs as $blog) {
		if (is_forced_blog($blog, $userENT)) {
			$blog->forced = true;
			array_push($list, $blog);
			// Add rights on blog if needed
			if ($userId) {
		        $default_role = getUserWpRole($userENT, $blog);
				$current_role = get_user_blog_role($userId, $blog->blog_id);
				// if the default role is better than the current upgrade/create it
				if ($default_role != null) {
					if ($current_role == null || $role_order[$default_role] > $role_order[$current_role]) {
						add_user_to_blog($blog->blog_id, $userId, $default_role);
					}
				}
			}
		}
	}

	$user_blogs = userBlogList($userENT->login);
	foreach ($user_blogs as $mine) {
		$found = false;
		foreach($list as $blog) {
			if ($mine->blog_id == $blog->blog_id) {
				$found = true;
				break;
			}
		}
		if (!$found)
			array_push($list, $mine);
	}

	return $list;
}

// --------------------------------------------------------------------------------
// fonction qui renvoie vrai si l'utilisateur a un role quelconque sur le blog donné.
// --------------------------------------------------------------------------------
function aUnRoleSurCeBlog($pUserId, $pBlogId){
  return is_user_member_of_blog($pUserId, $pBlogId);
}

// --------------------------------------------------------------------------------
function aLeRoleSurCeBlog($userId, $pBlogId, $role){
  $can = false;
  if ( is_multisite() ) {
    // D'abord swicher sur le bon blog, sinon rien ne fonctionne en terme de capabilities.
    switch_to_blog($pBlogId);
    // ensuite récupérer l'objet current_user, qui devrait être peuplé correctement
    $cu = (array) wp_get_current_user();
    // Le [cap_key] doit être égal à "wp_".$blogId."_capabilities"	
    if ($cu['cap_key'] =="wp_".$pBlogId."_capabilities") {  
      // Alors $cu[roles] donne le tableau des roles sur ce blog.
      if (in_array($role, $cu['roles'])) {
        $can = true;
      }
    }
    restore_current_blog();
    return $can;
  }
  return false;
}

// --------------------------------------------------------------------------------
// Fonction de gestionnaire d'assertion
// --------------------------------------------------------------------------------
function message_erreur_assertion($file, $line, $code, $desc = null)
{
    $s = "Echec de l'assertion : $code";
    if ($desc) {
        $s .= ": $desc";
    }

    header('Content-Type: application/json');
    echo '{ "error" :  "'.str_replace('"', "'", $s).'" }';
    die();
} 

