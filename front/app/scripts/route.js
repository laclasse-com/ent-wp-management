angular.module('blogsApp')
.config(['$stateProvider', '$urlRouterProvider', 'APP_PATH', function($stateProvider, $urlRouterProvider, APP_PATH) {
  $stateProvider
          .state( 'blogs',{
            abstract:true,
            templateUrl:APP_PATH + '/app/views/index.html',
          })

          .state( 'blogs.home',{
            parent: 'blogs',
            url: '/',
            views: {
              'aside': {
                templateUrl:APP_PATH + '/app/views/asides/aside-home.html',
                controller: 'AsideHomeCtrl'
              },
              'main': {
                templateUrl:APP_PATH + '/app/views/mains/main-home.html',
                controller: 'MainHomeCtrl'
               }
              }
            });

  $urlRouterProvider.otherwise(function ($injector, $location) {
      $location.path("/");
  });
}]);