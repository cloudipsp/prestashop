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
        $data = array();
        foreach ($_POST as $key => $val) {
            $data[$key] = Tools::getValue($key);
        }
        if (empty($data)) {
            $json_callback = json_decode(Tools::file_get_contents("php://input"));
            if (empty($json_callback)) {
                exit('No request.');
            }
            foreach ($json_callback as $key => $val) {
                $data[$key] = $val;
            }
        }
        try {
            if ($data['order_status'] == FondyCls::ORDER_DECLINED or $data['order_status'] == FondyCls::ORDER_EXPIRED) {
                list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $data['order_id']);
                $history = new OrderHistory();
                $history->id_order = $orderId;
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $orderId);
                $history->addWithemail(true, array(
                    'order_name' => $orderId
                ));
                throw new Exception('Order declined');
            }

            $fondy = new Fondy();
            $settings = array(
                'merchant_id' => $fondy->getOption('merchant'),
                'secret_key' => $fondy->getOption('secret_key')
            );

            list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $data['order_id']);
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

            $isPaymentValid = FondyCls::isPaymentValid($settings, $data);
            if ($isPaymentValid !== true) {
                throw new Exception($isPaymentValid);
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
            exit($e->getMessage());
        }
    }
}
