<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM
 * @copyright  2014-2019 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.0.0
 */

class Fondy extends PaymentModule
{
    private $settingsList = array(
        'FONDY_MERCHANT',
        'FONDY_SECRET_KEY',
        'FONDY_FORM_METHOD',
        'FONDY_BACK_REF'
    );

    public function __construct()
    {
        $this->name = 'fondy';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Fondy';
        $this->_postErrors = array();

        parent::__construct();
        $this->displayName = $this->l('Платежи Fondy');
        $this->description = $this->l('Оплата через Fondy');
        $this->confirmUninstall = $this->l('Действительно хотите удалить модуль?');
    }

    public function install()
    {
        if (!parent::install() or !$this->registerHook('payment')) {
            return false;
        }
        return true;
    }

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

    public function getOption($name)
    {
        return Configuration::get("FONDY_" . Tools::strtoupper($name));
    }

    private function _displayForm()
    {
        $checked = '';
        if ($this->getOption("form_method")) {
            $checked = 'checked';
        }
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
						<td width="130" style="height: 35px;">' . $this->l('Form method') . '</td>
						<td>
						    <input type="checkbox" ' . $checked . ' name="form_method" />
						</td>
					</tr>
					<tr>
					    <td colspan="2">
					        <input class="button" name="btnSubmit" value="' . $this->l('Update settings') . '" type="submit" />
					    </td>
					</tr>
				</table>
			</fieldset>
		</form>';
    }

    private function _displayFondy()
    {
        $this->_html .= '<img src="../modules/fondy/views/img/logo.png" style="float:left; margin-right:15px;"><b>' .
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
                foreach ($this->_postErrors as $err) {
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

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('FONDY_MERCHANT', Tools::getValue('merchant'));
            Configuration::updateValue('FONDY_SECRET_KEY', Tools::getValue('secret_key'));
            Configuration::updateValue('FONDY_FORM_METHOD', Tools::getIsset('form_method'));
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

        $this->context->smarty->assign(array(
            'this_path' => $this->_path,
            'id' => (int)$params['cart']->id,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'this_description' => $this->l('Pay via Fondy')
        ));

        return $this->display(__FILE__, 'views/templates/front/fondy.tpl');
    }

    private function _checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
}
