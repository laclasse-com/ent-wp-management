/*******************************
function detailEtab
*******************************/
function detailEtab(oLien, sRne) {
	var sUrl = "http://www.laclasse.com/pls/public/!ajax_server.service?serviceName=serviceListeEtab&p_str="+sRne;
	
	var a_cree= document.createElement("iframe");
	a_cree.id = sRne;
	a_cree.src = sUrl;
	a_cree.style.position = 'absolute';
	a_cree.style.top = '5px';
	a_cree.style.right = '45px';
	a_cree.style.fontSize  = '14px';
	a_cree.style.color  = 'black';
	a_cree.style.backgroundColor  = 'white';
	a_cree.style.padding  = '7px';
	
	oLien.appendChild(a_cree);
}
