<?php
/**
 * 2014-2019 Fondy
 *
 * @author DM
 * @copyright  2014-2019 Fondy
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version    1.0.0
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class fondy_banking extends PaymentModule
{
    private $settingsList = array(
        'FONDY_BANKING_MERCHANT',
        'FONDY_BANKING_KEY',
        'FONDY_BANKING_EU_BANKS',
        'FONDY_BANKING_PL_BANKS',
        'FONDY_BANKING_REF',
    );
    private $_html = '';
    private $postErrors = array();

    public function __construct()
    {
        $this->name = 'fondy_banking';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Fondy';

        parent::__construct();

        $this->displayName = $this->l('Fondy bank wire payments');
        $this->description = $this->l('Payment gateway supports EUR, USD, PLN, GBP, UAH, RUB and +100 other currencies.');
        $this->confirmUninstall = $this->l('Are you want to remove the module?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions');
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
        return Configuration::get("FONDY_BANKING_" . Tools::strtoupper($name));
    }

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
						<td width="130" style="height: 35px;">' . $this->l('Merchant ID') . '</td>
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
        $this->_html .= '<img src="../modules/fondy_banking/logo.png" style="float:left; margin-right:15px;"><b>' .
            $this->l('This module allows you to accept payments by Fondy.') . '</b><br /><br />' .
            $this->l('If the client chooses this payment mode, the order will change its status into a \'Waiting for payment\' status.') .
            '<br /><br /><br />';
    }

    public function getContent()
    {
        $this->_html = '<h2>' . $this->displayName . '</h2>';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!sizeof($this->postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->_html .= '<div class="bootstrap">
										<div class="module_error alert alert-danger">
										<button type="button" class="close" data-dismiss="alert">×</button>' . $err . '</div></div>';
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
            if (empty(Tools::getValue('merchant'))) {
                $this->postErrors[] = $this->l('Merchant ID is required.');
            }
            if (empty(Tools::getValue('secret_key'))) {
                $this->postErrors[] = $this->l('Secret key is required.');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('FONDY_BANKING_MERCHANT', Tools::getValue('merchant'));
            Configuration::updateValue('FONDY_BANKING_SECRET_KEY', Tools::getValue('secret_key'));
            Configuration::updateValue('FONDY_BANKING_PL_BANKS', Tools::getValue('pl_banks'));
            Configuration::updateValue('FONDY_BANKING_EU_BANKS', Tools::getValue('eu_banks'));
        }
        $updated = $this->l('Settings Updated');
        $this->_html .= '<div class="bootstrap">
        <div class="module_confirmation conf confirm alert alert-success">
            <button type="button" class="close" data-dismiss="alert">×</button>
            ' . $updated . '
        </div>
        </div>';
    }

    # Display

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return false;
        }
        if (!$this->_checkCurrency($params['cart'])) {
            return false;
        }

        $data = array(
            'this_path' => $this->_path,
            'id' => (int)$params['cart']->id,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
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

        $newOption = null;
        $newOption2 = null;

        if ($this->getOption("eu_banks")) {
            $newOption = new PaymentOption();
            $newOption->setModuleName($this->name)
                ->setCallToActionText($this->l('Pay by bank wire'))
                ->setAction(
                    $this->context->link->getModuleLink(
                        $this->name,
                        'redirect',
                        array('id_cart' => (int)$params['cart']->id),
                        true
                    )
                )
                ->setAdditionalInformation($this->context->smarty->fetch('module:fondy_banking/views/templates/front/fondy_banking.tpl'));
        }
        if ($this->getOption("pl_banks")) {
            $newOption2 = new PaymentOption();
            $newOption2->setModuleName($this->name)
                ->setCallToActionText($this->l('Fondy PL banklinks'))
                ->setAction(
                    $this->context->link->getModuleLink(
                        $this->name,
                        'redirect',
                        array('id_cart' => (int)$params['cart']->id, 'pl_banks_enabled' => true),
                        true
                    )
                )
                ->setAdditionalInformation($this->context->smarty->fetch('module:fondy_banking/views/templates/front/fondy_banking_pl.tpl'));
        }
        return array($newOption, $newOption2);
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
