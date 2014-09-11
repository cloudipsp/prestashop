<?php
include_once (dirname(__FILE__).'/../../config/config.inc.php');
include_once (dirname(__FILE__).'/../../header.php');
require_once(dirname(__FILE__).'/oplata.php');
require_once(dirname(__FILE__).'/oplata.cls.php');

class OplataRedirectModuleFrontController
{
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
        global $cart, $cookie, $smarty;

//        $cart->id = $_GET['id_cart'];

        $customer = new Customer((int)$cart->id_customer);

        $language = Language::getIsoById(intval($cookie->id_lang));
        $language = (!in_array($language, array('ua', 'en', 'ru'))) ? 'ru' : $language;

        $currency = new Currency($cookie->id_currency);

		$oplata = new Oplata();
		$total = $cart->getOrderTotal();

        $callback = _PS_BASE_URL_.__PS_BASE_URI__.'modules/oplata/callback.php';
        $result = _PS_BASE_URL_.__PS_BASE_URI__.'modules/oplata/result.php';

		$oplata->validateOrder(intval($cart->id), _PS_OS_PREPARATION_, $total, $oplata->displayName);

        $fields = array(
            'order_id' => $oplata->currentOrder . OplataCls::ORDER_SEPARATOR . time(),
            'merchant_id' => $oplata->getOption('merchant'),
            'order_desc' => 'Order description',
            'amount' => round($total * 100),
            'currency' => $currency->iso_code,
            'server_callback_url' => $callback,
            'response_url' => $result,
            'lang' => strtoupper($language),
            'sender_email' => $customer->email,
            'delayed' => 'N'
        );

        $fields['signature'] = OplataCls::getSignature($fields, $oplata->getOption('secret_key'));
        $fields['oplata_url'] = OplataCls::URL;

		$smarty->assign($fields);

        echo $this->display('redirect.tpl');
	}

    public static function display($template)
    {
        global $smarty;
        $previousTemplate = $smarty->currentTemplate;
        $smarty->currentTemplate = substr(basename($template), 0, -4);
        $result = $smarty->fetch(dirname(__FILE__).'/'.$template);
        $smarty->currentTemplate = $previousTemplate;
        return $result;
    }
}

$redirect = new OplataRedirectModuleFrontController();
$redirect->initContent();