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

class FondyRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $cookie = $this->context->cookie;
        $link = $this->context->link;

        $language = Language::getIsoById((int)$cookie->id_lang);
        $language = (!in_array($language, array('uk', 'en', 'ru', 'lv', 'fr'))) ? '' : $language;

        $payCurrency = $this->context->currency;
        $cart = $this->context->cart;

        $fondy = $this->module;
        $total = $cart->getOrderTotal();

        $fondy->validateOrder((int)$cart->id, _PS_OS_PREPARATION_, $total, $fondy->displayName);

        $fields = array(
            'order_id' => $fondy->currentOrder . FondyCls::ORDER_SEPARATOR . time(),
            'merchant_id' => $fondy->getOption('merchant'),
            'order_desc' => '#' . $fondy->currentOrder,
            'amount' => round($total * 100),
            'currency' => $payCurrency->iso_code,
            'server_callback_url' => $link->getModuleLink('fondy', 'callback'),
            'response_url' => $link->getModuleLink('fondy', 'result'),
            'sender_email' => $this->context->customer->email ? $this->context->customer->email : ''
        );
        if ($this->context->customer and !$fondy->getOption('form_method')) {
            $fields['merchant_data'] = json_encode(
                array(
                    'birthday' => $this->context->customer->birthday ? $this->context->customer->birthday : '',
                    'firstname' => $this->context->customer->firstname ? $this->context->customer->firstname : '',
                    'lastname' => $this->context->customer->lastname ? $this->context->customer->lastname : '',
                )
            );
        }
        if ($language !== '') {
            $fields['lang'] = Tools::strtolower($language);
        }

        $fields['signature'] = FondyCls::getSignature($fields, $fondy->getOption('secret_key'));

        if (!$fondy->getOption('form_method')) {
            $checkoutUrl = $this->generateFondyUrl($fields);
            if ($checkoutUrl['result']) {
                Tools::redirect($checkoutUrl['url']);
            } else {
                die($checkoutUrl['message']);
            }
        } else {
            $fields['fondy_url'] = FondyCls::URL;
            $this->context->smarty->assign($fields);
            $this->setTemplate('redirect.tpl');
        }
    }

    public function generateFondyUrl($payment_oplata_args)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.fondy.eu/api/checkout/url/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('request' => $payment_oplata_args)));
        $result = json_decode(curl_exec($ch));
        if ($result->response->response_status == 'failure') {
            $out = array(
                'result' => false,
                'message' => $result->response->error_message
            );
        } else {
            $out = array(
                'result' => true,
                'url' => $result->response->checkout_url
            );
        }
        return $out;
    }
}
