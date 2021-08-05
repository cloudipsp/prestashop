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

class FondyCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    /**
     * callback handler
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $requestBody = array();
        foreach ($_POST as $key => $val) {
            $requestBody[$key] = Tools::getValue($key);
        }
        if (empty($requestBody)) {
            $json_callback = json_decode(Tools::file_get_contents("php://input"));
            if (empty($json_callback)) {
                exit('No request.');
            }
            foreach ($json_callback as $key => $val) {
                $requestBody[$key] = $val;
            }
        }

        try {
            list($orderID,) = explode(FondyCls::ORDER_SEPARATOR, $requestBody['order_id']);
            $order = new Order((int)$orderID);
            $this->context->cart = new Cart($order->id_cart);

            FondyCls::setMerchantId(Configuration::get('FONDY_MERCHANT'));
            FondyCls::setSecretKey(Configuration::get('FONDY_SECRET_KEY'));

            if (($isRequestValid = FondyCls::validateRequest($requestBody)) !== true) {
                throw new Exception($isRequestValid);
            }

            $fOrder = new FondyOrder($requestBody['order_id']);

            // purchase and capture callback are identical (-_Q)
            if ($fOrder->status == 'approved' && $fOrder->last_tran_type == 'capture')
                exit('capture callback');

            $fOrder->last_tran_type = $requestBody['tran_type'];
            $fOrder->status = $requestBody['order_status'];
            $fOrder->payment_id = $requestBody['payment_id'];
            $fOrder->save();

            if ((int)$order->getCurrentState() == (int)Configuration::get('FONDY_SUCCESS_STATUS_ID')) {
                $message = sprintf('Order current state %s. Expected state - %s.', $order->getCurrentState(), Configuration::get('FONDY_OS_PROCESSING'));
                PrestaShopLogger::addLog($message, 3, null, Order::class, $order->id, true);
                throw new Exception('State is already Paid');
            }

            $history = new OrderHistory();
            $history->id_order = $order->id;

            switch ($requestBody['order_status']){
                case FondyCls::ORDER_APPROVED:
                    $history->changeIdOrderState((int)Configuration::get('FONDY_SUCCESS_STATUS_ID'), $order->id);
                    $history->addWithemail(true, ['order_name' => $order->id]);
                    $this->addOrderPaymentTransactionId($order, $requestBody['payment_id']);
                    $responseMsg = 'OK';
                    break;
                case FondyCls::ORDER_EXPIRED:
                case FondyCls::ORDER_DECLINED:
                    $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $order->id);
                    $history->addWithemail(true, ['order_name' => $order->id]);
                    $responseMsg = 'Order declined/expired';
                    break;
                default: $responseMsg = 'unhandled fondy order status';
            }
        } catch (Exception $e) {
            exit($e->getMessage());
        }

        exit($responseMsg);
    }

    /**
     * try add transaction_id to order payment
     *
     * @param $order
     * @param $transactionID
     */
    private function addOrderPaymentTransactionId($order, $transactionID)
    {
        $orderPayments = $order->getOrderPayments();

        if ($orderPayments[0]->payment_method === $this->module->displayName){
            $orderPayment = $orderPayments[0];
            $orderPayment->transaction_id = $transactionID;
            $orderPayment->save();
        }
    }
}


