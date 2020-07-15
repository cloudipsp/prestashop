<?php
include_once (dirname(__FILE__).'/../../config/config.inc.php');
include_once (dirname(__FILE__).'/../../header.php');
require_once(dirname(__FILE__).'/fondy.php');
require_once(dirname(__FILE__).'/fondy.cls.php');

class FondyRedirectModuleFrontController
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
        $language = (!in_array($language, array('uk', 'en', 'ru', 'lv', 'fr'))) ? '' : $language;

        $currency = new Currency($cookie->id_currency);

		$fondy = new Fondy();
		$total = $cart->getOrderTotal();

        $callback = _PS_BASE_URL_.__PS_BASE_URI__.'modules/fondy/callback.php';
        $result = _PS_BASE_URL_.__PS_BASE_URI__.'modules/fondy/result.php';

		$fondy->validateOrder(intval($cart->id), _PS_OS_PREPARATION_, $total, $fondy->displayName);

        $fields = array(
            'order_id' => $fondy->currentOrder . FondyCls::ORDER_SEPARATOR . time(),
            'merchant_id' => $fondy->getOption('merchant'),
            'order_desc' => $fondy->currentOrder,
            'amount' => round($total * 100),
            'currency' => $currency->iso_code,
            'server_callback_url' => $callback,
            'response_url' => $result,
            'sender_email' => $customer->email,
            'delayed' => 'N'
        );
		if ($language !== '')
            $fields['lang'] = strtolower($language);
        $fields['signature'] = FondyCls::getSignature($fields, $fondy->getOption('secret_key'));
        $fields['fondy_url'] = FondyCls::URL;

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

$redirect = new FondyRedirectModuleFrontController();
$redirect->initContent();