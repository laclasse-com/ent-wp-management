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
    $opts = Array('admin_email','siteurl','name','blogdescription','blogtype','etablissement_ENT','display_name', 'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT');
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

    // fais_voir($uid_ent_WP, "uid_ent_WP=");
    // fais_voir($profil_ent_WP, "profil_ent_WP=");
    // fais_voir($uai_user_WP, "uai_user_WP=");
    // fais_voir($classe_user_WP, "classe_user_WP=");

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
}

// --------------------------------------------------------------------------------
// fonction de listage de tous les blogs de l'utilisateur
// --------------------------------------------------------------------------------
function userBlogList($username) {
    global $wpdb;
    $user_id = username_exists($username);
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
                // case 'idBLogENT':
                //     $blog->idBLogENT = $opt->option_value;
                    break;
                case 'etablissement_ENT':
                    $blog->etablissement_ENT = $opt->option_value;
                    break;
                case 'classe_ENT':
                    $blog->classe_ENT = $opt->option_value;
                    break;
                case 'groupe_ENT':
                    $blog->groupe_ENT = $opt->option_value;
                    break;
                case 'groupelibre_ENT':
                    $blog->groupelibre_ENT = $opt->option_value;
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
    $opts = Array('admin_email','siteurl','name','blogdescription','blogtype','etablissement_ENT','display_name', 'type_de_blog', 'classe_ENT', 'groupe_ENT', 'groupelibre_ENT');
    $opts_str = implode("','", $opts);
    $closeForm = "&nbsp;<button type='submit'>Ok</button></form>";
    $message = "";

    // Quelques vérifications d'usage pour controler les résultats de l'extraction
    $current_user = get_user_by('login',phpCAS::getUser());
    // Vérifier si l'utilisateur est bien connecté
    assert ('$current_user->ID  != ""', "L'utilisateur n'est pas connecté sur la plateforme WordPress de laclasse.com.");
    // Récupération des champs meta de l'utilisateur 
    $userMeta = get_user_meta($current_user->ID);
    assert ('$userMeta[\'profil_ENT\'][0] != ""', "Cet utilisateur n'a pas de profil sur la plateforme WordPress de laclasse.com.");
    // Caractéristiques du blog.
    $uid_ent_WP =  $userMeta['uid_ENT'][0];
    $userENT =json_decode(get_http(generate_url(ANNUAIRE_URL."api/app/users/$uid_ent_WP", Array("expand" => "true"))));
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
            $message = "<div class='msg'>Blog #$id : Etablissement mis &agrave; jour. uai=".$_REQUEST['uai']."</div>";
            // switch_to_blog( $id );
            // $wpdb->replace( "wp_".$id."_options", array('option_name' => 'etablissement_ENT', 'option_value' => $_REQUEST['uai']));
            update_blog_option( $id, 'etablissement_ENT', $_REQUEST['uai'] );
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
    }

    // Extraction bdd
    global $wpdb;
    $query = "";
    $liste = $wpdb->get_results( "SELECT blog_id, domain, archived FROM $wpdb->blogs WHERE domain != '".BLOG_DOMAINE."'   order by domain", ARRAY_A );

    $html = "<html><head><title>Liste des sites à reprendre</title>
    <style>
          table td {padding:3px 20px 3px 20px;}
          table td {border:black solid 1px;}
          .gris-sale {background-color:#aaa;}
          .warn {background-color:orange;}
          .lilipute {font-size:0.6em;}
          .msg {border:green solid 1px; float:right; margin-right:20%;background-color:lightgreen;padding:4px;}
    </style>\n</head><body><div style='margin:40px;'><h1>Liste des sites &agrave; reprendre</h1>\n
    $message
    <table><tr><th>nom</th><th>url</th><th>Archivage</th><th>type_de_blog</th><th>UAI</th><th>classe_ENT</th><th>groupe_ENT</th><th>groupelibre_ENT</th></tr>\n";
    $html .= "<p>Affectation d'un id de classe, de groupe d'élèves, de groupe libre ou d'établissement. Pour chaque blog, les <span class='warn'> zones en orange</span> sont à mettre à jour.</p>";
    foreach($liste as $k => $blog) {
        // Récupérer des options du blog
        $blog_details = $wpdb->get_results( "SELECT option_name, option_value ". 
                                            "FROM wp_". $blog['blog_id'] ."_options ".
                                            "where option_name in ('".$opts_str."') order by option_name", ARRAY_A);
        $blog_opts = flatten($blog_details, 'option_name', 'option_value'); 

        $gris_sale = ( $blog['archived'] == 0 ) ? '' : 'gris-sale';

        $form = "<form method='post'>
        <input type='hidden' name='ENT_action' value='".$_REQUEST['ENT_action']."'/>
        <input type='hidden' name='action2' value='maj'/>
        <input type='hidden' name='id' value='" . $blog['blog_id'] . "'/>";
    
        $html .= "<tr class='$gris_sale'>";
        $html .= "<td><a name='".($k+1)."'></a>".($k+1)."</td>";
        $html .= "<td><a href='http://".$blog['domain']."/' target='_blank'>".$blog['domain']."</a><br/> ".$blog_opts['blogdescription']."</td>";
        if ($blog['archived'] == 0) {
            $html .= "<td><a href='?ENT_action=".$_REQUEST['ENT_action']."&action2=archiveblog&id=".$blog['blog_id']."#".($k+1)."'>Archiver</a></td>";              
        } else {
            $html .= "<td>Archivé !&nbsp;&nbsp;&nbsp;<a href='?ENT_action=".$_REQUEST['ENT_action']."&action2=unarchiveblog&id=".$blog['blog_id']."#".($k+1)."'><span class='lilipute'>Désarchiver</span></a></td>";                
        }
        $html .= "<td>". $blog_opts['type_de_blog']. "</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "ETB" || $blog_opts['type_de_blog'] == "CLS" || $blog_opts['type_de_blog'] == "GRP") {
            if ($blog_opts['etablissement_ENT'] == "") {
                $class_warn = "warn";
            }            
            $champ_data = "$form" . selectbox_etabs() . "$closeForm";
        }
        $html .= "<td class='$class_warn'>". $blog_opts['etablissement_ENT']. "$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "CLS") {
            if ($blog_opts['classe_ENT'] == "") {
                $class_warn = "warn";
            }            
            $champ_data = "$form<input type='text' name='clsid'/>$closeForm";
        }
        $html .= "<td class='$class_warn'>". $blog_opts['classe_ENT']. "$champ_data</td>";

        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "GRP") {
            if ($blog_opts['groupe_ENT'] == "") {
                $class_warn = "warn";
            }            
            $champ_data = "$form<input type='text' name='grpid'/>$closeForm";
        }
        $html .= "<td class='$class_warn'>". $blog_opts['groupe_ENT']. "$champ_data</td>";
        
        $class_warn = "";
        $champ_data = "";
        if ($blog_opts['type_de_blog'] == "ENV") {
            if ($blog_opts['groupelibre_ENT'] == "") {
                $class_warn = "warn";
            }            
            $champ_data = "$form<input type='text' name='gplid'/>$closeForm";
        }

        $html .= "<td class='$class_warn'>". $blog_opts['groupelibre_ENT']. "$champ_data</td>";

        $html .= "</tr>\n";
    }
    $html .= "</table>\n</div></body></html>";
    echo $html;

}

