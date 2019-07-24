<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM
 * @copyright  2014-2019 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.0.0
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Fondy extends PaymentModule
{
    private $settingsList = array(
        'FONDY_MERCHANT',
        'FONDY_SECRET_KEY',
        'FONDY_BACK_REF'
    );
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'fondy';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Fondy';
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->is_eu_compatible = 1;

        parent::__construct();
        $this->displayName = $this->l('Fondy Payments');
        $this->description = $this->l('Fondy is a payment platform whose main function is to provide internet acquiring. Payment gateway supports EUR, USD, PLN, GBP, UAH, RUB and +100 other currencies.');
        $this->confirmUninstall = $this->l('Are you want to remove the module?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions');
    }

    public function uninstall()
    {
        foreach ($this->settingsList as $val) {
            if (!Configuration::deleteByName($val)) {
                return false;
            }
        }
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    public function getOption($name)
    {
        return Configuration::get("FONDY_" . Tools::strtoupper($name));
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $err = '';
        if (((bool)Tools::isSubmit('submitFondyModule')) == true) {
            $this->_postValidation();
            if (!sizeof($this->_postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $err .= $this->displayError($err);
                }
            }
        }

        return $err.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitFondyModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Please specify the Fondy account details for customers'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-user"></i>',
                        'desc' => $this->l('Enter a merchant id'),
                        'name' => 'FONDY_MERCHANT',
                        'label' => $this->l('Merchant ID'),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'name' => 'FONDY_SECRET_KEY',
                        'desc' => $this->l('Enter a secret key'),
                        'label' => $this->l('Secret key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'FONDY_MERCHANT' => Configuration::get('FONDY_MERCHANT', null),
            'FONDY_SECRET_KEY' => Configuration::get('FONDY_SECRET_KEY', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }


    private function _postValidation()
    {
        if (Tools::isSubmit('submitFondyModule')) {
            if (empty(Tools::getValue('FONDY_MERCHANT'))) {
                $this->_postErrors[] = $this->l('Merchant ID is required.');
            }
            if (empty(Tools::getValue('FONDY_SECRET_KEY'))) {
                $this->_postErrors[] = $this->l('Secret key is required.');
            }
        }
    }

    # Display

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return false;
        }
        if (!$this->_checkCurrency($params['cart'])) {
            return false;
        }

        $this->context->smarty->assign(array(
            'this_path' => $this->_path,
            'id' => (int)$params['cart']->id,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'this_description' => $this->l('Pay via payment system Fondy')
        ));

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->l('Pay via Fondy'))
            ->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    'redirect',
                    array('id_cart' => (int)$params['cart']->id),
                    true
                )
            )
            ->setAdditionalInformation(
                $this->context->smarty->fetch('module:fondy/views/templates/front/fondy.tpl')
            );

        return array($newOption);
    }

    private function _checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
}
