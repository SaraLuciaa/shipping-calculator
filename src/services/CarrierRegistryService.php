<?php

class CarrierRegistryService
{

    public static function getAllRegistered()
    {
        return Db::getInstance()->executeS(
            'SELECT crt.id_carrier, c.name, crt.type
            FROM '._DB_PREFIX_.'shipping_rate_type crt
            JOIN '._DB_PREFIX_.'carrier c ON crt.id_carrier = c.id_carrier
            WHERE crt.active = 1'
        );
    }

    public static function isRegistered($id_carrier)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT id_rate_type FROM '._DB_PREFIX_.'shipping_rate_type
            WHERE id_carrier = '.(int)$id_carrier.' AND active = 1'
        );
    }

    public static function getType($id_carrier)
    {
        return Db::getInstance()->getValue(
            'SELECT type FROM '._DB_PREFIX_.'shipping_rate_type
            WHERE id_carrier = '.(int)$id_carrier.' AND active = 1'
        );
    }

    public static function registerCarrier($id_carrier, $type)
    {
        // Validación de tipo
        if (!in_array($type, ['range', 'per_kg'])) {
            return false;
        }

        // Verificar si ya existe
        if (self::isRegistered($id_carrier)) {
            return false;
        }

        return Db::getInstance()->insert('shipping_rate_type', [
            'id_carrier' => (int) $id_carrier,
            'type'       => pSQL($type),
            'active'     => 1,
        ]);
    }
}