// --------------------------------------------------------------------------------
// renvoie un sélectbox des établissements présents dans la v3 au déploiement
// --------------------------------------------------------------------------------
function selectbox_etabs(){
return "
<select name='uai'>
<option value=''>...</option>
<option value='0692157V'> - Collège Georges Brassens</option>
<option value='0693890D'>BRINDAS  - CLG-GEORGES CHARPAK </option>
<option value='0693834T'>BRON - CLG-THEODORE MONOD</option>
<option value='0692576A'>BRON - COLLEGE PABLO PICASSO </option>
<option value='0691831R'>BRON - E.S.E.M. ECOLE SPECIALISEE DES ENFANTS MALADES</option>
<option value='0690455V'>BRON - ECOLE MATERNELLE SAINT EXUPERY</option>
<option value='0693212S'>BRON - ECOLE PRIMAIRE JEAN MOULIN</option>
<option value='0691225G'>BRON - ECOLE PRIMAIRE SAINT EXUPERY</option>
<option value='0691479H'>BRON CEDEX - CLG-JOLIOT CURIE</option>
<option value='0690839M'>CAILLOUX SUR FONTAINES - ECOLE PRIMAIRE</option>
<option value='0691728D'>CALUIRE ET CUIRE - CLG-ANDRE LASSAGNE</option>
<option value='0692165D'>CALUIRE ET CUIRE - CLGH-ELIE VIGNAL</option>
<option value='0692410V'>CALUIRE ET CUIRE - COLLEGE CHARLES SENARD</option>
<option value='0690468J'>CALUIRE ET CUIRE - ECOLE PRIMAIRE JEAN JAURES</option>
<option value='0693017E'>CALUIRE ET CUIRE - ECOLE PRIMAIRE P. ET M. CURIE </option>
<option value='0692414Z'>CHAMPAGNE AU MONT D OR - COLLEGE JEAN-PHILIPPE RAMEAU</option>
<option value='0692849X'>CHARLY - ECOLE MATERNELLE LES ECUREUILS</option>
<option value='0692860J'>CHARLY - ECOLE PRIMAIRE LES TILLEULS </option>
<option value='0691614E'>CHASSIEU - COLLEGE LEONARD VINCI </option>
<option value='0693975W'>CHATILLON  - CLG-SIMONE VEIL </option>
<option value='0692898A'>CORBAS - CLG-RENE CASSIN </option>
<option value='0692422H'>CRAPONNE - CLG-JEAN ROSTAND</option>
<option value='0691495A'>DECINES CHARPIEU - COLLEGE MARYSE BASTIE </option>
<option value='0691830P'>ECULLY - C.E.M. HENRY GORMAND</option>
<option value='0691481K'>ECULLY - CLG-LAURENT MOURGUET</option>
<option value='0692520P'>FEYZIN - CLG-FREDERIC MISTRAL</option>
<option value='0692848W'>FEYZIN - ECOLE MATERNELLE DU PLATEAU </option>
<option value='0691588B'>FEYZIN - ECOLE PRIMAIRE DU PLATEAU </option>
<option value='0691736M'>FONTAINES SUR SAONE  - COLLEGE JEAN DE TOURNES </option>
<option value='0693513U'>FONTAINES SUR SAONE  - ECOLE PRIMAIRE CENTRE </option>
<option value='0691074T'>FONTAINES SUR SAONE  - ECOLE PRIMAIRE LES MARRONNIERS</option>
<option value='0694151M'>FRANCHEVILLE - CLG-CHRISTIANE BERNARDIN</option>
<option value='0693117N'>FRANCHEVILLE - ECOLE PRIMAIRE LE CHATER</option>
<option value='0693331W'>GENAS  - CLG-LOUIS LEPRINCE RINGUET</option>
<option value='0691483M'>GIVORS - CLG-LUCIE AUBRAC</option>
<option value='0692583H'>GIVORS - COLLEGE BANS</option>
<option value='0692419E'>GRIGNY - CLG-EMILE MALFROY </option>
<option value='0691824H'>IRIGNY - CLG-DAISY GEORGES MARTIN</option>
<option value='0690022Z'>LAMURE SUR AZERGUES  - CLG-DE LA HAUTE AZERGUES</option>
<option value='069BACAS'>Lyon - Bac à Sable </option>
<option value='0692933N'>LYON - CLG PR-CHARLES DE FOUCAULD</option>
<option value='0691663H'>LYON - CLG-BELLECOMBE</option>
<option value='0692339T'>LYON - CLG-GEORGES CLEMENCEAU</option>
<option value='0694007F'>LYON - CLG-GILBERT DRU </option>
<option value='0692340U'>LYON - CLG-HENRI LONGCHAMBON </option>
<option value='0691670R'>LYON - CLG-JEAN CHARCOT</option>
<option value='0692703N'>LYON - CLG-JEAN DE VERRAZANE </option>
<option value='0690060R'>LYON - CLG-JEAN MERMOZ </option>
<option value='0692334M'>LYON - CLG-JEAN MONNET </option>
<option value='0692698H'>LYON - CLG-JEAN PERRIN </option>
<option value='0694191F'>LYON - CLG-LA TOURETTE </option>
<option value='0690053H'>LYON - CLG-PROFESSEUR DARGENT</option>
<option value='0692338S'>LYON - CLG-VENDOME </option>
<option value='0691669P'>LYON - CLG-VICTOR GRIGNARD </option>
<option value='0690036P'>LYON - CLG-VICTOR SCHOELCHER </option>
<option value='069DANEZ'>Lyon - DANE</option>
<option value='0692928H'>LYON 1ER ARRONDISSEMENT  - COLLEGE PRIVE DES CHARTREUX </option>
<option value='0691300N'>LYON 1ER ARRONDISSEMENT  - ECOLE PRIMAIRE D'APPLICATION VICTOR HUGO</option>
<option value='0693827K'>LYON 1ER ARRONDISSEMENT  - ECOLE PRIMAIRE DES TABLES CLAUDIENNES </option>
<option value='0692932M'>LYON 2EME ARRONDISSEMENT - COLLEGE PRIVE CHEVREUL</option>
<option value='0691067K'>LYON 2EME ARRONDISSEMENT - ECOLE MATERNELLE ALIX </option>
<option value='0692695E'>LYON 3EME ARRONDISSEMENT - COLLEGE LACASSAGNE</option>
<option value='0692411W'>LYON 3EME ARRONDISSEMENT - COLLEGE MOLIERE </option>
<option value='0692263K'>LYON 3EME ARRONDISSEMENT - ECOLE PRIMAIRE ANATOLE FRANCE </option>
<option value='0693707E'>LYON 3EME ARRONDISSEMENT - ECOLE PRIMAIRE PAUL BERT</option>
<option value='0691662G'>LYON 4EME ARRONDISSEMENT - COLLEGE CLEMENT MAROT </option>
<option value='0690245S'>LYON 4EME ARRONDISSEMENT - CRDP de Lyon</option>
<option value='0690834G'>LYON 4EME ARRONDISSEMENT - ECOLE PRIMAIRE D'APPLICATION JOSEPH CORNIER </option>
<option value='0693836V'>LYON 4EME ARRONDISSEMENT - ECOLE PRIMAIRE PUBLIQUE LA FONTAINE </option>
<option value='0691798E'>LYON 5EME ARRONDISSEMENT - COLLEGE LES BATTIERES </option>
<option value='0692920Z'>LYON 5EME ARRONDISSEMENT - COLLEGE PRIVE AUX LAZARISTES</option>
<option value='0692921A'>LYON 5EME ARRONDISSEMENT - COLLEGE PRIVE SAINTE MARIE</option>
<option value='0691038D'>LYON 5EME ARRONDISSEMENT - ECOLE MATERNELLE JEAN GERSON</option>
<option value='0691311A'>LYON 5EME ARRONDISSEMENT - ECOLE PRIMAIRE FULCHIRON</option>
<option value='0691959E'>LYON 5EME ARRONDISSEMENT - ECOLE PRIMAIRE PRIVEE SAINT-JUST / SAINT-IRENEE </option>
<option value='0690604G'>LYON 6EME ARRONDISSEMENT - COLLEGE PRIVE NOTRE-DAME DE BELLECOMBE</option>
<option value='0693126Y'>LYON 6EME ARRONDISSEMENT - ECOLE PRIMAIRE ANTOINE REMOND </option>
<option value='0690431U'>LYON 7EME ARRONDISSEMENT - ECOLE ELEMENTAIRE CAVENNE </option>
<option value='0694189D'>LYON 7EME ARRONDISSEMENT - ECOLE PRIMAIRE JULIE-VICTOIRE DAUBIE</option>
<option value='0692940W'>LYON 8EME ARRONDISSEMENT - COLLEGE PRIVE PIERRE TERMIER</option>
<option value='0693907X'>LYON 8EME ARRONDISSEMENT - ECOLE PRIMAIRE PUBLIQUE LOUIS PASTEUR </option>
<option value='0692309K'>LYON 8EME ARRONDISSEMENT - FONDATION RICHARD </option>
<option value='0692285J'>LYON 9EME ARRONDISSEMENT - ECOLE ELEMENTAIRE ALPHONSE DAUDET </option>
<option value='0690409V'>LYON 9EME ARRONDISSEMENT - ECOLE ELEMENTAIRE AUDREY HEPBURN</option>
<option value='0693097S'>LYON 9EME ARRONDISSEMENT - ECOLE MATERNELLE FREDERIC MISTRAL </option>
<option value='0692693C'>LYON CEDEX 02  - CLG-AMPERE</option>
<option value='0690131T'>LYON CEDEX 03  - CLG-RAOUL DUFY</option>
<option value='0692937T'>LYON CEDEX 05  - CLG PR-LA FAVORITE SAINTE THERESE </option>
<option value='0692696F'>LYON CEDEX 05  - CLG-JEAN MOULIN </option>
<option value='0693479G'>LYON CEDEX 07  - CLG-CITE SCOLAIRE INTERNATIONALE</option>
<option value='0692578C'>LYON CEDEX 07  - CLG-GABRIEL ROSSET</option>
<option value='0691668N'>MEYZIEU  - CLG-LES SERVIZIERES </option>
<option value='0692704P'>MEYZIEU  - CLG-OLIVIER DE SERRES </option>
<option value='0691571H'>MEYZIEU  - ECOLE PRIMAIRE CONDORCET</option>
<option value='0691563Z'>MEYZIEU  - ECOLE PRIMAIRE LES CALABRES </option>
<option value='0692335N'>MEYZIEU CEDEX  - CLG-EVARISTE GALOIS </option>
<option value='0692579D'>MIONS  - COLLEGE MARTIN LUTHER KING</option>
<option value='0691629W'>MONTANAY - ECOLE ELEMENTAIRE </option>
<option value='0693254M'>MONTANAY - ECOLE MATERNELLE</option>
<option value='0692346A'>MORNANT  - CLG-PIERRE DE RONSARD </option>
<option value='0692423J'>NEUVILLE SUR SAONE - COLLEGE JEAN RENOIR </option>
<option value='0692941X'>NEUVILLE SUR SAONE - COLLEGE PRIVE NOTRE-DAME DE BELLEGARDE</option>
<option value='0690852B'>NEUVILLE SUR SAONE - ECOLE ELEMENTAIRE LUCIE GUIMET</option>
<option value='0693896K'>NEUVILLE SUR SAONE - ECOLE PRIMAIRE BONY AVENTURIERE </option>
<option value='0691780K'>NEUVILLE SUR SAONE - SEGPA CLG JEAN RENOIR </option>
<option value='0691673U'>OULLINS  - CLG-LA CLAVELIERE </option>
<option value='0690075G'>OULLINS  - CLG-PIERRE BROSSOLETTE</option>
<option value='0692900C'>OULLINS  - ECOLE ELEMENTAIRE DU GOLF </option>
<option value='0693712K'>OULLINS  - ECOLE PRIMAIRE JULES FERRY</option>
<option value='0690076H'>PIERRE BENITE  - CLG-MARCEL PAGNOL </option>
<option value='0692603E'>PIERRE BENITE  - ECOLE MATERNELLE JEAN LURCAT</option>
<option value='0691707F'>PIERRE BENITE  - ECOLE MATERNELLE PABLO PICASSO</option>
<option value='0690853C'>POLEYMIEUX AU MONT D OR  - ECOLE PRIMAIRE A.M. AMPERE</option>
<option value='0690855E'>QUINCIEUX  - ECOLE ELEMENTAIRE MARIUS GROS </option>
<option value='0691498D'>RILLIEUX LA PAPE - CLG-MARIA CASARES </option>
<option value='0692159X'>RILLIEUX LA PAPE - COLLEGE PAUL EMILE VICTOR </option>
<option value='0691620L'>RILLIEUX LA PAPE - ECOLE MATERNELLE CITE CASTELLANE</option>
<option value='0692300A'>RILLIEUX LA PAPE - ECOLE MATERNELLE ET ELEMENTAIRE VANCIA</option>
<option value='0693755G'>RILLIEUX LA PAPE - ECOLE MATERNELLE LES ALLAGNIERS </option>
<option value='0693754F'>RILLIEUX LA PAPE - ECOLE MATERNELLE LES CHARMILLES </option>
<option value='0691622N'>RILLIEUX LA PAPE - ECOLE PRIMAIRE CASTELLANE </option>
<option value='0690856F'>ROCHETAILLEE SUR SAONE - ECOLE PRIMAIRE JEAN RAINE </option>
<option value='0693423W'>SATHONAY CAMP  - ECOLE PRIMAIRE JOSEPH THEVENOT</option>
<option value='0691643L'>SATHONAY VILLAGE - ECOLE PRIMAIRE DANIS - LES GRAINS DE BLE</option>
<option value='0691977Z'>ST DIDIER AU MONT D OR - ECOLE PRIMAIRE PRIVEE SAINT CHARLES SAINT FRANCOIS D'ASSISE </option>
<option value='0692342W'>ST FONS  - CLG-ALAIN </option>
<option value='0690478V'>ST FONS  - ECOLE MATERNELLE PARMENTIER </option>
<option value='0693629V'>ST FONS  - ECOLE PRIMAIRE JULES VALLES </option>
<option value='0694190E'>ST FONS  - ECOLE PRIMAIRE SALVADOR ALLENDE </option>
<option value='0696962G'>ST FONS  - ECOLE PRIMAIRE SIMONE DE BEAUVOIR </option>
<option value='0692521R'>ST GENIS LAVAL - CLG-JEAN GIONO</option>
<option value='0693287Y'>ST GENIS LAVAL - CLG-PAUL D'AUBAREDE </option>
<option value='0693562X'>ST GENIS LAVAL - ECOLE ELEMENTAIRE ALBERT MOUTON </option>
<option value='0690614T'>ST LAURENT DE CHAMOUSSET - CLG PR-SAINT LAURENT</option>
<option value='0691497C'>ST PRIEST  - CLG-COLETTE </option>
<option value='0692160Y'>ST PRIEST  - CLG-GERARD PHILIPE</option>
<option value='0692417C'>ST PRIEST  - COLLEGE BORIS VIAN</option>
<option value='0693532P'>ST PRIEST  - ECOLE PRIMAIRE DE REVAISON</option>
<option value='0690860K'>ST ROMAIN AU MONT D OR - ECOLE PRIMAIRE</option>
<option value='0690078K'>STE FOY L ARGENTIERE - CLG-VAL D'ARGENT</option>
<option value='0691484N'>STE FOY LES LYON - CLG-LE PLAN DU LOUP </option>
<option value='0693894H'>STE FOY LES LYON - ECOLE PRIMAIRE DU CENTRE</option>
<option value='0692943Z'>TASSIN LA DEMI LUNE  - COLLEGE PRIVE SAINT JOSEPH</option>
<option value='0692163B'>TASSIN LA DEMI LUNE CEDEX  - CLG-JEAN-JACQUES ROUSSEAU </option>
<option value='0691666L'>VAULX EN VELIN - CLG-AIME CESAIRE</option>
<option value='0692336P'>VAULX EN VELIN - COLLEGE HENRI BARBUSSE</option>
<option value='0690249W'>VAULX EN VELIN - COLLEGE PIERRE VALDO</option>
<option value='0691480J'>VENISSIEUX - COLLEGE HONORE DE BALZAC</option>
<option value='0691799F'>VENISSIEUX - COLLEGE LOUIS ARAGON</option>
<option value='0690551Z'>VENISSIEUX - COLLEGE PRIVE LA XAVIERE</option>
<option value='0692303D'>VENISSIEUX - ECOLE PRIMAIRE B LOUIS PERGAUD</option>
<option value='0693852M'>VENISSIEUX - ECOLE PRIMAIRE CHARLES PERRAULT </option>
<option value='0693514V'>VENISSIEUX - ECOLE PRIMAIRE DU CENTRE</option>
<option value='0693326R'>VENISSIEUX - ECOLE PRIMAIRE LEO LAGRANGE </option>
<option value='0692343X'>VENISSIEUX CEDEX - CLG-ELSA TRIOLET</option>
<option value='0690094C'>VENISSIEUX CEDEX - CLG-JULES MICHELET</option>
<option value='0691645N'>VILLEFRANCHE SUR SAONE - CLG-FAUBERT </option>
<option value='0691664J'>VILLEURBANNE - CLG-JEAN JAURES </option>
<option value='0691478G'>VILLEURBANNE - CLG-JEAN MACE </option>
<option value='0692337R'>VILLEURBANNE - CLG-LAMARTINE </option>
<option value='0690280E'>VILLEURBANNE - CLG-LES IRIS</option>
<option value='0693491V'>VILLEURBANNE - COLLEGE PRIVE BETH MENAHEM</option>
<option value='0692945B'>VILLEURBANNE - COLLEGE PRIVE IMMACULEE CONCEPTION</option>
<option value='0690626F'>VILLEURBANNE - COLLEGE PRIVE MERE TERESA </option>
<option value='0693724Y'>VILLEURBANNE - ECOLE ELEMENTAIRE ANATOLE FRANCE</option>
<option value='0693042G'>VILLEURBANNE - ECOLE ELEMENTAIRE LOUIS PASTEUR </option>
<option value='0691213U'>VILLEURBANNE - ECOLE MATERNELLE EDOUARD HERRIOT</option>
<option value='0692155T'>VILLEURBANNE CEDEX - CLG-DES GRATTE-CIEL MORICE LEROUX </option>
<option value='0693093M'>VILLEURBANNE CEDEX - CLG-DU TONKIN </option>
<option value='0691675W'>VILLEURBANNE CEDEX - CLG-LOUIS JOUVET</option>
<option value='0692390Y'>VILLEURBANNE CEDEX - EREA-CITE SCOLAIRE RENE PELLET</option>
</select>
";    
}
