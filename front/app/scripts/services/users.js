'use strict';

/* Services */
angular.module('blogsApp')
.factory('UsersApi', ['$resource', 
	function ($resource) {
	  return $resource( '/api/users', {}, {
	    'current': { method:'GET', url: '/api/users/current' }
	  });
}])
.service('CurrentUser', [ 'UsersApi', function(UsersApi) {
	var currentUser = null;
	this.getOfAnnuaire = function() {
		currentUser = UsersApi.current();
	}
	this.get = function() {
		return currentUser;
	}
}]);
