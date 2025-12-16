<?php
/**
 * Actualización 1.1.1
 * Agrega columna max_units_per_package a tabla shipping_product
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_1($module)
{
    // Verificar si la columna ya existe
    $columnExists = Db::getInstance()->executeS("
        SHOW COLUMNS FROM `"._DB_PREFIX_."shipping_product` 
        LIKE 'max_units_per_package'
    ");

    if (empty($columnExists)) {
        // Agregar columna max_units_per_package
        $sql = "ALTER TABLE `"._DB_PREFIX_."shipping_product` 
                ADD COLUMN `max_units_per_package` INT NULL DEFAULT NULL 
                AFTER `is_grouped`";

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }
    }

    return true;
}
