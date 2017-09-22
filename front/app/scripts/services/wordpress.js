'use strict';

angular.module('blogsApp')
    .factory('WPApi',
    ['$http', '$q', 'BLOGS_API_URL', 'CurrentUser', 'WP_PATH', 'WP_BLOG_EXISTS',
        function ($http, $q, BLOGS_API_URL, CurrentUser, WP_PATH, WP_BLOG_EXISTS) {
            return {
                // get the WordPress current user
                getCurrentUser: function () {
                    return $http.get(BLOGS_API_URL + 'users/current')
                        .then(function (response) {
                            if (typeof response.data === 'object') {
                                return response.data;
                            } else {
                                // invalid response
                                return $q.reject(response.data);
                            }
                        }, function (response) {
                            // something went wrong
                            return $q.reject(response.data);
                        });
                },

                // get all blogs visible by the current user or limited to the given ids
                getBlogs: function (ids) {
                    var params = {};
                    // get only a list of ids
                    if (ids != undefined)
                        params['id[]'] = ids;
                    // get all the blogs visible by the current connected user
                    else
                        params.seen_by = CurrentUser.get().id;
                    
                    return $http.get(BLOGS_API_URL + 'blogs', { params: params })
                        .then(function (response) {
                            if (typeof response.data === 'object') {
                                return response.data;
                            } else {
                                // invalid response
                                return $q.reject(response.data);
                            }
                        }, function (response) {
                            // something went wrong
                            return $q.reject(response.data);
                        });
                },

                // Return all the blogs subscribed (or forced) by the current user
                getSubscribedBlogs: function () {
                    var self = this;
                    return this.getCurrentUser().then(function (user) {
                        return $http.get(BLOGS_API_URL + 'users/' + user.id + '/blogs')
                            .then(function (response) {
                                if (typeof response.data === 'object') {
                                    return response.data;
                                } else {
                                    // invalid response
                                    return $q.reject(response.data);
                                }
                            }, function (response) {
                                // something went wrong
                                return $q.reject(response.data);
                            });
                    }).then(function (user_blogs) {
                        var ids = [];
                        var user_blogs_by_id = {};
                        for (var i = 0; i < user_blogs.length; i++) {
                            ids.push(user_blogs[i].blog_id);
                            user_blogs_by_id[user_blogs[i].blog_id] = user_blogs[i];
                        }
                        return self.getBlogs(ids).then(function (blogs) {
                            // set the role and forced attribute for the current user on the blogs
                            for (var i = 0; i < blogs.length; i++) {
                                var blog = blogs[i];
                                if (user_blogs_by_id[blog.id] != undefined) {
                                    blog.forced = user_blogs_by_id[blog.id].forced;
                                    blog.role = user_blogs_by_id[blog.id].role;
                                }
                            }
                            return blogs;
                        });
                    });
                },

                // un-subscribe the current user from the given blog
                unsubscribeBlog: function (blog) {
                    return this.getCurrentUser().then(function (user) {
                        return $http.delete(BLOGS_API_URL + 'blogs/' + blog.id + '/users/' + user.id);
                    });
                },

                // subscribe the current user from the given blog
                subscribeBlog: function (blog) {
                    return this.getCurrentUser().then(function (user) {
                        return $http.post(BLOGS_API_URL + 'blogs/' + blog.id + '/users', { user_id: user.id });
                    });
                },

                // create a new blog
                createBlog: function (blog) {
                    return $http.post(BLOGS_API_URL + 'blogs', blog);
                },

                //
                // Action d'inscription ou de désinscription du d'un blog.
                //
                launchAction: function (action, param1) {
                    console.log("BLOGS_API_URL: " + BLOGS_API_URL);
    			  	var url = WP_PATH; 
                    switch(action) {
                        case 'BLOG_EXISTE':
                            url += WP_BLOG_EXISTS;
                            break;
                    }

                    url = url.replace( /\$1/, param1 );			    
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
    