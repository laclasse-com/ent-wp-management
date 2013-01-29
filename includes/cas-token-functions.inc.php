<?php
/**********************************************************************************
	Fonctions de manipulation du jeton CAS.
**********************************************************************************/
$casToken = "";

// --------------------------------------------------------------------------------
// Récupérer une données dan le jeton, à défaut en GET
// --------------------------------------------------------------------------------
function getAttr($TokenAttrName, $defaultValue= "") {
  global $casToken;
  return isset($casToken[$TokenAttrName]) ? $casToken[$TokenAttrName] : $defaultValue; 
}

// --------------------------------------------------------------------------------
// setter une données dan la session $_SESSION
// --------------------------------------------------------------------------------
function setAttr($TokenAttrName, $value= "") {
  $_SESSION['phpCAS']['attributes'][$TokenAttrName] = $value; 
}

// --------------------------------------------------------------------------------
// test existence attribut
// --------------------------------------------------------------------------------
function existsAttr($TokenAttrName) {
  return isset($_SESSION['phpCAS']['attributes'][$TokenAttrName]);
}

// --------------------------------------------------------------------------------
// tester sur attribut vide
// --------------------------------------------------------------------------------
function emptyAttr($TokenAttrName) {
  return ($_SESSION['phpCAS']['attributes'][$TokenAttrName] == "");
}

// --------------------------------------------------------------------------------
// setter Le jeton reçu de CAS
// --------------------------------------------------------------------------------
function setToken() {
  global $casToken;
  $casToken = isset($_SESSION['phpCAS']['attributes']) ? $_SESSION['phpCAS']['attributes'] : "";

}