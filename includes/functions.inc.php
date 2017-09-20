<?php
// --------------------------------------------------------------------------------
// fonction d'envoie d'un GET HTTP.
// --------------------------------------------------------------------------------
function get_http($url, &$error = null, &$http_status = null){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERPWD, ANNUAIRE_USER . ":" . ANNUAIRE_PASS);

	$data = curl_exec($ch);

	$error = curl_errno($ch);
	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if (curl_errno($ch))
		return curl_error($ch);
	curl_close($ch);
	return $data;
}

