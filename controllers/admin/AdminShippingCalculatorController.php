<?php

require_once _PS_MODULE_DIR_.'shipping_calculator/src/utils/CsvReader.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/NormalizerService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/CityLookupService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/CarrierRateTypeService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/RateImportService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/CarrierRegistryService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/ShippingQuoteService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/WeightCalculatorService.php';

class AdminShippingCalculatorController extends ModuleAdminController
{
    private $activePanel = 'panel-carriers';
    private $quotes = [];

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function postProcess()
    {
        /* ========================================================
         * REGISTRAR TRANSPORTISTA
         * ======================================================== */
        if (Tools::isSubmit('submitRegisterCarrier')) {

            $this->activePanel = 'panel-carriers';

            $idCarrier = (int)Tools::getValue('id_carrier');
            $rateType  = Tools::getValue('rate_type');

            if (!$idCarrier) {
                $this->errors[] = "Debes seleccionar un transportista.";
                return;
            }
            if (!$rateType) {
                $this->errors[] = "Debes seleccionar un tipo de tarifa.";
                return;
            }

            try {
                $registry = new CarrierRegistryService();

                if ($registry->isRegistered($idCarrier)) {
                    Db::getInstance()->update(
                        'shipping_rate_type',
                        ['type' => pSQL($rateType), 'active' => 1],
                        'id_carrier = '.(int)$idCarrier
                    );

                    $this->confirmations[] = "Transportista actualizado.";
                } else {
                    $registry->registerCarrier($idCarrier, $rateType);
                    $this->confirmations[] = "Transportista registrado correctamente.";
                }

            } catch (Exception $e) {
                $this->errors[] = "Error al registrar transportista: ".$e->getMessage();
            }
        }

        /* ========================================================
         * IMPORTAR TARIFAS
         * ======================================================== */
        if (Tools::isSubmit('submitImportRates')) {

            $this->activePanel = 'panel-import';

            $idCarrier = (int)Tools::getValue('id_carrier');

            if (!$idCarrier) {
                $this->errors[] = "Debes seleccionar un transportista.";
                return;
            }

            if (!isset($_FILES['rates_csv']) || !is_uploaded_file($_FILES['rates_csv']['tmp_name'])) {
                $this->errors[] = "Debes subir un archivo CSV.";
                return;
            }

            $filePath = $_FILES['rates_csv']['tmp_name'];

            try {
                $importer = new RateImportService(
                    new CsvReader(),
                    new NormalizerService(),
                    new CityLookupService(),
                    new CarrierRateTypeService(),
                    new CarrierRegistryService()
                );

                $result = $importer->import($idCarrier, $filePath);

                $this->confirmations[] = $result['summary'];

            } catch (Exception $e) {
                $this->errors[] = "Error durante la importación: ".$e->getMessage();
            }
        }

        /* ========================================================
         * CONFIGURACIÓN GLOBAL - EMPAQUE
         * ======================================================== */
        if (Tools::isSubmit('submitGlobalConfig')) {

            $this->activePanel = 'panel-config';

            $packagingPercent = (float)Tools::getValue('packaging_percent');

            if ($packagingPercent < 0) {
                $this->errors[] = "El porcentaje de empaque debe ser positivo.";
                return;
            }

            try {
                $existingPackaging = Db::getInstance()->getRow("
                    SELECT id_config FROM "._DB_PREFIX_."shipping_config
                    WHERE name = 'Empaque'
                ");

                if ($existingPackaging) {
                    Db::getInstance()->update('shipping_config', [
                        'value_number' => $packagingPercent,
                        'date_upd' => date('Y-m-d H:i:s'),
                    ], "name = 'Empaque'");
                } else {
                    Db::getInstance()->insert('shipping_config', [
                        'name' => 'Empaque',
                        'value_number' => $packagingPercent,
                        'date_add' => date('Y-m-d H:i:s'),
                        'date_upd' => date('Y-m-d H:i:s'),
                    ]);
                }

                $this->confirmations[] = "Configuración global actualizada correctamente.";

            } catch (Exception $e) {
                $this->errors[] = "Error al guardar configuración: ".$e->getMessage();
            }
        }

        /* ========================================================
         * CONFIGURACIÓN - PESO VOLUMÉTRICO POR TRANSPORTADORA
         * ======================================================== */
        if (Tools::isSubmit('submitVolumetricFactor')) {

            $this->activePanel = 'panel-config';

            $idCarrier = (int)Tools::getValue('volumetric_id_carrier');
            $volumetricFactor = (int)Tools::getValue('volumetric_factor');

            if (!$idCarrier) {
                $this->errors[] = "Debes seleccionar una transportadora.";
                return;
            }

            if ($volumetricFactor <= 0) {
                $this->errors[] = "El factor volumétrico debe ser mayor a 0.";
                return;
            }

            try {
                $existingVol = Db::getInstance()->getRow("
                    SELECT id_config FROM "._DB_PREFIX_."shipping_config
                    WHERE name = 'Peso volumetrico' AND id_carrier = ".(int)$idCarrier."
                ");

                if ($existingVol) {
                    Db::getInstance()->update('shipping_config', [
                        'value_number' => $volumetricFactor,
                        'date_upd' => date('Y-m-d H:i:s'),
                    ], "name = 'Peso volumetrico' AND id_carrier = ".(int)$idCarrier);
                } else {
                    Db::getInstance()->insert('shipping_config', [
                        'id_carrier' => $idCarrier,
                        'name' => 'Peso volumetrico',
                        'value_number' => $volumetricFactor,
                        'date_add' => date('Y-m-d H:i:s'),
                        'date_upd' => date('Y-m-d H:i:s'),
                    ]);
                }

                $this->confirmations[] = "Factor volumétrico actualizado correctamente.";

            } catch (Exception $e) {
                $this->errors[] = "Error al guardar factor volumétrico: ".$e->getMessage();
            }
        }

        /* ========================================================
         * CONFIGURACIÓN - TRANSPORTADORA POR RANGO (SEGURO)
         * ======================================================== */
        if (Tools::isSubmit('submitRangeInsurance')) {

            $this->activePanel = 'panel-config';

            $idCarrier = (int)Tools::getValue('range_insurance_carrier');
            $minWeight = (float)Tools::getValue('range_insurance_min');
            $maxWeight = (float)Tools::getValue('range_insurance_max');
            $percentage = (float)Tools::getValue('range_insurance_percentage');

            if (!$idCarrier) {
                $this->errors[] = "Debes seleccionar una transportadora.";
                return;
            }

            if ($percentage <= 0) {
                $this->errors[] = "El porcentaje debe ser mayor a 0.";
                return;
            }

            try {
                Db::getInstance()->insert('shipping_config', [
                    'id_carrier' => $idCarrier,
                    'name' => 'Seguro',
                    'min' => $minWeight,
                    'max' => $maxWeight > 0 ? $maxWeight : null,
                    'value_number' => $percentage,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s'),
                ]);

                $this->confirmations[] = "Rango de seguro agregado correctamente.";

            } catch (Exception $e) {
                $this->errors[] = "Error al guardar seguro: ".$e->getMessage();
            }
        }

        /* ========================================================
         * CONFIGURACIÓN - TRANSPORTADORA POR KG (COMPLETA)
         * ======================================================== */
        if (Tools::isSubmit('submitPerKgConfig')) {

            $this->activePanel = 'panel-config';

            $idCarrier = (int)Tools::getValue('perkg_id_carrier');
            $minFreight = (float)Tools::getValue('perkg_min_freight');
            $minKilos = (float)Tools::getValue('perkg_min_kilos');
            $baseValue = (float)Tools::getValue('perkg_base_value');
            $minInsurance = (float)Tools::getValue('perkg_min_insurance');
            $insurancePercent = (float)Tools::getValue('perkg_insurance_percent');

            if (!$idCarrier) {
                $this->errors[] = "Debes seleccionar una transportadora.";
                return;
            }

            try {
                // 1. Flete Mínimo Nacional
                if ($minFreight > 0) {
                    $this->upsertConfig($idCarrier, 'Flete minimo', $minFreight, null, null);
                }

                // 2. Kilos de Cobro Mínimo
                if ($minKilos > 0) {
                    $this->upsertConfig($idCarrier, 'Kilos minimo', $minKilos, null, null);
                }

                // 3 y 4. SEGUROS: Se guardan como rangos de "Seguro"
                // Primero eliminar seguros anteriores de esta transportadora
                Db::getInstance()->delete('shipping_config', 
                    "id_carrier = ".(int)$idCarrier." AND name = 'Seguro'");

                if ($baseValue > 0 && ($minInsurance > 0 || $insurancePercent > 0)) {
                    // Rango 1: De 0 a baseValue → Seguro Mínimo (valor fijo)
                    if ($minInsurance > 0) {
                        Db::getInstance()->insert('shipping_config', [
                            'id_carrier' => $idCarrier,
                            'name' => 'Seguro',
                            'min' => 0,
                            'max' => $baseValue,
                            'value_number' => $minInsurance,
                            'date_add' => date('Y-m-d H:i:s'),
                            'date_upd' => date('Y-m-d H:i:s'),
                        ]);
                    }

                    // Rango 2: De baseValue a infinito → Porcentaje
                    if ($insurancePercent > 0) {
                        Db::getInstance()->insert('shipping_config', [
                            'id_carrier' => $idCarrier,
                            'name' => 'Seguro',
                            'min' => $baseValue,
                            'max' => 0, // 0 = sin límite
                            'value_number' => $insurancePercent,
                            'date_add' => date('Y-m-d H:i:s'),
                            'date_upd' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $this->confirmations[] = "Configuración de transportadora por KG actualizada correctamente.";

            } catch (Exception $e) {
                $this->errors[] = "Error al guardar configuración: ".$e->getMessage();
            }
        }

        /* ========================================================
         * ELIMINAR CONFIGURACIÓN
         * ======================================================== */
        if (Tools::isSubmit('deleteConfig')) {

            $this->activePanel = 'panel-config';

            $idConfig = (int)Tools::getValue('id_config');

            if ($idConfig) {
                try {
                    Db::getInstance()->delete('shipping_config', "id_config = ".(int)$idConfig);
                    $this->confirmations[] = "Configuración eliminada.";
                } catch (Exception $e) {
                    $this->errors[] = "Error al eliminar: ".$e->getMessage();
                }
            }
        }

        /* ========================================================
         * COTIZADOR DE ENVÍOS
         * ======================================================== */
        if (Tools::isSubmit('submitQuote')) {
            $this->activePanel = 'panel-quote';

            $id_city = (int)Tools::getValue('id_city');

            // soporte para múltiples productos: campo products[] (cada item: id_product, qty, is_grouped)
            $productsInput = Tools::getValue('products');

            if (!$id_city) {
                $this->errors[] = "Selecciona la ciudad.";
                return;
            }

            try {
                $service = new ShippingQuoteService(
                    new CarrierRegistryService(),
                    new WeightCalculatorService()
                );

                if (is_array($productsInput) && count($productsInput) > 0) {
                    // normalizar items: obtener is_grouped de la BD
                    $items = [];
                    foreach ($productsInput as $p) {
                        $idp = isset($p['id_product']) ? (int)$p['id_product'] : 0;
                        $q   = isset($p['qty']) ? max(1, (int)$p['qty']) : 1;
                        if ($idp) {
                            // Obtener is_grouped desde BD
                            $groupedRow = Db::getInstance()->getRow("
                                SELECT is_grouped
                                FROM "._DB_PREFIX_."shipping_product
                                WHERE id_product = ".(int)$idp."
                            ");
                            $isGrouped = $groupedRow ? (int)$groupedRow['is_grouped'] : 0;
                            
                            $items[] = [
                                'id_product' => $idp,
                                'qty' => $q,
                                'is_grouped' => $isGrouped
                            ];
                        }
                    }

                    if (empty($items)) {
                        $this->errors[] = "Agrega al menos un producto válido.";
                        return;
                    }

                    // Usar nuevo método que maneja ambos tipos
                    $result = $service->quoteMultipleWithGrouped($items, $id_city);

                    $this->context->smarty->assign([
                        'grouped_packages' => $result['grouped_packages'],
                        'individual_items' => $result['individual_items'],
                        'total_grouped' => $result['total_grouped'],
                        'total_individual' => $result['total_individual'],
                        'grand_total' => $result['grand_total'],
                    ]);

                    // también asignar ciudad
                    $selectedCity = Db::getInstance()->getRow("\n                        SELECT c.id_city, c.name, s.name AS state\n                        FROM "._DB_PREFIX_."city c\n                        LEFT JOIN "._DB_PREFIX_."state s ON s.id_state=c.id_state\n                        WHERE c.id_city=".(int)$id_city."\n                    ");

                    $this->context->smarty->assign(['selected_city' => $selectedCity]);

                    $this->confirmations[] = "Cotización múltiple realizada (productos agrupados e individuales).";
                } else {
                    // modo legacy: id_product + qty
                    $id_product = (int)Tools::getValue('id_product');
                    $qty        = max(1, (int)Tools::getValue('qty'));

                    if (!$id_product) {
                        $this->errors[] = "Selecciona producto.";
                        return;
                    }

                    $this->quotes = $service->quote($id_product, $id_city, $qty);

                    // Para mostrar resumen en la vista:
                    $selectedProduct = Db::getInstance()->getRow("\n                        SELECT id_product, name\n                        FROM "._DB_PREFIX_."product_lang\n                        WHERE id_product=".(int)$id_product."\n                        AND id_lang=".(int)$this->context->language->id."\n                    ");

                    $selectedCity = Db::getInstance()->getRow("\n                        SELECT c.id_city, c.name, s.name AS state\n                        FROM "._DB_PREFIX_."city c\n                        LEFT JOIN "._DB_PREFIX_."state s ON s.id_state=c.id_state\n                        WHERE c.id_city=".(int)$id_city."\n                    ");

                    $this->context->smarty->assign([
                        'selected_product' => $selectedProduct,
                        'selected_city'    => $selectedCity,
                        'selected_qty'     => $qty,
                    ]);

                    $this->confirmations[] = $this->quotes
                        ? "Cotización realizada correctamente."
                        : "No se encontraron tarifas disponibles para la cotización.";        
                }

            } catch (Exception $e) {
                $this->errors[] = "Error al cotizar: ".$e->getMessage();
            }
        }
    }

    /* ============================================================
     * RENDER LIST
     * ============================================================ */
    public function renderList()
    {
        $registered = Db::getInstance()->executeS("\n            SELECT crt.id_carrier, crt.type, c.name\n            FROM "._DB_PREFIX_."shipping_rate_type crt\n            LEFT JOIN "._DB_PREFIX_."carrier c ON c.id_carrier = crt.id_carrier\n            WHERE crt.active = 1\n        ");

        $allCarriers = Carrier::getCarriers(
            $this->context->language->id,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );

        $products = Db::getInstance()->executeS("\n            SELECT id_product, name\n            FROM "._DB_PREFIX_."product_lang\n            WHERE id_lang = ".(int)$this->context->language->id."\n            ORDER BY name ASC\n        ");

        $cities = Db::getInstance()->executeS("\n            SELECT c.id_city, c.name, s.name AS state\n            FROM "._DB_PREFIX_."city c\n            LEFT JOIN "._DB_PREFIX_."state s ON s.id_state = c.id_state\n            WHERE c.active = 1\n            ORDER BY c.name ASC\n        ");

        // Obtener configuraciones para el panel de configuración
        $globalPackaging = Db::getInstance()->getRow("
            SELECT value_number
            FROM "._DB_PREFIX_."shipping_config
            WHERE name = 'Empaque' AND (id_carrier = 0 OR id_carrier IS NULL)
        ");

        $carrierConfigs = [];
        foreach ($registered as $carrier) {
            $idCarrier = (int)$carrier['id_carrier'];
            $type = $carrier['type'];

            // Peso volumétrico
            $volumetric = Db::getInstance()->getRow("
                SELECT value_number
                FROM "._DB_PREFIX_."shipping_config
                WHERE name = 'Peso volumetrico' AND id_carrier = ".$idCarrier."
            ");

            // Configuraciones específicas según tipo
            if ($type === 'per_kg') {
                // Transportadora POR KG
                $minFreight = Db::getInstance()->getRow("
                    SELECT value_number FROM "._DB_PREFIX_."shipping_config
                    WHERE name = 'Flete minimo' AND id_carrier = ".$idCarrier."
                ");

                $minKilos = Db::getInstance()->getRow("
                    SELECT value_number FROM "._DB_PREFIX_."shipping_config
                    WHERE name = 'Kilos minimo' AND id_carrier = ".$idCarrier."
                ");

                // Cargar seguros desde rangos
                $insuranceRanges = Db::getInstance()->executeS("
                    SELECT * FROM "._DB_PREFIX_."shipping_config
                    WHERE name = 'Seguro' AND id_carrier = ".$idCarrier."
                    ORDER BY min ASC
                ");

                $baseValue = null;
                $minInsurance = null;
                $insurancePercent = null;

                // Interpretar los rangos
                if ($insuranceRanges && count($insuranceRanges) > 0) {
                    foreach ($insuranceRanges as $range) {
                        $min = (float)$range['min'];
                        $max = (float)$range['max'];
                        $value = (float)$range['value_number'];

                        if ($min == 0 && $max > 0) {
                            // Rango inferior: 0 a baseValue → Seguro Mínimo
                            $baseValue = $max;
                            $minInsurance = $value;
                        } elseif ($min > 0 && $max == 0) {
                            // Rango superior: baseValue a infinito → Porcentaje
                            if ($baseValue === null) {
                                $baseValue = $min;
                            }
                            $insurancePercent = $value;
                        }
                    }
                }

                $carrierConfigs[$idCarrier] = [
                    'carrier' => $carrier,
                    'type' => 'per_kg',
                    'volumetric_factor' => $volumetric ? $volumetric['value_number'] : null,
                    'min_freight' => $minFreight ? $minFreight['value_number'] : null,
                    'min_kilos' => $minKilos ? $minKilos['value_number'] : null,
                    'base_value' => $baseValue,
                    'min_insurance' => $minInsurance,
                    'insurance_percent' => $insurancePercent,
                    'insurance_ranges' => $insuranceRanges, // Para mostrar en tabla
                ];
            } else {
                // Transportadora POR RANGO
                $insurances = Db::getInstance()->executeS("
                    SELECT *
                    FROM "._DB_PREFIX_."shipping_config
                    WHERE name = 'Seguro' AND id_carrier = ".$idCarrier."
                    ORDER BY min ASC
                ");

                $carrierConfigs[$idCarrier] = [
                    'carrier' => $carrier,
                    'type' => 'range',
                    'volumetric_factor' => $volumetric ? $volumetric['value_number'] : null,
                    'insurances' => $insurances,
                ];
            }
        }

        $this->context->smarty->assign([
            'registered_carriers' => $registered,
            'carriers_all'       => $allCarriers,
            'products'           => $products,
            'cities'             => $cities,
            'quotes'             => $this->quotes,
            'active_panel'       => $this->activePanel,
            'token'              => $this->token,
            'currentIndex'       => self::$currentIndex,
            'global_packaging'   => $globalPackaging ? $globalPackaging['value_number'] : 5.0,
            'carrier_configs'    => $carrierConfigs,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_.'shipping_calculator/views/templates/admin/main.tpl'
        );
    }

    /* ============================================================
     * MÉTODO AUXILIAR PARA UPSERT DE CONFIGURACIÓN
     * ============================================================ */
    private function upsertConfig($idCarrier, $name, $valueNumber, $min = null, $max = null)
    {
        $existing = Db::getInstance()->getRow("
            SELECT id_config FROM "._DB_PREFIX_."shipping_config
            WHERE name = '".pSQL($name)."' AND id_carrier = ".(int)$idCarrier."
            AND (min IS NULL OR min = ".(float)($min ?: 0).")
        ");

        if ($existing) {
            Db::getInstance()->update('shipping_config', [
                'value_number' => (float)$valueNumber,
                'date_upd' => date('Y-m-d H:i:s'),
            ], "id_config = ".(int)$existing['id_config']);
        } else {
            Db::getInstance()->insert('shipping_config', [
                'id_carrier' => (int)$idCarrier,
                'name' => pSQL($name),
                'min' => $min !== null ? (float)$min : null,
                'max' => $max !== null ? (float)$max : null,
                'value_number' => (float)$valueNumber,
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}