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

            $id_product = (int)Tools::getValue('id_product');
            $id_city    = (int)Tools::getValue('id_city');
            $qty        = max(1, (int)Tools::getValue('qty'));

            if (!$id_product || !$id_city) {
                $this->errors[] = "Selecciona producto y ciudad.";
                return;
            }

            try {
                $service = new ShippingQuoteService(
                    new CarrierRegistryService(),
                    new WeightCalculatorService()
                );

                $this->quotes = $service->quote($id_product, $id_city, $qty);

                // Para mostrar resumen en la vista:
                $selectedProduct = Db::getInstance()->getRow("
                    SELECT id_product, name
                    FROM "._DB_PREFIX_."product_lang
                    WHERE id_product=".(int)$id_product."
                    AND id_lang=".(int)$this->context->language->id."
                ");

                $selectedCity = Db::getInstance()->getRow("
                    SELECT c.id_city, c.name, s.name AS state
                    FROM "._DB_PREFIX_."city c
                    LEFT JOIN "._DB_PREFIX_."state s ON s.id_state=c.id_state
                    WHERE c.id_city=".(int)$id_city."
                ");

                $this->context->smarty->assign([
                    'selected_product' => $selectedProduct,
                    'selected_city'    => $selectedCity,
                    'selected_qty'     => $qty,
                ]);

                $this->confirmations[] = $this->quotes
                    ? "Cotización realizada correctamente."
                    : "No se encontraron tarifas disponibles para la cotización.";

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
        $registered = Db::getInstance()->executeS("
            SELECT crt.id_carrier, crt.type, c.name
            FROM "._DB_PREFIX_."shipping_rate_type crt
            LEFT JOIN "._DB_PREFIX_."carrier c ON c.id_carrier = crt.id_carrier
            WHERE crt.active = 1
        ");

        $allCarriers = Carrier::getCarriers(
            $this->context->language->id,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );

        $products = Db::getInstance()->executeS("
            SELECT id_product, name
            FROM "._DB_PREFIX_."product_lang
            WHERE id_lang = ".(int)$this->context->language->id."
            ORDER BY name ASC
        ");

        $cities = Db::getInstance()->executeS("
            SELECT c.id_city, c.name, s.name AS state
            FROM "._DB_PREFIX_."city c
            LEFT JOIN "._DB_PREFIX_."state s ON s.id_state = c.id_state
            WHERE c.active = 1
            ORDER BY c.name ASC
        ");

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