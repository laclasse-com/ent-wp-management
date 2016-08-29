'use strict';

angular.module('blogsApp')
//grille de couleur du damier
.constant('COLOR_DAMIER', ["bleu", "rouge", "vert", "jaune", "violet", "jaune", "bleu", "violet", "rouge", "bleu", "vert", "jaune", "vert", "violet", "rouge", "bleu"])
//les différents types de blog
.constant('TYPES_BLOG', [
	{
		name: "Etablissement",
		code: "ETB"
	},
	{
		name: "Classe",
		code: "CLS"
	},
	{
		name: "Groupe d'élèves",
		code: "GRP"
	},
	{
		name: "Groupe libre",
		code: "GPL"
	}
]);