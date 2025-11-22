<?php
/**
* Shipping Calculator – Versión corregida
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Shipping_calculator extends CarrierModule
{
    protected $config_form = false;

    const RATE_TYPE_RANGE  = 'range';
    const RATE_TYPE_PER_KG = 'per_kg';

    public function __construct()
    {
        $this->name = 'shipping_calculator';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Sara Lucia';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Shipping Calculator');
        $this->description = $this->l('Integra y automatiza el cálculo de costos de envío de forma precisa, transparente y optimizada');

        $this->confirmUninstall = $this->l('¿Estas seguro(a) que deseas desinstalar el módulo Shipping Calculator?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '9.0');
    }

    /**
     * Install
     */
    public function install()
    {
        if (!extension_loaded('curl')) {
            $this->_errors[] = $this->l('Debes habilitar cURL en tu servidor');
            return false;
        }

        if (!parent::install()) {
            return false;
        }

        if (!include dirname(__FILE__).'/sql/install.php') {
            return false;
        }

        Configuration::updateValue('SHIPPING_CALCULATOR_LIVE_MODE', false);

        return
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('updateCarrier') &&
            $this->registerHook('actionCarrierProcess');
    }

    public function uninstall()
    {
        Configuration::deleteByName('SHIPPING_CALCULATOR_LIVE_MODE');
        
        include dirname(__FILE__).'/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Backoffice
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitShipping_calculatorModule')) {
            $this->postProcess();
            $output .= $this->displayConfirmation($this->l('Configuración actualizada'));
        }

        // === Import per KG ===
        if (Tools::isSubmit('submitImportPerKgRates')) {
            $output .= $this->handleImportPerKgRates();
        }

        // === Import por Rangos ===
        if (Tools::isSubmit('submitImportRangeRates')) {
            $output .= $this->handleImportRangeRates();
        }

        $carriers = Carrier::getCarriers(
            $this->context->language->id, true, false, false, null, Carrier::ALL_CARRIERS
        );

        $this->context->smarty->assign(array(
            'module_dir'   => $this->_path,
            'carriers'     => $carriers,
            'currentIndex' => $this->context->link->getAdminLink('AdminModules', false)
                .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name,
            'token'        => Tools::getAdminTokenLite('AdminModules'),
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $output .= $this->renderForm();

        return $output;
    }

    /**
     * Formulario
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar   = false;
        $helper->submit_action  = 'submitShipping_calculatorModule';
        $helper->currentIndex   = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token          = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Live mode'),
                        'name'    => 'SHIPPING_CALCULATOR_LIVE_MODE',
                        'is_bool' => true,
                        'values'  => array(
                            array('id' => 'active_on',  'value' => true,  'label' => $this->l('Enabled')),
                            array('id' => 'active_off', 'value' => false, 'label' => $this->l('Disabled')),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            )
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'SHIPPING_CALCULATOR_LIVE_MODE' => Configuration::get('SHIPPING_CALCULATOR_LIVE_MODE'),
        );
    }

    protected function postProcess()
    {
        Configuration::updateValue(
            'SHIPPING_CALCULATOR_LIVE_MODE',
            (bool)Tools::getValue('SHIPPING_CALCULATOR_LIVE_MODE')
        );
    }

    /* ============================================================
     * MÉTODOS OBLIGATORIOS DE CarrierModule
     * ============================================================ */

    public function getOrderShippingCost($params, $shipping_cost)
    {
        // Luego aquí meteremos tu lógica real de cálculo
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return true;
    }

    /* ============================================================
     * HELPERS TIPO DE CARRIER (RANGE / PER_KG)
     * ============================================================ */

    protected function setCarrierRateType($id_carrier, $type)
    {
        $id_carrier = (int)$id_carrier;
        if (!in_array($type, array(self::RATE_TYPE_RANGE, self::RATE_TYPE_PER_KG))) {
            return false;
        }

        $id_rate_type = (int)Db::getInstance()->getValue(
            'SELECT `id_rate_type` FROM `'._DB_PREFIX_.'shipping_rate_type`
             WHERE `id_carrier` = '.(int)$id_carrier
        );

        if ($id_rate_type) {
            return Db::getInstance()->update(
                'shipping_rate_type',
                array(
                    'type'   => pSQL($type),
                    'active' => 1,
                ),
                'id_rate_type = '.(int)$id_rate_type
            );
        }

        return Db::getInstance()->insert(
            'shipping_rate_type',
            array(
                'id_carrier' => $id_carrier,
                'type'       => pSQL($type),
                'active'     => 1,
            )
        );
    }

    protected function getCarrierRateType($id_carrier)
    {
        return Db::getInstance()->getValue(
            'SELECT `type` FROM `'._DB_PREFIX_.'shipping_rate_type`
             WHERE `id_carrier` = '.(int)$id_carrier.' AND `active` = 1'
        );
    }

    /* ============================================================
     *  IMPORTACIÓN ALDIA – TARIFAS POR KG
     * ============================================================ */

    protected function handleImportPerKgRates()
    {
        $id_carrier = (int)Tools::getValue('per_kg_id_carrier');

        if (!$id_carrier) {
            return $this->displayError($this->l('Selecciona un transportista.'));
        }

        if (!isset($_FILES['per_kg_csv']) || !is_uploaded_file($_FILES['per_kg_csv']['tmp_name'])) {
            return $this->displayError($this->l('Debes subir un archivo CSV.'));
        }

        // Borrar tarifas previas
        Db::getInstance()->delete('shipping_per_kg_rate', 'id_carrier='.(int)$id_carrier);

        $tmp = $_FILES['per_kg_csv']['tmp_name'];

        list($inserted, $summary) = $this->importPerKgCsv($id_carrier, $tmp);

        $this->setCarrierRateType($id_carrier, self::RATE_TYPE_PER_KG);

        return $this->displayConfirmation($summary);
    }

    protected function importPerKgCsv($id_carrier, $filePath)
    {
        $h = fopen($filePath, 'r');
        if (!$h) {
            return array(0, "No se pudo abrir el archivo CSV.");
        }

        // ===== CONTADORES =====
        $stats = [
            'rows_total'        => 0,
            'rows_empty'        => 0,
            'rows_city_missing' => 0,
            'inserted'          => 0,
            'price_zero'        => 0,
            'ignored'           => 0,
        ];

        $omittedCities = [];

        $header = [];
        $first = true;

        while (($row = fgetcsv($h, 0, ';')) !== false) {

            // compatibilidad coma
            if (count($row) == 1) {
                $row = str_getcsv($row[0], ',');
            }

            // Filas vacías
            if (!isset($row[0]) || trim(implode('', $row)) === '') {
                $stats['rows_empty']++;
                continue;
            }

            if ($first) {
                $header = array_map('strtoupper', array_map('trim', $row));
                $first = false;
                continue;
            }

            $stats['rows_total']++;

            // Se espera: ID | CIUDAD | DEPARTAMENTO | PRECIO
            if (count($row) < 4) {
                $stats['ignored']++;
                continue;
            }

            $city  = trim($row[1]);
            $state = trim($row[2]);
            $price = $this->normalizeNumber($row[3]);

            // ciudad
            $id_city = $this->getIdCityByNameAndState($city, $state);
            if (!$id_city) {
                $stats['rows_city_missing']++;
                $omittedCities[] = $city;
                continue;
            }

            // precio inválido
            if ($price <= 0) {
                $stats['price_zero']++;
                continue;
            }

            // insertar
            $ok = Db::getInstance()->insert('shipping_per_kg_rate', [
                'id_carrier' => (int)$id_carrier,
                'id_city'    => (int)$id_city,
                'price'      => (float)$price,
                'active'     => 1,
            ]);

            if ($ok) {
                $stats['inserted']++;
            } else {
                $stats['ignored']++;
            }
        }

        fclose($h);

        // ===== RESUMEN HTML =====
        $summary = "<b>Resumen de importación (por kilo):</b><br><br>
            ✔ Filas procesadas: <b>{$stats['rows_total']}</b><br>
            ✔ Tarifas insertadas: <b>{$stats['inserted']}</b><br><br>

            ⚠ Filas vacías/invalidas: <b>{$stats['rows_empty']}</b><br>
            ⚠ Filas con ciudad no encontrada: <b>{$stats['rows_city_missing']}</b><br>
            ⚠ Precios en cero/no válidos: <b>{$stats['price_zero']}</b><br>
            ⚠ Filas ignoradas por error: <b>{$stats['ignored']}</b><br><br>

            <b>Ciudades omitidas:</b> " . implode(', ', $omittedCities) . "<br>
        ";

        return [$stats['inserted'], $summary];
    }


    /* ============================================================
     * IMPORTACIÓN ENVIA – RANGOS
     * ============================================================ */

    protected function handleImportRangeRates()
    {
        $id_carrier = (int)Tools::getValue('range_id_carrier');

        if (!$id_carrier) {
            return $this->displayError($this->l('Selecciona un transportista.'));
        }

        if (!isset($_FILES['range_csv']) || !is_uploaded_file($_FILES['range_csv']['tmp_name'])) {
            return $this->displayError($this->l('Debes subir un archivo CSV/XLS/XLSX.'));
        }

        Db::getInstance()->delete('shipping_range_rate', 'id_carrier='.(int)$id_carrier);

        $tmp     = $_FILES['range_csv']['tmp_name'];
        $name    = $_FILES['range_csv']['name'];

        list($inserted, $summary) = $this->importRangeCsv($id_carrier, $tmp);

        $this->setCarrierRateType($id_carrier, self::RATE_TYPE_RANGE);

        return $this->displayConfirmation($summary);
    }

    protected function importRangeCsv($id_carrier, $filePath)
    {
        $h = fopen($filePath, 'r');
        if (!$h) {
            return array(0, 0);
        }

        // ===============================
        //     CONTADORES DETALLADOS
        // ===============================
        $stats = [
            'rows_total'          => 0,
            'rows_empty'          => 0,
            'rows_city_missing'   => 0,
            'range_inserted'      => 0,
            'range_price_zero'    => 0,
            'range_bad_header'    => 0,
            'range_ignored'       => 0,
        ];
        
        // ---> NUEVO: arreglo para guardar ciudades omitidas
        $omittedCities = [];

        $header   = [];
        $first    = true;

        while (($row = fgetcsv($h, 0, ';')) !== false) {

            // Forzar separación por coma si solo viene 1 columna
            if (count($row) == 1) {
                $row = str_getcsv($row[0], ',');
            }

            // Saltar filas completamente vacías
            if (!isset($row[0]) || trim(implode('', $row)) === '') {
                $stats['rows_empty']++;
                continue;
            }

            if ($first) {
                $header = array_map('strtoupper', array_map('trim', $row));
                $first = false;
                continue;
            }

            $stats['rows_total']++;

            // Índices
            $cityIdx  = array_search('CIUDAD', $header);
            $stateIdx = array_search('DEPARTAMENTO', $header);

            if ($cityIdx === false || $stateIdx === false ||
                !isset($row[$cityIdx], $row[$stateIdx])) {

                $stats['rows_empty']++;
                continue;
            }

            $city  = trim($row[$cityIdx]);
            $state = trim($row[$stateIdx]);

            $id_city = $this->getIdCityByNameAndState($city, $state);
            if (!$id_city) {
                $stats['rows_city_missing']++;
                // ---> NUEVO: registrar ciudad omitida
                $omittedCities[] = "$city";
                
                continue;
            }

            // Procesar cada columna que sea un rango
            foreach ($header as $colIdx => $colName) {

                // Detectar encabezados RANGO
                if (!preg_match('/^(RANGO_)?(\d+)[\-_](\d+|INF)$/', $colName, $m)) {
                    // Este encabezado NO es un rango válido
                    if ($colIdx > 2) { // evitar marcar ciudad/departamento como "malo"
                        $stats['range_bad_header']++;
                    }
                    continue;
                }

                $min = (float)$m[2];
                $max = ($m[3] === 'INF') ? null : (float)$m[3];

                $raw = isset($row[$colIdx]) ? $row[$colIdx] : '';
                $price = $this->normalizeNumber($raw);

                if ($price <= 0) {
                    $stats['range_price_zero']++;
                    continue;
                }

                // Insertar rango
                $inserted = Db::getInstance()->insert('shipping_range_rate', [
                    'id_carrier' => (int)$id_carrier,
                    'id_city'    => (int)$id_city,
                    'min_weight' => $min,
                    'max_weight' => $max,
                    'price'      => $price,
                    'active'     => 1,
                ]);

                if ($inserted) {
                    $stats['range_inserted']++;
                } else {
                    $stats['range_ignored']++;
                }
            }
        }

        fclose($h);

        // ===============================
        //  RETORNAR RESUMEN PARA EL UI
        // ===============================
        $summary = "<b>Resumen de importación:</b><br><br>
            ✔ Filas procesadas: <b>{$stats['rows_total']}</b><br>
            ✔ Rangos insertados: <b>{$stats['range_inserted']}</b><br><br>

            ⚠ Filas vacías/invalidas: <b>{$stats['rows_empty']}</b><br>
            ⚠ Filas con ciudad no encontrada: <b>{$stats['rows_city_missing']}</b><br>
            ⚠ Rangos con precio 0: <b>{$stats['range_price_zero']}</b><br>
            ⚠ Encabezados de rango NO reconocidos: <b>{$stats['range_bad_header']}</b><br>
            ⚠ Rangos ignorados por error de inserción: <b>{$stats['range_ignored']}</b><br>
            Ciudades omitidas: <b>" . implode(', ', $omittedCities) . "</b><br>
            ";

        return [$stats['range_inserted'], $summary];
    }


    protected function logError($msg)
    {
        $log = dirname(__FILE__) . '/error_log.txt';
        file_put_contents($log, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
    }
    
    protected function normalizeNumber($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        $value = str_replace(' ', '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return (float)$value;
    }

    protected function getIdCityByNameAndState($city, $state)
    {
        $city  = pSQL(trim($city));
        $state = pSQL(trim($state));

        if (!$city) {
            return 0;
        }

        // Buscar id_state
        $id_state = (int)Db::getInstance()->getValue(
            'SELECT id_state FROM `'._DB_PREFIX_.'state`
            WHERE name LIKE "'.$state.'"'
        );

        // Si existe el departamento:
        if ($id_state) {

            // 1. Buscar coincidencia exacta por name
            $id_city = (int)Db::getInstance()->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                WHERE name = "'.$city.'" AND id_state = '.(int)$id_state
            );
            if ($id_city) {
                return $id_city;
            }

            // 2. Buscar coincidencia exacta por name_alt
            $id_city = (int)Db::getInstance()->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                WHERE name_alt = "'.$city.'" AND id_state = '.(int)$id_state
            );
            if ($id_city) {
                return $id_city;
            }

            // 3. Buscar coincidencia parcial por name
            $id_city = (int)Db::getInstance()->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                WHERE name LIKE "%'.$city.'%" AND id_state = '.(int)$id_state
            );
            if ($id_city) {
                return $id_city;
            }

            // 4. Buscar coincidencia parcial por name_alt
            $id_city = (int)Db::getInstance()->getValue(
                'SELECT id_city FROM `'._DB_PREFIX_.'city`
                WHERE name_alt LIKE "%'.$city.'%" AND id_state = '.(int)$id_state
            );
            if ($id_city) {
                return $id_city;
            }
        }

        // 5. Fallback: buscar en cualquier estado por name
        $id_city = (int)Db::getInstance()->getValue(
            'SELECT id_city FROM `'._DB_PREFIX_.'city`
            WHERE name LIKE "%'.$city.'%"'
        );
        if ($id_city) {
            return $id_city;
        }

        // 6. Fallback: buscar en cualquier estado por name_alt
        $id_city = (int)Db::getInstance()->getValue(
            'SELECT id_city FROM `'._DB_PREFIX_.'city`
            WHERE name_alt LIKE "%'.$city.'%"'
        );
        if ($id_city) {
            return $id_city;
        }

        // Si no se encontró nada
        return 0;
    }

    /* ============================================================
     * Hooks
     * ============================================================ */

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
            $this->context->controller->addJS($this->_path.'views/js/back.js');
        }
    }

    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/front.css');
        $this->context->controller->addJS($this->_path.'views/js/front.js');
    }
}