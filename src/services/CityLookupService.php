<?php

class CityLookupService
{
    public function getIdCityByNameAndState($city, $state)
    {
        $city  = pSQL(trim($city));
        $state = pSQL(trim($state));

        if (!$city) {
            return 0;
        }

        $db = Db::getInstance();

        // 1) Buscar estado exacto
        $id_state = (int)$db->getValue(
            'SELECT id_state FROM `'._DB_PREFIX_.'state`
             WHERE name = "'.$state.'"'
        );

        if ($id_state) {

            // exacto por name
            $id_city = (int)$db->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                 WHERE name = "'.$city.'" AND id_state = '.(int)$id_state
            );
            if ($id_city) return $id_city;

            // exacto por name_alt
            $id_city = (int)$db->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                 WHERE name_alt = "'.$city.'" AND id_state = '.(int)$id_state
            );
            if ($id_city) return $id_city;

            // parcial por name
            $id_city = (int)$db->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                 WHERE name LIKE "%'.$city.'%" AND id_state = '.(int)$id_state
            );
            if ($id_city) return $id_city;

            // parcial por name_alt
            $id_city = (int)$db->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                 WHERE name_alt LIKE "%'.$city.'%" AND id_state = '.(int)$id_state
            );
            if ($id_city) return $id_city;
        }

        // fallback global
        $id_city = (int)$db->getValue(
            'SELECT id_city FROM `'._DB_PREFIX_.'city`
             WHERE name LIKE "%'.$city.'%"'
        );
        if ($id_city) return $id_city;

        $id_city = (int)$db->getValue(
            'SELECT id_city FROM `'._DB_PREFIX_.'city`
             WHERE name_alt LIKE "%'.$city.'%"'
        );
        if ($id_city) return $id_city;

        return 0;
    }
}