'use strict';

/* Services */
angular.module('blogsApp')
.service('Blogs', [ '$rootScope', 'CurrentUser', 'Notifications', 'COLOR_DAMIER', 'TYPES_BLOG', 'WPApi',
  function( $rootScope, CurrentUser, Notifications, COLOR_DAMIER, TYPES_BLOG, WPApi) {
  this.idTemp = 0;
  var self = this;

  // ------------------------------------------------------------------------
  // crée un nouveau blog
  // ------------------------------------------------------------------------
  this.create = function(blog) {
    var qryStr = "&" + blog.type.toLowerCase() + "id=" + blog.rgptId;
    var type = blog.type;
    qryStr += "&blogtype=" + type + "&domain=" + blog.domain + "&blogdescription=" + encodeURI(blog.description);
    if (blog.uai)
      qryStr += "&etbid=" + blog.uai;

    return WPApi.launchAction( "CREATION_BLOG", encodeURI(blog.blogname) + qryStr )
    .then(function(data) {
      // recharge la liste des blogs de l'utilisateur
      $rootScope.loadSubscribeBlogs();
      Notifications.add( data.success, "info");
    }, function(error) {
      console.log('error', error.statusText);
      Notifications.add( "une erreur s'est produite à la création du blog.'" 
                  +  error.statusText, "error");
    });
  };

  // ------------------------------------------------------------------------
  // fonction qui supprime un blog
  // ------------------------------------------------------------------------
  this.delete = function(blog){
    $rootScope.blogs = _.reject($rootScope.blogs, function (b) { return b.blog_id == blog.blog_id});
  }

  // ------------------------------------------------------------------------
  // harmonise la liste des blogs sur 15 cases puisqu'une est fixe.
  // ------------------------------------------------------------------------
  this.attune = function( liste ){
    var i = 1;
    _.each(liste, function(item){
      item.color = COLOR_DAMIER[i%COLOR_DAMIER.length];
      i++;
    });
    var i = liste.length;
    while( i < 16 || i % 4 != 0 ){
      liste.push({
        id: null,
        blogname: null,
        description: null,
        type: null,
        siteurl: null,
        domain: null,
        flux: null,
        color: COLOR_DAMIER[i%COLOR_DAMIER.length],
        active:  true
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
}]);
