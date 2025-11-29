<?php
require(dirname(__FILE__) . '/config/config.inc.php');
require(dirname(__FILE__) . '/init.php');

echo "<h1>Shipping Config Table Content</h1>";

$sql = "SELECT * FROM " . _DB_PREFIX_ . "shipping_config";
$results = Db::getInstance()->executeS($sql);

if ($results) {
    echo "<table border='1'><tr>";
    foreach (array_keys($results[0]) as $key) {
        echo "<th>$key</th>";
    }
    echo "</tr>";
    foreach ($results as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>$value</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No results found in " . _DB_PREFIX_ . "shipping_config";
}

echo "<h2>Testing Queries</h2>";

// Test Packaging Query
echo "<h3>Packaging Query</h3>";
$packagingSql = "
    SELECT value_number
    FROM " . _DB_PREFIX_ . "shipping_config
    WHERE name = 'Empaque'
      AND (id_carrier = 0 OR id_carrier IS NULL)
";
echo "SQL: $packagingSql<br>";
$packagingRow = Db::getInstance()->getRow($packagingSql);
var_dump($packagingRow);

// Test Insurance Query (Mock)
echo "<h3>Insurance Query (Mock for Carrier ID 1, Weight 10)</h3>";
$id_carrier = 1;
$weight = 10;
$insuranceSql = "
    SELECT value_number
    FROM " . _DB_PREFIX_ . "shipping_config
    WHERE id_carrier = " . (int) $id_carrier . "
      AND name = 'Seguro'
      AND min < " . (float) $weight . "
      AND (max >= " . (float) $weight . " OR max = 0)
";
echo "SQL: $insuranceSql<br>";
$insuranceRow = Db::getInstance()->getRow($insuranceSql);
var_dump($insuranceRow);
