'use strict';

/* Controllers */

angular.module('blogsApp')
.controller('MainHomeCtrl', ['$scope', '$state', '$rootScope', '$window',  'APP_PATH', 'BLOGS_DOMAIN', 'Blogs', 'Modal', 'CurrentUser', 'Notifications', 'WPApi',
	function($scope, $state, $rootScope, $window, APP_PATH, BLOGS_DOMAIN, Blogs, Modal, CurrentUser, Notifications, WPApi) {

	//
	// fonction permettant d'ouvrir un blog dans un nouvel onglet
	//
	$scope.goBlog = function(blog) {
		$window.open(blog.url, '_blank');
	};

	//
	// -------------- Controllers Modal des blogs --------------- //
	// controleur de la modale de confirmation de désincription
	$scope.confirmUnsubscribeModalCtrl = ["$scope", "$modalInstance", "Blogs", function($scope, $modalInstance, Blogs){
		$scope.title = "Désinscription du blog";
		$scope.message = "Etes vous sûr de vouloir vous désinscrire de ce blog ?"
		$scope.no = function(){
			$modalInstance.close();
		}

		$scope.ok = function(){
			var b = $rootScope.blog;
			if (b.id != undefined)
				Blogs.unsubscribe(b);
			$modalInstance.close();
		}
	}];
	// ----------------------------------------------------- //


	//
	//fonction permettant la désinscription à un blog
	//
	$scope.unsubscribeLink = function(blog) {
		//pour les methodes du controller de la modal
		$rootScope.blog = blog;
		//appel de la modal de confirmation personnalisable	(controller, template, size)	
		Modal.open($scope.confirmUnsubscribeModalCtrl, APP_PATH+'/app/views/modals/confirm.html', 'md');
	};

	var connectedUser;

	connectedUser = CurrentUser.get();

	connectedUser.$promise.then(Blogs.loadSubscribeBlogs());
}]);
