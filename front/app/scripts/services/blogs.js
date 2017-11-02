'use strict';

/* Services */
angular.module('blogsApp')
.service('Blogs', [ '$rootScope', 'Notifications', 'COLOR_DAMIER', 'TYPES_BLOG', 'WPApi',
  function( $rootScope, Notifications, COLOR_DAMIER, TYPES_BLOG, WPApi) {
  this.idTemp = 0;
  var self = this;

  // ------------------------------------------------------------------------
  // crée un nouveau blog
  // ------------------------------------------------------------------------
  this.create = function (blog) {
    WPApi.createBlog(blog).then(function () {
      self.loadSubscribeBlogs();
      self.loadAllBlogs();
    });
  };

  // ------------------------------------------------------------------------
  // harmonise la liste des blogs sur 15 cases puisqu'une est fixe.
  // ------------------------------------------------------------------------
  this.attune = function(liste) {
    var i = 0;
    _.each(liste, function(item){
      item.color = COLOR_DAMIER[i%COLOR_DAMIER.length];
      i++;
    });
    while(i < 16 || i % 4 != 0) {
      liste.push({
        id: null,
        name: null,
        description: null,
        type: null,
        url: null,
        domain: null,
        color: COLOR_DAMIER[i%COLOR_DAMIER.length]
      });
      i++;
    };
    return liste;
  }

  // ------------------------------------------------------------------------
  // Fonction de gestion de la sélectbox "type de blog"
  // ------------------------------------------------------------------------
  this.changeTypeDropdown = function(type){
    if (type == "none") {
      return "Type de blogs"; 
    }
    for (var i = 0; i < TYPES_BLOG.length; i++) {
      if (type == TYPES_BLOG[i].code) {
        return TYPES_BLOG[i].name;
      }
    } 
  };

  // ------------------------------------------------------------------------
  // Fonction de chargement de la dropdown des regroupement 
  // en fonction du type de blog choisi
  // ------------------------------------------------------------------------
  this.loadRegroupmentsDropdown = function(type, user){
    var listDetailed = null;
    var regroupements = [];
    switch(type){
      //
      // Etablissements
      //
      case 'ETB':
        _.each(user.profiles, function (user_profile) {
          // only allow ADM or DIR to create a structure's blog
          if (_.indexOf(['ADM', 'DIR'], user_profile.type) == -1)
            return;
          var struct_name = user_profile.structure_id;
          if (user.user_structures[user_profile.structure_id] != undefined)
            struct_name = user.user_structures[user_profile.structure_id].name;
          if (_.findIndex(regroupements, function (struc) { return struc.id == user_profile.structure_id; }) == -1)
            regroupements.push({ id: user_profile.structure_id, name: struct_name, structure_id: user_profile.structure_id })
        });
        break;
      //
      // Groupes libres, Groupes d'élèves et Classes
      //
      case 'GPL':
      case 'GRP':
      case 'CLS':
        _.each(user.groups, function (user_group) {
          if (_.indexOf(['ADM', 'PRI', 'ENS'], user_group.type) == -1)  
            return;
          if (user.user_groups[user_group.group_id] == undefined)
            return;
          if (user.user_groups[user_group.group_id].type != type)
            return;  
          var group_name = user.user_groups[user_group.group_id].name;
          var structure_id = user.user_groups[user_group.group_id].structure_id;
          if (_.findIndex(regroupements, function (group) { return group.id == user_group.group_id; }) == -1)
            regroupements.push({ id: user_group.group_id, name: group_name, group_id: user_group.group_id, structure_id: structure_id });
        });
        break;
    }
    return _.sortBy(regroupements, function(item){ return item.name; });
  };

  // ------------------------------------------------------------------------
  // Fonction de vérification de valeurs de champs
  // ------------------------------------------------------------------------
  this.checkField = function(fieldType, fieldValue, condition){
    var regexSubDomain = new RegExp(/^([0-9]|[a-z]|[-])*$/);
    switch(fieldType){
      case "title":
      case "type":
      case "regroupement":
        return fieldValue != condition;
      case "subdomain":
        return regexSubDomain.exec(fieldValue) != null && fieldValue != condition;
    }
    return false;
  };
  
  this.unsubscribe = function (blog) {
    WPApi.unsubscribeBlog(blog).then(self.loadSubscribeBlogs);
  };
    
  this.subscribe = function (blog) {
    WPApi.subscribeBlog(blog).then(self.loadSubscribeBlogs);
  };

  // Return a sorted blogs list  
  this.sortBlogs = function (blogs) {
    return blogs.sort(function (a, b) {
      var typePriorities = {
        ETB: 0,
        CLS: 1,
        GRP: 2,
        GPL: 3,
        ENV: 4
      };
      if (a.forced != b.forced)
        return a.forced ? -1 : 1;
      if (a.type != b.type)
        return typePriorities[a.type] - typePriorities[b.type];
      return a.name == b.name ? 0 : ( a.name < b.name ? -1 : 1 );
    });
  };
  
  // Update the proposed blog list  
  this.updateProposedBlogs = function () {
    var proposedBlogs = [];
    for (var i = 0; i < $rootScope.allBlogs.length; i++) {
      var blog = $rootScope.allBlogs[i];
      var found = false;
      for (var i2 = 0; !found && (i2 < $rootScope.blogs.length); i2++) {
        found = $rootScope.blogs[i2].id == blog.id;
      }
      if (!found)
        proposedBlogs.push(blog);
    }
    $rootScope.proposedBlogs = self.sortBlogs(proposedBlogs);
  };
  
  this.updateUserBlogs = function () {
    var userBlogs = [];
    for (var i = 0; i < $rootScope.userBlogs.length; i++) {
      var user_blog = $rootScope.userBlogs[i];
      for (var i2 = 0; i2 < $rootScope.allBlogs.length; i2++) {
        var blog = $rootScope.allBlogs[i2];
        if (user_blog.blog_id == blog.id) {
          blog.forced = user_blog.forced;
          blog.role = user_blog.role;
          userBlogs.push(blog);
          break;
        }
      }
    }
    // Chargement de la liste
    $rootScope.blogs = self.attune(self.sortBlogs(userBlogs), false);
  };
  
  // Load all user visible blogs list  
  this.loadAllBlogs = function () {
    return WPApi.getBlogs()
      // then() called when son gets back
      .then(function (data) {
        $rootScope.allBlogs = self.sortBlogs(data);
        self.updateUserBlogs();
        self.updateProposedBlogs();
      }, function (error) {
        // promise rejected, could log the error with: console.log('error', error);
        Notifications.add("une erreur s'est produite sur le chargement de la liste des blogs pouvant vous intéresser.'"
          + data.statusText, "error");
      });
  };
  
  // Load the user subscribed blogs list  
  this.loadSubscribeBlogs = function () {
    return WPApi.getCurrentUserBlogs().then(function (user_blogs) {
      $rootScope.userBlogs = user_blogs;
      self.updateUserBlogs();
      self.updateProposedBlogs();
    });
  };

}]);
