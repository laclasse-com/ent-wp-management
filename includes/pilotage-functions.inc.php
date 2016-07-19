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
function fais_voir($o, $s="") {
    //echo "<pre>";
    echo $s;
    echo  print_r($o, true);
    echo "\n";
    //echo "</pre>";

}
function flatten($a, $name, $val) {
    $res = array();
    foreach($a as $k => $v) {
        $res[$v[$name]] = $v[$val];
    }
    return $res;
}

/*
 * vérifier que l'utilisateur a un role donné sur un etab donné.
 * Si on verifie le role 'TECH', l'étab n'est pas pris en compte.
 */
function has_role($roles, $wanted_role, $uai="") {
    foreach ($roles as $role) {
        if ($role->role_id == $wanted_role && ( $role->etablissement_code_uai == $uai || $wanted_role == 'TECH' )) {
            return true;
        }
    }
    return false;
}

/*
 * vérifier que l'utilisateur a un profil donné sur un etab donné.
 */
function has_profil($profils, $wanted_profil, $uai="") {
    foreach ($profils as $profil) {
        if ($profil->profil_id == $wanted_profil && $profil->etablissement_code_uai == $uai) {
            return true;
        }
    }
    return false;
}

/*
 * vérifier que l'utilisateur a une classe donnée sur un etab donné.
 */
function has_classe($classes, $wanted_classe) {
    foreach ($classes as $classe) {
        if ($classe->classe_id == $wanted_classe) {
            return true;
        }
    }
    return false;
}

/*
 * vérifier que l'utilisateur a un groupe donné sur un etab donné.
 */
