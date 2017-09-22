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
    WPApi.createBlog(blog).then(self.loadSubscribeBlogs);
  };

  // ------------------------------------------------------------------------
  // fonction qui supprime un blog
  // ------------------------------------------------------------------------
  this.delete = function(blog) {
    $rootScope.blogs = _.reject($rootScope.blogs, function (b) { return b.id == blog.id});
  }

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
    for (var i=0; i<TYPES_BLOG.length; i++) {
      if (type  == TYPES_BLOG[i].code) {
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
      case TYPES_BLOG[0].code:
        listDetailed = _.uniq(user.user_structures, function(etab) {
          return etab.id;
        });
        _.each(listDetailed, function(etab){
          regroupements.push({id: etab.id, name: etab.name});
        });
        break;
      //
      // Classes
      //
      case TYPES_BLOG[1].code:
        listDetailed = _.uniq(user.user_groups, function(cls){
          return cls.id;
        });
        _.each(listDetailed, function(cls){
          if (cls.type == "CLS") {
            var struct = user.user_structures.filter(function(s) { if (s.id == cls.structure_id) return true; })[0];
            regroupements.push({ uai: cls.structure_id, id: cls.id, name: cls.name + " " + struct.name });
          }
        });
        break;
      //
      // Groupes d'élèves
      //
      case TYPES_BLOG[2].code:
        listDetailed = _.uniq(user.user_groups, function(grp){
          return grp.id;
        });
        _.each(listDetailed, function(grp){
          if (grp.type == "GRP") {
            var struct = user.user_structures.filter(function(s) { if (s.id == grp.structure_id) return true; })[0];
            regroupements.push({ uai: grp.structure_id, id: grp.id, name: grp.name + " " + struct.name });
          }
        });
        break;
      //
      // Groupes libres
      //
      case TYPES_BLOG[3].code:
        listDetailed = _.uniq(user.user_groups, function(gpl){
          return gpl.id;
        });
        _.each(listDetailed, function(gpl){
          if (gpl.type == "GPL") {
            regroupements.push({id: gpl.id, name: gpl.name });
          }
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
      return a.name == b.name ? 0 : ( a.name > b.name ? -1 : 1 );
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
  
  // Load all user visible blogs list  
  this.loadAllBlogs = function () {
    return WPApi.getBlogs()
      // then() called when son gets back
      .then(function (data) {
        $rootScope.allBlogs = self.sortBlogs(data);
        self.updateProposedBlogs();
      }, function (error) {
        // promise rejected, could log the error with: console.log('error', error);
        Notifications.add("une erreur s'est produite sur le chargement de la liste des blogs pouvant vous intéresser.'"
          + data.statusText, "error");
      });
  };
  
  // Load the user subscribed blogs list  
	this.loadSubscribeBlogs = function () {
		return WPApi.getSubscribedBlogs()
      .then(function(data) {
       	// Chargement de la liste
			  $rootScope.blogs = self.attune(self.sortBlogs(data), false);
			  self.updateProposedBlogs();
      }, function(error) {
        Notifications.add( "une erreur s'est produite sur le chargement de la liste des blogs pouvant vous intéresser.'" 
      	  + data.statusText, "error");
      });
  };

}]);
