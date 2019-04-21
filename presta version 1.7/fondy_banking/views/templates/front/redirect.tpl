{*
* 2014-2019 Fondy
*
*  @author DM
*  @copyright  2014-2019 Fondy
*  @version  1.0.0
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}

{extends "$layout"}

{block name="content"}
    {l s='Wait for redirection...' mod='fondy_banking'}
    <form id="fondy" method="post" action="{$fondy_url|escape:'htmlall'}">
        <input type="hidden" name="order_id" value="{$order_id|escape:'htmlall'}"/>
        <input type="hidden" name="merchant_id" value="{$merchant_id|escape:'htmlall'}"/>
        <input type="hidden" name="order_desc" value="{$order_desc|escape:'htmlall'}"/>
        <input type="hidden" name="amount" value="{$amount|escape:'htmlall'}"/>
        <input type="hidden" name="currency" value="{$currency|escape:'htmlall'}"/>
        <input type="hidden" name="server_callback_url" value="{$server_callback_url|escape:'htmlall'}"/>
        <input type="hidden" name="response_url" value="{$response_url|escape:'htmlall'}"/>
        <input type="hidden" name="lang" value="{$lang|escape:'htmlall'}"/>
        <input type="hidden" name="sender_email" value="{$sender_email|escape:'htmlall'}"/>
        <input type="hidden" name="default_payment_system" value="{$default_payment_system|escape:'htmlall'}"/>
        <input type="hidden" name="signature" value="{$signature|escape:'htmlall'}"/>
        <input style="outline: none;border-radius: 7px;text-decoration: none;box-sizing: content-box;width: 200px;text-transform: uppercase;padding: 0;color: #fcfcfc;border-color: #62ba46;
    background: #62ba46;border-width: 10px;border-style: solid;border-image-outset: 4px;" type="submit"
               value="{l s='Pay' mod='fondy_banking'}">
    </form>
    <script type="text/javascript">
        (function () {
            document.getElementById('fondy').submit();
        })();

    </script>
{/block}
