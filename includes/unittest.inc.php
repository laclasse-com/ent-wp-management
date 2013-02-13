<?php
//-----------------------------------------------------------------------------------
// Mini framework de tests unitaires
//-----------------------------------------------------------------------------------
define('SUCCESS','<b><span style="color:green;">OK !</span></b><br/>');
define('ERR','<b><span style="color:red;">KO !</span></b><br/>');
$cptOK = 0;
$cptKO = 0;
//$testLog ="";

function ok($should="", $expected="", $hadgot=""){
  global $cptOK;
  echo($should.'. Attendu "'. $expected .'", obtenu : "' .$hadgot . '", ' . SUCCESS . "\n");
  $cptOK++;
}

function ko($should="", $expected="", $hadgot=""){
  global $cptKO;
  echo($should.'. Attendu "'. $expected .'", obtenu : "' .$hadgot . '", ' . ERR . "\n");
  $cptKO++;
}

function _hd($title){
  echo("
<!DOCTYPE html><html><head>
<link rel='stylesheet' id='twentytwelve-fonts-css'  href='http://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700&#038;subset=latin,latin-ext' type='text/css' media='all' />
<link rel='stylesheet' id='twentytwelve-style-css'  href='".network_site_url()."wp-content/themes/twentytwelve/style.css?ver=3.5.1' type='text/css' media='all' />
</head><body class='home blog logged-in admin-bar no-customize-support full-width custom-font-enabled single-author'>
<div id='page' class='hfeed site'>".$title);
}

function result(){
  global $cptOK, $cptKO;
  echo('<style>
  td {border:1px gray solid; padding:10px; margin:10px;}
  </style>');
  echo( "<hr/><table style='border:1px gray solid; padding:10px; margin:10px;'>");
  echo( "<tr><td>".$cptOK." test(s)</td><td>".SUCCESS."</td></tr>");
  echo( "<tr><td>".$cptKO." test(s)</td><td>".ERR."</td></tr>");
  echo( "<tr><td colspan='2'>".round(100*$cptOK/($cptKO+$cptOK), 2)."% passed</td></tr>");
  echo( "</table>");

}

function _ft(){ 
  echo("</div></body></html>"); 
}


function startTest($title){
  _hd($title);
}

function endTest(){
  //global $testLog;
  result();
  _ft();
  //echo $testLog;
}

/*
function echo($str) {
  global $testLog;
  $testLog .= $str."\n";
}
*/
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
