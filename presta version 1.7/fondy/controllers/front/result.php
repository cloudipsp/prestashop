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
require_once(dirname(__FILE__) . '../../../fondy.cls.php');

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
            $this->errors[] = Tools::displayError('Order declined');
        }
        if (Tools::getValue('order_status') == 'processing') {
            $this->errors[] = Tools::displayError('Payment proccesing');
        }
        if (Tools::getValue('order_status') == 'expired') {
            $this->errors[] = Tools::displayError('Order expired');
        }

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
                'index.php?controller=order-confirmation&id_cart=' . $cart->id .
                '&id_module=' . $this->module->id .
                '&id_order=' . $this->module->currentOrder .
                '&key=' . $customer->secure_key
            );
        } else {
            $this->redirectWithNotifications('index.php?controller=order');
        }
    }
}