function has_groupe($groupes, $wanted_groupe) {
    foreach ($groupes as $groupe) {
        if ($groupe->groupe_id == $wanted_groupe) {
            return true;
        }
    }
    return false;
}
// --------------------------------------------------------------------------------
// Liste des blogs visibles par un utilisateur selon son profil
//  TECH :  il voit tout
//  ADM_ETB/DIRECTION/DOC/PROF : Tous ceux de son établissement ETB + CLS + GRP + Transverses
//  ELEVE/PARENT : Sa Classe (celle de ses enfants), ses groupes et transverses
// --------------------------------------------------------------------------------
function blogList() {
    global $wpdb;
    $opts = Array('admin_email','siteurl','name','blogdescription','blogtype','etablissement_ENT','display_name', 'type_de_blog', 'classe_ENT', 'groupe_ENT');
    $opts_str = implode("','", $opts);
    $liste = array();
    $query = "";

    // Quelques vérifications d'usage pour controler les résultats de l'extraction
    $current_user = get_user_by('login',phpCAS::getUser());
    // Vérifier si l'utilisateur est bien connecté
    assert ('$current_user->ID  != ""', "L'utilisateur n'est pas connecté sur la plateforme WordPress de laclasse.com.");
    // Récupération des champs meta de l'utilisateur 
    $userMeta = get_user_meta($current_user->ID);
    assert ('$userMeta[\'profil_ENT\'][0] != ""', "Cet utilisateur n'a pas de profil sur la plateforme WordPress de laclasse.com.");

    // Caractéristiques du blog.
    $uid_ent_WP =  $userMeta['uid_ENT'][0];
    $profil_ent_WP = $userMeta['profil_ENT'][0];
    $uai_user_WP = $userMeta['etablissement_ENT'][0];
    $classe_user_WP = $userMeta['classe_ENT'][0];

    fais_voir($uid_ent_WP, "uid_ent_WP=");
    fais_voir($profil_ent_WP, "profil_ent_WP=");
    fais_voir($uai_user_WP, "uai_user_WP=");
    fais_voir($classe_user_WP, "classe_user_WP=");

    // Interrogation de l'annuaireV3 de l'ENT
    $userENT =json_decode(get_http(generate_url(ANNUAIRE_URL."api/app/users/$uid_ent_WP", Array("expand" => "true"))));

    // Caractéristiques du user connecté.
    $roles_user_annuaire   = $userENT->roles;
    $superadmin   = has_role($roles_user_annuaire, 'TECH');
    $admin        = has_role($roles_user_annuaire, 'ADM_ETB', $uai_user_WP) || has_profil($roles_user_annuaire, 'DIR', $uai_user_WP);
    $eleve_parent = has_profil($roles_user_annuaire, 'ELV', $uai_user_WP) || has_profil($roles_user_annuaire, 'TUT', $uai_user_WP);
    $perseducnat  = has_profil($roles_user_annuaire, 'ENS', $uai_user_WP) 
                    || has_profil($roles_user_annuaire, 'DOC', $uai_user_WP) 
                    || has_profil($roles_user_annuaire, 'ETA', $uai_user_WP) 
                    || has_profil($roles_user_annuaire, 'EVS', $uai_user_WP);
    // Tous les autres profils COL, ACA ne sont pas gérés pour le moment
    // Classes de l'utilisateur
    $classes_user_annuaire = $userENT->classes;
    // Groupes de l'utilisateur
    $groupes_user_annuaire = $userENT->groupes_eleves;

    // Constitution de la liste
    $blogs = $wpdb->get_results( 
        "SELECT * FROM $wpdb->blogs WHERE domain != '".BLOG_DOMAINE."'  
        and archived = 0 order by domain", 
        ARRAY_A );

    // Constitution de la liste
    foreach ($blogs as $blog) {
        $blog_details = $wpdb->get_results( "SELECT option_name, option_value ". 
                                            "FROM wp_". $blog['blog_id'] ."_options ".
                                            "where option_name in ('".$opts_str."') order by option_name", ARRAY_A);
        $blog_opts = flatten($blog_details, 'option_name', 'option_value');

        foreach ($blog_opts as $n => $v) {
            $blog[$n] = $v;
        }

        unset($blog['registered']);
        unset($blog['last_updated']);

        // Restriction de la liste selon le profil
        if ( 
            $superadmin || // tous pour le superadmin
            ( 
                 $admin || // tous pour l'admin d'atablissement
                 ( 
                    $blog['etablissement_ENT'] == $uai_user_WP &&
                    (
                        ($blog['type_de_blog'] == 'ETB' && ($eleve_parent || $perseducnat || $admin) )  // Blogs d'étab pour parents/eleves/perseducnat/Admin
                    ||
                        ($blog['type_de_blog'] == 'CLS' && has_classe($classes_user_annuaire, $blog['classe_ENT']) && ($eleve_parent || $perseducnat) )  // Blogs de classe pour parents/eleves/perseducnat
                    ||
                        ($blog['type_de_blog'] == 'GRP' && has_groupe($groupes_user_annuaire, $blog['groupe_ENT']) && ($eleve_parent || $perseducnat) )  // Blogs de classe pour parents/eleves/perseducnat
                    )
                 )
            || ($blog['type_de_blog'] !== 'ETB' && $blog['type_de_blog'] !== 'CLS'  && $blog['type_de_blog'] !== 'GRP' ) // Les blogs transverses pour tous.
            )
           ) {
            $liste[] = $blog;
        }
    }


    return $liste;
/*

    $blogs = wp_get_sites(array("limit" => "9999", "archived" => "0"));
    $list = array();
    foreach ($blogs as $blog) {
        // Pas de détail sur la liste des nblogs d'un utilisateur
            $blog_details = $wpdb->get_results( "SELECT * ". 
                                                "FROM wp_". $blog['blog_id'] ."_options ".
                                                "order by option_name");
            $post_details = $wpdb->get_results( "SELECT * ". 
                                                "FROM wp_". $blog['blog_id'] ."_posts ".
                                                "");

            $blog['nb_posts'] = count($post_details);
            $blog['admin_comment'] = " ";
            // S'il n'y a qu'un article, et que c'est l'article par défaut, si le blog est ancien, il n'est pas utilisé.
            if (count($post_details) == 1) {
                 if (substr($post_details[0]->post_title, 0, 35) == "Bienvenue dans votre nouveau weblog" && $post_details[0]->ID == 1) {
                    $blog['admin_comment'] = "Unused blog";
                }
            }

            foreach ($blog_details as $k => $opt) {
                switch ($opt->option_name) {
                    case 'admin_email':
                        $blog['admin_email'] = $opt->option_value;
                        break;
                    case 'siteurl':
                        $blog['siteurl'] = $opt->option_value;
                        break;
                    case 'blogname':
                        $blog['name'] = $opt->option_value;
                        break;
                    case 'blogdescription':
                        $blog['blogdescription'] = $opt->option_value;
                        break;
                    case 'idBLogENT':
                        $blog['idBLogENT'] = $opt->option_value;
                        break;
                    case 'type_de_blog':
                        $blog['blogtype'] = $opt->option_value;
                        break;
                    case 'etablissement_ENT':
                        $blog['etablissement_ENT'] = $opt->option_value;
                        break;
                    case 'display_name':
                        $blog['owner_name'] = $opt->option_value;
                        break;
                    default:
                        break;
                }
            }
        unset($blog['registered']);
        unset($blog['last_updated']);
        $list[] = $blog;
    }
    return $list;
    */
}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de l'utilisateur
// --------------------------------------------------------------------------------
function userBlogList($username) {
    global $wpdb;
    $user_id = username_exists($username);

/*
    $uRec = get_userdata($user_id);
    $uid_proprio = get_user_meta($uRec->ID, "uid_ENT", true);
    $display_name_proprio = get_user_meta($uRec->ID, "display_name", true);
*/

    $blogs = get_blogs_of_user( $user_id );
    $list = array();
    foreach ($blogs as $blog) {

        // Virer ce champ tout batard 
        $blog->blog_id = "$blog->userblog_id";
        unset($blog->userblog_id);
        // Normaliser celui-là
        $blog->name = $blog->blogname;
        unset($blog->blogname);

        $blog_details = $wpdb->get_results( "SELECT * ". 
                                            "FROM wp_". $blog->blog_id ."_options ".
                                            "order by option_name");

        foreach ($blog_details as $opt) {
            switch ($opt->option_name) {
                case 'admin_email':
                    $blog->admin_email = $opt->option_value;
                    break;
                case 'idBLogENT':
                    $blog->idBLogENT = $opt->option_value;
                    break;
                case 'etablissement_ENT':
                    $blog->etablissement_ENT = $opt->option_value;
                    break;
                case 'post_count':
                    $blog->nb_posts = $opt->option_value;
                    break;
                case 'blogname':
                    $blog->blogname = $opt->option_value;
                    break;
                case 'blogdescription':
                    $blog->blogdescription = $opt->option_value;
                    break;
                default:
                    break;
            }
        }
    

        // L'administrateur de chaque blog
        $user_id_from_email = get_user_id_from_string( get_blog_option($blog->blog_id, 'admin_email'));
        $details = get_userdata($user_id_from_email);
        $blog->owner_name = $details->display_name;
        // UID
        $u = get_userdata($details->ID);
        $uid_proprio = get_user_meta($u->ID, "uid_ENT", true);
        $blog->owner_uid = $uid_proprio;

        // Details du parametrage du blog
        $blog->public = get_blog_option($blog->blog_id, 'blog_public');
        $blog->blogtype = get_blog_option($blog->blog_id, 'type_de_blog');
       $blog->lang_id = "0";

        // Les posts de l'utilisateur
        $post_details = $wpdb->get_results( "SELECT * FROM wp_". $blog->blog_id ."_posts");
        //$blog->nb_posts = count($post_details);
        $blog->my_posts = 0;

        // S'il n'y a qu'un article, et que c'est l'article par défaut, si le blog est ancien, il n'est pas utilisé.
        $blog->admin_comment = " ";
        if (count($post_details) == 1) {
             if (substr($post_details[0]->post_title, 0, 35) == "Bienvenue dans votre nouveau weblog" && $post_details[0]->ID == 1) {
                $blog->admin_comment = "Unused blog";
            }
        }

        foreach ($post_details as $p) {
            if ($p->post_author == $user_id){
                $blog->my_posts++;
            }
        }
        $list[] = $blog;
    }
    return $list;
}

