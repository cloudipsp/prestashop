<?php
/**
 * 2014-2019 Fondy
 *
 *  @author DM
 *  @copyright  2014-2019 Fondy
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @version    1.0.0
 */

require_once(dirname(__FILE__) . '../../../fondy_banking.php');
require_once(dirname(__FILE__) . '../../../fondy.cls.php');

if (!defined('_PS_VERSION_')) {
    exit;
}

class fondy_bankingRedirectModuleFrontController extends ModuleFrontController
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

        $payCurrency = Context::getContext()->currency;
        $cart = $this->context->cart;

        $pl_system = Tools::getValue('pl_banks_enabled');

        $fondy = $this->module;

        $total = $cart->getOrderTotal();

        $fondy->validateOrder((int)$cart->id, _PS_OS_PREPARATION_, $total, $fondy->displayName);

        $fields = array(
            'order_id' => $fondy->currentOrder . FondyCls::ORDER_SEPARATOR . time(),
            'merchant_id' => $fondy->getOption('merchant'),
            'order_desc' => $this->l('Order pay №') . $fondy->currentOrder,
            'amount' => round($total * 100),
            'currency' => $payCurrency->iso_code,
            'server_callback_url' => $link->getModuleLink('fondy_banking', 'callback'),
            'response_url' => $link->getModuleLink('fondy_banking', 'result'),
            'sender_email' => $this->context->customer->email,
            'default_payment_system' => 'banklinks_eu'
        );
        if ($pl_system and $fondy->getOption('pl_banks')) {
            $fields['default_payment_system'] = 'banklinks_pl';
        }
        if ($language !== '') {
            $fields['lang'] = Tools::strtolower($language);
        }

        $fields['signature'] = FondyCls::getSignature($fields, $fondy->getOption('secret_key'));
        $fields['fondy_url'] = FondyCls::URL;

        $this->context->smarty->assign($fields);

        $this->setTemplate('module:' . $this->module->name . '/views/templates/front/redirect.tpl');
    }
}
