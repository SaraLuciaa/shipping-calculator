<?php
/**
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

$sql = [];

/**
 * TABLA: shipping_rate_type
 * Define si un carrier usa tarifas POR RANGO o POR KG
 */
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'shipping_rate_type` (
    `id_rate_type` INT(11) NOT NULL AUTO_INCREMENT,
    `id_carrier` INT(11) NOT NULL,
    `type` ENUM("range","per_kg") NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_rate_type`),
    INDEX (`id_carrier`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

/**
 * TABLA: shipping_range_rate
 * Tarifas por rango de peso
 */
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'shipping_range_rate` (
    `id_range_rate` INT(11) NOT NULL AUTO_INCREMENT,
    `id_carrier` INT(11) NOT NULL,
    `id_city` INT(11) NOT NULL,
    `min_weight` DECIMAL(10,2) NOT NULL,
    `max_weight` DECIMAL(10,2) NULL,
    `delivery_time` VARCHAR(20) NULL,
    `apply_packaging` TINYINT(1) NOT NULL DEFAULT 0,
    `apply_massive` TINYINT(1) NOT NULL DEFAULT 0,
    `price` DECIMAL(15,2) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_range_rate`),
    INDEX (`id_carrier`),
    INDEX (`id_city`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

/**
 * TABLA: shipping_per_kg_rate
 * Tarifas por peso exacto (precio por kilo)
 */
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'shipping_per_kg_rate` (
    `id_per_kg_rate` INT(11) NOT NULL AUTO_INCREMENT,
    `id_carrier` INT(11) NOT NULL,
    `id_city` INT(11) NOT NULL,
    `delivery_time` VARCHAR(20) NULL,
    `price` DECIMAL(15,2) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_per_kg_rate`),
    INDEX (`id_carrier`),
    INDEX (`id_city`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

/**
 * TABLA: shipping_product
 * Define si un producto usa agrupación de peso para el cálculo de envío
 */
$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'shipping_product` (
    `id_shipping_product` INT AUTO_INCREMENT PRIMARY KEY,
    `id_product` INT NOT NULL,
    `is_grouped` TINYINT(1) NOT NULL DEFAULT 0,
    `date_add` DATETIME NULL,
    `date_upd` DATETIME NULL,
    UNIQUE KEY (`id_product`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'shipping_config` (
    `id_config` INT(11) NOT NULL AUTO_INCREMENT,
    `id_carrier` INT(11) NULL DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `value_number` DECIMAL(15,3) NULL,    
    `date_add` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id_config`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4;';

/** Ejecutar instalación */
foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;