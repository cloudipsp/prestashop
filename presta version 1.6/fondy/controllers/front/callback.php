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
        $data = $_POST;
        if (empty($data)) {
            $fap = json_decode(Tools::file_get_contents("php://input"));
            if (empty($fap)) {
                die('Bad request');
            }
            $data = array();
            foreach ($fap as $key => $val) {
                $data[$key] = $val;
            }
        }
        try {
            if ($data['order_status'] == FondyCls::ORDER_DECLINED or
                $data['order_status'] == FondyCls::ORDER_EXPIRED) {
                list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $data['order_id']);
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));
                exit('Order declined');
            }

            $fondy = new Fondy();
            list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $data['order_id']);
            $order = new Order($orderId);
            $settings = array(
                'merchant_id' => $fondy->getOption('merchant'),
                'secret_key' => $fondy->getOption('secret_key')
            );

            $isPaymentValid = FondyCls::isPaymentValid($settings, $data);
            if ($isPaymentValid !== true) {
                exit($isPaymentValid);
            }

            if ((float)$order->total_paid != (float)($data['amount'] / 100)) {
                exit('Amount is invalid');
            }
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

            $history = new OrderHistory();
            $history->id_order = $orderId;
            $history->changeIdOrderState((int) Configuration::get('FONDY_SUCCESS_STATUS_ID', Configuration::get('PS_OS_PAYMENT')), $orderId);
            $history->addWithemail(true, array(
                'order_name' => $orderId
            ));

            exit('OK');
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}
