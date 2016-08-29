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
.config(['$httpProvider', function($httpProvider) {
        $httpProvider.defaults.useXDomain = true;
        $httpProvider.defaults.withCredentials = true;
        $httpProvider.defaults.headers.common['Access-Control-Allow-Origin'] = "*";
        delete $httpProvider.defaults.headers.common['X-Requested-With'];
    }
])
.run(['$rootScope', '$location', 'COLOR_DAMIER', 'Blogs', 'CurrentUser', 'Notifications', 'WP_PATH',
  function($rootScope, $location, COLOR_DAMIER, Blogs, CurrentUser, Notifications, WP_PATH) {
  Notifications.clear();
  //chargement de l'utilisateur courant pour l'ihm
  CurrentUser.getOfAnnuaire();
  
  //chargement des listes
  //liste des modifications des blogs
  $rootScope.modifBlogs =[];
  
  $rootScope.$on('$stateChangeStart', function($location){
    Notifications.clear();
    //on met à jour la liste de search blogs
    // Blogs.updateSearchArray();
  });
  window.scope = $rootScope;
}]);