// --------------------------------------------------------------------------------
// fonction de controle de l'intégration dans une Iframe.
// --------------------------------------------------------------------------------
function modeIntegreIframeENT() {	
	// utiliser le template "headless" spécial intégration dans l'ENT.
	return "headless";	
}

// --------------------------------------------------------------------------------
// fonction de modification des paramètres d'un blog.
// --------------------------------------------------------------------------------
function modifierParams($domain) {
	wp_redirect("http://$domain/wp-admin/options-general.php");
}

// --------------------------------------------------------------------------------
// fonction qui renvoie vrai si l'utilisateur a un role quelconque sur le blog donné.
// --------------------------------------------------------------------------------
function aUnRoleSurCeBlog($pUserId, $pBlogId){
  switch_to_blog($pBlogId);
  $res = is_blog_user($pBlogId);
  restore_current_blog();
  return $res;
}

// --------------------------------------------------------------------------------
// fonction qui renvoie true si l'utilisateur est administrateur de son domaine.
/*

Structure de l'objet WP_User une fois qu'on a switché sur le bon blog.

WP_User Object
(
    [data] => stdClass Object
        (
            [ID] => 99
            [user_login] => tests-unitaires-wp
            [user_pass] => $P$BUx4pmexHS38a2AJ.IuoXOvtetM2a7.
            [user_nicename] => tests-unitaires-wp
            [user_email] => tests-unitaires-wp@laclasse.com
            [user_url] => 
            [user_registered] => 2013-01-30 14:53:48
            [user_activation_key] => 
            [user_status] => 0
            [display_name] => tests-unitaires-wp
            [spam] => 0
            [deleted] => 0
        )

    [ID] => 99
    [caps] => Array
        (
            [author] => 1
        )

    [cap_key] => wp_125_capabilities
    [roles] => Array
        (
            [0] => author
        )

    [allcaps] => Array
        (
            [upload_files] => 1
            [edit_posts] => 1
            [edit_published_posts] => 1
            [publish_posts] => 1
            [read] => 1
            [level_2] => 1
            [level_1] => 1
            [level_0] => 1
            [delete_posts] => 1
            [delete_published_posts] => 1
            [author] => 1
        )

    [filter] => 
)
*/
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
// fonction qui permet de forcer l'usage d'un template simplifé pour 
// le mode "intégration dans l'ENT" en Iframe.
// --------------------------------------------------------------------------------
function setIframeTemplate() {
	wp_enqueue_script('jquery'); 
	// script de detection d'IFRAME qui ajoute le contexte de navigation à toutes les urls.
	$plugin_js_url = WP_PLUGIN_URL.'/ent-wp-management/js';
	wp_enqueue_script('wp_wall_script', $plugin_js_url.'/ent-wp-managment-iframe-detect.js');

	// Forcer l'affichage du modèle simplifié.
	add_filter('stylesheet', 'modeIntegreIframeENT');
	add_filter('template', 'modeIntegreIframeENT');
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


// --------------------------------------------------------------------------------
// renvoie l'id WP de l'utilisateur en fonction de son login
// --------------------------------------------------------------------------------
function get_user_id_by_login($login) {
    global $wpdb;
    $r = $wpdb->get_results( "SELECT ID FROM wp_users where user_login = '".strtolower($login)."'");    
    return $r[0]->ID;
}

function reprise_data_blogs(){
    $opts = Array('admin_email','siteurl','name','blogdescription','blogtype','etablissement_ENT','display_name', 'type_de_blog', 'classe_ENT', 'groupe_ENT');
    $opts_str = implode("','", $opts);
    $closeForm = "&nbsp;<button type='submit'>Ok</button></form>";
    $message = "";

    // Gestion des actions 
    $action2 = $_REQUEST['action2'];        
    if (isset($action2) && $action2 != "") {
        $id = $_REQUEST['id'];
        if(isset($_REQUEST['uai'])){
            $message = "<div class='msg'>Etablissement mis &agrave; jour.</div>";
            add_blog_option( $id, 'etablissement_ENT', $_REQUEST['uai'] );
        }
        if(isset($_REQUEST['clsid'])){
            $message = "<div class='msg'>Id de classe mis &agrave; jour.</div>";
            add_blog_option( $id, 'classe_ENT', $_REQUEST['clsid'] );
        }
        if(isset($_REQUEST['grpid'])){
            $message = "<div class='msg'>Id de groupe mis &agrave; jour.</div>";
            add_blog_option( $id, 'groupe_ENT', $_REQUEST['grpid'] );
        }
        //header("Location: http://".BLOG_DOMAINE."/?ENT_action=$ENT_action");
    }

    // Extraction bdd
    global $wpdb;
    $query = "";
    $liste = $wpdb->get_results( "SELECT blog_id, domain, archived FROM $wpdb->blogs WHERE domain != '".BLOG_DOMAINE."'  and archived = 0 order by domain", ARRAY_A );

    $html = "<html><head><title>Liste des sites à reprendre</title>
    <style>
          table td {padding:3px 20px 3px 20px;}
          table td {border:black solid 1px;}
          .warn {background-color:orange;}
          .lilipute {font-size:0.6em;}
          .msg {border:green solid 1px; float:right; margin-right:20%;background-color:lightgreen;padding:4px;}
    </style>\n</head><body><div style='margin:40px;'><h1>Liste des sites &agrave; reprendre</h1>\n
    $message
    <table><tr><th>nom</th><th>url</th><th>type_de_blog</th><th>UAI</th><th>classe_ENT</th><th>groupe_ENT</th></tr>\n";
    $html .= "<p>Affectation d'un id de classe, de groupe ou d'établissement</p>";
    foreach($liste as $k => $blog) {
        // Récupérer des options du blog
        $blog_details = $wpdb->get_results( "SELECT option_name, option_value ". 
                                            "FROM wp_". $blog['blog_id'] ."_options ".
                                            "where option_name in ('".$opts_str."') order by option_name", ARRAY_A);
        $blog_opts = flatten($blog_details, 'option_name', 'option_value'); 

        $form = "<form method='post'>
        <input type='hidden' name='ENT_action' value='".$_REQUEST['ENT_action']."'/>
        <input type='hidden' name='action2' value='maj'/>
        <input type='hidden' name='id' value='" . $blog['blog_id'] . "'/>";
    
        $html .= "<tr class=''>";
        $html .= "<td><a name='".($k+1)."'></a>".($k+1)."</td>";
        $html .= "<td><a href='http://".$blog['domain']."/' target='_blank'>".$blog['domain']."</a><br/> ".$blog_opts['blogdescription']."</td>";
        $html .= "<td>". $blog_opts['type_de_blog']. "</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "ETB" && $blog_opts['etablissement_ENT'] == "") {
            $class_warn = "warn";
            $champ_data = "$form" . selectbox_etabs() . "$closeForm";
        }
        $html .= "<td class='$class_warn'>". $blog_opts['etablissement_ENT']. "$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "CLS" && $blog_opts['classe_ENT'] == "") {
            $class_warn = "warn";
            $champ_data = "$form<input type='text' name='clsid'/>$closeForm";
        }
        $html .= "<td class='$class_warn'>". $blog_opts['classe_ENT']. "$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "GRP" && $blog_opts['groupe_ENT'] == "") {
            $class_warn = "warn";
            $champ_data = "$form<input type='text' name='grpid'/>$closeForm";
        }

        $html .= "<td class='$class_warn'>". $blog_opts['groupe_ENT']. "$champ_data</td>";

        $html .= "</tr>\n";
    }
    $html .= "</table>\n</div></body></html>";
    echo $html;

}

