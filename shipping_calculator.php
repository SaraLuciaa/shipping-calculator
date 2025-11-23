<?php
/**
 * Shipping Calculator – Módulo limpio y completo
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
        $this->description = $this->l('Automatiza el cálculo de tarifas de envío y gestiona reglas logísticas por producto y transportista.');

        $this->confirmUninstall = $this->l('¿Seguro que deseas desinstalar este módulo?');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => '9.0'];
    }

    /**
     * Install
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Crear tablas
        if (!include dirname(__FILE__) . '/sql/install.php') {
            return false;
        }

        Configuration::updateValue('SHIPPING_CALCULATOR_LIVE_MODE', false);

        return
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('header') &&
            $this->registerHook('updateCarrier') &&
            $this->registerHook('actionCarrierProcess') &&
            $this->registerHook('displayAdminProductsMainStepLeftColumnBottom') && // ⬅ Campo en Transporte
            $this->registerHook('actionProductSave'); // ⬅ Guarda el valor
    }

    /**
     * Uninstall
     */
    public function uninstall()
    {
        Configuration::deleteByName('SHIPPING_CALCULATOR_LIVE_MODE');

        include dirname(__FILE__) . '/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Redirige al nuevo controlador del módulo
     */
    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminShippingCalculator')
        );
    }

    /* ============================================================
     * Hooks de Backoffice
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
        $this->context->controller->addCSS($this->_path . 'views/css/front.css');
        $this->context->controller->addJS($this->_path . 'views/js/front.js');
    }

    /* ============================================================
     * MOSTRAR el campo “Tipo de embalaje” en pestaña Transporte
     * ============================================================ */
    public function hookDisplayAdminProductsMainStepLeftColumnBottom($params)
    {
        $id_product = (int)$params['id_product'];

        // cargar valor actual
        $row = Db::getInstance()->getRow("
            SELECT is_grouped
            FROM "._DB_PREFIX_."shipping_product
            WHERE id_product = $id_product
        ");

        $is_grouped = $row ? (string)$row['is_grouped'] : '';

        $this->context->smarty->assign([
            'is_grouped' => $is_grouped,
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

        $exists = Db::getInstance()->getValue("
            SELECT id_shipping_product
            FROM "._DB_PREFIX_."shipping_product
            WHERE id_product = $id_product
        ");

        if ($exists) {
            Db::getInstance()->update('shipping_product', [
                'is_grouped' => $is_grouped,
                'date_upd'   => date('Y-m-d H:i:s'),
            ], "id_product = $id_product");
        } else {
            Db::getInstance()->insert('shipping_product', [
                'id_product' => $id_product,
                'is_grouped' => $is_grouped,
                'date_add'   => date('Y-m-d H:i:s'),
                'date_upd'   => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /* ============================================================
     * Métodos obligatorios pero NO usados
     * ============================================================ */

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return true;
    }
}