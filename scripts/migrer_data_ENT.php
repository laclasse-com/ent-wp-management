<?php
/******************************************************************************
	Bibliothèque de migration des données des anciens blogs de l'ENT.
*******************************************************************************/
$pBlogId =  $_GET['pblogid'];
$logOpened = false;
$nbPost = 0;
$nbComm = 0;
$nbPostImported = 0;
$tabArticles = array();


// --------------------------------------------------------------------------------
// A function to take a date in ($date) in specified inbound format (eg mm/dd/yy for 12/08/10) and
// return date in $outFormat (eg yyyymmdd for 20101208)
// --------------------------------------------------------------------------------
function datefmt($date, $inFormat, $outFormat) {
    $order = array('mon' => NULL, 'day' => NULL, 'year' => NULL);
   
    for ($i=0; $i<strlen($inFormat);$i++) {
        switch ($inFormat[$i]) {
            case "m":
                $order['mon'] .= substr($date, $i, 1);
                break;
            case "d":
                $order['day'] .= substr($date, $i, 1);
                break;
            case "y":
                $order['year'] .= substr($date, $i, 1);
                break;
        }
    }
   
    $unixtime = mktime(0, 0, 0, $order['mon'], $order['day'], $order['year']);
    $outDate = date($outFormat, $unixtime);

    if ($outDate == False) {
        return False;
    } else {
        return $outDate;
    }
}

// --------------------------------------------------------------------------------
// fonction création d'un nouvel article
// --------------------------------------------------------------------------------
function creerArticle($titre, $date_creation, $auteur, $contenu, $attachment, $idArticleDansENT) {
	global $nbPostImported;
	global $pBlogId;
	// Voir si l'auteur existe sinon  rattacher à l'utilisateur courant.
	if ( username_exists($auteur) ) {
		// récupération des information de l'utilisateur 
		$userRec = get_user_by('login',$auteur);
		$userId = $userRec->ID;
	}
	else {
		$objUser = wp_get_current_user();
		$userId = $objUser->ID;
	}
		
	// S'occuper de télécharger l'attachement.
	if ($attachment != "") {
		show("Téléchargement du document attaché...");
		$metaJson = get_http($attachment."&metaOnly=O");
		// tableau des méta-données
		$metaArray = json_decode($metaJson, true);
		// contenu du fichier
		//echo("<a href='".$attachment."&metaOnly=N"."'>".$attachment."&metaOnly=N</a>");
		$fichier = get_http($attachment."&metaOnly=N");
		
		// paramètres du repéertoire d'upload WP
		$upload_dir = wp_upload_dir();
		
  		$wp_filetype = wp_check_filetype(basename($metaArray["name"]), null );
  		$attach = array(
     		'post_mime_type' => $metaArray["mime"],
     		'post_title' => preg_replace('/\.[^.]+$/', '', basename($metaArray["name"])),
     		'post_content' => '',
     		'post_status' => 'inherit'
  		);
  		  		
		// mettre un lien vers l'attachement dans le corps du post.
		switch($metaArray["type"]) {
		case 'IMG' : 
  			show("Création du fichier attaché : '".$metaArray["name"]."'.");
			$contenu .= "\n\n<a href='".$upload_dir['url']."/".$metaArray["name"]."'>".
				"<img src='".$upload_dir['url']."/".$metaArray["name"]."' class='alignnone size-full' /></a>";
				// création du fichier image
				$ret = file_put_contents($upload_dir['path']."/".$metaArray["name"], $fichier);
			break;
		case 'URL' :
			show("La ressource jointe est une url : '".$metaArray["localisation"]."'.");
			$contenu .= "\n\n<a href='".$metaArray["localisation"]."' target='_blank'>".$metaArray["localisation"]."</a>";
			// Si c'est une url on ne crée aucun fichier.
			break;
		default : 
			show("Création du fichier attaché : '".$metaArray["name"]."'.");
			$contenu .= "\n\n<a href='".$upload_dir['url']."/".$metaArray["name"]."'>".$metaArray["name"]."</a>";
			// création du fichier doc, audio, vidéo, ...
			$ret = file_put_contents($upload_dir['path']."/".$metaArray["name"], $fichier);
			break;
		}
		//if ($ret != 0) show("Une erreur s'est produite sur la récupération du fichier attaché.", "ERROR");

	}

	// Préparer le post.
	$post = array(
  		'ID' 				=> '0',				//Are you updating an existing post?
  		'post_date' 		=> datefmt($date_creation, 'dd/mm/yy', 'Y-m-d H:i:s'), 	//The time post was made.
  		'comment_status'	=> 'open', 			// 'closed' means no comments.
  		'ping_status' 		=> 'closed',  		// 'closed' means pingbacks or trackbacks turned off
  		'post_author' 		=> $userId, 		//The user ID number of the author.
  		'post_content' 		=> $contenu,		//The full text of the post.
  		'post_status' 		=> 'publish', 		//Set the status of the new post. 
  		'post_title' 		=> $titre, 			//The title of your post.
  		'post_type' 		=> 'post' 			//Sometimes you want to post a page.
	); 
	 
	// inserer du post.
	show("Création de l'article.");
	//$post_id = @wp_insert_post( $post, $wp_error );
	$post_id = wp_insert_post( $post, true );
	error_reporting(E_ALL);
	
	// s'occuper du fichier attaché
	show("Création du fichier attaché.");
	if ($attachment != "") {	
		$attach_id = wp_insert_attachment( $attach, $metaArray["name"], $post_id );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $metaArray["name"] );
		wp_update_attachment_metadata( $attach_id,  $attach_data );
	}
	
	
	//if ($wp_error) show($wp_error->get_error_message());
	//else 
	show("Article récupéré.");
	$nbPostImported++;
	
	// Création des commentaires sur l'article.
	show("Création des commentaires attachés.");
	creerCommentaires($post_id, $userId, $idArticleDansENT);

	//  suppression de l'article dans l'ENT.
	supprimerArticleAncienBlogDansENT($idArticleDansENT);
}

