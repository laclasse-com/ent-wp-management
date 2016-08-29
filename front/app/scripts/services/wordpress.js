'use strict';

angular.module( 'blogsApp' )
.factory('WPApi', 
	[ '$http', '$q', 'WP_PATH', 'WP_SUBSCRIBE', 'WP_UNSUBSCRIBE', 'WP_BLOG_EXISTS', 'WP_BLOG_LIST', 'WP_USER_BLOG_LIST', 'WP_CREATE_BLOG',
	    function( $http, $q, WP_PATH, WP_SUBSCRIBE, WP_UNSUBSCRIBE, WP_BLOG_EXISTS, WP_BLOG_LIST, WP_USER_BLOG_LIST, WP_CREATE_BLOG) {
            return {
                //
                // Action d'inscription ou de désinscription du d'un blog.
                //
                launchAction: function(action, param1) {
    			  	var url = WP_PATH; 
                    switch(action) {
                        case 'INSCRIRE':
                            url += WP_SUBSCRIBE;
                            break;
                        case 'DESINSCRIRE':
                            url += WP_UNSUBSCRIBE;
                            break;
                        case 'BLOG_EXISTE':
                            url += WP_BLOG_EXISTS;
                            break;
                        case 'LISTE_INTERETS':
                            url += WP_BLOG_LIST;
                            break;
                        case 'ABONNEMENTS':
                            url += WP_USER_BLOG_LIST;
                            break;
                        case 'CREATION_BLOG':
                            url += WP_CREATE_BLOG;
                            break;
                        default: 
                            break;
                    } 

                    url = url.replace( /\$1/, param1 );			    
    				console.log(url);
                    // the $http API is based on the deferred/promise APIs exposed by the $q service
                    // so it returns a promise for us by default
                    return $http.get( url )
                        .then(function(response) {
                            if (typeof response.data === 'object') {
                                return response.data;
                            } else {
                                // invalid response
                                return $q.reject(response.data);
                            }

                        }, function(response) {
                            // something went wrong
                            return $q.reject(response.data);
                        });
                },
            };
        },
    ] );
    