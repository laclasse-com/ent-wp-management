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
.run(['$rootScope', '$location', 'COLOR_DAMIER', 'Blogs', 'CurrentUser', 'Notifications', 'WP_PATH',
  function($rootScope, $location, COLOR_DAMIER, Blogs, CurrentUser, Notifications, WP_PATH) {
  Notifications.clear();
  //chargement de l'utilisateur courant pour l'ihm
  CurrentUser.getOfAnnuaire();
  
  $rootScope.$on('$stateChangeStart', function($location) {
    Notifications.clear();
  });
  
  // all user visible blogs  
  $rootScope.allBlogs = [];
  // the user subscribed blogs  
  $rootScope.blogs = [];  
  // the blogs the user can register (= allBlogs - blogs)
  $rootScope.proposedBlogs = [];     
    
  window.scope = $rootScope;
}]);
