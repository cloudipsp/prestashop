<?php
require_once(dirname(__FILE__).'../../../fondy.php');
require_once(dirname(__FILE__).'../../../fondy.cls.php');

class FondyRedirectModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

        global $cookie, $link;

        $language = Language::getIsoById(intval($cookie->id_lang));
        $language = (!in_array($language, array('ua', 'en', 'ru'))) ? 'ru' : $language;

        $payCurrency = Context::getContext()->currency;
        $cart = $this->context->cart;

		$fondy = new Fondy();
		$total = $cart->getOrderTotal();

		$fondy->validateOrder(intval($cart->id), _PS_OS_PREPARATION_, $total, $fondy->displayName);

        $fields = array(
            'order_id' => $fondy->currentOrder . FondyCls::ORDER_SEPARATOR . time(),
            'merchant_id' => $fondy->getOption('merchant'),
            'order_desc' => 'Order id:' . $fondy->currentOrder,
            'amount' => round($total * 100),
            'currency' => $payCurrency->iso_code,
            'server_callback_url' => $link->getModuleLink('fondy', 'callback'),
            'response_url' => $link->getModuleLink('fondy', 'result'),
            'lang' => strtoupper($language),
            'sender_email' => $this->context->customer->email
        );

        $fields['signature'] = FondyCls::getSignature($fields, $fondy->getOption('secret_key'));
        $fields['fondy_url'] = FondyCls::URL;

		$this->context->smarty->assign($fields);

		$this->setTemplate('redirect.tpl');
	}
}