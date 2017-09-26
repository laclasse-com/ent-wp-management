angular.module('blogsApp')
  .config(['$stateProvider', '$urlRouterProvider', 'APP_PATH', function ($stateProvider, $urlRouterProvider, APP_PATH) {
    $stateProvider
      .state('blogs', {
        abstract: true,
        templateUrl: APP_PATH + '/app/views/index.html',
      })

      .state('blogs.home', {
        parent: 'blogs',
        url: '/',
        views: {
          'aside': {
            templateUrl: APP_PATH + '/app/views/asides/aside-home.html',
            controller: 'AsideHomeCtrl'
          },
          'main': {
            templateUrl: APP_PATH + '/app/views/mains/main-home.html',
            controller: 'MainHomeCtrl'
          }
        }
      });

    $urlRouterProvider.otherwise(function ($injector, $location) {
      $location.path("/");
    });
  }])
  .config(['$provide', '$httpProvider', function ($provide, $httpProvider) {
    $provide.factory(
      'MyHttpInterceptor', [function ($q, FlashServiceStyled, $location) {
        return {
          // On request success
          request: function (config) {
            // Return the config or wrap it in a promise if blank.
            return config || $q.when(config);
          },
          // On request failure
          requestError: function (rejection) {
            // Return the promise rejection.
            return $q.reject(rejection);
          },
          // On response success
          response: function (response) {
            // Return the response or promise.
            return response || $q.when(response);
          },
          /*
          * one  place to mangae errors at response failture
          */
          responseError: function (rejection) {
            if (rejection.status === 401)
              location = '/sso/login?ticket=false&service='+encodeURIComponent(location.href);
            else if (rejection.status == 0 || rejection.status == 500) {
              // HJO FIX #105
              // FlashServiceStyled.show('Une erreur s\'est produite : ' + rejection.data["error"], "alert alert-error");
              // $location.path('/admin/error');
            }
            else if (rejection.status== 403) {
              //FlashServiceStyled.show('Vous n\'êtes pas autorisé à faire cette action.', "alert alert-error");
              //$location.path('/admin_etab/not_authorized');
            }
            else if (rejection.status == 400 || rejection.status == 404) {
              //FlashServiceStyled.show('Une erreur s\'est produite : ' + rejection.data["error"], "alert alert-error");
            }
            else
            {
              //FlashServiceStyled.show('Une erreur s\'est produite : ' + rejection.data["error"], "alert alert-error");
            }
            // Return the promise rejection.
            return $q.reject(rejection);
          }
        };
      }]);
    // Add the interceptor to the $httpProvider.
    $httpProvider.interceptors.push('MyHttpInterceptor');
  }]);