// --------------------------------------------------------------------------------
// fonction de suppression d'un ancien blog dans l'ENT.
// --------------------------------------------------------------------------------
function supprimerArticleAncienBlogDansENT($ancienBlogIENT) {
	global $pBlogId;
	$cle = md5(MD5_SALT.$pBlogId.$ancienBlogIENT);
	show("Suppression de l'article #$ancienBlogIENT dans l'ancien blog.");
	$ret = get_http("http://".SERVEUR_ENT."/pls/education/blogv2.supprimer_ancien_article?pBlogId=".$pBlogId."&pArticleId=".$ancienBlogIENT."&cle=".$cle);
	echo($ret);
}

// --------------------------------------------------------------------------------
// fonction création des commentaires d'un article
// --------------------------------------------------------------------------------
function creerCommentaires($post_id, $userId, $idArticleENT) {
	$t = retriveTabComment($idArticleENT);
		foreach($t as $k => $v){ // pour tous les commentaires 
			if ($v[0] != "") {
				show("Commentaire de l'utilisateur #".$userId.".");
				$data = array(
	    			'comment_post_ID' => $post_id,
   					'comment_author' =>  $v[0],
   	 				'comment_author_email' => 'nobody@mydomain.com',
   	 				//'comment_author_url' => 'http://',
   	 				'comment_content' => $v[2],
   	 				//'comment_type' => ,
  	 				'comment_parent' => 0,
   	 				'user_id' => 1,
   	 				'comment_author_IP' => '127.0.0.1',
   	 				'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
   	 				'comment_date' => datefmt($v[1], 'dd/mm/yy', 'Y-m-d H:i:s'),
   	 				'comment_approved' => 1
				);
			wp_insert_comment($data);
			}
		}
}

// --------------------------------------------------------------------------------
// fonction ecrire tous les articles récupérés
// --------------------------------------------------------------------------------
function ecrire($t) {
	global $domain;
	// se mettre sur le bon blogId de Wordpress.
	$blogId = getBlogIdByDomain($domain);
	if (!$blogId) {
		echo "L'identifiant de '$domain' n'a pas &eacute;t&eacute; trouv&eacute;. Ce blog existe-t-il ?";
		exit;
	}
	else {
		// Se positionner dans le bon blog : 
		switch_to_blog($blogId);
		// Envoyer les articles un par un.
		foreach($t as $k => $v){ // pour tous les articles 
			if ($v[0] != "") {
				echo "<hr><ul>";
				echo("<li style='color:blue;'>article : '".$v[0]."'</li>");
				// Création de l'article
				creerArticle($v[0], $v[1], $v[2], $v[3], $v[4], $v[5]);
				echo("</ul>");
			}
		}
	}
}

