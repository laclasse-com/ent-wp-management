'use strict';

/* Services */
angular.module('blogsApp')
.factory('UsersApi', ['$resource', 'APP_PATH', 'WP_CURRENT_USER', 
	function( $resource, APP_PATH, WP_CURRENT_USER ) {
	  return $resource( WP_CURRENT_USER, {}, {
	    'current':   {method:'GET', url: WP_CURRENT_USER }
	  });
}])
.service('CurrentUser', [ 'UsersApi', function( UsersApi ) {
	var currentUser = null;
	this.getOfAnnuaire = function(){
		currentUser = UsersApi.current();
	}
	this.get = function(){
		return currentUser;
	}
}]);
