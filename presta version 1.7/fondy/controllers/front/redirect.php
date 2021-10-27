<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM
 * @copyright  2014-2019 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.0.0
 */

require_once(dirname(__FILE__) . '../../../fondy.php');
require_once(dirname(__FILE__) . '../../../classes/fondy.cls.php');
require_once(dirname(__FILE__) . '../../../classes/FondyOrder.php');

if (!defined('_PS_VERSION_')) {
    exit;
}

class FondyRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        try {
            $cart = $this->context->cart;
            $orderTotal = $cart->getOrderTotal();
            $this->module->validateOrder(
                (int)$cart->id,
                Configuration::get('FONDY_OS_PROCESSING'),
                $orderTotal,
                $this->module->displayName,
                null,
                [],
                null,
                false,
                $cart->secure_key
            );

            $fields = [
                'order_id' => $this->module->currentOrder . FondyCls::ORDER_SEPARATOR . time(),
                'order_desc' => $this->l('Order pay №') . $this->module->currentOrder,
                'amount' => (int)round($orderTotal * 100),
                'currency' => $this->context->currency->iso_code,
                'server_callback_url' => $this->context->link->getModuleLink('fondy', 'callback'),
                'response_url' => $this->context->link->getModuleLink('fondy', 'result'),
                'sender_email' => $this->context->customer->email,
                'lang' => $this->context->language->iso_code,
                'preauth' => Configuration::get('FONDY_PREAUTH') ? 'Y' : 'N',
                'reservation_data' => $this->_getReservationData(),
            ];

            FondyCls::setMerchantId(Configuration::get('FONDY_MERCHANT'));
            FondyCls::setSecretKey(Configuration::get('FONDY_SECRET_KEY'));
            $checkoutUrl = FondyCls::getCheckoutUrl($fields);

            $fOrder = new FondyOrder($fields['order_id']);
            $fOrder->order_id = $fields['order_id'];
            $fOrder->id_cart = $cart->id;
            $fOrder->total = $fields['amount'];
            $fOrder->preauth = $fields['preauth'];
            $fOrder->checkout_url = $checkoutUrl;
            $fOrder->save();
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3, null, Cart::class, $cart->id, true);
            $this->errors[] = $e->getMessage();
            $this->redirectWithNotifications('index.php?controller=order');
        }

        Tools::redirect($checkoutUrl);
    }

    /**
     * Fondy antifraud parameters
     *
     * @return string
     */
    private function _getReservationData()
    {
        $customer = new Customer($this->context->cart->id_customer);
        $customerInfo = new Address($this->context->cart->id_address_delivery);

        $reservationData = [
            'customer_zip' => $customerInfo->postcode,
            'customer_name' => $customer->firstname . ' ' . $customer->lastname,
            'customer_address' => $customerInfo->address1 . ' ' . $customerInfo->city,
            'customer_country' => $customerInfo->country,
            'phonemobile' => $customerInfo->phone,
            'account' => $customer->email,
            'cms_name' => 'Prestashop',
            'cms_version' => _PS_VERSION_,
            'cms_plugin_version' => $this->module->version,
            'path' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'products' => $this->_getReservationDataProducts($this->context->cart->getProducts()),
        ];

        return base64_encode(json_encode($reservationData));
    }

    /**
     * data to PRRO
     *
     * @param $products
     * @return array
     */
    private function _getReservationDataProducts($products)
    {
        $reservationDataProducts = [];

        foreach ($products as $product) {
            $reservationDataProducts[] = [
                'id' => $product['id_product'],
                'name' => $product['name'],
                'price' => $product['price'],
                'total_amount' => $product['total'],
                'quantity' => $product['quantity'],
            ];
        }

        return $reservationDataProducts;
    }
}