// --------------------------------------------------------------------------------
// fonction de logging à l'écran.
// --------------------------------------------------------------------------------
function show($s, $type="NORMAL") {
	global $logOpened;
	$formate = "";
	if ($type == 'ERROR') $formate = " style='color:red;'";
	if (!$logOpened) {
		echo "<ol>";
		$logOpened = true;
	}
	echo "<li$formate>".htmlentities($s, ENT_NOQUOTES, 'UTF-8')."</li>\n";
}

// --------------------------------------------------------------------------------
// fonction de récupération des articles dans un tableau.
// --------------------------------------------------------------------------------
function retriveTabArticle(){
	global $pBlogId;
	global $nbPost;
	$tab = array();
	$tab2 = array();
	show("http://".SERVEUR_ENT."/pls/education/blogv2.exporter_csv_pour_WP?pblogid=".$pBlogId);
	$ret = get_http("http://".SERVEUR_ENT."/pls/education/blogv2.exporter_csv_pour_WP?pblogid=".$pBlogId);
	$tab = explode("@@\n", $ret);
	foreach ($tab as $k => $v) {
		// passer l'entête
		if ($k > 0)	{
			if ($tab2[$k] != "||||") {
				$tab2[$k] = explode("|", $tab[$k]);
				$nbPost++;
			}
		}
	}
	// Un post de trop ??? bug...
	$nbPost--;
	return $tab2;
}

// --------------------------------------------------------------------------------
// fonction de récupération des commentaires dans un tableau.
// --------------------------------------------------------------------------------
function retriveTabComment($idArticleENT){
	global $pBlogId;
	global $nbComm;
	$tab = array();
	$tab2 = array();
	$ret = get_http("http://".SERVEUR_ENT."/pls/education/blogv2.exporter_comments_csv_pour_WP?pblogid=".$pBlogId."&pArticleId=".$idArticleENT);
	$tab = explode("@@\n", $ret);
	foreach ($tab as $k => $v) {
		// passer l'entête
		if ($k > 0)	{
			if (isset($tab2[$k]) && $tab2[$k] != "||||") {
				$tab2[$k] = explode("|", $tab[$k]);
				$nbComm++;
			}
		}
	}
	return $tab2;
}

// --------------------------------------------------------------------------------
// fonction de logging à l'écran.
// --------------------------------------------------------------------------------
function redirectTemporise() {
	global $logOpened;
	echo ("<strong>Dans quelques instants vous serez redirig&eacute; sur votre <a href='http://".$_SERVER['SERVER_NAME']."/'>blog pr&eacute;f&eacute;r&eacute;</a>.</strong>");
	echo "
	<script>
		function benAllezOnYVaAlors() {
			location.href='http://".$_SERVER['SERVER_NAME']."/?ENT_action=IFRAME';
		}
		setTimeout(benAllezOnYVaAlors, 5000);
	</script>
	";
}

// --------------------------------------------------------------------------------
//                                M A I N 
// --------------------------------------------------------------------------------
echo("<html><head><link rel='stylesheet' href='http://".$_SERVER['SERVER_NAME']."/wp-content/themes/headless/style.css' type='text/css' media='screen' />");
echo("</head><body><style>
#content {margin:10px;}
#content ol {text-align:left; list-style:decimal outside right;}
#content ul {text-align:left; list-style:outside right;}
#content hr {width:80%;display:auto;}
</style>");
echo("<div id='page'><div id='content'>");
show("Début de la reprise des données...");
show("Téléchargement des données du blog de laclasse #$pBlogId...");
$tabArticles = retriveTabArticle($pBlogId);
show("Il y a ".$nbPost." article(s) à importer.");
show("Ecriture des articles...");
ecrire($tabArticles);
show("$nbPostImported articles importés.");
show("Fin d'importation des articles.");
show("Fin de la reprise des données.");
// aucun redirect si rien n'a été importé car il peut s'agir d'un erreur et dans cce cas il faut afficher le log
// Il peut aussi s'agir d'un blog vide.


if (($nbPost - $nbPostImported) != 0) die();
else redirectTemporise();
echo "</div></div></body></html>";

?>