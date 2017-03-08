'use strict';

/* Services */
angular.module('blogsApp')
.service('Blogs', [ '$rootScope', 'CurrentUser', 'Notifications', 'COLOR_DAMIER', 'TYPES_BLOG', 'WPApi',
  function( $rootScope, CurrentUser, Notifications, COLOR_DAMIER, TYPES_BLOG, WPApi) {
  this.idTemp = 0;
  var self = this;

  // ------------------------------------------------------------------------
  // ajoute un blog a la liste
  // ------------------------------------------------------------------------
  this.add = function(blog){
    var added = false;
    var i = 0;
    //on remplace une case vide, s'il y en a une
    while ( !added && i < $rootScope.blogs.length - 1 ){
      if ( $rootScope.blogs[i].blog_id == null ) {
        $rootScope.blogs[i].blog_id = blog.blog_id;
        $rootScope.blogs[i].blogname = blog.blogname;
        $rootScope.blogs[i].description = blog.description;
        $rootScope.blogs[i].type = blog.type;
        $rootScope.blogs[i].rgptId = blog.rgptId;
        $rootScope.blogs[i].owner = blog.owner;
        $rootScope.blogs[i].domain = blog.domain;
        $rootScope.blogs[i].siteurl = blog.siteurl;
        $rootScope.blogs[i].flux = blog.flux;
        $rootScope.blogs[i].active = true;
        added = true;
      };
      i++;
    }
    //si aucune des cases sont vides, on l'ajoute a la suite puis on harmonise la liste
    blog.active = true;
    if (!added) {
      $rootScope.blogs.push(blog);
    }
    //on enregistre la modification
    $rootScope.modifBlogs.push(blog);

    //on remet les case vide s'il le faut
    $rootScope.blogs = this.attune($rootScope.blogs, false);
    // Création dans WordPress s'il ne s'agit pas d'un abonnement mais d'une création de blog.
    if (blog.action != "subscribe") {
      this.saveWP();
    }
    $rootScope.modifBlogs = [];  
  }

  // ------------------------------------------------------------------------
  // fonction qui supprime un blog
  // ------------------------------------------------------------------------
  this.delete = function(blog){
    $rootScope.blogs = _.reject($rootScope.blogs, function (b) { return b.blog_id == blog.blog_id});
  }

  // ------------------------------------------------------------------------
  // retourne l'objet s'il est trouvé
  // ------------------------------------------------------------------------
  this.findBlog = function(blog, liste){
    return _.find(liste, function(b){
      return b.url === blog.siteurl;
    });
  }

  // ------------------------------------------------------------------------
  // remplace un objet dans une des listes des blogs 
  // ------------------------------------------------------------------------
  this.replaceBlog = function(blog, liste){
    var index = _.findIndex(liste, function(b){
      return b.url == blog.siteurl;
    });
    if (index != -1) {
      liste[index] = blog;
    };
  }

  // ------------------------------------------------------------------------
  // Fonction de sauvegarde coté WordPress 
  // FIXME : Voir si les urls de création sont bonnes ? Voir si elle sert encore...
  // ------------------------------------------------------------------------
  this.saveWP = function(){

    _.each($rootScope.modifBlogs, function(blog){
      var qryStr = "&" + blog.type.toLowerCase() + "id=" + blog.rgptId;
      var type = blog.type;
      qryStr += "&blogtype=" + type + "&domain=" + blog.domain + "&blogdescription=" + encodeURI(blog.description);

    WPApi.launchAction( "CREATION_BLOG", encodeURI(blog.blogname) + qryStr )
      .then(function(data) {
          Notifications.add( data.success, "info");
      }, function(error) {
         console.log('error', error.statusText);
          Notifications.add( "une erreur s'est produite à la création du blog.'" 
                    +  error.statusText, "error");
      });
     }); 
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
  // fonction permettant de générer un id temporaire unique non null 
  // ------------------------------------------------------------------------
  this.tempId = function(){
    return "tempId" + this.idTemp++;
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
        listDetailed = _.uniq(user.etablissements, function(etab){
          return etab.code_uai;
        });
        _.each(listDetailed, function(etab){
          regroupements.push({id: etab.code_uai, name: etab.nom});
        });
        break;
      //
      // Classes
      //
      case TYPES_BLOG[1].code:
        listDetailed = _.uniq(user.classes, function(cls){
          return cls.classe_id;
        });
        _.each(listDetailed, function(cls){
          regroupements.push({uai: cls.etablissement_code, id: cls.classe_id, name: cls.classe_libelle + " " + cls.etablissement_nom});
        });
        break;
      //
      // Groupes d'élèves
      //
      case TYPES_BLOG[2].code:
        listDetailed = _.uniq(user.groupes_eleves, function(grp){
          return grp.groupe_id;
        });
        _.each(listDetailed, function(grp){
          regroupements.push({uai: grp.etablissement_code, id: grp.groupe_id, name: grp.groupe_libelle + "  " + grp.etablissement_nom});
        });
        break;
      //
      // Groupes libres
      //
      case TYPES_BLOG[3].code:
        listDetailed = _.uniq(user.groupes_libres, function(gpl){
          return gpl.regroupement_libre_id;
        });
        _.each(listDetailed, function(gpl){
          regroupements.push({id: gpl.regroupement_libre_id, name: gpl.libelle});
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
