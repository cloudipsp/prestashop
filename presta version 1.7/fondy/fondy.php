<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM && DB
 * @copyright  2014-2021 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.2.0
 */
require_once(dirname(__FILE__) . '/classes/FondyOrder.php');
require_once(dirname(__FILE__) . '/classes/fondy.cls.php');

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Fondy extends PaymentModule
{
    private $configsList = array(
        'FONDY_MERCHANT',
        'FONDY_SECRET_KEY',
        'FONDY_BACK_REF',
        'FONDY_SUCCESS_STATUS_ID',
        'FONDY_PREAUTH',
        'FONDY_CONFIRM_PAYMENT_STATES_CONF',
        'FONDY_DECLINE_PAYMENT_STATES_CONF',
        'FONDY_SHOW_CARDS_LOGO',
    );

    protected $_html = '';
    protected $_postErrors = array();

    public function __construct()
    {
        $this->name = 'fondy';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.0';
        $this->author = 'Fondy';
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->is_eu_compatible = 1;
        $this->module_key = '180016d5b53f11d0d2833a98e137d9f0';

        parent::__construct();
        $this->displayName = $this->l('Fondy Payments');
        $this->description = $this->l(
            'Fondy is a payment platform whose main function is to provide internet acquiring. 
            Payment gateway supports EUR, USD, PLN, GBP, UAH, RUB and +100 other currencies.'
        );
        $this->confirmUninstall = $this->l('Are you want to remove the module?');
    }

    public function install()
    {
        return parent::install()
            && $this->installDB()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->registerHook('displayAdminOrderTabOrder')
            && $this->registerHook('displayAdminOrderContentOrder')
            && $this->registerHook('displayAdminOrderTabLink')
            && $this->registerHook('displayAdminOrderTabContent');
    }

    public function uninstall()
    {
        foreach ($this->configsList as $val) {
            if (!Configuration::deleteByName($val)) {
                return false;
            }
        }

        return $this->uninstallDB() && parent::uninstall();
    }

    public function installDB()
    {
        $return = true;
        $return &= Db::getInstance()->execute(
            '
                CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fondy_orders` (
				`id_cart` INT(10) UNSIGNED NOT NULL,
				`order_id` varchar(255) DEFAULT NULL,
				`status` varchar(15) DEFAULT NULL,
				`payment_id` int(10) DEFAULT NULL,
				`preauth` char(1) DEFAULT NULL,
				`checkout_url` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id_cart`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
        );

        return $return;
    }

    public function uninstallDB()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'fondy_orders`');
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitFondyModule')) == true) {
            $this->postValidation();
            if (!sizeof($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $error) {
                    $this->_html .= $this->displayError($error);
                }
            }
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
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

        return $helper->generateForm([$this->_getConfigMerchantForm(), $this->_getConfigStatusForm(), $this->_getConfigAdditionalForm()]);
    }


    /**
     * admin settings form part
     *
     * @return array[]
     */
    protected function _getConfigMerchantForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Please specify the Fondy account details for customers'),
                    'icon' => 'icon-cog',
                ],
                'input' => [
                    [
                        'col' => 1,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-user"></i>',
                        'name' => 'FONDY_MERCHANT',
                        'label' => $this->l('Merchant ID'),
                        'required' => true,
                        'hint' => [
                            $this->l('Can be found in the Fondy portal (section \'Merchant Settings\' → \'Technical settings\')'),
                        ],
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'name' => 'FONDY_SECRET_KEY',
                        'label' => $this->l('Secret key'),
                        'required' => true,
                        'hint' => [
                            $this->l('Can be found in the Fondy portal (section \'Merchant Settings\' → \'Technical settings\')'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * admin settings form part
     *
     * @return array[]
     */
    protected function _getConfigStatusForm()
    {
        $orderStates = OrderState::getOrderStates($this->context->language->id);

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Statuses config'),
                    'icon' => 'icon-time',
                ],
                'input' => [
                    [
                        'col' => 3,
                        'type' => 'select',
                        'name' => 'FONDY_SUCCESS_STATUS_ID',
                        'label' => $this->l('Status after success payment'),
                        'options' => [
                            'query' => $orderStates,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ]
                    ],

                    [
                        'type' => 'switch',
                        'label' => $this->l('Pre-authorization mode'),
                        'name' => 'FONDY_PREAUTH',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'y',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id' => 'n',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ],
                        'hint' => [
                            $this->l('Funds are only blocked on the client’s card without financial write-off from the client’s account.'),
                        ],
                    ],

                    [
                        'type' => 'select',
                        'name' => 'FONDY_CONFIRM_PAYMENT_STATES_CONF[]',
                        'label' => $this->l('Order states for confirm customer payment'),
                        'multiple' => true,
                        'class' => 'chosen',
                        'options' => [
                            'query' => $orderStates,
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ],
                        'hint' => [$this->l('Works only when pre-authorization mode is enabled!')],
                    ],
                    [
                        'type' => 'select',
                        'name' => 'FONDY_DECLINE_PAYMENT_STATES_CONF[]',
                        'label' => $this->l('Order states for decline customer payment'),
                        'multiple' => true,
                        'class' => 'chosen',
                        'placeholder' => 'OLOLE',
                        'options' => [
                            'query' => $orderStates,
                            'id' => 'id_order_state',
                            'name' => 'name',
                        ],
                        'hint' => [$this->l('Works only when pre-authorization mode is enabled!')],
                    ],
                ],
            ],
        ];

    }

    /**
     * admin settings form part
     *
     * @return array[]
     */
    protected function _getConfigAdditionalForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Additional'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Visa/MasterCard logo'),
                        'name' => 'FONDY_SHOW_CARDS_LOGO',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'show_cards',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id' => 'hide_cards',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ],
                        'hint' => [
                            $this->l('Show Visa/MasterCard logo on payment page.'),
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $configs = [];

        foreach ($this->configsList as $configName) {
            $value = Configuration::get($configName);

            if (is_array(json_decode($value, true))) {
                $configs[$configName . '[]'] = json_decode($value, true);
            } else {
                $configs[$configName] = Configuration::get($configName);
            }
        }

        return $configs;
    }

    /**
     * Save form data.
     */
    protected function _postProcess()
    {
        foreach ($this->configsList as $key) {
            $value = Tools::getValue($key);
            Configuration::updateValue($key, is_array($value) ? json_encode($value) : $value);
        }

        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * save setting form validation
     */
    private function postValidation()
    {
        if (Tools::isSubmit('submitFondyModule')) {
            $merchant_id = Tools::getValue('FONDY_MERCHANT');
            $secret_key = Tools::getValue('FONDY_SECRET_KEY');
            if (empty($merchant_id)) {
                $this->_postErrors[] = $this->l('Merchant ID is required.');
            }
            if (!is_numeric($merchant_id)) {
                $this->_postErrors[] = $this->l('Merchant ID must be numeric.');
            }
            if (empty($secret_key)) {
                $this->_postErrors[] = $this->l('Secret key is required.');
            }
            if (is_numeric($secret_key)) {
                $this->_postErrors[] = $this->l('Secret key is invalid.');
            }
        }
    }

    /**
     * display payment option select in Cart
     *
     * @param $params
     * @return false|PaymentOption[]
     * @throws SmartyException
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return false;
        }
        if (!$this->checkCurrency($params['cart'])) {
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

        if (Configuration::get('FONDY_SHOW_CARDS_LOGO')) {
            $newOption->setLogo(Tools::getHttpHost(true) . $this->_path . 'views/img/fondy_logo_cards.svg');
        }

        return array($newOption);
    }

    private function checkCurrency($cart)
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

    /**
     * make capture/reverse on change order status
     *
     * @param $params
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $cart_id = Cart::getCartIdByOrderId($params['id_order']);
        $fOrder = new FondyOrder($cart_id);

        if (!$fOrder->order_id || !($params['newOrderStatus'] instanceof OrderState) || $fOrder->preauth != 'Y') {
            return;
        }

        $captureOrderStatusIDs = json_decode(Configuration::get('FONDY_CONFIRM_PAYMENT_STATES_CONF'), true);
        $refundOrderStatusIDs = json_decode(Configuration::get('FONDY_DECLINE_PAYMENT_STATES_CONF'), true);

        try {
            $order = new Order($params['id_order']);

            $requestFields = [
                'order_id' => $fOrder->order_id,
                'amount' => (int)round($order->getTotalPaid() * 100),
                'currency' => (new Currency($order->id_currency))->iso_code,
            ];

            FondyCls::setMerchantId(Configuration::get('FONDY_MERCHANT'));
            FondyCls::setSecretKey(Configuration::get('FONDY_SECRET_KEY'));

            if (in_array($params['newOrderStatus']->id, $captureOrderStatusIDs)) {
                FondyCls::capture($requestFields);
                PrestaShopLogger::addLog('Fondy: capture successful!', 1, null, 'Order', $params['id_order'], true);
            }

            if (in_array($params['newOrderStatus']->id, $refundOrderStatusIDs)) {
                FondyCls::reverse($requestFields);
                PrestaShopLogger::addLog('Fondy: refund successful!', 1, null, 'Order', $params['id_order'], true);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 2, null, 'Order', $params['id_order'], true);
        }
    }

    /**
     * displays new tab link on the admin order view page
     *
     * @param $params
     * @return false|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderTabLink($params)
    {
        $order = new Order($params['id_order']);
        $fOrder = new FondyOrder($order->id_cart);

        if ($order->module != $this->name || !$fOrder->order_id) {
            return '';
        }

        return $this->display(__FILE__, 'views/templates/hook/adminOrderTabLink.tpl');
    }

    /**
     * displays tab content on the admin order view page
     *
     * @param $params
     * @return false|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminOrderTabContent($params)
    {
        $order = new Order($params['id_order']);
        $fOrder = new FondyOrder($order->id_cart);

        if ($order->module != $this->name || !$fOrder->order_id) {
            return '';
        }

        $this->context->smarty->assign(['fondy_checkout_url' => $fOrder->checkout_url]);

        return $this->display(__FILE__, 'views/templates/hook/adminOrderTabContent.tpl');
    }

    /**
     * @param $params
     * @return false|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @deprecated since PS version 1.7.7
     */
    public function hookDisplayAdminOrderTabOrder($params)
    {
        $params['id_order'] = $params['order']->id;
        return $this->hookDisplayAdminOrderTabLink($params);
    }

    /**
     * @param $params
     * @return false|string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @deprecated since PS version 1.7.7
     */
    public function hookDisplayAdminOrderContentOrder($params)
    {
        $params['id_order'] = $params['order']->id;
        return $this->hookDisplayAdminOrderTabContent($params);
    }
}
