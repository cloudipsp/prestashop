<?php

require_once(dirname(__FILE__).'../../../oplata.php');
require_once(dirname(__FILE__).'../../../oplata.cls.php');

class OplataCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess() {
        try {

            if ($_POST['order_status'] == OplataCls::ORDER_DECLINED) {
                exit('Order declined');
            }

            $oplata = new Oplata();
            $settings = array(
                'merchant_id' => $oplata->getOption('merchant'),
                'secret_key' => $oplata->getOption('secret_key')
            );

            $isPaymentValid = OplataCls::isPaymentValid($settings, $_POST);
            if ($isPaymentValid !== true) {
                exit($isPaymentValid);
            }

            list($orderId,) = explode(OplataCls::ORDER_SEPARATOR, $_POST['order_id']);
            $history = new OrderHistory();
            $history->id_order = $orderId;
            $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $orderId);
            $history->addWithemail(true, array(
                'order_name' => $orderId
            ));

            exit('OK');
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}