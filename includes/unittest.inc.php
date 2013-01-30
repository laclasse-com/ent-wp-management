<?php
//-----------------------------------------------------------------------------------
// Mini framework de tests unitaires
//-----------------------------------------------------------------------------------
define('SUCCESS','<b><span style="color:green;">OK !</span></b><br/>');
define('ERR','<b><span style="color:red;">KO !</span></b><br/>');

function ok($should, $expected, $hadgot){
  echo($should.' &eacute;gal &agrave; "'. $expected .'", obtenu : "' .$hadgot . '", ' . SUCCESS );
}

function ko($should, $expected, $hadgot){
  echo($should.' &eacute;gal &agrave; "'. $expected .'", obtenu : "' .$hadgot . '", ' . ERR );
}

function equal($should, $expected, $hadgot){
  if ($hadgot == $expected) ok($should, $expected, $hadgot);
  else ko($should, $expected, $hadgot);
}

function equalType($should, $expected, $hadgot){
  $typeOf = strtoupper(gettype($hadgot));
  $expected = strtoupper($expected);
  if ($typeOf == $expected) ok($should, $expected, $typeOf);
  else ko($should, $expected, $typeOf);
}

//-----------------------------------------------------------------------------------
