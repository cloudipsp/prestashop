<?php
/**
 * 2014-2019 Fondy
 *
 *  @author DM
 *  @copyright  2014-2019 Fondy
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @version    1.0.0
 */

require_once(dirname(__FILE__) . '../../../fondy.php');
require_once(dirname(__FILE__) . '../../../fondy.cls.php');

class FondyResultModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {

        $fondy = new Fondy();

        if (Tools::getValue('order_status') == FondyCls::ORDER_DECLINED) {
            $this->errors[] = Tools::displayError('Order declined');
        }
        if (Tools::getValue('order_status') == 'expired') {
            $this->errors[] = Tools::displayError('Order expired');
        }
        if (Tools::getValue('order_status') == 'processing') {
            $this->errors[] = Tools::displayError('Payment proccesing');
        }

        $settings = array(
            'merchant_id' => $fondy->getOption('merchant'),
            'secret_key' => $fondy->getOption('secret_key')
        );

        $isPaymentValid = FondyCls::isPaymentValid($settings, $_POST);
        if ($isPaymentValid !== true) {
            $this->errors[] = Tools::displayError($isPaymentValid);
        }

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (empty($this->errors)) {
            list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, Tools::getValue('order_id'));
            $order = new Order($orderId);
            $history = new OrderHistory();

            $history->id_order = $order->id;
            $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $orderId);
            $history->addWithemail(true, array(
                'order_name' => $orderId
            ));

            Tools::redirect(
            'index.php?controller=order-confirmation&id_cart=' . $order->id_cart .
                '&id_module=' . $this->module->id .
                '&id_order=' . $order->id .
                '&key=' . $customer->secure_key
            );
        }
    }
}
