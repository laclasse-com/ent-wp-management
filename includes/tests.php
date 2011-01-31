<?php
require_once("/var/www/wordpress/wp-config.php");
require_once("/var/www/wordpress/wp-includes/wp-db.php");


echo "<h1>voir la table wp_signups</h1>";
global $wpdb;

$res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->signups where active = '0' order by 1")  );

//print_r($res);

echo "<table>";
foreach ($res as $idx => $rec) {
	echo "<tr>";
	$t = (array)$rec;
	if ($idx == 0) {
		foreach ($t as $colName => $val) echo "<th>".$colName."</th>";
		echo "</tr><tr>";
		}
	foreach ($t as $colName => $val) {
		echo "<td>".$val."</td>";
	}
	echo "</tr>\n";
}
echo "</table>";

$wpdb->query("DELETE FROM $wpdb->signups WHERE active = '0'");


?>