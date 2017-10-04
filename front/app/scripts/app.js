'use strict';

// Declare app level module which depends on filters, and services
angular.module('blogsApp', [
  'ui.router', 
  'ngResource', 
  'ngCookies',
  'ui.bootstrap',
  'ui.sortable',
  'services.messages',
  'growlNotifications',
  'ngSanitize'
])
.run(['$q', '$rootScope', '$location', 'COLOR_DAMIER', 'Blogs', 'CurrentUser', 'Notifications', 'WPApi',
  function($q, $rootScope, $location, COLOR_DAMIER, Blogs, CurrentUser, Notifications, WPApi) {
  Notifications.clear();
  //chargement de l'utilisateur courant pour l'ihm
  CurrentUser.getOfAnnuaire();
  
  $rootScope.$on('$stateChangeStart', function($location) {
    Notifications.clear();
  });
  
  // all user visible blogs  
  $rootScope.allBlogs = [];
  // the user blogs relations
  $rootScope.userBlogs = [];
  // the user subscribed blogs  
  $rootScope.blogs = [];
  // the blogs the user can register (= allBlogs - blogs)
  $rootScope.proposedBlogs = [];
  
  // promise resolved when the WP and the ENT user are known  
  var ready = $q.defer();
  $rootScope.ready = ready.promise;
    
  WPApi.getCurrentUser().then(function (userWp) {
    CurrentUser.get().$promise.then(function (userENT) {
      ready.resolve({ userWp: userWp, userENT: userENT });
    });
  });
    
  window.scope = $rootScope;  
}]);