function selectbox_etabs(){
return "
<select name='uai'>
<option value=''>...</option>
<option value='0691830P'>C.E.M. HENRY GORMAND</option>
<option value='0692933N'>CLG PR-CHARLES DE FOUCAULD</option>
<option value='0692937T'>CLG PR-LA FAVORITE SAINTE THERESE </option>
<option value='0690614T'>CLG PR-SAINT LAURENT</option>
<option value='0691666L'>CLG-AIME CESAIRE</option>
<option value='0692342W'>CLG-ALAIN </option>
<option value='0692693C'>CLG-AMPERE</option>
<option value='0691728D'>CLG-ANDRE LASSAGNE</option>
<option value='0691663H'>CLG-BELLECOMBE</option>
<option value='0694151M'>CLG-CHRISTIANE BERNARDIN</option>
<option value='0693479G'>CLG-CITE SCOLAIRE INTERNATIONALE</option>
<option value='0691497C'>CLG-COLETTE </option>
<option value='0691824H'>CLG-DAISY GEORGES MARTIN</option>
<option value='0690022Z'>CLG-DE LA HAUTE AZERGUES</option>
<option value='0692155T'>CLG-DES GRATTE-CIEL MORICE LEROUX </option>
<option value='0693093M'>CLG-DU TONKIN </option>
<option value='0692343X'>CLG-ELSA TRIOLET</option>
<option value='0692419E'>CLG-EMILE MALFROY </option>
<option value='0692335N'>CLG-EVARISTE GALOIS </option>
<option value='0691645N'>CLG-FAUBERT </option>
<option value='0692520P'>CLG-FREDERIC MISTRAL</option>
<option value='0692578C'>CLG-GABRIEL ROSSET</option>
<option value='0693890D'>CLG-GEORGES CHARPAK </option>
<option value='0692339T'>CLG-GEORGES CLEMENCEAU</option>
<option value='0692160Y'>CLG-GERARD PHILIPE</option>
<option value='0694007F'>CLG-GILBERT DRU </option>
<option value='0692340U'>CLG-HENRI LONGCHAMBON </option>
<option value='0691670R'>CLG-JEAN CHARCOT</option>
<option value='0692703N'>CLG-JEAN DE VERRAZANE </option>
<option value='0692521R'>CLG-JEAN GIONO</option>
<option value='0691664J'>CLG-JEAN JAURES </option>
<option value='0691478G'>CLG-JEAN MACE </option>
<option value='0690060R'>CLG-JEAN MERMOZ </option>
<option value='0692334M'>CLG-JEAN MONNET </option>
<option value='0692696F'>CLG-JEAN MOULIN </option>
<option value='0692698H'>CLG-JEAN PERRIN </option>
<option value='0692422H'>CLG-JEAN ROSTAND</option>
<option value='0692163B'>CLG-JEAN-JACQUES ROUSSEAU </option>
<option value='0691479H'>CLG-JOLIOT CURIE</option>
<option value='0690094C'>CLG-JULES MICHELET</option>
<option value='0691673U'>CLG-LA CLAVELIERE </option>
<option value='0694191F'>CLG-LA TOURETTE </option>
<option value='0692337R'>CLG-LAMARTINE </option>
<option value='0691481K'>CLG-LAURENT MOURGUET</option>
<option value='0691484N'>CLG-LE PLAN DU LOUP </option>
<option value='0690280E'>CLG-LES IRIS</option>
<option value='0691668N'>CLG-LES SERVIZIERES </option>
<option value='0691675W'>CLG-LOUIS JOUVET</option>
<option value='0693331W'>CLG-LOUIS LEPRINCE RINGUET</option>
<option value='0691483M'>CLG-LUCIE AUBRAC</option>
<option value='0690076H'>CLG-MARCEL PAGNOL </option>
<option value='0691498D'>CLG-MARIA CASARES </option>
<option value='0692704P'>CLG-OLIVIER DE SERRES </option>
<option value='0693287Y'>CLG-PAUL D'AUBAREDE </option>
<option value='0690075G'>CLG-PIERRE BROSSOLETTE</option>
<option value='0692346A'>CLG-PIERRE DE RONSARD </option>
<option value='0690053H'>CLG-PROFESSEUR DARGENT</option>
<option value='0690131T'>CLG-RAOUL DUFY</option>
<option value='0692898A'>CLG-RENE CASSIN </option>
<option value='0693975W'>CLG-SIMONE VEIL </option>
<option value='0693834T'>CLG-THEODORE MONOD</option>
<option value='0690078K'>CLG-VAL D'ARGENT</option>
<option value='0692338S'>CLG-VENDOME </option>
<option value='0691669P'>CLG-VICTOR GRIGNARD </option>
<option value='0690036P'>CLG-VICTOR SCHOELCHER </option>
<option value='0692165D'>CLGH-ELIE VIGNAL</option>
<option value='0692583H'>COLLEGE BANS</option>
<option value='0692417C'>COLLEGE BORIS VIAN</option>
<option value='0692410V'>COLLEGE CHARLES SENARD</option>
<option value='0691662G'>COLLEGE CLEMENT MAROT </option>
<option value='0692157V'>Collège Georges Brassens</option>
<option value='0692336P'>COLLEGE HENRI BARBUSSE</option>
<option value='0691480J'>COLLEGE HONORE DE BALZAC</option>
<option value='0691736M'>COLLEGE JEAN DE TOURNES </option>
<option value='0692423J'>COLLEGE JEAN RENOIR </option>
<option value='0692414Z'>COLLEGE JEAN-PHILIPPE RAMEAU</option>
<option value='0692695E'>COLLEGE LACASSAGNE</option>
<option value='0691614E'>COLLEGE LEONARD VINCI </option>
<option value='0691798E'>COLLEGE LES BATTIERES </option>
<option value='0691799F'>COLLEGE LOUIS ARAGON</option>
<option value='0692579D'>COLLEGE MARTIN LUTHER KING</option>
<option value='0691495A'>COLLEGE MARYSE BASTIE </option>
<option value='0692411W'>COLLEGE MOLIERE </option>
<option value='0692576A'>COLLEGE PABLO PICASSO </option>
<option value='0692159X'>COLLEGE PAUL EMILE VICTOR </option>
<option value='0690249W'>COLLEGE PIERRE VALDO</option>
<option value='0692920Z'>COLLEGE PRIVE AUX LAZARISTES</option>
<option value='0693491V'>COLLEGE PRIVE BETH MENAHEM</option>
<option value='0692932M'>COLLEGE PRIVE CHEVREUL</option>
<option value='0692928H'>COLLEGE PRIVE DES CHARTREUX </option>
<option value='0692945B'>COLLEGE PRIVE IMMACULEE CONCEPTION</option>
<option value='0690551Z'>COLLEGE PRIVE LA XAVIERE</option>
<option value='0690626F'>COLLEGE PRIVE MERE TERESA </option>
<option value='0690604G'>COLLEGE PRIVE NOTRE-DAME DE BELLECOMBE</option>
<option value='0692941X'>COLLEGE PRIVE NOTRE-DAME DE BELLEGARDE</option>
<option value='0692940W'>COLLEGE PRIVE PIERRE TERMIER</option>
<option value='0692943Z'>COLLEGE PRIVE SAINT JOSEPH</option>
<option value='0692921A'>COLLEGE PRIVE SAINTE MARIE</option>
<option value='0690245S'>CRDP de Lyon</option>
<option value='069DANEZ'>DANE</option>
<option value='0691831R'>E.S.E.M. ECOLE SPECIALISEE DES ENFANTS MALADES</option>
<option value='0691629W'>ECOLE ELEMENTAIRE </option>
<option value='0693562X'>ECOLE ELEMENTAIRE ALBERT MOUTON </option>
<option value='0692285J'>ECOLE ELEMENTAIRE ALPHONSE DAUDET </option>
<option value='0693724Y'>ECOLE ELEMENTAIRE ANATOLE FRANCE</option>
<option value='0690409V'>ECOLE ELEMENTAIRE AUDREY HEPBURN</option>
<option value='0690431U'>ECOLE ELEMENTAIRE CAVENNE </option>
<option value='0692900C'>ECOLE ELEMENTAIRE DU GOLF </option>
<option value='0693042G'>ECOLE ELEMENTAIRE LOUIS PASTEUR </option>
<option value='0690852B'>ECOLE ELEMENTAIRE LUCIE GUIMET</option>
<option value='0690855E'>ECOLE ELEMENTAIRE MARIUS GROS </option>
<option value='0693254M'>ECOLE MATERNELLE</option>
<option value='0691067K'>ECOLE MATERNELLE ALIX </option>
<option value='0691620L'>ECOLE MATERNELLE CITE CASTELLANE</option>
<option value='0692848W'>ECOLE MATERNELLE DU PLATEAU </option>
<option value='0691213U'>ECOLE MATERNELLE EDOUARD HERRIOT</option>
<option value='0692300A'>ECOLE MATERNELLE ET ELEMENTAIRE VANCIA</option>
<option value='0693097S'>ECOLE MATERNELLE FREDERIC MISTRAL </option>
<option value='0691038D'>ECOLE MATERNELLE JEAN GERSON</option>
<option value='0692603E'>ECOLE MATERNELLE JEAN LURCAT</option>
<option value='0693755G'>ECOLE MATERNELLE LES ALLAGNIERS </option>
<option value='0693754F'>ECOLE MATERNELLE LES CHARMILLES </option>
<option value='0692849X'>ECOLE MATERNELLE LES ECUREUILS</option>
<option value='0691707F'>ECOLE MATERNELLE PABLO PICASSO</option>
<option value='0690478V'>ECOLE MATERNELLE PARMENTIER </option>
<option value='0690455V'>ECOLE MATERNELLE SAINT EXUPERY</option>
<option value='0690839M'>ECOLE PRIMAIRE</option>
<option value='0690860K'>ECOLE PRIMAIRE</option>
<option value='0690853C'>ECOLE PRIMAIRE A.M. AMPERE</option>
<option value='0692263K'>ECOLE PRIMAIRE ANATOLE FRANCE </option>
<option value='0693126Y'>ECOLE PRIMAIRE ANTOINE REMOND </option>
<option value='0692303D'>ECOLE PRIMAIRE B LOUIS PERGAUD</option>
<option value='0693896K'>ECOLE PRIMAIRE BONY AVENTURIERE </option>
<option value='0691622N'>ECOLE PRIMAIRE CASTELLANE </option>
<option value='0693513U'>ECOLE PRIMAIRE CENTRE </option>
<option value='0693852M'>ECOLE PRIMAIRE CHARLES PERRAULT </option>
<option value='0691571H'>ECOLE PRIMAIRE CONDORCET</option>
<option value='0690834G'>ECOLE PRIMAIRE D'APPLICATION JOSEPH CORNIER </option>
<option value='0691300N'>ECOLE PRIMAIRE D'APPLICATION VICTOR HUGO</option>
<option value='0691643L'>ECOLE PRIMAIRE DANIS - LES GRAINS DE BLE</option>
<option value='0693532P'>ECOLE PRIMAIRE DE REVAISON</option>
<option value='0693827K'>ECOLE PRIMAIRE DES TABLES CLAUDIENNES </option>
<option value='0693894H'>ECOLE PRIMAIRE DU CENTRE</option>
<option value='0693514V'>ECOLE PRIMAIRE DU CENTRE</option>
<option value='0691588B'>ECOLE PRIMAIRE DU PLATEAU </option>
<option value='0691311A'>ECOLE PRIMAIRE FULCHIRON</option>
<option value='0690468J'>ECOLE PRIMAIRE JEAN JAURES</option>
<option value='0693212S'>ECOLE PRIMAIRE JEAN MOULIN</option>
<option value='0690856F'>ECOLE PRIMAIRE JEAN RAINE </option>
<option value='0693423W'>ECOLE PRIMAIRE JOSEPH THEVENOT</option>
<option value='0693712K'>ECOLE PRIMAIRE JULES FERRY</option>
<option value='0693629V'>ECOLE PRIMAIRE JULES VALLES </option>
<option value='0694189D'>ECOLE PRIMAIRE JULIE-VICTOIRE DAUBIE</option>
<option value='0693117N'>ECOLE PRIMAIRE LE CHATER</option>
<option value='0693326R'>ECOLE PRIMAIRE LEO LAGRANGE </option>
<option value='0691563Z'>ECOLE PRIMAIRE LES CALABRES </option>
<option value='0691074T'>ECOLE PRIMAIRE LES MARRONNIERS</option>
<option value='0692860J'>ECOLE PRIMAIRE LES TILLEULS </option>
<option value='0693017E'>ECOLE PRIMAIRE P. ET M. CURIE </option>
<option value='0693707E'>ECOLE PRIMAIRE PAUL BERT</option>
<option value='0691977Z'>ECOLE PRIMAIRE PRIVEE SAINT CHARLES SAINT FRANCOIS D'ASSISE </option>
<option value='0691959E'>ECOLE PRIMAIRE PRIVEE SAINT-JUST / SAINT-IRENEE </option>
<option value='0693836V'>ECOLE PRIMAIRE PUBLIQUE LA FONTAINE </option>
<option value='0693907X'>ECOLE PRIMAIRE PUBLIQUE LOUIS PASTEUR </option>
<option value='0691225G'>ECOLE PRIMAIRE SAINT EXUPERY</option>
<option value='0694190E'>ECOLE PRIMAIRE SALVADOR ALLENDE </option>
<option value='0696962G'>ECOLE PRIMAIRE SIMONE DE BEAUVOIR </option>
<option value='0699990Z'>ERASME</option>
<option value='0692390Y'>EREA-CITE SCOLAIRE RENE PELLET</option>
<option value='0692309K'>FONDATION RICHARD </option>
<option value='0691780K'>SEGPA CLG JEAN RENOIR </option>
</select>
";    
}
