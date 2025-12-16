<?php
/**
 * Script de diagnóstico para el carrier de Shipping Calculator
 * Ejecutar desde: http://tu-tienda.com/modules/shipping_calculator/debug_carrier.php
 */

require_once '../../config/config.inc.php';

echo "<h2>9. Logs Recientes Shipping Calculator (Últimos 50)</h2>";
$logs = Db::getInstance()->executeS("
    SELECT * FROM "._DB_PREFIX_."log 
    WHERE message LIKE '%Shipping%'
    ORDER BY date_add DESC
    LIMIT 100
");
echo "<pre>" . print_r($logs, true) . "</pre>";
