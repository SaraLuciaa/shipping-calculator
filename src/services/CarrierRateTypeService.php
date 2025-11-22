<?php

class CarrierRateTypeService
{
    const RATE_TYPE_RANGE  = 'range';
    const RATE_TYPE_PER_KG = 'per_kg';

    public function setCarrierRateType($id_carrier, $type)
    {
        $id_carrier = (int)$id_carrier;
        if (!in_array($type, [self::RATE_TYPE_RANGE, self::RATE_TYPE_PER_KG])) {
            return false;
        }

        $db = Db::getInstance();

        $id_rate_type = (int)$db->getValue(
            'SELECT id_rate_type FROM `'._DB_PREFIX_.'shipping_rate_type`
             WHERE id_carrier = '.$id_carrier
        );

        if ($id_rate_type) {
            return $db->update(
                'shipping_rate_type',
                ['type' => pSQL($type), 'active' => 1],
                'id_rate_type = '.$id_rate_type
            );
        }

        return $db->insert('shipping_rate_type', [
            'id_carrier' => $id_carrier,
            'type'       => pSQL($type),
            'active'     => 1,
        ]);
    }

    public function getCarrierRateType($id_carrier)
    {
        return Db::getInstance()->getValue(
            'SELECT type FROM `'._DB_PREFIX_.'shipping_rate_type`
             WHERE id_carrier = '.(int)$id_carrier.' AND active = 1'
        );
    }
}