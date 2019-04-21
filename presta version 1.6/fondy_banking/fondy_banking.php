<?php
/**
 * 2014-2019 Fondy
 *
 *  @author DM
 *  @copyright  2014-2019 Fondy
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  @version    1.0.0
 */

class fondy_banking extends PaymentModule
{
    private $settingsList = array(
        'FONDY_BANKING_MERCHANT',
        'FONDY_BANKING_SECRET_KEY',
        'FONDY_BANKING_EU_BANKS',
        'FONDY_BANKING_PL_BANKS',
        'FONDY_BANKING_REF',
    );

    /**
     * fondy_banking constructor.
     */
    public function __construct()
    {
        $this->name = 'fondy_banking';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.1';
        $this->author = 'Fondy';
        $this->_postErrors = array();

        parent::__construct();
        $this->displayName = $this->l('Fondy bank wire payments');
        $this->description = $this->l('Payment gateway supports EUR, USD, PLN, GBP, UAH, RUB and +100 other currencies.');
        $this->confirmUninstall = $this->l('Are you want to remove the module?');
    }

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install() OR !$this->registerHook('payment')) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        foreach ($this->settingsList as $val) {
            if (!Configuration::deleteByName($val)) {
                return false;
            }
        }
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getOption($name)
    {
        return Configuration::get("FONDY_BANKING_" . Tools::strtoupper($name));
    }

    /**
     * Settings
     */
    private function _displayForm()
    {
        $is_checked_pl = $this->getOption("pl_banks") ? 'checked' : '';
        $is_checked_eu = $this->getOption("eu_banks") ? 'checked' : '';
        $this->_html .=
            '<form action="' . Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />' . $this->l('Contact details') . '</legend>
				<table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
					<tr><td colspan="2">' . $this->l('Please specify the Fondy account details for customers') . '.<br /><br /></td></tr>

					<tr>
						<td width="130" style="height: 35px;">' . $this->l('Merchant') . '</td>
						<td><input type="text" name="merchant" value="' . $this->getOption("merchant") . '" style="width: 300px;" /></td>
					</tr>
					<tr>
						<td width="130" style="height: 35px;">' . $this->l('Secret key') . '</td>
						<td><input type="text" name="secret_key" value="' . $this->getOption("secret_key") . '" style="width: 300px;" /></td>
					</tr>
					<tr>
						<td width="130" style="height: 35px;">' . $this->l('Enable EU Banklinks') . '</td>
						<td><input type="checkbox" name="eu_banks" ' . $is_checked_eu . ' style="width: 10px;" /></td>
					</tr>
					<tr>
						<td width="130" style="height: 35px;">' . $this->l('Enable PL Banklinks') . '</td>
						<td><input type="checkbox" name="pl_banks" ' . $is_checked_pl . ' style="width: 10px;" /></td>
					</tr>
					<tr><td colspan="2" align="center"><input class="button" name="btnSubmit" value="' . $this->l('Update settings') . '" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>';
    }

    private function _displayFondy()
    {
        $this->_html .= '<img src="../modules/fondy_banking/views/img/logo.png" style="float:left; margin-right:15px;"><b>' .
            $this->l('This module allows you to accept payments by Fondy.') . '</b><br /><br />' .
            $this->l('If the client chooses this payment mode, the order will change its status into a \'Waiting for payment\' status.') .
            '<br /><br /><br />';
    }

    public function getContent()
    {
        $this->_html = '<h2>' . $this->displayName . '</h2>';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!sizeof($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors AS $err) {
                    $this->_html .= '<div class="alert error">' . $err . '</div>';
                }
            }
        } else {
            $this->_html .= '<br />';
        }
        $this->_displayFondy();
        $this->_displayForm();
        return $this->_html;
    }


    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            /*$this->_postErrors[] = $this->l('Account details are required.');*/
        }
    }

    /**
     * postProcess
     */
    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('FONDY_BANKING_MERCHANT', Tools::getValue('merchant'));
            Configuration::updateValue('FONDY_BANKING_SECRET_KEY', Tools::getValue('secret_key'));
            Configuration::updateValue('FONDY_BANKING_PL_BANKS', Tools::getValue('pl_banks'));
            Configuration::updateValue('FONDY_BANKING_EU_BANKS', Tools::getValue('eu_banks'));
        }
        $this->_html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="' . $this->l('ok') . '" /> ' . $this->l('Settings updated') . '</div>';
    }

    /**
     * @param $params
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->_checkCurrency($params['cart'])) {
            return;
        }

        $data = array(
            'this_path' => $this->_path,
            'id' => (int)$params['cart']->id,
            'this_path_ssl' => Tools::getShopDomainSsl(true,
                    true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'this_description' => $this->l('Bank wire payment.'),
            'this_description_pl' => $this->l('Fondy PL banklinks.'),
            'pl_banks_enabled' => false
        );

        if ($this->getOption("pl_banks")) {
            $data['pl_banks_enabled'] = true;
        }

        if ($this->getOption("eu_banks")) {
            $data['eu_banks_enabled'] = true;
        }

        $this->context->smarty->assign($data);

        return $this->display(__FILE__, 'views/templates/front/fondy_banking.tpl');
    }

    /**
     * @param $cart
     * @return bool
     */
    private function _checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module AS $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
}
