'use strict';

/* Controllers */

angular.module('blogsApp')
.controller('AsideHomeCtrl', ['$scope', '$rootScope', 'Blogs', 'BLOGS_DOMAIN', 'APP_PATH', 'Notifications', 'WPApi', 'CurrentUser', 'Modal',
	function($scope, $rootScope, Blogs, BLOGS_DOMAIN, APP_PATH, Notifications, WPApi, CurrentUser, Modal) { 

	var connectedUser = CurrentUser.get();
	connectedUser.$promise.then(function() {
		console.log(connectedUser);
		//affiche le bouton modification s'il a les droits
		$scope.canCreateNewBlog = connectedUser.roles_max_priority_etab_actif > 0;

	    WPApi.launchAction("LISTE_INTERETS", connectedUser.login)
	        // then() called when son gets back
	        .then(function(data) {
	            $rootScope.proposedBlogs = data;
	        }, function(error) {
	            // promise rejected, could log the error with: console.log('error', error);
	            console.log('error', error);
	            Notifications.add( "une erreur s'est produite sur le chargement de la liste des blogs pouvant vous intéresser.'" 
	            					+  data.statusText, "error");
        	});
    });
	//
	// affiche le nom complet du type de blog par rapport à son code
	//
	$scope.nameType = function(type){
		return Blogs.changeTypeDropdown(type);
	}

	//
	// Supprimer un blog de la liste des propositions
	//
	$scope.deleteBlogFromInterestList = function(blog) {
		//on enleve le blog proposé puisqu'on l'a ajouté
		$rootScope.proposedBlogs = _.reject($rootScope.proposedBlogs, function(item){ return item.blog_id === blog.blog_id;	});
	}

	//
	// ajoute le blogs dans la liste de ses blogs
	//
	$scope.addBlog = function(blog, idx){
		// Gestion d'erreur
		if (blog.blogname == undefined || blog.type_de_blog == undefined ) {
			Notifications.add("Erreur ! Ce blog ne semble pas valide.", "error");
		} else {
			//on peut ajouter un blog seulement si nous sommes pas en mode modification.
			if ( !$rootScope.modification ) {
				blog.action = 'subscribe'

	            WPApi.launchAction("INSCRIRE", blog.domain.replace("." + BLOGS_DOMAIN, ""))
	                // then() called when son gets back
	                .then(function(data) {
	                    // promise fulfilled
	                    if (data.success != undefined && data.success != "") {
	                        Notifications.add(data.success, "info");
	                        Blogs.add(blog);
	                    } else {
	                        Notifications.add(data.error, "error");
	                    }
	                }, function(error) {
	                    // promise rejected, could log the error with: console.log('error', error);
	                    console.log('error', error);
	                    Notifications.add( "une erreur s'est produite sur l'ajout du blog '" + 
	        							   blog.name + "'." +  response.statusText, "error");
	                });
			};
			$scope.deleteBlogFromInterestList(blog);
		}
	}

	//
	//fonction qui ouvre la modal de création d'un nouveau blog.
	//
	$scope.addNewBlog = function() {
		Modal.open('ModalAddBlogCtrl', APP_PATH+'/app/views/modals/add-blog.html', 'md');
	}

}]);
