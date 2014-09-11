<?php

require_once(dirname(__FILE__).'../../../oplata.php');
require_once(dirname(__FILE__).'../../../oplata.cls.php');

class OplataResultModuleFrontController extends ModuleFrontController {
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess() {

        $oplata = new Oplata();

        if ($_POST['order_status'] == OplataCls::ORDER_DECLINED) {
            $this->errors[] = Tools::displayError('Order declined');
        }

        $settings = array(
            'merchant_id' => $oplata->getOption('merchant'),
            'secret_key' => $oplata->getOption('secret_key')
        );

        $isPaymentValid = OplataCls::isPaymentValid($settings, $_POST);
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

            list($orderId,) = explode(OplataCls::ORDER_SEPARATOR, $_POST['order_id']);
            $history = new OrderHistory();
            $history->id_order = $orderId;
            $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $orderId);
            $history->addWithemail(true, array(
                'order_name' => $orderId
            ));

            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
        }
    }
}