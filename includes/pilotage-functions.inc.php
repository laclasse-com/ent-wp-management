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
function has_profil($profil_actif, $wanted_profil, $uai="") {
    if ($profil_actif->profil_id == $wanted_profil && $profil_actif->etablissement_code_uai == $uai) {
        return true;
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

function has_groupelibre($groupes, $wanted_groupe) {
    foreach ($groupes as $groupe) {
        if ($groupe->regroupement_libre_id == $wanted_groupe) {
            return true;
        }
    }
    return false;
}

//
// Return a WordPress role for the given user on the given blog
// depending on his current "profil_actif"
// TODO: match with all profil and not only "profil_actif"
//
function getUserWpRole($userENT, $blog) {
    // if the user if a TECH allow all blogs
    if(has_role($userENT->roles, 'TECH')) {
        return 'administrator';
    }

    // if a public blog
    if($blog['type_de_blog'] == 'ENV') {
        return 'subscriber';
    }

    $uai = $userENT->profil_actif->etablissement_code_uai;

    // depending on the blog type
    if($blog['type_de_blog'] == 'ETB') {
        if(has_role($userENT->roles, 'ADM_ETB', $uai) ||
           has_role($userENT->roles, 'DIR_ETB', $uai))
            return 'administrator';
        if(has_role($userENT->roles, 'PROF_ETB', $uai) || 
           has_role($userENT->roles, 'AVS_ETB', $uai) || 
           has_role($userENT->roles, 'CPE_ETB', $uai))
            return 'editor';
        if($uai == $blog['etablissement_ENT'])
            return 'subscriber';
    }
    elseif($blog['type_de_blog'] == 'CLS') {
        if(has_role($userENT->roles, 'ADM_ETB', $uai) || has_role($userENT->roles, 'DIR_ETB', $uai))
            return 'administrator';
        elseif(has_classe($userENT->classes, $blog['classe_ENT'])) {
            if(has_role($userENT->roles, 'AVS_ETB', $uai) ||
               has_role($userENT->roles, 'CPE_ETB', $uai) || 
               has_role($userENT->roles, 'PROF_ETB', $uai))
                return 'editor';
            elseif(has_role($userENT->roles, 'ELV_ETB', $uai))
                return 'contributor';
            else 
                return 'subscriber';
        }
    }
    elseif($blog['type_de_blog'] == 'GRP') {
        if(has_role($userENT->roles, 'ADM_ETB', $uai) || has_role($userENT->roles, 'DIR_ETB', $uai))
            return 'administrator';
        if(has_groupe($userENT->groupes_eleves, $blog['groupe_ENT'])) {
            if(has_role($userENT->roles, 'AVS_ETB', $uai) ||
               has_role($userENT->roles, 'CPE_ETB', $uai) || 
               has_role($userENT->roles, 'PROF_ETB', $uai))
                return 'editor';
            elseif(has_role($userENT->roles, 'ELV_ETB', $uai))
                return 'contributor';
            else
                return 'subscriber';
        }
    }
    elseif($blog['type_de_blog'] == 'GPL') {
        if(has_role($userENT->roles, 'ADM_ETB', $uai) || has_role($userENT->roles, 'DIR_ETB', $uai))
            return 'administrator';
        if(has_groupelibre($userENT->groupes_libres, $blog['groupelibre_ENT'])) {
            if(has_role($userENT->roles, 'AVS_ETB', $uai) ||
               has_role($userENT->roles, 'CPE_ETB', $uai) || 
               has_role($userENT->roles, 'PROF_ETB', $uai))
                return 'editor';
            elseif(has_role($userENT->roles, 'ELV_ETB', $uai))
                return 'contributor';
            else
                return 'subscriber';
        }
    }
    return null;
}

function has_right($userENT, $blog) {
    // if a public blog
    if($blog['type_de_blog'] == 'ENV') {
        return true;
    }

    // only view blog that correspond to the current
    // etablissement in the profil_actif
    if($userENT->profil_actif->etablissement_code_uai != $blog['etablissement_ENT']) {
        return false;
    }

    // if the user if a TECH allow all blogs
    if(has_role($userENT->roles, 'TECH')) {
        return true;
    }

    // depending on the blog type
    if($blog['type_de_blog'] == 'ETB') {
        // like we have an active profil in the etablissement
        return true;
    }
    elseif($blog['type_de_blog'] == 'CLS') {
       if(has_classe($userENT->classes, $blog['classe_ENT'])) {
           return true;
       }
    }
    elseif($blog['type_de_blog'] == 'GRP') {
       if(has_groupe($userENT->groupes_eleves, $blog['groupe_ENT'])) {
           return true;
       }
    }
    elseif($blog['type_de_blog'] == 'GPL') {
       if(has_groupelibre($userENT->groupes_libres, $blog['groupelibre_ENT'])) {
           return true;
       }
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
    global $wpdb;
    $opts = Array('admin_email', 'siteurl', 'name', 'blogname',
        'blogdescription', 'blogtype', 'etablissement_ENT', 'display_name',
        'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT');
    $opts_str = implode("','", $opts);
    $blog_details = $wpdb->get_results(
        "SELECT option_name, option_value ". 
        "FROM wp_". $blogId ."_options ".
        "WHERE option_name IN ('".$opts_str."') ORDER BY option_name",
        ARRAY_A);
    $blog_opts = flatten($blog_details, 'option_name', 'option_value');
    $blog = [];

    foreach ($blog_opts as $n => $v) {
        $blog[$n] = $v;
    }
    $blog['blog_id'] = $blogId;

    unset($blog['registered']);
    unset($blog['last_updated']);
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
    $userENT = json_decode(get_http(ANNUAIRE_URL."api/app/users/$uid_ent?expand=true"));

    // Constitution de la liste
    $blogs = $wpdb->get_results( 
        "SELECT * FROM $wpdb->blogs WHERE domain != '".BLOG_DOMAINE."'
        and archived = 0 and deleted = 0 and blog_id > 1 order by domain", 
        ARRAY_A );

	// Get all blogs
    foreach ($blogs as $blog) {
        $blogData = getBlogData($blog['blog_id']);
        if(has_right($userENT, $blogData)) {
            $liste[] = $blogData;
        }
    }

    // sort (ETB, CLS, GRP, GPL and others)
    usort($liste, function($a, $b) {
       $ao = order_type($a['type_de_blog']);
       $bo = order_type($b['type_de_blog']);
       if($ao < $bo)
           return 1;
       if($ao > $bo)
           return -1;
       else
           return strcmp ($a['blogname'], $b['blogname']);
    });

    return $liste;
}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de l'utilisateur
// --------------------------------------------------------------------------------
function userBlogList($username) {
    global $wpdb;
    $user_id = username_exists($username);
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

// --------------------------------------------------------------------------------
// Reprendre les données pour les blogs restants / Migration v2 => v3
// --------------------------------------------------------------------------------
function reprise_data_blogs(){
    $opts = Array('admin_email','siteurl','name','blogdescription','blogtype','etablissement_ENT','display_name', 'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT', 'blogname');
    $opts_str = implode("','", $opts);
    $closeForm = "<td><button type='submit'>Ok</button></td></form>";
    $message = "";
    $nb_a_reprendre = 0;
    $need_data_completion = false; // true si un formulaire quelconque est affiché.
    $tout_voir_quand_meme = false;
    if (isset($_REQUEST['tout_voir']) && $_REQUEST['tout_voir'] != "") {
        $tout_voir_quand_meme = true;
    }

    // Quelques vérifications d'usage pour controler les résultats de l'extraction
    $current_user = get_user_by('login',phpCAS::getUser());
    // Vérifier si l'utilisateur est bien connecté
    assert ('$current_user->ID  != ""', "L'utilisateur n'est pas connecté sur la plateforme WordPress de laclasse.com.");
    // Récupération des champs meta de l'utilisateur 
    $userMeta = get_user_meta($current_user->ID);
    assert ('$userMeta[\'profil_ENT\'][0] != ""', "Cet utilisateur n'a pas de profil sur la plateforme WordPress de laclasse.com.");
    // Caractéristiques du blog.
    $uid_ent_WP = $userMeta['uid_ENT'][0];
    $userENT = json_decode(get_http(ANNUAIRE_URL."api/app/users/$uid_ent_WP?expand=true"));
    // Caractéristiques du user connecté.
    $roles_user_annuaire   = $userENT->roles;
    $superadmin   = has_role($roles_user_annuaire, 'TECH');
    // 
    // Vérification de droits
    //
    if (!$superadmin) {
        die("Cette page n'est pas pour vous !");
    }

    // Gestion des actions 
    $action2 = $_REQUEST['action2'];

    if (isset($action2) && ($action2 == "archiveblog" || $action2 == "unarchiveblog")) {
        $id = $_REQUEST['id'];
        update_blog_status( $id, 'archived', ( 'archiveblog' === $action2 ) ? '1' : '0' );
    }

    if (isset($action2) && $action2 == "maj") {
        $id = $_REQUEST['id'];
        if(isset($_REQUEST['uai'])){
            $message = "<div class='msg'>Blog #$id : Etablissement mis &agrave; jour. uai=".strtoupper($_REQUEST['uai'])."</div>";
            // switch_to_blog( $id );
            // $wpdb->replace( "wp_".$id."_options", array('option_name' => 'etablissement_ENT', 'option_value' => $_REQUEST['uai']));
            update_blog_option( $id, 'etablissement_ENT', strtoupper($_REQUEST['uai']) );
            // restore_current_blog();
        }
        if(isset($_REQUEST['clsid'])){
            $message = "<div class='msg'>Blog #$id : Id de classe mis &agrave; jour. clsid=".$_REQUEST['clsid']."</div>";
            update_blog_option( $id, 'classe_ENT', $_REQUEST['clsid'] );
        }
        if(isset($_REQUEST['grpid'])){
            $message = "<div class='msg'>Blog #$id : Id de groupe mis &agrave; jour. grpid=".$_REQUEST['grpid']."</div>";
            update_blog_option( $id, 'groupe_ENT', $_REQUEST['grpid'] );
        }
        if(isset($_REQUEST['gplid'])){
            $message = "<div class='msg'>Blog #$id : Id de groupe mis &agrave; jour. gplid=".$_REQUEST['gplid']."</div>";
            update_blog_option( $id, 'groupelibre_ENT', $_REQUEST['gplid'] );
        }
        if(isset($_REQUEST['type_de_blog'])){
            $message = "<div class='msg'>Blog #$id : Type de blog mis &agrave; jour. type_de_blog=".$_REQUEST['type_de_blog']."</div>";
            update_blog_option( $id, 'type_de_blog', $_REQUEST['type_de_blog'] );
        }
        if(isset($_REQUEST['blogname'])){
            $message = "<div class='msg'>Blog #$id : blogname mis &agrave; jour</div>";
            update_blog_option( $id, 'blogname', $_REQUEST['blogname'] );
        }
        if(isset($_REQUEST['blogdescription'])){
            $message = "<div class='msg'>Blog #$id : blogdescription mis &agrave; jour</div>";
            update_blog_option( $id, 'blogdescription', $_REQUEST['blogdescription'] );
        }
    }

    // Extraction bdd
    global $wpdb;
    $query = "";
    $condition_archived = ($tout_voir_quand_meme) ? "" :"and archived = 0";
    $liste = $wpdb->get_results( "SELECT blog_id, domain, archived FROM $wpdb->blogs WHERE domain != '".BLOG_DOMAINE."' $condition_archived  order by domain", ARRAY_A );

    $headerHtml = "<html><head><title>Liste des sites à reprendre</title>" . css() .
    "<style>
          table td {padding:3px 20px 3px 20px;}
          table td {border:black solid 1px;}
          .gris-sale {background-color:#aaa;}
          .warn {background-color:orange;}
          .lilipute {font-size:0.6em;}
          .msg {border:green solid 1px; float:right; margin-right:20%;background-color:lightgreen;padding:4px;}
    </style>\n</head><body><div style='margin:40px;'><h1>Liste des sites &agrave; reprendre</h1>\n
    $message
    <table><tr><th>blog_id</th><th>url</th><th>Archivage</th><th>type_de_blog</th><th>UAI</th><th>classe_ENT</th><th>groupe_ENT</th><th>groupelibre_ENT</th></tr>\n";
    $headerHtml .= "<p>Affectation d'un id de classe, de groupe d'élèves, de groupe libre ou d'établissement. Pour chaque blog, les <span class='warn'> zones en orange</span> sont à mettre à jour.</p>
    <p>Le système filtre les blogs déjà complètés mais vous avez la possibilité de <a href='/?ENT_action=REPRISE_DATA&tout_voir=Yesman'>tout voir quand même</a>.</p>
    <p>Pour récupérer un site archivé par mégarde, allez voir sur la page de <a href='/?ENT_action=LISTE_ARCHIVAGE' target='_blank'>gestion de l'archivage</a>.</p>";

    $k = 1;
    foreach($liste as $blog) {
        $need_data_completion = false;
        if ($tout_voir_quand_meme) {
            $need_data_completion = true;
        }

        // Récupérer des options du blog
        $blog_details = $wpdb->get_results( "SELECT option_name, option_value ". 
                                            "FROM wp_". $blog['blog_id'] ."_options ".
                                            "where option_name in ('".$opts_str."') order by option_name", ARRAY_A);
        $blog_opts = flatten($blog_details, 'option_name', 'option_value'); 

        $gris_sale = ( $blog['archived'] == 0 ) ? '' : 'gris-sale';

        $form = "<form method='post' action='#".$k."'>
        <input type='hidden' name='ENT_action' value='".$_REQUEST['ENT_action']."'/>
        <input type='hidden' name='action2' value='maj'/>
        <input type='hidden' name='id' value='" . $blog['blog_id'] . "'/>";
    
        $ligne = "<tr class='$gris_sale'>$form";
        $ligne .= "<td><a name='".$k."'></a>".$blog['blog_id']."</td>";
        $ligne .= "<td><a href='http://".$blog['domain']."/' target='_blank'>".$blog['domain']."</a><br/><input type='text' name='blogname' value='".$blog_opts['blogname']."' style='width: 100%; margin: 4px;'></input><br/><input type='text' name='blogdescription' value='".$blog_opts['blogdescription']."' style='width: 100%; margin: 4px;'></input></td>";

        if ($blog['archived'] == 0) {
            $ligne .= "<td><a href='?ENT_action=".$_REQUEST['ENT_action']."&action2=archiveblog&id=".$blog['blog_id']."#".$k."'>Archiver</a></td>";              
        } else {
            $ligne .= "<td>Archivé !&nbsp;&nbsp;&nbsp;<a href='?ENT_action=".$_REQUEST['ENT_action']."&action2=unarchiveblog&id=".$blog['blog_id']."#".$k."'><span class='lilipute'>Désarchiver</span></a></td>";                
        }

        $champ_data = selectbox_type_blog($blog_opts['type_de_blog']);
        $ligne .= "<td>$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "ETB" || $blog_opts['type_de_blog'] == "CLS" || $blog_opts['type_de_blog'] == "GRP" || $blog_opts['type_de_blog'] == "GPL") {
            if ($blog_opts['etablissement_ENT'] == "") {
                $class_warn = "warn";
                $need_data_completion = true;
            }
            $champ_data = "<input type='text' name='uai' value='" . $blog_opts['etablissement_ENT'] . "'/>";
        }
        $ligne .= "<td class='$class_warn $gris_sale'>$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "CLS") {
            if ($blog_opts['classe_ENT'] == "") {
                $class_warn = "warn";
                $need_data_completion = true;
            }            
            $champ_data = "<input type='text' name='clsid' value='". $blog_opts['classe_ENT']. "'/>";
        }
        $ligne .= "<td class='$class_warn $gris_sale'>$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "GRP") {
            if ($blog_opts['groupe_ENT'] == "") {
                $class_warn = "warn";
                $need_data_completion = true;
            }            
            $champ_data = "<input type='text' name='grpid' value='". $blog_opts['groupe_ENT']. "'/>";
        }
        $ligne .= "<td class='$class_warn $gris_sale'>$champ_data</td>";
        
        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "GPL") {
            if ($blog_opts['groupelibre_ENT'] == "") {
                $class_warn = "warn";
                $need_data_completion = true;
            }            
            $champ_data = "<input type='text' name='gplid' value='". $blog_opts['groupelibre_ENT']. "'/>";
        }

        $ligne .= "<td class='$class_warn $gris_sale'>$champ_data</td>";

        $ligne .= "$closeForm</tr>\n";
        // S'il y a eu un formulaire, on affiche.
        if ($need_data_completion) {
            $html .= $ligne;
            $nb_a_reprendre += 1;
            $k += 1;
        }
    }
    echo $headerHtml . "<p><b>Plus que $nb_a_reprendre sur " . count($liste) . " à reprendre !</b></p>" . $html . "</table>\n</div></body></html>";
}


// --------------------------------------------------------------------------------
// renvoie un sélectbox des type de blogs
// --------------------------------------------------------------------------------
function selectbox_type_blog($selectval) {
    switch ($selectval) {
        case 'ETB': $e = "selected"; break;
        case 'CLS': $c = "selected"; break;
        case 'GRP': $g = "selected"; break;
        case 'GPL': $l = "selected"; break;
        case 'ENV': $p = "selected"; break;
        default: break;
    }
    return "
    <select name='type_de_blog'>
    <option value=''>...</option>
    <option value='ETB'$e>ETB</option>
    <option value='CLS'$c>CLS</option>
    <option value='GRP'$g>GRP</option>
    <option value='GPL'$l>GPL</option>
    <option value='ENV'$p>ENV</option>
    </select>";
}

function css() {
    return '
    <style>
/* ==============================================
   FEUILLE DE STYLES DES GABARITS HTML/CSS
   © Elephorm & Alsacreations.com
   Conditions d\'utilisation:
   http://creativecommons.org/licenses/by/2.0/fr/
   ============================================== */


/* --- STYLES DE BASE POUR LE TEXTE ET LES PRINCIPAUX ÉLÉMENTS --- */

/* Page */
html {
    font-size: 100%; /* Voir -> Note 1 à la fin de la feuille de styles. */
}
body {
    margin: 0;
    padding: 10px 20px; /* Note -> 2 */
    font-family: Verdana, "Bitstream Vera Sans", "Lucida Grande", sans-serif; /* 3 */
    font-size: .8em; /* -> 4 */
    line-height: 1.25; /* -> 5 */
    color: black;
    background: white;
}

/* Titres */
h1, h2, h3, h4, h5, h6 {
    margin: 1em 0 .5em 0; /* -> 6 */
}
h1, h2 {
    font-family: Georgia, "Bitstream Vera Serif", Norasi, serif;
    font-weight: normal; /* -> 7 */
}
h1 {
    font-size: 3em; /* -> 8 */
    font-style: italic;
}
h2 {font-size: 1.8em;}
h3 {font-size: 1.2em;}
h4 {font-size: 1em;}

/* Listes */
ul, ol {
    margin: .75em 0 .75em 24px;
    padding: 0; /* -> 9 */
}
ul {
    list-style: square;
}
li {
    margin: 0;
    padding: 0;
}

/* Paragraphes */
p {
    margin: .75em 0;
}
li p, blockquote p {
    margin: .5em 0;
}

/* Citations */
blockquote, q {
    font-size: 1.1em;
    font-style: italic;
    font-family: Georgia, "Bitstream Vera Serif", Norasi, serif;
}
blockquote {
    margin: .75em 0 .75em 24px;
}
cite {
    font-style: italic;
}

/* Liens */
a {
    color: mediumblue;
    text-decoration: underline;
}
a:hover, a:focus {
    color: crimson;
}
a img {
    border: none; /* -> 10 */
}

/* Divers éléments de type en-ligne */
em {
    font-style: italic;
}
strong {
    font-weight: bold;
    color: dimgray;
}


/* --- STYLES POUR CERTAINS CONTENUS DES GABARITS --- */

pre, code {
    font-size: 100%;
    font-family: "Bitstream Vera Mono", "Lucida Console", "Courier New", monospace;
}
pre {
    width: 90%;
    overflow: auto;
    overflow-y: hidden;
    margin: .75em 0;
    padding: 12px;
    background: #eee;
    color: #555;
}
pre strong {
    font-weight: normal;
    color: black;
}
#copyright {
    margin: 20px 0 5px 0;
    text-align: right;
    font-size: .8em;
    color: #848F63;
}
#copyright a {
    color: #848F63;
    text-decoration: none;
}
#copyright a:hover, #copyright a:focus {
    text-decoration: underline;
}


