'use strict';

angular.module('blogsApp')
    .factory('WPApi',
    ['$http', '$q', 'BLOGS_API_URL', 'CurrentUser',
        function ($http, $q, BLOGS_API_URL, CurrentUser) {
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

                isDomainAvailable: function (domain) {
                    return $http.get(BLOGS_API_URL + 'blogs', { params: { domain: domain }})
                        .then(function (response) {
                            return response.data.length == 0;
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
                }
            };
        },
    ] );
    