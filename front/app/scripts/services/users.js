'use strict';

/* Services */
angular.module('blogsApp')
.factory('UsersApi', ['$resource', 
	function ($resource) {
	  return $resource( '/api/users', {}, {
	    'current': { method:'GET', url: '/api/users/current' }
	  });
		}])
.factory('StructuresApi', ['$resource', 
	function ($resource) {
		return $resource('/api/structures', {}, {
			'search': { method: 'GET', isArray: true }
		});
}])
.factory('GroupsApi', ['$resource', 
	function ($resource) {
		return $resource('/api/groups', {}, {
			'search': { method: 'GET', isArray: true }
		});
}])
.service('CurrentUser', [ 'UsersApi', 'StructuresApi', 'GroupsApi', function(UsersApi, StructuresApi, GroupsApi) {
	var currentUser = null;
	this.getOfAnnuaire = function () {
		currentUser = UsersApi.current();
		currentUser.$promise.then(function (userENT) {
			var groups_ids = [];
			_.each(userENT.groups, function (user_group) {
				groups_ids.push(user_group.group_id);
			});
			groups_ids = _.uniq(groups_ids);

			var struct_ids = [];
			_.each(userENT.profiles, function (user_profile) {
				struct_ids.push(user_profile.structure_id);
			});
			struct_ids = _.uniq(struct_ids);
			// load all the user's structures
			return StructuresApi.search({ expand: false, 'id[]': struct_ids }).$promise.then(function (structs) {
				var user_structures = {};
				_.each(structs, function (struc) {
					user_structures[struc.id] = struc;
				});
				userENT.user_structures = user_structures;

				return GroupsApi.search({ expand: false, 'id[]': groups_ids }).$promise.then(function (groups) {
					var user_groups = {};
					_.each(groups, function (group) {
						user_groups[group.id] = group;
					});
					userENT.user_groups = user_groups;
					console.log(userENT);
					return userENT;
				});
			});
		});
		return currentUser;
	}
	this.get = function() {
		return currentUser;
	}
}]);
