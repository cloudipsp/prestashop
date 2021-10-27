<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM && DB
 * @copyright  2014-2021 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.2.2
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
        $this->version = '1.2.2';
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
            && $this->registerHook('displayAdminOrderTabContent')
            && $this->registerOrderStates();
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
				`order_id` varchar(255) NOT NULL,
				`id_cart` INT(10) UNSIGNED NOT NULL,
				`total` INT(11) DEFAULT NULL,
				`status` varchar(15) DEFAULT NULL,
				`payment_id` int(10) DEFAULT NULL,
				`last_tran_type` varchar(255) DEFAULT NULL,
				`preauth` char(1) DEFAULT NULL,
				`checkout_url` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`order_id`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
        );

        return $return;
    }

    public function uninstallDB()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'fondy_orders`');
    }

    /**
     * add fondy custom order status
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function registerOrderStates()
    {
        // при редиректе на страницу оплаты
        if (!Configuration::get('FONDY_OS_PROCESSING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_PROCESSING')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Processing / Awaiting payment',
                'uk' => 'В обробці / Очікування оплати',
                'ru' => 'В обработке / Ожидание оплаты',
            ]);
            $order_state->logable = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#ff6d01';
            if ($order_state->add()){
                $this->addOsImage('processing', $order_state->id);
                Configuration::updateValue('FONDY_OS_PROCESSING', $order_state->id);
            }
        }

        // callback после capture на всю сумму при помощи смены статуса заказа в ПС
        if (!Configuration::get('FONDY_OS_CAPTURED')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_CAPTURED')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Sent / Issued / FULL PAYMENT',
                'uk' => 'Відправлено / Видано / ПОВНА ОПЛАТА',
            ]);
            $order_state->logable = true;
            $order_state->invoice = true;
            $order_state->shipped = true;
            $order_state->paid = true;
            $order_state->delivery = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#38761c';
            if ($order_state->add()){
                $this->addOsImage('captured', $order_state->id);
                Configuration::updateValue('FONDY_OS_CAPTURED', $order_state->id);
            }
        }

        // callback после capture на всю сумму при помощи смены статуса заказа в ПС если сумма заказа поменялась
        if (!Configuration::get('FONDY_OS_CAPTURED_PART')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_CAPTURED_PART')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Shipment is incomplete / FULL PAYMENT',
                'uk' => 'Відвантаження неповне / ПОВНА ОПЛАТА',
            ]);
            $order_state->logable = true;
            $order_state->shipped = true;
            $order_state->paid = true;
            $order_state->delivery = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#c9daf8';
            if ($order_state->add()){
                $this->addOsImage('captured_part', $order_state->id);
                Configuration::updateValue('FONDY_OS_CAPTURED_PART', $order_state->id);
            }
        }

        // callback после capture c МП
        if (!Configuration::get('FONDY_OS_CAPTURED_MP')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_CAPTURED_MP')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Payment of the order FULL (manual)',
                'uk' => 'Оплата замовлення повна (manual)',
            ]);
            $order_state->logable = true;
            $order_state->invoice = true;
            $order_state->hidden = true;
            $order_state->paid = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#00ff01';
            if ($order_state->add()){
                $this->addOsImage('captured_mp', $order_state->id);
                Configuration::updateValue('FONDY_OS_CAPTURED_MP', $order_state->id);
            }
        }

        // callback после частичной capture c МП
        if (!Configuration::get('FONDY_OS_CAPTURED_PART_MP')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_CAPTURED_PART_MP')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'uk' => 'Оплата замовлення неповна (manual)',
                'en' => 'Payment of the order is incomplete (manual)'
            ]);
            $order_state->logable = true;
            $order_state->hidden = true;
            $order_state->shipped = true;
            $order_state->paid = true;
            $order_state->delivery = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#fbbc04';
            if ($order_state->add()){
                $this->addOsImage('captured_part_mp', $order_state->id);
                Configuration::updateValue('FONDY_OS_CAPTURED_PART_MP', $order_state->id);
            }
        }

        // callback о полном reverse с PS
        if (!Configuration::get('FONDY_OS_REVERSED')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_REVERSED')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Full refund',
                'uk' => 'Повне повернення грошей',
            ]);
            $order_state->logable = true;
            $order_state->invoice = true;
            $order_state->shipped = true;
            $order_state->paid = true;
            $order_state->delivery = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#dc143c';
            if ($order_state->add()){
                $this->addOsImage('reversed', $order_state->id);
                Configuration::updateValue('FONDY_OS_REVERSED', $order_state->id);
            }
        }

        // callback о полном reverse с МП
        if (!Configuration::get('FONDY_OS_REVERSED_MP')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_REVERSED_MP')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Full refund (manual)',
                'uk' => 'Повне повернення грошей (manual)',
            ]);
            $order_state->logable = true;
            $order_state->invoice = true;
            $order_state->hidden = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#9900ff';
            if ($order_state->add()){
                $this->addOsImage('reversed_mp', $order_state->id);
                Configuration::updateValue('FONDY_OS_REVERSED_MP', $order_state->id);
            }
        }

        // callback о частичном reverse с МП
        // если сделать частичный capture c МП, а за тем частичный\полный reverse - то тоже получим этот статус
        if (!Configuration::get('FONDY_OS_REVERSED_PART_MP')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('FONDY_OS_REVERSED_PART_MP')))) {
            $order_state = new OrderState();
            $order_state->name = $this->getOsLocalizedNames([
                'en' => 'Partial refund (manual)',
                'uk' => 'Часткове повернення грошей (manual)',
            ]);
            $order_state->logable = true;
            $order_state->invoice = true;
            $order_state->hidden = true;
            $order_state->module_name = $this->name;
            $order_state->color = '#a64d79';
            if ($order_state->add()){
                $this->addOsImage('reversed_part_mp', $order_state->id);
                Configuration::updateValue('FONDY_OS_REVERSED_PART_MP', $order_state->id);
            }
        }

        return true;
    }

    /**
     * @param $stateLang
     * @return array
     */
    public function getOsLocalizedNames($stateLang)
    {
        $osName = [];

        foreach (Language::getLanguages() as $language) {
            $langIsoCode = strtolower($language['iso_code']);
            $osName[$language['id_lang']] = array_key_exists($langIsoCode, $stateLang) ?
                $stateLang[$langIsoCode] :
                $stateLang['en'];
        }

        return $osName;
    }

    public function addOsImage($fileName, $stateID)
    {
        $source = _PS_MODULE_DIR_ . $this->name . "/views/img/order_states/$fileName.gif";
        $destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$stateID . '.gif';
        copy($source, $destination);
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
            $this->_postValidation();
            if (!sizeof($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $error) {
                    $this->_html .= $this->displayError($error);
                }
            }
        }

        $this->_html .= $this->_renderForm();

        return $this->_html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    private function _renderForm()
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
            'fields_value' => $this->_getConfigFormValues(), /* Add values for your inputs */
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
    private function _getConfigMerchantForm()
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
    private function _getConfigStatusForm()
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
    private function _getConfigAdditionalForm()
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
    private function _getConfigFormValues()
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
    private function _postProcess()
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
    private function _postValidation()
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
        $fOrder = FondyOrder::getByPSOrderId((int)$params['id_order']);

        if (!$fOrder->order_id || !($params['newOrderStatus'] instanceof OrderState)) {
            return;
        }

        $captureOrderStatusIDs = json_decode(Configuration::get('FONDY_CONFIRM_PAYMENT_STATES_CONF'), true);
        $refundOrderStatusIDs = json_decode(Configuration::get('FONDY_DECLINE_PAYMENT_STATES_CONF'), true);

        try {
            $order = new Order($params['id_order']);
            $orderTotal = (int)round($order->getTotalPaid() * 100);

            $requestFields = [
                'order_id' => $fOrder->order_id,
                'amount' => $fOrder->total,
                'currency' => (new Currency($order->id_currency))->iso_code,
            ];

            FondyCls::setMerchantId(Configuration::get('FONDY_MERCHANT'));
            FondyCls::setSecretKey(Configuration::get('FONDY_SECRET_KEY'));

            // capture
            if (in_array($params['newOrderStatus']->id, $captureOrderStatusIDs) && $fOrder->preauth == 'Y') {
                FondyCls::capture($requestFields);
                $fOrder->last_tran_type = 'capture';
                $fOrder->save();
                PrestaShopLogger::addLog('Fondy: capture successful!', 1, null, 'Order', $params['id_order'], true);
            }

            // reverse
            if (in_array($params['newOrderStatus']->id, $refundOrderStatusIDs)) {
                if ($orderTotal != $fOrder->total) { // temp solution
                    PrestaShopLogger::addLog(
                        sprintf('Fondy: order not reversed! Reason: order amount has been changed (expected: %s).', $fOrder->total / 100),
                        2,
                        null,
                        'Order',
                        $params['id_order'],
                        true
                    );
                    return;
                }

                FondyCls::reverse($requestFields);
                $fOrder->last_tran_type = 'reverse';
                $fOrder->save();
                PrestaShopLogger::addLog('Fondy: refund successful!', 1, null, 'Order', $params['id_order'], true);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3, null, 'Order', $params['id_order'], true);
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
        $fOrder = FondyOrder::getByPSOrderId($order->id);

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
        $fOrder = FondyOrder::getByPSOrderId($order->id);

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
