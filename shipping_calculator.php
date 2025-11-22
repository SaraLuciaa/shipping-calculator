<?php
/**
 * Shipping Calculator – Módulo limpio
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
        $this->description = $this->l('Automatiza el cálculo de tarifas de envío y gestiona las reglas logísticas.');
        $this->confirmUninstall = $this->l('¿Seguro que deseas desinstalar Shipping Calculator?');

        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => '9.0'];
    }

    /**
     * Install
     */
    public function install()
    {
        if (!extension_loaded('curl')) {
            $this->_errors[] = $this->l('Debes habilitar cURL en el servidor.');
            return false;
        }

        if (!parent::install()) {
            return false;
        }

        // Crear tablas
        if (!include dirname(__FILE__) . '/sql/install.php') {
            return false;
        }

        // Valor por defecto
        Configuration::updateValue('SHIPPING_CALCULATOR_LIVE_MODE', false);

        // Registrar hooks necesarios
        return
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('header') &&
            $this->registerHook('updateCarrier') &&
            $this->registerHook('actionCarrierProcess');
    }

    /**
     * Uninstall
     */
    public function uninstall()
    {
        Configuration::deleteByName('SHIPPING_CALCULATOR_LIVE_MODE');

        // Eliminar tablas
        include dirname(__FILE__) . '/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Backoffice Menu
     * Redirige al nuevo Admin Controller
     */
    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminShippingCalculator')
        );
    }

    /* ============================================================
     * Hooks
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
     * Métodos obligatorios pero NO usados (cálculo real va en servicios)
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