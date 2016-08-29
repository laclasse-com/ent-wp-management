'use strict';

/* Directive */
angular.module('blogsApp')
.directive('disableAutoClose', [ function() {
	// directive for disabling the default
	// close on 'click' behavior
	return {
	  link: function($scope, $element) {
	    $element.on('click', function($event) {
        $event.stopPropagation();
	    });
	  }
	};
}]);
