<?php
require_once(dirname(__FILE__).'../../../oplata.php');
require_once(dirname(__FILE__).'../../../oplata.cls.php');

class OplataRedirectModuleFrontController extends ModuleFrontController
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

		$oplata = new Oplata();
		$total = $cart->getOrderTotal();

		$oplata->validateOrder(intval($cart->id), _PS_OS_PREPARATION_, $total, $oplata->displayName);

        $fields = array(
            'order_id' => $oplata->currentOrder . OplataCls::ORDER_SEPARATOR . time(),
            'merchant_id' => $oplata->getOption('merchant'),
            'order_desc' => 'Order description',
            'amount' => round($total * 100),
            'currency' => $payCurrency->iso_code,
            'server_callback_url' => $link->getModuleLink('oplata', 'callback'),
            'response_url' => $link->getModuleLink('oplata', 'result'),
            'lang' => strtoupper($language),
            'sender_email' => $this->context->customer->email
        );

        $fields['signature'] = OplataCls::getSignature($fields, $oplata->getOption('secret_key'));
        $fields['oplata_url'] = OplataCls::URL;

		$this->context->smarty->assign($fields);

		$this->setTemplate('redirect.tpl');
	}
}