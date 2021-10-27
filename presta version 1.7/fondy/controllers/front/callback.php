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

            if (($isRequestValid = FondyCls::validateRequest($requestBody)) !== true)
                throw new Exception($isRequestValid);

            if (!empty($requestBody['reversal_amount']) && $requestBody['reversal_amount'] > 0) // its part reverse
                $requestBody['order_status'] = FondyCls::ORDER_REVERSED;

            $fOrder = new FondyOrder($requestBody['order_id']);
            $fOrder->status = $requestBody['order_status'];
            $fOrder->payment_id = $requestBody['payment_id'];
            $history = new OrderHistory();
            $history->id_order = $order->id;
            $additionalInfo = isset($requestBody['additional_info']) ? json_decode($requestBody['additional_info']) : false;

            switch ($requestBody['order_status']) {
                case FondyCls::ORDER_APPROVED:
                    if ($fOrder->last_tran_type == 'capture') { // PS capture callback
                        $newOrderStateKey = (int)round($order->getTotalPaid() * 100) == $fOrder->total ?
                            'FONDY_OS_CAPTURED' : 'FONDY_OS_CAPTURED_PART';
                        $history->changeIdOrderState((int)Configuration::get($newOrderStateKey), $order->id);
                        $history->add();
                        $responseMsg = "Order state changed to $newOrderStateKey";
                    } elseif ($additionalInfo && $additionalInfo->capture_status == 'captured') { // MP capture callback
                        $newOrderStateKey = $additionalInfo->capture_amount == $fOrder->total / 100 ?
                            'FONDY_OS_CAPTURED_MP' : 'FONDY_OS_CAPTURED_PART_MP';
                        $fOrder->last_tran_type = 'capture';
                        $history->changeIdOrderState((int)Configuration::get($newOrderStateKey), $order->id);
                        $history->add();
                        $responseMsg = 'Captured!';
                    } else { // purchase callback
                        $fOrder->last_tran_type = $requestBody['tran_type'];
                        $history->changeIdOrderState((int)Configuration::get('FONDY_SUCCESS_STATUS_ID'), $order->id);
                        $history->addWithemail(true, ['order_name' => $order->id]);
                        $this->addOrderPaymentTransactionId($order, $requestBody['payment_id']);
                        $responseMsg = 'OK';
                    }

                    break;
                case FondyCls::ORDER_REVERSED:
                    $fullReverse = $requestBody['amount'] == $requestBody['reversal_amount'];

                    if ($fOrder->last_tran_type == 'reverse') { // PS reverse callback
                        $responseMsg = 'PS reverse callback or MP repeated reverse.';
                        if ($fullReverse) {
                            $history->changeIdOrderState((int)Configuration::get('FONDY_OS_REVERSED'), $order->id);
                            $history->add();
                        }
                    } else { // MP reverse callback
                        $newOrderStateKey = $fullReverse ? 'FONDY_OS_REVERSED_MP' : 'FONDY_OS_REVERSED_PART_MP';
                        $fOrder->last_tran_type = 'reverse';
                        $history->changeIdOrderState((int)Configuration::get($newOrderStateKey), $order->id);
                        $history->addWithemail(true, ['order_name' => $order->id]);
                        $responseMsg = 'Reversed!';
                    }

                    break;
                case FondyCls::ORDER_EXPIRED:
                case FondyCls::ORDER_DECLINED:
                    $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $order->id);
                    $history->addWithemail(true, ['order_name' => $order->id]);
                    $responseMsg = 'Order declined/expired';
                    break;
                default:
                    $responseMsg = "unhandled fondy order status: {$requestBody['order_status']}";
            }

            $fOrder->save();
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

        if ($orderPayments[0]->payment_method === $this->module->displayName) {
            $orderPayment = $orderPayments[0];
            $orderPayment->transaction_id = $transactionID;
            $orderPayment->save();
        }
    }
}


