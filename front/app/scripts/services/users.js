'use strict';

/* Services */
angular.module('blogsApp')
.factory('UsersApi', ['$resource', 'APP_PATH', function( $resource, APP_PATH ) {
  return $resource( APP_PATH + '/api/users/', {}, {
    'current':   {method:'GET', url: APP_PATH + '/api/users/current'}
  });
}])
.service('CurrentUser', [ 'UsersApi', function( UsersApi ) {
	var currentUser = null;
	this.getOfAnnuaire = function(){
		currentUser = UsersApi.current();
		// console.log(currentUser);
	}
	this.get = function(){
		return currentUser;
	}
}]);