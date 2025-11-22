<?php

require_once _PS_MODULE_DIR_.'shipping_calculator/src/utils/CsvReader.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/NormalizerService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/CityLookupService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/CarrierRateTypeService.php';
require_once _PS_MODULE_DIR_.'shipping_calculator/src/services/RateImportService.php';

class AdminShippingCalculatorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitImportRates')) {
            $idCarrier = (int)Tools::getValue('id_carrier');
            $rateType  = Tools::getValue('rate_type');

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
                    new CarrierRateTypeService()
                );

                $result = $importer->import($idCarrier, $rateType, $filePath);

                $this->confirmations[] = $result['summary'];

            } catch (Exception $e) {
                $this->errors[] = "Error durante la importación: ".$e->getMessage();
            }
        }
    }

    public function renderList()
    {
        $carriers = Carrier::getCarriers(
            $this->context->language->id,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );

        $this->context->smarty->assign([
            'carriers' => $carriers,
            'token' => $this->token,
            'currentIndex' => self::$currentIndex
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_.'shipping_calculator/views/templates/admin/main.tpl'
        );
    }
}