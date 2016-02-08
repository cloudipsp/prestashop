<?php

require_once(dirname(__FILE__).'/fondy.php');
require_once(dirname(__FILE__).'/fondy.cls.php');
require_once _PS_CLASS_DIR_ . 'Mail.php';

class FondyResultModuleFrontController extends Fondy
{
    /**
     * @var Order
     */
    protected $_order;
    /**
     * @var Customer
     */
    protected $_customer;

    private function showError($subject)
    {
        $id_lang = intval($this->_order->id_lang);
        $to = $this->_customer->email;
        $toName = $this->_customer->firstname . ' ' . $this->_customer->lastname;

        $message = new Swift_Message('['.Configuration::get('PS_SHOP_NAME').'] '.$subject);

        $data = array(
            '{lastname}' => $this->_customer->lastname,
            '{firstname}' => $this->_customer->firstname,
            '{shop_url}' => 'http://'.Tools::getHttpHost(false, true).__PS_BASE_URI__,
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_logo}' => (file_exists(_PS_IMG_DIR_.'logo.jpg')) ? $message->attach(new Swift_Message_Image(new Swift_File(_PS_IMG_DIR_.'logo.jpg'))) : '',
            '{id_order}' => intval($this->_order->id)
        );

        Mail::Send($id_lang, 'payment_error', $subject, $data, $to, $toName);

        // redirect to error with message
        Tools::redirectLink(__PS_BASE_URI__.'modules/fondy/result-error.php?message=' . urlencode($subject));
    }

    public function postProcess()
    {
        list($orderId,) = explode(FondyCls::ORDER_SEPARATOR, $_POST['order_id']);
        $this->_order = new Order(intval($orderId));

        $this->_customer = new Customer($this->_order->id_customer);

        if ($_POST['order_status'] == FondyCls::ORDER_DECLINED) {
            $this->showError(Tools::displayError('Order declined'));
        }

        $settings = array(
            'merchant_id' => $this->getOption('merchant'),
            'secret_key' => $this->getOption('secret_key')
        );

        $isPaymentValid = FondyCls::isPaymentValid($settings, $_POST);
        if ($isPaymentValid !== true) {
            $this->showError(Tools::displayError($isPaymentValid));
        }

        if (!Validate::isLoadedObject($this->_customer)) {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        $history = new OrderHistory();
        $history->id_order = $orderId;

        $id_order_state = _PS_OS_PAYMENT_;

        $history->changeIdOrderState(intval($id_order_state), intval($orderId));
        $history->addWithemail(true, "");

        // redirect to success
        Tools::redirectLink(__PS_BASE_URI__.'modules/fondy/result-success.php');
    }
}

$result = new FondyResultModuleFrontController();
$result->postProcess();