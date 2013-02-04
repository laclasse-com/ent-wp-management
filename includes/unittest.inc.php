<?php
//-----------------------------------------------------------------------------------
// Mini framework de tests unitaires
//-----------------------------------------------------------------------------------
define('SUCCESS','<b><span style="color:green;">OK !</span></b><br/>');
define('ERR','<b><span style="color:red;">KO !</span></b><br/>');

function ok($should="", $expected="", $hadgot=""){
  echo($should.'. Attendu "'. $expected .'", obtenu : "' .$hadgot . '", ' . SUCCESS . "\n");
}

function ko($should="", $expected="", $hadgot=""){
  echo($should.'. Attendu "'. $expected .'", obtenu : "' .$hadgot . '", ' . ERR . "\n");
}

function _hd($title){
  echo "
<!DOCTYPE html><html><head>
<link rel='stylesheet' id='twentytwelve-fonts-css'  href='http://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700&#038;subset=latin,latin-ext' type='text/css' media='all' />
<link rel='stylesheet' id='twentytwelve-style-css'  href='".network_site_url()."wp-content/themes/twentytwelve/style.css?ver=3.5.1' type='text/css' media='all' />
</head><body class='home blog logged-in admin-bar no-customize-support full-width custom-font-enabled single-author'>
<div id='page' class='hfeed site'>".$title ;
}

function _ft(){ echo "</div></body></html>"; }


function startTest($title){
  _hd($title);
}

function endTest(){
  _ft();
}
//-----------------------------------------------------------------------------------

function equal($should, $expected, $hadgot){
  if ($hadgot == $expected) ok($should, $expected, $hadgot);
  else ko($should, $expected, $hadgot);
}

function non_equal($should, $expected, $hadgot){
  if ($hadgot != $expected) ok($should, $expected, $hadgot);
  else ko($should, $expected, $hadgot);
}

function equalType($should, $expected, $hadgot){
  $typeOf = strtoupper(gettype($hadgot));
  $expected = strtoupper($expected);
  if ($typeOf == $expected) ok($should, $expected, $typeOf);
  else ko($should, $expected, $typeOf);
}
//-----------------------------------------------------------------------------------
