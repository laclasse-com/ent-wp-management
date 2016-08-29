'use strict';

/* Controllers */

angular.module('blogsApp')
.controller('ModalAddBlogCtrl', ['$scope', '$modalInstance', 'WPApi', 'Blogs', 'CurrentUser', 'BLOGS_DOMAIN', 'TYPES_BLOG', 
	function($scope, $modalInstance, WPApi, Blogs, CurrentUser, BLOGS_DOMAIN, TYPES_BLOG) {
	//permet d'activer les champs required
	$scope.required = {title: false, type: false, regroupement: false, domain: false};
	// il peut y avoir deux erreurs différentes sur le sous domaine
	// alors on doit pouvoir changer le message
	$scope.errorMsgDomain = ""; //"Le sous-domaine est obligatoire et il doit être en miniscule !";
	//Domaine des blogs qui est une constante
	$scope.domain = BLOGS_DOMAIN;
	//différents model des champs
	$scope.titleBlog = "";
	$scope.subDomain = "";	
	$scope.currentType = {name:"Type de blogs", code: null};
	$scope.currentRegroupement = {id: null, name: null};
	$scope.regroupements = [];

	$scope.droitsCreation = 0;
	var connectedUser = CurrentUser.get();
	connectedUser.$promise.then(function() {
		$scope.droitsCreation = connectedUser.roles_max_priority_etab_actif;
		//les différents type de blogs
		$scope.typesBlog = $scope.rightTypeBlog($scope.droitsCreation);
	});

	// ---------------------------------------------------------------------------
	//renseigne les type de blogs disponible pour le profile utilisateur
	// ---------------------------------------------------------------------------
	$scope.rightTypeBlog = function(droit){
		return _.reject(TYPES_BLOG, function(type){
			if (type.code === TYPES_BLOG[0].code && droit < 2) {
				return true;
			};	
		})
	}

	//fonction qui permet de changer le type courant
	$scope.changeTypeBlogs = function(type){
		$scope.currentType.code = type;
		$scope.currentType.name = Blogs.changeTypeDropdown(type);

		// Gérer les pluriels et les genres
		var genre = "un";
		var partitif = "du ";
		var label = $scope.currentType.name.toLowerCase();

		if ($scope.currentType.name == "Classe") {
			genre = "une";
			partitif = "de la ";
		}

		if ($scope.currentType.name == "Etablissement") {
			label = "établissement";
			partitif = "de l'";
		}

		//on affecte tous ses regroupements correspondant au type.
		$scope.currentRegroupement.name = "Choisissez " + genre + " de vos "+ label.replace(' ', 's ') + "s";
		$scope.regroupements = Blogs.loadRegroupmentsDropdown(type, connectedUser);
		$scope.blogdescription = "site " + partitif + label + " ";
	};

	// ---------------------------------------------------------------------------
	// fonction de proposition de nom de domaine liée à la selectbox des classes/groupes/etab
	// ---------------------------------------------------------------------------
	$scope.changeTypeRegroupement = function(regroupement){
		$scope.currentRegroupement = regroupement;
		proposedSubDomain();
	}

	// ---------------------------------------------------------------------------
	// les fonctions de vérification des champs et leurs régles
	// ---------------------------------------------------------------------------
	$scope.checkTitle = function(){
		if (Blogs.checkField("title", $scope.titleBlog, "")) {
			return !($scope.required.title = false);
		};
		return false;
	}
	// ---------------------------------------------------------------------------
	// Vérifier le type de blog
	// ---------------------------------------------------------------------------
	$scope.checkType = function(){
		if (Blogs.checkField("type", $scope.currentType.code, null)) {
			return !($scope.required.type = false);
		};
		return false;
	}
	// ---------------------------------------------------------------------------
	// Vérifier le regroupement
	// ---------------------------------------------------------------------------
	$scope.checkRegroupement = function(){
		if (Blogs.checkField("regroupement", $scope.currentRegroupement.id, null)) {
			return !($scope.required.regroupement = false);
		};
		return false;
	}
	// ---------------------------------------------------------------------------
	// Vérifier le nom du domaine
	// ---------------------------------------------------------------------------
	$scope.checkSubDomain = function(){
		$scope.required.domain = true;
		$scope.errorMsgDomain = "";
		$scope.existing = false;
		$scope.subDomain = subDomainReplaceWrongChar($scope.subDomain);
		if (Blogs.checkField("subdomain", $scope.subDomain, "")) {
			$scope.required.domain = false;
			$scope.existing = true;
		};
		// tester aussi l'existance dans WP
		if ($scope.subDomain.length > 3) {
			checkSubDomainExistance($scope.subDomain);
		}
	}

	// ---------------------------------------------------------------------------
	// Tester l'existence du sous-domaine sur la plateforme WP
	// ---------------------------------------------------------------------------
	var checkSubDomainExistance = function(name) {	
		WPApi.launchAction( 'BLOG_EXISTE', name )		                
        .then(function(data) {
        	if (data.result == 0) {
				$scope.required.domain = false;
				$scope.errorMsgDomain = "Ce nom de sous-domaine est valide.";
				$scope.existing = false;
			} else {
				$scope.required.domain = true;
				$scope.errorMsgDomain = "Ce nom de sous-domaine n'est pas disponible.";
				$scope.existing = true;
			}

            // return data.result;
        }, function(error) {
			$scope.required.domain = true;
			$scope.errorMsgDomain = "une erreur s'est produite sur la recherche d'existence du sous-domaine.'" + $scope.subDomain + "'.";
			$scope.existing = false;
            console.log( "une erreur s'est produite sur la recherche d'existence du blog.'" + 
							   name + "'. " +  error);
        });	
	}

	// ---------------------------------------------------------------------------
	// fonction permettant de proposer un sous-domaine
	// ---------------------------------------------------------------------------
	var proposedSubDomain = function(){
		$scope.required.domain = false;
		//on propose et verifions si le nom de sous-domaine du nom du regroupement exist
		var tempName = $scope.currentRegroupement.name.toLowerCase();
		tempName = subDomainReplaceWrongChar(tempName);
		// Si c'est une classe, on propose un millésime
		if ($scope.currentType.code != TYPES_BLOG[0].code && $scope.existing) {
			tempName+="-"+schoolYear();
		}
		$scope.subDomain = tempName;
		checkSubDomainExistance(tempName);
	}

	// ---------------------------------------------------------------------------
	// fonction qui remplace tous les caractères faussés par un - pour correspondre a la regex
	// ---------------------------------------------------------------------------
	var subDomainReplaceWrongChar = function(subDomain){
		var subDomainChecked = "";
		_.each(subDomain.trim(), function(c){
			if (!Blogs.checkField("subdomain", c, "")) {
				subDomainChecked += '';
			}else{
				subDomainChecked += c;
			};
		});
		return subDomainChecked;
	}

	// ---------------------------------------------------------------------------
	// fonction qui nous renvoie l'année scolaire
	// ---------------------------------------------------------------------------
	var schoolYear = function(){
		var currentDate = new Date();
		var schoolYear = "";
		if (currentDate.getMonth() > 7) {
			schoolYear = currentDate.getFullYear()+"-"+currentDate.getFullYear()+1;
		} else {
			schoolYear = currentDate.getFullYear()-1+"-"+currentDate.getFullYear();
		};
		return schoolYear;
	}

	// ---------------------------------------------------------------------------
	// fonction ajout ou d'annulation
	// ---------------------------------------------------------------------------
	$scope.addBlog = function () {
		//vérification des champs
		$scope.required.title = !Blogs.checkField("title", $scope.titleBlog, "");
		$scope.required.type = !Blogs.checkField("type", $scope.currentType.code, null);
		$scope.required.regroupement = !Blogs.checkField("regroupement", $scope.currentRegroupement.id, null);
		$scope.errorMsgDomain ="Le sous-domaine est obligatoire et il doit être en miniscule !";
		$scope.required.domain = !Blogs.checkField("subdomain", $scope.subDomain, "");
		//pour pouvoir fermer la modal et créer le blogs, il faut que tous les champs soit correcte
		// tester aussi l'existance dans WP
		if ($scope.subDomain.length > 3) {
			checkSubDomainExistance($scope.subDomain);
		}
		if (!$scope.required.title && !$scope.required.type && !$scope.required.regroupement && !$scope.required.domain) {
			var blog = {
				blog_id: Blogs.tempId(),
				blogname: $scope.titleBlog,
				description: $scope.blogdescription + $scope.currentRegroupement.name,
				type: $scope.currentType.code,
				uai: $scope.currentRegroupement.uai,
				rgptId: $scope.currentRegroupement.id,
				owner: CurrentUser.get().uid,
				action: 'add',
				siteurl: "https://" + $scope.subDomain + "." + BLOGS_DOMAIN + "/",
				domain: $scope.subDomain,
				flux: ""
			};
			Blogs.add(blog);
			$modalInstance.close();   
		};		           			
	};

	// ---------------------------------------------------------------------------
	$scope.cancel = function () {
	  	$modalInstance.close();                
	};
}]);
