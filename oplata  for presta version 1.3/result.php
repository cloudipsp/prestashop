<?php

require_once(dirname(__FILE__).'/oplata.php');
require_once(dirname(__FILE__).'/oplata.cls.php');

class OplataResultModuleFrontController extends Oplata
{
    private function showError($error)
    {
        global $smarty;

        $smarty->assign('message', $error);

        // Display all and exit
        include(_PS_ROOT_DIR_.'/header.php');
        echo $this->display(__FILE__, 'error.tpl');
        include(_PS_ROOT_DIR_.'/footer.php');
        die ;
    }

    private function showSuccess($message)
    {
        global $smarty;

        $smarty->assign('message', $message);

        // Display all and exit
        include(_PS_ROOT_DIR_.'/header.php');
        echo $this->display(__FILE__, 'success.tpl');
        include(_PS_ROOT_DIR_.'/footer.php');
        die ;
    }

    public function postProcess()
    {
        if ($_POST['order_status'] == OplataCls::ORDER_DECLINED) {
            $this->showError(Tools::displayError('Order declined'));
        }

        $settings = array(
            'merchant_id' => $this->getOption('merchant'),
            'secret_key' => $this->getOption('secret_key')
        );

        $isPaymentValid = OplataCls::isPaymentValid($settings, $_POST);
        if ($isPaymentValid !== true) {
            $this->showError(Tools::displayError($isPaymentValid));
        }

        list($orderId,) = explode(OplataCls::ORDER_SEPARATOR, $_POST['order_id']);
        $order = new Order(intval($orderId));

        $customer = new Customer($order->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        $history = new OrderHistory();
        $history->id_order = $orderId;

        $id_order_state = _PS_OS_PAYMENT_;

        $history->changeIdOrderState(intval($id_order_state), intval($orderId));
        $history->addWithemail(true, "");

        $this->showSuccess('Payment was successful');
    }
}

$result = new OplataResultModuleFrontController();
$result->postProcess();