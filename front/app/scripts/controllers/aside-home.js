'use strict';

/* Controllers */

angular.module('blogsApp')
.controller('AsideHomeCtrl', ['$scope', '$rootScope', 'Blogs', 'BLOGS_DOMAIN', 'APP_PATH', 'Notifications', 'WPApi', 'CurrentUser', 'Modal',
	function($scope, $rootScope, Blogs, BLOGS_DOMAIN, APP_PATH, Notifications, WPApi, CurrentUser, Modal) { 

	var connectedUser = CurrentUser.get();
	connectedUser.$promise.then(function() {
		// affiche le bouton modification s'il a les droits
		var canCreateNewBlog = false;
		for (var i = 0; i < connectedUser.profiles.length; i++) {
			var profile = connectedUser.profiles[i];
			if ((profile.type !== 'TUT') && (profile.type !== 'ELV'))
				canCreateNewBlog = true;
		}
		$scope.canCreateNewBlog = canCreateNewBlog;

		Blogs.loadAllBlogs();
    });
	//
	// affiche le nom complet du type de blog par rapport à son code
	//
	$scope.nameType = function (type) {
		return Blogs.changeTypeDropdown(type);
	};

	//
	// ajoute le blogs dans la liste de ses blogs
	//
	$scope.addBlog = function (blog, idx) {
		Blogs.subscribe(blog);
	};

	//
	//fonction qui ouvre la modal de création d'un nouveau blog.
	//
	$scope.addNewBlog = function () {
		Modal.open('ModalAddBlogCtrl', APP_PATH + '/app/views/modals/add-blog.html', 'md');
	};

}]);
