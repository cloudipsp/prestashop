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
            list($cartID,) = explode(FondyCls::ORDER_SEPARATOR, $requestBody['order_id']);
            $this->context->cart = new Cart((int) $cartID);

            if ($this->context->cart->OrderExists() == false){
                $total = $requestBody['amount'] / 100;
                $this->module->validateOrder((int)$cartID, _PS_OS_PREPARATION_, $total, $this->module->displayName, null, ['transaction_id' => $requestBody['payment_id']]);
            } else {
                $this->module->currentOrder = Order::getIdByCartId($cartID);
            }

            $orderId = $this->module->currentOrder;
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
                throw new Exception('State is already Paid');
            }

            if ($requestBody['order_status'] == FondyCls::ORDER_DECLINED or $requestBody['order_status'] == FondyCls::ORDER_EXPIRED) {
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));
                throw new Exception('Order declined');
            }

            $fondy = new Fondy();
            $settings = [
                'merchant_id' => $fondy->getOption('merchant'),
                'secret_key' => $fondy->getOption('secret_key')
            ];

            $isPaymentValid = FondyCls::isPaymentValid($settings, $requestBody);
            if ($isPaymentValid !== true) {
                throw new Exception($isPaymentValid);
            } else {
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int) Configuration::get('FONDY_SUCCESS_STATUS_ID'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));

                exit('OK');
            }
        } catch (Exception $e) {
            exit($e->getMessage());
        }
    }
}
