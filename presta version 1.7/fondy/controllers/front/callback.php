<?php

require_once(dirname(__FILE__) . '../../../fondy.php');
require_once(dirname(__FILE__) . '../../../fondy.cls.php');

class FondyCallbackModuleFrontController extends ModuleFrontController
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
            if (empty($callback))
                die('Bad request');
            $_POST = array();
            foreach ($callback as $key => $val) {
                $_POST[$key] = $val;
            }
        }
        try {
            if ($_POST['order_status'] == FondyCls::ORDER_DECLINED or $_POST['order_status'] == FondyCls::ORDER_EXPIRED) {
                list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));
                exit('Order declined');
            }

            $fondy = new Fondy();
            $settings = array(
                'merchant_id' => $fondy->getOption('merchant'),
                'secret_key' => $fondy->getOption('secret_key')
            );

            list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
            $order = new Order($orderId);

            if ((int)$order->getCurrentState() == (int)Configuration::get('PS_OS_PAYMENT')) {
                PrestaShopLogger::addLog(
                    sprintf(
                        'Order id %s current state %s = expected state %s',
                        $order->id,
                        $order->getCurrentState(),
                        1
                    ),
                    3
                );
                die('State is already Paid');
            }

            $isPaymentValid = FondyCls::isPaymentValid($settings, $_POST);
            if ($isPaymentValid !== true) {
                exit($isPaymentValid);
            } else {
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