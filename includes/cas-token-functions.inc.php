<?php
/**********************************************************************************
	Fonctions de manipulation du jeton CAS.
**********************************************************************************/
$casToken = array();

// --------------------------------------------------------------------------------
// Récupérer une données dans le jeton, à défaut en GET
// --------------------------------------------------------------------------------
function getAttr($TokenAttrName, $defaultValue= "") {
  global $casToken;
  return isset($casToken[$TokenAttrName]) ? $casToken[$TokenAttrName] : $defaultValue; 
}

// --------------------------------------------------------------------------------
// setter une données dans la session $_SESSION
// --------------------------------------------------------------------------------
function setAttr($TokenAttrName, $value= "") {
  global $casToken;
  $casToken[$TokenAttrName] = $value;
}

// --------------------------------------------------------------------------------
// test existence attribut
// --------------------------------------------------------------------------------
function existsAttr($TokenAttrName) {
  global $casToken;
  return isset($casToken[$TokenAttrName]);
}

// --------------------------------------------------------------------------------
// tester sur attribut vide
// --------------------------------------------------------------------------------
function emptyAttr($TokenAttrName) {
  global $casToken;
  return ($casToken[$TokenAttrName] == "");
}

// --------------------------------------------------------------------------------
// setter Le jeton reçu de CAS
// --------------------------------------------------------------------------------
function setToken($token) {
  global $casToken;
  $casToken = $token;
}
// --------------------------------------------------------------------------------
// getter sur Le jeton reçu de CAS
// --------------------------------------------------------------------------------
function getToken() {
  global $casToken;
  return $casToken;
}