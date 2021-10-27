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

if (!defined('_PS_VERSION_')) {
    exit;
}

class FondyResultModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (Tools::getValue('order_status') == FondyCls::ORDER_DECLINED) {
            $this->errors[] = Tools::displayError('Order declined!');
        }
        if (Tools::getValue('order_status') == FondyCls::ORDER_PROCESSING) {
            $this->errors[] = Tools::displayError('Payment proccesing!');
        }
        if (Tools::getValue('order_status') == FondyCls::ORDER_EXPIRED) {
            $this->errors[] = Tools::displayError('Order expired!');
        }

        list($orderID,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
        $order = new Order((int)$orderID);
        $this->context->cart = new Cart($order->id_cart);
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0
            || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (empty($this->errors)) {
            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart=' . (int)$order->id_cart .
                '&id_module=' . (int)$this->module->id .
                '&id_order=' . (int)$order->id .
                '&key=' . $order->secure_key
            );
        } else {
            $this->redirectWithNotifications('index.php?controller=order');
        }
    }
}
