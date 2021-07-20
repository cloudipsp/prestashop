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

        $cart = $this->context->cart;

        try {
            FondyCls::setMerchantId(Configuration::get('FONDY_MERCHANT'));
            FondyCls::setSecretKey(Configuration::get('FONDY_SECRET_KEY'));

            $fields = [
                'order_id' => $cart->id . FondyCls::ORDER_SEPARATOR . time(),
                'order_desc' => $this->l('Cart pay №') . $cart->id,
                'amount' => round($cart->getOrderTotal() * 100),
                'currency' => $this->context->currency->iso_code,
                'server_callback_url' => $this->context->link->getModuleLink('fondy', 'callback'),
                'response_url' => $this->context->link->getModuleLink('fondy', 'result'),
                'sender_email' => $this->context->customer->email,
                'lang' => $this->context->language->iso_code,
                'lifetime' => 604800,
                'preauth' => Configuration::get('FONDY_PREAUTH') ? 'Y' : 'N',
            ];

            $checkoutUrl = FondyCls::getCheckoutUrl($fields);

            $fOrder = new FondyOrder($cart->id);
            $fOrder->id_cart = $cart->id;
            $fOrder->order_id = $fields['order_id'];
            $fOrder->preauth = $fields['preauth'];
            $fOrder->checkout_url = $checkoutUrl;
            $fOrder->save();
        } catch (Exception $e){
            PrestaShopLogger::addLog($e->getMessage(), 3,null, 'Cart', $cart->id, true);
            $this->errors[] = $e->getMessage();
            $this->redirectWithNotifications('index.php?controller=order');
        }

        Tools::redirect($checkoutUrl);
    }
}
