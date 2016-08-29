'use strict';

/* Services */
angular.module('blogsApp')
.service('Modal', ['$modal', 'APP_PATH', function($modal, APP_PATH) {
  //ouverture d'une modal personnalisable'
  this.open = function (controller, template, size) {

    var modalInstance = $modal.open({
      templateUrl: template,
      controller: controller,
      size: size
    });

    modalInstance.result.then(function () {
    });
  };
}]);

/************************************************************************************/
/*                   Resource to show flash messages and responses                  */
/************************************************************************************/
angular.module('services.messages', []); 
angular.module('services.messages').factory("Notifications", ['$rootScope', function($rootScope) {
  var indexSuccess = 0;
  var indexError = 0;
  var indexWarning = 0;
  var indexInfo = 0;
  $rootScope.notifications = {success: {}, error: {}, warning: {}, info: {}};
  return {
    add: function(message, classe) {
      /* classe success, error, warning, info*/
      switch(classe){
        case "success":
          $rootScope.notifications.success[indexSuccess++] = message;
          break;
        case "error":
          $rootScope.notifications.error[indexError++] = message;
          break;
        case "warning":
          $rootScope.notifications.warning[indexWarning++] = message;
          break;
        case "info":
          $rootScope.notifications.info[indexInfo++] = message;
          break;
      }
    },
    clear: function() {
      $rootScope.notifications = {success: {}, error: {}, warning: {}, info: {}};
    }
  }
}]);