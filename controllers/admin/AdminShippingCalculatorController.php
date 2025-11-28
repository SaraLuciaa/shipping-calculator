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
                    
                    // DEBUG: Log valores
                    $this->confirmations[] = "DEBUG quoteMultipleWithGrouped result: " . json_encode([
                        'total_grouped' => $result['total_grouped'],
                        'total_individual' => $result['total_individual'],
                        'grand_total' => $result['grand_total'],
                        'grouped_packages_count' => count($result['grouped_packages']),
                        'individual_items_count' => count($result['individual_items']),
                    ]);
        
                    // Debug: mostrar precios de paquetes agrupados
                    if (!empty($result['grouped_packages'])) {
                        foreach ($result['grouped_packages'] as $i => $pkg) {
                            error_log("DEBUG package[$i]: cheapest=" . json_encode($pkg['cheapest']));
                        }
                    }                    

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

        $this->context->smarty->assign([
            'registered_carriers' => $registered,
            'carriers_all'       => $allCarriers,
            'products'           => $products,
            'cities'             => $cities,
            'quotes'             => $this->quotes,
            'active_panel'       => $this->activePanel,
            'token'              => $this->token,
            'currentIndex'       => self::$currentIndex,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_.'shipping_calculator/views/templates/admin/main.tpl'
        );
    }
}