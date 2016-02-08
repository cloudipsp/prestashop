<?php

require_once(dirname(__FILE__).'/fondy.php');
require_once(dirname(__FILE__).'/fondy.cls.php');

class FondyCallbackModuleFrontController
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
			If (empty($_POST)){
			 $fap = json_decode(file_get_contents("php://input"));
        $_POST=array();
        foreach($fap as $key=>$val)
        {
          $_POST[$key] =  $val ;
        }
		}
        try {

            if ($_POST['order_status'] == FondyCls::ORDER_DECLINED) {
                exit('Order declined');
            }

            $fondy = new Fondy();
            $settings = array(
                'merchant_id' => $fondy->getOption('merchant'),
                'secret_key' => $fondy->getOption('secret_key')
            );

            $isPaymentValid = FondyCls::isPaymentValid($settings, $_POST);
            if ($isPaymentValid !== true) {
                exit($isPaymentValid);
            }

            list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
            $history = new OrderHistory();
            $history->id_order = $orderId;

            $id_order_state = _PS_OS_PAYMENT_;

            $history->changeIdOrderState(intval($id_order_state), intval($orderId));
            $history->addWithemail(true, "");

            exit('OK');
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}

$callback = new FondyCallbackModuleFrontController();
$callback->postProcess();