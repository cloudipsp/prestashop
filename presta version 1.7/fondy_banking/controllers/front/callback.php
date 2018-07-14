<?php

require_once(dirname(__FILE__) . '../../../fondy_banking.php');
require_once(dirname(__FILE__) . '../../../fondy.cls.php');

class fondy_bankingCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (empty($_POST)) {
            $callback = json_decode(file_get_contents("php://input"));
            if(empty($callback))
                die('Bad request');
            $_POST = array();
            foreach ($callback as $key => $val) {
                $_POST[$key] = $val;
            }
        }
        try {
            if ($_POST['order_status'] == FondyCls::ORDER_DECLINED) {
                list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));
                exit('Order declined');
            }

            $fondy = new fondy_banking();
            $settings = array(
                'merchant_id' => $fondy->getOption('merchant'),
                'secret_key' => $fondy->getOption('secret_key')
            );

            $isPaymentValid = FondyCls::isPaymentValid($settings, $_POST);
            if ($isPaymentValid !== true) {
                exit($isPaymentValid);
            } else {
                list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));
                exit('OK');
            }
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}