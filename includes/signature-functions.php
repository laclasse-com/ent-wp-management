<?php
// --------------------------------------------------------------------------------
//
// Fonctions de signature pour s'adresser à l'annuaire.
//
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------------
// Construire une jolie url à signer.
// --------------------------------------------------------------------------------
function build_canonical($url, $params) {
  $canonical_string = $url."?";
  $query_string = build_querystring($params);
  $canonical_string.= $query_string;
  // 3. ajout du timestamp
  $timestamp = date("Y-m-d\TH:i:s");
  $canonical_string .= ";".$timestamp;
  //4. Ajout de l'identifiant d'application (connu de l'annuaire, et qui lu permet de comprendre la signature)
  $canonical_string .= ";".ANNUAIRE_APP_ID; 
  return $canonical_string;
}


// --------------------------------------------------------------------------------
// Générer la query string
// --------------------------------------------------------------------------------
function build_querystring($params) {
  $query_string = "";
  // 1. trier les paramètres
  ksort($params);
  // 2. construction de la canonical string
  foreach ($params as $k => $v) $query_string .= $k."=".urlencode ($v)."&";
  $query_string = trim($query_string, "&");
  return $query_string;
}

// --------------------------------------------------------------------------------
// Générer une signature
// --------------------------------------------------------------------------------
function sign($canonical_string) {
  return urlencode(base64_encode(hash_hmac('sha1', $canonical_string, ANNUAIRE_API_KEY, true)));
}

// --------------------------------------------------------------------------------
// Génerer une url signée
// --------------------------------------------------------------------------------
function generate_url($url, $params) {
  $canonical_string = build_canonical($url, $params);
  $query_string = build_querystring($params);
  // 5. Calcul de la signature : sha1 et Encodage Base64
  $signature = "signature=".sign($canonical_string);
  $timestamp = date("Y-m-d\TH:i:s");
  // Renvoie de la requete constituée
  return $url . "?" .  $query_string . ";app_id=" . ANNUAIRE_APP_ID . ";timestamp=" . urlencode($timestamp) . ";" . $signature;
}

// --------------------------------------------------------------------------------
// Vérifier une url signée en recalculant la signature
// --------------------------------------------------------------------------------
function verify_signature($received_signature, $url, $params){
  $canonical_string = build_canonical($url, $params);
  return $received_signature == sign($canonical_string);
}