/* --- NOTES ---

1.  Ce "font-size: 100%" est normalement inutile. On l\'utilise uniquement
    pour éviter un bug de redimensionnement du texte dans Internet Explorer.

2.  Par défaut, les navigateurs ont un padding (ou, pour certains, un
    margin) de 6px pour l\'élément BODY. C\'est ce qui évite que le texte
    ne soit complètement collé aux bords de la zone de visualisation du
    navigateur lorsqu\'on affiche une page «brute», sans mise en forme.
    Mais ce retrait de 6px est un peu faiblard: on le renforce donc.
    Notez bien que les feuilles de styles des gabarits pourront augmenter
    ce retrait, ou bien l\'annuler.
    
3.  Voici quelques exemples de collections cohérentes de fontes (propriété
    CSS "font-family"):
    font-family: Arial, Helvetica, "Nimbus Sans L", sans-serif;
    font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
    font-family: Georgia, "Bitstream Vera Serif", Norasi, serif;
    font-family: "Times New Roman", Times, "Nimbus Roman No9 L", serif;
        
4.  Taille du texte de base de la page. Dépend de la taille du texte par
    défaut du navigateur (souvent 16px), et des réglages de l\'utilisateur.
    À adapter en fonction de la fonte choisie, et du rendu souhaité.
    En général, on utilisera une valeur de base entre .65em et 1em
    (ou 65% et 100%).

5.  Hauteur de ligne. À adapter en fonction de la fonte choisie, et des
    besoins particuliers (lignes de texte longues ou courtes, titre ou
    corps de texte...).

6.  En général, les styles par défaut des navigateurs font que les marges
    en haut et en bas des titres sont équivalentes. Ici, en diminuant la
    marge du bas, on cherche à rapprocher le titre du contenu qu\'il introduit.

7.  Les styles par défaut des navigateurs mettent les titres en gras.
    Si on souhaite passer à des caractères «normaux», on doit utiliser
    font-size: normal.

8.  Pour un élément en "font-size: 3em", la taille du texte sera le triple de
    la taille du texte de l\'élément parent.
    À noter: on aurait pu écrire "font-size: 300%" pour le même résultat.

9.  Par défaut, les listes UL et OL ont un retrait à gauche qui peut être,
    suivant les navigateurs:
    - un padding-left de 40px;
    - ou bien un margin-left de 40px.
    On met tout le monde d\'accord avec une marge à gauche de 24px, et pas
    de padding.

10. Les navigateurs donnent souvent aux images placées dans des liens
    une bordure disgracieuse. On annule ce style souvent gênant en appliquant
    un "border: none" aux images qui se trouvent à l\'intérieur d\'un lien.

*/
</style>
    ';
}
