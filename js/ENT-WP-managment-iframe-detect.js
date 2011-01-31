/*
	Surchagarge des URLs avec le paramtre IFRAME 
	pour contraindre l'affichage du blog en mode intŽgration
*/


// Fonction d'interception des click sur les url pour y rajouter le mode IFRAME.
function addNavig(){
	// pour les urls
	jQuery('#page a').click(function(e) {
		var sUrl = e.target.href;
		var sPageAnchor = "";
		var params = "ENT_action=IFRAME";
		var hookCar = '?';
		e.preventDefault();
		// Voir s'il existe dŽjˆ le paramtre "ENT_action"
		// Si c'est le cas, on ne change rien, sinon l'action est toujours ŽcrasŽe par IFRAME.
		if (sUrl.indexOf("ENT_action") > -1 ) {
			location.href = sUrl;
			return;
		}
		
		// voir ce qu'il faut mettre comme caractre de liaison & ou ?
		if (sUrl.indexOf("?") > -1) hookCar = '&';
		else hookCar = '?';
		// s'il y a un# alors il faut mettre les paramtres avant le #.
		if (e.target.href.indexOf("#") > -1) {
			sUrl = e.target.href.substring(0, e.target.href.indexOf("#"));
			sPageAnchor = e.target.href.substring(e.target.href.indexOf("#"), e.target.href.length);
		}
		//alert(sUrl + hookCar + params + sPageAnchor); return false;
		location.href = sUrl + hookCar + params + sPageAnchor;
	});
}

// Charger la fonction ˆ la fin du chargement du document.
jQuery(document).ready(function() {
	addNavig();
});

