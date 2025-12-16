<?php
/**
 * Shipping Calculator – Carrier dinámico y compatible con cualquier checkout
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Shipping_calculator extends CarrierModule
{
    public function __construct()
    {
        $this->name = 'shipping_calculator';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Sara Lucia';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Shipping Calculator');
        $this->description = $this->l(
            'Calcula dinámicamente el costo de envío según ciudad y reglas logísticas.'
        );

        $this->confirmUninstall = $this->l(
            '¿Seguro que deseas desinstalar este módulo?'
        );

        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => '9.0'
        ];
    }

    /* ============================================================
     * INSTALL / UNINSTALL
     * ============================================================ */

    public function install()
    {
        return parent::install()
            && include dirname(__FILE__) . '/sql/install.php'
            && $this->installTab()
            && $this->createCarrier()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('header')
            && $this->registerHook('displayAdminProductsMainStepLeftColumnBottom')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('displayCarrierExtraContent');
    }

    public function uninstall()
    {
        $id_carrier = (int)Configuration::get('SHIPPING_CALCULATOR_CARRIER_ID');

        if ($id_carrier) {
            $carrier = new Carrier($id_carrier);
            if (Validate::isLoadedObject($carrier)) {
                $carrier->delete();
            }
            Configuration::deleteByName('SHIPPING_CALCULATOR_CARRIER_ID');
        }

        $this->uninstallTab();
        include dirname(__FILE__) . '/sql/uninstall.php';

        return parent::uninstall();
    }
    
    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminShippingCalculator';
        $tab->name = [];
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Calculadora de Envíos';
        }
        
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentShipping');
        $tab->module = $this->name;
        
        return $tab->add();
    }
    
    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminShippingCalculator');
        
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        
        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminShippingCalculator')
        );
    }

    /* ============================================================
     * BACKOFFICE ASSETS
     * ============================================================ */

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') === 'AdminShippingCalculator') {
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
        }
    }

    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/front.css');
        $this->context->controller->addJS($this->_path.'views/js/front.js');
    }
    
    /**
     * Hook para mostrar contenido adicional del carrier
     */
    public function hookDisplayCarrierExtraContent($params)
    {
        // Verificar si es nuestro carrier
        $id_carrier = (int)Configuration::get('SHIPPING_CALCULATOR_CARRIER_ID');
        
        if (isset($params['carrier']['id_carrier']) && (int)$params['carrier']['id_carrier'] === $id_carrier) {
            $cart = $this->context->cart;
            
            // Si no hay dirección o ciudad, mostrar mensaje
            if (!$cart->id_address_delivery) {
                return '<div class="shipping-calculator-message" style="color: #f39c12; font-style: italic; margin-top: 5px;">
                    Por calcular - Seleccione una dirección de entrega
                </div>';
            }
            
            $address = new Address($cart->id_address_delivery);
            if (!Validate::isLoadedObject($address) || !$address->city) {
                return '<div class="shipping-calculator-message" style="color: #f39c12; font-style: italic; margin-top: 5px;">
                    Por calcular - Complete la ciudad en la dirección
                </div>';
            }
            
            // Verificar si la ciudad existe en el sistema
            $cityRow = Db::getInstance()->getRow("
                SELECT c.id_city
                FROM "._DB_PREFIX_."city c
                WHERE c.name LIKE '".pSQL($address->city)."'
            ");
            
            if (!$cityRow) {
                return '<div class="shipping-calculator-message" style="color: #f39c12; font-style: italic; margin-top: 5px;">
                    Por calcular - Ciudad sin cobertura registrada
                </div>';
            }
        }
        
        return '';
    }

    /* ============================================================
     * CAMPO "TIPO DE EMBALAJE" EN PRODUCTO
     * ============================================================ */

    public function hookDisplayAdminProductsMainStepLeftColumnBottom($params)
    {
        $id_product = (int)$params['id_product'];

        $row = Db::getInstance()->getRow("
            SELECT is_grouped, max_units_per_package
            FROM "._DB_PREFIX_."shipping_product
            WHERE id_product = $id_product
        ");

        $this->context->smarty->assign([
            'is_grouped' => $row ? (string)$row['is_grouped'] : '',
            'max_units_per_package' => $row && $row['max_units_per_package'] ? (int)$row['max_units_per_package'] : ''
        ]);

        return $this->fetch('module:shipping_calculator/views/templates/hook/admin_product_transport.tpl');
    }

    /* ============================================================
     * GUARDAR “Tipo de embalaje” al guardar producto
     * ============================================================ */
    public function hookActionProductSave($params)
    {
        $id_product = (int)$params['id_product'];

        $is_grouped = Tools::getValue('shipping_is_grouped');
        if ($is_grouped === '') {
            $is_grouped = null;
        }

        $max_units = Tools::getValue('shipping_max_units_per_package');
        if ($max_units === '' || $max_units === '0') {
            $max_units = null;
        } else {
            $max_units = (int)$max_units;
        }

        $exists = Db::getInstance()->getValue("
            SELECT id_shipping_product
            FROM "._DB_PREFIX_."shipping_product
            WHERE id_product = $id_product
        ");

        if ($exists) {
            Db::getInstance()->update('shipping_product', [
                'is_grouped' => $is_grouped,
                'max_units_per_package' => $max_units,
                'date_upd'   => date('Y-m-d H:i:s'),
            ], "id_product = $id_product");
        } else {
            Db::getInstance()->insert('shipping_product', [
                'id_product' => $id_product,
                'is_grouped' => $is_grouped,
                'max_units_per_package' => $max_units,
                'date_add'   => date('Y-m-d H:i:s'),
                'date_upd'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /* ============================================================
     * CREACIÓN DEL CARRIER (SIN PRECIOS)
     * ============================================================ */

    private function createCarrier()
    {
        $carrier = new Carrier();
        $carrier->name = 'Envío calculado';
        $carrier->active = true;
        $carrier->deleted = false;
        $carrier->is_module = true;
        $carrier->shipping_external = true;
        $carrier->external_module_name = $this->name;
        $carrier->need_range = true;
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->id_tax_rules_group = 0;
        $carrier->shipping_handling = false;
        $carrier->range_behavior = 0;
        $carrier->max_weight = 10000;
        $carrier->max_width = 0;
        $carrier->max_height = 0;
        $carrier->max_depth = 0;

        foreach (Language::getLanguages(true) as $lang) {
            $carrier->delay[$lang['id_lang']] =
                'Costo calculado según ciudad';
        }

        if (!$carrier->add()) {
            return false;
        }

        foreach (Group::getGroups(true) as $group) {
            Db::getInstance()->insert('carrier_group', [
                'id_carrier' => (int)$carrier->id,
                'id_group'   => (int)$group['id_group']
            ]);
        }

        // Crear rango de peso (necesario para que el carrier funcione)
        $rangeWeight = new RangeWeight();
        $rangeWeight->id_carrier = $carrier->id;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '10000';
        $rangeWeight->add();
        
        // Crear rango de precio
        $rangePrice = new RangePrice();
        $rangePrice->id_carrier = $carrier->id;
        $rangePrice->delimiter1 = '0';
        $rangePrice->delimiter2 = '1000000';
        $rangePrice->add();

        foreach (Zone::getZones(true) as $zone) {
            Db::getInstance()->insert('carrier_zone', [
                'id_carrier' => (int)$carrier->id,
                'id_zone'    => (int)$zone['id_zone']
            ]);
            
            // Agregar precio 0 en delivery - getOrderShippingCost() lo sobrescribirá
            Db::getInstance()->insert('delivery', [
                'id_carrier' => (int)$carrier->id,
                'id_range_price' => null,
                'id_range_weight' => (int)$rangeWeight->id,
                'id_zone' => (int)$zone['id_zone'],
                'price' => 0
            ]);
        }

        Configuration::updateValue(
            'SHIPPING_CALCULATOR_CARRIER_ID',
            (int)$carrier->id
        );

        @copy(
            dirname(__FILE__).'/views/img/carrier.jpg',
            _PS_SHIP_IMG_DIR_.$carrier->id.'.jpg'
        );

        return true;
    }

    /* ============================================================
     * CÁLCULO REAL DEL ENVÍO
     * ============================================================ */

    public function getOrderShippingCostExternal($params)
    {
        return $this->calculateShipping();
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        // El $shipping_cost viene de los rangos (configurado en 0)
        // Lo reemplazamos completamente con nuestro cálculo
        $customCost = $this->calculateShipping();
        
        // Si calculateShipping retorna false, ocultar el carrier
        if ($customCost === false) {
            return false;
        }
        
        // Si retorna 0, mostrar el carrier con precio 0 (para "Por calcular")
        // Si retorna un valor > 0, ese es el costo real calculado
        return $customCost;
    }
    
    /**
     * Método requerido por PrestaShop para carriers externos
     */
    public function getPackageShippingCost($cart, $shipping_cost, $products)
    {
        return $this->calculateShipping();
    }
    
    /**
     * Lógica central de cálculo de envío
     */
    private function calculateShipping()
    {
        try {
            $cart = $this->context->cart;
            
            // Si no hay dirección, mostrar "Por calcular"
            if (!$cart->id_address_delivery) {
                return 0;
            }
            
            $address = new Address($cart->id_address_delivery);
            
            if (!Validate::isLoadedObject($address)) {
                return 0;
            }
            
            // Buscar ciudad por nombre
            $cityName = trim($address->city);
            $id_city = null;
            
            if (!empty($cityName)) {
                $rows = Db::getInstance()->executeS("
                    SELECT c.id_city, c.name
                    FROM "._DB_PREFIX_."city c
                    WHERE c.name LIKE '".pSQL($cityName)."'
                    OR c.name LIKE '%".pSQL($cityName)."%'
                ");

                $cityRow = $rows[0] ?? null;
                
                if ($cityRow && isset($cityRow['id_city'])) {
                    $id_city = (int)$cityRow['id_city'];
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
            
            // Obtener productos del carrito
            $products = $cart->getProducts();
            
            if (empty($products)) {
                return 0;
            }
            
            // Cargar servicios
            require_once dirname(__FILE__) . '/src/services/CarrierRegistryService.php';
            require_once dirname(__FILE__) . '/src/services/WeightCalculatorService.php';
            require_once dirname(__FILE__) . '/src/services/ShippingQuoteService.php';
            
            $carrierRegistry = new CarrierRegistryService();
            $weightCalc = new WeightCalculatorService();
            $quoteService = new ShippingQuoteService($carrierRegistry, $weightCalc);
            
            // Preparar items con información de agrupamiento
            $items = [];
            foreach ($products as $product) {
                $id_product = (int)$product['id_product'];
                $qty = (int)$product['cart_quantity'];
                
                // Obtener is_grouped y max_units_per_package desde BD
                $groupedRow = Db::getInstance()->getRow("
                    SELECT is_grouped, max_units_per_package
                    FROM "._DB_PREFIX_."shipping_product
                    WHERE id_product = ".(int)$id_product."
                ");
                                
                $is_grouped = $groupedRow ? (int)$groupedRow['is_grouped'] : 0;
                $max_units = $groupedRow && $groupedRow['max_units_per_package'] ? (int)$groupedRow['max_units_per_package'] : 0;
                                
                $items[] = [
                    'id_product' => $id_product,
                    'qty' => $qty,
                    'is_grouped' => $is_grouped,
                    'max_units_per_package' => $max_units
                ];
            }
            
            // Cotizar usando el servicio
            $quoteResult = $quoteService->quoteMultipleWithGrouped($items, $id_city);

            // Validación dura
            if (
                !is_array($quoteResult) ||
                !isset($quoteResult['grand_total']) ||
                (float)$quoteResult['grand_total'] <= 0
            ) {
                return false; // <- CLAVE
            }

            $totalCost = (float)$quoteResult['grand_total'];

            return $totalCost;
        } catch (Exception $e) {
            return false;
        }
    }
}